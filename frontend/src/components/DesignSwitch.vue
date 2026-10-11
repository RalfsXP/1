<script setup>
import { computed } from 'vue'
import { changeDesign } from '@/lib/auth'
import { DESIGNS, design } from '@/lib/design'

// A slider: the thumb glides to the chosen design. Each option previews its palette.
const PREVIEW = {
  default: ['var(--seagrass)', '#000000', '#ffffff'],
  new: ['var(--imperial)', 'var(--bluebell)', 'var(--magenta)', 'var(--ghost)'],
  ocean: ['var(--deepspace)', 'var(--bluegreen)', 'var(--amber)', 'var(--orange)'],
}

const index = computed(() => Math.max(0, DESIGNS.indexOf(design.value)))
</script>

<template>
  <div class="switch" role="radiogroup" :aria-label="$t('settings.design')" :style="{ '--count': DESIGNS.length, '--index': index }">
    <span class="thumb" aria-hidden="true" />
    <button
      v-for="d in DESIGNS"
      :key="d"
      type="button"
      role="radio"
      class="option"
      :aria-checked="design === d"
      @click="changeDesign(d)"
    >
      <span class="dots" aria-hidden="true">
        <span v-for="c in PREVIEW[d]" :key="c" :style="{ background: c }" />
      </span>
      <span class="label">{{ $t(`settings.designs.${d}`) }}</span>
    </button>
  </div>
</template>

<style scoped>
.switch {
  position: relative;
  display: grid;
  grid-template-columns: repeat(var(--count), 1fr);
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 999px;
}

/* The sliding pill behind the chosen option. */
.thumb {
  position: absolute;
  top: 4px;
  bottom: 4px;
  left: 4px;
  width: calc((100% - 8px) / var(--count));
  border-radius: 999px;
  background: var(--accent);
  transform: translateX(calc(var(--index) * 100%));
  transition: transform 0.35s cubic-bezier(0.2, 0.8, 0.2, 1), background-color 0.3s;
}

.option {
  position: relative;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 0;
  min-height: 42px;
  padding: 0 8px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: color 0.3s;
}

.option:hover {
  color: var(--fg);
}

.option[aria-checked='true'] {
  color: var(--on-accent);
}

.label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dots {
  display: inline-flex;
  flex-shrink: 0;
}

.dots span {
  width: 12px;
  height: 12px;
  margin-left: -3px;
  border: 1.5px solid var(--bg);
  border-radius: 50%;
}

.dots span:first-child {
  margin-left: 0;
}

/* Phones: palette above the name, so three options fit side by side. */
@media (max-width: 520px) {
  .switch {
    border-radius: 22px;
  }

  .thumb {
    border-radius: 18px;
  }

  .option {
    flex-direction: column;
    gap: 4px;
    min-height: 56px;
    border-radius: 18px;
    font-size: 0.78rem;
  }
}
</style>
