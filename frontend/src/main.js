import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import { vReveal, vSlidePill } from './lib/directives'
import { syncThemeColor } from './lib/theme'
import { t } from './lib/i18n'
import router from './router'

// iOS Safari ignores user-scalable=no; cancelling its gesture events blocks pinch zoom there too.
for (const type of ['gesturestart', 'gesturechange']) {
  document.addEventListener(type, (e) => e.preventDefault(), { passive: false })
}

// iOS Safari only shows :active (the press feedback on every button, row and tile) when a touch listener
// exists; an empty passive one turns it on everywhere without costing scroll performance.
document.addEventListener('touchstart', () => {}, { passive: true })

// The signed-in shell never scrolls the window (only <main> scrolls). iOS still shifts the window to show a
// focused field (login keyboard) and leaves it shifted, which slid the page under the top bar and cut the list
// off early — so in the shell, any window offset snaps back once nothing is being typed.
addEventListener(
  'scroll',
  () => {
    if (!window.scrollY || !document.querySelector('.shell')) return
    if (document.activeElement?.matches?.('input, textarea, select')) return
    window.scrollTo(0, 0)
  },
  { passive: true },
)
addEventListener('focusout', () => setTimeout(() => window.dispatchEvent(new Event('scroll')), 150))

const app = createApp(App)
// `$t('key')` in templates; reactive to language changes.
app.config.globalProperties.$t = t
// v-slide-pill (sliding pill in pill groups) and v-reveal (rise in when scrolled into view): lib/directives.js
app.directive('slide-pill', vSlidePill)
app.directive('reveal', vReveal)
app.use(router).mount('#app')

// Mobile browser chrome takes the real page background of the current theme.
syncThemeColor()

