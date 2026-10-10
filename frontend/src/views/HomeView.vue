<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import LiveClock from '@/components/LiveClock.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { logout, user } from '@/lib/auth'
import { withLoader } from '@/lib/loader'

const router = useRouter()

const firstName = computed(() => user.value?.name?.split(' ')[0] ?? '')

function signOut() {
  return withLoader(async () => {
    await logout()
    await router.push({ name: 'landing' })
  })
}
</script>

<template>
  <div class="home">
    <header class="top">
      <LiveClock />
      <div class="top-actions">
        <button type="button" class="btn btn-ghost" @click="signOut">Log out</button>
        <ThemeToggle />
      </div>
    </header>

    <main class="center">
      <div class="logo"><AppLogo /></div>
      <h1>Welcome<template v-if="firstName">, {{ firstName }}</template></h1>
    </main>
  </div>
</template>

<style scoped>
.home {
  display: grid;
  grid-template-rows: auto 1fr;
  min-height: 100vh;
  min-height: 100dvh;
  padding: 24px 32px;
  background:
    radial-gradient(40rem 28rem at 50% 50%, var(--glow), transparent 70%),
    var(--bg);
}

.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.top-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.center {
  display: grid;
  place-items: center;
  align-content: center;
  gap: 28px;
  padding-bottom: 8vh;
}

.logo {
  width: min(300px, 64vw);
  aspect-ratio: 998 / 828;
}

h1 {
  margin: 0;
  font-size: clamp(1.75rem, 5vw, 2.6rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  text-align: center;
}

@media (max-width: 640px) {
  .home {
    padding: 16px;
  }
}
</style>
