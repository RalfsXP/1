<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronDownIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import BadgePicker from '@/components/BadgePicker.vue'
import Expand from '@/components/Expand.vue'
import GoalBadge from '@/components/GoalBadge.vue'
import { changeLocale, logout, user } from '@/lib/auth'
import { categoryStyle, saveCategoryStyle } from '@/lib/goals'
import { LOCALES, locale, t } from '@/lib/i18n'
import { withLoader } from '@/lib/loader'
import { PERIODS } from '@/lib/periods'
import { theme, toggleTheme } from '@/lib/theme'

const router = useRouter()

/* Default badge per goal category + events: one row open at a time, saved per row. */
const STYLE_CATEGORIES = [...PERIODS, 'event']
const categoryName = (period) => (period === 'event' ? t('events.title') : t(`periods.${period}`))

const openPeriod = ref(null)
const draft = ref({ color: '', icon: '' })
const saving = ref(false)
const error = ref('')

function toggle(period) {
  error.value = ''
  if (openPeriod.value === period) {
    openPeriod.value = null
    return
  }
  openPeriod.value = period
  draft.value = { ...categoryStyle(period) }
}

async function save(period) {
  saving.value = true
  error.value = ''
  try {
    await saveCategoryStyle(period, draft.value)
    openPeriod.value = null
  } catch {
    error.value = t('auth.errorGeneric')
  } finally {
    saving.value = false
  }
}

const setTheme = (value) => theme.value !== value && toggleTheme()

const initial = computed(() => user.value?.name?.trim()?.[0]?.toUpperCase() ?? '')

function signOut() {
  return withLoader(async () => {
    await logout()
    await router.push({ name: 'landing' })
  })
}
</script>

<template>
  <div class="page narrow">
    <header class="page-head">
      <div>
        <h1 class="page-title">{{ $t('settings.title') }}</h1>
      </div>
    </header>

    <section v-reveal class="section">
      <h2 class="section-title">{{ $t('settings.appearance') }}</h2>
      <div class="card prefs">
        <div class="pref">
          <span class="pref-name">{{ $t('settings.theme') }}</span>
          <div v-slide-pill class="segmented" role="group" :aria-label="$t('settings.theme')">
            <button type="button" :aria-pressed="theme === 'light'" @click="setTheme('light')">{{ $t('common.themeLight') }}</button>
            <button type="button" :aria-pressed="theme === 'dark'" @click="setTheme('dark')">{{ $t('common.themeDark') }}</button>
          </div>
        </div>
        <div v-if="LOCALES.length > 1" class="pref">
          <span class="pref-name">{{ $t('common.language') }}</span>
          <div v-slide-pill class="segmented" role="group" :aria-label="$t('common.language')">
            <button v-for="code in LOCALES" :key="code" type="button" :aria-pressed="locale === code" @click="changeLocale(code)">
              {{ $t(`settings.languages.${code}`) }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <section v-reveal class="section">
      <header>
        <h2 class="section-title">{{ $t('settings.badges') }}</h2>
        <p class="lead">{{ $t('settings.badgesLead') }}</p>
      </header>

      <ul class="card rows">
        <li v-for="period in STYLE_CATEGORIES" :key="period" class="row" :class="{ open: openPeriod === period }">
          <button type="button" class="row-head" :aria-expanded="openPeriod === period" @click="toggle(period)">
            <GoalBadge
              :title="categoryName(period)"
              v-bind="openPeriod === period ? draft : categoryStyle(period)"
            />
            <ChevronDownIcon class="chevron" aria-hidden="true" />
          </button>

          <Expand>
          <form v-if="openPeriod === period" class="row-body" @submit.prevent="save(period)">
            <BadgePicker v-model:color="draft.color" v-model:icon="draft.icon" />
            <p v-if="error" class="error" role="alert"><InformationCircleIcon aria-hidden="true" />{{ error }}</p>
            <button type="submit" class="btn btn-primary btn-lg" :disabled="saving">
              {{ saving ? $t('goal.saving') : $t('goal.save') }}
            </button>
          </form>
          </Expand>
        </li>
      </ul>
    </section>

    <section v-reveal class="section">
      <h2 class="section-title">{{ $t('common.account') }}</h2>
      <div class="card account">
        <span class="avatar" aria-hidden="true">{{ initial }}</span>
        <div class="who">
          <p class="name">{{ user?.name }}</p>
          <p class="email">{{ user?.email }}</p>
        </div>
        <button type="button" class="btn btn-ghost" @click="signOut">{{ $t('nav.logout') }}</button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.section {
  display: grid;
  gap: 10px;
}

.section .section-title {
  margin: 0;
  padding: 0 4px;
}

.lead {
  margin: 4px 0 0;
  padding: 0 4px;
  color: var(--muted);
  font-size: 0.85rem;
}

.prefs {
  display: grid;
  gap: 16px;
}

.pref {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px 16px;
}

.pref-name {
  font-size: 0.95rem;
  font-weight: 600;
}

.pref .segmented {
  flex: 1 1 280px;
}

.rows {
  display: grid;
  margin: 0;
  padding: 4px 16px;
  list-style: none;
}

.row + .row {
  border-top: 1px solid var(--line);
}

.row-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 60px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--fg);
  cursor: pointer;
}

.row-head:focus-visible {
  outline: 2px solid var(--link);
  outline-offset: 2px;
  border-radius: 12px;
}

.chevron {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  color: var(--muted);
  transition: transform 0.3s var(--spring);
}

.open .chevron {
  transform: rotate(180deg);
}

.row-body {
  display: grid;
  gap: 18px;
  padding: 4px 0 18px;
}

.error {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.85rem;
}

.error svg {
  width: 18px;
  height: 18px;
  color: var(--flag-important);
}

.account {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
}

.avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: var(--peach);
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 800;
}

.who {
  flex: 1;
  min-width: 0;
}

.who p {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.name {
  font-family: var(--font-display);
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.email {
  color: var(--muted);
  font-size: 0.85rem;
}
</style>
