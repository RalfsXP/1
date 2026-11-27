<script setup>
import { computed } from 'vue'
import { CheckIcon } from '@heroicons/vue/20/solid'
import { eventBadge } from '@/lib/events'
import { badgeFor, compareOccurrences, justTicked, readableOn } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { GOAL_ICONS } from '@/lib/icons'
import { touchUI } from '@/lib/input'
import { useSwipe } from '@/lib/motion'
import { usePress } from '@/lib/press'
import { addDays, parseISO, periodLabel, toISO, weekdayName } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

/**
 * Week board (v1.8). Desktop: seven columns side by side; phones: seven day cards stacked.
 * Each day lists its events (pastel cards, by time) and its daily goals (tinted rows you can tick off).
 * Goals: desktop — the circle ticks, the title edits; touch — tap ticks, hold edits (like goal rows).
 */
const props = defineProps({
  start: { type: String, required: true }, // Monday, YYYY-MM-DD
  selected: { type: String, required: true },
  occurrences: { type: Array, required: true },
  events: { type: Array, default: () => [] },
  direction: { type: String, default: 'next' },
})
const emit = defineEmits(['swipe', 'select', 'open', 'toggle', 'edit-event'])

const press = usePress({ onTap: (occ) => emit('toggle', occ), onHold: (occ) => emit('open', occ) })
const onGoal = (e, occ) => (touchUI.value ? press.click(e, occ) : emit('open', occ))

const days = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const iso = toISO(addDays(parseISO(props.start), i))
    const goals = props.occurrences.filter((o) => o.goal.period === 'day' && o.start === iso).sort(compareOccurrences)
    const events = props.events
      .filter((o) => o.date === iso)
      .sort((a, b) => (a.event.time ?? '').localeCompare(b.event.time ?? '') || a.event.title.localeCompare(b.event.title))
    return {
      iso,
      dow: weekdayName(iso, true),
      num: parseISO(iso).getDate(),
      today: iso === todayISO.value,
      past: iso < todayISO.value,
      goals,
      events,
      done: goals.filter((o) => o.done).length,
    }
  }),
)

const dayLabel = (day) => periodLabel('day', day.iso)
const habitLabel = (day) => t('calendar.dailyDone', { done: day.done, total: day.goals.length })

/* Swipe left/right to change week: the board follows the finger, then pages (or springs back). */
const swipe = useSwipe((n) => emit('swipe', n))
</script>

<template>
  <div
    class="week-wrap"
    :class="{ dragging: swipe.dragging.value }"
    :style="{ transform: `translateX(${swipe.dx.value}px)` }"
    @touchstart.passive="swipe.down"
    @touchmove.passive="swipe.move"
    @touchend="swipe.up"
    @touchcancel="swipe.up"
  >
    <Transition :name="`slide-${direction}`" mode="out-in">
      <div :key="start" class="week stagger">
        <section
          v-for="day in days"
          :key="day.iso"
          class="day"
          :class="{ today: day.today, past: day.past, selected: day.iso === selected }"
          :aria-label="dayLabel(day)"
        >
          <header class="day-head">
            <button type="button" class="date" :aria-pressed="day.iso === selected" :aria-label="dayLabel(day)" @click="emit('select', day.iso)">
              <span class="dow">{{ day.dow }}</span>
              <span class="num">{{ day.num }}</span>
            </button>
            <span v-if="day.goals.length" class="score" :class="{ full: day.done === day.goals.length }" :aria-label="habitLabel(day)">
              <CheckIcon v-if="day.done === day.goals.length" aria-hidden="true" />
              {{ day.done }}/{{ day.goals.length }}
            </span>
          </header>

          <ul class="items">
            <li v-for="occ in day.events" :key="occ.key">
              <button
                type="button"
                class="event"
                :style="{ background: eventBadge(occ.event).color, color: readableOn(eventBadge(occ.event).color) }"
                @click="emit('edit-event', occ)"
              >
                <component :is="GOAL_ICONS[eventBadge(occ.event).icon] ?? GOAL_ICONS.calendar" class="event-icon" aria-hidden="true" />
                <span class="event-title">{{ occ.event.title }}</span>
                <span class="event-time">{{ occ.event.time ?? $t('events.allDay') }}</span>
              </button>
            </li>

            <li
              v-for="occ in day.goals"
              :key="occ.key"
              class="goal"
              :class="{ done: occ.done, ticked: occ.done && justTicked(occ.key), pressing: press.pressing.value === occ }"
              :style="{ '--tint': badgeFor(occ.goal).color }"
            >
              <button
                type="button"
                class="tick"
                :aria-pressed="occ.done"
                :aria-label="$t('goal.toggle', { title: occ.goal.title })"
                @click="emit('toggle', occ)"
              >
                <CheckIcon v-if="occ.done" aria-hidden="true" />
              </button>
              <button
                type="button"
                class="goal-title"
                @pointerdown="touchUI && press.down($event, occ)"
                @pointermove="press.move"
                @pointerup="press.up"
                @pointercancel="press.up"
                @contextmenu="touchUI && $event.preventDefault()"
                @click="onGoal($event, occ)"
              >
                {{ occ.goal.title }}
              </button>
            </li>

            <li v-if="!day.events.length && !day.goals.length" class="free">{{ $t('calendar.free') }}</li>
          </ul>
        </section>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.week-wrap {
  min-width: 0;
  touch-action: pan-y;
}

.week-wrap:not(.dragging) {
  transition: transform 0.4s var(--ease);
}

/* Phones: one card per day. */
.week {
  display: grid;
  gap: 10px;
}

.day {
  display: grid;
  grid-template-columns: 58px minmax(0, 1fr);
  gap: 4px 12px;
  padding: 12px 12px 12px 10px;
  border-radius: 26px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  transition: box-shadow 0.25s var(--ease);
}

.day.selected {
  box-shadow: var(--card-shadow), inset 0 0 0 2px var(--fg);
}

.day-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  grid-row: span 2;
}

.date {
  display: grid;
  justify-items: center;
  gap: 2px;
  width: 54px;
  padding: 8px 0;
  border: 0;
  border-radius: 20px;
  background: var(--surface-2);
  color: var(--fg);
  cursor: pointer;
  transition: transform 0.25s var(--spring), background-color 0.2s;
}

.date:active {
  transform: scale(0.92);
}

.dow {
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 700;
}

.num {
  font-family: var(--font-display);
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.04em;
}

.today .date {
  background: var(--accent);
  color: var(--on-accent);
}

.today .dow {
  color: inherit;
  opacity: 0.75;
}

.past .date {
  opacity: 0.7;
}

.score {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 4px 8px;
  border-radius: 999px;
  background: var(--surface-2);
  font-size: 0.72rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.score.full {
  background: var(--mint);
  color: var(--ink);
}

.score svg {
  width: 12px;
  height: 12px;
}

.items {
  display: grid;
  align-content: start;
  gap: 6px;
  min-width: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.event {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 44px;
  padding: 8px 12px 8px 10px;
  border: 0;
  border-radius: 16px;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: transform 0.25s var(--spring), filter 0.2s;
}

@media (hover: hover) {
  .event:hover {
    filter: brightness(0.97);
  }
}

.event:active {
  transform: scale(0.96);
}

.event-icon {
  width: 18px;
  height: 18px;
}

.event-title {
  overflow: hidden;
  font-size: 0.88rem;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.event-time {
  font-size: 0.75rem;
  font-weight: 600;
  opacity: 0.75;
  font-variant-numeric: tabular-nums;
}

.goal {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 4px 6px 4px 4px;
  border-radius: 16px;
  background: color-mix(in srgb, var(--tint) var(--wash), var(--surface));
  transition: background-color 0.3s var(--ease), transform 0.25s var(--spring);
}

.goal.pressing {
  transform: scale(0.96);
}

.goal.done {
  background: var(--surface-2);
}

.tick {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--on-accent);
  cursor: pointer;
}

.tick::before {
  content: '';
  grid-area: 1 / 1;
  width: 22px;
  height: 22px;
  border: 2px solid color-mix(in srgb, var(--fg) 35%, transparent);
  border-radius: 50%;
  transition: border-color 0.2s, background-color 0.25s var(--ease), transform 0.3s var(--spring);
}

@media (hover: hover) {
  .tick:hover::before {
    border-color: var(--fg);
  }
}

.done .tick::before {
  border-color: var(--accent);
  background: var(--accent);
}

.ticked .tick::before {
  animation: pop 0.4s var(--spring);
}

.tick svg {
  grid-area: 1 / 1;
  z-index: 1;
  width: 15px;
  height: 15px;
}

.ticked .tick svg {
  animation: pop 0.4s var(--spring) 0.05s backwards;
}

.goal-title {
  flex: 1;
  min-width: 0;
  padding: 6px 4px;
  overflow: hidden;
  border: 0;
  background: transparent;
  color: var(--fg);
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 700;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
  -webkit-touch-callout: none;
}

.done .goal-title {
  color: var(--muted);
  text-decoration: line-through;
}

.free {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 0 4px;
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 500;
}

/* Desktop: seven columns on one board. */
@media (min-width: 860px) {
  .week {
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0;
    min-height: max(520px, calc(var(--app-h) - 300px));
    padding: 10px;
    border-radius: 32px;
    background: var(--surface);
    box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  }

  /* Columns rise together on desktop (the board is the animated unit). */
  .stagger > .day {
    animation: none;
  }

  .day {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 8px 7px 12px;
    border-radius: 24px;
    background: transparent;
    box-shadow: none;
  }

  .day + .day {
    box-shadow: inset 1px 0 0 var(--line);
  }

  .day.today {
    background: color-mix(in srgb, var(--lav) var(--wash), var(--surface));
  }

  .day.selected,
  .day.selected + .day {
    box-shadow: none;
  }

  .day.selected .date {
    box-shadow: inset 0 0 0 2px var(--fg);
  }

  .day-head {
    flex-direction: column;
    gap: 8px;
  }

  .date {
    width: 100%;
    max-width: 92px;
    padding: 8px 0 10px;
    background: transparent;
  }

  .num {
    font-size: 1.9rem;
  }

  .today .date {
    background: var(--accent);
  }

  .event {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: auto auto;
    gap: 2px 6px;
    padding: 10px;
  }

  .event-icon {
    grid-column: 2;
    grid-row: 1 / span 2;
    align-self: start;
  }

  .event-title {
    grid-row: 1;
    grid-column: 1;
    white-space: normal;
    overflow-wrap: anywhere;
  }

  .event-time {
    grid-row: 2;
    grid-column: 1;
  }

  .goal-title {
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.25;
  }

  .free {
    justify-content: center;
    font-size: 0.8rem;
    opacity: 0.7;
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
