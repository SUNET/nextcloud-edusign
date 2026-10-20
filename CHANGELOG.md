# Changelog

## 0.0.14

- Add secure API-key configuration and use it for eduSign API calls.

## 0.0.13

- Fix the signing file action not appearing when configuration loads after the Files app evaluates available actions.
- Load the signing bundle only in the Files app instead of on login and other unrelated pages.
- Reject incomplete configuration and unexpected login-page responses before registering the action.

## 0.0.12

- Support Nextcloud 33–35; Nextcloud 32 users should stay on 0.0.11.
- Migrate the signing file action to the Nextcloud Files v4 registration and context API.
- Update frontend libraries, Vue, PHP dependencies, and lint/build tooling.
- Load the packaged PHP dependencies through Nextcloud's `composer/` convention.
- Use explicit translation and Vue component imports in the admin settings bundle.
- Pin Node.js 24 LTS and npm 12.2 for builds, restrict dependency install scripts, use lockfile-based installs, and run frontend tests and builds in CI.
- Package only runtime app files and production PHP dependencies; preserve existing release archives.
- Sign releases in an isolated foreground container without startup delays or stopping existing containers; replace the archive only after signing succeeds.
