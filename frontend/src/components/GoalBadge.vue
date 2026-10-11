<script setup>
import { computed } from 'vue'
import { CheckIcon } from '@heroicons/vue/20/solid'
import { readableOn } from '@/lib/goals'
import { GOAL_ICONS } from '@/lib/icons'

// A goal as it appears in the calendar: a pill in its badge colour.
const props = defineProps({
  title: { type: String, required: true },
  color: { type: String, required: true },
  icon: { type: String, required: true },
  done: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

const style = computed(() => ({ '--badge': props.color, color: readableOn(props.color) }))
</script>

<template>
  <span class="badge" :class="{ done, compact }" :style="style" :title="compact ? title : undefined">
    <CheckIcon v-if="done" class="badge-icon" aria-hidden="true" />
    <!-- Compact (calendar cell) chips are colour-only to leave room for the title. -->
    <component :is="GOAL_ICONS[icon] ?? GOAL_ICONS.flag" v-else-if="!compact" class="badge-icon" aria-hidden="true" />
    <span class="badge-title">{{ title }}</span>
  </span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  min-width: 0;
  height: 30px;
  padding: 0 12px 0 9px;
  border-radius: 999px;
  background: var(--badge);
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
}

.badge.compact {
  display: flex;
  gap: 3px;
  width: 100%;
  height: 20px;
  padding: 0 7px;
  border-radius: 6px;
  font-size: 0.68rem;
}

.badge.compact.done {
  padding-left: 4px;
}

.badge.done {
  opacity: 0.5;
}

.badge.done .badge-title {
  text-decoration: line-through;
}

.badge-icon {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
}

.compact .badge-icon {
  width: 12px;
  height: 12px;
}

.badge-title {
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
