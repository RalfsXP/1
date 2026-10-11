import { computed, reactive, ref, watch } from 'vue'
import api from './api'
import { user } from './auth'
import { occurrencesIn, toISO } from './periods'

/*
 * Goal store shared by the calendar and the category pages. Goals are cached
 * by id; each view loads the date range it shows and derives occurrences.
 */

export const PRIORITIES = ['low', 'high', 'important']
const PRIORITY_RANK = { important: 0, high: 1, low: 2 }

/** User-picked colours start from these; any hex is allowed (badges/flags are the colour exception). */
export const SWATCHES = ['#66a182', '#3b82f6', '#a855f7', '#ec4899', '#ef4444', '#f59e0b', '#14b8a6', '#64748b']

/** Built-in category look; mirrors config/goals.php default_styles (every period needs one). */
const DEFAULT_STYLES = {
  day: { color: '#66a182', icon: 'sun' },
  week: { color: '#3b82f6', icon: 'bolt' },
  month: { color: '#a855f7', icon: 'star' },
  year: { color: '#f59e0b', icon: 'trophy' },
  custom: { color: '#14b8a6', icon: 'flag' },
}

const cache = reactive(new Map())

const allGoals = computed(() => [...cache.values()])

function upsert(goal) {
  cache.set(goal.id, goal)
  return goal
}

/**
 * A range load only knows completions inside [from, to]; keep the ones the
 * cache already has outside it so other views don't lose their ticks.
 */
function upsertWindow(goal, from, to) {
  const cached = cache.get(goal.id)
  if (cached?.completed_at) {
    const outside = Object.entries(cached.completed_at).filter(([d]) => d < from || d > to)
    goal.completed_at = { ...Object.fromEntries(outside), ...goal.completed_at }
    goal.completed_on = Object.keys(goal.completed_at).sort()
  }
  return upsert(goal)
}

/* Range loads: one request per range at a time, and none again while it's fresh. */
const FRESH_MS = 30_000
const loads = new Map() // "from|to" -> { at, pending, promise }

/** Bumped when the window regains focus; views watch it to refresh stale ranges. */
export const refreshTick = ref(0)
addEventListener('focus', () => refreshTick.value++)

/** Fetch every goal with an occurrence in [from, to] (deduplicated, cached for 30 s). */
export function loadRange(from, to) {
  const [f, t] = [toISO(from), toISO(to)]
  const key = `${f}|${t}`
  const hit = loads.get(key)
  if (hit && (hit.pending || Date.now() - hit.at < FRESH_MS)) return hit.promise

  const entry = { at: 0, pending: true }
  entry.promise = api
    .get('/goals', { params: { from: f, to: t } })
    .then(({ data }) => {
      data.forEach((goal) => upsertWindow(goal, f, t))
      Object.assign(entry, { at: Date.now(), pending: false })
      return data
    })
    .catch((e) => {
      loads.delete(key)
      throw e
    })
  loads.set(key, entry)
  return entry.promise
}

// Signing out (or in as someone else) must never show the previous account's goals.
watch(user, (next, prev) => {
  if (!next || next.id !== prev?.id) {
    cache.clear()
    loads.clear()
  }
})

/** Occurrences of cached goals in [from, to], optionally of one period, sorted for display. */
export function occurrencesBetween(from, to, period = null) {
  return allGoals.value
    .filter((g) => !period || g.period === period)
    .flatMap((g) => occurrencesIn(g, from, to))
    .sort(compareOccurrences)
}

/** Open before done, then Important → High → Low, then soonest deadline. */
export function compareOccurrences(a, b) {
  return (
    a.done - b.done ||
    PRIORITY_RANK[a.goal.priority] - PRIORITY_RANK[b.goal.priority] ||
    a.deadline - b.deadline ||
    a.goal.title.localeCompare(b.goal.title)
  )
}

export async function createGoal(payload) {
  const { data } = await api.post('/goals', payload)
  return upsert(data)
}

export async function updateGoal(id, payload) {
  const { data } = await api.patch(`/goals/${id}`, payload)
  return upsert(data)
}

export async function deleteGoal(id) {
  await api.delete(`/goals/${id}`)
  cache.delete(id)
}

// Latest toggle per occurrence: a slower, older response must not overwrite a newer tap.
const toggleSeq = new Map()

/** Tick or untick one occurrence; the UI updates first and rolls back on failure. */
export async function setDone(occurrence, done) {
  const goal = cache.get(occurrence.goal.id)
  const seq = (toggleSeq.get(occurrence.key) ?? 0) + 1
  toggleSeq.set(occurrence.key, seq)
  const isLatest = () => toggleSeq.get(occurrence.key) === seq
  const before = { on: goal.completed_on, at: goal.completed_at }
  const at = { ...before.at }
  if (done) at[occurrence.start] = new Date().toISOString()
  else delete at[occurrence.start]
  goal.completed_at = at
  goal.completed_on = done
    ? [...new Set([...before.on, occurrence.start])]
    : before.on.filter((d) => d !== occurrence.start)
  try {
    const url = `/goals/${goal.id}/completions/${occurrence.start}`
    const { data } = done ? await api.put(url) : await api.delete(url)
    if (isLatest()) upsert(data)
  } catch (e) {
    if (!isLatest()) return
    goal.completed_on = before.on
    goal.completed_at = before.at
    throw e
  }
}

/* ---------- Badge styles ---------- */

export const categoryStyle = (period) => ({ ...DEFAULT_STYLES[period], ...user.value?.goal_styles?.[period] })

/** A goal's badge: its own colour/icon, else its category's. */
export function badgeFor(goal) {
  const base = categoryStyle(goal.period)
  return { color: goal.badge_color ?? base.color, icon: goal.badge_icon ?? base.icon }
}

export async function saveCategoryStyle(period, style) {
  const { data } = await api.put('/user/goal-styles', { [period]: style })
  user.value = data
}

/** Black or white text, whichever reads better on `hex` (WCAG relative luminance). */
export function readableOn(hex) {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const c = parseInt(hex.slice(i, i + 2), 16) / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b
  return lum > 0.179 ? 'var(--black)' : 'var(--white)'
}
