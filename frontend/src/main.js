import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import { syncThemeColor } from './lib/design'
import { t } from './lib/i18n'
import router from './router'

// iOS Safari ignores user-scalable=no; cancelling its gesture events blocks pinch zoom there too.
for (const type of ['gesturestart', 'gesturechange']) {
  document.addEventListener(type, (e) => e.preventDefault(), { passive: false })
}

const app = createApp(App)
// `$t('key')` in templates; reactive to language changes.
app.config.globalProperties.$t = t
app.use(router).mount('#app')

// Mobile browser chrome takes the real page background of the current design + theme.
syncThemeColor()
