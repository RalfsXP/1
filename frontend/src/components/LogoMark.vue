<script setup>
// Checkmark only (no wordmark), in the version matching the current theme.
import markDark from '@/assets/LogoMark.png'
import markLight from '@/assets/LogoMark-light.png'
</script>

<template>
  <span class="mark">
    <img class="mark-img on-dark" :src="markDark" alt="" width="360" height="360" />
    <img class="mark-img on-light" :src="markLight" alt="" width="360" height="360" />
  </span>
</template>

<style scoped>
.mark {
  display: block;
  width: 100%;
  height: 100%;
}

.mark-img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.on-light {
  display: none;
}

:root[data-theme='light'] .on-dark {
  display: none;
}

:root[data-theme='light'] .on-light {
  display: block;
}
</style>
