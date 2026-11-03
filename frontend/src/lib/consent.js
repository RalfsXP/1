import { ref, watch } from 'vue'

/*
 * The visitor's choice in the Cheklet cookie banner (script in index.html, API on
 * window.CookieProtector; categories: n necessary, p performance, f functionality, t targeting).
 * Performance → Vercel Web Analytics (App.vue). Functionality → remembering theme, language and
 * the dismissed hold hint between visits; without it they last only for this page load.
 * A category that isn't set up in the Cheklet dashboard yet is never asked about, so it counts as
 * allowed (same as before the banner) — gating starts as soon as the category is added there.
 * No banner at all (blocked, offline) = nothing optional allowed.
 */
export const consent = ref({ performance: false, functional: false })

/** Preferences kept in localStorage only with functional consent. */
const PREF_KEYS = ['merkis-theme', 'merkis-locale', 'merkis-hold-hint']
const pending = new Map() // set this visit, written once consent is given

function read() {
  const cp = window.CookieProtector
  if (!cp) {
    consent.value = { performance: false, functional: false }
    return
  }
  // Categories the banner offers (config `k`: [[code, name], …]).
  let offered = null
  try {
    offered = new Set((cp.debug()?.cfg?.k ?? []).map(([code]) => code))
  } catch {
    // Unknown config shape: rely on the visitor's choice alone.
  }
  const allows = (code) => (offered && !offered.has(code)) || Boolean(cp.allowed(code))
  consent.value = { performance: allows('p'), functional: allows('f') }
}

read()
for (const event of ['cp:ready', 'cp:change', 'cp:accept', 'cp:reject', 'cp:withdraw']) addEventListener(event, read)

watch(
  () => consent.value.functional,
  (allowed) => {
    try {
      if (allowed) pending.forEach((value, key) => localStorage.setItem(key, value))
      else PREF_KEYS.forEach((key) => localStorage.removeItem(key))
    } catch {
      // Storage unavailable (private mode): preferences just aren't remembered.
    }
  },
)

/** Remember a preference across visits — only if the visitor allowed functional cookies. */
export function remember(key, value) {
  pending.set(key, value)
  if (!consent.value.functional) return
  try {
    localStorage.setItem(key, value)
  } catch {
    // Not persisted; the choice still applies for this visit.
  }
}
