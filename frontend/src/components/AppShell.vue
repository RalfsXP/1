<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Bars3Icon } from '@heroicons/vue/24/outline'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import LiveClock from '@/components/LiveClock.vue'
import LogoMark from '@/components/LogoMark.vue'
import MobileNav from '@/components/MobileNav.vue'
import SideNav from '@/components/SideNav.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

/**
 * Signed-in layout. Desktop: side nav on the left. Phones: a fixed top bar
 * (solid, so iOS Safari tints the status bar instead of blurring the page under
 * it) and a floating Menu button that opens the navigation as a bottom sheet.
 * The main area scrolls on its own while the page itself stays fixed.
 */
const route = useRoute()
const menuOpen = ref(false)

watch(() => route.fullPath, () => (menuOpen.value = false))

// The whiteboard editor uses the whole screen (its own back button and bottom toolbars).
const showMenuButton = computed(() => route.name !== 'whiteboard')
</script>

<template>
  <div class="shell">
    <!-- Same corner as on the login page: language + theme top right. -->
    <header class="topbar">
      <RouterLink :to="{ name: 'home' }" class="mark" :aria-label="$t('nav.home')"><LogoMark /></RouterLink>
      <LiveClock class="clock" />
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <aside class="side">
      <SideNav />
    </aside>

    <main class="main" :class="{ 'with-menu': showMenuButton }">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="route.path" />
        </Transition>
      </RouterView>
    </main>

    <button
      v-if="showMenuButton"
      type="button"
      class="menu-btn"
      :aria-label="$t('nav.menu')"
      :aria-expanded="menuOpen"
      @click="menuOpen = true"
    >
      <Bars3Icon aria-hidden="true" />
    </button>
    <MobileNav :open="menuOpen" @close="menuOpen = false" />
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-rows: 1fr;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  /* = phone top bar height: top padding + 40 px row + 8 px bottom padding + 1 px border */
  padding-top: calc(max(8px, var(--top-inset)) + 49px);
}

.topbar {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 8px;
  height: calc(max(8px, var(--top-inset)) + 49px);
  padding: max(8px, var(--top-inset)) 16px 8px max(16px, env(safe-area-inset-left));
  border-bottom: 1px solid var(--line);
  background: var(--bg);
}

/* Phones: no room for the clock next to the logo and controls. */
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

/* Room so the last rows can scroll clear of the floating Menu button. */
.main.with-menu {
  padding-bottom: calc(env(safe-area-inset-bottom) + 72px);
}

.side {
  display: none;
}

/* Floating, not a full-width bar: Safari would paint a full-width bottom bar into its toolbar area. */
.menu-btn {
  position: fixed;
  bottom: max(16px, calc(env(safe-area-inset-bottom) + 8px));
  left: max(16px, env(safe-area-inset-left));
  z-index: 40;
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--bg);
  color: var(--fg);
  box-shadow: 0 8px 24px color-mix(in srgb, var(--black) 30%, transparent);
  cursor: pointer;
  transition: transform 0.2s;
}

.menu-btn:active {
  transform: scale(0.94);
}

.menu-btn svg {
  width: 26px;
  height: 26px;
}

@media (min-width: 860px) {
  .shell {
    grid-template-columns: 248px 1fr;
    grid-template-rows: auto 1fr;
    padding-top: 0;
  }

  .mark,
  .menu-btn {
    display: none;
  }

  /* Desktop: a borderless bar over the content column — clock left, controls right (like the auth header). */
  .topbar {
    position: static;
    grid-column: 2;
    height: auto;
    padding: 24px 32px 0;
    border-bottom: 0;
    background: transparent;
  }

  .topbar .clock {
    display: inline;
  }

  .main {
    grid-column: 2;
  }

  .main.with-menu {
    padding-bottom: 0;
  }

  .side {
    display: block;
    grid-row: 1 / span 2;
    grid-column: 1;
  }
}
</style>
