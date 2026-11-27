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
  width: 38px;
  height: 38px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.06);
  cursor: pointer;
  transition: transform 0.3s var(--spring), box-shadow 0.2s;
}

@media (hover: hover) {
  .swatch:hover {
    transform: scale(1.1);
  }
}

.swatch:active {
  transform: scale(0.9);
}

.swatch[aria-pressed='true'],
.swatch.custom.active {
  box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px var(--fg);
  transform: scale(1.06);
}

.swatch svg {
  animation: pop 0.35s var(--spring);
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
  border: 0;
  border-radius: 14px;
  background: var(--surface-2);
  color: var(--fg);
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s, transform 0.25s var(--spring);
}

.icon-choice:active {
  transform: scale(0.88);
}

.icon-choice svg {
  width: 20px;
  height: 20px;
}

@media (hover: hover) {
  .icon-choice:hover {
    background: var(--line);
  }
}

.icon-choice[aria-pressed='true'] {
  background: var(--accent);
  color: var(--on-accent);
}
</style>
