<script setup>
import { MoonIcon, SunIcon } from '@heroicons/vue/24/outline'
import { theme, toggleTheme } from '@/lib/theme'
</script>

<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="theme === 'dark' ? $t('common.themeToLight') : $t('common.themeToDark')"
    :title="theme === 'dark' ? $t('common.themeLight') : $t('common.themeDark')"
    @click="toggleTheme"
  >
    <SunIcon v-if="theme === 'dark'" aria-hidden="true" />
    <MoonIcon v-else aria-hidden="true" />
  </button>
</template>

<style scoped>
.theme-toggle {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: transparent;
  color: var(--fg);
  cursor: pointer;
  transition: border-color 0.2s, color 0.2s, transform 0.2s;
}

.theme-toggle:hover {
  border-color: var(--seagrass);
  color: var(--seagrass);
}

.theme-toggle:active {
  transform: scale(0.94);
}

.theme-toggle:focus-visible {
  outline: 2px solid var(--seagrass);
  outline-offset: 3px;
}

.theme-toggle svg {
  width: 18px;
  height: 18px;
}
</style>
