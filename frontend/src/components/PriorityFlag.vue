<script setup>
import { FlagIcon } from '@heroicons/vue/20/solid'

// Heroicons render aria-hidden by default: undo it so screen readers hear the priority,
// unless the flag sits next to its own text (`decorative`).
defineProps({
  priority: { type: String, required: true },
  decorative: { type: Boolean, default: false },
})
</script>

<template>
  <FlagIcon v-if="decorative" class="flag" :class="priority" aria-hidden="true" />
  <FlagIcon v-else class="flag" :class="priority" role="img" aria-hidden="false" :aria-label="$t(`priority.${priority}`)" />
</template>

<style scoped>
.flag {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
}

.low {
  color: var(--flag-low);
}

.high {
  color: var(--flag-high);
}

.important {
  color: var(--flag-important);
}
</style>
