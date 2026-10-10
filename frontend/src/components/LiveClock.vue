<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

// Latvian date + time, e.g. "1. novembris 20:23".
const MONTHS_LV = [
  'janvāris', 'februāris', 'marts', 'aprīlis', 'maijs', 'jūnijs',
  'jūlijs', 'augusts', 'septembris', 'oktobris', 'novembris', 'decembris',
]

const now = ref(new Date())
let timer

const pad = (n) => String(n).padStart(2, '0')
const label = computed(() => {
  const d = now.value
  return `${d.getDate()}. ${MONTHS_LV[d.getMonth()]} ${pad(d.getHours())}:${pad(d.getMinutes())}`
})

onMounted(() => {
  timer = setInterval(() => (now.value = new Date()), 1000)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <time class="clock" :datetime="now.toISOString()">{{ label }}</time>
</template>

<style scoped>
/* Montserrat ExtraBold, the wordmark's typeface, in Seagrass. */
.clock {
  color: var(--seagrass);
  font-family: Montserrat, ui-sans-serif, system-ui, sans-serif;
  font-size: 0.9rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.01em;
  white-space: nowrap;
}
</style>
