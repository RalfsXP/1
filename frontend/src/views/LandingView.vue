<script setup>
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import PaperScene from '@/components/PaperScene.vue'
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
      <h1 class="brand rise"><AppLogo /><span class="visually-hidden">Merkis</span></h1>
      <PaperScene class="scene" />
      <div class="actions rise">
        <button type="button" class="btn btn-primary btn-lg" @click="open('register')">{{ $t('landing.signup') }}</button>
        <button type="button" class="btn btn-ghost btn-lg" @click="open('login')">{{ $t('landing.login') }}</button>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Exactly one screen tall: the landing page never scrolls. Controls top right; the logo, the floating
   paper scene and the sign-up / login buttons form one block, well above the bottom edge so the buttons
   sit in easy thumb reach without hugging it. */
.page {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 8px;
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
  gap: clamp(16px, 3.5dvh, 36px);
  min-height: 0;
  padding: clamp(4px, 2dvh, 24px) 0 clamp(36px, 12dvh, 120px);
}

.brand {
  margin: 0;
  font-size: clamp(2rem, 9vw, 2.6rem);
}

/* The scene keeps its proportions and shrinks to whatever height is left. */
.scene {
  max-width: 380px;
  max-height: 402px;
}

.actions {
  display: grid;
  gap: 10px;
  width: min(420px, 100%);
  animation-delay: 0.5s;
}

@media (min-width: 640px) {
  .page {
    padding: 24px 32px 40px;
  }

  .brand {
    font-size: 3rem;
  }

  .scene {
    max-width: 500px;
    max-height: 530px;
  }

  .actions {
    grid-template-columns: 1fr 1fr;
    width: 440px;
  }
}
</style>
