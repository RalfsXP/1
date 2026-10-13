<script setup>
import { useRouter } from 'vue-router'
import { ArrowRightStartOnRectangleIcon } from '@heroicons/vue/24/outline'
import AppLogo from '@/components/AppLogo.vue'
import AppVersion from '@/components/AppVersion.vue'
import { NAV_FOOT, NAV_LINKS as links, signOut as doSignOut } from '@/lib/nav'

// Desktop navigation column (phones use MobileNav).
const emit = defineEmits(['navigate'])
const router = useRouter()
const signOut = () => doSignOut(router)
</script>

<template>
  <nav class="nav" :aria-label="$t('nav.menu')">
    <div class="brand">
      <RouterLink :to="{ name: 'home' }" class="logo" :aria-label="$t('nav.home')" @click="emit('navigate')">
        <AppLogo />
      </RouterLink>
    </div>

    <ul class="links">
      <li v-for="link in links" :key="link.label">
        <RouterLink :to="link.to" class="link" exact-active-class="active" @click="emit('navigate')">
          <component :is="link.icon" aria-hidden="true" />
          <span>{{ $t(link.label) }}</span>
        </RouterLink>
      </li>
    </ul>

    <div class="foot">
      <RouterLink v-for="link in NAV_FOOT" :key="link.label" :to="link.to" class="link" exact-active-class="active" @click="emit('navigate')">
        <component :is="link.icon" aria-hidden="true" />
        <span>{{ $t(link.label) }}</span>
      </RouterLink>
      <button type="button" class="link logout" @click="signOut">
        <ArrowRightStartOnRectangleIcon aria-hidden="true" />
        <span>{{ $t('nav.logout') }}</span>
      </button>
      <AppVersion inline />
    </div>
  </nav>
</template>

<style scoped>
/* A floating glass column. */
.nav {
  display: flex;
  flex-direction: column;
  gap: 24px;
  height: 100%;
  padding: 26px 14px 16px;
  overflow-y: auto;
  border: 1px solid var(--glass-edge);
  border-radius: 28px;
  background: var(--surface);
  box-shadow: var(--glass-shadow);
}

.brand {
  padding: 0 12px;
}

.logo {
  display: block;
  width: 128px;
  height: 38px;
}

.links {
  display: grid;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.link {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 48px;
  padding: 0 14px;
  border: 0;
  border-radius: 16px;
  background: transparent;
  color: var(--fg);
  font-size: 0.95rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.link svg {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  color: var(--link);
}

.link:hover {
  background: var(--pill);
}

.link.active {
  background: var(--accent);
  box-shadow: 0 8px 20px color-mix(in srgb, var(--accent) 30%, transparent);
  color: var(--on-accent);
  font-weight: 700;
}

.link.active svg {
  color: currentColor;
}

.link:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.foot {
  display: grid;
  gap: 2px;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid var(--line);
}

.foot .link:not(.active) svg,
.logout {
  color: var(--muted);
}

.foot :deep(.version) {
  padding: 6px 14px 0;
}
</style>
