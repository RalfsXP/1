import { ref } from 'vue'

/** Whether the full-screen spinning-logo loader is showing. */
export const loading = ref(false)

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

// Long enough for the overlay to fade in and cover the page before we swap it.
const COVER_MS = 250

/**
 * Show the loader, run `task` once the screen is covered, and keep the loader
 * up for at least `minMs` so it reads as a deliberate transition, not a flicker.
 * Errors from `task` are re-thrown after the loader hides.
 */
export async function withLoader(task, minMs = 1100) {
  loading.value = true
  const started = Date.now()
  try {
    await sleep(COVER_MS)
    return await task()
  } finally {
    const remaining = minMs - (Date.now() - started)
    if (remaining > 0) await sleep(remaining)
    loading.value = false
  }
}
