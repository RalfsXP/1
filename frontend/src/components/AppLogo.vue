<script setup>
// Two versions of the logo: white checkmark/text for dark mode, black for light.
// Both are rendered and CSS shows the one matching the theme, so switching is instant.
import logoDark from '@/assets/Logo.png'
import logoLight from '@/assets/Logo-light.png'
</script>

<template>
  <span class="logo">
    <img class="logo-img on-dark" :src="logoDark" alt="Merkis.app" width="998" height="828" />
    <img class="logo-img on-light" :src="logoLight" alt="" aria-hidden="true" width="998" height="828" />
  </span>
</template>

<style scoped>
.logo {
  display: block;
  width: 100%;
  height: 100%;
}

.logo-img {
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
