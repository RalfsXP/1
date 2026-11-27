import { ref } from 'vue'

/*
 * Shared motion helpers (v1.8). Everything here respects "reduce motion" and runs through the
 * Web Animations API, so elements end in their natural CSS state (no leftover inline styles).
 */
const reduce = matchMedia('(prefers-reduced-motion: reduce)')
export const reducedMotion = () => reduce.matches

const EASE = 'cubic-bezier(0.2, 0.8, 0.2, 1)'

/** The vertical gap the element sits in (grid/flex row-gap of its parent), so it can open/close too. */
const gapOf = (el) => parseFloat(getComputedStyle(el.parentElement ?? el).rowGap) || 0

function run(el, frames, options, done) {
  if (reducedMotion()) return done()
  el.style.overflow = 'hidden'
  const anim = el.animate(frames, { easing: EASE, ...options })
  const finish = () => {
    el.style.overflow = ''
    done()
  }
  anim.onfinish = finish
  anim.oncancel = finish
}

/** Height 0 → natural, opening its gap and fading/rising in. For <Transition>/<TransitionGroup> @enter. */
export function expandIn(el, done, { duration = 380, rise = true } = {}) {
  const h = el.offsetHeight
  const gap = gapOf(el)
  run(
    el,
    [
      { height: '0px', marginTop: `${-gap}px`, opacity: 0, transform: rise ? 'translateY(8px) scale(0.98)' : 'none' },
      { height: `${h}px`, marginTop: '0px', opacity: 1, transform: 'none' },
    ],
    { duration },
    done,
  )
}

/** Natural height → 0, closing its gap. For @leave. */
export function collapseOut(el, done, { duration = 260 } = {}) {
  const h = el.offsetHeight
  const gap = gapOf(el)
  run(
    el,
    [
      { height: `${h}px`, marginTop: '0px', opacity: 1, transform: 'none' },
      { height: '0px', marginTop: `${-gap}px`, opacity: 0, transform: 'scale(0.97)' },
    ],
    { duration, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' },
    done,
  )
}

/**
 * Horizontal swipe that follows the finger (damped), for paging boards (calendar month/week).
 * Bind on the element: @touchstart.passive="s.down" @touchmove.passive="s.move" @touchend="s.up" @touchcancel="s.up",
 * and translate it by `s.dx` (no transition while `s.dragging`). A long or fast enough swipe calls onSwipe(±1).
 */
export function useSwipe(onSwipe) {
  const dx = ref(0)
  const dragging = ref(false)
  let start = null
  let axis = null

  function down(e) {
    const t = e.touches[0]
    start = { x: t.clientX, y: t.clientY, at: performance.now() }
    axis = null
  }

  function move(e) {
    if (!start) return
    const t = e.touches[0]
    const x = t.clientX - start.x
    const y = t.clientY - start.y
    if (!axis) {
      if (Math.abs(x) < 8 && Math.abs(y) < 8) return
      axis = Math.abs(x) > Math.abs(y) * 1.2 ? 'x' : 'y'
    }
    if (axis !== 'x') return
    dragging.value = true
    // Rubber band: follows 1:1 at first, then resists.
    dx.value = Math.sign(x) * Math.min(Math.abs(x), 60 + Math.abs(x) * 0.35)
  }

  function up(e) {
    if (!start) return
    const t = e.changedTouches?.[0]
    const x = t ? t.clientX - start.x : 0
    const speed = Math.abs(x) / Math.max(1, performance.now() - start.at) // px per ms
    const page = axis === 'x' && (Math.abs(x) > 60 || speed > 0.45) ? (x < 0 ? 1 : -1) : 0
    start = null
    dragging.value = false
    dx.value = 0
    if (page) onSwipe(page)
  }

  return { dx, dragging, down, move, up }
}
