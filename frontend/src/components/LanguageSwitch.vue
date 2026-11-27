<script setup>
import { changeLocale } from '@/lib/auth'
import { LOCALES, locale } from '@/lib/i18n'
</script>

<template>
  <!-- Hidden while only one language is enabled (English only since v1.6.3, see lib/i18n.js LOCALES). -->
  <div v-if="LOCALES.length > 1" class="lang" role="group" :aria-label="$t('common.language')">
    <button
      v-for="code in LOCALES"
      :key="code"
      type="button"
      class="lang-option"
      :class="{ active: locale === code }"
      :aria-pressed="locale === code"
      :lang="code"
      @click="changeLocale(code)"
    >
      {{ code.toUpperCase() }}
    </button>
  </div>
</template>

<style scoped>
/* White pill; the chosen language is the ink pill. */
.lang {
  display: inline-flex;
  flex-shrink: 0;
  height: 44px;
  padding: 4px;
  border-radius: 999px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
}

.lang-option {
  min-width: 36px;
  padding: 0 10px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  cursor: pointer;
  transition: background-color 0.25s var(--ease), color 0.25s var(--ease);
}

@media (hover: hover) {
  .lang-option:hover {
    color: var(--fg);
  }
}

.lang-option.active {
  background: var(--accent);
  color: var(--on-accent);
}
</style>
