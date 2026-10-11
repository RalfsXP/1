import { locale, t } from './i18n'

/**
 * Date helpers for goal periods. Dates are local `Date`s at midnight, or
 * "YYYY-MM-DD" strings when they travel to/from the API. Weeks start on Monday.
 */
export const PERIODS = ['day', 'week', 'month', 'year', 'custom']

/** URL segment ↔ period, e.g. /goals/weekly → 'week'. */
export const ROUTE_TO_PERIOD = { daily: 'day', weekly: 'week', monthly: 'month', yearly: 'year', custom: 'custom' }
export const PERIOD_TO_ROUTE = Object.fromEntries(Object.entries(ROUTE_TO_PERIOD).map(([k, v]) => [v, k]))

const pad = (n) => String(n).padStart(2, '0')

export const toISO = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

export function parseISO(value) {
  if (value instanceof Date) return new Date(value.getFullYear(), value.getMonth(), value.getDate())
  const [y, m, d] = String(value).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

export const today = () => parseISO(new Date())

export const addDays = (date, n) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + n)

/** 1 = Monday … 7 = Sunday. */
export const isoWeekday = (date) => date.getDay() || 7

/** ISO-8601 week number. */
export function isoWeek(date) {
  const thursday = addDays(date, 4 - isoWeekday(date))
  const jan1 = new Date(thursday.getFullYear(), 0, 1)
  // Round the day count: a DST change makes the millisecond difference an hour short.
  return Math.floor(Math.round((thursday - jan1) / 86400000) / 7) + 1
}

/** First day of the period containing `date`. */
export function periodStart(period, date) {
  const d = parseISO(date)
  if (period === 'week') return addDays(d, 1 - isoWeekday(d))
  if (period === 'month') return new Date(d.getFullYear(), d.getMonth(), 1)
  if (period === 'year') return new Date(d.getFullYear(), 0, 1)
  return d
}

/** Start of the period `n` periods after the one starting on `start`. */
export function shiftPeriod(period, start, n = 1) {
  const d = parseISO(start)
  if (period === 'week') return addDays(d, 7 * n)
  if (period === 'month') return new Date(d.getFullYear(), d.getMonth() + n, 1)
  if (period === 'year') return new Date(d.getFullYear() + n, 0, 1)
  return addDays(d, n)
}

/** Last day of the period starting on `start`. */
export const periodEnd = (period, start) => addDays(shiftPeriod(period, start, 1), -1)

/** Last day of `goal`'s occurrence starting on `start` (custom periods carry their own end). */
const occurrenceEnd = (goal, start) =>
  goal.period === 'custom' ? parseISO(goal.period_end) : periodEnd(goal.period, start)

/** Whether `goal` has an occurrence in the period starting on `start` (mirrors Goal::occursOn). */
export function occursOn(goal, start) {
  const s = toISO(parseISO(start))
  if (s !== toISO(periodStart(goal.period, start))) return false
  if (!goal.repeats) return s === goal.period_start
  if (s < goal.period_start || (goal.repeat_until && s > goal.repeat_until)) return false
  return goal.period !== 'day' || !goal.repeat_days?.length || goal.repeat_days.includes(isoWeekday(parseISO(s)))
}

/**
 * Moment an occurrence is due: the end of its period, or an earlier `due_at`
 * on one-off goals.
 */
function deadlineOf(goal, start) {
  const end = addDays(occurrenceEnd(goal, start), 1)
  if (!goal.repeats && goal.due_at) {
    const due = new Date(goal.due_at)
    if (due < end) return due
  }
  return end
}

/**
 * Every occurrence of `goal` whose period overlaps [from, to], as
 * { key, goal, start: 'YYYY-MM-DD', done, deadline }.
 */
export function occurrencesIn(goal, from, to) {
  const fromISO = toISO(parseISO(from))
  const toISOStr = toISO(parseISO(to))
  // One-off goals have a single occurrence: start there (a custom period may begin before `from`).
  let cursor = periodStart(goal.period, from)
  if (!goal.repeats || toISO(cursor) < goal.period_start) cursor = parseISO(goal.period_start)

  const result = []
  // Safety cap: the widest range (Overview: a year back + the rest of this year) stays under ~800 days.
  for (let i = 0; i < 1000 && toISO(cursor) <= toISOStr; i++, cursor = shiftPeriod(goal.period, cursor, 1)) {
    const start = toISO(cursor)
    if (toISO(occurrenceEnd(goal, cursor)) < fromISO) continue
    if (!goal.repeats && start !== goal.period_start) break
    if (!occursOn(goal, start)) continue
    result.push({
      key: `${goal.id}:${start}`,
      goal,
      start,
      end: toISO(occurrenceEnd(goal, cursor)),
      done: goal.completed_on?.includes(start) ?? false,
      completedAt: goal.completed_at?.[start] ? new Date(goal.completed_at[start]) : null,
      deadline: deadlineOf(goal, start),
    })
    if (!goal.repeats) break
  }
  return result
}

/* ---------- Labels ---------- */

/** "1 day" / "21 days"; Latvian: "1 diena", "21 diena", "11 dienas". */
export function dayCount(n) {
  const one = locale.value === 'lv' ? n % 10 === 1 && n % 100 !== 11 : n === 1
  return `${n} ${t(one ? 'picker.day1' : 'picker.dayN')}`
}

const monthName = (d, short = false) => t(short ? 'common.monthsShort' : 'common.months')[d.getMonth()]

/** "14. oktobris" / "14 October" (short: "14. okt." / "14 Oct"). */
export function dayMonth(date, short = false) {
  const d = parseISO(date)
  return locale.value === 'lv' ? `${d.getDate()}. ${monthName(d, short)}` : `${d.getDate()} ${monthName(d, short)}`
}

export const weekdayName = (date, short = false) =>
  t(short ? 'common.weekdaysShort' : 'common.weekdays')[isoWeekday(parseISO(date)) - 1]

export const monthLabel = (date) => {
  const d = parseISO(date)
  return t('calendar.monthYear', { month: monthName(d), year: d.getFullYear() })
}

/** "13.–23. okt." / "13–23 Oct", or "28. sept. – 4. okt." across months. */
export function rangeLabel(from, to) {
  const a = parseISO(from)
  const b = parseISO(to)
  if (toISO(a) === toISO(b)) return dayMonth(a, true)
  const sameMonth = a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear()
  const first = sameMonth ? (locale.value === 'lv' ? `${a.getDate()}.` : `${a.getDate()}`) : dayMonth(a, true)
  return sameMonth ? `${first}–${dayMonth(b, true)}` : `${first} – ${dayMonth(b, true)}`
}

/** Human label for the period starting on `start` (custom periods need their `end`). */
export function periodLabel(period, start, end = null) {
  const d = parseISO(start)
  if (period === 'custom') return rangeLabel(d, end ?? d)
  if (period === 'week') {
    return t('calendar.weekRange', { n: isoWeek(d), range: rangeLabel(d, periodEnd('week', d)) })
  }
  if (period === 'month') return monthLabel(d)
  if (period === 'year') return String(d.getFullYear())
  return `${weekdayName(d)}, ${dayMonth(d)}`
}
