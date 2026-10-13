<script setup>
import { HandRaisedIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { dismissHoldHint, holdHintDismissed, touchUI } from '@/lib/input'

// Touch devices only: explains tap = done, hold = edit until dismissed once.
</script>

<template>
  <p v-if="touchUI && !holdHintDismissed" class="hold-hint">
    <HandRaisedIcon class="hand" aria-hidden="true" />
    <span>{{ $t('goal.holdHint') }}</span>
    <button type="button" class="close" :aria-label="$t('nav.close')" @click="dismissHoldHint">
      <XMarkIcon aria-hidden="true" />
    </button>
  </p>
</template>

<style scoped>
.hold-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 8px 6px 8px 14px;
  border: 1px solid var(--glass-edge);
  border-radius: 16px;
  background: var(--surface);
  color: var(--fg);
  font-size: 0.8rem;
  font-weight: 600;
}

.hand {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  color: var(--link);
}

span {
  flex: 1;
}

.close {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
}

.close svg {
  width: 16px;
  height: 16px;
}
</style>
