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

  <AppVersion />
  <LoadingScreen />
</template>

<style>
.page-enter-active,
.page-leave-active {
  transition: opacity 0.2s ease;
}

.page-enter-from,
.page-leave-to {
  opacity: 0;
}
</style>
