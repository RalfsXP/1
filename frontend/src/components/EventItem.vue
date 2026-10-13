<script setup>
import { computed } from 'vue'
import { countdown, dayUnit, daysUntil, eventBadge } from '@/lib/events'
import { readableOn } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { GOAL_ICONS } from '@/lib/icons'
import { dayMonth, weekdayName } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

/*
 * One event occurrence: badge, title, when (+ how often) and the countdown to it.
 * The whole row opens the event for editing (there's nothing to tick off).
 */
const props = defineProps({
  occurrence: { type: Object, required: true },
  // In the calendar's day panel the date is already the heading: no countdown pill there.
  compact: { type: Boolean, default: false },
})
const emit = defineEmits(['edit'])

const ev = computed(() => props.occurrence.event)
const badge = computed(() => eventBadge(ev.value))
const days = computed(() => daysUntil(props.occurrence.date, todayISO.value))
const when = computed(() =>
  [
    props.compact ? null : `${weekdayName(props.occurrence.date)}, ${dayMonth(props.occurrence.date, true)}`,
    ev.value.time ?? (props.compact ? t('events.allDay') : null),
    ev.value.repeat ? t(`periods.every.${ev.value.repeat}`) : null,
  ]
    .filter(Boolean)
    .join(' · '),
)
</script>

<template>
  <li class="item" :class="{ past: days < 0 }">
    <button type="button" class="row" @click="emit('edit', occurrence)">
      <span class="badge" :style="{ background: badge.color, color: readableOn(badge.color) }" aria-hidden="true">
        <component :is="GOAL_ICONS[badge.icon] ?? GOAL_ICONS.calendar" />
      </span>
      <span class="body">
        <span class="title">{{ ev.title }}</span>
        <span class="meta">{{ when }}</span>
      </span>
      <!-- Today / tomorrow / the day after: a word pill. Further out: the number of days, big. -->
      <template v-if="!compact">
        <span v-if="days >= 0 && days <= 2" class="pill" :class="{ today: days === 0 }">{{ countdown(occurrence.date, todayISO) }}</span>
        <span v-else-if="days > 2" class="count" :class="{ soon: days <= 7 }" :aria-label="countdown(occurrence.date, todayISO)">
          <span class="num">{{ days }}</span>
          <span class="unit">{{ dayUnit(days) }}</span>
        </span>
        <span v-else class="ago">{{ countdown(occurrence.date, todayISO) }}</span>
      </template>
    </button>
  </li>
</template>

<style scoped>
.item {
  border: 1px solid var(--glass-edge);
  border-radius: 20px;
  background: var(--surface);
  box-shadow: var(--glass-shadow);
}

.item.past {
  background: color-mix(in srgb, var(--surface) 55%, transparent);
  box-shadow: none;
}

.row {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
  min-height: 70px;
  padding: 12px 14px;
  border: 0;
  border-radius: inherit;
  background: transparent;
  color: var(--fg);
  text-align: left;
  cursor: pointer;
}

.row:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: -2px;
}

.badge {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 42px;
  height: 42px;
  border-radius: 14px;
}

.past .badge {
  opacity: 0.5;
}

.badge svg {
  width: 22px;
  height: 22px;
}

.body {
  display: grid;
  flex: 1;
  gap: 4px;
  min-width: 0;
}

.title {
  overflow: hidden;
  font-size: 1rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.past .title {
  color: var(--muted);
}

.meta {
  overflow: hidden;
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta::first-letter,
.pill::first-letter,
.ago::first-letter {
  text-transform: uppercase;
}

.pill {
  flex-shrink: 0;
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--accent);
  color: var(--on-accent);
  font-size: 0.8rem;
  font-weight: 700;
}

.pill.today {
  background: var(--highlight);
  color: var(--on-highlight);
}

.count {
  display: grid;
  flex-shrink: 0;
  justify-items: end;
  min-width: 48px;
  line-height: 1;
}

.num {
  font-size: 1.5rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
}

.count.soon .num {
  color: var(--accent);
}

.unit {
  margin-top: 3px;
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 600;
}

.ago {
  flex-shrink: 0;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 600;
}
</style>
