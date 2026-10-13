<script setup>
import { reactive, ref, watch } from 'vue'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'
import BadgePicker from '@/components/BadgePicker.vue'
import BottomSheet from '@/components/BottomSheet.vue'
import DatePicker from '@/components/DatePicker.vue'
import GoalBadge from '@/components/GoalBadge.vue'
import { REPEATS, createEvent, deleteEvent, updateEvent } from '@/lib/events'
import { categoryStyle } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { todayISO } from '@/lib/timeLeft'

// Create or edit an event. `date` pre-fills the day for a new one (e.g. the day picked in the calendar).
const props = defineProps({
  open: { type: Boolean, default: false },
  event: { type: Object, default: null },
  date: { type: String, default: null },
})
const emit = defineEmits(['close'])

const form = reactive({})
const saving = ref(false)
const confirmingDelete = ref(false)
const errors = ref([])

function reset() {
  const ev = props.event
  const style = categoryStyle('event')
  Object.assign(form, {
    title: ev?.title ?? '',
    date: ev?.date ?? props.date ?? todayISO.value,
    time: ev?.time ?? '',
    repeat: ev?.repeat ?? null,
    customBadge: Boolean(ev?.badge_color || ev?.badge_icon),
    color: ev?.badge_color ?? style.color,
    icon: ev?.badge_icon ?? style.icon,
  })
  errors.value = []
  confirmingDelete.value = false
}

watch(() => props.open, (open) => open && reset(), { immediate: true })

// Until the user customises the badge, it follows the default event badge.
function useDefaultBadge() {
  if (form.customBadge) return
  Object.assign(form, categoryStyle('event'))
}

const payload = () => ({
  title: form.title.trim(),
  date: form.date,
  time: form.time || null,
  repeat: form.repeat,
  badge_color: form.customBadge ? form.color : null,
  badge_icon: form.customBadge ? form.icon : null,
})

async function save() {
  if (!form.title.trim() || !form.date) return
  saving.value = true
  errors.value = []
  try {
    if (props.event) await updateEvent(props.event.id, payload())
    else await createEvent(payload())
    emit('close')
  } catch (e) {
    errors.value = e.response?.status === 422
      ? Object.values(e.response.data.errors ?? {}).map((list) => list[0])
      : [t('auth.errorGeneric')]
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!confirmingDelete.value) {
    confirmingDelete.value = true
    return
  }
  saving.value = true
  try {
    await deleteEvent(props.event.id)
    emit('close')
  } catch {
    errors.value = [t('auth.errorGeneric')]
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BottomSheet :open="open" :title="event ? $t('events.edit') : $t('events.new')" @close="emit('close')">
    <form class="form" novalidate @submit.prevent="save">
      <label class="form-field">
        <span class="form-label">{{ $t('events.name') }}</span>
        <input v-model="form.title" class="input" type="text" maxlength="120" required enterkeyhint="done" />
      </label>

      <div class="form-field">
        <span class="form-label">{{ $t('events.date') }}</span>
        <DatePicker v-model="form.date" mode="day" />
      </div>

      <div class="row">
        <label class="form-field time">
          <span class="form-label">{{ $t('events.time') }}</span>
          <input v-model="form.time" class="input" type="time" :placeholder="$t('events.allDay')" />
        </label>
        <button v-if="form.time" type="button" class="btn btn-ghost all-day" @click="form.time = ''">{{ $t('events.allDay') }}</button>
      </div>

      <div class="form-field">
        <span class="form-label">{{ $t('events.repeat') }}</span>
        <div class="segmented" role="group" :aria-label="$t('events.repeat')">
          <button type="button" :aria-pressed="!form.repeat" @click="form.repeat = null">{{ $t('events.once') }}</button>
          <button
            v-for="r in REPEATS"
            :key="r"
            type="button"
            :aria-pressed="form.repeat === r"
            :aria-label="$t(`periods.every.${r}`)"
            @click="form.repeat = r"
          >
            {{ $t(`periods.short.${r}`) }}
          </button>
        </div>
      </div>

      <div class="form-field">
        <span class="form-label">{{ $t('goal.badge') }}</span>
        <div class="badge-row">
          <GoalBadge :title="form.title || $t('events.name')" :color="form.color" :icon="form.icon" />
          <label class="toggle">
            <input v-model="form.customBadge" type="checkbox" @change="useDefaultBadge" />
            <span>{{ $t('goal.customBadge') }}</span>
          </label>
        </div>
      </div>
      <BadgePicker v-if="form.customBadge" v-model:color="form.color" v-model:icon="form.icon" />

      <ul v-if="errors.length" class="errors" role="alert">
        <li v-for="msg in errors" :key="msg"><InformationCircleIcon aria-hidden="true" />{{ msg }}</li>
      </ul>

      <div class="actions">
        <button v-if="event" type="button" class="btn btn-ghost btn-lg" :disabled="saving" @click="remove">
          {{ confirmingDelete ? $t('goal.confirmDelete') : $t('goal.delete') }}
        </button>
        <button type="submit" class="btn btn-primary btn-lg" :disabled="saving || !form.title.trim()">
          {{ saving ? $t('goal.saving') : $t('goal.save') }}
        </button>
      </div>
    </form>
  </BottomSheet>
</template>

<style scoped>
.form {
  display: grid;
  gap: 18px;
}

.row {
  display: flex;
  align-items: flex-end;
  gap: 10px;
}

.time {
  width: 150px;
}

.all-day {
  height: 52px;
}

.toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 32px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
}

.toggle input {
  width: 20px;
  height: 20px;
  margin: 0;
  accent-color: var(--accent);
}

.badge-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.errors {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.85rem;
}

.errors li {
  display: flex;
  gap: 6px;
  align-items: center;
}

.errors svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  color: var(--accent);
}

.actions {
  display: flex;
  gap: 10px;
}

.actions .btn {
  flex: 1;
}
</style>
