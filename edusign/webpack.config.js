const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const appId = 'edusign'
webpackConfig.entry = {
	adminSettings: { import: path.join(__dirname, 'src', 'adminSettings.js'), filename: appId + '-adminSettings.js' },
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: appId + '-main.js' },
}
module.exports = webpackConfig
