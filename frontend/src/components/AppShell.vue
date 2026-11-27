<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import AppVersion from '@/components/AppVersion.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import PhoneMenu from '@/components/PhoneMenu.vue'
import PillNav from '@/components/PillNav.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { user } from '@/lib/auth'
import { NAV_FOOT, NAV_MAIN } from '@/lib/nav'

/**
 * Signed-in layout (v1.8).
 * Desktop: a header row — logo left, the pill nav centred, theme + account right; pages rise in.
 * Phones: a fixed opaque top bar (logo + theme; once you scroll, the page name slides into it like an
 * iOS large title) and one round menu button bottom-right that morphs into the menu (PhoneMenu).
 * Pages slide sideways in menu order. Only <main> scrolls; the page itself stays fixed.
 */
const route = useRoute()
const router = useRouter()

// Signed out underneath us (expired session, lib/api.js): back to the login page. A normal Log out goes to
// the landing page on its own (lib/nav.js signOut) and never fires this.
const toLogin = () => router.replace({ name: 'login' })
addEventListener('merkis:signed-out', toLogin)
onBeforeUnmount(() => removeEventListener('merkis:signed-out', toLogin))

// The whiteboard editor uses the whole screen (its own back button and bottom toolbars).
const showMenu = computed(() => route.name !== 'whiteboard')
const initial = computed(() => (user.value?.name?.trim()?.[0] ?? '·').toUpperCase())

/* Phones: pages slide in from the side of where they sit in the menu (Overview → Calendar = from the right). */
const ORDER = ['home', 'calendar', 'goals', 'events', 'guide', 'settings', 'health', 'whiteboards', 'whiteboard']
const phone = matchMedia('(max-width: 859px)')
const isPhone = ref(phone.matches)
const onMedia = (e) => (isPhone.value = e.matches)
phone.addEventListener('change', onMedia)
const slide = ref('next')
watch(
  () => route.name,
  (to, from) => (slide.value = ORDER.indexOf(to) >= ORDER.indexOf(from) ? 'next' : 'prev'),
)
const viewTransition = computed(() => (isPhone.value ? `view-${slide.value}` : 'view'))

/* Phones: the page's big title scrolls away under the bar and its name fades into the bar. */
const TITLES = { home: 'nav.home', calendar: 'nav.calendar', goals: 'nav.goals', events: 'nav.events', guide: 'nav.guide', settings: 'nav.settings', health: 'nav.health', whiteboards: 'nav.whiteboards' }
const barTitle = computed(() => TITLES[route.name])
const scrolled = ref(false)
let ticking = false
function onScroll(e) {
  if (ticking) return
  ticking = true
  requestAnimationFrame(() => {
    scrolled.value = e.target.scrollTop > 64
    ticking = false
  })
}
onBeforeUnmount(() => phone.removeEventListener('change', onMedia))

// Once the app is idle, load the other pages' code in the background, so the first visit to each slides
// in as smoothly as later ones (no download/parse pause mid-transition). The whiteboard editor (Fabric) is skipped.
onMounted(() => {
  const idle = window.requestIdleCallback ?? ((fn) => setTimeout(fn, 1200))
  idle(() =>
    router
      .getRoutes()
      .filter((r) => r.name !== 'whiteboard' && typeof r.components?.default === 'function')
      .forEach((r) => r.components.default().catch(() => {})),
  )
})

// One key for every goal category: switching tabs keeps the Goals page (it swaps its list itself).
const viewKey = computed(() => (route.name === 'goals' ? 'goals' : route.path))

// A new page starts at the top (<main> is one scroller shared by every page).
const main = ref(null)
const resetScroll = () => {
  if (main.value) main.value.scrollTop = 0
  scrolled.value = false
}
</script>

<template>
  <div class="shell">
    <header class="topbar" :class="{ scrolled }">
      <div class="left">
        <RouterLink :to="{ name: 'home' }" class="brand" :aria-label="$t('nav.home')">
          <AppLogo />
        </RouterLink>
        <AppVersion inline class="version" />
      </div>

      <Transition name="bar-title">
        <span v-if="scrolled && barTitle" :key="barTitle" class="bar-title" aria-hidden="true">{{ $t(barTitle) }}</span>
      </Transition>

      <PillNav class="topnav" :items="[...NAV_MAIN, NAV_FOOT[0]]" variant="top" />

      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
        <RouterLink
          :to="{ name: 'settings' }"
          class="avatar"
          :class="{ active: route.name === 'settings' }"
          :aria-label="$t('nav.settings')"
          :title="$t('nav.settings')"
        >
          {{ initial }}
        </RouterLink>
      </div>
    </header>

    <main ref="main" class="main" :class="{ 'with-menu': showMenu }" @scroll.passive="onScroll">
      <RouterView v-slot="{ Component }">
        <Transition :name="viewTransition" mode="out-in" @after-leave="resetScroll">
          <component :is="Component" :key="viewKey" />
        </Transition>
      </RouterView>
    </main>

    <PhoneMenu v-if="showMenu && isPhone" />
  </div>
</template>

<style scoped>
.shell {
  --bar-h: calc(max(8px, var(--top-inset)) + 60px);
  /* Gap under the menu button: the home indicator's safe area in the Home Screen app, a little air in a tab. */
  --dock-gap: max(14px, env(safe-area-inset-bottom));
  display: grid;
  grid-template-rows: 1fr;
  height: 100vh;
  height: var(--app-h);
  overflow: hidden;
}

/* Phones: an opaque bar in the ground colour (no tint, no backdrop-filter — that also keeps iOS 26 from
   adding its own blur over the Home Screen app). The ::after continues it a little lower and fades out,
   so scrolled content slips under a soft edge. */
.topbar {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 10px;
  height: var(--bar-h);
  padding: max(8px, var(--top-inset)) 18px 4px max(18px, env(safe-area-inset-left));
  background: var(--bg);
}

.topbar::after {
  content: '';
  position: absolute;
  top: 100%;
  right: 0;
  left: 0;
  height: 22px;
  background: linear-gradient(var(--bg), transparent);
  pointer-events: none;
}

.brand {
  display: block;
  border-radius: 12px;
  color: var(--fg);
  font-size: 1.3rem;
  text-decoration: none;
  transition: transform 0.25s var(--spring);
}

.brand:active {
  transform: scale(0.95) rotate(-2deg);
}

.version,
.topnav,
.avatar {
  display: none;
}

.left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.controls {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}

.avatar {
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--peach);
  box-shadow: 0 0 0 3px var(--surface);
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 800;
  text-decoration: none;
  transition: transform 0.25s var(--spring), box-shadow 0.2s;
}

@media (hover: hover) {
  .avatar:hover {
    transform: rotate(-6deg) scale(1.05);
  }
}

.avatar.active {
  box-shadow: 0 0 0 3px var(--fg);
}

.main {
  min-height: 0;
  padding-top: var(--bar-h);
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

/* Room so the last rows can scroll clear of the menu button. */
.main.with-menu {
  padding-bottom: calc(var(--dock-gap) + 84px);
}

.main > :deep(.page) {
  width: 100%;
  margin-inline: auto;
}

/* The page name slides up into the bar once the big title has scrolled away (phones). */
.bar-title {
  position: absolute;
  left: 50%;
  bottom: 21px;
  max-width: 50vw;
  overflow: hidden;
  font-family: var(--font-display);
  font-size: 1.08rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  text-overflow: ellipsis;
  white-space: nowrap;
  transform: translateX(-50%);
  pointer-events: none;
}

.bar-title-enter-active,
.bar-title-leave-active {
  transition: opacity 0.25s ease, transform 0.35s var(--ease);
}

.bar-title-enter-from {
  opacity: 0;
  transform: translate(-50%, 10px);
}

.bar-title-leave-to {
  opacity: 0;
  transform: translate(-50%, -6px);
}

/* The wordmark gives way to the page name; the logo sticker stays. */
.brand :deep(.word) {
  transition: opacity 0.25s ease, transform 0.35s var(--ease);
}

.topbar.scrolled .brand :deep(.word) {
  opacity: 0;
  transform: translateX(-6px);
}

/* A soft line under the bar once content runs beneath it. */
.topbar::before {
  content: '';
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 1px;
  background: var(--line);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.topbar.scrolled::before {
  opacity: 1;
}

/* Page changes: the old view fades, the new one rises in. */
.view-enter-active {
  transition: opacity 0.3s var(--ease), transform 0.4s var(--ease);
}

.view-leave-active {
  transition: opacity 0.14s ease;
}

.view-enter-from {
  opacity: 0;
  transform: translateY(12px);
}

.view-leave-to {
  opacity: 0;
}

/* Phones: pages slide sideways in menu order (the old one drifts off, the new one glides in). */
.view-next-enter-active,
.view-prev-enter-active {
  transition: opacity 0.3s ease, transform 0.45s var(--sheet-ease);
}

.view-next-leave-active,
.view-prev-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease-in;
}

.view-next-enter-from {
  opacity: 0;
  transform: translateX(44px);
}

.view-prev-enter-from {
  opacity: 0;
  transform: translateX(-44px);
}

.view-next-leave-to {
  opacity: 0;
  transform: translateX(-24px);
}

.view-prev-leave-to {
  opacity: 0;
  transform: translateX(24px);
}

@media (min-width: 860px) {
  .shell {
    grid-template-rows: auto minmax(0, 1fr);
  }

  /* Desktop: a header row over the content — logo left, nav centred, controls right. */
  .topbar {
    position: static;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    height: auto;
    padding: 20px 32px 16px;
    background: none;
  }

  .topbar::after {
    display: none;
  }

  .brand {
    font-size: 1.45rem;
  }

  .version {
    display: block;
    padding: 5px 9px;
    border-radius: 999px;
    background: var(--surface-2);
  }

  .topnav {
    display: flex;
  }

  .controls {
    justify-self: end;
  }

  .avatar {
    display: grid;
  }

  .main {
    padding-top: 0;
  }

  .main.with-menu {
    padding-bottom: 0;
  }

  .bar-title,
  .topbar::before {
    display: none;
  }
}

/* Mid-size windows: the nav keeps its icons and drops the labels before it would collide. */
@media (min-width: 860px) and (max-width: 1099px) {
  .topnav :deep(.label) {
    display: none;
  }

  .topnav :deep(.item) {
    width: 48px;
    padding: 0;
  }

  .version {
    display: none;
  }
}
</style>
