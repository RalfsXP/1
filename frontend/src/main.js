import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import { t } from './lib/i18n'
import router from './router'

const app = createApp(App)
// `$t('key')` in templates; reactive to language changes.
app.config.globalProperties.$t = t
app.use(router).mount('#app')
