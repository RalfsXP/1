<script setup>
import { computed } from 'vue'
import { CalendarDaysIcon, Squares2X2Icon, StarIcon, SunIcon, ViewColumnsIcon } from '@heroicons/vue/24/outline'
import { CheckIcon, ChevronLeftIcon, ChevronRightIcon, FlagIcon } from '@heroicons/vue/20/solid'
import LogoMark from '@/components/LogoMark.vue'
import AppLogo from '@/components/AppLogo.vue'
import { SWATCHES } from '@/lib/goals'
import { locale, t } from '@/lib/i18n'
import { addDays, dayMonth, isoWeekday, monthLabel, parseISO, periodEnd, periodStart, toISO, weekdayName } from '@/lib/periods'
import { now, timeLeft, todayISO } from '@/lib/timeLeft'

/**
 * A miniature, non-interactive copy of the app for the landing page devices:
 * `desktop` = Overview, `tablet` = Calendar, `phone` = today's goals. Sized in
 * container units (cqw), so it scales with whatever screen it sits in. Uses the
 * real tokens, so it follows the theme; demo text follows the language.
 */
const props = defineProps({ variant: { type: String, required: true } })

const [SEA, BLUE, ALMOND, BLUSH, , AMBER, TEAL] = SWATCHES
const H = 3600 * 1000
const left = (hours) => timeLeft(new Date(now.value + hours * H)).text

const goals = computed(() => [
  { key: 'run', color: SEA, flag: 'important', done: true },
  { key: 'report', color: BLUE, flag: 'important', done: false, when: left(3) },
  { key: 'read', color: BLUSH, flag: 'low', done: true },
  { key: 'water', color: TEAL, flag: 'low', done: false, when: left(9) },
  { key: 'gym', color: ALMOND, flag: 'high', done: false, when: left(11) },
  { key: 'call', color: AMBER, flag: 'high', done: true },
])

const clock = computed(() => {
  const d = new Date(now.value)
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
})

/* Overview tiles + activity bars. */
const tiles = [
  { key: 'yesterday', done: 3, total: 3 },
  { key: 'day', done: 3, total: 6 },
  { key: 'week', done: 2, total: 2 },
  { key: 'month', done: 1, total: 3 },
  { key: 'year', done: 0, total: 2 },
  { key: 'overdue', done: 0, total: 0 },
]
const bars = [3, 5, 2, 6, 4, 7, 3]
const R = 15
const C = 2 * Math.PI * R

/* Calendar: the real current month, with a few deterministic dots and one custom-period band. */
const month = computed(() => toISO(periodStart('month', todayISO.value)))
const calendar = computed(() => {
  const first = periodStart('week', month.value)
  const last = periodEnd('week', periodEnd('month', month.value))
  const m = parseISO(month.value).getMonth()
  const cells = []
  const bandFrom = toISO(addDays(parseISO(todayISO.value), 2))
  const bandTo = toISO(addDays(parseISO(todayISO.value), 8))
  for (let d = first; d <= last; d = addDays(d, 1)) {
    const iso = toISO(d)
    const n = d.getDate()
    cells.push({
      iso,
      n,
      out: d.getMonth() !== m,
      today: iso === todayISO.value,
      dots: [SEA, BLUSH, BLUE, ALMOND, TEAL].filter((_, i) => (n * (i + 3)) % 7 < 3).slice(0, 3),
      band: iso >= bandFrom && iso <= bandTo,
      flag: n % 9 === 4,
      col: isoWeekday(d),
    })
  }
  return cells
})

const nav = [Squares2X2Icon, CalendarDaysIcon, SunIcon, ViewColumnsIcon, CalendarDaysIcon, StarIcon]
const navLabels = ['nav.home', 'nav.calendar', 'periods.day', 'periods.week', 'periods.month', 'periods.year']
</script>

<template>
  <!-- v-* class names: a parent's scoped .phone/.tablet styles would otherwise hit this root. -->
  <div class="demo" :class="`v-${variant}`" aria-hidden="true">
    <!-- ================= Laptop: Overview ================= -->
    <template v-if="variant === 'desktop'">
      <aside class="side">
        <div class="side-logo"><AppLogo /></div>
        <span v-for="(icon, i) in nav" :key="i" class="nav" :class="{ active: i === 0 }">
          <component :is="icon" /> {{ t(navLabels[i]) }}
        </span>
      </aside>
      <section class="main">
        <div class="bar">
          <span class="clock">{{ dayMonth(todayISO) }} {{ clock }}</span>
          <span class="pills"><span class="pill">{{ locale.toUpperCase() }}</span><span class="dot-btn" /></span>
        </div>
        <p class="hello">{{ t('dashboard.greeting', { name: t('demo.name') }) }}</p>
        <div class="tiles">
          <div v-for="tile in tiles" :key="tile.key" class="tile" :class="{ complete: tile.total ? tile.done === tile.total : true }">
            <span class="tile-label">{{ t(`dashboard.${tile.key}`) }}</span>
            <span class="tile-row">
              <b>{{ tile.done }}<i v-if="tile.total">/{{ tile.total }}</i></b>
              <span v-if="tile.total ? tile.done === tile.total : true" class="check"><CheckIcon /></span>
              <svg v-else class="ring" viewBox="0 0 40 40">
                <circle cx="20" cy="20" :r="R" class="track" />
                <circle cx="20" cy="20" :r="R" class="fill" :stroke-dasharray="C" :stroke-dashoffset="C * (1 - tile.done / tile.total)" />
              </svg>
            </span>
          </div>
        </div>
        <div class="chart">
          <span class="chart-title">{{ t('dashboard.activity') }}</span>
          <div class="bars">
            <span v-for="(b, i) in bars" :key="i" class="bar-col" :class="{ today: i === 6 }">
              <span class="bar-fill" :style="{ height: `${b * 13}%` }" />
              <em>{{ weekdayName(addDays(parseISO(todayISO), i - 6), true) }}</em>
            </span>
          </div>
        </div>
        <p class="chart-title">{{ t('dashboard.focus') }}</p>
        <div v-for="g in goals.filter((x) => !x.done).slice(0, 2)" :key="g.key" class="item">
          <span class="tick" />
          <span class="item-text">
            <b>{{ t(`demo.goals.${g.key}`) }}</b>
            <small><FlagIcon class="flag" :class="g.flag" />{{ g.when }}</small>
          </span>
          <span class="swatch" :style="{ background: g.color }" />
        </div>
      </section>
    </template>

    <!-- ================= Tablet: Calendar ================= -->
    <template v-else-if="variant === 'tablet'">
      <div class="cal-head">
        <span class="cal-title">{{ monthLabel(month) }}</span>
        <span class="pills"><span class="nav-btn"><ChevronLeftIcon /></span><span class="nav-btn"><ChevronRightIcon /></span></span>
      </div>
      <div class="cal-grid">
        <span v-for="name in t('common.weekdaysShort')" :key="name" class="dow">{{ name }}</span>
        <span
          v-for="c in calendar"
          :key="c.iso"
          class="cell"
          :class="{ out: c.out, today: c.today, 'band-on': c.band, first: c.band && c.col === 1, last: c.band && c.col === 7 }"
        >
          <span class="num">{{ c.n }}</span>
          <FlagIcon v-if="c.flag && !c.out" class="cell-flag" />
          <span class="cell-dots"><span v-for="(d, i) in c.dots" :key="i" :style="{ background: d }" /></span>
          <span v-if="c.band" class="band" />
        </span>
      </div>
      <p class="panel-title">{{ t('periods.day') }}</p>
      <div v-for="g in goals.slice(0, 3)" :key="g.key" class="item" :class="{ done: g.done }">
        <span class="tick"><CheckIcon v-if="g.done" /></span>
        <span class="item-text"><b>{{ t(`demo.goals.${g.key}`) }}</b></span>
        <span class="swatch" :style="{ background: g.color }" />
      </div>
    </template>

    <!-- ================= Phone: today ================= -->
    <template v-else>
      <div class="status"><b>{{ clock }}</b><span class="island" /><span class="batt" /></div>
      <div class="ptop"><span class="pmark"><LogoMark /></span><span class="pills"><span class="pill">{{ locale.toUpperCase() }}</span><span class="dot-btn" /></span></div>
      <p class="ptitle">{{ t('dashboard.day') }}</p>
      <div class="progress"><span>3/6</span><i><em /></i></div>
      <div v-for="g in goals" :key="g.key" class="item" :class="{ done: g.done }">
        <span class="tick"><CheckIcon v-if="g.done" /></span>
        <span class="item-text">
          <b>{{ t(`demo.goals.${g.key}`) }}</b>
          <small><FlagIcon class="flag" :class="g.flag" />{{ g.done ? t('goal.done') : g.when }}</small>
        </span>
        <span class="swatch" :style="{ background: g.color }" />
      </div>
      <span class="fab">+</span>
    </template>
  </div>
</template>

<style scoped>
/* Everything is in cqw of the screen the demo sits in (the screen is the container). */
.demo {
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: var(--bg);
  color: var(--fg);
  font-family: Montserrat, ui-sans-serif, system-ui, sans-serif;
  line-height: 1.2;
  pointer-events: none;
  user-select: none;
}

.pills {
  display: flex;
  gap: 0.8cqw;
  align-items: center;
}

.flag.low,
.cell-flag {
  color: var(--flag-low);
}

.flag.high {
  color: var(--flag-high);
}

.flag.important,
.cell-flag {
  color: var(--flag-important);
}

/* ---------- Desktop (Overview) ---------- */
.v-desktop {
  display: grid;
  grid-template-columns: 19cqw 1fr;
}

.side {
  display: grid;
  align-content: start;
  gap: 0.5cqw;
  padding: 2.4cqw 1.4cqw;
  border-right: 0.12cqw solid var(--line);
}

.side-logo {
  width: 11cqw;
  height: 3.4cqw;
  margin: 0 0 1.8cqw 0.8cqw;
}

.nav {
  display: flex;
  align-items: center;
  gap: 0.9cqw;
  padding: 0.75cqw 0.9cqw;
  border-radius: 1cqw;
  font-size: 1.15cqw;
  font-weight: 700;
  white-space: nowrap;
}

.nav svg {
  width: 1.6cqw;
  height: 1.6cqw;
  flex-shrink: 0;
}

.nav.active {
  background: var(--accent);
  color: var(--on-accent);
}

.main {
  display: grid;
  align-content: start;
  gap: 1.3cqw;
  padding: 2cqw 2.8cqw;
  min-width: 0;
}

.bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.clock {
  color: var(--accent);
  font-size: 1.15cqw;
  font-weight: 800;
}

.pill {
  padding: 0.35cqw 0.8cqw;
  border-radius: 99px;
  background: var(--accent);
  color: var(--on-accent);
  font-size: 0.9cqw;
  font-weight: 800;
}

.dot-btn {
  width: 2.2cqw;
  height: 2.2cqw;
  border: 0.12cqw solid var(--line);
  border-radius: 50%;
}

.hello {
  margin: 0;
  font-size: 2.9cqw;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1cqw;
}

.tile {
  display: grid;
  gap: 1.2cqw;
  padding: 1.2cqw;
  border: 0.12cqw solid var(--line);
  border-radius: 1.4cqw;
  background: var(--surface);
}

.tile.complete {
  border-color: var(--accent);
  background: var(--accent);
  color: var(--on-accent);
}

.tile-label {
  overflow: hidden;
  color: var(--muted);
  font-size: 0.95cqw;
  font-weight: 700;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.tile.complete .tile-label,
.tile.complete i {
  color: color-mix(in srgb, var(--on-accent) 70%, var(--accent));
}

.tile-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.tile b {
  font-size: 2.4cqw;
  font-weight: 800;
  line-height: 1;
}

.tile i {
  color: var(--muted);
  font-size: 1.4cqw;
  font-style: normal;
}

.check {
  display: grid;
  place-items: center;
  width: 3cqw;
  height: 3cqw;
  border-radius: 50%;
  background: var(--on-accent);
  color: var(--accent);
}

.check svg {
  width: 2cqw;
  height: 2cqw;
}

.ring {
  width: 3cqw;
  height: 3cqw;
  transform: rotate(-90deg);
}

.ring circle {
  fill: none;
  stroke-width: 5;
}

.track {
  stroke: var(--line);
}

.ring .fill {
  stroke: var(--accent);
  stroke-linecap: round;
}

.chart {
  display: grid;
  gap: 1cqw;
  padding: 1.3cqw;
  border: 0.12cqw solid var(--line);
  border-radius: 1.4cqw;
  background: var(--surface);
}

.chart-title {
  font-size: 1.15cqw;
  font-weight: 800;
}

.bars {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1cqw;
  height: 8cqw;
}

.bar-col {
  display: grid;
  grid-template-rows: 1fr auto;
  gap: 0.5cqw;
  justify-items: center;
}

.bar-col .bar-fill {
  align-self: end;
  width: 1.6cqw;
  border-radius: 0.4cqw 0.4cqw 0 0;
  background: color-mix(in srgb, var(--link) 55%, transparent);
}

.bar-col.today .bar-fill {
  background: var(--accent);
}

.bar-col em {
  color: var(--muted);
  font-size: 0.85cqw;
  font-style: normal;
  font-weight: 700;
}

.v-desktop .main > .chart-title {
  margin: 0.4cqw 0 -0.6cqw;
}

.v-desktop .item {
  gap: 1.2cqw;
  min-height: 4.4cqw;
  padding: 0.8cqw 1.2cqw;
  border-width: 0.12cqw;
  border-radius: 1.2cqw;
}

.v-desktop .tick {
  width: 2cqw;
  height: 2cqw;
  border-width: 0.25cqw;
}

.v-desktop .item b {
  font-size: 1.15cqw;
}

.v-desktop small {
  display: flex;
  align-items: center;
  gap: 0.5cqw;
  color: var(--muted);
  font-size: 0.9cqw;
  font-weight: 600;
}

.v-desktop .flag {
  width: 1cqw;
  height: 1cqw;
}

.v-desktop .swatch {
  width: 2cqw;
  height: 2cqw;
}

/* ---------- Tablet (Calendar) ---------- */
.v-tablet {
  display: grid;
  align-content: start;
  gap: 2.4cqw;
  padding: 6cqw 4.5cqw;
}

.cal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.cal-title {
  font-size: 5.4cqw;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.cal-title::first-letter {
  text-transform: uppercase;
}

.nav-btn {
  display: grid;
  place-items: center;
  width: 6cqw;
  height: 6cqw;
  border: 0.25cqw solid var(--line);
  border-radius: 50%;
}

.nav-btn svg {
  width: 3.4cqw;
  height: 3.4cqw;
}

.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1.2cqw;
}

.dow {
  color: var(--muted);
  font-size: 2.2cqw;
  font-weight: 700;
  text-align: center;
}

.cell {
  position: relative;
  display: grid;
  justify-items: center;
  align-content: start;
  gap: 1cqw;
  height: 10.5cqw;
  padding-top: 1.2cqw;
  border-radius: 2cqw;
  background: var(--surface);
}

.cell.out {
  background: transparent;
  color: var(--muted);
}

.num {
  display: grid;
  place-items: center;
  width: 5cqw;
  height: 5cqw;
  border-radius: 50%;
  font-size: 2.5cqw;
  font-weight: 700;
}

.cell.today .num {
  background: var(--highlight);
  color: var(--on-highlight);
  font-weight: 800;
}

.cell-flag {
  position: absolute;
  top: 0.8cqw;
  right: 0.8cqw;
  width: 1.8cqw;
  height: 1.8cqw;
}

.cell-dots {
  display: flex;
  gap: 0.6cqw;
}

.cell-dots span {
  width: 1.3cqw;
  height: 1.3cqw;
  border-radius: 50%;
}

.band {
  position: absolute;
  right: -0.6cqw;
  bottom: 0.8cqw;
  left: -0.6cqw;
  height: 0.6cqw;
  background: var(--accent);
}

.cell.first .band {
  left: 1.2cqw;
  border-radius: 0.3cqw 0 0 0.3cqw;
}

.cell.last .band {
  right: 1.2cqw;
  border-radius: 0 0.3cqw 0.3cqw 0;
}

.panel-title {
  margin: 1cqw 0 0;
  color: var(--muted);
  font-size: 2.2cqw;
  font-weight: 700;
  text-transform: uppercase;
}

.v-tablet .item {
  min-height: 11cqw;
  padding: 1.6cqw 2.4cqw;
  border-radius: 3cqw;
}

.v-tablet .tick {
  width: 5cqw;
  height: 5cqw;
}

.v-tablet .item b {
  font-size: 3cqw;
}

.v-tablet .swatch {
  width: 4.6cqw;
  height: 4.6cqw;
}

/* ---------- Shared goal rows (tablet + phone) ---------- */
.item {
  display: flex;
  align-items: center;
  gap: 3cqw;
  border: 0.25cqw solid var(--line);
  background: var(--surface);
}

.item.done {
  background: transparent;
}

.tick {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border: 0.6cqw solid var(--muted);
  border-radius: 50%;
}

.done .tick {
  border-color: var(--accent);
  background: var(--accent);
  color: var(--on-accent);
}

.tick svg {
  width: 70%;
  height: 70%;
}

.item-text {
  display: grid;
  flex: 1;
  gap: 0.8cqw;
  min-width: 0;
}

.item b {
  overflow: hidden;
  font-weight: 700;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.done b {
  color: var(--muted);
  text-decoration: line-through;
}

.swatch {
  flex-shrink: 0;
  border-radius: 50%;
}

.done .swatch {
  opacity: 0.45;
}

/* ---------- Phone (today) ---------- */
.v-phone {
  position: relative;
  display: grid;
  align-content: start;
  gap: 3cqw;
  padding: 3.5cqw 5cqw;
}

.status {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 9cqw;
  padding: 0 3cqw;
  font-size: 4.2cqw;
}

.island {
  width: 26cqw;
  height: 7.5cqw;
  border-radius: 99px;
  background: var(--black);
}

.batt {
  width: 8cqw;
  height: 3.8cqw;
  border: 0.6cqw solid var(--fg);
  border-radius: 1.2cqw;
  box-shadow: inset 4cqw 0 0 var(--fg);
}

.ptop {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 3cqw;
  border-bottom: 0.3cqw solid var(--line);
}

.pmark {
  width: 11cqw;
  height: 6cqw;
}

.v-phone .pill {
  padding: 1.2cqw 2.6cqw;
  font-size: 3.2cqw;
}

.v-phone .dot-btn {
  width: 7.5cqw;
  height: 7.5cqw;
  border-width: 0.4cqw;
}

.ptitle {
  margin: 2cqw 0 0;
  font-size: 7.4cqw;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.progress {
  display: flex;
  align-items: center;
  gap: 3cqw;
  color: var(--muted);
  font-size: 3.4cqw;
  font-weight: 800;
}

.progress i {
  flex: 1;
  height: 1.8cqw;
  overflow: hidden;
  border-radius: 99px;
  background: var(--line);
}

.progress em {
  display: block;
  width: 50%;
  height: 100%;
  background: var(--accent);
}

.v-phone .item {
  min-height: 17cqw;
  padding: 2.4cqw 3.6cqw;
  border-radius: 5cqw;
}

.v-phone .tick {
  width: 7.5cqw;
  height: 7.5cqw;
}

.v-phone .item b {
  font-size: 4.3cqw;
}

.v-phone small {
  display: flex;
  align-items: center;
  gap: 1.6cqw;
  color: var(--muted);
  font-size: 3.2cqw;
  font-weight: 600;
}

.v-phone .flag {
  width: 3.6cqw;
  height: 3.6cqw;
}

.v-phone .swatch {
  width: 7.5cqw;
  height: 7.5cqw;
}

.fab {
  position: absolute;
  right: 6cqw;
  bottom: 7cqw;
  display: grid;
  place-items: center;
  width: 15cqw;
  height: 15cqw;
  border-radius: 50%;
  background: var(--accent);
  color: var(--on-accent);
  font-size: 9cqw;
  font-weight: 500;
  box-shadow: 0 2cqw 5cqw color-mix(in srgb, var(--black) 35%, transparent);
}
</style>
