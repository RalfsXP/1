<script setup>
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import GlassScene from '@/components/GlassScene.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { withLoader } from '@/lib/loader'

const router = useRouter()

// Short loading screen (logo) before showing the login page.
const openLogin = () => withLoader(() => router.push({ name: 'login' }))
</script>

<template>
  <div class="page">
    <header class="top">
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <main class="hero">
      <h1 class="brand"><AppLogo /></h1>
      <GlassScene class="scene" />
      <button type="button" class="btn btn-primary btn-lg login" @click="openLogin">{{ $t('landing.login') }}</button>
    </main>
  </div>
</template>

<style scoped>
/* Exactly one screen tall: the landing page never scrolls. Controls top right; the wordmark,
   the floating glass scene and the login button form one block, well above the bottom edge
   so the button sits in easy thumb reach without hugging the edge. */
.page {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 16px;
  height: 100vh;
  height: var(--app-h);
  padding: max(16px, var(--top-inset)) 20px max(24px, env(safe-area-inset-bottom));
  overflow: hidden;
}

.top {
  display: flex;
  justify-content: flex-end;
}

.controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hero {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  justify-items: center;
  gap: clamp(16px, 4dvh, 36px);
  min-height: 0;
  padding: clamp(8px, 3dvh, 32px) 0 clamp(40px, 14dvh, 140px);
}

.brand {
  width: min(250px, 64vw);
  height: auto;
  aspect-ratio: 343.5 / 99.4;
  margin: 0;
}

/* The scene keeps its proportions and shrinks to whatever height is left. */
.scene {
  max-width: 360px;
  max-height: 382px;
}

.login {
  width: min(420px, 100%);
}

@media (min-width: 640px) {
  .page {
    padding: 24px 32px 40px;
  }

  .scene {
    max-width: 460px;
    max-height: 488px;
  }

  .login {
    width: 280px;
  }
}
</style>
