<script setup>
// The mark: the wordmark's "m" plus its highlight-colour dot. Follows the text colour.
</script>

<template>
  <svg class="mark" viewBox="6.1 -55.1 113.5 55.1" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
    <path fill="currentColor" d="M25.10 0L6.10 0L6.10-54.20L24.20-54.20L24.20-48.50Q26.20-50.70 28.80-52.20L28.80-52.20Q34-55.10 40.40-55.10L40.40-55.10Q47.70-55.10 53.30-51.40L53.30-51.40Q56-49.50 57.90-46.60L57.90-46.60Q60.20-49.40 63.30-51.40L63.30-51.40Q69.10-55.10 76.70-55.10L76.70-55.10Q83-55.10 87.95-52.55Q92.90-50 95.75-44.70Q98.60-39.40 98.60-31L98.60-31L98.60 0L79.60 0L79.60-27.90Q79.60-33.70 77.40-36.35Q75.20-39 71.40-39L71.40-39Q68.70-39 66.50-37.70Q64.30-36.40 63.10-33.75Q61.90-31.10 61.90-26.90L61.90-26.90L61.90 0L42.90 0L42.90-27.90Q42.90-33.70 40.75-36.35Q38.60-39 34.70-39L34.70-39Q31.90-39 29.75-37.70Q27.60-36.40 26.35-33.75Q25.10-31.10 25.10-26.90L25.10-26.90L25.10 0Z" />
    <circle class="dot" cx="112.1" cy="-7.5" r="7.5" />
  </svg>
</template>

<style scoped>
.mark {
  display: block;
  width: 100%;
  height: 100%;
  color: var(--fg);
}

.dot {
  fill: var(--highlight);
}
</style>
