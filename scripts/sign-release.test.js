/**
 * SPDX-FileCopyrightText: 2026 Mikael Nordin <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { Buffer } from 'node:buffer'
import { spawnSync } from 'node:child_process'
import { generateKeyPairSync, verify } from 'node:crypto'
import { mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'

const projectDir = dirname(dirname(fileURLToPath(import.meta.url)))
const helper = join(projectDir, 'scripts', 'sign-release.sh')
const image = 'nextcloud:35.0.1-apache'
const { privateKey, publicKey } = generateKeyPairSync('rsa', {
  modulusLength: 2048,
  privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
  publicKeyEncoding: { type: 'spki', format: 'pem' },
})

let root
let archive
let certificateDir
let dockerLog
let env
let originalArchive

function runHelper(mode = 'success', appId = 'edusign') {
  return spawnSync('bash', [helper, archive, appId, certificateDir, image], {
    env: { ...env, SIGN_TEST_MODE: mode },
    encoding: 'utf8',
  })
}

function expectUnchangedArchive(result) {
  expect(result.status).not.toBe(0)
  expect(result.stdout).not.toContain('App Store archive signature:')
  expect(readFileSync(archive)).toEqual(originalArchive)
  expect(readdirSync(dirname(archive))).toEqual(['edusign-0.0.12.tar.gz'])
}

describe('release signing', () => {
  beforeEach(() => {
    root = mkdtempSync(join(tmpdir(), 'edusign-signing-'))
    const binaryDir = join(root, 'bin')
    const appDir = join(root, 'source', 'edusign', 'appinfo')
    const archiveDir = join(root, 'release artifacts')
    certificateDir = join(root, 'private certificates')
    dockerLog = join(root, 'docker.json')
    archive = join(archiveDir, 'edusign-0.0.12.tar.gz')
    for (const directory of [binaryDir, appDir, archiveDir, certificateDir]) {
      mkdirSync(directory, { recursive: true })
    }
    writeFileSync(join(appDir, 'info.xml'), '<info><id>edusign</id></info>')
    writeFileSync(join(appDir, 'signature.json'), '{"stale":true}')
    writeFileSync(join(certificateDir, 'edusign.crt'), 'Test certificate')
    writeFileSync(join(certificateDir, 'edusign.key'), privateKey, { mode: 0o600 })
    const packageResult = spawnSync('tar', ['-czf', archive, '-C', join(root, 'source'), 'edusign'])
    expect(packageResult.status).toBe(0)
    originalArchive = readFileSync(archive)

    writeFileSync(join(binaryDir, 'docker'), `#!/usr/bin/env node
const fs = require('node:fs')
const path = require('node:path')
const args = process.argv.slice(2)
fs.writeFileSync(process.env.SIGN_TEST_DOCKER_LOG, JSON.stringify(args))
if (args[0] !== 'run') process.exit(99)
if (process.env.SIGN_TEST_MODE === 'failure') process.exit(17)
if (process.env.SIGN_TEST_MODE === 'missing-signature') process.exit(0)
const mount = args.find(value => value.startsWith('type=bind,source=') && value.endsWith(',target=/app'))
const appDir = mount.slice('type=bind,source='.length, -',target=/app'.length)
fs.writeFileSync(path.join(appDir, 'appinfo', 'signature.json'), JSON.stringify({ signed: true }))
`, { mode: 0o755 })

    env = {
      ...process.env,
      PATH: `${binaryDir}:${process.env.PATH}`,
      SIGN_TEST_DOCKER_LOG: dockerLog,
    }
  })

  afterEach(() => {
    rmSync(root, { recursive: true, force: true })
  })

  it('signs with an isolated foreground container and prints a valid archive signature', () => {
    const result = runHelper()
    expect(result.status, result.stderr).toBe(0)
    const args = JSON.parse(readFileSync(dockerLog, 'utf8'))
    expect(args.slice(0, 7)).toEqual(['run', '--rm', '--network', 'none', '--cap-drop', 'ALL', '--security-opt'])
    expect(args).toContain('no-new-privileges')
    expect(args[args.indexOf('--user') + 1]).toBe(`${process.getuid()}:${process.getgid()}`)
    expect(args[args.indexOf('--entrypoint') + 1]).toBe('/bin/sh')
    expect(args[args.indexOf('--workdir') + 1]).toBe('/usr/src/nextcloud')
    expect(args).toContain('NEXTCLOUD_CONFIG_DIR=/tmp/nextcloud-sign-config')
    expect(args).toContain(image)
    expect(args).not.toContain('--detach')
    expect(args).not.toContain('--name')
    expect(args).toContain(`type=bind,source=${certificateDir}/edusign.crt,target=/certificates/app.crt,readonly`)
    expect(args).toContain(`type=bind,source=${certificateDir}/edusign.key,target=/certificates/app.key,readonly`)
    const containerCommand = args[args.indexOf('-ec') + 1]
    expect(containerCommand).toContain('exec php /usr/src/nextcloud/occ integrity:sign-app')
    expect(containerCommand).not.toContain('/var/www/html')
    expect(spawnSync('sh', ['-n', '-c', containerCommand]).status).toBe(0)

    const signature = result.stdout.match(/App Store archive signature:\n([A-Za-z0-9+/=]+)/)[1]
    expect(verify('RSA-SHA512', readFileSync(archive), publicKey, Buffer.from(signature, 'base64'))).toBe(true)
    const appSignature = spawnSync('tar', ['-xOzf', archive, 'edusign/appinfo/signature.json'], { encoding: 'utf8' })
    expect(appSignature.status).toBe(0)
    expect(JSON.parse(appSignature.stdout)).toEqual({ signed: true })
    expect(statSync(join(certificateDir, 'edusign.key')).mode & 0o777).toBe(0o600)
    expect(readFileSync(join(certificateDir, 'edusign.key'), 'utf8')).toBe(privateKey)
    expect(readdirSync(dirname(archive))).toEqual(['edusign-0.0.12.tar.gz'])
  })

  it('preserves the input archive and cleans staging when Docker fails', () => {
    const result = runHelper('failure')
    expect(result.status).toBe(17)
    expectUnchangedArchive(result)
  })

  it('rejects a successful Docker exit without a new app signature', () => {
    const result = runHelper('missing-signature')
    expect(result.stderr).toContain('Nextcloud did not produce an app signature')
    expectUnchangedArchive(result)
  })

  it('preserves the input archive when archive signing fails', () => {
    writeFileSync(join(certificateDir, 'edusign.key'), 'Not a private key')
    expectUnchangedArchive(runHelper())
  })

  it('fails before starting Docker when a certificate is missing', () => {
    rmSync(join(certificateDir, 'edusign.crt'))
    const result = runHelper()
    expect(result.stderr).toContain('Certificate is not readable')
    expectUnchangedArchive(result)
    expect(readdirSync(root)).not.toContain('docker.json')
  })

  it('rejects an app ID containing a path', () => {
    const result = runHelper('success', '../edusign')
    expect(result.stderr).toContain('Invalid app ID')
    expectUnchangedArchive(result)
    expect(readdirSync(root)).not.toContain('docker.json')
  })

  it('runs make appstore through the helper without killing or naming containers', () => {
    const result = spawnSync('make', [
      '--no-print-directory',
      '-o',
      'package',
      'appstore',
      'version=0.0.12',
      `build_dir=${dirname(archive)}`,
      `cert_dir=${certificateDir}`,
      `sign_image=${image}`,
    ], { cwd: projectDir, env, encoding: 'utf8' })
    expect(result.status, result.stderr).toBe(0)
    expect(result.stdout).toContain('App Store archive signature:')
    expect(JSON.parse(readFileSync(dockerLog, 'utf8'))[0]).toBe('run')
  })
})
