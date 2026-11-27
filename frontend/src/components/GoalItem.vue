<script setup>
import { computed } from 'vue'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import PriorityFlag from '@/components/PriorityFlag.vue'
import { badgeFor, justTicked, readableOn } from '@/lib/goals'
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
// The pop + drawn check only right after ticking (also when the row reappears in "Completed"), not on every page visit.
const ticked = computed(() => props.occurrence.done && justTicked(props.occurrence.key))
</script>

<template>
  <li
    class="item"
    :class="{ done: occurrence.done, ticked, touch: touchUI, pressing: press.pressing.value }"
    :style="{ '--tint': badge.color }"
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
      <span class="box">
        <svg v-if="occurrence.done" viewBox="0 0 24 24" aria-hidden="true"><path d="m5.5 12.5 4.2 4.2 8.8-9.4" pathLength="1" /></svg>
      </span>
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

    <span class="tile" :style="{ background: badge.color, color: readableOn(badge.color) }" aria-hidden="true">
      <component :is="GOAL_ICONS[badge.icon] ?? GOAL_ICONS.flag" />
    </span>
  </li>
</template>

<style scoped>
/* A white row; done rows take on a wash of their badge colour. */
.item {
  display: flex;
  align-items: center;
  gap: 2px;
  min-height: 72px;
  padding: 6px 12px 6px 6px;
  border-radius: 24px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  transition: background-color 0.35s var(--ease), box-shadow 0.35s var(--ease), transform 0.25s var(--spring);
}

.item.done {
  background: color-mix(in srgb, var(--tint) var(--wash), var(--surface));
  box-shadow: none;
}

/* Touch: the row is one press target; a hold fills the box, then edit opens. */
.item.touch {
  -webkit-touch-callout: none;
  user-select: none;
}

.item.pressing {
  transform: scale(0.97);
}

.item.pressing .box {
  animation: hold-fill 450ms linear forwards;
}

@keyframes hold-fill {
  to {
    border-color: var(--accent);
    box-shadow: inset 0 0 0 14px var(--accent);
  }
}

.check {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 50px;
  height: 50px;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
}

.box {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border: 2px dashed color-mix(in srgb, var(--fg) 28%, transparent);
  border-radius: 50%;
  color: var(--on-accent);
  transition: border-color 0.2s, background-color 0.25s var(--ease), transform 0.25s var(--spring);
}

@media (hover: hover) {
  .check:hover .box {
    border-color: var(--fg);
    border-style: solid;
  }
}

.check:active .box {
  transform: scale(0.85);
}

/* Ticked: the box pops into an ink disc and the check draws itself. */
.done .box {
  border: 0;
  background: var(--accent);
}

.ticked .box {
  animation: pop 0.45s var(--spring);
}

.box svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 1;
}

.ticked .box svg {
  animation: tick 0.35s var(--ease) 0.12s backwards;
}

@keyframes tick {
  from {
    stroke-dashoffset: 1;
  }
}

.check:focus-visible,
.body:focus-visible {
  outline: 2px solid var(--link);
  outline-offset: -2px;
  border-radius: 14px;
}

.body {
  display: grid;
  flex: 1;
  gap: 5px;
  min-width: 0;
  padding: 6px 6px 6px 2px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.title {
  overflow: hidden;
  font-size: 1.02rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
  transition: color 0.3s;
}

.done .title {
  color: var(--muted);
  text-decoration: line-through;
  text-decoration-thickness: 1.5px;
}

.meta {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  line-height: 16px;
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 600;
}

.when {
  min-width: 0;
}

.when.early {
  color: var(--flag-low);
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

.range {
  flex-shrink: 0;
  white-space: nowrap;
}

/* The badge: a pastel tile with its icon. */
.tile {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 14px;
  transition: transform 0.3s var(--spring);
}

.done .tile {
  transform: rotate(-8deg) scale(0.92);
}

.tile svg {
  width: 20px;
  height: 20px;
}
</style>
