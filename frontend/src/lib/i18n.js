import { ref } from 'vue'
import en from '@/lang/en.json'
import lv from '@/lang/lv.json'

const messages = { lv, en }

/**
 * Interface languages users can switch to; the first is the default.
 * English only since v1.6.3: the Latvian strings (lang/lv.json) stay in the repo, but the
 * switcher is hidden and a saved 'lv' (this device or the account) is ignored.
 * To bring Latvian back: add 'lv' here and show <LanguageSwitch /> again.
 */
export const LOCALES = ['en']

const STORAGE_KEY = 'merkis-locale'

function initialLocale() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (LOCALES.includes(saved)) return saved
  } catch {
    // Storage unavailable (private mode): fall through to the default.
  }
  return LOCALES[0]
}

export const locale = ref(initialLocale())
document.documentElement.lang = locale.value

/** Switch the interface language and remember it on this device. */
export function setLocale(next) {
  if (!LOCALES.includes(next)) return
  locale.value = next
  document.documentElement.lang = next
  try {
    localStorage.setItem(STORAGE_KEY, next)
  } catch {
    // Not persisted locally; the account still stores it once signed in.
  }
}

const lookup = (lang, key) => key.split('.').reduce((node, part) => node?.[part], messages[lang])

/**
 * Translate a dotted key, e.g. t('auth.email') or t('calendar.week', { n }).
 * Falls back to English, then to the key itself. Arrays (month names) are returned as-is.
 */
export function t(key, params = {}) {
  const value = lookup(locale.value, key) ?? lookup('en', key) ?? key
  if (typeof value !== 'string') return value
  return value.replace(/\{(\w+)\}/g, (match, name) => params[name] ?? match)
}
