import { computed, ref } from 'vue'
import { locale, t } from './i18n'
import { parseISO, toISO } from './periods'

/** Shared clock for countdowns; a 30 s tick is plenty for "in 3 hours". */
export const now = ref(Date.now())
setInterval(() => (now.value = Date.now()), 30000)

/** Today's date as "YYYY-MM-DD"; rolls over at midnight with the shared clock. */
export const todayISO = computed(() => toISO(new Date(now.value)))

const MINUTE = 60000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

/**
 * Time until `deadline` in the user's language ("pēc 3 dienām" / "in 3 days"),
 * or since it when overdue ("pirms 2 stundām"). Intl handles the plurals.
 */
export function timeLeft(deadline, at = now.value) {
  const diff = deadline.getTime() - at
  const abs = Math.abs(diff)
  const sign = diff < 0 ? -1 : 1

  let value
  let unit
  if (abs >= 60 * DAY) [value, unit] = [Math.floor(abs / (30 * DAY)), 'month']
  else if (abs >= 14 * DAY) [value, unit] = [Math.floor(abs / (7 * DAY)), 'week']
  else if (abs >= DAY) [value, unit] = [Math.floor(abs / DAY), 'day']
  else if (abs >= HOUR) [value, unit] = [Math.floor(abs / HOUR), 'hour']
  else [value, unit] = [Math.max(1, Math.ceil(abs / MINUTE)), 'minute']

  const text = new Intl.RelativeTimeFormat(locale.value, { numeric: 'always' }).format(sign * value, unit)
  return { text, overdue: diff < 0, soon: diff >= 0 && diff < DAY }
}

const intlLocale = () => (locale.value === 'en' ? 'en-GB' : locale.value)

/** "trešd., 14. okt." / "Wed, 14 Oct", plus the time for daily goals. */
function formatCompleted(date, withTime) {
  const opts = { weekday: 'short', day: 'numeric', month: 'short' }
  if (withTime) Object.assign(opts, { hour: '2-digit', minute: '2-digit' })
  // Non-breaking spaces keep the date on one line when the status text wraps.
  return new Intl.DateTimeFormat(intlLocale(), opts).format(date).replace(/ /g, '\u00a0')
}

/** "3 days" / "3 dienas"; Latvian uses the singular after numbers ending in 1 (except 11): "21 dienu". */
function days(n) {
  if (locale.value === 'lv') return `${n} ${n % 10 === 1 && n % 100 !== 11 ? 'dienu' : 'dienas'}`
  return new Intl.NumberFormat('en', { style: 'unit', unit: 'day', unitDisplay: 'long' }).format(n)
}

/**
 * How a completed occurrence finished: early (on an earlier day than its
 * deadline), late, or on time — with when it was ticked off.
 */
export function completionInfo(occurrence) {
  const at = occurrence.completedAt
  if (!at) return { kind: 'done', text: t('goal.done') }

  const when = formatCompleted(at, occurrence.goal.period === 'day')
  // The deadline is exclusive (midnight after the last day), so step back 1 ms for its calendar day.
  const dueDay = parseISO(new Date(occurrence.deadline.getTime() - 1))
  const doneDay = parseISO(at)
  const dayDiff = Math.round((dueDay - doneDay) / 86400000)

  if (at > occurrence.deadline) {
    const late = Math.max(0, -dayDiff)
    return { kind: 'late', text: late ? t('goal.finishedLateBy', { n: days(late), when }) : t('goal.finishedLate', { when }) }
  }
  if (toISO(doneDay) < toISO(dueDay)) return { kind: 'early', text: t('goal.finishedEarly', { n: days(dayDiff), when }) }
  return { kind: 'done', text: t('goal.finishedOnTime', { when }) }
}
