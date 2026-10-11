<script setup>
import { useRouter } from 'vue-router'
import {
  ArrowRightStartOnRectangleIcon,
  CalendarDateRangeIcon,
  CalendarDaysIcon,
  CalendarIcon,
  Cog6ToothIcon,
  SignalIcon,
  Squares2X2Icon,
  StarIcon,
  SunIcon,
  ViewColumnsIcon,
} from '@heroicons/vue/24/outline'
import AppLogo from '@/components/AppLogo.vue'
import AppVersion from '@/components/AppVersion.vue'
import { logout, user } from '@/lib/auth'
import { withLoader } from '@/lib/loader'

const emit = defineEmits(['navigate'])
const router = useRouter()

const links = [
  { to: { name: 'home' }, label: 'nav.home', icon: Squares2X2Icon },
  { to: { name: 'calendar' }, label: 'nav.calendar', icon: CalendarDaysIcon },
  { to: { name: 'goals', params: { period: 'daily' } }, label: 'periods.day', icon: SunIcon },
  { to: { name: 'goals', params: { period: 'weekly' } }, label: 'periods.week', icon: ViewColumnsIcon },
  { to: { name: 'goals', params: { period: 'monthly' } }, label: 'periods.month', icon: CalendarIcon },
  { to: { name: 'goals', params: { period: 'yearly' } }, label: 'periods.year', icon: StarIcon },
  { to: { name: 'goals', params: { period: 'custom' } }, label: 'periods.custom', icon: CalendarDateRangeIcon },
]

function signOut() {
  return withLoader(async () => {
    await logout()
    await router.push({ name: 'landing' })
  })
}
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
      <RouterLink :to="{ name: 'settings' }" class="link" exact-active-class="active" @click="emit('navigate')">
        <Cog6ToothIcon aria-hidden="true" />
        <span>{{ $t('nav.settings') }}</span>
      </RouterLink>
      <RouterLink v-if="user?.is_admin" :to="{ name: 'health' }" class="link" exact-active-class="active" @click="emit('navigate')">
        <SignalIcon aria-hidden="true" />
        <span>{{ $t('nav.health') }}</span>
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
  color: var(--seagrass);
}

.link.active {
  background: var(--seagrass);
  color: var(--black);
}

.link:focus-visible {
  outline: 2px solid var(--seagrass);
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
