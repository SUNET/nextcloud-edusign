/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * Inspiration from https://github.com/nextcloud/integration_docusign/blob/c0c47dddd6ee403a56761135594b43092b5dddc9/src/filesplugin.js
 *
 * @author Micke Nordin <kano@sunet.se>
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Micke Nordin 2024
 * @copyright Julien Veyssier 2021
 */
import Vue from 'vue'
import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router'
import {
  registerFileAction, Permission, FileAction, FileType,
} from '@nextcloud/files'
Vue.mixin({ methods: { t, n } })
if (!OCA.Edusign) {
  /**
   * @namespace
   */
  OCA.Edusign = {
    configured: false,
    requestOnFileChange: false,
    mimetype: ['application/pdf'],
    ignoreLists: [
      'trashbin',
      'files.public',
    ],
  }
}
const requestSignatureAction = new FileAction({
  id: 'edusign-sign',
  displayName: (nodes) => {
    return t('edusign', 'Sign with Edusign')
  },
  iconSvgInline() {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M9.75 20.85c1.78-.7 1.39-2.63.49-3.85c-.89-1.25-2.12-2.11-3.36-2.94A9.8 9.8 0 0 1 4.54 12c-.28-.33-.85-.94-.27-1.06c.59-.12 1.61.46 2.13.68c.91.38 1.81.82 2.65 1.34l1.01-1.7C8.5 10.23 6.5 9.32 4.64 9.05c-1.06-.16-2.18.06-2.54 1.21c-.32.99.19 1.99.77 2.77c1.37 1.83 3.5 2.71 5.09 4.29c.34.33.75.72.95 1.18c.21.44.16.47-.31.47c-1.24 0-2.79-.97-3.8-1.61l-1.01 1.7c1.53.94 4.09 2.41 5.96 1.79m9.21-13.52L13.29 13H11v-2.29l5.67-5.68zm3.4-.78c-.01.3-.32.61-.64.92L19.2 10l-.87-.87l2.6-2.59l-.59-.59l-.67.67l-2.29-2.29l2.15-2.15c.24-.24.63-.24.86 0l1.43 1.43c.24.22.24.62 0 .86c-.21.21-.41.41-.41.61c-.02.2.18.42.38.59c.29.3.58.58.57.88"/></svg>'
  },
  enabled(nodes, view) {
    if (!OCA.Edusign.configured) {
      const url = generateUrl('/index.php/apps/edusign/query');
      axios.get(url).then(result => {
        OCA.Edusign.configured = 
          result.data.assurance != ""
          && result.data.authn_context != ""
          && result.data.edusign_endpoint != ""
          && result.data.idp != ""
          && result.data.organization != ""
          && result.data.registration_authority != ""
          && result.data.saml_attr_schema != "";
      });

    }

    return !OCA.Edusign.ignoreLists.includes(view.id)
      && nodes.length === 1
      && nodes.some(({ mime }) => OCA.Edusign.mimetype.includes(mime))
      && !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
      && !nodes.some(({ type }) => type !== FileType.File)
      && OCA.Edusign.configured;
  },
  async exec(node) {
    const url = generateUrl("/index.php/apps/edusign/request")

    let request_data = await axios.get(url, { "params": { "path": node.path, "redirect_uri": window.location } });
    if (!request_data.error) {
      const payload = JSON.parse(request_data.data).payload;
      if (payload) {
        const form_data = {
          'Binding': payload.binding,
          'RelayState': payload.relay_state,
          'EidSignRequest': payload.sign_request
        }
        var form = document.createElement("form");
        form.setAttribute("method", "post");
        form.setAttribute("action", payload.destination_url);
        for (var key in form_data) {
          var hidden_field = document.createElement("input");
          hidden_field.setAttribute("type", "hidden");
          hidden_field.setAttribute("name", key);
          hidden_field.setAttribute("value", form_data[key]);
          form.appendChild(hidden_field);
        }
        document.body.appendChild(form);
        form.submit();
      } else {
        console.log("Error: payload empty");
      }
    }
    return null;
  },
})
registerFileAction(requestSignatureAction)

