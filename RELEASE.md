# Release 0.0.15

## Compatibility and dependency choices

- Supported Nextcloud versions: 33–35. Version 0.0.11 remains the release for Nextcloud 32.
- Build with Node.js 24 LTS (24.15 or newer, selected by `.nvmrc`) and npm 12.2. CI installs npm 12.2.0 explicitly.
- Install scripts are denied by default under npm 12. Only the reviewed `@nextcloud/webpack-vue-config@7.0.4` compatibility check is approved; Parcel uses a prebuilt watcher and the `core-js` funding script stays denied.
- Guzzle stays on the current 7.x branch for Nextcloud integration; 7.15.5 and its updated dependencies pass Composer audit.
- `@nextcloud/babel-config` stays on 1.3.0 because the current Webpack configuration requires Babel 7; the 2.x configuration requires Babel 8.
- `stylelint-config-html` stays on 1.1.0 to satisfy the HTML parser peer dependencies of Nextcloud's current Vue lint configuration.

## Outstanding development-tool advisories

As checked on 2026-10-11, the full npm audit reports 20 affected packages
(15 high, 5 low), traced to two upstream advisories with no patched releases:

- [`braces`, GHSA-vfj7-8cjw-p6xm](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm): stack-exhaustion denial of service from deeply nested glob patterns, used by build/lint tooling.
- [`elliptic`, GHSA-848j-6mx2-7j84](https://github.com/advisories/GHSA-848j-6mx2-7j84): risky cryptographic implementation, pulled in by Webpack's Node polyfill tooling.

`npm audit --omit=dev` and `composer audit` report no advisories. The built
JavaScript source maps were also checked: neither vulnerable package is bundled.
No audit findings are suppressed, and no forced tooling downgrade or peer-dependency
bypass is used. Do not feed untrusted glob patterns to the tooling. These
development-tool findings require maintainer review before publication; re-run
the audits before signing to check for upstream fixes.

## Verification before signing

A clean install, lint checks, 18 frontend tests, and packaging were verified
with Node.js 24.21.0 and npm 12.2.0. The approved Webpack compatibility check ran,
no unreviewed install scripts remained, and Parcel's prebuilt watcher loaded with
source compilation denied.

```sh
composer validate --strict
composer run cs:check
npm run lint
npm run stylelint
npm test
composer audit
npm audit --omit=dev
make package
```

Install the archive on disposable Nextcloud 33 and 35 instances and verify:

1. `occ app:enable edusign` succeeds, including the signing-request migration.
2. Admin settings load, save, and remove configuration.
3. A configured user sees “Sign with Edusign” for a single PDF or XML file.
4. A signing request returns through eduSign and writes the signed document back.
5. `occ edusign:cleanup` runs successfully.

Local preparation verified enablement, migration, service construction,
configuration reads, signing-request DB operations, and cleanup on Nextcloud
33.0.0 and 35.0.1 with PHP 8.4 and SQLite. Frontend regression tests cover
registration, eligibility, configuration failure/loading, and signing-form
submission. Browser checks and a live eduSign round trip remain to be completed
on a configured instance. Webpack still emits size warnings for the settings
bundle and its lazy-loaded components.

## Host-side signing and publication

After reviewing the development-tool advisories and completing the live smoke
test, use the signing target on the host:

```sh
make sign
```

This rebuilds the archive, signs it using `~/.nextcloud/certificates/edusign.crt`
and `edusign.key`, and prints the App Store archive signature. `make appstore`
runs the same signing target; it does not upload or publish anything.

Signing uses a foreground, automatically removed `nextcloud:35.0.1-apache`
container with networking disabled, the host UID/GID, and read-only certificate
and key mounts. It runs `occ` from the image's complete `/usr/src/nextcloud` tree
with a temporary CLI configuration, so there is no web-root initialization race
or fixed startup delay. Existing containers, including one named `nextcloud`,
are left untouched. Only a temporary extracted copy of the app is mounted
writable; the archive is replaced after both signing steps succeed. The notice
that Nextcloud is not installed is expected for this CLI-only signing setup.

Override the certificate directory or reviewed image when needed:

```sh
make appstore cert_dir=/path/to/certificates sign_image=nextcloud:35.0.1-apache
```

Docker is not available in the preparation box. The signing helper is tested
with seven regression tests using a simulated Docker command and disposable
keys. Real CLI signing with empty configurations also passed on the disposable
Nextcloud 33 and 35 trees: all 216 file hashes and both app signatures were
verified cryptographically. An actual image run and signing with the release
certificate must be verified on the host.

Commit the prepared files, tag the reviewed commit as `v0.0.15`, publish the
signed archive with the changelog, and submit the release to the Nextcloud App
Store. Do not publish the unsigned preparation archive. No commit, tag,
signing, upload, or publication is performed during local preparation.
