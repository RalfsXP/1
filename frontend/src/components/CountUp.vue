<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { reducedMotion } from '@/lib/motion'

// A number that counts up to its value when it appears, and rolls to every new value after that.
const props = defineProps({
  value: { type: Number, required: true },
  duration: { type: Number, default: 900 },
})

const shown = ref(0)
let frame = 0

function roll(from, to) {
  cancelAnimationFrame(frame)
  if (reducedMotion() || from === to) {
    shown.value = to
    return
  }
  const start = performance.now()
  const step = (now) => {
    const p = Math.min(1, (now - start) / props.duration)
    shown.value = Math.round(from + (to - from) * (1 - (1 - p) ** 3))
    if (p < 1) frame = requestAnimationFrame(step)
  }
  frame = requestAnimationFrame(step)
}

watch(() => props.value, (to, from) => roll(from ?? 0, to), { immediate: true })
onBeforeUnmount(() => cancelAnimationFrame(frame))
</script>

<template>
  <span class="num">{{ shown }}</span>
</template>
