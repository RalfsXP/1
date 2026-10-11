<script setup>
import { useRouter } from 'vue-router'
import { ArrowRightStartOnRectangleIcon } from '@heroicons/vue/24/outline'
import AppVersion from '@/components/AppVersion.vue'
import BottomSheet from '@/components/BottomSheet.vue'
import { NAV_FOOT, NAV_LINKS, signOut } from '@/lib/nav'

// Phone navigation: a sheet from the bottom with every page as a tile.
defineProps({ open: { type: Boolean, default: false } })
const emit = defineEmits(['close'])
const router = useRouter()
</script>

<template>
  <BottomSheet :open="open" :title="$t('nav.menu')" @close="emit('close')">
    <nav class="mobile-nav" :aria-label="$t('nav.menu')">
      <ul class="tiles">
        <li v-for="link in NAV_LINKS" :key="link.label">
          <RouterLink :to="link.to" class="tile" exact-active-class="active" @click="emit('close')">
            <component :is="link.icon" aria-hidden="true" />
            <span>{{ $t(link.label) }}</span>
          </RouterLink>
        </li>
      </ul>

      <div class="rows">
        <RouterLink v-for="link in NAV_FOOT" :key="link.label" :to="link.to" class="row" exact-active-class="active" @click="emit('close')">
          <component :is="link.icon" aria-hidden="true" />
          <span>{{ $t(link.label) }}</span>
        </RouterLink>
        <button type="button" class="row logout" @click="emit('close'); signOut(router)">
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
  gap: 16px;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Icon above the label, so full names fit two to a row. */
.tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 8px;
  min-height: 76px;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--surface);
  color: var(--fg);
  font-size: 0.9rem;
  font-weight: 700;
  text-decoration: none;
  transition: background-color 0.2s, border-color 0.2s, color 0.2s;
}

.tile svg,
.row svg {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
}

.tile span {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tile.active {
  border-color: var(--accent);
  background: var(--accent);
  color: var(--on-accent);
}

.rows {
  display: grid;
  gap: 2px;
  padding-top: 8px;
  border-top: 1px solid var(--line);
}

.row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 48px;
  padding: 0 12px;
  border: 0;
  border-radius: 14px;
  background: transparent;
  color: var(--fg);
  font-size: 0.95rem;
  font-weight: 700;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}

.row.active {
  background: var(--accent);
  color: var(--on-accent);
}

.row.logout {
  color: var(--muted);
}

.version {
  padding: 0 12px;
}
</style>
