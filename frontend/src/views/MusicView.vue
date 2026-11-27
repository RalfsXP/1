<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowTopRightOnSquareIcon,
  MagnifyingGlassIcon,
  MusicalNoteIcon,
  PauseIcon,
  PlayIcon,
} from '@heroicons/vue/24/outline'
import AppLogo from '@/components/AppLogo.vue'
import BottomSheet from '@/components/BottomSheet.vue'
import Expand from '@/components/Expand.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import api from '@/lib/api'
import { vSlidePill } from '@/lib/directives'
import { t } from '@/lib/i18n'

// Music prototype (/music, public, not in any menu): a lyric snippet → matching songs with 30 s previews.
// The backend (GET /api/music/search) asks Genius, then Deezer / iTunes for the preview clips.
const query = ref('')
const results = ref(null) // null = nothing searched yet
const mode = ref('lyrics')
const loading = ref(false)
const error = ref('')
const searchId = ref(0)
// Paging: the backend sends 5 songs per page; "Load more" asks for the next page of the same search.
const page = ref(1)
const hasMore = ref(false)
const loadingMore = ref(false)
let current = null // { q, by } of the shown results — the field may have changed since

// Best match by: lyrics (Genius) or song name (Deezer titles + artists). Remembered; switching re-runs the search.
const BY_KEY = 'merkis-music-by'
const by = ref('lyrics')
try {
  if (localStorage.getItem(BY_KEY) === 'title') by.value = 'title'
} catch {
  // Storage unavailable: lyrics.
}
function setBy(value) {
  if (by.value === value) return
  by.value = value
  try {
    localStorage.setItem(BY_KEY, value)
  } catch {
    // Not remembered, still works.
  }
  if (results.value && query.value.trim().length >= 3) search()
}

async function search() {
  const q = query.value.trim()
  if (q.length < 3 || loading.value) return
  loading.value = true
  error.value = ''
  stop()
  try {
    const { data } = await api.get('/music/search', { params: { q, by: by.value } })
    searchId.value++
    current = { q, by: by.value }
    results.value = []
    addResults(data)
    mode.value = data.mode
  } catch (e) {
    error.value = e.response?.status === 429 ? t('music.throttle') : t('music.error')
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  if (!current || loadingMore.value || !hasMore.value) return
  loadingMore.value = true
  error.value = ''
  const id = searchId.value
  try {
    const { data } = await api.get('/music/search', { params: { ...current, page: page.value + 1 } })
    if (id === searchId.value) addResults(data) // a new search started meanwhile: drop this page
  } catch (e) {
    error.value = e.response?.status === 429 ? t('music.throttle') : t('music.error')
  } finally {
    loadingMore.value = false
  }
}

// Same rule as the backend's unique() (same title + artist + album, or the same preview file): the backend merges
// repeats within a page, this catches one that comes back on a later page.
const songKeys = (s) => {
  const norm = (v) => String(v ?? '').trim().toLowerCase().replace(/\s+/g, ' ')
  const keys = [`meta:${norm(s.title)}|${norm(s.artist)}|${norm(s.album)}`]
  if (s.preview) keys.push(`clip:${s.preview.split('?')[0]}`)
  return keys
}

function addResults(data) {
  const seen = new Set(results.value.flatMap(songKeys))
  // Rows get their own id: the same recording on several albums shares one preview file (and Deezer/Genius
  // links can repeat), so neither can tell rows apart.
  const fresh = data.results
    .filter((song) => !songKeys(song).some((k) => seen.has(k)))
    .map((song, i) => ({ ...song, uid: `${searchId.value}-${results.value.length + i}` }))
  results.value = [...results.value, ...fresh]
  page.value = data.page ?? 1
  hasMore.value = !!data.has_more
}

// The field starts one line tall and grows with the text (up to its max-height, then it scrolls).
const field = ref(null)
watch(query, () =>
  nextTick(() => {
    const el = field.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${el.scrollHeight + 3}px` // + the 1.5px top/bottom border
  }),
)

// Enter searches; Shift+Enter makes a new line.
function onKey(e) {
  if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
    e.preventDefault()
    search()
  }
}

// One shared player: starting a clip stops the one before.
let audio = null
const playing = ref(null) // uid of the row that is playing
const progress = ref(0)

function toggle(song) {
  if (!song.preview) return
  if (playing.value === song.uid) {
    stop()
    return
  }
  stop()
  // Listeners use their own clip: a stopped clip can still fire one late timeupdate.
  const clip = (audio = new Audio(song.preview))
  clip.addEventListener('timeupdate', () => {
    if (audio === clip) progress.value = clip.duration ? clip.currentTime / clip.duration : 0
  })
  clip.addEventListener('ended', () => audio === clip && stop())
  playing.value = song.uid
  clip.play().catch((e) => {
    if (audio !== clip || e.name === 'AbortError') return
    stop()
    error.value = t('music.error')
  })
}

function stop() {
  if (audio) {
    audio.pause()
    audio.src = ''
    audio = null
  }
  playing.value = null
  progress.value = 0
}

onBeforeUnmount(stop)

// "Open in" sheet. Apple Music + Deezer go to the song when we know its page; Spotify, YouTube Music and
// SoundCloud open a search for "artist title" (their APIs need keys / are closed). The Apple Music page is
// looked up from the browser when the sheet opens (iTunes Search: free, no key, CORS) — one call per song.
const picked = ref(null)
const appleUrls = ref({}) // song key → music.apple.com URL ('' = not found)

const songKey = (s) => `${s.artist} ${s.title}`

function openIn(song) {
  picked.value = song
  const key = songKey(song)
  if (song.apple_url || key in appleUrls.value) return
  appleUrls.value[key] = null // looking it up
  fetch(`https://itunes.apple.com/search?${new URLSearchParams({ term: `${song.artist} ${song.title}`, entity: 'song', limit: '1' })}`)
    .then((r) => (r.ok ? r.json() : null))
    .then((d) => (appleUrls.value[key] = d?.results?.[0]?.trackViewUrl ?? ''))
    .catch(() => (appleUrls.value[key] = ''))
}

const services = computed(() => {
  const s = picked.value
  if (!s) return []
  const q = encodeURIComponent(`${s.artist} ${s.title}`)
  const apple = s.apple_url || appleUrls.value[songKey(s)]
  return [
    { name: 'Apple Music', url: apple || `https://music.apple.com/us/search?term=${q}`, exact: !!apple },
    { name: 'Spotify', url: `https://open.spotify.com/search/${q}`, exact: false },
    { name: 'YouTube Music', url: `https://music.youtube.com/search?q=${q}`, exact: false },
    { name: 'SoundCloud', url: `https://soundcloud.com/search/sounds?q=${q}`, exact: false },
    { name: 'Deezer', url: s.deezer_url || `https://www.deezer.com/search/${q}`, exact: !!s.deezer_url },
    ...(s.genius_url ? [{ name: t('music.lyrics'), url: s.genius_url, exact: true }] : []),
  ]
})
</script>

<template>
  <div class="music">
    <header class="top">
      <h1 class="brand">
        <RouterLink to="/" class="home"><AppLogo word="music" /></RouterLink>
      </h1>
      <ThemeToggle class="toggle" />
    </header>

    <main class="page narrow">
      <p class="page-sub rise">{{ $t('music.sub') }}</p>

      <div class="filter rise">
        <span id="music-by" class="filter-label">{{ $t('music.by') }}</span>
        <div v-slide-pill class="segmented raised" role="group" aria-labelledby="music-by">
          <button type="button" :aria-pressed="by === 'lyrics'" @click="setBy('lyrics')">{{ $t('music.byLyrics') }}</button>
          <button type="button" :aria-pressed="by === 'title'" @click="setBy('title')">{{ $t('music.byTitle') }}</button>
        </div>
      </div>

      <form class="card search rise" @submit.prevent="search">
        <label class="visually-hidden" for="lyrics">{{ $t('music.label') }}</label>
        <textarea
          id="lyrics"
          ref="field"
          v-model="query"
          class="input"
          rows="2"
          maxlength="300"
          :placeholder="by === 'title' ? $t('music.placeholderTitle') : $t('music.placeholder')"
          autocomplete="off"
          @keydown="onKey"
        />
        <button
          type="submit"
          class="btn go"
          :class="{ busy: loading }"
          :disabled="loading || query.trim().length < 3"
          :aria-busy="loading"
        >
          <MagnifyingGlassIcon aria-hidden="true" />
          <span class="go-label">{{ loading ? $t('music.searching') : $t('music.search') }}</span>
        </button>
      </form>

      <Expand>
        <p v-if="error" class="note" role="alert">{{ error }}</p>
      </Expand>

      <template v-if="results">
        <p v-if="mode === 'title' && by === 'lyrics'" class="note">{{ $t('music.titleMode') }}</p>
        <p v-if="!results.length" class="empty-state">{{ $t('music.empty') }}</p>
        <ul v-else :key="searchId" class="list stagger">
          <li
            v-for="song in results"
            :key="song.uid"
            class="song"
            :class="{ on: playing === song.uid }"
          >
            <div class="cover">
              <img v-if="song.cover" :src="song.cover" alt="" loading="lazy" />
              <MusicalNoteIcon v-else aria-hidden="true" />
            </div>
            <div class="meta">
              <strong>{{ song.title }}</strong>
              <span>{{ song.artist }}</span>
              <span class="extra">
                <span v-if="song.album" class="album">{{ $t('music.album') }}: {{ song.album }}</span>
                <span v-if="song.source" class="source">{{ $t('music.source') }}: {{ song.source }}</span>
              </span>
            </div>
            <button
              type="button"
              class="icon-btn bare"
              :aria-label="$t('music.openIn')"
              :title="$t('music.openIn')"
              @click="openIn(song)"
            >
              <ArrowTopRightOnSquareIcon aria-hidden="true" />
            </button>
            <button
              type="button"
              class="icon-btn play"
              :disabled="!song.preview"
              :aria-label="!song.preview ? $t('music.noPreview') : playing === song.uid ? $t('music.pause') : $t('music.play')"
              :title="!song.preview ? $t('music.noPreview') : undefined"
              @click="toggle(song)"
            >
              <PauseIcon v-if="playing === song.uid" aria-hidden="true" />
              <PlayIcon v-else aria-hidden="true" />
            </button>
            <span class="bar" aria-hidden="true">
              <span :style="{ transform: `scaleX(${playing === song.uid ? progress : 0})` }" />
            </span>
          </li>
        </ul>
        <button
          v-if="hasMore && results.length"
          type="button"
          class="btn more"
          :class="{ busy: loadingMore }"
          :disabled="loadingMore"
          :aria-busy="loadingMore"
          @click="loadMore"
        >
          <span class="more-label">{{ loadingMore ? $t('music.loadingMore') : $t('music.loadMore') }}</span>
        </button>
      </template>
    </main>

    <BottomSheet :open="!!picked" :title="$t('music.openIn')" @close="picked = null">
      <template v-if="picked">
        <p class="picked">
          <strong>{{ picked.title }}</strong>
          <span>{{ picked.artist }}<template v-if="picked.album"> · {{ picked.album }}</template></span>
        </p>
        <ul class="services">
          <li v-for="s in services" :key="s.name">
            <a class="service" :href="s.url" target="_blank" rel="noopener" @click="picked = null">
              <span>{{ s.name }}</span>
              <span class="visually-hidden">({{ s.exact ? $t('music.songLink') : $t('music.searchLink') }})</span>
              <ArrowTopRightOnSquareIcon v-if="s.exact" aria-hidden="true" />
              <MagnifyingGlassIcon v-else aria-hidden="true" />
            </a>
          </li>
        </ul>
      </template>
    </BottomSheet>
  </div>
</template>

<style scoped>
/* Its own page outside the app shell: the window scrolls (like the auth page on phones). */
.music {
  min-height: var(--app-h);
  padding: max(16px, var(--top-inset)) 0 calc(72px + env(safe-area-inset-bottom));
}

/* The sticker lockup with "music" instead of "mērķis", centred; theme toggle top right. */
.top {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 12px;
  padding: 0 18px;
}

.brand {
  grid-column: 2;
  margin: 0;
  font-size: 1.5rem;
}

.home {
  color: inherit;
  text-decoration: none;
}

.toggle {
  justify-self: end;
}

.page {
  margin: 0 auto;
  padding-top: 10px;
}

.filter {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 12px;
}

.filter-label {
  color: var(--muted);
  font-size: 0.86rem;
  font-weight: 700;
}

/* Label + switch share one line, also on a 375px phone (the switch shrinks to ~230px there). */
.filter .segmented {
  flex: 1 1 200px;
  max-width: 280px;
}

/* Phones: the field (two lines, grows with the text) with the full-width Search button under it;
   ≥640px: side by side, the button as tall as the field. */
.search {
  display: grid;
  gap: 10px;
  padding: 12px;
}

.search textarea {
  height: 68px;
  min-height: 68px; /* two lines + padding + border, so typing the first letter doesn't make it jump */
  max-height: 168px;
  padding: 10px 16px;
  line-height: 1.4;
  resize: none;
}

/* Search: ink text on the sticker's colours (lavender, butter dot, a touch of peach) slowly drifting.
   The gradient is a layer twice as wide that slides (transform only), so nothing repaints the button. */
.go {
  position: relative;
  isolation: isolate;
  width: 100%;
  height: 46px;
  overflow: hidden;
  background: var(--lav);
  color: var(--ink);
  font-size: 0.98rem;
}

.go::before {
  position: absolute;
  inset: 0 auto 0 0;
  z-index: -1;
  width: 300%;
  background: linear-gradient(
    100deg,
    var(--lav) 0%,
    var(--butter) 16.66%,
    var(--peach) 25%,
    var(--lav) 33.33%,
    var(--butter) 50%,
    var(--peach) 58.33%,
    var(--lav) 66.66%,
    var(--butter) 83.33%,
    var(--peach) 91.66%,
    var(--lav) 100%
  );
  content: '';
  animation: drift 3s linear infinite;
}

/* One loop = one colour cycle (a third of the layer), so the restart is seamless. */
@keyframes drift {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-33.333%);
  }
}

/* Searching: full colour (it's disabled meanwhile, which would fade it) and a faster drift. */
.go.busy {
  opacity: 1;
}

.go.busy::before {
  animation-duration: 0.9s;
}

.go:disabled:not(.busy) {
  opacity: 0.5;
}

.go:disabled:not(.busy)::before {
  animation-play-state: paused;
}

.go svg {
  stroke-width: 2.4;
}

/* The global reduced-motion rule shortens animations to ~0 ms, which would make an endless one flicker. */
@media (prefers-reduced-motion: reduce) {
  .go::before,
  .more-label {
    animation: none;
  }
}


.note {
  margin: 0;
  color: var(--muted);
  font-size: 0.92rem;
  font-weight: 500;
}

.song {
  position: relative;
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 12px;
  padding: 10px 12px 10px 10px;
  overflow: hidden;
  border-radius: 24px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  transition: background-color 0.3s;
}

.song.on {
  background: color-mix(in srgb, var(--lav) var(--wash), var(--surface));
}

.cover {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  overflow: hidden;
  border-radius: 16px;
  background: var(--lav);
  color: var(--ink);
}

.cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover svg {
  width: 24px;
  height: 24px;
}

.meta {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.meta strong,
.meta span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta strong {
  font-family: var(--font-display);
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.meta span {
  color: var(--muted);
  font-size: 0.9rem;
  font-weight: 500;
}

/* Album + where it came from: tells same-named songs apart. One line each (phones), side by side when wide. */
.meta .extra {
  display: grid;
  gap: 1px;
  margin-top: 3px;
  font-size: 0.78rem;
}

.meta .extra > span {
  font-size: inherit;
}

@media (min-width: 640px) {
  .meta .extra {
    display: flex;
    gap: 12px;
  }

  .meta .extra .album {
    min-width: 0;
  }

  .meta .extra .source {
    flex-shrink: 0;
  }
}

.play {
  background: var(--accent);
  box-shadow: none;
  color: var(--on-accent);
}

.play svg {
  fill: currentColor;
}

.play:disabled {
  background: var(--surface-2);
  color: var(--muted);
  cursor: default;
}

@media (hover: hover) {
  .play:hover:not(:disabled) {
    background: color-mix(in srgb, var(--accent) 86%, var(--bg));
  }
}

/* Load more: full width under the list, its text in the Search button's drifting colours. Pastel text needs a dark
   pill to stay readable (lavender on white is ~1.6:1): --dock = the ink card (ink in light mode, a dark card in dark mode;
   a scoped :global([data-theme=dark]) rule would be dropped by Vue). */
.more {
  width: 100%;
  height: 46px;
  background: var(--dock);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  font-size: 0.98rem;
}

@media (hover: hover) {
  .more:hover:not(:disabled) {
    background: color-mix(in srgb, var(--dock) 86%, var(--fg));
  }
}

/* Loading: full colour (it's disabled meanwhile) and a faster drift. */
.more.busy {
  opacity: 1;
}

/* Gradient text: two colour cycles across a 200%-wide background, sliding one cycle per loop (seamless).
   background-position on one short label is cheap; a transform can't move a text-clipped background. */
.more-label {
  background: linear-gradient(
    100deg,
    var(--lav) 0%,
    var(--butter) 16.66%,
    var(--peach) 33.33%,
    var(--lav) 50%,
    var(--butter) 66.66%,
    var(--peach) 83.33%,
    var(--lav) 100%
  );
  background-size: 200% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: shimmer 2.5s linear infinite;
}

.more.busy .more-label {
  animation-duration: 0.9s;
}

@keyframes shimmer {
  from {
    background-position: 0% 50%;
  }
  to {
    background-position: 100% 50%;
  }
}

/* Clip progress: a thin line along the row's bottom edge (transform only). */
.bar {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 3px;
}

.bar span {
  display: block;
  height: 100%;
  background: var(--fg);
  transform-origin: left;
  transition: transform 0.25s linear;
}

.picked {
  display: grid;
  gap: 2px;
  margin: 0 0 14px;
}

.picked strong {
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.picked span {
  color: var(--muted);
  font-weight: 500;
}

.services {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Exact song page = arrow, search page = magnifier. */
.service {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-height: 52px;
  padding: 0 18px;
  border-radius: 18px;
  background: var(--surface-2);
  color: var(--fg);
  font-weight: 700;
  text-decoration: none;
  transition: background-color 0.2s, transform 0.25s var(--spring);
}

.service:active {
  transform: scale(0.98);
}

@media (hover: hover) {
  .service:hover {
    background: var(--line);
  }
}

.service svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: var(--muted);
}

@media (min-width: 640px) {
  .search {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .go {
    width: auto;
    height: auto;
    padding: 0 24px;
  }
}
</style>
