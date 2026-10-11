<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronDownIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import BadgePicker from '@/components/BadgePicker.vue'
import DesignSwitch from '@/components/DesignSwitch.vue'
import GoalBadge from '@/components/GoalBadge.vue'
import { changeLocale, logout, user } from '@/lib/auth'
import { categoryStyle, saveCategoryStyle } from '@/lib/goals'
import { LOCALES, locale, t } from '@/lib/i18n'
import { withLoader } from '@/lib/loader'
import { PERIODS } from '@/lib/periods'
import { theme, toggleTheme } from '@/lib/theme'

const router = useRouter()

/* Default badge per category: one row open at a time, saved per row. */
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

function signOut() {
  return withLoader(async () => {
    await logout()
    await router.push({ name: 'landing' })
  })
}
</script>

<template>
  <div class="page narrow">
    <h1 class="page-title">{{ $t('settings.title') }}</h1>

    <section class="section">
      <header class="section-head">
        <h2 class="section-title">{{ $t('settings.badges') }}</h2>
        <p>{{ $t('settings.badgesLead') }}</p>
      </header>

      <ul class="rows">
        <li v-for="period in PERIODS" :key="period" class="row" :class="{ open: openPeriod === period }">
          <button type="button" class="row-head" :aria-expanded="openPeriod === period" @click="toggle(period)">
            <GoalBadge
              :title="$t(`periods.${period}`)"
              v-bind="openPeriod === period ? draft : categoryStyle(period)"
            />
            <ChevronDownIcon class="chevron" aria-hidden="true" />
          </button>

          <form v-if="openPeriod === period" class="row-body" @submit.prevent="save(period)">
            <BadgePicker v-model:color="draft.color" v-model:icon="draft.icon" />
            <p v-if="error" class="error" role="alert"><InformationCircleIcon aria-hidden="true" />{{ error }}</p>
            <button type="submit" class="btn btn-primary btn-lg" :disabled="saving">
              {{ saving ? $t('goal.saving') : $t('goal.save') }}
            </button>
          </form>
        </li>
      </ul>
    </section>

    <section class="section">
      <header class="section-head">
        <h2 class="section-title">{{ $t('settings.appearance') }}</h2>
      </header>
      <div class="pref">
        <span class="form-label">{{ $t('settings.design') }}</span>
        <DesignSwitch />
      </div>
      <div class="pref">
        <span class="form-label">{{ $t('settings.theme') }}</span>
        <div class="segmented" role="group" :aria-label="$t('settings.theme')">
          <button type="button" :aria-pressed="theme === 'light'" @click="setTheme('light')">{{ $t('common.themeLight') }}</button>
          <button type="button" :aria-pressed="theme === 'dark'" @click="setTheme('dark')">{{ $t('common.themeDark') }}</button>
        </div>
      </div>
      <div class="pref">
        <span class="form-label">{{ $t('common.language') }}</span>
        <div class="segmented" role="group" :aria-label="$t('common.language')">
          <button v-for="code in LOCALES" :key="code" type="button" :aria-pressed="locale === code" @click="changeLocale(code)">
            {{ $t(`settings.languages.${code}`) }}
          </button>
        </div>
      </div>
    </section>

    <section class="section">
      <header class="section-head">
        <h2 class="section-title">{{ $t('common.account') }}</h2>
      </header>
      <div class="account">
        <div>
          <p class="name">{{ user?.name }}</p>
          <p class="email">{{ user?.email }}</p>
        </div>
        <button type="button" class="btn btn-ghost btn-lg" @click="signOut">{{ $t('nav.logout') }}</button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.section {
  display: grid;
  gap: 12px;
}

.section-head p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.85rem;
}

.rows {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.row {
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
  transition: border-color 0.2s;
}

.row.open {
  border-color: var(--accent);
}

.row-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  min-height: 60px;
  padding: 0 16px;
  border: 0;
  background: transparent;
  color: var(--fg);
  cursor: pointer;
}

.row-head:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: -2px;
  border-radius: 18px;
}

.chevron {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  color: var(--muted);
  transition: transform 0.2s;
}

.open .chevron {
  transform: rotate(180deg);
}

.row-body {
  display: grid;
  gap: 18px;
  padding: 4px 16px 16px;
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
  color: var(--accent);
}

.pref {
  display: grid;
  gap: 8px;
}

.account {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
}

.account p {
  margin: 0;
}

.name {
  font-weight: 800;
}

.email {
  color: var(--muted);
  font-size: 0.85rem;
}

</style>
