<script setup>
import { computed, ref, watch } from 'vue'
import { ArrowRightIcon, CheckIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import { user } from '@/lib/auth'
import { compareOccurrences, loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { PERIOD_TO_ROUTE, addDays, dayMonth, parseISO, periodEnd, periodLabel, periodStart, toISO, weekdayName } from '@/lib/periods'
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

const SHORT_LABEL = ['week', 'month', 'year']
const todayLabel = computed(() => periodLabel('day', todayISO.value))
const todayShort = computed(() => dayMonth(todayISO.value, true))

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
    <header class="page-head">
      <div>
        <h1 class="page-title">{{ firstName ? $t('dashboard.greeting', { name: firstName }) : $t('nav.home') }}</h1>
        <p class="page-sub">{{ todayLabel }}</p>
      </div>
    </header>

    <p v-if="failed" class="empty-state" role="alert">{{ $t('auth.errorGeneric') }}</p>

    <!-- Today is the big tile; yesterday + overdue, then this week / month / year under it. -->
    <section class="tiles" :aria-label="$t('goal.progress')">
      <component
        :is="tile.to ? 'RouterLink' : 'div'"
        v-for="tile in tiles"
        :key="tile.key"
        class="tile"
        :class="[`t-${tile.key}`, { complete: tile.complete, link: tile.to }]"
        :to="tile.to || undefined"
      >
        <span class="tile-top">
          <!-- The three small tiles use the short "This week / month / year". -->
          <span class="tile-label">{{ SHORT_LABEL.includes(tile.key) ? $t(`periods.this.${tile.key}`) : $t(`dashboard.${tile.key}`) }}</span>
          <span v-if="tile.key === 'day'" class="chip">{{ todayShort }}</span>
          <!-- Everything done (or nothing late): the tile turns accent with a check. -->
          <CheckIcon v-else-if="tile.complete" class="tile-icon check" aria-hidden="true" />
          <ExclamationCircleIcon v-else-if="tile.kind === 'count'" class="tile-icon warn" aria-hidden="true" />
        </span>
        <span class="tile-value">
          <template v-if="tile.kind === 'progress'">{{ tile.done }}<span class="of">/{{ tile.total }}</span></template>
          <template v-else>{{ tile.count }}</template>
        </span>
        <span v-if="tile.kind === 'progress' && tile.key !== 'yesterday'" class="meter" aria-hidden="true">
          <span class="meter-fill" :style="{ width: `${tile.ratio * 100}%` }" />
        </span>
      </component>
    </section>

    <section class="card activity">
      <header class="card-head">
        <h2 class="card-title">{{ $t('dashboard.activity') }}</h2>
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
            <span class="bar" :style="{ height: `${Math.max(14, (d.count / activityMax) * 100)}%` }" />
          </span>
          <span class="bar-label" aria-hidden="true">{{ d.label }}</span>
        </button>
      </div>
    </section>

    <section class="focus">
      <header class="focus-head">
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
/* ---------- Tiles: today across the top, then 2 + 3 ---------- */
.tiles {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  grid-template-areas:
    'day day day day day day'
    'yesterday yesterday yesterday overdue overdue overdue'
    'week week month month year year';
  gap: 10px;
}

.t-day { grid-area: day; }
.t-yesterday { grid-area: yesterday; }
.t-overdue { grid-area: overdue; }
.t-week { grid-area: week; }
.t-month { grid-area: month; }
.t-year { grid-area: year; }

.tile {
  display: grid;
  align-content: space-between;
  gap: 12px;
  min-width: 0;
  padding: 14px 14px 16px;
  border: 1px solid var(--glass-edge);
  border-radius: 22px;
  background: var(--surface);
  box-shadow: var(--glass-shadow);
  color: var(--fg);
  text-decoration: none;
  transition: border-color 0.2s, background-color 0.3s, color 0.3s, transform 0.2s;
}

.t-day {
  gap: 16px;
  padding: 20px 22px 22px;
  border-radius: 28px;
}

.t-yesterday,
.t-overdue {
  gap: 18px;
  padding: 16px 18px;
  border-radius: 24px;
}

.tile.link:hover {
  border-color: var(--accent);
}

.tile.link:active {
  transform: scale(0.98);
}

.tile-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 20px;
}

.tile-label {
  min-width: 0;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.25;
}

.chip {
  flex-shrink: 0;
  padding: 5px 10px;
  border-radius: 999px;
  background: var(--highlight);
  color: var(--on-highlight);
  font-size: 0.75rem;
  font-weight: 700;
}

.tile-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
}

.tile-icon.warn {
  color: var(--flag-high);
}

.tile-icon.check {
  stroke-width: 2.5;
  animation: pop 0.35s cubic-bezier(0.2, 0.8, 0.2, 1.4);
}

.tile-value {
  font-size: 1.4rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  line-height: 1;
}

.t-yesterday .tile-value,
.t-overdue .tile-value {
  font-size: 1.9rem;
  letter-spacing: -0.03em;
}

.t-day .tile-value {
  font-size: 4rem;
  letter-spacing: -0.04em;
  line-height: 0.95;
}

.of {
  margin-left: 2px;
  color: var(--muted);
  font-size: 0.6em;
  font-weight: 600;
  letter-spacing: 0;
}

.t-day .of {
  margin-left: 6px;
  font-size: 0.38em;
}

.meter {
  display: block;
  height: 5px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--pill);
}

.t-day .meter {
  height: 8px;
}

.meter-fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: var(--accent);
  transition: width 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
}

/* Complete: the whole tile goes accent with on-accent text. */
.tile.complete {
  border-color: var(--accent);
  background: var(--accent);
  box-shadow: 0 12px 28px color-mix(in srgb, var(--accent) 30%, transparent);
  color: var(--on-accent);
}

.tile.complete .tile-label,
.tile.complete .of {
  color: color-mix(in srgb, var(--on-accent) 75%, var(--accent));
}

.tile.complete .meter {
  background: color-mix(in srgb, var(--on-accent) 25%, transparent);
}

.tile.complete .meter-fill {
  background: var(--on-accent);
}

.tile.complete.link:hover {
  border-color: var(--on-accent);
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
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.card-title {
  margin: 0;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
}

.card-meta {
  font-size: 0.95rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* Rounded pill bars, no axis; today in the highlight colour. */
.bars {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  height: 116px;
}

.bar-slot {
  position: relative;
  display: grid;
  grid-template-rows: 1fr auto;
  gap: 8px;
  min-width: 0;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--muted);
  cursor: default;
}

.bar-track {
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.bar {
  display: block;
  width: min(24px, 72%);
  border-radius: 999px;
  background: var(--soft);
  transition: height 0.5s cubic-bezier(0.2, 0.8, 0.2, 1), background-color 0.2s;
}

.bar-slot:hover .bar,
.bar-slot:focus-visible .bar {
  background: var(--accent);
}

.bar-slot.today .bar {
  background: var(--highlight);
}

.bar-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-align: center;
}

.bar-slot.today .bar-label {
  color: var(--fg);
  font-weight: 800;
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
.focus {
  display: grid;
  gap: 10px;
  padding-top: 8px;
}

.focus-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 0 4px;
}

.more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--link);
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

@media (min-width: 860px) {
  .tiles {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-template-areas:
      'day day yesterday'
      'day day overdue'
      'week month year';
  }

  .t-day .tile-value {
    font-size: 5rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .tile-icon.check {
    animation: none;
  }
}
</style>
