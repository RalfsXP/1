import { ref } from 'vue'

// index.html sets data-theme before first paint; this keeps Vue in sync with it.
export const theme = ref(document.documentElement.dataset.theme === 'light' ? 'light' : 'dark')

export function toggleTheme() {
  const next = theme.value === 'dark' ? 'light' : 'dark'
  const apply = () => {
    theme.value = next
    document.documentElement.dataset.theme = next
    syncThemeColor()
  }
  // Cross-fade the whole page (cards, text, ground together) where View Transitions exist (Safari 18+, Chrome);
  // elsewhere, or with reduced motion, switch at once. (The callback runs a frame later.)
  if (document.startViewTransition && !matchMedia('(prefers-reduced-motion: reduce)').matches) document.startViewTransition(apply)
  else apply()
  try {
    localStorage.setItem('merkis-theme', next)
  } catch {
    // Storage can be unavailable (private mode); the toggle still works.
  }
}

/** Browser chrome (mobile address bar) matches the page background of the current theme. */
export function syncThemeColor() {
  // Measure --bg on a throwaway element (always the current value, whatever is mid-animation).
  const probe = document.createElement('span')
  probe.style.cssText = 'position:absolute;visibility:hidden;transition:none;background:var(--bg)'
  document.body.append(probe)
  const bg = getComputedStyle(probe).backgroundColor
  probe.remove()
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', toHex(bg))
}

/** Modern colour syntax may not be accepted by theme-color: paint 1 px and read it back as hex. */
function toHex(color) {
  const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  ctx.fillStyle = color
  ctx.fillRect(0, 0, 1, 1)
  const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data
  return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`
}
