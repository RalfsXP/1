<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { isActive } from '@/lib/nav'

/**
 * Pill navigation with a sliding active pill.
 * variant 'top' (desktop header): white track, icon + label, the active pill is ink.
 */
defineProps({
  items: { type: Array, required: true },
  variant: { type: String, default: 'top' },
})

const route = useRoute()
const track = ref(null)
const pill = ref({ x: 0, w: 0, on: false })
let ready = false

function place() {
  const el = track.value?.querySelector('.item.active')
  if (!el) {
    pill.value = { ...pill.value, on: false }
    return
  }
  pill.value = { x: el.offsetLeft, w: el.offsetWidth, on: true, instant: !ready }
  ready = true
}

watch(() => route.name, () => nextTick(place))
let observer
onMounted(() => {
  place()
  // Web fonts and window resizes change the item widths.
  observer = new ResizeObserver(place)
  observer.observe(track.value)
  document.fonts?.ready.then(place)
})
onBeforeUnmount(() => observer?.disconnect())

const active = (item) => isActive(item, route)
</script>

<template>
  <div ref="track" class="pill-nav" :class="variant">
    <span
      class="indicator"
      :class="{ on: pill.on, instant: pill.instant }"
      :style="{ width: `${pill.w}px`, transform: `translateX(${pill.x}px)` }"
      aria-hidden="true"
    />
    <RouterLink
      v-for="item in items"
      :key="item.label"
      :to="item.to"
      class="item"
      :class="{ active: active(item) }"
      :aria-current="active(item) ? 'page' : undefined"
      :aria-label="variant === 'top' ? undefined : $t(item.label)"
    >
      <component :is="item.icon" aria-hidden="true" />
      <span v-if="variant === 'top'" class="label">{{ $t(item.label) }}</span>
    </RouterLink>
    <slot />
  </div>
</template>

<style scoped>
.pill-nav {
  position: relative;
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 5px;
  border-radius: 999px;
}

.indicator {
  position: absolute;
  top: 5px;
  bottom: 5px;
  left: 0;
  border-radius: 999px;
  opacity: 0;
  transition: transform 0.45s var(--spring), width 0.45s var(--spring), opacity 0.2s ease;
}

.indicator.on {
  opacity: 1;
}

.indicator.instant {
  transition: none;
}

.item {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 999px;
  font-size: 0.92rem;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.3s var(--ease), transform 0.25s var(--spring);
}

.item:active {
  transform: scale(0.92);
}

.item svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* ---------- Desktop header ---------- */
.top {
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
}

.top .indicator {
  background: var(--accent);
}

.top .item {
  height: 44px;
  padding: 0 18px 0 15px;
  color: var(--muted);
}

@media (hover: hover) {
  .top .item:hover {
    color: var(--fg);
  }
}

.top .item.active {
  color: var(--on-accent);
}
</style>
