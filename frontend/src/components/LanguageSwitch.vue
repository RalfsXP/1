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
.lang {
  display: inline-flex;
  flex-shrink: 0;
  height: 34px;
  padding: 3px;
  border: 1px solid var(--line);
  border-radius: 999px;
}

.lang-option {
  min-width: 34px;
  padding: 0 8px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.lang-option:hover {
  color: var(--seagrass);
}

.lang-option.active {
  background: var(--seagrass);
  color: var(--black);
}

.lang-option:focus-visible {
  outline: 2px solid var(--seagrass);
  outline-offset: 2px;
}
</style>
