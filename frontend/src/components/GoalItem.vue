<script setup>
import { computed } from 'vue'
import { CheckCircleIcon as DoneIcon } from '@heroicons/vue/24/solid'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import PriorityFlag from '@/components/PriorityFlag.vue'
import { badgeFor, readableOn } from '@/lib/goals'
import { GOAL_ICONS } from '@/lib/icons'
import { rangeLabel } from '@/lib/periods'
import { completionInfo, now, timeLeft } from '@/lib/timeLeft'

// One goal occurrence in a list: tick it off, or tap it to edit.
const props = defineProps({ occurrence: { type: Object, required: true } })
const emit = defineEmits(['toggle', 'edit'])

const goal = computed(() => props.occurrence.goal)
const badge = computed(() => badgeFor(goal.value))
const left = computed(() => (props.occurrence.done ? null : timeLeft(props.occurrence.deadline, now.value)))
const finished = computed(() => (props.occurrence.done ? completionInfo(props.occurrence) : null))
</script>

<template>
  <li class="item" :class="{ done: occurrence.done }">
    <button
      type="button"
      class="check"
      :aria-pressed="occurrence.done"
      :aria-label="$t('goal.toggle', { title: goal.title })"
      @click="emit('toggle', occurrence)"
    >
      <DoneIcon v-if="occurrence.done" aria-hidden="true" />
      <span v-else class="ring" />
    </button>

    <button type="button" class="body" @click="emit('edit', occurrence)">
      <span class="title">{{ goal.title }}</span>
      <span class="meta">
        <PriorityFlag :priority="goal.priority" />
        <ArrowPathIcon v-if="goal.repeats" class="repeat" role="img" aria-hidden="false" :aria-label="$t('goal.repeats')" />
        <span v-if="goal.period === 'custom'" class="range">{{ rangeLabel(occurrence.start, occurrence.end) }} ·</span>
        <span v-if="finished" class="when" :class="finished.kind">{{ finished.text }}</span>
        <span v-else class="when" :class="{ overdue: left.overdue, soon: left.soon }">
          <template v-if="left.overdue">{{ $t('goal.overdue') }} · </template>{{ left.text }}
        </span>
      </span>
    </button>

    <span class="dot" :style="{ background: badge.color, color: readableOn(badge.color) }" aria-hidden="true">
      <component :is="GOAL_ICONS[badge.icon] ?? GOAL_ICONS.flag" />
    </span>
  </li>
</template>

<style scoped>
.item {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 64px;
  padding: 6px 12px 6px 4px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
  transition: background-color 0.2s;
}

/* Done rows recede (no fill, muted title) but keep their status text readable. */
.item.done {
  background: transparent;
}

.done .title {
  color: var(--muted);
  text-decoration: line-through;
}

.done .dot {
  opacity: 0.45;
}

.check {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--seagrass);
  cursor: pointer;
}

.check svg {
  width: 28px;
  height: 28px;
}

.ring {
  width: 24px;
  height: 24px;
  border: 2px solid var(--muted);
  border-radius: 50%;
  transition: border-color 0.2s;
}

.check:hover .ring {
  border-color: var(--seagrass);
}

.check:focus-visible,
.body:focus-visible {
  outline: 2px solid var(--seagrass);
  outline-offset: -2px;
  border-radius: 12px;
}

.body {
  display: grid;
  flex: 1;
  gap: 4px;
  min-width: 0;
  padding: 6px 4px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.title {
  overflow: hidden;
  font-size: 0.95rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}


.meta {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  line-height: 16px;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.when.early {
  color: var(--seagrass);
}

.when.soon {
  color: var(--fg);
}

/* Overdue is spelled out in words; the red flag already marks urgency, so text stays neutral. */
.when.overdue {
  color: var(--fg);
  font-weight: 800;
}

.repeat {
  flex-shrink: 0;
  width: 14px;
  height: 16px;
}

.when {
  min-width: 0;
}

.range {
  flex-shrink: 0;
  white-space: nowrap;
}

.dot {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 30px;
  height: 30px;
  border-radius: 50%;
}

.dot svg {
  width: 16px;
  height: 16px;
}
</style>
