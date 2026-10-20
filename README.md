# nextcloud-edusign

## Compatibility

Version 0.0.14 supports Nextcloud 33–35. For Nextcloud 32, use 0.0.11.

## Development and release

Use Node.js 24 LTS (24.15 or newer) and npm 12.2. Install Composer
dependencies into `composer/`, the directory automatically loaded by Nextcloud.

With nvm installed, select the supported toolchain from this directory:

```sh
nvm install
nvm use
npm install --global npm@12.2.0
```

The npm 12 install-script policy approves only the reviewed Webpack Vue-loader
compatibility check at version 7.0.4. The `core-js` funding banner and Parcel's
source-build script are denied; Parcel uses its platform-specific prebuilt binary.
Keep optional dependencies enabled. If your platform has no prebuilt watcher,
review a source-build policy change instead of enabling all install scripts.
Review the compatibility script again before approving a new Webpack configuration
version with `npm install-scripts approve @nextcloud/webpack-vue-config`.

```sh
composer install
npm ci
composer run cs:check
npm run lint
npm run stylelint
npm test
composer audit
npm audit --omit=dev
make package
```

`make package` creates `build/artifacts/edusign-0.0.14.tar.gz` using the lockfiles.
It includes compiled JavaScript and production PHP dependencies, not source-tree
tooling, `node_modules`, or development PHP dependencies. Existing archives are
preserved. The archive is unsigned; signing and publishing are separate steps.
See [RELEASE.md](RELEASE.md) for the release checklist and outstanding audit notes.

## Usage
Install this application in your Nextcloud instance and as an admin go to Settings->eduSign to configure.

This is the configuration used for the dev instans of edusign.

```
edusign_endpoint: https://dev.edusign.sunet.se/api/v1
idp: https://login.idp.eduid.se/idp.xml
authn_context: https://refeds.org/profile/mfa
organization: eduID Sweden
assurance: http://www.swamid.se/policy/assurance/al1
registration_authority: http://www.swamid.se/
saml_attr_schema: 20
```

After that, any user that logged in with eduID can use the edusign application by right clicking on a pdf file in the file browser.
