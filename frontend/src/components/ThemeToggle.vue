<script setup>
import { theme, toggleTheme } from '@/lib/theme'
</script>

<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="theme === 'dark' ? 'Ieslēgt gaišo režīmu' : 'Ieslēgt tumšo režīmu'"
    :title="theme === 'dark' ? 'Gaišais režīms' : 'Tumšais režīms'"
    @click="toggleTheme"
  >
    <!-- Sun (shown in dark mode) -->
    <svg v-if="theme === 'dark'" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="4.2" />
      <path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6" />
    </svg>
    <!-- Moon (shown in light mode) -->
    <svg v-else viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" />
    </svg>
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
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>
