<script setup>
import { Analytics } from '@vercel/analytics/vue'
import AppVersion from '@/components/AppVersion.vue'
import { consent } from '@/lib/consent'
import LoadingScreen from '@/components/LoadingScreen.vue'
</script>

<template>
  <!-- Vercel Web Analytics only after the visitor allows performance cookies (Cheklet banner). -->
  <Analytics v-if="consent.performance" />
  <RouterView v-slot="{ Component, route }">
    <Transition name="page" mode="out-in">
      <component :is="Component" :key="route.meta.viewKey ?? route.path" />
    </Transition>
  </RouterView>

  <!-- The signed-in shell shows the version inside its side nav instead. -->
  <AppVersion v-if="$route.meta.viewKey !== 'app'" />
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
