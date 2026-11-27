<script setup>
// The mark (v1.8): a tilted lavender sticker with an ink "m" and a butter dot. Fixed colours in both
// themes (it's a sticker, not text). Same geometry as scripts/build-logo.mjs, which renders the icons from it.
// `animated` gives it a gentle wobble (loading screen).
defineProps({ animated: { type: Boolean, default: false } })
</script>

<template>
  <svg class="mark" :class="{ animated }" viewBox="0 0 52 52" aria-hidden="true">
    <g class="tile">
      <rect x="4" y="4" width="44" height="44" rx="15" />
      <path d="M14.5 34V23.5c0-2.4 1.8-4.2 4-4.2s4 1.8 4 4.2V34M22.5 23.5c0-2.4 1.8-4.2 4-4.2s4 1.8 4 4.2V34" />
    </g>
    <circle class="dot" cx="40" cy="36" r="6.5" />
  </svg>
</template>

<style scoped>
.mark {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}

.tile {
  transform: rotate(-8deg);
  transform-origin: 26px 26px;
}

rect {
  fill: var(--lav);
  stroke: var(--ink);
  stroke-width: 2.5;
}

path {
  fill: none;
  stroke: var(--ink);
  stroke-width: 3.6;
  stroke-linecap: round;
}

.dot {
  fill: var(--butter);
  stroke: var(--ink);
  stroke-width: 2.5;
}

.animated .tile {
  animation: wobble 1.6s var(--ease) infinite;
}

.animated .dot {
  animation: bounce 1.6s var(--ease) infinite;
  transform-origin: 40px 42px;
}

@keyframes wobble {
  0%,
  100% {
    transform: rotate(-8deg);
  }
  35% {
    transform: rotate(6deg) scale(1.04);
  }
  60% {
    transform: rotate(-12deg);
  }
}

@keyframes bounce {
  0%,
  100% {
    transform: translateY(0);
  }
  30% {
    transform: translateY(-9px) scale(1.05, 0.95);
  }
  50% {
    transform: translateY(0) scale(1.12, 0.86);
  }
  62% {
    transform: translateY(-2px);
  }
}
</style>
