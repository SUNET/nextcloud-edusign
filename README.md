# nextcloud-edusign
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
