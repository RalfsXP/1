import { ref } from 'vue'
import { syncThemeColor } from './design'

// index.html sets data-theme before first paint; this keeps Vue in sync with it.
export const theme = ref(document.documentElement.dataset.theme === 'light' ? 'light' : 'dark')

export function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark'
  document.documentElement.dataset.theme = theme.value
  syncThemeColor()
  try {
    localStorage.setItem('merkis-theme', theme.value)
  } catch {
    // Storage can be unavailable (private mode); the toggle still works.
  }
}
