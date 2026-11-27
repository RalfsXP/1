<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Bars3Icon, PlusIcon } from '@heroicons/vue/24/outline'
import AppLogo from '@/components/AppLogo.vue'
import AppVersion from '@/components/AppVersion.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import MobileNav from '@/components/MobileNav.vue'
import PillNav from '@/components/PillNav.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { addAction } from '@/lib/addAction'
import { user } from '@/lib/auth'
import { NAV_FOOT, NAV_MAIN, isActive } from '@/lib/nav'

/**
 * Signed-in layout (v1.7).
 * Desktop: a header row — logo left, the pill nav centred, theme + account right.
 * Phones: a fixed opaque top bar (logo + theme) and a floating dock at the bottom: the pill nav
 * (+ Menu, which opens every page as a sheet) and a round + for the page's add action.
 * Only <main> scrolls; the page itself stays fixed.
 */
const route = useRoute()
const menuOpen = ref(false)
watch(() => route.fullPath, () => (menuOpen.value = false))

// The whiteboard editor uses the whole screen (its own back button and bottom toolbars).
const showDock = computed(() => route.name !== 'whiteboard')
const initial = computed(() => (user.value?.name?.trim()?.[0] ?? '·').toUpperCase())
const menuActive = computed(() => NAV_FOOT.some((l) => isActive(l, route)))

// One key for every goal category: switching tabs keeps the Goals page (it swaps its list itself).
const viewKey = computed(() => (route.name === 'goals' ? 'goals' : route.path))

// A new page starts at the top (<main> is one scroller shared by every page).
const main = ref(null)
const resetScroll = () => main.value && (main.value.scrollTop = 0)
</script>

<template>
  <div class="shell">
    <header class="topbar">
      <div class="left">
        <RouterLink :to="{ name: 'home' }" class="brand" :aria-label="$t('nav.home')">
          <AppLogo />
        </RouterLink>
        <AppVersion inline class="version" />
      </div>

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

    <main ref="main" class="main" :class="{ 'with-dock': showDock }">
      <RouterView v-slot="{ Component }">
        <Transition name="view" mode="out-in" @after-leave="resetScroll">
          <component :is="Component" :key="viewKey" />
        </Transition>
      </RouterView>
    </main>

    <template v-if="showDock">
      <!-- Phones: content dissolves into the ground before it reaches the dock. -->
      <div class="bottom-fade" aria-hidden="true" />
      <div class="dock-wrap">
        <PillNav :items="NAV_MAIN" variant="dock">
          <button
            type="button"
            class="menu-btn"
            :class="{ on: menuActive || menuOpen }"
            :aria-label="$t('nav.menu')"
            :aria-expanded="menuOpen"
            @click="menuOpen = true"
          >
            <Bars3Icon aria-hidden="true" />
          </button>
        </PillNav>
        <Transition name="add-pop">
          <button v-if="addAction" type="button" class="dock-add" :aria-label="$t(addAction.label)" @click="addAction.run()">
            <PlusIcon aria-hidden="true" />
          </button>
        </Transition>
      </div>
    </template>
    <MobileNav :open="menuOpen" @close="menuOpen = false" />
  </div>
</template>

<style scoped>
.shell {
  --bar-h: calc(max(8px, var(--top-inset)) + 60px);
  /* Gap under the dock: the home indicator's safe area in the Home Screen app, a little air in a tab. */
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

/* Room so the last rows can scroll clear of the dock. */
.main.with-dock {
  padding-bottom: calc(var(--dock-gap) + 96px);
}

.main > :deep(.page) {
  width: 100%;
  margin-inline: auto;
}

/* Placed from the top like every full-screen piece (see --app-h): bottom anchors drift in iOS web apps. */
.bottom-fade {
  position: fixed;
  top: calc(var(--app-h) - var(--dock-gap) - 104px);
  right: 0;
  left: 0;
  z-index: 30;
  height: calc(var(--dock-gap) + 104px);
  background: linear-gradient(transparent, var(--bg) 62%);
  pointer-events: none;
}

.dock-wrap {
  position: fixed;
  top: calc(var(--app-h) - var(--dock-gap) - 62px);
  left: 50%;
  z-index: 40;
  display: flex;
  align-items: center;
  gap: 10px;
  transform: translateX(-50%);
  animation: dock-in 0.6s var(--spring) backwards 0.1s;
}

@keyframes dock-in {
  from {
    transform: translate(-50%, 40px);
    opacity: 0;
  }
}

.menu-btn {
  display: grid;
  place-items: center;
  transition: color 0.3s var(--ease), transform 0.25s var(--spring);
}

.menu-btn:active {
  transform: scale(0.9);
}

.menu-btn.on {
  color: #ffffff !important;
}

.dock-add {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 62px;
  height: 62px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--lav);
  box-shadow: var(--float-shadow);
  color: var(--ink);
  cursor: pointer;
  transition: transform 0.3s var(--spring);
}

.dock-add:active {
  transform: scale(0.88) rotate(90deg);
}

.dock-add svg {
  width: 28px;
  height: 28px;
  stroke-width: 2.2;
}

.add-pop-enter-active {
  transition: transform 0.45s var(--spring), opacity 0.2s ease, width 0.35s var(--ease), margin 0.35s var(--ease);
}

.add-pop-leave-active {
  transition: transform 0.25s ease, opacity 0.2s ease, width 0.3s var(--ease), margin 0.3s var(--ease);
}

.add-pop-enter-from,
.add-pop-leave-to {
  width: 0;
  margin-left: -10px;
  transform: scale(0.3) rotate(-90deg);
  opacity: 0;
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

  .main.with-dock {
    padding-bottom: 0;
  }

  .bottom-fade,
  .dock-wrap {
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
