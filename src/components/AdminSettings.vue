<template>
	<div id="eduid_prefs" class="section">
		<div id="eduid-content">
			<NcSettingsSection
				name="eduSign"
				description="eduSign signature engine."
				docUrl="https://github.com/SUNET/nextcloud-edusign"
				@default="populate">
				<div class="external-label">
					<label for="EdusignEndpoint">eduSign Endpoint</label>
					<NcTextField
						id="EdusignEndpoint"
						v-model="edusign_endpoint"
						:labelOutside="true"
						placeholder="eduSign Endpoint"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="ApiKey">API Key</label>
					<NcTextField
						id="ApiKey"
						v-model="api_key"
						:labelOutside="true"
						:placeholder="api_key_configured ? 'Leave blank to keep existing API key' : 'eduSign API Key'"
						type="password"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="IdP">IdP</label>
					<NcTextField
						id="IdP"
						v-model="idp"
						:labelOutside="true"
						placeholder="IdP (entity id)"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="AuthnContext">Authn Context</label>
					<NcTextField
						id="AuthnContext"
						v-model="authn_context"
						:labelOutside="true"
						placeholder="Authn Context"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="Organization">Organization</label>
					<NcTextField
						id="Organization"
						v-model="organization"
						:labelOutside="true"
						placeholder="Organization"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="Assurance">Assurance</label>
					<NcTextField
						id="Assurance"
						v-model="assurance"
						:labelOutside="true"
						placeholder="Assurance"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="RegistrationAuthority">Registration Authority</label>
					<NcTextField
						id="RegistrationAuthority"
						v-model="registration_authority"
						:labelOutside="true"
						placeholder="Registration Authority"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="SamlAttrSchema">Saml Attribute Schema</label>
					<NcTextField
						id="SamlAttrSchema"
						v-model="saml_attr_schema"
						:labelOutside="true"
						placeholder="Saml Attribute Schema"
						@update:modelValue="check" />
				</div>
				<div class="external-label">
					<label for="CSPDomains">Additional CSP Domains</label>
					<NcTextField
						id="CSPDomains"
						v-model="csp_domains"
						:labelOutside="true"
						placeholder="Space separated list of domains"
						@update:modelValue="check" />
				</div>
				<NcButton
					id="Button"
					:disabled="!isValid"
					:readonly="readonly"
					:wide="true"
					text="Save"
					:type="submit"
					@click="register">
					<template #icon>
						<Check id="Icon" :size="20" />
					</template>
					Save
				</NcButton>
				<div id="oidc-configured">
					<ul id="oidc-configured-list">
						<NcListItemIcon
							v-for="i in configured"
							:key="i.name"
							:name="i.name"
							:subname="i.token_endpoint">
							<NcActions>
								<NcActionButton @click="(_) => remove()">
									<template #icon>
										<Delete :size="20" />
									</template>
									Delete
								</NcActionButton>
							</NcActions>
						</NcListItemIcon>
					</ul>
				</div>
			</NcSettingsSection>
		</div>
	</div>
</template>

<script>
// Nextcloud API
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
// Nextcloud components
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcListItemIcon from '@nextcloud/vue/components/NcListItemIcon'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
// Icons
import Check from 'vue-material-design-icons/Check.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
  name: 'AdminSettings',

  components: {
    Check,
    Delete,
    NcActionButton,
    NcActions,
    NcButton,
    NcListItemIcon,
    NcSettingsSection,
    NcTextField,
  },

  props: [],
  data() {
    return {
      idp: '',
      authn_context: '',
      edusign_endpoint: '',
      api_key: '',
      api_key_configured: false,
      organization: '',
      assurance: '',
      registration_authority: '',
      saml_attr_schema: '',
      csp_domains: '',
      configured: [],
    }
  },

  mounted() {
    const url = generateUrl('/apps/edusign/query')
    axios.get(url).then((result) => {
      this.idp = result.data.idp || ''
      this.authn_context = result.data.authn_context || ''
      this.edusign_endpoint = result.data.edusign_endpoint || ''
      this.api_key_configured = result.data.api_key_configured === true
      this.organization = result.data.organization || ''
      this.assurance = result.data.assurance[0] || ''
      this.registration_authority = result.data.registration_authority || ''
      this.saml_attr_schema = result.data.saml_attr_schema || ''
      this.csp_domains = result.data.csp_domains || ''
      this.check()
    }).catch((error) => { console.error(error) })
  },

  methods: {
    check() {
      this.isValid = (
        this.idp !== ''
        && this.authn_context !== ''
        && this.edusign_endpoint !== ''
        && (this.api_key.trim() !== '' || this.api_key_configured)
        && this.organization !== ''
        && this.assurance !== ''
        && this.registration_authority !== ''
        && this.saml_attr_schema !== ''
      )
    },

    async remove() {
      const url = generateUrl('/apps/edusign/remove')
      const res = await axios.post(url)
      console.log(res)
      if (res.data.status === 'success') {
        this.idp = ''
        this.authn_context = ''
        this.edusign_endpoint = ''
        this.api_key = ''
        this.api_key_configured = false
        this.organization = ''
        this.assurance = ''
        this.registration_authority = ''
        this.saml_attr_schema = ''
        this.csp_domains = ''
      }
    },

    async register() {
      const url = generateUrl('/apps/edusign/register')
      const payload = {
        idp: this.idp,
        authn_context: this.authn_context,
        edusign_endpoint: this.edusign_endpoint,
        api_key: this.api_key,
        organization: this.organization,
        assurance: this.assurance,
        registration_authority: this.registration_authority,
        saml_attr_schema: this.saml_attr_schema,
        csp_domains: this.csp_domains,
      }
      const res = await axios.post(url, payload)
      if (res.data.status !== 'success') {
        console.log(res)
        return
      }
      this.api_key_configured = true
      this.api_key = ''
      this.check()
    },
  },
}
</script>
