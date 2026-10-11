<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Bars3Icon } from '@heroicons/vue/24/outline'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import LiveClock from '@/components/LiveClock.vue'
import LogoMark from '@/components/LogoMark.vue'
import SideNav from '@/components/SideNav.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

/**
 * Signed-in layout: side nav on the left (a slide-in drawer on phones) and a
 * main area that scrolls on its own while the page itself stays fixed.
 */
const route = useRoute()
const drawerOpen = ref(false)

// Below 860px the nav is an off-screen drawer: while closed it must not be tabbable or read out.
const wide = matchMedia('(min-width: 860px)')
const isWide = ref(wide.matches)
const onWide = (e) => (isWide.value = e.matches)
wide.addEventListener('change', onWide)
onBeforeUnmount(() => wide.removeEventListener('change', onWide))
const navHidden = computed(() => !isWide.value && !drawerOpen.value)

watch(() => route.fullPath, () => (drawerOpen.value = false))

const onKey = (e) => e.key === 'Escape' && (drawerOpen.value = false)
addEventListener('keydown', onKey)
onBeforeUnmount(() => removeEventListener('keydown', onKey))

/* Swipe the open drawer to the left to close it. */
let startX = null
const onTouchStart = (e) => (startX = e.touches[0].clientX)
function onTouchEnd(e) {
  if (startX !== null && startX - e.changedTouches[0].clientX > 60) drawerOpen.value = false
  startX = null
}
</script>

<template>
  <div class="shell">
    <!-- Same corner as on the login page: language + theme top right. -->
    <header class="topbar">
      <button type="button" class="icon-btn bare menu" :aria-label="$t('nav.menu')" :aria-expanded="drawerOpen" @click="drawerOpen = true">
        <Bars3Icon aria-hidden="true" />
      </button>
      <RouterLink :to="{ name: 'home' }" class="mark" :aria-label="$t('nav.home')"><LogoMark /></RouterLink>
      <LiveClock class="clock" />
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <Transition name="fade">
      <div v-if="drawerOpen" class="backdrop" @click="drawerOpen = false" />
    </Transition>

    <aside class="side" :class="{ open: drawerOpen }" :inert="navHidden" @touchstart.passive="onTouchStart" @touchend="onTouchEnd">
      <SideNav @navigate="drawerOpen = false" />
    </aside>

    <main class="main">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="route.path" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-rows: auto 1fr;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

.topbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: max(8px, env(safe-area-inset-top)) 16px 8px max(8px, env(safe-area-inset-left));
  border-bottom: 1px solid var(--line);
}

/* Phones: the clock lives in the drawer-free space only on wider screens. */
.topbar .clock {
  display: none;
}

.controls {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}

.mark {
  display: block;
  width: 40px;
  height: 22px;
}

.main {
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

.side {
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 60;
  width: min(300px, 84vw);
  border-right: 1px solid var(--line);
  background: var(--bg);
  transform: translateX(-100%);
  transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.side.open {
  transform: none;
}

.backdrop {
  position: fixed;
  inset: 0;
  z-index: 55;
  background: var(--backdrop);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (min-width: 860px) {
  .shell {
    grid-template-columns: 248px 1fr;
    grid-template-rows: auto 1fr;
  }

  .backdrop,
  .menu,
  .mark {
    display: none;
  }

  /* Desktop: a borderless bar over the content column — clock left, controls right (like the auth header). */
  .topbar {
    grid-column: 2;
    padding: 24px 32px 0;
    border-bottom: 0;
  }

  .topbar .clock {
    display: inline;
  }

  .main {
    grid-column: 2;
  }

  .side {
    grid-row: 1 / span 2;
    grid-column: 1;
    position: static;
    width: auto;
    transform: none;
    transition: none;
  }
}
</style>
