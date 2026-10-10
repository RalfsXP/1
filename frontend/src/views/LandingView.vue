<script setup>
import { useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import LiveClock from '@/components/LiveClock.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { withLoader } from '@/lib/loader'

const router = useRouter()

// Fake loading screen (spinning logo) before showing the auth page.
const open = (name) => withLoader(() => router.push({ name }))
</script>

<template>
  <div class="page">
    <LiveClock class="clock" />

    <nav class="auth" aria-label="Konts">
      <button type="button" class="btn btn-ghost" @click="open('login')">Log in</button>
      <button type="button" class="btn btn-primary" @click="open('register')">Sign up</button>
    </nav>

    <ThemeToggle class="theme-toggle" />

    <main class="stage-wrap">
      <h1 class="visually-hidden">Merkis.app</h1>

      <div class="stage">
        <div class="laptop" aria-hidden="true">
          <div class="laptop-lid">
            <span class="laptop-cam" />
            <div class="screen">
              <div class="screen-logo laptop-logo"><AppLogo /></div>
            </div>
          </div>
          <div class="laptop-base"><span class="laptop-notch" /></div>
        </div>

        <div class="phone" aria-hidden="true">
          <div class="screen phone-screen">
            <span class="phone-island" />
            <div class="screen-logo phone-logo"><AppLogo /></div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.page {
  display: grid;
  grid-template-columns: 1fr auto auto;
  grid-template-rows: auto 1fr;
  grid-template-areas:
    'clock auth toggle'
    'stage stage stage';
  align-items: center;
  column-gap: 12px;
  padding: 24px 32px 0;
  min-height: 100vh;
  min-height: 100dvh;
  overflow: hidden;
  background:
    radial-gradient(50rem 32rem at 50% 58%, var(--glow), transparent 70%),
    var(--bg);
  transition: background-color 0.3s ease;
}

/* ---------- Corners ---------- */
.clock {
  grid-area: clock;
}

.auth {
  grid-area: auth;
  display: flex;
  gap: 8px;
}

.theme-toggle {
  grid-area: toggle;
}

/* ---------- Devices ---------- */
.stage-wrap {
  grid-area: stage;
  align-self: stretch;
  display: grid;
  place-items: center;
  padding: 2rem 0 4rem;
}

/* All device proportions are in cqw so the scene scales as one unit. */
.stage {
  container-type: inline-size;
  position: relative;
  /* The scene is ~0.6× as tall as it is wide; cap it by viewport height too
     so short, wide windows don't clip the devices. */
  width: min(980px, 100%, max(240px, calc((100dvh - 210px) * 1.5)));
  padding-bottom: 4cqw;
}

.laptop {
  width: 84cqw;
  margin-left: 8cqw;
}

.laptop-lid {
  position: relative;
  aspect-ratio: 16 / 10;
  padding: 1.9cqw 1.9cqw 2.6cqw;
  border-radius: 2.6cqw 2.6cqw 0.8cqw 0.8cqw;
  background: var(--frame);
  box-shadow: inset 0 0 0 0.15cqw var(--frame-edge);
  transition: background-color 0.3s ease;
}

.laptop-cam {
  position: absolute;
  top: 0.75cqw;
  left: 50%;
  width: 0.55cqw;
  height: 0.55cqw;
  border-radius: 50%;
  background: var(--frame-edge);
  transform: translateX(-50%);
}

.screen {
  position: relative;
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 1cqw;
  background:
    radial-gradient(70% 60% at 50% 50%, var(--glow), transparent 75%),
    var(--screen);
  transition: background-color 0.3s ease;
}

.screen-logo {
  width: 46%;
  height: 64%;
}

.laptop-base {
  position: relative;
  width: 100cqw;
  height: 2.2cqw;
  margin-left: -8cqw;
  border-radius: 0.3cqw 0.3cqw 2.2cqw 2.2cqw / 0.3cqw 0.3cqw 1.6cqw 1.6cqw;
  background: var(--frame);
  box-shadow: inset 0 0.15cqw 0 var(--frame-edge);
  transition: background-color 0.3s ease;
}

.laptop-notch {
  position: absolute;
  top: 0;
  left: 50%;
  width: 14cqw;
  height: 0.9cqw;
  border-radius: 0 0 1cqw 1cqw;
  background: var(--frame-edge);
  transform: translateX(-50%);
}

.phone {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 22cqw;
  aspect-ratio: 9 / 19.5;
  padding: 1.1cqw;
  border-radius: 3.6cqw;
  background: var(--frame);
  box-shadow:
    inset 0 0 0 0.15cqw var(--frame-edge),
    0 2cqw 5cqw -1cqw color-mix(in srgb, var(--black) 45%, transparent);
  transition: background-color 0.3s ease;
}

.phone-screen {
  border-radius: 2.6cqw;
}

.phone-island {
  position: absolute;
  top: 1.4cqw;
  left: 50%;
  width: 6cqw;
  height: 1.7cqw;
  border-radius: 999px;
  background: var(--frame);
  transform: translateX(-50%);
}

.phone-logo {
  width: 76%;
  height: 40%;
}

/* ---------- Mobile ---------- */
@media (max-width: 640px) {
  .page {
    grid-template-columns: 1fr auto;
    grid-template-rows: auto 1fr auto;
    grid-template-areas:
      'clock toggle'
      'stage stage'
      'auth auth';
    padding: 16px 16px 0;
  }

  .auth {
    justify-content: center;
    padding-bottom: calc(20px + env(safe-area-inset-bottom));
  }


  .stage-wrap {
    padding: 1rem 0 2rem;
  }

  /* Give the phone more room on small screens so its logo stays readable. */
  .laptop {
    width: 80cqw;
    margin-left: 6cqw;
  }

  .laptop-base {
    width: 92cqw;
    margin-left: -6cqw;
  }

  .phone {
    width: 28cqw;
    padding: 1.4cqw;
    border-radius: 4.6cqw;
  }

  .phone-screen {
    border-radius: 3.4cqw;
  }

  .phone-island {
    top: 1.8cqw;
    width: 7.5cqw;
    height: 2.1cqw;
  }

  .phone-logo {
    width: 82%;
  }
}
</style>
