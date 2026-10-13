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
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Icon beside the label; two glass tiles to a row. */
.tile {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 58px;
  padding: 8px 12px;
  border: 1px solid var(--glass-edge);
  border-radius: 18px;
  background: var(--surface);
  color: var(--fg);
  font-size: 0.85rem;
  font-weight: 600;
  line-height: 1.2;
  text-decoration: none;
  transition: background-color 0.2s, border-color 0.2s, color 0.2s;
}

.tile svg,
.row svg {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
}

.tile svg {
  color: var(--link);
}

/* Long names wrap to a second line rather than being cut off. */
.tile span {
  min-width: 0;
  overflow-wrap: anywhere;
}

.tile.active {
  border-color: var(--accent);
  background: var(--accent);
  box-shadow: 0 8px 20px color-mix(in srgb, var(--accent) 30%, transparent);
  color: var(--on-accent);
  font-weight: 700;
}

.tile.active svg {
  color: currentColor;
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
  gap: 14px;
  min-height: 52px;
  padding: 0 8px;
  border: 0;
  border-radius: 14px;
  background: transparent;
  color: var(--fg);
  font-size: 0.95rem;
  font-weight: 600;
  text-align: left;
  text-decoration: none;
  cursor: pointer;
}

.row svg {
  color: var(--muted);
}

.row.active {
  background: var(--accent);
  color: var(--on-accent);
}

.row.active svg {
  color: currentColor;
}

.version {
  padding: 0 8px;
}
</style>
