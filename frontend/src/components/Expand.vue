<script setup>
import { collapseOut, expandIn } from '@/lib/motion'

// Wrap one v-if'd element: it opens to its height (and its gap) instead of popping in, and closes the same way.
</script>

<template>
  <Transition :css="false" @enter="expandIn" @leave="collapseOut">
    <slot />
  </Transition>
</template>
