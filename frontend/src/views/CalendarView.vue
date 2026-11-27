<script setup>
import { computed, ref, watch } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AnimatedList from '@/components/AnimatedList.vue'
import CalendarMonth from '@/components/CalendarMonth.vue'
import CalendarWeek from '@/components/CalendarWeek.vue'
import EventForm from '@/components/EventForm.vue'
import EventItem from '@/components/EventItem.vue'
import GoalBadge from '@/components/GoalBadge.vue'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import ProgressMeter from '@/components/ProgressMeter.vue'
import { useAddAction } from '@/lib/addAction'
import { eventOccurrences, loadEvents } from '@/lib/events'
import { badgeFor, loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { touchUI } from '@/lib/input'
import { usePress } from '@/lib/press'
import {
  PERIODS,
  PERIOD_TO_ROUTE,
  addDays,
  isoWeek,
  parseISO,
  periodEnd,
  periodLabel,
  periodStart,
  rangeLabel,
  shiftPeriod,
  toISO,
} from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

/*
 * Calendar (v1.8): a Month board (with the selected day's panel) or a Week board, switched in the
 * header and remembered. One selected day drives both: paging moves it, picking a day follows it.
 */
const VIEW_KEY = 'merkis-cal-view'
const view = ref('month')
try {
  if (localStorage.getItem(VIEW_KEY) === 'week') view.value = 'week'
} catch {
  // Storage unavailable: start on the month.
}
watch(view, (v) => {
  try {
    localStorage.setItem(VIEW_KEY, v)
  } catch {
    // Not remembered, still works.
  }
})

const monthOf = (iso) => toISO(periodStart('month', iso))
const weekOf = (iso) => toISO(periodStart('week', iso))

const selected = ref(todayISO.value)
const month = ref(monthOf(selected.value))
const weekStart = ref(weekOf(selected.value))
const direction = ref('next')

// Picking a day anywhere moves both boards to it.
watch(selected, (iso) => {
  if (monthOf(iso) !== month.value) month.value = monthOf(iso)
  if (weekOf(iso) !== weekStart.value) weekStart.value = weekOf(iso)
})

/** Page the current board; the selection moves into the new page (today if it's there, else its first day). */
function go(n) {
  direction.value = n > 0 ? 'next' : 'prev'
  if (view.value === 'month') {
    const m = toISO(shiftPeriod('month', month.value, n))
    selected.value = monthOf(todayISO.value) === m ? todayISO.value : m
  } else {
    const w = toISO(addDays(parseISO(weekStart.value), 7 * n))
    selected.value = weekOf(todayISO.value) === w ? todayISO.value : w
  }
}

const isCurrent = computed(() =>
  view.value === 'month' ? month.value === monthOf(todayISO.value) : weekStart.value === weekOf(todayISO.value),
)

function goToday() {
  direction.value = todayISO.value > selected.value ? 'next' : 'prev'
  selected.value = todayISO.value
}

// A week number in the month grid opens that week.
function openWeek(monday) {
  direction.value = 'next'
  selected.value = weekOf(todayISO.value) === monday ? todayISO.value : monday
  view.value = 'week'
}

/* ---------- Data ---------- */
// Month: the visible grid (Monday before the 1st to the Sunday after the last day). Week: that week.
const range = computed(() =>
  view.value === 'month'
    ? { from: periodStart('week', month.value), to: periodEnd('week', periodEnd('month', month.value)) }
    : { from: parseISO(weekStart.value), to: addDays(parseISO(weekStart.value), 6) },
)

watch([range, refreshTick], ([{ from, to }]) => loadRange(from, to).catch(() => {}), { immediate: true })
loadEvents().catch(() => {})

const occurrences = computed(() => occurrencesBetween(range.value.from, range.value.to))
const events = computed(() => eventOccurrences(toISO(range.value.from), toISO(range.value.to)))
const dayEvents = computed(() => events.value.filter((o) => o.date === selected.value))

/** The selected day's goals, grouped: that day, its week, month, year and any custom period covering it. */
const groups = computed(() =>
  PERIODS.map((period) => {
    const start = toISO(periodStart(period, selected.value))
    const items = occurrences.value.filter((o) =>
      o.goal.period === period &&
      (period === 'custom' ? o.start <= selected.value && o.end >= selected.value : o.start === start),
    )
    return { period, start: period === 'custom' ? undefined : start, items }
  }).filter((g) => g.items.length),
)
const dayItems = computed(() => groups.value.flatMap((g) => g.items))

/** Longer goals over the board: month view = month, year, custom; week view = week, month, custom. */
const strip = computed(() => {
  const from = toISO(range.value.from)
  const to = toISO(range.value.to)
  const anchor = view.value === 'month' ? month.value : weekStart.value
  return occurrences.value.filter((o) => {
    if (o.goal.period === 'custom') return o.start <= to && o.end >= from
    if (o.goal.period === 'month') return o.start === monthOf(anchor)
    if (o.goal.period === 'year') return view.value === 'month' && o.start === `${anchor.slice(0, 4)}-01-01`
    if (o.goal.period === 'week') return view.value === 'week' && o.start === weekStart.value
    return false
  })
})

// Strip badges: desktop click edits; touch tap ticks off, hold edits (same as goal rows).
const press = usePress({ onTap: (occ) => toggle(occ), onHold: (occ) => edit(occ) })
const onBadge = (e, occ) => (touchUI.value ? press.click(e, occ) : edit(occ))

/* ---------- Header ---------- */
const title = computed(() =>
  view.value === 'month'
    ? t('common.months')[parseISO(month.value).getMonth()]
    : rangeLabel(weekStart.value, toISO(addDays(parseISO(weekStart.value), 6))),
)
const chip = computed(() =>
  view.value === 'month' ? month.value.slice(0, 4) : t('calendar.week', { n: isoWeek(parseISO(weekStart.value)) }),
)

/* ---------- Forms ---------- */
const formOpen = ref(false)
const editing = ref(null)
const formDefaults = ref({ period: 'day', date: selected.value })

function add() {
  editing.value = null
  formDefaults.value = { period: 'day', date: selected.value }
  formOpen.value = true
}
useAddAction(add, 'goal.new')

function edit(occurrence) {
  editing.value = occurrence.goal
  formOpen.value = true
}

const toggle = (occurrence) => setDone(occurrence, !occurrence.done).catch(() => {})

const eventFormOpen = ref(false)
const editingEvent = ref(null)
function editEvent(occurrence) {
  editingEvent.value = occurrence.event
  eventFormOpen.value = true
}
function addEvent() {
  editingEvent.value = null
  eventFormOpen.value = true
}
</script>

<template>
  <div class="page calendar-page" :class="`view-${view}`">
    <header class="page-head cal-head">
      <div class="title-wrap">
        <h1 class="page-title">
          <Transition :name="`title-${direction}`" mode="out-in">
            <span :key="title" class="title-text">{{ title }}</span>
          </Transition>
          <span class="chip">{{ chip }}</span>
        </h1>
      </div>

      <div class="page-actions cal-actions">
        <div v-slide-pill class="segmented raised view-switch" role="group" :aria-label="$t('calendar.view')">
          <button type="button" :aria-pressed="view === 'month'" @click="view = 'month'">{{ $t('calendar.monthView') }}</button>
          <button type="button" :aria-pressed="view === 'week'" @click="view = 'week'">{{ $t('calendar.weekView') }}</button>
        </div>
        <Transition name="fade-pop">
          <button v-if="!isCurrent" type="button" class="btn btn-ghost today-btn" @click="goToday">{{ $t('calendar.today') }}</button>
        </Transition>
        <div class="arrows">
          <button type="button" class="icon-btn" :aria-label="$t('calendar.prev')" @click="go(-1)">
            <ChevronLeftIcon aria-hidden="true" />
          </button>
          <button type="button" class="icon-btn" :aria-label="$t('calendar.next')" @click="go(1)">
            <ChevronRightIcon aria-hidden="true" />
          </button>
        </div>
        <button type="button" class="btn btn-ghost wide-only" @click="addEvent">
          <PlusIcon aria-hidden="true" />{{ $t('events.new') }}
        </button>
        <button type="button" class="btn btn-primary wide-only" @click="add">
          <PlusIcon aria-hidden="true" />{{ $t('goal.new') }}
        </button>
      </div>
    </header>

    <TransitionGroup v-if="strip.length" tag="div" name="row" class="strip">
      <button
        v-for="occ in strip"
        :key="occ.key"
        type="button"
        class="strip-item"
        :class="{ pressing: press.pressing.value === occ }"
        @pointerdown="touchUI && press.down($event, occ)"
        @pointermove="press.move"
        @pointerup="press.up"
        @pointercancel="press.up"
        @contextmenu="touchUI && $event.preventDefault()"
        @click="onBadge($event, occ)"
      >
        <GoalBadge :title="occ.goal.title" v-bind="badgeFor(occ.goal)" :done="occ.done" />
      </button>
    </TransitionGroup>

    <Transition name="view-swap" mode="out-in">
      <div v-if="view === 'month'" key="month" class="month-layout">
        <CalendarMonth
          :month="month"
          :selected="selected"
          :occurrences="occurrences"
          :events="events"
          :direction="direction"
          @select="selected = $event"
          @swipe="go"
          @open-week="openWeek"
        />

        <section class="day-panel" :aria-label="periodLabel('day', selected)">
          <header class="day-head">
            <Transition name="fade" mode="out-in">
              <h2 :key="selected" class="section-title day-title">{{ periodLabel('day', selected) }}</h2>
            </Transition>
            <button type="button" class="icon-btn add-day" :aria-label="$t('goal.new')" @click="add">
              <PlusIcon aria-hidden="true" />
            </button>
          </header>

          <ProgressMeter :done="dayItems.filter((o) => o.done).length" :total="dayItems.length" />
          <HoldHint v-if="dayItems.length" />

          <p v-if="!groups.length && !dayEvents.length" class="empty-state">{{ $t('goal.emptyDay') }}</p>

          <div v-if="dayEvents.length" class="group">
            <RouterLink class="section-label group-title" :to="{ name: 'events' }">{{ $t('events.title') }}</RouterLink>
            <AnimatedList>
              <EventItem v-for="occ in dayEvents" :key="occ.key" :occurrence="occ" compact @edit="editEvent" />
            </AnimatedList>
          </div>

          <div v-for="group in groups" :key="group.period" v-reveal class="group">
            <RouterLink
              class="section-label group-title"
              :to="{ name: 'goals', params: { period: PERIOD_TO_ROUTE[group.period] }, query: { date: group.start } }"
            >
              {{ $t(`periods.${group.period}`) }}
            </RouterLink>
            <AnimatedList>
              <GoalItem v-for="occ in group.items" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
            </AnimatedList>
          </div>
        </section>
      </div>

      <CalendarWeek
        v-else
        key="week"
        :start="weekStart"
        :selected="selected"
        :occurrences="occurrences"
        :events="events"
        :direction="direction"
        @select="selected = $event"
        @swipe="go"
        @open="edit"
        @toggle="toggle"
        @edit-event="editEvent"
      />
    </Transition>

    <GoalForm :open="formOpen" :goal="editing" :defaults="formDefaults" @close="formOpen = false" />
    <EventForm :open="eventFormOpen" :event="editingEvent" :date="selected" @close="eventFormOpen = false" />
  </div>
</template>

<style scoped>
.calendar-page {
  max-width: 1440px;
}

.cal-head {
  flex-wrap: wrap;
  row-gap: 14px;
}

.title-wrap {
  flex: 1 1 auto;
}

/* "November 2026" / "23–29 Nov  Week 48": one line; the chip sits beside the title. */
.page-title {
  flex-wrap: nowrap;
  font-size: clamp(2.1rem, 9.4vw, 3rem);
  white-space: nowrap;
}

.title-text {
  display: inline-block;
}

.cal-actions {
  flex-wrap: wrap;
  width: 100%;
}

.view-switch {
  flex: 1;
}

.arrows {
  display: flex;
  gap: 6px;
}

.today-btn {
  padding: 0 16px;
}

.wide-only {
  display: none;
}

.strip {
  display: flex;
  gap: 8px;
  margin: 0 -18px;
  padding: 2px 18px 4px;
  overflow-x: auto;
  scrollbar-width: none;
  mask-image: linear-gradient(90deg, transparent, #000 16px, #000 calc(100% - 24px), transparent);
}

.strip::-webkit-scrollbar {
  display: none;
}

.strip-item {
  flex-shrink: 0;
  max-width: 70vw;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: none;
  cursor: pointer;
  -webkit-touch-callout: none;
  user-select: none;
  transition: transform 0.25s var(--spring), opacity 0.15s;
}

@media (hover: hover) {
  .strip-item:hover {
    opacity: 0.85;
  }
}

.strip-item:active {
  transform: scale(0.95);
}

.strip-item.pressing {
  transform: scale(0.94);
  opacity: 0.8;
  transition: transform 0.45s linear, opacity 0.45s linear;
}

.month-layout {
  display: grid;
  gap: 20px;
  min-width: 0;
}

.day-panel {
  display: grid;
  align-content: start;
  gap: 14px;
}

.day-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.day-title::first-letter {
  text-transform: uppercase;
}

.add-day {
  display: none;
  background: var(--accent);
  color: var(--on-accent);
}

@media (hover: hover) {
  .add-day:hover {
    background: var(--accent);
  }
}

.group {
  display: grid;
  gap: 8px;
}

.group-title {
  padding: 0 4px;
}

@media (hover: hover) {
  .group-title:hover {
    color: var(--fg);
  }
}

/* Title turns like a page when you move between months/weeks. */
.title-next-enter-active,
.title-prev-enter-active {
  transition: transform 0.35s var(--ease), opacity 0.25s ease;
}

.title-next-leave-active,
.title-prev-leave-active {
  transition: transform 0.12s ease-in, opacity 0.12s ease-in;
}

.title-next-enter-from,
.title-prev-leave-to {
  transform: translateY(14px);
  opacity: 0;
}

.title-next-leave-to,
.title-prev-enter-from {
  transform: translateY(-14px);
  opacity: 0;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.fade-pop-enter-active {
  transition: transform 0.35s var(--spring), opacity 0.2s ease;
}

.fade-pop-leave-active {
  transition: transform 0.15s ease, opacity 0.15s ease;
}

.fade-pop-enter-from,
.fade-pop-leave-to {
  transform: scale(0.7);
  opacity: 0;
}

.view-swap-enter-active {
  transition: opacity 0.3s ease, transform 0.4s var(--ease);
}

.view-swap-leave-active {
  transition: opacity 0.12s ease;
}

.view-swap-enter-from {
  opacity: 0;
  transform: translateY(12px) scale(0.99);
}

.view-swap-leave-to {
  opacity: 0;
}

@media (min-width: 860px) {
  .cal-actions {
    width: auto;
  }

  .view-switch {
    flex: none;
    width: 200px;
  }

  .wide-only {
    display: inline-flex;
  }

  .add-day {
    display: grid;
  }

  .strip {
    margin: 0 -32px;
    padding: 2px 32px 4px;
  }
}

/* Side by side only when the month keeps enough room for readable chips. */
@media (min-width: 1180px) {
  .month-layout {
    grid-template-columns: minmax(0, 1fr) 360px;
    align-items: start;
    gap: 24px;
  }

  .day-panel {
    position: sticky;
    top: 0;
  }
}
</style>
