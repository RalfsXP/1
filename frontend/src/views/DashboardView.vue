<script setup>
import { computed, ref, watch } from 'vue'
import { ArrowRightIcon, CheckIcon, FireIcon } from '@heroicons/vue/24/outline'
import AnimatedList from '@/components/AnimatedList.vue'
import CountUp from '@/components/CountUp.vue'
import EventForm from '@/components/EventForm.vue'
import EventItem from '@/components/EventItem.vue'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import { useAddAction } from '@/lib/addAction'
import { user } from '@/lib/auth'
import { events, loadEvents, nextOccurrence } from '@/lib/events'
import { compareOccurrences, loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { addDays, dayMonth, parseISO, periodEnd, periodLabel, periodStart, toISO, weekdayName } from '@/lib/periods'
import { now, todayISO } from '@/lib/timeLeft'

/*
 * Overview: today's and yesterday's daily goals as progress rings, the streak of fully done days,
 * the next events, the last 7 days of activity and what needs attention now. "Today" follows the
 * shared clock, so the page stays right past midnight.
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

/**
 * Streak: days in a row on which every daily goal was done (days without daily goals don't break it).
 * Today counts once it's complete; until then the streak runs up to yesterday. Looks back over the
 * loaded range (from 1 January or a week back, whichever is earlier).
 */
const dailyByDay = computed(() => {
  const map = {}
  for (const o of occurrences.value) if (o.goal.period === 'day') (map[o.start] ??= []).push(o)
  return map
})
const dayComplete = (iso) => {
  const items = dailyByDay.value[iso]
  return items?.length ? items.every((o) => o.done) : null // null = nothing planned
}
const streak = computed(() => {
  let n = 0
  let d = todayDate.value
  if (dayComplete(todayISO.value) !== true) d = addDays(d, -1)
  for (const stop = range.value.from; d >= stop; d = addDays(d, -1)) {
    const state = dayComplete(toISO(d))
    if (state === false) break
    if (state) n++
  }
  return n
})
const streakWeek = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const iso = toISO(addDays(todayDate.value, i - 6))
    return { iso, label: weekdayName(iso, true), state: dayComplete(iso) }
  }),
)

/* Coming up: the next three events. */
loadEvents().catch(() => {})
const upcoming = computed(() =>
  events.value
    .map((event) => ({ key: event.id, event, date: nextOccurrence(event, todayISO.value) }))
    .filter((o) => o.date)
    .sort((a, b) => a.date.localeCompare(b.date) || (a.event.time ?? '').localeCompare(b.event.time ?? ''))
    .slice(0, 3),
)
const eventFormOpen = ref(false)
const editingEvent = ref(null)
function editEvent(occurrence) {
  editingEvent.value = occurrence.event
  eventFormOpen.value = true
}

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
useAddAction(add, 'goal.new')
const toggle = (occurrence) => setDone(occurrence, !occurrence.done).catch(() => {})
</script>

<template>
  <div class="page wide">
    <header class="page-head rise">
      <div>
        <h1 class="page-title">{{ firstName ? $t('dashboard.greeting', { name: firstName }) : $t('nav.home') }}</h1>
        <p class="page-sub">{{ todayLabel }}</p>
      </div>
    </header>

    <p v-if="failed" class="empty-state" role="alert">{{ $t('auth.errorGeneric') }}</p>

    <div class="bento stagger">
      <!-- Today (lavender) and yesterday: how much of the day's goals is done, as a filling ring. -->
      <RouterLink
        v-for="ring in rings"
        :key="ring.key"
        :to="ring.to"
        class="tile ring-card"
        :class="[`r-${ring.key}`, { complete: ring.complete }]"
        :aria-label="`${$t(`dashboard.${ring.key}`)}: ${ring.done}/${ring.total}`"
      >
        <span class="tile-top">
          <span class="tile-label">{{ $t(`dashboard.${ring.key}`) }}</span>
          <span v-if="ring.key === 'day'" class="chip quiet">{{ todayShort }}</span>
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
              :style="{ '--from': RING_C }"
            />
          </svg>
          <span class="ring-center">
            <CheckIcon v-if="ring.complete" class="ring-check" aria-hidden="true" />
            <template v-else>
              <span class="ring-value"><CountUp :value="ring.done" /><span class="of">/{{ ring.total }}</span></span>
              <span v-if="ring.total" class="ring-percent"><CountUp :value="ring.percent" />%</span>
            </template>
          </span>
        </span>
        <span v-if="ring.complete" class="ring-done">{{ ring.done }}/{{ ring.total }}</span>
      </RouterLink>

      <section class="tile streak" :aria-label="`${$t('dashboard.streak')}: ${streak}`">
        <span class="tile-top">
          <span class="tile-label"><FireIcon aria-hidden="true" />{{ $t('dashboard.streak') }}</span>
        </span>
        <CountUp class="streak-num" :value="streak" />
        <span class="streak-sub">{{ $t('dashboard.daysInRow') }}</span>
        <span class="streak-week" aria-hidden="true">
          <span v-for="d in streakWeek" :key="d.iso" class="streak-day" :class="{ on: d.state === true, none: d.state === null }">
            <CheckIcon v-if="d.state === true" />
            <span v-else>{{ d.label.slice(0, 1) }}</span>
          </span>
        </span>
      </section>

      <section class="tile activity">
        <header class="tile-top">
          <h2 class="tile-label">{{ $t('dashboard.activity') }}</h2>
          <CountUp class="tile-meta" :value="activityTotal" />
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
            <Transition name="tip">
              <span v-if="hovered === i" class="tip" aria-hidden="true">{{ $t('dashboard.completedCount', { n: d.count }) }}</span>
            </Transition>
            <span class="bar-track">
              <span class="bar" :style="{ height: `${Math.max(12, (d.count / activityMax) * 100)}%`, animationDelay: `${0.25 + i * 0.05}s` }" />
            </span>
            <span class="bar-label" aria-hidden="true">{{ d.label }}</span>
          </button>
        </div>
      </section>

      <section class="tile upcoming">
        <header class="tile-top">
          <h2 class="tile-label">{{ $t('dashboard.upcoming') }}</h2>
          <RouterLink class="more" :to="{ name: 'events' }" :aria-label="$t('events.title')"><ArrowRightIcon aria-hidden="true" /></RouterLink>
        </header>
        <ul v-if="upcoming.length" class="list up-list">
          <EventItem v-for="occ in upcoming" :key="occ.key" :occurrence="occ" @edit="editEvent" />
        </ul>
        <p v-else class="up-empty">{{ $t('dashboard.noUpcoming') }}</p>
      </section>
    </div>

    <section v-reveal class="focus">
      <header class="focus-head">
        <h2 class="section-title">{{ $t('dashboard.focus') }}</h2>
        <RouterLink class="more" :to="{ name: 'calendar' }">
          {{ $t('nav.calendar') }}<ArrowRightIcon aria-hidden="true" />
        </RouterLink>
      </header>
      <HoldHint v-if="focus.length" class="hint" />
      <AnimatedList v-if="focus.length" class="focus-list">
        <GoalItem v-for="occ in focus" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
      </AnimatedList>
      <div v-else-if="loaded && !failed" class="empty-state">
        <span>{{ $t('dashboard.allDone') }}</span>
        <button type="button" class="btn btn-primary" @click="add">{{ $t('goal.new') }}</button>
      </div>
    </section>

    <GoalForm :open="formOpen" :goal="editing" :defaults="{ period: 'day', date: todayISO }" @close="formOpen = false" />
    <EventForm :open="eventFormOpen" :event="editingEvent" @close="eventFormOpen = false" />
  </div>
</template>

<style scoped>
/* ---------- Bento ---------- */
.bento {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
  padding: 18px;
  border-radius: 28px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  color: var(--fg);
}

.tile-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 30px;
}

.tile-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.02rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.tile-label svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.tile-meta {
  font-family: var(--font-display);
  font-size: 1.2rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

/* ---------- Rings ---------- */
.ring-card {
  align-items: center;
  text-decoration: none;
  transition: transform 0.3s var(--spring), box-shadow 0.2s;
}

.ring-card .tile-top {
  align-self: stretch;
}

@media (hover: hover) {
  .ring-card:hover {
    transform: translateY(-3px);
  }
}

.ring-card:active {
  transform: scale(0.97);
}

/* Today: the lavender tile. */
.r-day {
  background: var(--lav);
  color: var(--ink);
}

.r-day .chip {
  background: rgb(255 255 255 / 0.6);
  color: var(--ink);
}

/* The ring fills clockwise from 12 o'clock as goals get done (and sweeps in on load). */
.ring {
  position: relative;
  display: grid;
  place-items: center;
  width: min(100%, 140px);
  aspect-ratio: 1;
  margin: 4px 0;
}

.r-yesterday .ring {
  width: min(100%, 110px);
  margin-top: auto;
  margin-bottom: auto;
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
  stroke-width: 13;
}

.ring-track {
  stroke: var(--surface-2);
}

.r-day .ring-track {
  stroke: rgb(255 255 255 / 0.55);
}

.ring-fill {
  stroke: var(--accent);
  stroke-linecap: round;
  transition: stroke-dashoffset 0.8s var(--spring);
  animation: sweep 1.1s var(--ease) backwards 0.2s;
}

.r-day .ring-fill {
  stroke: var(--ink);
}

.r-yesterday .ring-fill {
  stroke: var(--soft);
}

@keyframes sweep {
  from {
    stroke-dashoffset: var(--from);
  }
}

.ring-center {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 3px;
  line-height: 1;
}

.ring-value {
  font-family: var(--font-display);
  font-size: 2.2rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.05em;
}

.r-yesterday .ring-value {
  font-size: 1.5rem;
}

.of {
  margin-left: 1px;
  opacity: 0.55;
  font-size: 0.55em;
  font-weight: 700;
  letter-spacing: 0;
}

.ring-percent {
  opacity: 0.65;
  font-size: 0.8rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.ring-check {
  width: 46px;
  height: 46px;
  stroke-width: 2.6;
  animation: pop 0.5s var(--spring) backwards 0.6s;
}

.r-yesterday .ring-check {
  width: 32px;
  height: 32px;
}

.ring-done {
  opacity: 0.7;
  font-size: 0.85rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

/* ---------- Streak (butter) ---------- */
.streak {
  grid-column: span 2;
  display: grid;
  grid-template-areas: 'top top' 'num sub' 'week week';
  grid-template-columns: auto minmax(0, 1fr);
  grid-template-rows: auto 1fr auto;
  gap: 6px 14px;
  background: var(--butter);
  color: var(--ink);
}

.streak .tile-top {
  grid-area: top;
}

.streak-num {
  grid-area: num;
  align-self: end;
  font-family: var(--font-display);
  font-size: 4.2rem;
  font-weight: 800;
  line-height: 0.85;
  letter-spacing: -0.06em;
  font-variant-numeric: tabular-nums;
  animation: pop 0.6s var(--spring) backwards 0.3s;
}

.streak-sub {
  grid-area: sub;
  align-self: end;
  padding-bottom: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  opacity: 0.75;
  line-height: 1.3;
}

.streak-week {
  grid-area: week;
  display: flex;
  gap: 5px;
  margin-top: 6px;
}

.streak-day {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: rgb(23 23 28 / 0.12);
  font-size: 0.68rem;
  font-weight: 800;
}

.streak-day.none {
  background: transparent;
  box-shadow: inset 0 0 0 1.5px rgb(23 23 28 / 0.18);
  opacity: 0.6;
}

.streak-day.on {
  background: var(--ink);
  color: var(--butter);
}

.streak-day svg {
  width: 14px;
  height: 14px;
  stroke-width: 3;
}

.streak-day:nth-child(n) {
  animation: pop 0.4s var(--spring) backwards;
}

.streak-day:nth-child(1) { animation-delay: 0.3s; }
.streak-day:nth-child(2) { animation-delay: 0.35s; }
.streak-day:nth-child(3) { animation-delay: 0.4s; }
.streak-day:nth-child(4) { animation-delay: 0.45s; }
.streak-day:nth-child(5) { animation-delay: 0.5s; }
.streak-day:nth-child(6) { animation-delay: 0.55s; }
.streak-day:nth-child(7) { animation-delay: 0.6s; }

/* ---------- Activity ---------- */
.activity {
  grid-column: span 2;
}

/* Rounded pill bars, no axis; today in lavender, the rest a quiet fill. */
.bars {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  height: 128px;
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
  width: min(28px, 74%);
  border-radius: 999px;
  background: color-mix(in srgb, var(--lav) var(--wash-strong), var(--surface));
  transform-origin: bottom;
  transition: height 0.5s var(--ease), background-color 0.2s;
  animation: grow 0.7s var(--spring) backwards;
}

@keyframes grow {
  from {
    transform: scaleY(0);
  }
}

@media (hover: hover) {
  .bar-slot:hover .bar,
  .bar-slot:focus-visible .bar {
    background: var(--lav);
  }
}

.bar-slot.today .bar {
  background: var(--accent);
}

.bar-label {
  font-size: 0.74rem;
  font-weight: 600;
  text-align: center;
}

.bar-slot.today .bar-label {
  color: var(--fg);
  font-weight: 800;
}

.tip {
  position: absolute;
  bottom: calc(100% - 4px);
  left: 50%;
  z-index: 2;
  padding: 5px 9px;
  border-radius: 10px;
  background: var(--accent);
  color: var(--on-accent);
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
  transform: translateX(-50%);
  pointer-events: none;
}

.tip-enter-active {
  transition: opacity 0.2s, transform 0.3s var(--spring);
}

.tip-enter-from {
  opacity: 0;
  transform: translate(-50%, 6px) scale(0.9);
}

/* ---------- Coming up ---------- */
.upcoming {
  grid-column: span 2;
}

.up-list {
  gap: 8px;
}

/* Rows sit on the tile: quiet fills instead of their own cards. */
.up-list :deep(.item) {
  background: var(--surface-2);
  box-shadow: none;
}

.up-list :deep(.row) {
  min-height: 62px;
  padding: 8px 12px 8px 8px;
}

.up-empty {
  margin: 0;
  color: var(--muted);
  font-size: 0.9rem;
}

.more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--link);
  font-size: 0.88rem;
  font-weight: 700;
  text-decoration: none;
}

.upcoming .more {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--surface-2);
  color: var(--fg);
  transition: transform 0.25s var(--spring);
}

@media (hover: hover) {
  .upcoming .more:hover {
    transform: translateX(3px);
  }
}

@media (hover: hover) {
  .more:hover {
    text-decoration: underline;
  }
}

.more svg {
  width: 16px;
  height: 16px;
  stroke-width: 2.2;
}

/* ---------- Focus ---------- */
.focus {
  display: grid;
  gap: 10px;
  padding-top: 10px;
}

.focus-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 0 4px;
}

@media (min-width: 860px) {
  .bento {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
  }

  .tile {
    padding: 22px;
  }

  .streak {
    grid-column: auto;
  }

  .activity {
    grid-column: span 2;
  }

  .upcoming {
    grid-column: auto;
  }

  .ring {
    width: min(100%, 170px);
  }

  .r-yesterday .ring {
    width: min(100%, 128px);
  }

  .streak-num {
    font-size: 5rem;
  }

  .focus-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
