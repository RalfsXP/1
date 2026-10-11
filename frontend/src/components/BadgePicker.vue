<script setup>
import { CheckIcon } from '@heroicons/vue/20/solid'
import { computed } from 'vue'
import { SWATCHES, readableOn } from '@/lib/goals'
import { GOAL_ICONS, ICON_NAMES } from '@/lib/icons'

// Colour (presets + any custom hex) and icon for a goal badge.
const color = defineModel('color', { type: String, required: true })
const icon = defineModel('icon', { type: String, required: true })

// The "any colour" ring is built from the presets, so it never introduces hues of its own.
const rainbow = computed(() => ({ background: `conic-gradient(${[...SWATCHES, SWATCHES[0]].join(', ')})` }))
</script>

<template>
  <div class="picker">
    <div class="form-field">
      <span class="form-label">{{ $t('goal.color') }}</span>
      <div class="swatches" role="group" :aria-label="$t('goal.color')">
        <button
          v-for="(swatch, i) in SWATCHES"
          :key="swatch"
          type="button"
          class="swatch"
          :style="{ background: swatch, color: readableOn(swatch) }"
          :aria-label="$t('colors')[i]"
          :aria-pressed="color.toLowerCase() === swatch"
          @click="color = swatch"
        >
          <CheckIcon v-if="color.toLowerCase() === swatch" aria-hidden="true" />
        </button>
        <label
          class="swatch custom"
          :class="{ active: !SWATCHES.includes(color.toLowerCase()) }"
          :style="!SWATCHES.includes(color.toLowerCase()) ? { background: color } : rainbow"
          :title="$t('goal.customColor')"
        >
          <input v-model="color" type="color" :aria-label="$t('goal.customColor')" />
        </label>
      </div>
    </div>

    <div class="form-field">
      <span class="form-label">{{ $t('goal.icon') }}</span>
      <div class="icons" role="group" :aria-label="$t('goal.icon')">
        <button
          v-for="name in ICON_NAMES"
          :key="name"
          type="button"
          class="icon-choice"
          :aria-label="$t(`icons.${name}`)"
          :aria-pressed="icon === name"
          @click="icon = name"
        >
          <component :is="GOAL_ICONS[name]" aria-hidden="true" />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.picker {
  display: grid;
  gap: 18px;
}

.swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.swatch {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 50%;
  cursor: pointer;
  transition: transform 0.15s;
}

.swatch:hover {
  transform: scale(1.08);
}

.swatch:active {
  transform: scale(0.92);
}

.swatch[aria-pressed='true'],
.swatch.custom.active {
  box-shadow: 0 0 0 2px var(--bg), 0 0 0 4px var(--fg);
}

.swatch svg {
  width: 18px;
  height: 18px;
}

/* "Any colour": a ring of the presets (see `rainbow`); the picked colour replaces it. */
.swatch.custom {
  position: relative;
}

.swatch.custom input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.icons {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(42px, 1fr));
  gap: 6px;
}

.icon-choice {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: transparent;
  color: var(--fg);
  cursor: pointer;
  transition: border-color 0.2s, background-color 0.2s, color 0.2s;
}

.icon-choice svg {
  width: 20px;
  height: 20px;
}

.icon-choice:hover {
  border-color: var(--accent);
}

.icon-choice[aria-pressed='true'] {
  border-color: var(--accent);
  background: var(--accent);
  color: var(--on-accent);
}
</style>
