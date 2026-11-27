<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

// Slides up from the bottom on phones, a centred dialog on wider screens.
const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
})
const emit = defineEmits(['close'])

const titleId = useId()
const sheet = ref(null)
let returnFocus = null

const focusables = () =>
  [...(sheet.value?.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])') ?? [])].filter(
    (el) => !el.disabled && el.offsetParent !== null,
  )

/* Esc closes; Tab stays inside the dialog while it's open. */
function onKey(e) {
  if (e.key === 'Escape') emit('close')
  if (e.key !== 'Tab') return
  const items = focusables()
  if (!items.length) return
  const [first, last] = [items[0], items[items.length - 1]]
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault()
    last.focus()
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault()
    first.focus()
  }
}

/*
 * Drag the handle/header down to dismiss (phones), like a native sheet: it follows the finger (a little
 * give upwards), the backdrop fades with it, and a long pull or a quick flick closes it — sliding on from
 * where it was let go instead of jumping back first. Otherwise it springs back.
 */
const drag = ref(0)
const dragging = ref(false)
let startY = null
let lastY = 0
let lastT = 0
let speed = 0

function onDragStart(e) {
  startY = lastY = e.touches[0].clientY
  lastT = performance.now()
  speed = 0
  dragging.value = true
}

function onDragMove(e) {
  if (startY === null) return
  const y = e.touches[0].clientY
  const now = performance.now()
  speed = (y - lastY) / Math.max(1, now - lastT)
  lastY = y
  lastT = now
  const d = y - startY
  drag.value = d < 0 ? Math.max(-24, d / 4) : d
}

function onDragEnd() {
  if (startY === null) return
  startY = null
  dragging.value = false
  if (drag.value > 110 || (speed > 0.5 && drag.value > 16)) {
    // Written straight to the element: once closing starts, Vue no longer patches the leaving sheet.
    const el = sheet.value
    el.classList.remove('dragging')
    el.style.transition = `transform ${speed > 1 ? 0.2 : 0.3}s cubic-bezier(0.4, 0, 1, 1)`
    el.style.transform = 'translateY(105%)'
    emit('close')
    return
  }
  drag.value = 0
}

const sheetStyle = computed(() => (drag.value ? { transform: `translateY(${drag.value}px)` } : null))
// The dimmed page lightens as the sheet is pulled down.
const rootStyle = computed(() =>
  drag.value > 0 ? { backgroundColor: `color-mix(in srgb, var(--backdrop) ${Math.max(0, 100 - drag.value / 4)}%, transparent)` } : null,
)
// Focus moves into the dialog on open and back to what opened it on close.
watch(
  () => props.open,
  async (open) => {
    if (open) {
      drag.value = 0
      returnFocus = document.activeElement
      addEventListener('keydown', onKey)
      await nextTick()
      // A field marked autofocus (e.g. "name your board") gets the keyboard; otherwise the dialog.
      const target = sheet.value?.querySelector('[autofocus]') ?? sheet.value
      target?.focus({ preventScroll: true })
    } else {
      removeEventListener('keydown', onKey)
      returnFocus?.focus?.({ preventScroll: true })
      returnFocus = null
    }
  },
  { immediate: true },
)
onBeforeUnmount(() => removeEventListener('keydown', onKey))

</script>

<template>
  <Teleport to="body">
    <Transition name="sheet">
      <div v-if="open" class="sheet-root" :style="rootStyle" @click.self="emit('close')">
        <section
          ref="sheet"
          class="sheet"
          :class="{ dragging }"
          :style="sheetStyle"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
        >
          <div
            class="drag-zone"
            @touchstart.passive="onDragStart"
            @touchmove.passive="onDragMove"
            @touchend="onDragEnd"
            @touchcancel="onDragEnd"
          >
            <span class="grabber" aria-hidden="true" />
            <header class="sheet-head">
              <h2 :id="titleId">{{ title }}</h2>
              <button type="button" class="icon-btn bare" :aria-label="$t('nav.close')" @click="emit('close')">
                <XMarkIcon aria-hidden="true" />
              </button>
            </header>
          </div>
          <div class="sheet-body">
            <slot />
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet-root {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  height: var(--app-h);
  z-index: 100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: var(--backdrop);
}

.sheet:focus {
  outline: none;
}

/* A white card that slides up over the dimmed page. */
.sheet {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: calc(var(--app-h) * 0.92);
  border-radius: 32px 32px 0 0;
  background: var(--surface);
  box-shadow: 0 -16px 50px rgb(0 0 0 / 0.16), inset 0 0 0 1px var(--card-edge);
  color: var(--fg);
}

.sheet:not(.dragging) {
  transition: transform 0.4s var(--sheet-ease);
}

.drag-zone {
  display: flex;
  flex-direction: column;
  touch-action: none;
}

.grabber {
  align-self: center;
  width: 42px;
  height: 5px;
  margin-top: 9px;
  border-radius: 3px;
  background: var(--line);
}

.sheet-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px 6px 22px;
}

h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.sheet-head .icon-btn {
  width: 38px;
  height: 38px;
}

.sheet-head .icon-btn svg {
  width: 18px;
  height: 18px;
  stroke-width: 2.2;
}

.sheet-body {
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 10px 22px max(24px, calc(env(safe-area-inset-bottom) + 12px));
}

.sheet-enter-active {
  transition: background-color 0.3s ease;
}

.sheet-leave-active {
  transition: background-color 0.3s ease;
}

.sheet-enter-active .sheet {
  transition: transform 0.5s var(--sheet-ease), opacity 0.2s ease;
}

.sheet-leave-active .sheet {
  transition: transform 0.25s ease-in, opacity 0.2s ease;
}

.sheet-enter-from,
.sheet-leave-to {
  background-color: transparent;
}

.sheet-enter-from .sheet,
.sheet-leave-to .sheet {
  transform: translateY(100%);
}

@media (min-width: 640px) {
  .sheet-root {
    align-items: center;
    padding: 24px;
  }

  .sheet {
    width: min(540px, 100%);
    max-height: min(calc(var(--app-h) * 0.88), 840px);
    border-radius: 32px;
  }

  .grabber {
    display: none;
  }

  .sheet-head {
    padding-top: 20px;
  }

  .sheet-enter-active .sheet {
    transition: transform 0.45s var(--spring), opacity 0.25s ease;
  }

  .sheet-enter-from .sheet,
  .sheet-leave-to .sheet {
    transform: translateY(24px) scale(0.94);
    opacity: 0;
  }
}
</style>
