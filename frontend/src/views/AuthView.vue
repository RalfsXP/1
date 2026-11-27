<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import AppLogo from '@/components/AppLogo.vue'
import PaperScene from '@/components/PaperScene.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { login, register } from '@/lib/auth'
import { t } from '@/lib/i18n'
import { withLoader } from '@/lib/loader'

// /login and /register share this view (same viewKey): the page stays put and only the form swaps.
const route = useRoute()
const router = useRouter()

const isRegister = computed(() => route.meta.mode === 'register')

// Shared between both forms so a typed email survives switching modes.
const form = reactive({ name: '', email: '', password: '', passwordConfirmation: '' })
const showPassword = ref(false)
const submitting = ref(false)
const errors = ref({})
const formError = ref('')

watch(isRegister, () => {
  errors.value = {}
  formError.value = ''
})

// Password strength (sign up only): 0–5 points, recomputed on every keystroke.
const strength = computed(() => {
  const pw = form.password
  if (!pw) return 0
  // Under 8 characters is always weak (and rejected by the server).
  if (pw.length < 8) return 1
  let score = 1
  if (pw.length >= 12) score++
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++
  if (/\d/.test(pw)) score++
  if (/[^A-Za-z0-9]/.test(pw)) score++
  return score
})
const strengthLabel = computed(() => t('auth.strengthLevels')[strength.value])

async function submit() {
  if (submitting.value) return
  submitting.value = true
  errors.value = {}
  formError.value = ''

  try {
    if (isRegister.value) await register(form)
    else await login(form)
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

  // Only a successful sign-in / sign-up gets the spinning-logo loader on the way home.
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
      <!-- The logo top left, which also leads back home. -->
      <RouterLink to="/" class="home" :aria-label="$t('auth.back')"><AppLogo /></RouterLink>
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <!-- Decorative side (desktop): the landing page's floating paper scene on a lavender panel. -->
    <section class="art" aria-hidden="true">
      <div class="art-panel"><PaperScene class="scene" /></div>
    </section>

    <section class="form-side">
      <div class="form-wrap">
        <Transition name="form-swap" mode="out-in">
          <form :key="isRegister ? 'register' : 'login'" class="form" novalidate @submit.prevent="submit">
            <h1>{{ isRegister ? $t('auth.registerTitle') : $t('auth.loginTitle') }}</h1>
            <p class="lead">{{ isRegister ? $t('auth.registerLead') : $t('auth.loginLead') }}</p>

            <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>

            <!-- Fields + submit on one white card. -->
            <div class="form-card">
              <div v-if="isRegister" class="field">
                <label for="name">{{ $t('auth.name') }}</label>
                <input
                  id="name"
                  v-model.trim="form.name"
                  type="text"
                  autocomplete="name"
                  required
                  :aria-invalid="!!errors.name"
                  :aria-describedby="errors.name ? 'name-error' : undefined"
                />
                <p v-if="errors.name" id="name-error" class="field-error">
                  <InformationCircleIcon aria-hidden="true" />
                  {{ errors.name }}
                </p>
              </div>

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
                    :autocomplete="isRegister ? 'new-password' : 'current-password'"
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
                <div v-if="isRegister" class="strength">
                  <span class="strength-label">{{ $t('auth.strength') }}</span>
                  <div
                    class="strength-track"
                    role="meter"
                    :aria-label="$t('auth.strengthAria')"
                    aria-valuemin="0"
                    aria-valuemax="5"
                    :aria-valuenow="strength"
                    :aria-valuetext="strengthLabel"
                  >
                    <div class="strength-fill" :style="{ transform: `translateX(${strength * 20 - 100}%)` }" />
                  </div>
                </div>
              </div>

              <div v-if="isRegister" class="field">
                <label for="password-confirmation">{{ $t('auth.confirmPassword') }}</label>
                <input
                  id="password-confirmation"
                  v-model="form.passwordConfirmation"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                />
              </div>

              <button type="submit" class="btn btn-primary btn-lg submit" :disabled="submitting">
                <span v-if="submitting" class="btn-spinner" aria-hidden="true" />
                {{
                  submitting
                    ? isRegister ? $t('auth.submittingRegister') : $t('auth.submittingLogin')
                    : isRegister ? $t('auth.submitRegister') : $t('auth.submitLogin')
                }}
              </button>
            </div>

            <p class="switch">
              <template v-if="isRegister">
                {{ $t('auth.haveAccount') }}
                <RouterLink :to="{ name: 'login' }">{{ $t('auth.switchToLogin') }}</RouterLink>
              </template>
              <template v-else>
                {{ $t('auth.noAccount') }}
                <RouterLink :to="{ name: 'register' }">{{ $t('auth.switchToRegister') }}</RouterLink>
              </template>
            </p>
          </form>
        </Transition>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Desktop: the paper scene on the left, the form on the right. */
.auth-page {
  position: relative;
  display: grid;
  grid-template-columns: 1fr 1fr;
  height: 100vh;
  height: var(--app-h);
  overflow: hidden;
}

.art {
  display: grid;
  min-height: 0;
  padding: 92px 0 24px 24px;
}

.art-panel {
  display: grid;
  place-items: center;
  min-height: 0;
  padding: 40px;
  border-radius: 40px;
  background: color-mix(in srgb, var(--lav) var(--wash), var(--bg));
  animation: rise 0.6s var(--ease) backwards;
}

.scene {
  max-width: 480px;
  max-height: 510px;
}

.form-side {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 82px 40px 24px;
}

/* Mark (home) on the left, language + theme on the right. */
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
  border-radius: 12px;
  color: var(--fg);
  font-size: 1.4rem;
  text-decoration: none;
  transition: transform 0.25s var(--spring);
}

.home:active {
  transform: scale(0.95) rotate(-2deg);
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
  font-family: var(--font-display);
  font-size: 2.8rem;
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.02;
}

.lead {
  margin: 8px 0 22px;
  color: var(--muted);
  font-size: 0.95rem;
  font-weight: 500;
}

.form-card {
  padding: 22px;
  border-radius: 30px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
}

.field {
  margin-bottom: 16px;
}

label {
  display: block;
  margin-bottom: 8px;
  color: var(--muted);
  font-size: 0.82rem;
  font-weight: 700;
}

input {
  width: 100%;
  height: 54px;
  padding: 0 16px;
  border: 1.5px solid transparent;
  border-radius: 18px;
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
  border-color: var(--fg);
  background: var(--surface);
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

@media (hover: hover) {
  .reveal:hover {
    color: var(--fg);
  }
}

.reveal:focus-visible {
  outline: 2px solid var(--link);
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
  color: var(--flag-important);
  stroke-width: 2;
}

.form-error {
  margin: -6px 0 16px;
  padding: 10px 14px;
  border: 0;
  border-radius: 16px;
  background: color-mix(in srgb, var(--rose) var(--wash-strong), var(--surface));
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

/* Strength meter (sign up): muted label + accent bar that fills as the password improves. */
.strength {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
}

.strength-label {
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.strength-track {
  flex: 1;
  height: 6px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--surface-2);
}

.strength-fill {
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: var(--accent);
  transition: transform 0.4s var(--spring);
}

/* "New to Merkis? Sign up" under the card. */
.switch {
  margin: 20px 0 0;
  color: var(--muted);
  font-size: 0.88rem;
  font-weight: 500;
  text-align: center;
}

.switch a {
  color: var(--link);
  font-weight: 700;
  text-decoration: none;
}

@media (hover: hover) {
  .switch a:hover {
    text-decoration: underline;
  }
}

/* Log in <-> Sign up: the form cross-fades. */
.form-swap-enter-active {
  transition: opacity 0.3s ease, transform 0.45s var(--spring);
}

.form-swap-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.form-swap-enter-from {
  opacity: 0;
  transform: translateY(14px) scale(0.98);
}

.form-swap-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ---------- Narrow screens: header + form only ---------- */
@media (max-width: 860px) {
  .auth-page {
    display: flex;
    flex-direction: column;
    height: auto;
    min-height: var(--app-h);
    overflow: visible;
  }

  /* The scene would push the form off screen. */
  .art {
    display: none;
  }

  .home {
    font-size: 1.25rem;
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
    font-size: 2.3rem;
  }
}
</style>
