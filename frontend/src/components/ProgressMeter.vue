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
    <span class="track"><span class="fill" :style="{ width: `${percent}%` }" /></span>
  </div>
</template>

<style scoped>
.meter {
  display: flex;
  align-items: center;
  gap: 10px;
}

.count {
  order: 2;
  min-width: 2.6em;
  font-size: 0.9rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.track {
  flex: 1;
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--pill);
}

.fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: var(--accent);
  transition: width 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
}
</style>
