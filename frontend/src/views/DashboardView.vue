<script setup>
import { computed, ref, watch } from 'vue'
import { ArrowRightIcon, CheckIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import { user } from '@/lib/auth'
import { compareOccurrences, loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { PERIOD_TO_ROUTE, addDays, parseISO, periodEnd, periodStart, toISO, weekdayName } from '@/lib/periods'
import { now, todayISO } from '@/lib/timeLeft'

/*
 * Overview: six tiles (yesterday, today, this week / month / year, overdue),
 * the last 7 days of activity and what needs attention now. "Today" follows
 * the shared clock, so the page stays right past midnight.
 */
const todayDate = computed(() => parseISO(todayISO.value))
const yesterdayISO = computed(() => toISO(addDays(todayDate.value, -1)))

const range = computed(() => {
  const d = todayDate.value
  const from = [periodStart('year', d), periodStart('week', d), addDays(d, -8)].reduce((a, b) => (a < b ? a : b))
  const to = [periodEnd('year', d), periodEnd('week', d)].reduce((a, b) => (a > b ? a : b))
  return { from, to }
})

const loaded = ref(false)
const failed = ref(false)
watch(
  () => [toISO(range.value.from) + toISO(range.value.to), refreshTick.value],
  () =>
    loadRange(range.value.from, range.value.to)
      .then(() => (failed.value = false))
      .catch(() => (failed.value = true))
      .finally(() => (loaded.value = true)),
  { immediate: true },
)

const occurrences = computed(() => occurrencesBetween(range.value.from, range.value.to))

/** Occurrences that are "now": this day/week/month/year, or a custom period covering today. */
const current = computed(() =>
  occurrences.value.filter((o) =>
    o.goal.period === 'custom'
      ? o.start <= todayISO.value && o.end >= todayISO.value
      : o.start === toISO(periodStart(o.goal.period, todayDate.value)),
  ),
)

/**
 * Overdue: one-off goals past their deadline (until done), plus repeating goals missed in the
 * last 7 days — older misses of a habit would just pile up (a daily goal since January = hundreds).
 */
const OVERDUE_WINDOW = 7 * 24 * 3600 * 1000
const overdue = computed(() =>
  occurrences.value.filter((o) => {
    const due = o.deadline.getTime()
    if (o.done || due >= now.value) return false
    return !o.goal.repeats || now.value - due <= OVERDUE_WINDOW
  }),
)

function progressTile(key, items, to) {
  const done = items.filter((o) => o.done).length
  const total = items.length
  return { key, kind: 'progress', done, total, ratio: total ? done / total : 0, complete: total > 0 && done === total, to }
}

const tiles = computed(() => [
  progressTile(
    'yesterday',
    occurrences.value.filter((o) => o.goal.period === 'day' && o.start === yesterdayISO.value),
    { name: 'goals', params: { period: 'daily' }, query: { date: yesterdayISO.value } },
  ),
  ...['day', 'week', 'month', 'year'].map((period) =>
    progressTile(
      period,
      current.value.filter((o) => o.goal.period === period),
      { name: 'goals', params: { period: PERIOD_TO_ROUTE[period] } },
    ),
  ),
  // Overdue: a count, "complete" (all clear) when there is nothing late.
  { key: 'overdue', kind: 'count', count: overdue.value.length, complete: loaded.value && !failed.value && !overdue.value.length },
])

/**
 * Overdue one-off goals first (real tasks), then what's open now, then habits missed in
 * the last few days (newest first) — so old misses never push today's work off the list.
 */
const focus = computed(() => {
  const late = overdue.value.filter((o) => !o.goal.repeats).sort(compareOccurrences)
  const missed = overdue.value.filter((o) => o.goal.repeats).sort((a, b) => b.deadline - a.deadline)
  const lateKeys = new Set(overdue.value.map((o) => o.key))
  const open = current.value.filter((o) => !o.done && !lateKeys.has(o.key)).sort(compareOccurrences)
  return [...late, ...open, ...missed].slice(0, 8)
})

/* Activity: goals ticked off on each of the last 7 days. */
const completedByDay = computed(() => {
  const map = {}
  for (const o of occurrences.value) if (o.completedAt) map[toISO(o.completedAt)] = (map[toISO(o.completedAt)] ?? 0) + 1
  return map
})

const activity = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const date = addDays(todayDate.value, i - 6)
    const iso = toISO(date)
    return { iso, label: weekdayName(date, true), name: weekdayName(date), count: completedByDay.value[iso] ?? 0, isToday: iso === todayISO.value }
  }),
)
const activityMax = computed(() => Math.max(4, ...activity.value.map((d) => d.count)))
const activityTotal = computed(() => activity.value.reduce((sum, d) => sum + d.count, 0))
const hovered = ref(null)

const firstName = computed(() => user.value?.name?.split(' ')[0] ?? '')

/* Ring geometry for the progress tiles. */
const R = 24
const C = 2 * Math.PI * R

const formOpen = ref(false)
const editing = ref(null)
function edit(occurrence) {
  editing.value = occurrence.goal
  formOpen.value = true
}
function add() {
  editing.value = null
  formOpen.value = true
}
const toggle = (occurrence) => setDone(occurrence, !occurrence.done).catch(() => {})
</script>

<template>
  <div class="page wide">
    <h1 class="page-title">{{ firstName ? $t('dashboard.greeting', { name: firstName }) : $t('nav.home') }}</h1>

    <p v-if="failed" class="empty-state" role="alert">{{ $t('auth.errorGeneric') }}</p>

    <section class="tiles" :aria-label="$t('goal.progress')">
      <component
        :is="tile.to ? 'RouterLink' : 'div'"
        v-for="tile in tiles"
        :key="tile.key"
        class="tile"
        :class="{ complete: tile.complete, link: tile.to }"
        :to="tile.to || undefined"
      >
        <span class="tile-label">{{ $t(`dashboard.${tile.key}`) }}</span>
        <span class="tile-row">
          <span class="tile-value">
            <template v-if="tile.kind === 'progress'">{{ tile.done }}<span class="of">/{{ tile.total }}</span></template>
            <template v-else>{{ tile.count }}</template>
          </span>

          <!-- Everything done (or nothing late): the tile turns Seagrass with a check. -->
          <span v-if="tile.complete" class="tile-check" aria-hidden="true"><CheckIcon /></span>
          <svg v-else-if="tile.kind === 'progress'" class="ring" viewBox="0 0 60 60" aria-hidden="true">
            <circle class="ring-track" cx="30" cy="30" :r="R" />
            <circle class="ring-fill" cx="30" cy="30" :r="R" :stroke-dasharray="C" :stroke-dashoffset="C * (1 - tile.ratio)" />
          </svg>
          <ExclamationCircleIcon v-else class="tile-icon" aria-hidden="true" />
        </span>
      </component>
    </section>

    <section class="card">
      <header class="card-head">
        <h2 class="section-title">{{ $t('dashboard.activity') }}</h2>
        <span class="card-meta">{{ activityTotal }}</span>
      </header>
      <!-- One series, so no legend: the title names it. Hover/tap a bar for its value. -->
      <div class="bars" @mouseleave="hovered = null">
        <button
          v-for="(d, i) in activity"
          :key="d.iso"
          type="button"
          class="bar-slot"
          :class="{ today: d.isToday }"
          :aria-label="`${d.name}: ${$t('dashboard.completedCount', { n: d.count })}`"
          @mouseenter="hovered = i"
          @focus="hovered = i"
          @blur="hovered = null"
          @click="hovered = i"
        >
          <span v-if="hovered === i" class="tip" aria-hidden="true">{{ $t('dashboard.completedCount', { n: d.count }) }}</span>
          <span class="bar-track">
            <span class="bar" :style="{ height: `${(d.count / activityMax) * 100}%` }" />
          </span>
          <span class="bar-label" aria-hidden="true">{{ d.label }}</span>
        </button>
      </div>
    </section>

    <section class="focus">
      <header class="card-head">
        <h2 class="section-title">{{ $t('dashboard.focus') }}</h2>
        <RouterLink class="more" :to="{ name: 'calendar' }">
          {{ $t('nav.calendar') }}<ArrowRightIcon aria-hidden="true" />
        </RouterLink>
      </header>
      <HoldHint v-if="focus.length" class="hint" />
      <ul v-if="focus.length" class="list">
        <GoalItem v-for="occ in focus" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
      </ul>
      <div v-else-if="loaded && !failed" class="empty-state">
        <span>{{ $t('dashboard.allDone') }}</span>
        <button type="button" class="btn btn-primary" @click="add">{{ $t('goal.new') }}</button>
      </div>
    </section>

    <GoalForm :open="formOpen" :goal="editing" :defaults="{ period: 'day', date: todayISO }" @close="formOpen = false" />
  </div>
</template>

<style scoped>
/* ---------- Tiles: 2 × 3 on phones, 3 × 2 from tablet up ---------- */
.tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.tile {
  display: grid;
  align-content: space-between;
  gap: 10px;
  min-width: 0;
  min-height: 108px;
  padding: 14px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
  color: var(--fg);
  text-decoration: none;
  transition: border-color 0.2s, background-color 0.3s, color 0.3s, transform 0.2s;
}

.tile.link:hover {
  border-color: var(--accent);
}

.tile.link:active {
  transform: scale(0.98);
}

.tile-label {
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.3;
}

.tile-row {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
}

.tile-value {
  font-size: 1.7rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
  line-height: 1;
}

.of {
  color: var(--muted);
  font-size: 1rem;
}

.ring {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  transform: rotate(-90deg);
}

.ring circle {
  fill: none;
  stroke-width: 6;
}

.ring-track {
  stroke: var(--line);
}

.ring-fill {
  stroke: var(--accent);
  stroke-linecap: round;
  transition: stroke-dashoffset 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.tile-icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  color: var(--muted);
}

/* Complete: the whole tile goes Seagrass, black text (7:1), a black disc with a Seagrass check. */
.tile.complete {
  border-color: var(--accent);
  background: var(--accent);
  color: var(--on-accent);
}

.tile.complete .tile-label,
.tile.complete .of {
  color: color-mix(in srgb, var(--on-accent) 70%, var(--accent));
}

.tile.complete.link:hover {
  border-color: var(--on-accent);
}

.tile-check {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--on-accent);
  color: var(--accent);
  animation: pop 0.35s cubic-bezier(0.2, 0.8, 0.2, 1.4);
}

.tile-check svg {
  width: 24px;
  height: 24px;
  stroke-width: 3;
}

@keyframes pop {
  from {
    transform: scale(0.4);
    opacity: 0;
  }
}

/* ---------- Activity ---------- */
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.card-meta {
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* Thin bars, rounded data ends, recessive baseline. */
.bars {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  height: 140px;
}

.bar-slot {
  position: relative;
  display: grid;
  grid-template-rows: 1fr auto;
  gap: 6px;
  min-width: 0;
  padding: 0;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--muted);
  cursor: default;
}

.bar-track {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  border-bottom: 1px solid var(--line);
}

.bar {
  display: block;
  width: min(22px, 70%);
  min-height: 2px;
  border-radius: 4px 4px 0 0;
  background: color-mix(in srgb, var(--link) 55%, transparent);
  transition: height 0.5s cubic-bezier(0.2, 0.8, 0.2, 1), background-color 0.2s;
}

.bar-slot.today .bar,
.bar-slot:hover .bar,
.bar-slot:focus-visible .bar {
  background: var(--accent);
}

.bar-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-align: center;
}

.bar-slot.today .bar-label {
  color: var(--fg);
}

.tip {
  position: absolute;
  bottom: calc(100% - 6px);
  left: 50%;
  z-index: 2;
  padding: 4px 8px;
  border-radius: 8px;
  background: var(--fg);
  color: var(--bg);
  font-size: 0.7rem;
  font-weight: 700;
  white-space: nowrap;
  transform: translateX(-50%);
  pointer-events: none;
}

/* ---------- Focus ---------- */
.hint {
  margin-bottom: 10px;
}

.more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--accent);
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
}

.more:hover {
  text-decoration: underline;
}

.more svg {
  width: 16px;
  height: 16px;
}

@media (min-width: 640px) {
  .tiles {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (prefers-reduced-motion: reduce) {
  .tile-check {
    animation: none;
  }
}
</style>
