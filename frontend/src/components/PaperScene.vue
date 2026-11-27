<script setup>
import { computed } from 'vue'
import { CakeIcon, CheckIcon, FireIcon } from '@heroicons/vue/24/outline'
import { dayUnit } from '@/lib/events'
import { t } from '@/lib/i18n'
import { addDays, isoWeekday, parseISO } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

/*
 * Landing / auth art (v1.7): a few tilted paper cards and pastel pills floating gently — this week with
 * today in ink, today's ring, a streak, a ticked-off goal and an upcoming birthday. Decorative only.
 * Drawn on a 340 × 360 grid in container units, so it fits whatever box it gets.
 */
const today = computed(() => parseISO(todayISO.value))
const monthName = computed(() => t('common.months')[today.value.getMonth()])
const week = computed(() => {
  const monday = addDays(today.value, 1 - isoWeekday(today.value))
  return Array.from({ length: 7 }, (_, i) => {
    const d = addDays(monday, i)
    return { key: i, name: t('common.weekdaysShort')[i].slice(0, 2), day: d.getDate(), today: d.getTime() === today.value.getTime() }
  })
})
// Ring geometry for "3 of 5".
const R = 34
const C = 2 * Math.PI * R
</script>

<template>
  <div class="scene" aria-hidden="true">
    <div class="stage">
      <div class="card week" style="--i: 0">
        <span class="month">{{ monthName }}</span>
        <span class="days">
          <span v-for="d in week" :key="d.key" class="day" :class="{ today: d.today }">
            <span class="dow">{{ d.name }}</span>
            <span class="num">{{ d.day }}</span>
          </span>
        </span>
      </div>

      <div class="card ring-card" style="--i: 1">
        <span class="label">{{ $t('dashboard.day') }}</span>
        <span class="ring">
          <svg viewBox="0 0 80 80">
            <circle class="track" cx="40" cy="40" :r="R" />
            <circle class="fill" cx="40" cy="40" :r="R" :stroke-dasharray="C" :stroke-dashoffset="C * 0.4" :style="{ '--c': C }" />
          </svg>
          <span class="value">3<span class="of">/5</span></span>
        </span>
      </div>

      <div class="card streak" style="--i: 2">
        <span class="label"><FireIcon />{{ $t('dashboard.streak') }}</span>
        <span class="big">12</span>
      </div>

      <div class="card goal" style="--i: 3">
        <span class="check"><CheckIcon /></span>
        <span class="title">{{ $t('landing.sceneGoal') }}</span>
      </div>

      <div class="card event" style="--i: 4">
        <span class="icon"><CakeIcon /></span>
        <span class="title">{{ $t('landing.sceneEvent') }}</span>
        <span class="days-left">3 {{ dayUnit(3) }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Fills the box it's given (the parent sets width + height) and fits the scene inside it. */
.scene {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  min-height: 0;
  container-type: size;
}

/* Drawn on a 340 × 360 grid; --u is one grid unit at the size that fits. */
.stage {
  --u: min(calc(100cqw / 340), calc(100cqh / 360));
  position: relative;
  width: calc(var(--u) * 340);
  height: calc(var(--u) * 360);
  font-family: var(--font-body);
}

/* Each card pops in (staggered by --i), then floats on its own slow loop. */
.card {
  position: absolute;
  display: flex;
  border-radius: calc(var(--u) * 22);
  background: var(--surface);
  box-shadow: 0 calc(var(--u) * 18) calc(var(--u) * 40) calc(var(--u) * -14) rgb(23 23 28 / 0.32), inset 0 0 0 1px var(--card-edge);
  color: var(--fg);
  animation:
    card-in 0.8s var(--spring) backwards calc(0.15s + var(--i) * 0.1s),
    float 7s ease-in-out infinite calc(var(--i) * -1.3s);
}

@keyframes card-in {
  from {
    opacity: 0;
    scale: 0.6;
  }
}

@keyframes float {
  0%,
  100% {
    translate: 0 0;
  }
  50% {
    translate: 0 calc(var(--u) * -9);
  }
}

/* This week, today in ink. */
.week {
  top: calc(var(--u) * 46);
  left: calc(var(--u) * 14);
  flex-direction: column;
  gap: calc(var(--u) * 10);
  width: calc(var(--u) * 292);
  padding: calc(var(--u) * 16);
  rotate: -3deg;
}

.month {
  font-family: var(--font-display);
  font-size: calc(var(--u) * 17);
  font-weight: 800;
  letter-spacing: -0.03em;
}

.days {
  display: flex;
  justify-content: space-between;
}

.day {
  display: grid;
  justify-items: center;
  gap: calc(var(--u) * 3);
  width: calc(var(--u) * 34);
  padding: calc(var(--u) * 6) 0;
  border-radius: calc(var(--u) * 17);
  background: var(--surface-2);
}

.dow {
  color: var(--muted);
  font-size: calc(var(--u) * 9);
  font-weight: 700;
}

.num {
  font-family: var(--font-display);
  font-size: calc(var(--u) * 15);
  font-weight: 800;
}

.day.today {
  background: var(--accent);
  color: var(--on-accent);
}

.day.today .dow {
  color: inherit;
  opacity: 0.7;
}

/* Today's ring (lavender). */
.ring-card {
  top: calc(var(--u) * 166);
  left: calc(var(--u) * 22);
  flex-direction: column;
  gap: calc(var(--u) * 8);
  width: calc(var(--u) * 150);
  padding: calc(var(--u) * 16);
  background: var(--lav);
  color: var(--ink);
  rotate: -6deg;
}

.label {
  display: flex;
  align-items: center;
  gap: calc(var(--u) * 5);
  font-family: var(--font-display);
  font-size: calc(var(--u) * 13);
  font-weight: 700;
}

.label svg {
  width: calc(var(--u) * 16);
  height: calc(var(--u) * 16);
}

.ring {
  position: relative;
  display: grid;
  place-items: center;
  align-self: center;
  width: calc(var(--u) * 96);
  height: calc(var(--u) * 96);
}

.ring svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.ring circle {
  fill: none;
  stroke-width: 9;
}

.track {
  stroke: rgb(255 255 255 / 0.55);
}

.fill {
  stroke: var(--ink);
  stroke-linecap: round;
  animation: sweep 1.4s var(--ease) backwards 0.6s;
}

@keyframes sweep {
  from {
    stroke-dashoffset: var(--c);
  }
}

.value {
  font-family: var(--font-display);
  font-size: calc(var(--u) * 26);
  font-weight: 800;
  letter-spacing: -0.05em;
}

.of {
  font-size: 0.55em;
  opacity: 0.6;
}

/* Streak (butter). */
.streak {
  top: calc(var(--u) * 158);
  left: calc(var(--u) * 196);
  flex-direction: column;
  gap: calc(var(--u) * 2);
  width: calc(var(--u) * 122);
  padding: calc(var(--u) * 14) calc(var(--u) * 16);
  background: var(--butter);
  color: var(--ink);
  rotate: 7deg;
}

.big {
  font-family: var(--font-display);
  font-size: calc(var(--u) * 54);
  font-weight: 800;
  line-height: 0.9;
  letter-spacing: -0.06em;
}

/* A ticked-off goal (white pill). */
.goal {
  top: calc(var(--u) * 286);
  left: calc(var(--u) * 150);
  align-items: center;
  gap: calc(var(--u) * 10);
  padding: calc(var(--u) * 9) calc(var(--u) * 18) calc(var(--u) * 9) calc(var(--u) * 9);
  border-radius: 999px;
  rotate: -3deg;
}

.check {
  display: grid;
  place-items: center;
  width: calc(var(--u) * 28);
  height: calc(var(--u) * 28);
  border-radius: 50%;
  background: var(--accent);
  color: var(--on-accent);
}

.check svg {
  width: 60%;
  height: 60%;
  stroke-width: 3;
}

.title {
  font-size: calc(var(--u) * 13);
  font-weight: 700;
  white-space: nowrap;
}

.goal .title {
  text-decoration: line-through;
  text-decoration-thickness: 1.5px;
  color: var(--muted);
}

/* An upcoming birthday (rose pill). */
.event {
  top: calc(var(--u) * 0);
  left: calc(var(--u) * 120);
  align-items: center;
  gap: calc(var(--u) * 8);
  padding: calc(var(--u) * 8) calc(var(--u) * 14) calc(var(--u) * 8) calc(var(--u) * 8);
  border-radius: 999px;
  background: var(--rose);
  color: var(--ink);
  rotate: 4deg;
}

.icon {
  display: grid;
  place-items: center;
  width: calc(var(--u) * 26);
  height: calc(var(--u) * 26);
  border-radius: 50%;
  background: rgb(255 255 255 / 0.6);
}

.icon svg {
  width: 62%;
  height: 62%;
}

.days-left {
  padding: calc(var(--u) * 3) calc(var(--u) * 8);
  border-radius: 999px;
  background: var(--ink);
  color: #ffffff;
  font-size: calc(var(--u) * 11);
  font-weight: 800;
  white-space: nowrap;
}
</style>
