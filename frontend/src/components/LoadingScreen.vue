<script setup>
import { loading } from '@/lib/loader'
import LogoMark from './LogoMark.vue'
</script>

<template>
  <Transition name="loader">
    <div v-if="loading" class="loader" role="status" aria-live="polite">
      <div class="spinner">
        <span class="ring" aria-hidden="true" />
        <div class="mark"><LogoMark /></div>
      </div>
      <span class="visually-hidden">{{ $t('common.loading') }}</span>
    </div>
  </Transition>
</template>

<style scoped>
.loader {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  background:
    radial-gradient(28rem 28rem at 50% 50%, var(--glow), transparent 70%),
    var(--bg);
}

.spinner {
  position: relative;
  display: grid;
  place-items: center;
  width: 132px;
  height: 132px;
}

/* The "m." breathes inside the orbiting ring (a spinning letter reads oddly). */
.mark {
  width: 64px;
  height: 34px;
  animation: breathe 1.4s ease-in-out infinite;
}

@keyframes breathe {
  50% {
    transform: scale(0.88);
    opacity: 0.75;
  }
}

/* A thin accent arc orbiting the mark. */
.ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid color-mix(in srgb, var(--accent) 18%, transparent);
  border-top-color: var(--accent);
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.loader-enter-active,
.loader-leave-active {
  transition: opacity 0.25s ease, backdrop-filter 0.25s ease;
}

.loader-enter-from,
.loader-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .mark {
    animation: none;
  }
}
</style>
