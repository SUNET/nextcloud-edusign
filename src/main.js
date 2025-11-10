/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * Inspiration from https://github.com/nextcloud/integration_docusign/blob/c0c47dddd6ee403a56761135594b43092b5dddc9/src/filesplugin.js
 *
 * @author Micke Nordin 2024-2025 <kano@sunet.se>
 * @author Julien Veyssier 2021 <eneiluj@posteo.net>
 */
import axios from '@nextcloud/axios'
import {
  FileAction, registerFileAction,
} from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

if (!OCA.Edusign) {
  OCA.Edusign = {
    configured: false,
    requestOnFileChange: false,
    mimetype: ['application/pdf', 'application/xml'],
  };
  (async () => {
    try {
      const { data } = await axios.get(generateUrl('/apps/edusign/query'))
      OCA.Edusign.configured = (
        data.assurance !== ''
        && data.authn_context !== ''
        && data.edusign_endpoint !== ''
        && data.idp !== ''
        && data.organization !== ''
        && data.registration_authority !== ''
        && data.saml_attr_schema !== ''
      )
    } catch (e) {
      OCA.Edusign.configured = false
      console.error('Edusign: failed to load configuration', e)
    }
  })()
}

const requestSignatureAction = new FileAction({
  id: 'edusign-sign',
  displayName: () => {
    return t('edusign', 'Sign with Edusign')
  },
  iconSvgInline() {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M9.75 20.85c1.78-.7 1.39-2.63.49-3.85c-.89-1.25-2.12-2.11-3.36-2.94A9.8 9.8 0 0 1 4.54 12c-.28-.33-.85-.94-.27-1.06c.59-.12 1.61.46 2.13.68c.91.38 1.81.82 2.65 1.34l1.01-1.7C8.5 10.23 6.5 9.32 4.64 9.05c-1.06-.16-2.18.06-2.54 1.21c-.32.99.19 1.99.77 2.77c1.37 1.83 3.5 2.71 5.09 4.29c.34.33.75.72.95 1.18c.21.44.16.47-.31.47c-1.24 0-2.79-.97-3.8-1.61l-1.01 1.7c1.53.94 4.09 2.41 5.96 1.79m9.21-13.52L13.29 13H11v-2.29l5.67-5.68zm3.4-.78c-.01.3-.32.61-.64.92L19.2 10l-.87-.87l2.6-2.59l-.59-.59l-.67.67l-2.29-2.29l2.15-2.15c.24-.24.63-.24.86 0l1.43 1.43c.24.22.24.62 0 .86c-.21.21-.41.41-.41.61c-.02.2.18.42.38.59c.29.3.58.58.57.88"/></svg>'
  },
  enabled(nodes, view) {
    return (
      OCA.Edusign.configured
      && nodes.length === 1
      && nodes.some((node) => OCA.Edusign.mimetype.includes(node.mime))
    )
  },
  async exec(arg) {
    const node = Array.isArray(arg) ? arg[0] : arg
    const url = generateUrl('/apps/edusign/request')

    const response = await axios.get(url, {
      params: { path: node.path, redirect_uri: window.location.href },
    })

    const data = response.data

    const payload = data?.payload

    if (payload) {
      const formData = {
        Binding: payload.binding,
        RelayState: payload.relay_state,
        EidSignRequest: payload.sign_request,
      }
      const form = document.createElement('form')
      form.setAttribute('method', 'post')
      form.setAttribute('action', payload.destination_url)
      for (const key in formData) {
        const hiddenField = document.createElement('input')
        hiddenField.setAttribute('type', 'hidden')
        hiddenField.setAttribute('name', key)
        hiddenField.setAttribute('value', formData[key])
        form.appendChild(hiddenField)
      }
      document.body.appendChild(form)
      form.submit()
    } else {
      console.log('Error: payload empty')
    }
    return null
  },
})
registerFileAction(requestSignatureAction)
