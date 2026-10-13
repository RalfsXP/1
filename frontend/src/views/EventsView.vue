<script setup>
import { computed, ref } from 'vue'
import { PlusIcon } from '@heroicons/vue/24/outline'
import EventForm from '@/components/EventForm.vue'
import EventItem from '@/components/EventItem.vue'
import { events, loadEvents, nextOccurrence } from '@/lib/events'
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
const past = computed(() => all.value.filter((o) => o.past).sort((a, b) => b.date.localeCompare(a.date)))

const formOpen = ref(false)
const editing = ref(null)
function add() {
  editing.value = null
  formOpen.value = true
}
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

    <ul v-if="upcoming.length" class="list">
      <EventItem v-for="occ in upcoming" :key="occ.key" :occurrence="occ" @edit="edit" />
    </ul>

    <details v-if="past.length" class="past-group">
      <summary class="section-label">{{ $t('events.past') }} ({{ past.length }})</summary>
      <ul class="list">
        <EventItem v-for="occ in past" :key="occ.key" :occurrence="occ" @edit="edit" />
      </ul>
    </details>

    <button type="button" class="float-btn add" :aria-label="$t('events.new')" @click="add">
      <PlusIcon aria-hidden="true" />
    </button>

    <EventForm :open="formOpen" :event="editing" @close="formOpen = false" />
  </div>
</template>

<style scoped>
.page {
  padding-bottom: calc(env(safe-area-inset-bottom) + 104px);
}

.add-wide {
  display: none;
  flex-shrink: 0;
}

.past-group summary {
  margin-bottom: 10px;
  padding: 0 4px;
  cursor: pointer;
}

.past-group summary:hover {
  color: var(--fg);
}

@media (min-width: 860px) {
  .page {
    padding-bottom: 32px;
  }

  .add-wide {
    display: inline-flex;
  }
}
</style>
