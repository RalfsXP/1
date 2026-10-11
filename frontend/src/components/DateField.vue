<script setup>
import { ref } from 'vue'
import { CalendarIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import DatePicker from '@/components/DatePicker.vue'
import { periodLabel } from '@/lib/periods'

// A single optional date: shows the value, opens the brand date picker inline when tapped.
const props = defineProps({
  label: { type: String, required: true },
  // Picker mode: an "until" for a weekly goal picks whole weeks, and so on.
  mode: { type: String, default: 'day' },
  min: { type: String, default: null },
  max: { type: String, default: null },
  series: { type: Object, default: null },
})
const value = defineModel({ type: String, default: '' })
const open = ref(false)

function onPick(iso) {
  value.value = iso
  open.value = false
}
</script>

<template>
  <div class="date-field">
    <div class="row">
      <button
        type="button"
        class="input trigger"
        :aria-expanded="open"
        :aria-label="value ? `${label}: ${periodLabel(mode, value)}` : label"
        @click="open = !open"
      >
        <CalendarIcon aria-hidden="true" />
        <span v-if="value" class="value">{{ periodLabel(mode, value) }}</span>
        <span v-else class="placeholder">—</span>
      </button>
      <button v-if="value" type="button" class="icon-btn" :aria-label="$t('picker.clear')" @click="value = ''">
        <XMarkIcon aria-hidden="true" />
      </button>
    </div>
    <DatePicker
      v-if="open"
      :model-value="value || null"
      :mode="mode"
      :min="min"
      :max="max"
      :series="series"
      @update:model-value="onPick"
    />
  </div>
</template>

<style scoped>
.date-field {
  display: grid;
  gap: 8px;
}

.row {
  display: flex;
  gap: 8px;
  align-items: center;
}

.row .icon-btn {
  width: 46px;
  height: 46px;
}

.trigger {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 10px;
  text-align: left;
  cursor: pointer;
}

.trigger svg {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  color: var(--seagrass);
}

.value {
  display: inline-block;
}

.value::first-letter {
  text-transform: uppercase;
}

.placeholder {
  color: var(--muted);
}
</style>
