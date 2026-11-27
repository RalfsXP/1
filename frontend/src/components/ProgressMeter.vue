<script setup>
import { computed } from 'vue'

// "3/5" with a slim accent bar: how much of a set of goals is done.
const props = defineProps({
  done: { type: Number, required: true },
  total: { type: Number, required: true },
})

const percent = computed(() => (props.total ? Math.round((props.done / props.total) * 100) : 0))
</script>

<template>
  <div
    v-if="total"
    class="meter"
    role="progressbar"
    :aria-valuenow="done"
    aria-valuemin="0"
    :aria-valuemax="total"
    :aria-label="$t('goal.progress')"
  >
    <span class="count">{{ done }}/{{ total }}</span>
    <span class="track"><span class="fill" :style="{ transform: `translateX(${percent - 100}%)` }" /></span>
  </div>
</template>

<style scoped>
.meter {
  display: flex;
  align-items: center;
  gap: 12px;
}

.count {
  order: 2;
  min-width: 2.6em;
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.track {
  flex: 1;
  height: 12px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--surface-2);
}

/* Fills from empty when it appears, then follows every tick. A full-width bar slid in from the left
   (transform only, so it never re-lays out the page); the track's rounded clip shapes its left end. */
.fill {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: 999px;
  background: var(--accent);
  transition: transform 0.6s var(--spring);
  animation: fill-in 0.9s var(--ease) backwards 0.15s;
}

@keyframes fill-in {
  from {
    transform: translateX(-100%);
  }
}
</style>
