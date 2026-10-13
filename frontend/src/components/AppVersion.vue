<script setup>
// Single source of truth: frontend/VERSION (inlined at build time).
import raw from '../../VERSION?raw'

// `inline` places it in the flow (the side nav) instead of the fixed corner.
defineProps({ inline: { type: Boolean, default: false } })

const version = raw.trim()
</script>

<template>
  <span class="version" :class="{ inline }" :title="`Merkis ${version}`">{{ version }}</span>
</template>

<style scoped>
.version {
  position: fixed;
  /* Placed from the top (see --app-h in main.css): bottom-anchored fixed boxes drift up in iOS web apps. */
  top: calc(var(--app-h) - max(12px, env(safe-area-inset-bottom)));
  left: max(16px, env(safe-area-inset-left));
  transform: translateY(-100%);
  z-index: 50;
  color: var(--muted);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  pointer-events: none;
  user-select: none;
}

.version.inline {
  position: static;
  transform: none;
}
</style>
