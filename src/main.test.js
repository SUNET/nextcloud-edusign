/**
 * SPDX-FileCopyrightText: 2026 Mikael Nordin <kano@sunet.se>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { axios, registerFileAction } = vi.hoisted(() => ({
  axios: { get: vi.fn(), post: vi.fn() },
  registerFileAction: vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({ default: axios }))
vi.mock('@nextcloud/files', () => ({ registerFileAction }))
vi.mock('@nextcloud/l10n', () => ({ t: (app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const configuration = {
  assurance: ['https://example.org/assurance'],
  authn_context: 'https://example.org/mfa',
  edusign_endpoint: 'https://example.org/api/v1',
  idp: 'https://example.org/idp',
  organization: 'Example',
  registration_authority: 'https://example.org/',
  saml_attr_schema: '20',
  api_key_configured: true,
}

async function loadAction() {
  await import('./main.js')
  await vi.waitFor(() => expect(registerFileAction).toHaveBeenCalledOnce())
  return registerFileAction.mock.calls[0][0]
}

describe('Nextcloud 33+ signing action', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    vi.stubGlobal('OCA', {})
    vi.stubGlobal('window', { location: { href: 'https://cloud.example.org/apps/files/' } })
    axios.get.mockResolvedValue({ data: configuration })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('registers a plain action using the current Files API', async () => {
    const action = await loadAction()
    expect(registerFileAction).toHaveBeenCalledOnce()
    expect(action.id).toBe('edusign-sign')
    expect(action.displayName()).toBe('Sign with Edusign')
    expect(Object.getPrototypeOf(action)).toBe(Object.prototype)
    expect(axios.get).toHaveBeenCalledWith('/apps/edusign/query')
  })

  it.each(['application/pdf', 'application/xml'])('enables a single %s file', async (mime) => {
    const action = await loadAction()
    expect(action.enabled({ nodes: [{ mime }] })).toBe(true)
  })

  it('rejects unsupported files and empty or multiple selections', async () => {
    const action = await loadAction()
    expect(action.enabled({ nodes: [{ mime: 'text/plain' }] })).toBe(false)
    expect(action.enabled({ nodes: [] })).toBe(false)
    expect(action.enabled({ nodes: [{ mime: 'application/pdf' }, { mime: 'application/pdf' }] })).toBe(false)
  })

  it('waits to register until configuration is loaded', async () => {
    let resolve
    axios.get.mockReturnValue(new Promise((done) => {
      resolve = done
    }))
    await import('./main.js')
    expect(registerFileAction).not.toHaveBeenCalled()
    resolve({ data: configuration })
    await vi.waitFor(() => expect(registerFileAction).toHaveBeenCalledOnce())
    const action = registerFileAction.mock.calls[0][0]
    expect(action.enabled({ nodes: [{ mime: 'application/pdf' }] })).toBe(true)
  })

  it('does not register for incomplete configuration', async () => {
    axios.get.mockResolvedValue({ data: { ...configuration, idp: '' } })
    await import('./main.js')
    await vi.waitFor(() => expect(OCA.Edusign.configured).toBe(false))
    expect(registerFileAction).not.toHaveBeenCalled()
  })

  it('does not register when loading configuration fails', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    axios.get.mockRejectedValue(new Error('Unavailable'))
    await import('./main.js')
    await vi.waitFor(() => expect(console.error).toHaveBeenCalledOnce())
    expect(registerFileAction).not.toHaveBeenCalled()
  })

  it('does not treat an HTML login response as configuration', async () => {
    axios.get.mockResolvedValue({ data: '<!doctype html><title>Login</title>' })
    await import('./main.js')
    await vi.waitFor(() => expect(OCA.Edusign.configured).toBe(false))
    expect(registerFileAction).not.toHaveBeenCalled()
  })

  it('does not register when assurance has no value', async () => {
    axios.get.mockResolvedValue({ data: { ...configuration, assurance: [''] } })
    await import('./main.js')
    await vi.waitFor(() => expect(OCA.Edusign.configured).toBe(false))
    expect(registerFileAction).not.toHaveBeenCalled()
  })

  it('uses the selected node from the new execution context and submits the signing form', async () => {
    const form = { setAttribute: vi.fn(), appendChild: vi.fn(), submit: vi.fn() }
    const fields = []
    vi.stubGlobal('document', {
      body: { appendChild: vi.fn() },
      createElement: vi.fn((tag) => {
        if (tag === 'form') {
          return form
        }
        const field = { setAttribute: vi.fn() }
        fields.push(field)
        return field
      }),
    })
    axios.post.mockResolvedValue({
      data: {
        payload: {
          binding: 'POST',
          relay_state: 'relay',
          sign_request: 'signed-request',
          destination_url: 'https://sign.example.org/',
        },
      },
    })
    const action = await loadAction()
    expect(await action.exec({ nodes: [{ path: '/Documents/file.pdf' }] })).toBeNull()
    expect(axios.post).toHaveBeenCalledWith('/apps/edusign/request', {
      path: '/Documents/file.pdf',
      redirect_uri: window.location.href,
    })
    expect(form.setAttribute).toHaveBeenCalledWith('method', 'post')
    expect(form.setAttribute).toHaveBeenCalledWith('action', 'https://sign.example.org/')
    expect(fields).toHaveLength(3)
    for (const [index, [name, value]] of Object.entries({
      Binding: 'POST',
      RelayState: 'relay',
      EidSignRequest: 'signed-request',
    }).entries()) {
      expect(fields[index].setAttribute).toHaveBeenCalledWith('type', 'hidden')
      expect(fields[index].setAttribute).toHaveBeenCalledWith('name', name)
      expect(fields[index].setAttribute).toHaveBeenCalledWith('value', value)
    }
    expect(document.body.appendChild).toHaveBeenCalledWith(form)
    expect(form.submit).toHaveBeenCalledOnce()
  })

  it('does not submit a form when no signing payload is returned', async () => {
    vi.spyOn(console, 'log').mockImplementation(() => {})
    vi.stubGlobal('document', { createElement: vi.fn() })
    axios.post.mockResolvedValue({ data: {} })
    const action = await loadAction()
    expect(await action.exec({ nodes: [{ path: '/file.pdf' }] })).toBeNull()
    expect(document.createElement).not.toHaveBeenCalled()
  })
})
