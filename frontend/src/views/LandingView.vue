<script setup>
import { useRouter } from 'vue-router'
import LogoMark from '@/components/LogoMark.vue'
import GlassScene from '@/components/GlassScene.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { withLoader } from '@/lib/loader'

const router = useRouter()

// Short loading screen (logo) before showing the login / sign-up page.
const open = (name) => withLoader(() => router.push({ name }))
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
      <h1 class="brand"><LogoMark /><span class="visually-hidden">Merkis</span></h1>
      <GlassScene class="scene" />
      <div class="actions">
        <button type="button" class="btn btn-primary btn-lg" @click="open('register')">{{ $t('landing.signup') }}</button>
        <button type="button" class="btn btn-ghost btn-lg" @click="open('login')">{{ $t('landing.login') }}</button>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Exactly one screen tall: the landing page never scrolls. Controls top right; the m. mark,
   the floating glass scene and the sign-up / login buttons form one block, well above the bottom edge
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
  width: min(132px, 34vw);
  height: auto;
  aspect-ratio: 113.5 / 55.1;
  margin: 0;
}

/* The scene keeps its proportions and shrinks to whatever height is left. */
.scene {
  max-width: 360px;
  max-height: 382px;
}

.actions {
  display: grid;
  gap: 10px;
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

  .actions {
    grid-template-columns: 1fr 1fr;
    width: 420px;
  }
}
</style>
