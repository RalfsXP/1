<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { InformationCircleIcon } from '@heroicons/vue/24/outline'
import BadgePicker from '@/components/BadgePicker.vue'
import BottomSheet from '@/components/BottomSheet.vue'
import DateField from '@/components/DateField.vue'
import DatePicker from '@/components/DatePicker.vue'
import GoalBadge from '@/components/GoalBadge.vue'
import PriorityFlag from '@/components/PriorityFlag.vue'
import { PRIORITIES, categoryStyle, createGoal, deleteGoal, updateGoal } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { PERIODS, periodEnd, periodStart, toISO } from '@/lib/periods'

// Create or edit a goal. `defaults` pre-fills period/date for a new one.
const props = defineProps({
  open: { type: Boolean, default: false },
  goal: { type: Object, default: null },
  defaults: { type: Object, default: () => ({ period: 'day', date: toISO(new Date()) }) },
})
const emit = defineEmits(['close'])

const form = reactive({})
const saving = ref(false)
const confirmingDelete = ref(false)
const errors = ref([])

const pad = (n) => String(n).padStart(2, '0')
const timeOf = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`

function reset() {
  const g = props.goal
  const period = g?.period ?? props.defaults.period
  const style = categoryStyle(period)
  Object.assign(form, {
    title: g?.title ?? '',
    notes: g?.notes ?? '',
    priority: g?.priority ?? 'low',
    period,
    // The day the user actually means; `date` is that day snapped to the period, so switching
    // Day → Year → Day comes back to the same day instead of 1 January.
    anchor: g?.period_start ?? props.defaults.date,
    date: toISO(periodStart(period, g?.period_start ?? props.defaults.date)),
    endDate: g?.period_end ?? g?.period_start ?? props.defaults.date,
    repeats: g?.repeats ?? false,
    repeatDays: g?.repeat_days ?? [],
    repeatUntil: g?.repeat_until ?? '',
    hasDeadline: Boolean(g?.due_at),
    dueDate: g?.due_at ? toISO(new Date(g.due_at)) : '',
    dueTime: g?.due_at ? timeOf(new Date(g.due_at)) : '18:00',
    customBadge: Boolean(g?.badge_color || g?.badge_icon),
    color: g?.badge_color ?? style.color,
    icon: g?.badge_icon ?? style.icon,
  })
  errors.value = []
  confirmingDelete.value = false
}

watch(() => props.open, (open) => open && reset(), { immediate: true })

// Until the user customises the badge, it follows the category's look.
function useCategoryBadge() {
  if (form.customBadge) return
  const style = categoryStyle(form.period)
  form.color = style.color
  form.icon = style.icon
}

/**
 * Switching type (a tap on the type pills) re-snaps the chosen day to the new period and
 * resets the badge. Done on the tap, not in a watcher: loading a goal into the form also
 * changes `form.period`, and that must not touch its saved dates.
 */
function choosePeriod(period) {
  if (period === form.period) return
  const previous = form.period
  const oldDate = form.date
  form.period = period
  useCategoryBadge()
  if (!form.anchor) return
  form.date = toISO(periodStart(period, form.anchor))
  if (period === 'custom') {
    // Coming from a week/month/year: keep that stretch as the custom range.
    form.repeats = false
    const end = previous !== 'day' && oldDate ? toISO(periodEnd(previous, oldDate)) : form.date
    form.endDate = end >= form.date ? end : form.date
  }
  if (form.repeatUntil) form.repeatUntil = toISO(periodStart(period, form.repeatUntil))
}

const start = computed(() => (form.date ? periodStart(form.period, form.date) : null))
/** Last day of the chosen period: an "earlier deadline" can't be after it. */
const lastDay = computed(() => (start.value && form.period !== 'custom' ? toISO(periodEnd(form.period, start.value)) : null))

watch(
  () => form.date,
  (date) => {
    if (!date) return
    // A pick in the calendar becomes the new anchor (period switches don't change it).
    if (toISO(periodStart(form.period, form.anchor)) !== date) form.anchor = date
    // Moving the start past "until" would make an empty series; a deadline must stay inside the period.
    if (form.repeatUntil && form.repeatUntil < date) form.repeatUntil = ''
    if (form.dueDate && (form.dueDate < date || (lastDay.value && form.dueDate > lastDay.value))) form.dueDate = ''
  },
)
const isCustom = computed(() => form.period === 'custom')

/** The repeating series as the date pickers draw it (later occurrences get a lighter band). */
const series = computed(() =>
  form.repeats && !isCustom.value && start.value
    ? {
        period: form.period,
        start: toISO(start.value),
        days: form.period === 'day' ? form.repeatDays : [],
        until: form.repeatUntil || null,
      }
    : null,
)

function toggleDay(day) {
  form.repeatDays = form.repeatDays.includes(day)
    ? form.repeatDays.filter((d) => d !== day)
    : [...form.repeatDays, day].sort()
}

const repeatSummary = computed(() =>
  form.period === 'day' && form.repeatDays.length && form.repeatDays.length < 7
    ? form.repeatDays.map((d) => t('common.weekdaysShort')[d - 1]).join(', ')
    : t(`periods.every.${form.period}`),
)

function payload() {
  const repeats = form.repeats
  return {
    title: form.title.trim(),
    notes: form.notes.trim() || null,
    priority: form.priority,
    period: form.period,
    period_start: toISO(start.value),
    period_end: isCustom.value ? form.endDate : null,
    repeats,
    repeat_days: repeats && form.period === 'day' && form.repeatDays.length ? form.repeatDays : null,
    repeat_until: repeats && form.repeatUntil ? form.repeatUntil : null,
    due_at:
      !repeats && !isCustom.value && form.hasDeadline && form.dueDate
        ? new Date(`${form.dueDate}T${form.dueTime || '23:59'}`).toISOString()
        : null,
    badge_color: form.customBadge ? form.color : null,
    badge_icon: form.customBadge ? form.icon : null,
  }
}

async function save() {
  if (!form.title.trim() || !start.value) return
  saving.value = true
  errors.value = []
  try {
    if (props.goal) await updateGoal(props.goal.id, payload())
    else await createGoal(payload())
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
    await deleteGoal(props.goal.id)
    emit('close')
  } catch {
    errors.value = [t('auth.errorGeneric')]
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BottomSheet :open="open" :title="goal ? $t('goal.edit') : $t('goal.new')" @close="emit('close')">
    <form class="form" novalidate @submit.prevent="save">
      <label class="form-field">
        <span class="form-label">{{ $t('goal.title') }}</span>
        <input v-model="form.title" class="input" type="text" maxlength="255" required enterkeyhint="done" />
      </label>

      <div class="form-field">
        <span class="form-label">{{ $t('goal.period') }}</span>
        <div class="segmented tight" role="group" :aria-label="$t('goal.period')">
          <button v-for="p in PERIODS" :key="p" type="button" :aria-pressed="form.period === p" @click="choosePeriod(p)">
            {{ $t(`periods.short.${p}`) }}
          </button>
        </div>
      </div>

      <div class="form-field">
        <span class="form-label">{{ isCustom ? $t('goal.dates') : $t('goal.date') }}</span>
        <DatePicker v-model="form.date" v-model:end="form.endDate" :mode="form.period" :series="series" />
      </div>

      <template v-if="!isCustom">
        <div class="form-field">
          <span class="form-label">{{ $t('goal.repeat') }}</span>
          <div class="segmented" role="group" :aria-label="$t('goal.repeat')">
            <button type="button" :aria-pressed="!form.repeats" @click="form.repeats = false">{{ $t('goal.once') }}</button>
            <button type="button" :aria-pressed="form.repeats" @click="form.repeats = true">{{ $t(`periods.every.${form.period}`) }}</button>
          </div>
        </div>

        <template v-if="form.repeats">
          <div v-if="form.period === 'day'" class="form-field">
            <span class="form-label">{{ $t('goal.onDays') }}</span>
            <div class="days" role="group" :aria-label="$t('goal.onDays')">
              <button
                v-for="(name, i) in $t('common.weekdaysShort')"
                :key="name"
                type="button"
                class="day-chip"
                :aria-label="$t('common.weekdays')[i]"
                :aria-pressed="form.repeatDays.includes(i + 1)"
                @click="toggleDay(i + 1)"
              >
                {{ name }}
              </button>
            </div>
            <span class="hint">{{ repeatSummary }}</span>
          </div>
          <div class="form-field">
            <span class="form-label">{{ $t('goal.until') }}</span>
            <DateField v-model="form.repeatUntil" :label="$t('goal.until')" :mode="form.period" :min="form.date" :series="series" />
          </div>
        </template>

        <template v-else>
          <label class="toggle">
            <input v-model="form.hasDeadline" type="checkbox" />
            <span>{{ $t('goal.deadline') }}</span>
          </label>
          <div v-if="form.hasDeadline" class="deadline">
            <DateField v-model="form.dueDate" :label="$t('goal.deadline')" :min="form.date" :max="lastDay" />
            <input v-model="form.dueTime" class="input time" type="time" :aria-label="$t('goal.time')" />
          </div>
        </template>
      </template>

      <div class="form-field">
        <span class="form-label">{{ $t('priority.label') }}</span>
        <div class="segmented outline" role="group" :aria-label="$t('priority.label')">
          <button v-for="p in PRIORITIES" :key="p" type="button" :aria-pressed="form.priority === p" @click="form.priority = p">
            <PriorityFlag :priority="p" decorative />{{ $t(`priority.${p}`) }}
          </button>
        </div>
      </div>

      <label class="form-field">
        <span class="form-label">{{ $t('goal.notes') }}</span>
        <textarea v-model="form.notes" class="input" maxlength="2000" rows="2" />
      </label>

      <div class="form-field">
        <span class="form-label">{{ $t('goal.badge') }}</span>
        <div class="badge-row">
          <GoalBadge :title="form.title || $t('goal.title')" :color="form.color" :icon="form.icon" />
          <label class="toggle">
            <input v-model="form.customBadge" type="checkbox" @change="useCategoryBadge" />
            <span>{{ $t('goal.customBadge') }}</span>
          </label>
        </div>
      </div>
      <BadgePicker v-if="form.customBadge" v-model:color="form.color" v-model:icon="form.icon" />

      <ul v-if="errors.length" class="errors" role="alert">
        <li v-for="msg in errors" :key="msg"><InformationCircleIcon aria-hidden="true" />{{ msg }}</li>
      </ul>

      <div class="actions">
        <button v-if="goal" type="button" class="btn btn-ghost btn-lg" :disabled="saving" @click="remove">
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

.hint {
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
}

.days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 6px;
}

.day-chip {
  height: 42px;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: transparent;
  color: var(--fg);
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.2s, border-color 0.2s, color 0.2s;
}

.day-chip:hover {
  border-color: var(--accent);
}

.day-chip[aria-pressed='true'] {
  border-color: var(--accent);
  background: var(--accent);
  color: var(--on-accent);
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

.deadline {
  display: grid;
  gap: 8px;
}

.deadline .time {
  width: 140px;
}

/* Five period options on a phone: tighter pills. */
.segmented.tight button {
  padding: 0 4px;
  font-size: 0.74rem;
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
