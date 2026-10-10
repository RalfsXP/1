<script setup>
// Single source of truth: frontend/VERSION (inlined at build time).
import raw from '../../VERSION?raw'

const version = raw.trim()
</script>

<template>
  <span class="version" :title="`Merkis ${version}`">{{ version }}</span>
</template>

<style scoped>
.version {
  position: fixed;
  left: max(16px, env(safe-area-inset-left));
  bottom: max(12px, env(safe-area-inset-bottom));
  z-index: 50;
  color: var(--muted);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  pointer-events: none;
  user-select: none;
}
</style>
