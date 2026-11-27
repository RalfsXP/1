<script setup>
import { MoonIcon, SunIcon } from '@heroicons/vue/24/outline'
import { theme, toggleTheme } from '@/lib/theme'
</script>

<template>
  <button
    type="button"
    class="icon-btn theme-toggle"
    :aria-label="theme === 'dark' ? $t('common.themeToLight') : $t('common.themeToDark')"
    :title="theme === 'dark' ? $t('common.themeLight') : $t('common.themeDark')"
    @click="toggleTheme"
  >
    <Transition name="swap" mode="out-in">
      <SunIcon v-if="theme === 'dark'" key="sun" aria-hidden="true" />
      <MoonIcon v-else key="moon" aria-hidden="true" />
    </Transition>
  </button>
</template>

<style scoped>
/* A white disc; the icon spins over when the theme flips. */
.swap-enter-active,
.swap-leave-active {
  transition: transform 0.3s var(--spring), opacity 0.15s ease;
}

.swap-enter-from {
  transform: rotate(-90deg) scale(0.4);
  opacity: 0;
}

.swap-leave-to {
  transform: rotate(90deg) scale(0.4);
  opacity: 0;
}
</style>
