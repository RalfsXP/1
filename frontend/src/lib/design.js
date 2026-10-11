import { ref } from 'vue'

// Interface design: "default" = V1 (Turf / Sea Green / Light Yellow / Powder Blush / Toasted Almond) or
// "teal" = V2 (Stormy Teal / Pacific Blue / Sky Blue / Parchment / Amber Glow) or
// "ember" = V3 (Prussian Blue / Deep Teal / Jasmine / Brick Ember / Blood Red). index.html sets data-design before first paint.
export const DESIGNS = ['default', 'teal', 'ember']
const STORAGE_KEY = 'merkis-design'

export const design = ref(DESIGNS.includes(document.documentElement.dataset.design) ? document.documentElement.dataset.design : 'default')

/** Apply a design on this device (no account save). */
export function setDesign(next) {
  // Retired designs (v1.4 "new" / "ocean") fall back to the default.
  if (!DESIGNS.includes(next)) next = 'default'
  design.value = next
  if (next === 'default') delete document.documentElement.dataset.design
  else document.documentElement.dataset.design = next
  syncThemeColor()
  try {
    localStorage.setItem(STORAGE_KEY, next)
  } catch {
    // Not persisted locally; the account still stores it once signed in.
  }
}

/** Browser chrome (mobile address bar) matches the page background of the current design + theme. */
export function syncThemeColor() {
  // Measure --bg on a throwaway element: <body> fades its background, so mid-switch it reads stale.
  const probe = document.createElement('span')
  probe.style.cssText = 'position:absolute;visibility:hidden;transition:none;background:var(--bg)'
  document.body.append(probe)
  const bg = getComputedStyle(probe).backgroundColor
  probe.remove()
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', toHex(bg))
}

/** color-mix() backgrounds compute to color(srgb …), which theme-color doesn't take: paint 1 px and read it back. */
function toHex(color) {
  const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true })
  ctx.fillStyle = color
  ctx.fillRect(0, 0, 1, 1)
  const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data
  return `#${[r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('')}`
}
