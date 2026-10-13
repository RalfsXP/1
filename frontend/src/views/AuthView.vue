<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import AppLogo from '@/components/AppLogo.vue'
import GlassScene from '@/components/GlassScene.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { login } from '@/lib/auth'
import { t } from '@/lib/i18n'
import { withLoader } from '@/lib/loader'

// Log in (there is no sign-up: the app is personal).
const router = useRouter()

const form = reactive({ email: '', password: '' })
const showPassword = ref(false)
const submitting = ref(false)
const errors = ref({})
const formError = ref('')

async function submit() {
  if (submitting.value) return
  submitting.value = true
  errors.value = {}
  formError.value = ''

  try {
    await login(form)
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(e.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
      )
    } else if (e.response?.status === 429) {
      formError.value = t('auth.errorThrottle')
    } else {
      formError.value = t('auth.errorGeneric')
    }
    submitting.value = false
    return
  }

  // Only a successful sign-in gets the spinning-logo loader on the way home.
  try {
    await withLoader(() => router.push({ name: 'home' }), 1400)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <header class="form-top">
      <!-- Same corner as the landing page: the wordmark, which also leads back home. -->
      <RouterLink to="/" class="home" :aria-label="$t('auth.back')"><AppLogo /></RouterLink>
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <!-- Decorative side (desktop): the landing page's floating glass cards. -->
    <section class="art" aria-hidden="true">
      <GlassScene class="scene" />
    </section>

    <section class="form-side">
      <div class="form-wrap">
        <form class="form" novalidate @submit.prevent="submit">
          <h1>{{ $t('auth.loginTitle') }}</h1>
          <p class="lead">{{ $t('auth.loginLead') }}</p>

          <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>

          <!-- Fields + submit on one glass card. -->
          <div class="form-card">
            <div class="field">
              <label for="email">{{ $t('auth.email') }}</label>
              <input
                id="email"
                v-model.trim="form.email"
                type="email"
                autocomplete="email"
                inputmode="email"
                required
                :aria-invalid="!!errors.email"
                :aria-describedby="errors.email ? 'email-error' : undefined"
              />
              <p v-if="errors.email" id="email-error" class="field-error">
                <InformationCircleIcon aria-hidden="true" />
                {{ errors.email }}
              </p>
            </div>

            <div class="field">
              <label for="password">{{ $t('auth.password') }}</label>
              <div class="password">
                <input
                  id="password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  :aria-invalid="!!errors.password"
                  :aria-describedby="errors.password ? 'password-error' : undefined"
                />
                <button
                  type="button"
                  class="reveal"
                  :aria-label="showPassword ? $t('auth.hidePassword') : $t('auth.showPassword')"
                  :aria-pressed="showPassword"
                  @click="showPassword = !showPassword"
                >
                  <EyeIcon v-if="!showPassword" aria-hidden="true" />
                  <EyeSlashIcon v-else aria-hidden="true" />
                </button>
              </div>
              <p v-if="errors.password" id="password-error" class="field-error">
                <InformationCircleIcon aria-hidden="true" />
                {{ errors.password }}
              </p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg submit" :disabled="submitting">
              <span v-if="submitting" class="btn-spinner" aria-hidden="true" />
              {{ submitting ? $t('auth.submittingLogin') : $t('auth.submitLogin') }}
            </button>
          </div>
        </form>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Desktop: the glass scene on the left, the form on the right. */
.auth-page {
  position: relative;
  display: grid;
  grid-template-columns: 1fr 1fr;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}

.art {
  display: grid;
  place-items: center;
  min-height: 0;
  padding: 96px 40px 40px;
}

.scene {
  max-width: 460px;
  max-height: 488px;
}

.form-side {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 82px 40px 24px;
}

/* Wordmark (home) on the left, language + theme on the right. */
.form-top {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 40px;
  pointer-events: none;
}

.form-top > * {
  pointer-events: auto;
}

.controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.home {
  display: block;
  width: 132px;
  height: 40px;
  border-radius: 6px;
}

.home:focus-visible {
  outline-offset: 6px;
}

.form-wrap {
  flex: 1;
  display: grid;
  place-items: center;
  overflow-y: auto;
  padding: 24px 0;
}

.form {
  width: min(400px, 100%);
}

h1 {
  margin: 0;
  font-size: 2.3rem;
  font-weight: 800;
  letter-spacing: -0.035em;
  line-height: 1.1;
}

.lead {
  margin: 8px 0 22px;
  color: var(--muted);
  font-size: 0.95rem;
  font-weight: 500;
}

.form-card {
  padding: 20px;
  border: 1px solid var(--glass-edge);
  border-radius: 28px;
  background: var(--surface);
  box-shadow: var(--glass-shadow);
}

.field {
  margin-bottom: 16px;
}

label {
  display: block;
  margin-bottom: 8px;
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
}

input {
  width: 100%;
  height: 52px;
  padding: 0 16px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--field);
  color: var(--fg);
  font: inherit;
  /* 16px: anything smaller makes iOS Safari zoom in on focus. */
  font-size: 1rem;
  font-weight: 500;
  transition: border-color 0.2s, box-shadow 0.2s;
}

input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 1px var(--accent);
}

input[aria-invalid='true'] {
  border-color: var(--fg);
}

.password {
  position: relative;
}

.password input {
  padding-right: 48px;
}

.reveal {
  position: absolute;
  top: 50%;
  right: 6px;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transform: translateY(-50%);
}

.reveal:hover {
  color: var(--accent);
}

.reveal:focus-visible {
  outline: 2px solid var(--accent);
}

.reveal svg {
  width: 18px;
  height: 18px;
}

.field-error {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 6px 0 0;
  color: var(--fg);
  font-size: 0.78rem;
  font-weight: 700;
}

.field-error svg {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  color: var(--accent);
  stroke-width: 2;
}

.form-error {
  margin: -6px 0 16px;
  padding: 10px 14px;
  border: 1px solid var(--line);
  border-left: 3px solid var(--accent);
  border-radius: 12px;
  background: var(--field);
  color: var(--fg);
  font-size: 0.85rem;
  font-weight: 700;
}

.submit {
  width: 100%;
  margin-top: 8px;
}

/* Small inline spinner while the request is in flight. */
.btn-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid color-mix(in srgb, var(--on-accent) 25%, transparent);
  border-top-color: var(--on-accent);
  border-radius: 50%;
  animation: btn-spin 0.7s linear infinite;
}

@keyframes btn-spin {
  to {
    transform: rotate(360deg);
  }
}

/* ---------- Narrow screens: header + form only ---------- */
@media (max-width: 860px) {
  .auth-page {
    display: flex;
    flex-direction: column;
    height: auto;
    min-height: 100dvh;
    overflow: visible;
  }

  /* The scene would push the form off screen. */
  .art {
    display: none;
  }

  .home {
    width: 112px;
    height: 34px;
  }

  .form-side {
    flex: 1;
    padding: calc(66px + var(--top-inset)) 16px calc(24px + env(safe-area-inset-bottom));
  }

  .form-top {
    padding: max(16px, var(--top-inset)) 16px 0;
  }

  /* The page itself scrolls here; a scrolling wrapper would clip the card's shadow at the sides. */
  .form-wrap {
    place-items: start center;
    padding-top: 20px;
    overflow: visible;
  }

  h1 {
    font-size: 2.1rem;
  }
}
</style>
