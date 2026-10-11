<script setup>
import { computed, ref } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { FlagIcon } from '@heroicons/vue/20/solid'
import GoalBadge from '@/components/GoalBadge.vue'
import { badgeFor } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { todayISO } from '@/lib/timeLeft'
import { addDays, isoWeek, monthLabel, periodEnd, periodLabel, periodStart, shiftPeriod, toISO } from '@/lib/periods'

/**
 * Month grid (Monday first) with week numbers. Days show their daily goals as
 * badges on wide screens and coloured dots on phones; custom periods run as
 * bands through their days. Monthly, yearly and custom goals touching the
 * month sit in a strip above the grid. Swipe sideways to change month.
 */
const props = defineProps({
  month: { type: String, required: true }, // first day, YYYY-MM-DD
  selected: { type: String, required: true },
  occurrences: { type: Array, required: true },
})
const emit = defineEmits(['update:month', 'select', 'open-week', 'open'])

const direction = ref('next')

function go(n) {
  direction.value = n > 0 ? 'next' : 'prev'
  emit('update:month', toISO(shiftPeriod('month', props.month, n)))
}

function goToday() {
  const target = toISO(periodStart('month', todayISO.value))
  direction.value = target > props.month ? 'next' : 'prev'
  emit('update:month', target)
  emit('select', todayISO.value)
}

const byStart = (period) => {
  const map = {}
  for (const occ of props.occurrences) if (occ.goal.period === period) (map[occ.start] ??= []).push(occ)
  return map
}

const customs = computed(() => props.occurrences.filter((o) => o.goal.period === 'custom'))

const weeks = computed(() => {
  const daily = byStart('day')
  const weekly = byStart('week')
  const monthIndex = Number(props.month.slice(5, 7)) - 1
  const last = periodEnd('week', periodEnd('month', props.month))
  const rows = []
  for (let monday = periodStart('week', props.month); monday <= last; monday = addDays(monday, 7)) {
    const mondayISO = toISO(monday)
    rows.push({
      monday: mondayISO,
      number: isoWeek(monday),
      weekly: weekly[mondayISO] ?? [],
      days: Array.from({ length: 7 }, (_, i) => {
        const date = addDays(monday, i)
        const iso = toISO(date)
        const items = daily[iso] ?? []
        return {
          iso,
          day: date.getDate(),
          inMonth: date.getMonth() === monthIndex,
          items,
          spans: customs.value.filter((o) => o.start <= iso && o.end >= iso),
          important: items.some((o) => !o.done && o.goal.priority === 'important'),
        }
      }),
    })
  }
  return rows
})

const monthEndISO = computed(() => toISO(periodEnd('month', props.month)))
const strip = computed(() =>
  props.occurrences.filter(
    (o) =>
      (o.goal.period === 'month' && o.start === props.month) ||
      (o.goal.period === 'year' && o.start === `${props.month.slice(0, 4)}-01-01`) ||
      (o.goal.period === 'custom' && o.start <= monthEndISO.value && o.end >= props.month),
  ),
)

const dayLabel = (day) =>
  day.items.length ? `${periodLabel('day', day.iso)} · ${t('calendar.goalCount', { n: day.items.length })}` : periodLabel('day', day.iso)

const isCurrentMonth = computed(() => props.month === toISO(periodStart('month', todayISO.value)))

/* Swipe left/right on the grid to change month. */
let touch = null
const onTouchStart = (e) => (touch = { x: e.touches[0].clientX, y: e.touches[0].clientY })
function onTouchEnd(e) {
  if (!touch) return
  const dx = e.changedTouches[0].clientX - touch.x
  const dy = e.changedTouches[0].clientY - touch.y
  touch = null
  if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) go(dx < 0 ? 1 : -1)
}
</script>

<template>
  <section class="calendar">
    <header class="cal-head">
      <h1 class="page-title">{{ monthLabel(month) }}</h1>
      <div class="cal-nav">
        <button v-if="!isCurrentMonth" type="button" class="btn btn-ghost" @click="goToday">{{ $t('calendar.today') }}</button>
        <button type="button" class="icon-btn" :aria-label="$t('calendar.prev')" @click="go(-1)">
          <ChevronLeftIcon aria-hidden="true" />
        </button>
        <button type="button" class="icon-btn" :aria-label="$t('calendar.next')" @click="go(1)">
          <ChevronRightIcon aria-hidden="true" />
        </button>
      </div>
    </header>

    <div v-if="strip.length" class="strip">
      <button v-for="occ in strip" :key="occ.key" type="button" class="strip-item" @click="emit('open', occ)">
        <GoalBadge :title="occ.goal.title" v-bind="badgeFor(occ.goal)" :done="occ.done" />
      </button>
    </div>

    <div class="grid-wrap" @touchstart.passive="onTouchStart" @touchend="onTouchEnd">
      <div class="row head" aria-hidden="true">
        <span class="wk">{{ $t('calendar.weekShort') }}</span>
        <span v-for="name in $t('common.weekdaysShort')" :key="name" class="dow">{{ name }}</span>
      </div>

      <Transition :name="`slide-${direction}`" mode="out-in">
        <div :key="month" class="weeks">
          <div v-for="week in weeks" :key="week.monday" class="row">
            <button
              type="button"
              class="wk wk-btn"
              :aria-label="$t('calendar.week', { n: week.number })"
              @click="emit('open-week', week.monday)"
            >
              {{ week.number }}
              <span class="wk-marks">
                <span
                  v-for="occ in week.weekly.slice(0, 3)"
                  :key="occ.key"
                  class="wk-mark"
                  :class="{ done: occ.done }"
                  :style="{ '--c': badgeFor(occ.goal).color }"
                />
              </span>
            </button>

            <button
              v-for="day in week.days"
              :key="day.iso"
              type="button"
              class="cell"
              :class="{ out: !day.inMonth, today: day.iso === todayISO, selected: day.iso === selected }"
              :aria-pressed="day.iso === selected"
              :aria-label="dayLabel(day)"
              @click="emit('select', day.iso)"
            >
              <span class="num">{{ day.day }}</span>
              <FlagIcon v-if="day.important" class="important" aria-hidden="true" />

              <span v-if="day.items.length" class="dots">
                <span
                  v-for="occ in day.items.slice(0, 4)"
                  :key="occ.key"
                  class="dot"
                  :class="{ done: occ.done }"
                  :style="{ '--c': badgeFor(occ.goal).color }"
                />
              </span>

              <span v-if="day.items.length" class="badges">
                <GoalBadge
                  v-for="occ in day.items.slice(0, 2)"
                  :key="occ.key"
                  compact
                  :title="occ.goal.title"
                  v-bind="badgeFor(occ.goal)"
                  :done="occ.done"
                />
                <span v-if="day.items.length > 2" class="more">+{{ day.items.length - 2 }}</span>
              </span>

              <!-- Custom periods: a band through every day they cover. -->
              <span v-if="day.spans.length" class="spans" aria-hidden="true">
                <span
                  v-for="occ in day.spans.slice(0, 2)"
                  :key="occ.key"
                  class="span"
                  :class="{ first: occ.start === day.iso, last: occ.end === day.iso, done: occ.done }"
                  :style="{ '--c': badgeFor(occ.goal).color }"
                />
              </span>
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </section>
</template>

<style scoped>
.calendar {
  display: grid;
  gap: 14px;
  min-width: 0;
}

.cal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px 12px;
}

.cal-head h1 {
  min-width: 0;
}

.cal-nav .btn {
  height: 40px;
}


.cal-nav {
  display: flex;
  align-items: center;
  gap: 6px;
}

.strip {
  display: flex;
  gap: 8px;
  margin: 0 -16px;
  padding: 2px 16px;
  overflow-x: auto;
  scrollbar-width: none;
  mask-image: linear-gradient(90deg, transparent, var(--black) 16px, var(--black) calc(100% - 24px), transparent);
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
  transition: transform 0.15s, opacity 0.15s;
}

.strip-item:hover {
  opacity: 0.85;
}

.strip-item:active {
  transform: scale(0.97);
}

.grid-wrap {
  overflow: hidden;
  touch-action: pan-y;
}

.weeks {
  display: grid;
  gap: 4px;
}

.row {
  display: grid;
  grid-template-columns: 26px repeat(7, minmax(0, 1fr));
  gap: 4px;
}

.row.head {
  margin-bottom: 4px;
}

.dow,
.wk {
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 700;
  text-align: center;
}

.wk-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 11px 0 0; /* lines up with the day numbers */
  border: 0;
  border-radius: 8px;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.wk-btn:hover,
.wk-btn:focus-visible {
  color: var(--seagrass);
}

.wk-marks {
  display: grid;
  gap: 2px;
}

.wk-mark {
  width: 14px;
  height: 4px;
  border-radius: 2px;
  background: var(--c);
}

.wk-mark.done {
  opacity: 0.35;
}

.cell {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 0;
  height: 54px;
  padding: 6px 2px;
  border: 1px solid transparent;
  border-radius: 14px;
  background: var(--surface);
  color: var(--fg);
  font-family: inherit;
  cursor: pointer;
  transition: border-color 0.2s, background-color 0.2s;
}

.cell.out {
  background: transparent;
  color: var(--muted);
}

.cell:hover {
  border-color: var(--line);
}

.cell.selected {
  border-color: var(--seagrass);
}

.cell:focus-visible {
  outline: 2px solid var(--seagrass);
  outline-offset: 1px;
}

.num {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.today .num {
  background: var(--seagrass);
  color: var(--black);
  font-weight: 800;
}

.important {
  position: absolute;
  top: 5px;
  right: 5px;
  width: 10px;
  height: 10px;
  color: var(--flag-important);
}

.dots {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 3px;
  max-width: 100%;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--c);
}

/* Completed: hollow ring in the same colour. */
.dot.done {
  background: transparent;
  box-shadow: inset 0 0 0 1.5px var(--c);
}

.badges {
  display: none;
}

.spans {
  position: absolute;
  right: -3px;
  bottom: 4px;
  left: -3px;
  display: grid;
  gap: 2px;
}

.span {
  height: 3px;
  background: var(--c);
}

.span.first {
  margin-left: 8px;
  border-radius: 2px 0 0 2px;
}

.span.last {
  margin-right: 8px;
  border-radius: 0 2px 2px 0;
}

.span.done {
  opacity: 0.35;
}

.more {
  color: var(--muted);
  font-size: 0.66rem;
  font-weight: 700;
}

@media (min-width: 860px) {
  .row {
    grid-template-columns: 32px repeat(7, minmax(0, 1fr));
    gap: 6px;
  }

  .cell {
    align-items: stretch;
    height: 104px;
    padding: 6px;
  }

  .num {
    align-self: flex-start;
  }

  .important {
    top: 10px;
    right: 10px;
  }

  .dots {
    display: none;
  }

  .badges {
    display: grid;
    gap: 3px;
    min-width: 0;
  }
}

.slide-next-enter-active,
.slide-next-leave-active,
.slide-prev-enter-active,
.slide-prev-leave-active {
  transition: transform 0.18s ease, opacity 0.18s ease;
}

.slide-next-enter-from,
.slide-prev-leave-to {
  transform: translateX(24px);
  opacity: 0;
}

.slide-next-leave-to,
.slide-prev-enter-from {
  transform: translateX(-24px);
  opacity: 0;
}
</style>
