import { ref } from 'vue'

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

/** Browser chrome (mobile address bar) matches the page background of the current theme. */
export function syncThemeColor() {
  // Measure --bg on a throwaway element: <body> fades its background, so mid-switch it reads stale.
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
