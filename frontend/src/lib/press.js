import { ref } from 'vue'

/**
 * Tap vs. hold on touch screens. A tap is an ordinary click (so screen readers
 * and keyboards keep working); holding ≥ `delay` ms without moving fires onHold
 * and swallows the click that follows. Scrolling (pointercancel) or moving
 * more than 10 px cancels the hold. Mouse input is ignored here — desktop keeps
 * its own click behaviour.
 *
 * Bind on the element: @pointerdown="p.down($event, x)" @pointermove="p.move"
 * @pointerup="p.up" @pointercancel="p.up" @click="p.click($event, x)"
 */
export function usePress({ onTap, onHold, delay = 450 }) {
  const pressing = ref(null) // payload being held, for the fill animation
  let timer = null
  let origin = null
  let held = false

  function reset() {
    clearTimeout(timer)
    timer = null
    origin = null
    pressing.value = null
  }

  function down(e, payload = true) {
    if (e.pointerType === 'mouse') return
    reset()
    held = false
    origin = { x: e.clientX, y: e.clientY }
    pressing.value = payload
    timer = setTimeout(() => {
      held = true
      reset()
      navigator.vibrate?.(12)
      onHold(payload)
    }, delay)
  }

  function move(e) {
    if (origin && Math.hypot(e.clientX - origin.x, e.clientY - origin.y) > 10) reset()
  }

  function click(e, payload = true) {
    if (held) {
      held = false
      e?.preventDefault()
      e?.stopPropagation()
      return
    }
    onTap(payload)
  }

  return { pressing, down, move, up: reset, click }
}
