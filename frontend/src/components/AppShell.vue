<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Bars3Icon } from '@heroicons/vue/24/outline'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import LogoMark from '@/components/LogoMark.vue'
import MobileNav from '@/components/MobileNav.vue'
import SideNav from '@/components/SideNav.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

/**
 * Signed-in layout. Desktop: side nav on the left. Phones: a fixed glass top bar
 * (the mist continues under it, content scrolls under it blurred) and a floating
 * Menu button that opens the navigation as a bottom sheet. The main area scrolls
 * on its own while the page itself stays fixed.
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
      class="float-btn glass-float menu-btn"
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
  --bar-h: calc(max(8px, var(--top-inset)) + 56px);
  display: grid;
  grid-template-rows: 1fr;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

/* No visible bar: no tint, so the mist runs on unbroken. Content scrolling under it is blurred
   by a layer whose blur fades out downwards (mask), so the bar never shows an edge. */
.topbar {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 8px;
  height: var(--bar-h);
  padding: max(8px, var(--top-inset)) 20px 6px max(20px, env(safe-area-inset-left));
}

.topbar::before {
  content: '';
  position: absolute;
  inset: 0 0 -28px;
  z-index: -1;
  -webkit-backdrop-filter: blur(18px);
  backdrop-filter: blur(18px);
  -webkit-mask-image: linear-gradient(to bottom, black 55%, transparent);
  mask-image: linear-gradient(to bottom, black 55%, transparent);
  pointer-events: none;
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
  padding-top: var(--bar-h);
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

/* Room so the last rows can scroll clear of the floating buttons. */
.main.with-menu {
  padding-bottom: calc(env(safe-area-inset-bottom) + 84px);
}

.side {
  display: none;
}

.menu-btn {
  left: max(20px, env(safe-area-inset-left));
  color: var(--fg);
}

@media (min-width: 860px) {
  .shell {
    grid-template-columns: 272px 1fr;
    grid-template-rows: auto 1fr;
  }

  .mark,
  .menu-btn {
    display: none;
  }

  /* Desktop: a borderless bar over the content column, controls right (like the auth header). */
  .topbar {
    position: static;
    grid-column: 2;
    height: auto;
    padding: 24px 32px 0;
  }

  .topbar::before {
    display: none;
  }

  .main {
    grid-column: 2;
    padding-top: 0;
  }

  .main.with-menu {
    padding-bottom: 0;
  }

  .side {
    display: block;
    grid-row: 1 / span 2;
    grid-column: 1;
    min-height: 0;
    padding: 16px 0 16px 16px;
  }
}
</style>
