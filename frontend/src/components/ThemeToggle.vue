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
/* Glass disc, same as the language pill next to it. */
.theme-toggle {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--glass-edge);
  border-radius: 50%;
  background: var(--surface);
  color: var(--fg);
  box-shadow: var(--glass-shadow);
  -webkit-backdrop-filter: blur(20px) saturate(1.4);
  backdrop-filter: blur(20px) saturate(1.4);
  cursor: pointer;
  transition: border-color 0.2s, transform 0.2s;
}

.theme-toggle:hover {
  border-color: var(--accent);
}

.theme-toggle:active {
  transform: scale(0.94);
}

.theme-toggle:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 3px;
}

.theme-toggle svg {
  width: 20px;
  height: 20px;
}
</style>
