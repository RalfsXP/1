<script setup>
import { changeLocale } from '@/lib/auth'
import { LOCALES, locale } from '@/lib/i18n'
</script>

<template>
  <div class="lang" role="group" :aria-label="$t('common.language')">
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
/* Glass pill; the chosen language is a raised inner pill. */
.lang {
  display: inline-flex;
  flex-shrink: 0;
  height: 40px;
  padding: 3px;
  border: 1px solid var(--glass-edge);
  border-radius: 999px;
  background: var(--surface);
  box-shadow: var(--glass-shadow);
  -webkit-backdrop-filter: blur(20px) saturate(1.4);
  backdrop-filter: blur(20px) saturate(1.4);
}

.lang-option {
  min-width: 36px;
  padding: 0 9px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s, box-shadow 0.2s;
}

.lang-option:hover {
  color: var(--fg);
}

.lang-option.active {
  background: var(--pill-active);
  box-shadow: 0 2px 8px rgb(10 33 40 / 0.12);
  color: var(--fg);
}

.lang-option:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
</style>
