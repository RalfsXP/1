<script setup>
import { computed } from 'vue'
import { CheckCircleIcon as DoneIcon } from '@heroicons/vue/24/solid'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import PriorityFlag from '@/components/PriorityFlag.vue'
import { badgeFor, readableOn } from '@/lib/goals'
import { GOAL_ICONS } from '@/lib/icons'
import { rangeLabel } from '@/lib/periods'
import { touchUI } from '@/lib/input'
import { usePress } from '@/lib/press'
import { completionInfo, now, timeLeft } from '@/lib/timeLeft'

/*
 * One goal occurrence in a list.
 * Desktop: the round check ticks it, clicking the row edits.
 * Touch:   tapping anywhere on the row ticks it, holding it opens edit.
 */
const props = defineProps({ occurrence: { type: Object, required: true } })
const emit = defineEmits(['toggle', 'edit'])

const press = usePress({
  onTap: () => emit('toggle', props.occurrence),
  onHold: () => emit('edit', props.occurrence),
})
const onCheck = (e) => (touchUI.value ? press.click(e) : emit('toggle', props.occurrence))
const onBody = (e) => (touchUI.value ? press.click(e) : emit('edit', props.occurrence))

const goal = computed(() => props.occurrence.goal)
const badge = computed(() => badgeFor(goal.value))
const left = computed(() => (props.occurrence.done ? null : timeLeft(props.occurrence.deadline, now.value)))
const finished = computed(() => (props.occurrence.done ? completionInfo(props.occurrence) : null))
</script>

<template>
  <li
    class="item"
    :class="{ done: occurrence.done, touch: touchUI, pressing: press.pressing.value }"
    @pointerdown="touchUI && press.down($event)"
    @pointermove="press.move"
    @pointerup="press.up"
    @pointercancel="press.up"
    @contextmenu="touchUI && $event.preventDefault()"
  >
    <button
      type="button"
      class="check"
      :aria-pressed="occurrence.done"
      :aria-label="$t('goal.toggle', { title: goal.title })"
      @click="onCheck"
    >
      <DoneIcon v-if="occurrence.done" aria-hidden="true" />
      <span v-else class="ring" />
    </button>

    <button type="button" class="body" @click="onBody">
      <span class="title">{{ goal.title }}</span>
      <span v-if="touchUI" class="visually-hidden">{{ $t('goal.holdHint') }}</span>
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
/* Touch: the row is one press target; a hold fills the ring, then edit opens. */
.item.touch {
  -webkit-touch-callout: none;
  user-select: none;
  transition: background-color 0.2s, transform 0.2s;
}

.item.pressing {
  transform: scale(0.98);
}

.item.pressing .ring {
  animation: hold-fill 450ms linear forwards;
}

@keyframes hold-fill {
  to {
    border-color: var(--accent);
    box-shadow: inset 0 0 0 12px var(--accent);
  }
}

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
  color: var(--accent);
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
  border-color: var(--accent);
}

.check:focus-visible,
.body:focus-visible {
  outline: 2px solid var(--accent);
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
  color: var(--accent);
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
