<script setup>
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import DeviceScene from '@/components/DeviceScene.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { withLoader } from '@/lib/loader'

const router = useRouter()

// Short loading screen (logo) before showing the auth page.
const open = (name) => withLoader(() => router.push({ name }))
</script>

<template>
  <div class="page">
    <header class="top">
      <h1 class="brand"><AppLogo /></h1>
      <nav class="auth" :aria-label="$t('common.account')">
        <button type="button" class="btn btn-ghost" @click="open('login')">{{ $t('landing.login') }}</button>
        <button type="button" class="btn btn-primary" @click="open('register')">{{ $t('landing.signup') }}</button>
      </nav>
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <main class="stage-wrap">
      <DeviceScene layout="landing" />
    </main>
  </div>
</template>

<style scoped>
.page {
  display: grid;
  grid-template-rows: auto 1fr;
  padding: 24px 32px 0;
  /* Exactly one screen tall: the landing page never scrolls. */
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  background:
    radial-gradient(56rem 34rem at 50% 62%, var(--glow), transparent 70%),
    var(--bg);
  transition: background-color 0.3s ease;
}

/* ---------- Header ---------- */
.top {
  display: grid;
  grid-template-columns: 1fr auto auto;
  grid-template-areas: 'brand auth controls';
  align-items: center;
  gap: 12px;
}

.brand {
  grid-area: brand;
  width: 132px;
  height: 40px;
  margin: 0;
}

.auth {
  grid-area: auth;
  display: flex;
  gap: 8px;
}

.controls {
  grid-area: controls;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stage-wrap {
  display: grid;
  place-items: center;
  min-height: 0;
  padding: 1rem 0 2rem;
}

/* ---------- Phones ---------- */
@media (max-width: 640px) {
  /* Header items join the page grid; scene + buttons form one block a little above centre. */
  .page {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: auto 1fr auto auto 1.6fr;
    grid-template-areas:
      'brand controls'
      '. .'
      'stage stage'
      'auth auth'
      '. .';
    column-gap: 12px;
    padding: max(16px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom));
  }

  .top {
    display: contents;
  }

  .brand {
    width: 112px;
    height: 34px;
  }

  .stage-wrap {
    grid-area: stage;
    min-width: 0;
    padding: 0;
  }

  .auth {
    justify-content: center;
    gap: 12px;
    padding-top: 28px;
  }

  .auth .btn {
    flex: 1 1 0;
    max-width: 180px;
    height: 50px;
    font-size: 0.95rem;
  }
}
</style>
