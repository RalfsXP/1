<script setup>
import { loading } from '@/lib/loader'
import LogoMark from './LogoMark.vue'
</script>

<template>
  <Transition name="loader">
    <div v-if="loading" class="loader" role="status" aria-live="polite">
      <div class="mark"><LogoMark animated /></div>
      <span class="dots" aria-hidden="true"><i /><i /><i /></span>
      <span class="visually-hidden">{{ $t('common.loading') }}</span>
    </div>
  </Transition>
</template>

<style scoped>
.loader {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: var(--app-h);
  z-index: 100;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 26px;
  background-color: var(--bg);
}

/* The sticker wobbles while its dot bounces; three pastel dots pulse underneath. */
.mark {
  width: 88px;
  height: 88px;
}

.dots {
  display: flex;
  gap: 8px;
}

.dots i {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--lav);
  animation: pulse 1.1s var(--ease) infinite;
}

.dots i:nth-child(2) {
  background: var(--mint);
  animation-delay: 0.15s;
}

.dots i:nth-child(3) {
  background: var(--peach);
  animation-delay: 0.3s;
}

@keyframes pulse {
  0%,
  100% {
    transform: translateY(0) scale(1);
  }
  40% {
    transform: translateY(-8px) scale(1.15);
  }
}

.loader-enter-active,
.loader-leave-active {
  transition: opacity 0.3s ease;
}

.loader-enter-active .mark {
  transition: transform 0.5s var(--spring);
}

.loader-enter-from,
.loader-leave-to {
  opacity: 0;
}

.loader-enter-from .mark {
  transform: scale(0.6) rotate(-20deg);
}
</style>
