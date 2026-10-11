import { ref } from 'vue'

/** True on touch-first devices (phones/tablets): there, tap = done and hold = edit. */
const query = matchMedia('(hover: none) and (pointer: coarse)')
export const touchUI = ref(query.matches)
query.addEventListener('change', (e) => (touchUI.value = e.matches))

/* The "tap — done · hold — edit" hint: dismissed once, gone everywhere on this device. */
const HINT_KEY = 'merkis-hold-hint'
let seen = false
try {
  seen = localStorage.getItem(HINT_KEY) === 'dismissed'
} catch {
  // Storage unavailable: the hint just shows again next time.
}
export const holdHintDismissed = ref(seen)

export function dismissHoldHint() {
  holdHintDismissed.value = true
  try {
    localStorage.setItem(HINT_KEY, 'dismissed')
  } catch {
    // Not remembered; fine.
  }
}
