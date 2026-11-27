<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AnimatedList from '@/components/AnimatedList.vue'
import Fold from '@/components/Fold.vue'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import ProgressMeter from '@/components/ProgressMeter.vue'
import { useAddAction } from '@/lib/addAction'
import { loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { GOAL_TABS } from '@/lib/nav'
import { ROUTE_TO_PERIOD, addDays, parseISO, periodEnd, periodLabel, periodStart, shiftPeriod, toISO } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

// Goals: category tabs on top, then one category (daily/weekly/monthly/yearly/custom) for one period at a time.
const route = useRoute()
const router = useRouter()

// Every category shares this page (AppShell keys it 'goals'), so the tabs stay while the list swaps.
// Follow the route only while it's a goals route: when leaving, the route already points at the next
// page during the fade-out — reading it blindly would flash "periods.undefined".
const period = ref(ROUTE_TO_PERIOD[route.params.period])
const direction = ref('next')
const tabsEl = ref(null)
const tabIndex = (key) => GOAL_TABS.findIndex((tab) => tab.key === key)
watch(
  () => route.params.period,
  (p) => {
    if (route.name !== 'goals' || !ROUTE_TO_PERIOD[p]) return
    direction.value = tabIndex(ROUTE_TO_PERIOD[p]) > tabIndex(period.value) ? 'next' : 'prev'
    period.value = ROUTE_TO_PERIOD[p]
    // Phones: bring the chosen tab into view in the sideways-scrolling row.
    nextTick(() => tabsEl.value?.querySelector('.tab.active')?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }))
  },
)
const isCustom = computed(() => period.value === 'custom')
const tint = computed(() => GOAL_TABS[tabIndex(period.value)].tint)
const dateParam = ref(route.query.date)
watch(
  () => route.query.date,
  (date) => route.name === 'goals' && (dateParam.value = date),
)

// Custom periods have no fixed length to page through: show the past year and what's ahead.
const start = computed(() =>
  isCustom.value ? addDays(parseISO(todayISO.value), -365) : periodStart(period.value, dateParam.value || todayISO.value),
)
const end = computed(() => (isCustom.value ? addDays(parseISO(todayISO.value), 3 * 365) : periodEnd(period.value, start.value)))
const isCurrent = computed(() => toISO(start.value) === toISO(periodStart(period.value, todayISO.value)))

watch([start, end, refreshTick], ([from, to]) => loadRange(from, to).catch(() => {}), { immediate: true })

const occurrences = computed(() => occurrencesBetween(start.value, end.value, period.value))
const open = computed(() => occurrences.value.filter((o) => !o.done))
const done = computed(() => occurrences.value.filter((o) => o.done))

function go(n) {
  router.replace({ query: { date: toISO(shiftPeriod(period.value, start.value, n)) } })
}

const goCurrent = () => router.replace({ query: {} })

const formOpen = ref(false)
const editing = ref(null)
const formDefaults = computed(() => ({ period: period.value, date: isCustom.value ? todayISO.value : toISO(start.value) }))

function add() {
  editing.value = null
  formOpen.value = true
}
useAddAction(add, 'goal.new')

function edit(occurrence) {
  editing.value = occurrence.goal
  formOpen.value = true
}

const toggle = (occurrence) => setDone(occurrence, !occurrence.done).catch(() => {})
</script>

<template>
  <div class="page narrow">
    <header class="page-head">
      <div>
        <h1 class="page-title">{{ $t('nav.goals') }}</h1>
      </div>
      <button type="button" class="btn btn-primary add-wide" @click="add">
        <PlusIcon aria-hidden="true" />{{ $t('goal.new') }}
      </button>
    </header>

    <!-- Categories: one pill each, the current one is ink. -->
    <nav ref="tabsEl" class="tabs" :aria-label="$t('nav.goals')">
      <RouterLink
        v-for="tab in GOAL_TABS"
        :key="tab.period"
        :to="{ name: 'goals', params: { period: tab.period } }"
        class="tab"
        :class="{ active: tab.key === period }"
        :aria-current="tab.key === period ? 'page' : undefined"
        :style="{ '--tint': `var(--${tab.tint})` }"
        replace
      >
        <span class="tab-icon"><component :is="tab.icon" aria-hidden="true" /></span>
        {{ $t(`periods.short.${tab.key}`) }}
      </RouterLink>
    </nav>

    <!-- The chosen category slides in from the side of its tab. -->
    <Transition :name="`swap-${direction}`" mode="out-in">
    <div :key="period" class="goal-body">
    <!-- Period navigator + progress on one card ("Today / Tuesday, 13 October"). -->
    <section v-if="!isCustom || occurrences.length" class="card period-card" :style="{ '--tint': `var(--${tint})` }">
      <div v-if="!isCustom" class="period-nav">
        <button type="button" class="icon-btn bare" :aria-label="$t('calendar.prev')" @click="go(-1)">
          <ChevronLeftIcon aria-hidden="true" />
        </button>
        <Transition name="period" mode="out-in">
          <div :key="toISO(start)" class="period-label">
            <span class="period-main">{{ isCurrent ? $t(`periods.this.${period}`) : periodLabel(period, start) }}</span>
            <span v-if="isCurrent" class="period-sub">{{ periodLabel(period, start) }}</span>
            <button v-else type="button" class="current" @click="goCurrent">{{ $t(`periods.this.${period}`) }}</button>
          </div>
        </Transition>
        <button type="button" class="icon-btn bare" :aria-label="$t('calendar.next')" @click="go(1)">
          <ChevronRightIcon aria-hidden="true" />
        </button>
      </div>
      <ProgressMeter class="period-meter" :done="done.length" :total="occurrences.length" />
    </section>
    <HoldHint v-if="occurrences.length" />

    <p v-if="!occurrences.length" class="empty-state">{{ $t('goal.empty') }}</p>

    <AnimatedList>
      <GoalItem v-for="occ in open" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
    </AnimatedList>

    <Fold v-if="done.length" open>
      <template #summary>{{ $t('goal.completed') }} ({{ done.length }})</template>
      <AnimatedList>
        <GoalItem v-for="occ in done" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
      </AnimatedList>
    </Fold>
    </div>
    </Transition>

    <GoalForm :open="formOpen" :goal="editing" :defaults="formDefaults" @close="formOpen = false" />
  </div>
</template>

<style scoped>
.add-wide {
  display: none;
  flex-shrink: 0;
}

.goal-body {
  display: grid;
  gap: 16px;
  min-width: 0;
}

/* An empty open list keeps no gap. */
.goal-body > .list:not(:has(> *)) {
  display: none;
}

.swap-next-enter-active,
.swap-prev-enter-active {
  transition: transform 0.4s var(--ease), opacity 0.3s ease;
}

.swap-next-leave-active,
.swap-prev-leave-active {
  transition: transform 0.14s ease-in, opacity 0.14s ease-in;
}

.swap-next-enter-from,
.swap-prev-leave-to {
  transform: translateX(32px);
  opacity: 0;
}

.swap-next-leave-to,
.swap-prev-enter-from {
  transform: translateX(-32px);
  opacity: 0;
}

/* One swipeable row on phones. */
.tabs {
  display: flex;
  gap: 8px;
  margin: 0 -18px;
  padding: 2px 18px 6px;
  overflow-x: auto;
  scrollbar-width: none;
}

.tabs::-webkit-scrollbar {
  display: none;
}

.tab {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 8px;
  height: 46px;
  padding: 0 16px 0 6px;
  border-radius: 999px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  color: var(--fg);
  font-size: 0.92rem;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 0.25s var(--ease), color 0.25s var(--ease), transform 0.25s var(--spring);
}

.tab:active {
  transform: scale(0.94);
}

.tab-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--tint);
  color: var(--ink);
}

.tab-icon svg {
  width: 18px;
  height: 18px;
}

.tab.active {
  background: var(--accent);
  color: var(--on-accent);
}

/* The period card takes its category's pastel as a soft wash. */
.period-card {
  display: grid;
  gap: 16px;
  padding: 10px 10px 18px;
  background: color-mix(in srgb, var(--tint) var(--wash-strong), var(--surface));
}

.period-card:not(:has(.period-nav)) {
  padding: 18px;
}

.period-card .icon-btn.bare {
  background: var(--wash-fill);
}

.period-nav {
  display: flex;
  align-items: center;
  gap: 8px;
}

.period-label {
  display: grid;
  flex: 1;
  justify-items: center;
  gap: 2px;
  min-width: 0;
  text-align: center;
}

.period-main {
  font-family: var(--font-display);
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.period-sub {
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 600;
}

.period-main::first-letter,
.period-sub::first-letter {
  text-transform: uppercase;
}

.period-meter {
  padding: 0 10px;
}

.period-meter :deep(.track) {
  background: var(--wash-fill);
}

.current {
  padding: 2px 8px;
  border: 0;
  border-radius: 999px;
  background: none;
  color: var(--link);
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

@media (hover: hover) {
  .current:hover {
    text-decoration: underline;
  }
}

.period-enter-active {
  transition: opacity 0.25s ease, transform 0.35s var(--ease);
}

.period-leave-active {
  transition: opacity 0.1s ease;
}

.period-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.period-leave-to {
  opacity: 0;
}

@media (min-width: 860px) {
  .add-wide {
    display: inline-flex;
  }

  .tabs {
    margin: 0;
    padding: 2px 0 6px;
    overflow: visible;
    flex-wrap: wrap;
  }
}
</style>
