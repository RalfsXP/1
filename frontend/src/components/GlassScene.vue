<script setup>
import { computed } from 'vue'
import { CheckIcon } from '@heroicons/vue/24/outline'
import { t } from '@/lib/i18n'
import { addDays, isoWeekday, parseISO } from '@/lib/periods'
import { todayISO } from '@/lib/timeLeft'

/*
 * Landing / auth art: three tilted glass cards floating over the mist — this week with
 * today highlighted, today's progress and a finished goal. Static, sized in container
 * units so it scales with whatever width it gets.
 */
const today = computed(() => parseISO(todayISO.value))
const monthName = computed(() => t('common.months')[today.value.getMonth()])
const week = computed(() => {
  // Monday-first, six days around today so the highlight never sits at the edge.
  const monday = addDays(today.value, 1 - isoWeekday(today.value))
  return Array.from({ length: 6 }, (_, i) => {
    const d = addDays(monday, i)
    return { key: i, name: t('common.weekdaysShort')[i], day: d.getDate(), today: d.getTime() === today.value.getTime() }
  })
})
</script>

<template>
  <div class="glass-scene" aria-hidden="true">
    <div class="stage">
      <div class="card week">
        <span class="month">{{ monthName }}</span>
        <span class="days">
          <span v-for="d in week" :key="d.key" class="day" :class="{ today: d.today }">
            <span class="dow">{{ d.name }}</span>
            <span class="num">{{ d.day }}</span>
          </span>
        </span>
      </div>

      <div class="card progress">
        <span class="label">{{ $t('dashboard.day') }}</span>
        <span class="value">3<span class="of">/ 5</span></span>
        <span class="meter"><span class="fill" /></span>
      </div>

      <div class="card goal">
        <span class="check"><CheckIcon /></span>
        <span class="title">{{ $t('landing.sceneGoal') }}</span>
        <span class="badge">{{ $t('periods.short.day') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Fills the box it's given (the parent sets width + height) and fits the scene inside it. */
.glass-scene {
  display: grid;
  place-items: start center;
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
  font-family: Montserrat, ui-sans-serif, system-ui, sans-serif;
}

.card {
  position: absolute;
  display: flex;
  border: 1px solid var(--glass-edge);
  background: var(--surface);
  box-shadow: 0 calc(var(--u) * 20) calc(var(--u) * 44) rgb(0 0 0 / 0.16);
  color: var(--fg);
  -webkit-backdrop-filter: blur(20px) saturate(1.4);
  backdrop-filter: blur(20px) saturate(1.4);
  animation: float 7s ease-in-out infinite;
}

.week {
  top: 0;
  left: calc(var(--u) * 96);
  width: calc(var(--u) * 240);
  flex-direction: column;
  gap: calc(var(--u) * 12);
  padding: calc(var(--u) * 16);
  border-radius: calc(var(--u) * 24);
  rotate: 7deg;
  animation-delay: -2s;
}

.month {
  font-size: calc(var(--u) * 16);
  font-weight: 800;
}

.month::first-letter {
  text-transform: uppercase;
}

.days {
  display: flex;
  justify-content: space-between;
}

.day {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: calc(var(--u) * 4);
  width: calc(var(--u) * 28);
}

.dow {
  color: var(--muted);
  font-size: calc(var(--u) * 10);
  font-weight: 600;
}

.num {
  display: grid;
  place-items: center;
  width: calc(var(--u) * 26);
  height: calc(var(--u) * 26);
  border-radius: 50%;
  font-size: calc(var(--u) * 13);
  font-weight: 600;
}

.day.today .num {
  background: var(--highlight);
  color: var(--on-highlight);
  font-weight: 800;
}

.progress {
  top: calc(var(--u) * 134);
  left: calc(var(--u) * 4);
  width: calc(var(--u) * 250);
  flex-direction: column;
  gap: calc(var(--u) * 14);
  padding: calc(var(--u) * 18) calc(var(--u) * 20) calc(var(--u) * 20);
  border-radius: calc(var(--u) * 26);
  rotate: -5deg;
}

.label {
  color: var(--muted);
  font-size: calc(var(--u) * 13);
  font-weight: 600;
}

.value {
  font-size: calc(var(--u) * 52);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 0.95;
}

.of {
  margin-left: calc(var(--u) * 6);
  color: var(--muted);
  font-size: calc(var(--u) * 20);
  font-weight: 600;
  letter-spacing: 0;
}

.meter {
  height: calc(var(--u) * 7);
  overflow: hidden;
  border-radius: 999px;
  background: var(--pill);
}

.fill {
  display: block;
  width: 60%;
  height: 100%;
  border-radius: 999px;
  background: var(--accent);
}

.goal {
  top: calc(var(--u) * 300);
  left: calc(var(--u) * 100);
  width: calc(var(--u) * 236);
  align-items: center;
  gap: calc(var(--u) * 12);
  padding: calc(var(--u) * 12) calc(var(--u) * 14);
  border-radius: calc(var(--u) * 20);
  rotate: 3deg;
  animation-delay: -4s;
}

.check {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: calc(var(--u) * 24);
  height: calc(var(--u) * 24);
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
  flex: 1;
  min-width: 0;
  overflow: hidden;
  font-size: calc(var(--u) * 14);
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge {
  flex-shrink: 0;
  padding: calc(var(--u) * 3) calc(var(--u) * 8);
  border-radius: 999px;
  background: var(--soft);
  color: var(--ink);
  font-size: calc(var(--u) * 10);
  font-weight: 700;
}

/* A slow drift so the scene feels alive. */
@keyframes float {
  50% {
    translate: 0 calc(var(--u) * -6);
  }
}

@media (prefers-reduced-motion: reduce) {
  .card {
    animation: none;
  }
}
</style>
