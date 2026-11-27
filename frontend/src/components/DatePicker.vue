<script setup>
import { computed, ref, watch } from 'vue'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { t } from '@/lib/i18n'
import { useSwipe } from '@/lib/motion'
import { todayISO } from '@/lib/timeLeft'
import {
  addDays,
  dayCount,
  dayMonth,
  isoWeekday,
  monthLabel,
  parseISO,
  periodEnd,
  periodLabel,
  periodStart,
  rangeLabel,
  shiftPeriod,
  toISO,
  today,
} from '@/lib/periods'

/**
 * Brand-coloured inline calendar. In week/month/year mode a tap selects the
 * whole period (highlighted from today when it is the current one); in
 * custom mode two taps pick a start and an end. v-model is the period start.
 */
const props = defineProps({
  mode: { type: String, default: 'day' }, // day | week | month | year | custom
  min: { type: String, default: null },
  max: { type: String, default: null },
  // A repeating goal: { period, start, days: [ISO weekdays], until } — its later occurrences get a lighter band.
  series: { type: Object, default: null },
})
const value = defineModel({ type: String, default: null })
const end = defineModel('end', { type: String, default: null })

const view = ref(toISO(periodStart('month', value.value || today())))
const pickingEnd = ref(false)
const hover = ref(null)

// Follow the value when it changes from outside (e.g. switching the goal type).
watch(value, (v) => {
  if (!v) return
  const unit = props.mode === 'year' ? 'year' : 'month'
  if (toISO(periodStart(unit, v)) !== toISO(periodStart(unit, view.value))) view.value = toISO(periodStart('month', v))
})
watch(() => props.mode, () => (pickingEnd.value = false))

/** The days a selection covers; a current week/month/year only counts from today. */
function spanOf(iso, endISO = null) {
  if (!iso) return null
  if (props.mode === 'custom') return [iso, endISO ?? iso]
  if (props.mode === 'day') return [iso, iso]
  const unit = props.mode
  const from = toISO(periodStart(unit, iso))
  const to = toISO(periodEnd(unit, from))
  return [todayISO.value > from && todayISO.value <= to ? todayISO.value : from, to]
}

const selected = computed(() => spanOf(value.value, end.value))
const preview = computed(() => {
  if (!hover.value) return null
  if (props.mode === 'custom') return pickingEnd.value && hover.value >= value.value ? [value.value, hover.value] : null
  return props.mode === 'day' ? null : spanOf(hover.value)
})

/** Whether `iso` falls in one of the series' occurrences (mirrors periods.occursOn). */
function inSeries(iso) {
  const s = props.series
  if (!s?.start) return false
  const start = toISO(periodStart(s.period, iso))
  // The first occurrence is the selection itself (or its already-passed days), not a repeat.
  if (start <= s.start) return false
  if (s.until && start > toISO(periodStart(s.period, s.until))) return false
  return s.period !== 'day' || !s.days?.length || s.days.includes(isoWeekday(parseISO(iso)))
}

const within = (iso, span) => span && iso >= span[0] && iso <= span[1]

/**
 * Month grid. Each cell gets a highlight kind (selection > series > hover
 * preview); the band's corners round wherever the kind changes, so weeks,
 * ranges and single repeating days all read as clean shapes.
 */
const weeks = computed(() => {
  const first = periodStart('week', view.value)
  const last = periodEnd('week', periodEnd('month', view.value))
  const month = parseISO(view.value).getMonth()
  const sel = selected.value
  const pre = preview.value
  const rows = []
  for (let monday = first; monday <= last; monday = addDays(monday, 7)) {
    const row = Array.from({ length: 7 }, (_, i) => {
      const date = addDays(monday, i)
      const iso = toISO(date)
      const kind = within(iso, sel) ? 'sel' : inSeries(iso) ? 'series' : within(iso, pre) ? 'pre' : null
      return {
        iso,
        day: date.getDate(),
        col: i,
        kind,
        out: date.getMonth() !== month,
        today: iso === todayISO.value,
        disabled: Boolean((props.min && iso < props.min) || (props.max && iso > props.max)),
        // Solid dot on single days and range ends; whole periods are shown by the band alone.
        dot: kind === 'sel' && (props.mode === 'day' || props.mode === 'custom') && (iso === sel[0] || iso === sel[1]),
      }
    })
    row.forEach((cell, i) => {
      cell.roundL = cell.kind && row[i - 1]?.kind !== cell.kind
      cell.roundR = cell.kind && row[i + 1]?.kind !== cell.kind
    })
    rows.push(row)
  }
  return rows
})

function pick(iso) {
  if ((props.min && iso < props.min) || (props.max && iso > props.max)) return
  if (props.mode !== 'custom') {
    value.value = toISO(periodStart(props.mode, iso))
    return
  }
  // Custom: first tap = start, second tap = end (an earlier date restarts).
  if (!pickingEnd.value || iso < value.value) {
    value.value = iso
    end.value = iso
    pickingEnd.value = true
  } else {
    end.value = iso
    pickingEnd.value = false
  }
}

// Which way the page turned: the new month/year slides in from that side ('' until the first turn).
const direction = ref('')
function go(n) {
  direction.value = n > 0 ? 'next' : 'prev'
  view.value = toISO(shiftPeriod(props.mode === 'year' ? 'year' : 'month', view.value, n))
}

/* Year mode: twelve month tiles. */
const year = computed(() => parseISO(view.value).getFullYear())
const months = computed(() =>
  t('common.monthsShort').map((name, i) => {
    const iso = toISO(new Date(year.value, i, 1))
    const sel = selected.value
    const inSel = sel && toISO(periodEnd('month', iso)) >= sel[0] && iso <= sel[1]
    return {
      name,
      iso,
      kind: inSel ? 'sel' : inSeries(iso) ? 'series' : null,
      current: iso === toISO(periodStart('month', todayISO.value)),
    }
  }),
)

const summary = computed(() => {
  if (!value.value) return ''
  if (props.mode === 'custom') {
    if (pickingEnd.value) return t('picker.pickEnd')
    const n = Math.round((parseISO(end.value ?? value.value) - parseISO(value.value)) / 86400000) + 1
    return `${rangeLabel(value.value, end.value ?? value.value)} · ${dayCount(n)}`
  }
  const span = selected.value
  const label = periodLabel(props.mode, value.value)
  // Point out when only the rest of the current period counts.
  return span && span[0] !== toISO(periodStart(props.mode, value.value)) && props.mode !== 'day'
    ? `${label} · ${t('picker.fromToday', { range: rangeLabel(span[0], span[1]) })}`
    : label
})

/** "Every week · until 8 Nov" for a repeating goal. */
const seriesSummary = computed(() => {
  const s = props.series
  if (!s?.start) return ''
  const every =
    s.period === 'day' && s.days?.length && s.days.length < 7
      ? s.days.map((d) => t('common.weekdaysShort')[d - 1]).join(', ')
      : t(`periods.every.${s.period}`)
  if (!s.until) return every
  const last = periodEnd(s.period, periodStart(s.period, s.until))
  return `${every} · ${t('picker.until', { date: dayMonth(last, true) })}`
})

/* Swipe sideways to change month/year: the days follow the finger, then page (or spring back). */
const swipe = useSwipe(go)
</script>

<template>
  <div
    class="picker"
    :class="`mode-${mode}`"
    @touchstart.passive="swipe.down"
    @touchmove.passive="swipe.move"
    @touchend="swipe.up"
    @touchcancel="swipe.up"
  >
    <header class="head">
      <button type="button" class="nav" :aria-label="$t('calendar.prev')" @click="go(-1)">
        <ChevronLeftIcon aria-hidden="true" />
      </button>
      <span class="title">{{ mode === 'year' ? year : monthLabel(view) }}</span>
      <button type="button" class="nav" :aria-label="$t('calendar.next')" @click="go(1)">
        <ChevronRightIcon aria-hidden="true" />
      </button>
    </header>

    <div class="drag" :class="{ dragging: swipe.dragging.value }" :style="{ transform: `translateX(${swipe.dx.value}px)` }">
    <div v-if="mode === 'year'" :key="`y${year}`" class="months" :class="`in-${direction}`">
      <button
        v-for="m in months"
        :key="m.iso"
        type="button"
        class="month"
        :class="[m.kind, { current: m.current }]"
        :aria-pressed="m.kind === 'sel'"
        @click="pick(m.iso)"
      >
        {{ m.name }}
      </button>
    </div>

    <div v-else :key="view" class="grid" :class="`in-${direction}`" @mouseleave="hover = null">
      <span v-for="name in $t('common.weekdaysShort')" :key="name" class="dow" aria-hidden="true">{{ name }}</span>
      <template v-for="week in weeks" :key="week[0].iso">
        <button
          v-for="cell in week"
          :key="cell.iso"
          type="button"
          class="cell"
          :class="[cell.kind, { out: cell.out, today: cell.today, disabled: cell.disabled, dot: cell.dot, 'round-l': cell.roundL, 'round-r': cell.roundR }]"
          :disabled="cell.disabled"
          :aria-label="periodLabel('day', cell.iso)"
          :aria-pressed="cell.kind === 'sel'"
          @click="pick(cell.iso)"
          @mouseenter="hover = cell.iso"
        >
          <span class="num">{{ cell.day }}</span>
        </button>
      </template>
    </div>
    </div>

    <p class="summary" aria-live="polite">
      {{ summary }}
      <span v-if="seriesSummary" class="series-summary">{{ seriesSummary }}</span>
    </p>
  </div>
</template>

<style scoped>
.picker {
  display: grid;
  gap: 10px;
  padding: 12px;
  border-radius: 24px;
  background: var(--surface-2);
  user-select: none;
}

.picker {
  touch-action: pan-y;
}

.drag:not(.dragging) {
  transition: transform 0.4s var(--ease);
}

/* A new month/year slides in from the side it was paged towards (re-keyed, so no gap while it swaps). */
.in-next {
  animation: in-next 0.35s var(--ease);
}

.in-prev {
  animation: in-prev 0.35s var(--ease);
}

@keyframes in-next {
  from {
    opacity: 0;
    transform: translateX(28px);
  }
}

@keyframes in-prev {
  from {
    opacity: 0;
    transform: translateX(-28px);
  }
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title {
  font-family: var(--font-display);
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.title::first-letter {
  text-transform: uppercase;
}

.nav {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--surface);
  color: var(--fg);
  cursor: pointer;
  transition: transform 0.25s var(--spring);
}

.nav:active {
  transform: scale(0.88);
}

.nav svg {
  width: 18px;
  height: 18px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  row-gap: 4px;
}

.dow {
  padding-bottom: 4px;
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 700;
  text-align: center;
}

/* The highlight is drawn on the cell so a range reads as one continuous band. */
.cell {
  position: relative;
  display: grid;
  place-items: center;
  height: 40px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--fg);
  font-size: 0.85rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}

.cell.out {
  color: var(--muted);
}

.cell.disabled {
  opacity: 0.3;
  cursor: default;
}

.num {
  position: relative;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  transition: background-color 0.15s, color 0.15s, transform 0.25s var(--spring);
}

@media (hover: hover) {
  .cell:not(:disabled):hover .num {
    background: var(--surface);
  }
}

.cell:not(:disabled):active .num {
  transform: scale(0.88);
}

.cell.today .num {
  box-shadow: inset 0 0 0 1.5px var(--fg);
  font-weight: 800;
}

/* Bands (lavender): selection (strong), later repeats (medium), hover preview (faint). */
.cell.sel {
  background: var(--lav);
  color: var(--ink);
}

.cell.series {
  background: color-mix(in srgb, var(--lav) var(--wash-strong), transparent);
}

.cell.pre {
  background: color-mix(in srgb, var(--lav) var(--wash), transparent);
}

.cell.round-l {
  border-top-left-radius: 20px;
  border-bottom-left-radius: 20px;
}

.cell.round-r {
  border-top-right-radius: 20px;
  border-bottom-right-radius: 20px;
}

.cell.dot .num {
  background: var(--ink);
  color: #ffffff;
  font-weight: 800;
  animation: pop 0.35s var(--spring);
}

.months {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
}

@media (hover: hover) {
  .month:hover {
    background: var(--line);
  }
}

.month {
  height: 44px;
  border: 0;
  border-radius: 14px;
  background: var(--surface);
  color: var(--fg);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: capitalize;
  cursor: pointer;
}

.month.sel {
  background: var(--lav);
  color: var(--ink);
}

.month.series {
  background: color-mix(in srgb, var(--lav) var(--wash-strong), var(--surface));
}

.month:focus-visible,
.cell:focus-visible {
  outline: 2px solid var(--link);
  outline-offset: -2px;
  border-radius: 12px;
}

.month.current {
  box-shadow: inset 0 0 0 1.5px var(--fg);
}

.summary {
  display: grid;
  gap: 2px;
  min-height: 1.2em;
  margin: 2px 4px 0;
  color: var(--fg);
  font-size: 0.82rem;
  font-weight: 700;
}

.series-summary {
  color: var(--muted);
  font-weight: 600;
}
</style>
