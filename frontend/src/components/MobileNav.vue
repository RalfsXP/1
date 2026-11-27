<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ArrowRightStartOnRectangleIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import AppVersion from '@/components/AppVersion.vue'
import BottomSheet from '@/components/BottomSheet.vue'
import { GOAL_TABS, NAV_FOOT, isActive, signOut } from '@/lib/nav'

// Phone menu (the dock's Menu button): every goal category as a pastel tile, then Guide, Settings, Log out.
defineProps({ open: { type: Boolean, default: false } })
const emit = defineEmits(['close'])
const route = useRoute()
const router = useRouter()
const goalActive = (tab) => route.name === 'goals' && route.params.period === tab.period
</script>

<template>
  <BottomSheet :open="open" :title="$t('nav.menu')" @close="emit('close')">
    <nav class="mobile-nav" :aria-label="$t('nav.menu')">
      <span class="section-label">{{ $t('nav.goals') }}</span>
      <ul class="tiles stagger">
        <li v-for="tab in GOAL_TABS" :key="tab.period">
          <RouterLink
            :to="{ name: 'goals', params: { period: tab.period } }"
            class="tile"
            :class="{ active: goalActive(tab) }"
            :style="{ '--tint': `var(--${tab.tint})` }"
            @click="emit('close')"
          >
            <span class="tile-icon"><component :is="tab.icon" aria-hidden="true" /></span>
            <span>{{ $t(tab.label) }}</span>
          </RouterLink>
        </li>
      </ul>

      <div class="rows">
        <RouterLink
          v-for="link in NAV_FOOT"
          :key="link.label"
          :to="link.to"
          class="row"
          :class="{ active: isActive(link, route) }"
          @click="emit('close')"
        >
          <component :is="link.icon" aria-hidden="true" />
          <span>{{ $t(link.label) }}</span>
          <ChevronRightIcon class="chev" aria-hidden="true" />
        </RouterLink>
        <button type="button" class="row" @click="emit('close'); signOut(router)">
          <ArrowRightStartOnRectangleIcon aria-hidden="true" />
          <span>{{ $t('nav.logout') }}</span>
        </button>
      </div>

      <AppVersion inline class="version" />
    </nav>
  </BottomSheet>
</template>

<style scoped>
.mobile-nav {
  display: grid;
  gap: 12px;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0 0 6px;
  padding: 0;
  list-style: none;
}

/* The fifth tile (Custom) spans the row. */
.tiles li:last-child:nth-child(odd) {
  grid-column: span 2;
}

.tile {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 64px;
  padding: 10px 14px 10px 10px;
  border-radius: 22px;
  background: color-mix(in srgb, var(--tint) var(--wash-strong), var(--surface));
  color: var(--fg);
  font-size: 0.92rem;
  font-weight: 700;
  line-height: 1.2;
  text-decoration: none;
  transition: transform 0.25s var(--spring), background-color 0.2s;
}

.tile:active {
  transform: scale(0.95);
}

.tile.active {
  background: var(--tint);
  box-shadow: inset 0 0 0 2px var(--ink);
  color: var(--ink);
}

.tile-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 14px;
  background: var(--tint);
  color: var(--ink);
}

.tile-icon svg {
  width: 22px;
  height: 22px;
}

.tile span:last-child {
  min-width: 0;
  overflow-wrap: anywhere;
}

.rows {
  display: grid;
  overflow: hidden;
  border-radius: 22px;
  background: var(--surface-2);
}

.row {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 56px;
  padding: 0 16px;
  border: 0;
  background: transparent;
  color: var(--fg);
  font-size: 0.98rem;
  font-weight: 600;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s;
}

.row + .row {
  box-shadow: inset 0 1px 0 var(--line);
}

.row:active,
.row.active {
  background: var(--line);
}

.row svg {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  color: var(--muted);
}

.row .chev {
  width: 18px;
  height: 18px;
  margin-left: auto;
}

.version {
  justify-self: center;
  padding-top: 4px;
}
</style>
