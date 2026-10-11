<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { locale, t } from '@/lib/i18n'

const now = ref(new Date())
let timer

const pad = (n) => String(n).padStart(2, '0')
// "1. novembris 20:23" in Latvian, "1 November 20:23" in English.
const label = computed(() => {
  const d = now.value
  const day = locale.value === 'lv' ? `${d.getDate()}.` : `${d.getDate()}`
  return `${day} ${t('common.months')[d.getMonth()]} ${pad(d.getHours())}:${pad(d.getMinutes())}`
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
