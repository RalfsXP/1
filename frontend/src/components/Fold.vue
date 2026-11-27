<script setup>
import { ref, useId } from 'vue'
import Expand from '@/components/Expand.vue'

// A collapsible group ("Completed (3)", "Past (2)"): the header toggles, the body opens and closes smoothly.
const props = defineProps({ open: { type: Boolean, default: false } })
const isOpen = ref(props.open)
const id = useId()
</script>

<template>
  <section class="fold" :class="{ open: isOpen }">
    <button type="button" class="fold-head section-label" :aria-expanded="isOpen" :aria-controls="id" @click="isOpen = !isOpen">
      <slot name="summary" />
    </button>
    <Expand>
      <div v-if="isOpen" :id="id" class="fold-body">
        <slot />
      </div>
    </Expand>
  </section>
</template>
