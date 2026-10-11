<script setup>
import DemoScreen from '@/components/DemoScreen.vue'

/**
 * The floating 3D device scene (landing + login/register): a laptop and a phone
 * turned towards each other, plus a tablet between them on the wide landing
 * layout. Each screen runs a static miniature of the app (DemoScreen).
 * Every size is in cqw of the stage, so the whole scene scales as one piece.
 *
 *   layout="landing"  laptop + tablet + phone (tablet dropped on phones)
 *   layout="auth"     laptop + phone, composed for half a screen
 */
defineProps({ layout: { type: String, default: 'landing' } })
</script>

<template>
  <div class="stage" :class="`layout-${layout}`" aria-hidden="true">
    <div class="device laptop">
      <div class="lid">
        <span class="cam" />
        <div class="screen"><DemoScreen variant="desktop" /></div>
      </div>
      <div class="base"><span class="notch" /></div>
    </div>

    <div v-if="layout === 'landing'" class="device tablet">
      <div class="screen"><DemoScreen variant="tablet" /></div>
    </div>

    <div class="device phone">
      <div class="screen"><DemoScreen variant="phone" /></div>
    </div>
  </div>
</template>

<style scoped>
.stage {
  container-type: inline-size;
  position: relative;
  perspective: 2400px;
}

/* Wide landing: ~2:1, capped by height so short windows never clip it. */
.layout-landing {
  width: min(1180px, 100%, calc((100dvh - 150px) * 2));
  aspect-ratio: 100 / 50;
}

/* Half a screen next to the login/register form. */
.layout-auth {
  width: min(620px, 100%, calc((100dvh - 160px) * 1.2));
  aspect-ratio: 100 / 82;
  perspective: 1800px;
}

.device {
  position: absolute;
  transform-style: preserve-3d;
  transform: var(--tilt);
  animation: float 7s ease-in-out infinite;
}

/* Soft floor shadow under each device. */
.device::after {
  content: '';
  position: absolute;
  right: 8%;
  bottom: -3.2cqw;
  left: 8%;
  height: 2.4cqw;
  border-radius: 50%;
  background: radial-gradient(closest-side, color-mix(in srgb, var(--black) 55%, transparent), transparent);
  opacity: 0.7;
  pointer-events: none;
}

.screen {
  container-type: inline-size;
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: var(--bg);
}

/* ---------- Laptop ---------- */
.laptop {
  top: 1cqw;
  left: 2cqw;
  width: 60cqw;
  --tilt: rotateY(14deg) rotateX(5deg);
}

.lid {
  position: relative;
  aspect-ratio: 16 / 10;
  padding: 1.3cqw 1.3cqw 1.8cqw;
  border-radius: 2cqw 2cqw 0.6cqw 0.6cqw;
  background: var(--frame);
  box-shadow: inset 0 0 0 0.12cqw var(--frame-edge);
}

.lid .screen {
  border-radius: 0.6cqw;
}

.cam {
  position: absolute;
  top: 0.5cqw;
  left: 50%;
  width: 0.4cqw;
  height: 0.4cqw;
  border-radius: 50%;
  background: var(--frame-edge);
  transform: translateX(-50%);
}

.base {
  position: relative;
  width: 112%;
  height: 1.6cqw;
  margin-left: -6%;
  border-radius: 0.2cqw 0.2cqw 1.6cqw 1.6cqw / 0.2cqw 0.2cqw 1.2cqw 1.2cqw;
  background: var(--frame);
  box-shadow: inset 0 0.12cqw 0 var(--frame-edge);
}

.notch {
  position: absolute;
  top: 0;
  left: 50%;
  width: 10cqw;
  height: 0.6cqw;
  border-radius: 0 0 0.8cqw 0.8cqw;
  background: var(--frame-edge);
  transform: translateX(-50%);
}

/* ---------- Tablet ---------- */
.tablet {
  right: 17cqw;
  bottom: 3cqw;
  width: 23cqw;
  aspect-ratio: 3 / 4;
  padding: 1.1cqw;
  border-radius: 2.2cqw;
  background: var(--frame);
  box-shadow:
    inset 0 0 0 0.12cqw var(--frame-edge),
    0 2cqw 4cqw -1cqw color-mix(in srgb, var(--black) 45%, transparent);
  --tilt: rotateY(-18deg) rotateX(5deg);
  animation-delay: -2.3s;
}

.tablet .screen {
  border-radius: 1.3cqw;
}

/* ---------- Phone ---------- */
.phone {
  right: 6cqw;
  bottom: 0;
  width: 12.5cqw;
  aspect-ratio: 9 / 19.5;
  padding: 0.7cqw;
  border-radius: 2.4cqw;
  background: var(--frame);
  box-shadow:
    inset 0 0 0 0.12cqw var(--frame-edge),
    0 2cqw 4cqw -1cqw color-mix(in srgb, var(--black) 50%, transparent);
  --tilt: rotateY(-26deg) rotateX(5deg);
  animation-delay: -4.6s;
}

.phone .screen {
  border-radius: 1.8cqw;
}

/* ---------- Auth layout: laptop behind, phone in front ---------- */
.layout-auth .laptop {
  top: 4cqw;
  left: 0;
  width: 84cqw;
  --tilt: rotateY(12deg) rotateX(5deg);
}

.layout-auth .lid {
  padding: 1.7cqw 1.7cqw 2.3cqw;
}

.layout-auth .phone {
  right: 2cqw;
  bottom: 1cqw;
  width: 30cqw;
  padding: 1.4cqw;
  border-radius: 5cqw;
  --tilt: rotateY(-20deg) rotateX(5deg);
}

.layout-auth .phone .screen {
  border-radius: 3.8cqw;
}

/* A slow drift; each device starts at a different point so they don't move in step. */
@keyframes float {
  50% {
    transform: var(--tilt) translateY(-0.8cqw);
  }
}

/* ---------- Landing on phones: laptop + phone only ---------- */
@media (max-width: 640px) {
  .layout-landing {
    width: min(100%, calc((100dvh - 300px) * 1.25));
    aspect-ratio: 100 / 80;
    perspective: 1400px;
  }

  .layout-landing .tablet {
    display: none;
  }

  .layout-landing .laptop {
    top: 4cqw;
    left: 0;
    width: 86cqw;
    --tilt: rotateY(12deg) rotateX(4deg);
  }

  .layout-landing .lid {
    padding: 1.6cqw 1.6cqw 2.2cqw;
  }

  .layout-landing .phone {
    right: 1cqw;
    bottom: 1cqw;
    width: 31cqw;
    padding: 1.4cqw;
    border-radius: 5.4cqw;
    --tilt: rotateY(-18deg) rotateX(4deg);
  }

  .layout-landing .phone .screen {
    border-radius: 4.2cqw;
  }
}

@media (prefers-reduced-motion: reduce) {
  .device {
    animation: none;
  }
}
</style>
