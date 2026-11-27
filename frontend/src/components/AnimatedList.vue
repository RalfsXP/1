<script setup>
import { onMounted, ref } from 'vue'
import { collapseOut, expandIn } from '@/lib/motion'

/*
 * A `.list` whose rows animate: on first show they rise in one after another (.stagger); afterwards a
 * row that appears opens its space (e.g. a goal arriving in "Completed"), one that goes closes it (the
 * rows below glide up instead of jumping), and reordered rows slide (.list-move).
 */
defineProps({ tag: { type: String, default: 'ul' } })

const settled = ref(false)
onMounted(() => setTimeout(() => (settled.value = true), 900))
// Until it settles (first load, incl. data arriving a moment later) the stagger handles entrances.
const onEnter = (el, done) => (settled.value ? expandIn(el, done) : done())
</script>

<template>
  <TransitionGroup :tag="tag" name="list" class="list" :class="{ stagger: !settled }" @enter="onEnter" @leave="collapseOut">
    <slot />
  </TransitionGroup>
</template>
