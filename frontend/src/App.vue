<script setup>
import { Analytics } from '@vercel/analytics/vue'
import AppVersion from '@/components/AppVersion.vue'
import LoadingScreen from '@/components/LoadingScreen.vue'
</script>

<template>
  <Analytics />
  <RouterView v-slot="{ Component, route }">
    <Transition name="page" mode="out-in">
      <component :is="Component" :key="route.meta.viewKey ?? route.path" />
    </Transition>
  </RouterView>

  <!-- The signed-in shell shows the version in its header (desktop) and menu (phones) instead. -->
  <AppVersion v-if="$route.meta.viewKey !== 'app'" />
  <LoadingScreen />
</template>

<style>
.page-enter-active {
  transition: opacity 0.3s ease, transform 0.45s var(--ease);
}

.page-leave-active {
  transition: opacity 0.15s ease;
}

.page-enter-from {
  opacity: 0;
  transform: translateY(12px);
}

.page-leave-to {
  opacity: 0;
}
</style>
