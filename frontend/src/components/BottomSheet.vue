<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
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

// Focus moves into the dialog on open and back to what opened it on close.
watch(
  () => props.open,
  async (open) => {
    if (open) {
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

/* Drag the handle/header down to dismiss (phones). */
const drag = ref(0)
let startY = null
const onDragStart = (e) => (startY = e.touches[0].clientY)
const onDragMove = (e) => startY !== null && (drag.value = Math.max(0, e.touches[0].clientY - startY))
function onDragEnd() {
  if (drag.value > 90) emit('close')
  drag.value = 0
  startY = null
}
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet">
      <div v-if="open" class="sheet-root" @click.self="emit('close')">
        <section
          ref="sheet"
          class="sheet"
          :class="{ dragging: drag }"
          :style="drag ? { transform: `translateY(${drag}px)` } : null"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
        >
          <div class="drag-zone" @touchstart.passive="onDragStart" @touchmove.passive="onDragMove" @touchend="onDragEnd">
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
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: var(--backdrop);
}

.sheet:focus {
  outline: none;
}

.sheet {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: 92dvh;
  border: 1px solid var(--line);
  border-bottom: 0;
  border-radius: 24px 24px 0 0;
  background: var(--bg);
  color: var(--fg);
}

.sheet:not(.dragging) {
  transition: transform 0.25s ease;
}

.drag-zone {
  display: flex;
  flex-direction: column;
  touch-action: none;
}

.grabber {
  align-self: center;
  width: 40px;
  height: 5px;
  margin-top: 8px;
  border-radius: 3px;
  background: var(--line);
}

.sheet-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 14px 6px 22px;
}

h2 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.sheet-body {
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 8px 22px max(22px, env(safe-area-inset-bottom));
}

.sheet-enter-active,
.sheet-leave-active {
  transition: background-color 0.25s ease;
}

.sheet-enter-active .sheet,
.sheet-leave-active .sheet {
  transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.25s ease;
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
    width: min(520px, 100%);
    max-height: min(88dvh, 820px);
    border-bottom: 1px solid var(--line);
    border-radius: 24px;
  }

  .grabber {
    display: none;
  }

  .sheet-enter-from .sheet,
  .sheet-leave-to .sheet {
    transform: translateY(16px) scale(0.98);
    opacity: 0;
  }
}
</style>
