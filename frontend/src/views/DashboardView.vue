<script setup>
import { computed, ref, watch } from 'vue'
import { ArrowRightIcon, CheckIcon } from '@heroicons/vue/24/outline'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import { user } from '@/lib/auth'
import { compareOccurrences, loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { addDays, dayMonth, parseISO, periodEnd, periodLabel, periodStart, toISO, weekdayName } from '@/lib/periods'
import { now, todayISO } from '@/lib/timeLeft'

/*
 * Overview: today's and yesterday's daily goals as progress rings, the last 7 days
 * of activity and what needs attention now. "Today" follows the shared clock, so
 * the page stays right past midnight.
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

/** A ring for one day's daily goals: done/total, how full the ring is and where it links. */
function dayRing(key, iso) {
  const items = occurrences.value.filter((o) => o.goal.period === 'day' && o.start === iso)
  const done = items.filter((o) => o.done).length
  const total = items.length
  const ratio = total ? done / total : 0
  return {
    key,
    done,
    total,
    percent: Math.round(ratio * 100),
    complete: total > 0 && done === total,
    offset: RING_C * (1 - ratio),
    to: { name: 'goals', params: { period: 'daily' }, query: key === 'day' ? undefined : { date: iso } },
  }
}

/* Ring geometry (viewBox 120 × 120). */
const RING_R = 52
const RING_C = 2 * Math.PI * RING_R

const rings = computed(() => [dayRing('day', todayISO.value), dayRing('yesterday', yesterdayISO.value)])

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

    <!-- Today (big) and yesterday: how much of the day's goals is done, as a filling ring. -->
    <section class="rings" :aria-label="$t('goal.progress')">
      <RouterLink
        v-for="ring in rings"
        :key="ring.key"
        :to="ring.to"
        class="ring-card"
        :class="[`r-${ring.key}`, { complete: ring.complete }]"
        :aria-label="`${$t(`dashboard.${ring.key}`)}: ${ring.done}/${ring.total}`"
      >
        <span class="ring-top">
          <span class="ring-label">{{ $t(`dashboard.${ring.key}`) }}</span>
          <span v-if="ring.key === 'day'" class="chip">{{ todayShort }}</span>
        </span>
        <span class="ring">
          <svg viewBox="0 0 120 120" aria-hidden="true">
            <circle class="ring-track" cx="60" cy="60" :r="RING_R" />
            <circle
              class="ring-fill"
              cx="60"
              cy="60"
              :r="RING_R"
              :stroke-dasharray="RING_C"
              :stroke-dashoffset="ring.offset"
            />
          </svg>
          <span class="ring-center">
            <CheckIcon v-if="ring.complete" class="ring-check" aria-hidden="true" />
            <template v-else>
              <span class="ring-value">{{ ring.done }}<span class="of">/{{ ring.total }}</span></span>
              <span v-if="ring.total" class="ring-percent">{{ ring.percent }}%</span>
            </template>
          </span>
        </span>
        <span v-if="ring.complete" class="ring-done">{{ ring.done }}/{{ ring.total }}</span>
      </RouterLink>
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
/* ---------- Rings: today (wide) + yesterday ---------- */
.rings {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 10px;
}

.ring-card {
  display: grid;
  grid-template-rows: auto 1fr auto;
  justify-items: center;
  gap: 12px;
  min-width: 0;
  padding: 16px 14px 18px;
  border: 1px solid var(--glass-edge);
  border-radius: 28px;
  background: var(--surface);
  box-shadow: var(--glass-shadow);
  color: var(--fg);
  text-decoration: none;
  transition: border-color 0.2s, transform 0.2s;
}

.ring-card:hover {
  border-color: var(--accent);
}

.ring-card:active {
  transform: scale(0.98);
}

.ring-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  min-height: 26px;
  padding: 0 4px;
}

.ring-label {
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

/* The ring fills clockwise from 12 o'clock as goals get done. */
.ring {
  position: relative;
  display: grid;
  place-items: center;
  width: min(100%, 150px);
  aspect-ratio: 1;
}

.r-yesterday .ring {
  width: min(100%, 104px);
  align-self: center;
}

.ring svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.ring circle {
  fill: none;
  stroke-width: 12;
}

.ring-track {
  stroke: var(--pill);
}

.ring-fill {
  stroke: var(--accent);
  stroke-linecap: round;
  transition: stroke-dashoffset 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.r-yesterday .ring-fill {
  stroke: var(--soft);
}

.ring-center {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 2px;
  line-height: 1;
}

.ring-value {
  font-size: 2rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
}

.r-yesterday .ring-value {
  font-size: 1.35rem;
}

.of {
  margin-left: 1px;
  color: var(--muted);
  font-size: 0.55em;
  font-weight: 600;
  letter-spacing: 0;
}

.ring-percent {
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.ring-check {
  width: 44px;
  height: 44px;
  color: var(--accent);
  stroke-width: 2.5;
  animation: pop 0.35s cubic-bezier(0.2, 0.8, 0.2, 1.4);
}

.r-yesterday .ring-check {
  width: 32px;
  height: 32px;
}

/* All done: the ring closes in the accent colour with a check inside; the count moves under it. */
.complete .ring-fill {
  stroke: var(--accent);
}

.ring-done {
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
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
  .ring {
    width: min(100%, 180px);
  }

  .r-yesterday .ring {
    width: min(100%, 132px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .ring-check {
    animation: none;
  }
}
</style>
