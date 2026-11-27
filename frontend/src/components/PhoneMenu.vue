<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRightStartOnRectangleIcon, PlusIcon } from '@heroicons/vue/24/outline'
import AppVersion from '@/components/AppVersion.vue'
import { addAction } from '@/lib/addAction'
import { GOAL_TABS, NAV_FOOT, NAV_MAIN, isActive, signOut } from '@/lib/nav'

/*
 * Phone navigation (v1.8): one round ink button in the bottom-right corner. Tapping it morphs the button
 * itself into the menu — the circle grows into a white card (clip-path), the bars turn into a cross, and
 * the contents rise in one after another. The page's add action ("New goal" / "New event") is the first
 * thing in it. Closes on the cross, the backdrop, Esc, a choice or any page change.
 */
const route = useRoute()
const router = useRouter()
const open = ref(false)
const panel = ref(null)
const toggleBtn = ref(null)

function close(returnFocus = true) {
  if (!open.value) return
  open.value = false
  if (returnFocus) toggleBtn.value?.focus({ preventScroll: true })
}

function toggle() {
  if (open.value) return close()
  open.value = true
  nextTick(() => panel.value?.querySelector('.pm-content a, .pm-content button')?.focus({ preventScroll: true }))
}

const onKey = (e) => e.key === 'Escape' && close()
watch(open, (o) => (o ? addEventListener('keydown', onKey) : removeEventListener('keydown', onKey)))
onBeforeUnmount(() => removeEventListener('keydown', onKey))
watch(() => route.fullPath, () => close(false))

function runAdd() {
  const action = addAction.value
  close(false)
  // Let the menu fold away before the form's sheet slides up.
  setTimeout(() => action?.run(), 220)
}

const goalActive = (tab) => route.name === 'goals' && route.params.period === tab.period
</script>

<template>
  <div class="pm-backdrop" :class="{ open }" aria-hidden="true" @click="close()" />
  <div class="pm-layer">
    <div class="pm-shadow">
      <nav ref="panel" class="pm-panel" :class="{ open }" :aria-label="$t('nav.menu')">
        <div class="pm-content" :inert="!open">
          <button v-if="addAction" type="button" class="pm-item pm-add" style="--i: 0" @click="runAdd">
            <PlusIcon aria-hidden="true" />{{ $t(addAction.label) }}
          </button>

          <ul class="pm-tiles">
            <li v-for="(link, i) in NAV_MAIN" :key="link.label" class="pm-item" :style="{ '--i': i + 1, '--tint': `var(--${link.tint})` }">
              <RouterLink :to="link.to" class="pm-tile" :class="{ active: isActive(link, route) }">
                <span class="pm-icon"><component :is="link.icon" aria-hidden="true" /></span>
                <span>{{ $t(link.label) }}</span>
              </RouterLink>
            </li>
          </ul>

          <div class="pm-item pm-goals" style="--i: 5">
            <RouterLink
              v-for="tab in GOAL_TABS"
              :key="tab.period"
              :to="{ name: 'goals', params: { period: tab.period } }"
              class="pm-chip"
              :class="{ active: goalActive(tab) }"
              :style="{ '--tint': `var(--${tab.tint})` }"
            >
              <span class="pm-dot" />{{ $t(`periods.short.${tab.key}`) }}
            </RouterLink>
          </div>

          <div class="pm-item pm-foot" style="--i: 6">
            <RouterLink v-for="link in NAV_FOOT" :key="link.label" :to="link.to" class="pm-link" :class="{ active: isActive(link, route) }">
              <component :is="link.icon" aria-hidden="true" />{{ $t(link.label) }}
            </RouterLink>
            <button type="button" class="pm-link" @click="close(false); signOut(router)">
              <ArrowRightStartOnRectangleIcon aria-hidden="true" />{{ $t('nav.logout') }}
            </button>
            <AppVersion inline class="pm-version" />
          </div>
        </div>

        <button
          ref="toggleBtn"
          type="button"
          class="pm-toggle"
          :aria-label="open ? $t('nav.close') : $t('nav.menu')"
          :aria-expanded="open"
          @click="toggle"
        >
          <span class="bar top" />
          <span class="bar mid" />
          <span class="bar bot" />
        </button>
      </nav>
    </div>
  </div>
</template>

<style scoped>
/* ---------- Layers (placed from the top with --app-h: no fixed `bottom:` — see main.css) ---------- */
.pm-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 55;
  width: 100vw;
  height: var(--app-h);
  background: var(--backdrop);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.35s ease;
}

.pm-backdrop.open {
  opacity: 1;
  pointer-events: auto;
}

.pm-layer {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 60;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: flex-end;
  width: 100vw;
  height: var(--app-h);
  padding: 0 max(16px, env(safe-area-inset-right)) var(--dock-gap) max(16px, env(safe-area-inset-left));
  pointer-events: none;
}

/* The shadow follows the clipped shape (a box-shadow would be clipped away with it). */
.pm-shadow {
  width: min(100%, 420px);
  filter: drop-shadow(0 14px 24px rgb(23 23 28 / 0.28));
}

/* ---------- The morph: a 64 px circle in the corner ⇄ the whole card ---------- */
.pm-panel {
  --closed: inset(calc(100% - 64px) 0 0 calc(100% - 64px) round 32px);
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 18px 18px 14px;
  border-radius: 32px;
  background: var(--accent);
  clip-path: var(--closed);
  pointer-events: auto;
  transition:
    clip-path 0.42s cubic-bezier(0.4, 0, 0.2, 1),
    background-color 0.3s ease 0.08s;
}

.pm-panel.open {
  background: var(--surface);
  clip-path: inset(0 0 0 0 round 32px);
  transition:
    clip-path 0.58s cubic-bezier(0.32, 0.72, 0, 1),
    background-color 0.22s ease;
}

.pm-content {
  display: grid;
  gap: 12px;
}

/* Contents rise in after the card has started to open, one after another; they leave all at once. */
.pm-item {
  opacity: 0;
  transform: translateY(14px) scale(0.96);
  transition: opacity 0.12s ease, transform 0.2s ease;
}

.open .pm-item {
  opacity: 1;
  transform: none;
  transition:
    opacity 0.35s ease calc(0.1s + var(--i, 0) * 0.04s),
    transform 0.55s var(--spring) calc(0.1s + var(--i, 0) * 0.04s);
}

/* ---------- The button: bars ⇄ cross ---------- */
.pm-toggle {
  position: absolute;
  right: 0;
  bottom: 0;
  z-index: 2;
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--on-accent);
  cursor: pointer;
  transition: color 0.25s ease, transform 0.3s var(--spring);
}

.pm-toggle:active {
  transform: scale(0.86);
}

.open .pm-toggle {
  color: var(--fg);
}

.bar {
  grid-area: 1 / 1;
  width: 22px;
  height: 2.5px;
  border-radius: 2px;
  background: currentColor;
  transition: transform 0.45s var(--spring), opacity 0.2s ease, width 0.3s ease;
}

.bar.top {
  transform: translateY(-7px);
}

.bar.mid {
  width: 16px;
  transform: translateX(-3px);
}

.bar.bot {
  transform: translateY(7px);
}

.open .bar.top {
  transform: rotate(45deg);
}

.open .bar.mid {
  opacity: 0;
  transform: translateX(-12px) scaleX(0.2);
}

.open .bar.bot {
  transform: rotate(-45deg);
}

/* ---------- Contents ---------- */
.pm-add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 54px;
  border: 0;
  border-radius: 999px;
  background: var(--accent);
  color: var(--on-accent);
  font: inherit;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.pm-add svg {
  width: 20px;
  height: 20px;
  stroke-width: 2.4;
}

.pm-add:active {
  transform: scale(0.96);
}

.pm-tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pm-tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  padding: 14px;
  border-radius: 24px;
  background: color-mix(in srgb, var(--tint) var(--wash), var(--surface));
  color: var(--fg);
  font-family: var(--font-display);
  font-size: 1.12rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  text-decoration: none;
  transition: transform 0.3s var(--spring), background-color 0.2s;
}

.pm-tile:active {
  transform: scale(0.94);
}

.pm-tile.active {
  background: var(--tint);
  box-shadow: inset 0 0 0 2px var(--ink);
  color: var(--ink);
}

.pm-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 14px;
  background: var(--tint);
  color: var(--ink);
}

.pm-tile.active .pm-icon {
  background: rgb(255 255 255 / 0.65);
}

.pm-icon svg {
  width: 22px;
  height: 22px;
}

.pm-goals {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.pm-chip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  height: 38px;
  padding: 0 13px 0 10px;
  border-radius: 999px;
  background: var(--surface-2);
  color: var(--fg);
  font-size: 0.86rem;
  font-weight: 700;
  text-decoration: none;
  transition: transform 0.3s var(--spring);
}

.pm-chip:active {
  transform: scale(0.92);
}

.pm-chip.active {
  background: var(--accent);
  color: var(--on-accent);
}

.pm-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--tint);
}

/* Footer: room on the right for the button (it sits in the card's corner). */
.pm-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px 4px;
  min-height: 50px;
  padding-right: 64px;
}

.pm-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  padding: 0 10px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--muted);
  font: inherit;
  font-size: 0.88rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.pm-link:active,
.pm-link.active {
  background: var(--surface-2);
  color: var(--fg);
}

.pm-link svg {
  width: 18px;
  height: 18px;
}

.pm-version {
  padding: 0 10px;
}

@media (prefers-reduced-motion: reduce) {
  .pm-panel,
  .pm-panel.open {
    transition: none;
  }
}
</style>
