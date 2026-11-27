<script setup>
import LogoMark from './LogoMark.vue'

// Brand lockup (v1.8): the sticker mark + "mērķis" set in Bricolage Grotesque ExtraBold.
// The size comes from the parent's font-size (the mark is 1.75em).
</script>

<template>
  <span class="logo">
    <LogoMark class="logo-mark" />
    <span class="word">mērķis</span>
  </span>
</template>

<style scoped>
.logo {
  display: inline-flex;
  align-items: center;
  gap: 0.4em;
  color: var(--fg);
  font-family: var(--font-display);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1;
}

.logo-mark {
  width: 1.75em;
  height: 1.75em;
  flex-shrink: 0;
}

.word {
  padding-bottom: 0.08em;
}
</style>
