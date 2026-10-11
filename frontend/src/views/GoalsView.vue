<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeftIcon, ChevronRightIcon, PlusIcon } from '@heroicons/vue/24/outline'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import HoldHint from '@/components/HoldHint.vue'
import ProgressMeter from '@/components/ProgressMeter.vue'
import { loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { ROUTE_TO_PERIOD, addDays, parseISO, periodEnd, periodLabel, periodStart, shiftPeriod, toISO } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

// One goal category (daily/weekly/monthly/yearly/custom) for one period at a time.
const route = useRoute()
const router = useRouter()

// Read once: this view is keyed by path, and while it fades out the route already
// points at the next page — a reactive read would flash "periods.undefined".
const period = ROUTE_TO_PERIOD[route.params.period]
const isCustom = period === 'custom'
const dateParam = ref(route.query.date)
watch(
  () => route.query.date,
  (date) => route.name === 'goals' && (dateParam.value = date),
)

// Custom periods have no fixed length to page through: show the past year and what's ahead.
const start = computed(() =>
  isCustom ? addDays(parseISO(todayISO.value), -365) : periodStart(period, dateParam.value || todayISO.value),
)
const end = computed(() => (isCustom ? addDays(parseISO(todayISO.value), 3 * 365) : periodEnd(period, start.value)))
const isCurrent = computed(() => toISO(start.value) === toISO(periodStart(period, todayISO.value)))

watch([start, end, refreshTick], ([from, to]) => loadRange(from, to).catch(() => {}), { immediate: true })

const occurrences = computed(() => occurrencesBetween(start.value, end.value, period))
const open = computed(() => occurrences.value.filter((o) => !o.done))
const done = computed(() => occurrences.value.filter((o) => o.done))

function go(n) {
  router.replace({ query: { date: toISO(shiftPeriod(period, start.value, n)) } })
}

const goCurrent = () => router.replace({ query: {} })

const formOpen = ref(false)
const editing = ref(null)
const formDefaults = computed(() => ({ period, date: isCustom ? todayISO.value : toISO(start.value) }))

function add() {
  editing.value = null
  formOpen.value = true
}

function edit(occurrence) {
  editing.value = occurrence.goal
  formOpen.value = true
}

const toggle = (occurrence) => setDone(occurrence, !occurrence.done).catch(() => {})
</script>

<template>
  <div class="page narrow">
    <header class="head">
      <h1 class="page-title">{{ $t(`periods.${period}`) }}</h1>
      <div class="head-actions">
        <button type="button" class="btn btn-primary add-wide" @click="add">
          <PlusIcon aria-hidden="true" />{{ $t('goal.new') }}
        </button>
      </div>
    </header>

    <div v-if="!isCustom" class="period-nav">
      <button type="button" class="icon-btn" :aria-label="$t('calendar.prev')" @click="go(-1)">
        <ChevronLeftIcon aria-hidden="true" />
      </button>
      <div class="period-label">
        <span>{{ periodLabel(period, start) }}</span>
        <button v-if="!isCurrent" type="button" class="current" @click="goCurrent">{{ $t(`periods.this.${period}`) }}</button>
      </div>
      <button type="button" class="icon-btn" :aria-label="$t('calendar.next')" @click="go(1)">
        <ChevronRightIcon aria-hidden="true" />
      </button>
    </div>

    <ProgressMeter :done="done.length" :total="occurrences.length" />
    <HoldHint v-if="occurrences.length" />

    <p v-if="!occurrences.length" class="empty-state">{{ $t('goal.empty') }}</p>

    <ul v-if="open.length" class="list">
      <GoalItem v-for="occ in open" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
    </ul>

    <details v-if="done.length" class="done-group" open>
      <summary class="section-label">{{ $t('goal.completed') }} ({{ done.length }})</summary>
      <ul class="list">
        <GoalItem v-for="occ in done" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
      </ul>
    </details>

    <button type="button" class="fab" :aria-label="$t('goal.new')" @click="add">
      <PlusIcon aria-hidden="true" />
    </button>

    <GoalForm :open="formOpen" :goal="editing" :defaults="formDefaults" @close="formOpen = false" />
  </div>
</template>

<style scoped>
.page {
  gap: 18px;
  padding-bottom: calc(env(safe-area-inset-bottom) + 104px);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.head-actions {
  display: flex;
  gap: 8px;
}

.add-wide {
  display: none;
  height: 40px;
}

.add-wide svg {
  width: 18px;
  height: 18px;
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
  font-size: 0.95rem;
  font-weight: 700;
  text-align: center;
}

.period-label span::first-letter {
  text-transform: uppercase;
}

.current {
  padding: 2px 8px;
  border: 0;
  border-radius: 999px;
  background: none;
  color: var(--accent);
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.current:hover {
  text-decoration: underline;
}

.done-group summary {
  margin-bottom: 10px;
  cursor: pointer;
}

.done-group summary:hover {
  color: var(--fg);
}

/* Thumb-reach add button on phones, above the home indicator. */
.fab {
  position: fixed;
  right: max(20px, env(safe-area-inset-right));
  bottom: max(20px, calc(env(safe-area-inset-bottom) + 12px));
  z-index: 40;
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  border: 0;
  border-radius: 50%;
  background: var(--accent);
  color: var(--on-accent);
  box-shadow: 0 8px 24px color-mix(in srgb, var(--black) 35%, transparent);
  cursor: pointer;
  transition: transform 0.2s;
}

.fab:active {
  transform: scale(0.94);
}

.fab svg {
  width: 28px;
  height: 28px;
}

@media (min-width: 860px) {
  .page {
    padding-bottom: 32px;
  }

  .add-wide {
    display: inline-flex;
  }

  .fab {
    display: none;
  }
}
</style>
