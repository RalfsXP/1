<script setup>
import { computed, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import EventForm from '@/components/EventForm.vue'
import AnimatedList from '@/components/AnimatedList.vue'
import EventItem from '@/components/EventItem.vue'
import Fold from '@/components/Fold.vue'
import { useAddAction } from '@/lib/addAction'
import { countdown, dayUnit, daysUntil, eventBadge, events, loadEvents, nextOccurrence } from '@/lib/events'
import { readableOn } from '@/lib/goals'
import { GOAL_ICONS } from '@/lib/icons'
import { dayMonth, weekdayName } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

/*
 * Events with a countdown to each one's next occurrence, soonest first.
 * One-off events that have passed sit in a collapsed "Past" group, newest first.
 */
const loaded = ref(false)
const failed = ref(false)
loadEvents()
  .catch(() => (failed.value = true))
  .finally(() => (loaded.value = true))

const all = computed(() =>
  events.value.map((event) => {
    const next = nextOccurrence(event, todayISO.value)
    return { key: event.id, event, date: next ?? event.date, past: !next }
  }),
)

const upcoming = computed(() =>
  all.value
    .filter((o) => !o.past)
    .sort((a, b) => a.date.localeCompare(b.date) || (a.event.time ?? '').localeCompare(b.event.time ?? '') || a.event.title.localeCompare(b.event.title)),
)
// The soonest one gets the big card; the rest follow as rows.
const hero = computed(() => upcoming.value[0] ?? null)
const heroDays = computed(() => (hero.value ? daysUntil(hero.value.date, todayISO.value) : 0))
const rest = computed(() => upcoming.value.slice(1))
const past = computed(() => all.value.filter((o) => o.past).sort((a, b) => b.date.localeCompare(a.date)))

const formOpen = ref(false)
const editing = ref(null)
function add() {
  editing.value = null
  formOpen.value = true
}
useAddAction(add, 'events.new')
function edit(occurrence) {
  editing.value = occurrence.event
  formOpen.value = true
}
</script>

<template>
  <div class="page narrow">
    <header class="page-head">
      <div>
        <h1 class="page-title">{{ $t('events.title') }}</h1>
      </div>
      <button type="button" class="btn btn-primary add-wide" @click="add">
        <PlusIcon aria-hidden="true" />{{ $t('events.new') }}
      </button>
    </header>

    <p v-if="failed" class="empty-state" role="alert">{{ $t('auth.errorGeneric') }}</p>

    <div v-else-if="loaded && !all.length" class="empty-state">
      <span>{{ $t('events.empty') }}</span>
      <button type="button" class="btn btn-primary" @click="add">{{ $t('events.new') }}</button>
    </div>

    <button v-if="hero" type="button" class="hero rise" @click="edit(hero)">
      <span class="hero-badge" :style="{ background: eventBadge(hero.event).color, color: readableOn(eventBadge(hero.event).color) }">
        <component :is="GOAL_ICONS[eventBadge(hero.event).icon] ?? GOAL_ICONS.calendar" aria-hidden="true" />
      </span>
      <span class="hero-body">
        <span class="hero-kicker">{{ $t('dashboard.upcoming') }}</span>
        <span class="hero-title">{{ hero.event.title }}</span>
        <span class="hero-when">
          {{ weekdayName(hero.date) }}, {{ dayMonth(hero.date, true) }}<template v-if="hero.event.time"> · {{ hero.event.time }}</template>
        </span>
      </span>
      <span v-if="heroDays > 2" class="hero-count" :aria-label="countdown(hero.date, todayISO)">
        <span class="hero-num">{{ heroDays }}</span>
        <span class="hero-unit">{{ dayUnit(heroDays) }}</span>
      </span>
      <span v-else class="hero-pill">{{ countdown(hero.date, todayISO) }}</span>
    </button>

    <AnimatedList v-if="rest.length">
      <EventItem v-for="occ in rest" :key="occ.key" :occurrence="occ" @edit="edit" />
    </AnimatedList>

    <Fold v-if="past.length">
      <template #summary>{{ $t('events.past') }} ({{ past.length }})</template>
      <AnimatedList>
        <EventItem v-for="occ in past" :key="occ.key" :occurrence="occ" @edit="edit" />
      </AnimatedList>
    </Fold>

    <EventForm :open="formOpen" :event="editing" @close="formOpen = false" />
  </div>
</template>

<style scoped>
/* The next event: an ink card with a big countdown. */
.hero {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  width: 100%;
  padding: 20px 22px 20px 18px;
  border: 0;
  border-radius: 30px;
  background: var(--dock);
  box-shadow: var(--float-shadow);
  color: #ffffff;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: transform 0.3s var(--spring);
}

@media (hover: hover) {
  .hero:hover {
    transform: translateY(-2px);
  }
}

.hero:active {
  transform: scale(0.98);
}

.hero-badge {
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  animation: pop 0.6s var(--spring) backwards 0.2s;
}

.hero-badge svg {
  width: 26px;
  height: 26px;
}

.hero-body {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.hero-kicker {
  color: rgb(255 255 255 / 0.6);
  font-size: 0.78rem;
  font-weight: 700;
}

.hero-title {
  display: -webkit-box;
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
  overflow-wrap: anywhere;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.hero-when {
  color: rgb(255 255 255 / 0.65);
  font-size: 0.85rem;
  font-weight: 500;
}

.hero-when::first-letter,
.hero-pill::first-letter {
  text-transform: uppercase;
}

.hero-count {
  display: grid;
  justify-items: end;
  line-height: 0.85;
}

.hero-num {
  font-family: var(--font-display);
  font-size: 3.4rem;
  font-weight: 800;
  letter-spacing: -0.06em;
  font-variant-numeric: tabular-nums;
}

.hero-unit {
  margin-top: 6px;
  color: rgb(255 255 255 / 0.65);
  font-size: 0.8rem;
  font-weight: 600;
}

.hero-pill {
  white-space: nowrap;
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--lav);
  color: var(--ink);
  font-size: 0.85rem;
  font-weight: 800;
}

.add-wide {
  display: none;
  flex-shrink: 0;
}

@media (min-width: 860px) {
  .add-wide {
    display: inline-flex;
  }
}
</style>
