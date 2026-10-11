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
.nav {
  display: flex;
  flex-direction: column;
  gap: 24px;
  height: 100%;
  padding: max(24px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
  overflow-y: auto;
}

.brand {
  padding: 0 10px;
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
  min-height: 46px;
  padding: 0 12px;
  border: 0;
  border-radius: 14px;
  background: transparent;
  color: var(--fg);
  font-size: 0.95rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.link svg {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
}

.link:hover {
  color: var(--accent);
}

.link.active {
  background: var(--accent);
  color: var(--on-accent);
}

.link:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.foot {
  display: grid;
  gap: 2px;
  margin-top: auto;
}

.logout {
  color: var(--muted);
}

.foot :deep(.version) {
  padding: 0 12px;
}
</style>
