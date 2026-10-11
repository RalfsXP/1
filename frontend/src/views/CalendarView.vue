<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon } from '@heroicons/vue/24/outline'
import CalendarMonth from '@/components/CalendarMonth.vue'
import GoalForm from '@/components/GoalForm.vue'
import GoalItem from '@/components/GoalItem.vue'
import ProgressMeter from '@/components/ProgressMeter.vue'
import { loadRange, occurrencesBetween, refreshTick, setDone } from '@/lib/goals'
import { PERIODS, PERIOD_TO_ROUTE, periodEnd, periodLabel, periodStart, toISO } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

const router = useRouter()

const month = ref(toISO(periodStart('month', todayISO.value)))
const selected = ref(todayISO.value)

// The visible grid runs from the Monday before the 1st to the Sunday after the last day.
const range = computed(() => ({
  from: periodStart('week', month.value),
  to: periodEnd('week', periodEnd('month', month.value)),
}))

watch([range, refreshTick], ([{ from, to }]) => loadRange(from, to).catch(() => {}), { immediate: true })

const occurrences = computed(() => occurrencesBetween(range.value.from, range.value.to))

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

// Picking a day in another month follows it there…
watch(selected, (iso) => {
  const m = toISO(periodStart('month', iso))
  if (m !== month.value) month.value = m
})

// …and paging to another month moves the selection into it (today if it's there, else the 1st).
watch(month, (m) => {
  if (toISO(periodStart('month', selected.value)) === m) return
  selected.value = toISO(periodStart('month', todayISO.value)) === m ? todayISO.value : m
})

const dayItems = computed(() => groups.value.flatMap((g) => g.items))

const formOpen = ref(false)
const editing = ref(null)
const formDefaults = ref({ period: 'day', date: selected.value })

function add() {
  editing.value = null
  formDefaults.value = { period: 'day', date: selected.value }
  formOpen.value = true
}

function edit(occurrence) {
  editing.value = occurrence.goal
  formOpen.value = true
}

const toggle = (occurrence) => setDone(occurrence, !occurrence.done).catch(() => {})

const openWeek = (monday) => router.push({ name: 'goals', params: { period: 'weekly' }, query: { date: monday } })
</script>

<template>
  <div class="page calendar-page">
    <CalendarMonth
      v-model:month="month"
      :selected="selected"
      :occurrences="occurrences"
      @select="selected = $event"
      @open-week="openWeek"
      @open="edit"
    />

    <section class="day" :aria-label="periodLabel('day', selected)">
      <header class="day-head">
        <h2 class="section-title day-title">{{ periodLabel('day', selected) }}</h2>
        <button type="button" class="icon-btn add" :aria-label="$t('goal.new')" @click="add">
          <PlusIcon aria-hidden="true" />
        </button>
      </header>

      <ProgressMeter :done="dayItems.filter((o) => o.done).length" :total="dayItems.length" />

      <p v-if="!groups.length" class="empty-state">{{ $t('goal.emptyDay') }}</p>

      <div v-for="group in groups" :key="group.period" class="group">
        <RouterLink
          class="section-label group-title"
          :to="{ name: 'goals', params: { period: PERIOD_TO_ROUTE[group.period] }, query: { date: group.start } }"
        >
          {{ $t(`periods.${group.period}`) }}
        </RouterLink>
        <ul class="list">
          <GoalItem v-for="occ in group.items" :key="occ.key" :occurrence="occ" @toggle="toggle" @edit="edit" />
        </ul>
      </div>
    </section>

    <GoalForm :open="formOpen" :goal="editing" :defaults="formDefaults" @close="formOpen = false" />
  </div>
</template>

<style scoped>
.day {
  display: grid;
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

.add {
  border-color: var(--seagrass);
  background: var(--seagrass);
  color: var(--black);
}

.add:hover {
  color: var(--black);
}

.group {
  display: grid;
  gap: 8px;
}

.group-title:hover {
  color: var(--seagrass);
}

/* Side-by-side only when the calendar keeps enough room for readable badges. */
@media (min-width: 1180px) {
  .calendar-page {
    grid-template-columns: minmax(0, 1fr) 340px;
    align-items: start;
  }

  .day {
    position: sticky;
    top: 0;
    padding-top: 6px;
  }
}
</style>
