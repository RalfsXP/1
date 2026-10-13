import { computed, reactive, watch } from 'vue'
import api from './api'
import { user } from './auth'
import { categoryStyle, refreshTick } from './goals'
import { locale } from './i18n'
import { addDays, parseISO, toISO } from './periods'

/*
 * Events: dates that happen once or every week / month / year (birthdays, rent day…).
 * There is nothing to tick off; the app counts down to the next occurrence. The whole
 * list is small, so it is loaded at once and occurrences are worked out here.
 */

export const REPEATS = ['week', 'month', 'year']

const cache = reactive(new Map())
export const events = computed(() => [...cache.values()])

const FRESH_MS = 30_000
let load = null // { at, promise }

/** Fetch every event (deduplicated, cached for 30 s; `force` refetches). */
export function loadEvents({ force = false } = {}) {
  if (!force && load && (load.pending || Date.now() - load.at < FRESH_MS)) return load.promise
  const entry = { at: 0, pending: true }
  entry.promise = api
    .get('/events')
    .then(({ data }) => {
      cache.clear()
      data.forEach((ev) => cache.set(ev.id, ev))
      Object.assign(entry, { at: Date.now(), pending: false })
      return data
    })
    .catch((e) => {
      load = null
      throw e
    })
  load = entry
  return entry.promise
}

// Coming back to the tab refreshes stale data, like goals.
watch(refreshTick, () => load && loadEvents().catch(() => {}))

// Signing out (or in as someone else) must never show the previous account's events.
watch(user, (next, prev) => {
  if (!next || next.id !== prev?.id) {
    cache.clear()
    load = null
  }
})

export async function createEvent(payload) {
  const { data } = await api.post('/events', payload)
  cache.set(data.id, data)
  return data
}

export async function updateEvent(id, payload) {
  const { data } = await api.patch(`/events/${id}`, payload)
  cache.set(data.id, data)
  return data
}

export async function deleteEvent(id) {
  await api.delete(`/events/${id}`)
  cache.delete(id)
}

/* ---------- Occurrences ---------- */

const daysInMonth = (year, month) => new Date(year, month + 1, 0).getDate()

/**
 * The n-th occurrence (0 = the event's own date). Monthly/yearly ones keep their day of the
 * month and fall back to the month's last day when it's shorter (31st → 30th, 29 Feb → 28 Feb).
 */
function nth(ev, start, n) {
  if (ev.repeat === 'week') return addDays(start, 7 * n)
  const months = ev.repeat === 'year' ? 12 * n : n
  const year = start.getFullYear() + Math.floor((start.getMonth() + months) / 12)
  const month = (start.getMonth() + months) % 12
  return new Date(year, month, Math.min(start.getDate(), daysInMonth(year, month)))
}

/** Index of the first occurrence on or after `from` (null when a one-off is already past). */
function firstIndex(ev, start, from) {
  if (start >= from) return 0
  if (!ev.repeat) return null
  let n
  if (ev.repeat === 'week') n = Math.floor(Math.round((from - start) / 86400000) / 7)
  else if (ev.repeat === 'month') n = (from.getFullYear() - start.getFullYear()) * 12 + from.getMonth() - start.getMonth()
  else n = from.getFullYear() - start.getFullYear()
  n = Math.max(0, n - 1)
  while (nth(ev, start, n) < from) n++
  return n
}

/** Every occurrence of every event in [from, to], soonest first: { key, date, event }. */
export function eventOccurrences(from, to) {
  const [lo, hi] = [parseISO(from), parseISO(to)]
  const list = []
  for (const ev of events.value) {
    const start = parseISO(ev.date)
    let n = firstIndex(ev, start, lo)
    if (n === null) continue
    for (let d = nth(ev, start, n); d <= hi; d = nth(ev, start, ++n)) {
      list.push({ key: `${ev.id}:${toISO(d)}`, date: toISO(d), event: ev })
      if (!ev.repeat) break
    }
  }
  return list.sort((a, b) => compareOccurrences(a, b))
}

/** The next occurrence on or after `fromISO` as "YYYY-MM-DD", or null for a past one-off. */
export function nextOccurrence(ev, fromISO) {
  const start = parseISO(ev.date)
  const n = firstIndex(ev, start, parseISO(fromISO))
  return n === null ? null : toISO(nth(ev, start, n))
}

/** Soonest first; on the same day all-day events come before timed ones, then by time and title. */
export function compareOccurrences(a, b) {
  return (
    a.date.localeCompare(b.date) ||
    (a.event.time ?? '').localeCompare(b.event.time ?? '') ||
    a.event.title.localeCompare(b.event.title)
  )
}

/** Whole days from `fromISO` to `dateISO` (DST-safe). */
export const daysUntil = (dateISO, fromISO) => Math.round((parseISO(dateISO) - parseISO(fromISO)) / 86400000)

/** "šodien" / "rīt" / "pēc 27 dienām" — "today" / "tomorrow" / "in 27 days" (Intl handles the plurals). */
export function countdown(dateISO, fromISO) {
  return new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }).format(daysUntil(dateISO, fromISO), 'day')
}

/** The unit under a big day count: "diena" / "dienas" (LV: singular after 1, 21, 31… but not 11), "day" / "days". */
export function dayUnit(n) {
  if (locale.value === 'lv') return n % 10 === 1 && n % 100 !== 11 ? 'diena' : 'dienas'
  return n === 1 ? 'day' : 'days'
}

/** An event's badge: its own colour/icon, else the default event badge (Settings). */
export function eventBadge(ev) {
  const base = categoryStyle('event')
  return { color: ev.badge_color ?? base.color, icon: ev.badge_icon ?? base.icon }
}
