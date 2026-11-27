<script setup>
import { computed } from 'vue'
import { FlagIcon } from '@heroicons/vue/20/solid'
import GoalBadge from '@/components/GoalBadge.vue'
import { eventBadge } from '@/lib/events'
import { badgeFor } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { useSwipe } from '@/lib/motion'
import { todayISO } from '@/lib/timeLeft'
import { addDays, isoWeek, periodEnd, periodLabel, periodStart, toISO } from '@/lib/periods'

/**
 * Month grid (Monday first) with week numbers, on one white board. Days show their events and daily
 * goals as pastel chips on wide screens and coloured dots on phones; custom periods run as bands
 * through their days. Swipe sideways to change month (the page header has the arrows).
 */
const props = defineProps({
  month: { type: String, required: true }, // first day, YYYY-MM-DD
  selected: { type: String, required: true },
  occurrences: { type: Array, required: true },
  // Event occurrences ({ key, date, event }) in the visible weeks.
  events: { type: Array, default: () => [] },
  // Which way the last page turn went: the new month slides in from that side.
  direction: { type: String, default: 'next' },
})
const emit = defineEmits(['swipe', 'select', 'open-week'])
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
          events: props.events.filter((o) => o.date === iso),
          spans: customs.value.filter((o) => o.start <= iso && o.end >= iso),
          important: items.some((o) => !o.done && o.goal.priority === 'important'),
        }
      }),
    })
  }
  return rows
})

const dayLabel = (day) =>
  [
    periodLabel('day', day.iso),
    ...day.events.map((o) => o.event.title),
    day.items.length ? t('calendar.goalCount', { n: day.items.length }) : null,
  ]
    .filter(Boolean)
    .join(' · ')

/* Swipe left/right to change month: the grid follows the finger, then pages (or springs back). */
const swipe = useSwipe((n) => emit('swipe', n))
</script>

<template>
  <section
    class="board"
    @touchstart.passive="swipe.down"
    @touchmove.passive="swipe.move"
    @touchend="swipe.up"
    @touchcancel="swipe.up"
  >
    <div class="row head" aria-hidden="true">
      <span class="wk">{{ $t('calendar.weekShort') }}</span>
      <span v-for="(name, i) in $t('common.weekdaysShort')" :key="name" class="dow" :class="{ weekend: i > 4 }">{{ name }}</span>
    </div>

    <div class="drag" :class="{ dragging: swipe.dragging.value }" :style="{ transform: `translateX(${swipe.dx.value}px)` }">
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

            <span v-if="day.items.length || day.events.length" class="dots">
              <!-- Events: a small rounded square in their badge colour; goals: round dots. -->
              <span
                v-for="occ in day.events.slice(0, 2)"
                :key="occ.key"
                class="dot event"
                :style="{ '--c': eventBadge(occ.event).color }"
              />
              <span
                v-for="occ in day.items.slice(0, 4 - Math.min(2, day.events.length))"
                :key="occ.key"
                class="dot"
                :class="{ done: occ.done }"
                :style="{ '--c': badgeFor(occ.goal).color }"
              />
            </span>

            <span v-if="day.items.length || day.events.length" class="badges">
              <GoalBadge
                v-for="occ in day.events.slice(0, 2)"
                :key="occ.key"
                compact
                :title="occ.event.title"
                v-bind="eventBadge(occ.event)"
              />
              <GoalBadge
                v-for="occ in day.items.slice(0, 2 - Math.min(2, day.events.length))"
                :key="occ.key"
                compact
                :title="occ.goal.title"
                v-bind="badgeFor(occ.goal)"
                :done="occ.done"
              />
              <span v-if="day.items.length + day.events.length > 2" class="more">+{{ day.items.length + day.events.length - 2 }}</span>
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
/* The month sits on one white board. */
.board {
  min-width: 0;
  padding: 12px 8px 8px;
  overflow: hidden;
  border-radius: 30px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  touch-action: pan-y;
}

.drag:not(.dragging) {
  transition: transform 0.4s var(--ease);
}

.weeks {
  display: grid;
  gap: 3px;
}

.row {
  display: grid;
  grid-template-columns: 24px repeat(7, minmax(0, 1fr));
  gap: 3px;
}

.row.head {
  margin-bottom: 4px;
}

.dow,
.wk {
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 700;
  text-align: center;
}

.dow.weekend {
  opacity: 0.7;
}

.wk {
  opacity: 0.7;
}

.wk-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 12px 0 0; /* lines up with the day numbers */
  border: 0;
  border-radius: 10px;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

@media (hover: hover) {
  .wk-btn:hover,
  .wk-btn:focus-visible {
    color: var(--fg);
    opacity: 1;
  }
}

.wk-marks {
  display: grid;
  gap: 2px;
}

.wk-mark {
  width: 12px;
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
  height: 58px;
  padding: 4px 2px;
  border: 0;
  border-radius: 18px;
  background: transparent;
  color: var(--fg);
  font-family: inherit;
  cursor: pointer;
  transition: background-color 0.25s var(--ease), box-shadow 0.25s var(--ease), transform 0.25s var(--spring);
}

.cell:active {
  transform: scale(0.94);
}

.cell.out {
  color: var(--muted);
  opacity: 0.5;
}

@media (hover: hover) {
  .cell:hover {
    background: var(--surface-2);
  }
}

.cell.selected {
  background: color-mix(in srgb, var(--lav) var(--wash-strong), var(--surface));
}

.cell:focus-visible {
  outline: 2px solid var(--link);
  outline-offset: 1px;
}

.num {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-size: 0.95rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  transition: background-color 0.25s, color 0.25s;
}

/* Today: the ink disc. */
.today .num {
  background: var(--accent);
  color: var(--on-accent);
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
  box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.12);
}

.dot.event {
  border-radius: 2px;
}

/* Completed: hollow ring in the same colour (deepened a little so pastels still show on white). */
.dot.done {
  background: transparent;
  box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--c) 75%, var(--fg));
}

.badges {
  display: none;
}

.spans {
  position: absolute;
  right: -2px;
  bottom: 2px;
  left: -2px;
  display: grid;
  gap: 2px;
}

.span {
  height: 5px;
  background: var(--c);
}

.span.first {
  margin-left: 8px;
  border-radius: 999px 0 0 999px;
}

.span.last {
  margin-right: 8px;
  border-radius: 0 999px 999px 0;
}

.span.done {
  opacity: 0.35;
}

.more {
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 700;
}

@media (min-width: 860px) {
  .board {
    padding: 16px;
  }

  .row {
    grid-template-columns: 30px repeat(7, minmax(0, 1fr));
    gap: 6px;
  }

  .weeks {
    gap: 6px;
  }

  .dow {
    text-align: left;
    padding-left: 10px;
  }

  .cell {
    align-items: stretch;
    height: 108px;
    padding: 7px;
    background: var(--surface-2);
  }

  @media (hover: hover) {
    .cell:hover {
      background: color-mix(in srgb, var(--surface-2) 50%, var(--line));
    }
  }

  .cell.out {
    background: transparent;
    box-shadow: inset 0 0 0 1.5px var(--surface-2);
  }

  .cell.selected {
    background: color-mix(in srgb, var(--lav) var(--wash-strong), var(--surface));
    box-shadow: inset 0 0 0 2px var(--fg);
  }

  .num {
    align-self: flex-start;
    width: 30px;
    height: 30px;
    font-size: 0.98rem;
  }

  .important {
    top: 11px;
    right: 11px;
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
.slide-prev-enter-active {
  transition: transform 0.4s var(--ease), opacity 0.3s ease;
}

.slide-next-leave-active,
.slide-prev-leave-active {
  transition: transform 0.15s ease-in, opacity 0.15s ease-in;
}

.slide-next-enter-from,
.slide-prev-leave-to {
  transform: translateX(40px);
  opacity: 0;
}

.slide-next-leave-to,
.slide-prev-enter-from {
  transform: translateX(-40px);
  opacity: 0;
}
</style>
