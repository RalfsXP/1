<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { login, register } from '@/lib/auth'
import { t } from '@/lib/i18n'
import { withLoader } from '@/lib/loader'

const route = useRoute()
const router = useRouter()

// /login and /register share this view; the panels swap sides between them.
const mode = computed(() => (route.meta.mode === 'register' ? 'register' : 'login'))
const isRegister = computed(() => mode.value === 'register')

const SWAP_MS = 850
const swapping = ref(false)
let swapTimer

watch(mode, () => {
  errors.value = {}
  formError.value = ''
  swapping.value = true
  clearTimeout(swapTimer)
  swapTimer = setTimeout(() => (swapping.value = false), SWAP_MS)
})

// Shared between both forms so a typed email survives switching modes.
const form = reactive({ name: '', email: '', password: '', passwordConfirmation: '' })
const showPassword = ref(false)

// Password strength (sign up only): 0–5 points, recomputed on every keystroke.
const strength = computed(() => {
  const pw = form.password
  if (!pw) return 0
  // Under 8 characters is always weak (and rejected by the server).
  if (pw.length < 8) return 1
  let score = 0
  if (pw.length >= 8) score++
  if (pw.length >= 12) score++
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++
  if (/\d/.test(pw)) score++
  if (/[^A-Za-z0-9]/.test(pw)) score++
  return score
})
const strengthLabel = computed(() => t('auth.strengthLevels')[strength.value])
const submitting = ref(false)
const errors = ref({})
const formError = ref('')

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

  // Only a successful sign-in gets the spinning-logo loader on the way home.
  try {
    await withLoader(() => router.push({ name: 'home' }), 1400)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="auth-page" :class="[mode, { swapping }]">
    <!-- Pinned to the page, not a panel, so it stays put while the panels swap. -->
    <header class="form-top">
      <RouterLink to="/" class="back">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        {{ $t('auth.back') }}
      </RouterLink>
      <div class="controls">
        <LanguageSwitch />
        <ThemeToggle />
      </div>
    </header>

    <!-- Decorative side -->
    <section class="art" aria-hidden="true">
      <div class="art-logo"><AppLogo /></div>
    </section>

    <!-- Form side -->
    <section class="form-side">

      <div class="form-wrap">
        <Transition name="form-swap" mode="out-in">
          <form :key="mode" class="form" novalidate @submit.prevent="submit">
            <h1>{{ isRegister ? $t('auth.registerTitle') : $t('auth.loginTitle') }}</h1>
            <p class="lead">
              {{ isRegister ? $t('auth.registerLead') : $t('auth.loginLead') }}
            </p>

            <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>

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
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></svg>
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
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></svg>
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
                  <svg v-if="!showPassword" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                  <svg v-else viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 3l18 18M10.6 5.6A10 10 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.8M6.5 7.4A17 17 0 0 0 2.5 12S6 18.5 12 18.5a9.6 9.6 0 0 0 4.4-1" />
                  </svg>
                </button>
              </div>
              <p v-if="errors.password" id="password-error" class="field-error">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></svg>
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
                  <div class="strength-fill" :style="{ width: strength * 20 + '%' }" />
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
.auth-page {
  --swap-ease: cubic-bezier(0.76, 0, 0.24, 1);
  --field-bg: color-mix(in srgb, var(--fg) 4%, transparent);

  position: relative;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  background: var(--bg);
}

/* ---------- Split layout + swap ---------- */
.art,
.form-side {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 50%;
  transition: transform 0.85s var(--swap-ease);
  will-change: transform;
}

/* Login: art left, form right. Register: they trade places. */
.login .art {
  transform: translateX(0);
}

.login .form-side {
  transform: translateX(100%);
}

.register .art {
  transform: translateX(100%);
}

.register .form-side {
  transform: translateX(0);
}

.swapping .art,
.swapping .form-side {
  animation: swap-blur 0.85s var(--swap-ease);
}

@keyframes swap-blur {
  0% {
    filter: blur(0);
  }

  45% {
    filter: blur(14px);
    opacity: 0.85;
  }

  100% {
    filter: blur(0);
  }
}

/* ---------- Art side: the logo, nothing else ---------- */
.art {
  display: grid;
  place-items: center;
  padding: 32px;
}

.art-logo {
  width: min(360px, 64%);
  aspect-ratio: 998 / 828;
}

/* ---------- Form side ---------- */
.form-side {
  display: flex;
  flex-direction: column;
  padding: 82px 40px 24px;
}

/* Back on the left, language + theme on the right, regardless of panel order. */
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

.back {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
  transition: color 0.2s;
}

.back:hover {
  color: var(--seagrass);
}

.back svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.back:focus-visible {
  outline: 2px solid var(--seagrass);
  outline-offset: 4px;
  border-radius: 4px;
}

.form-wrap {
  flex: 1;
  display: grid;
  place-items: center;
  overflow-y: auto;
  padding: 24px 0;
}

.form {
  width: min(380px, 100%);
}

h1 {
  margin: 0;
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.15;
}

.lead {
  margin: 8px 0 28px;
  color: var(--muted);
  font-size: 0.92rem;
  font-weight: 600;
}

.field {
  margin-bottom: 16px;
}

label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.78rem;
  font-weight: 700;
}

input {
  width: 100%;
  height: 46px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: var(--field-bg);
  color: var(--fg);
  font: inherit;
  /* 16px: anything smaller makes iOS Safari zoom in on focus. */
  font-size: 1rem;
  font-weight: 600;
  transition: border-color 0.2s, box-shadow 0.2s;
}

input:focus {
  outline: none;
  border-color: var(--seagrass);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--seagrass) 22%, transparent);
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
  color: var(--seagrass);
}

.reveal:focus-visible {
  outline: 2px solid var(--seagrass);
}

.reveal svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
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
  width: 14px;
  height: 14px;
  fill: none;
  stroke: var(--seagrass);
  stroke-width: 2.2;
  stroke-linecap: round;
}

/* Strength meter: muted label + Seagrass bar that fills as the password improves. */
.strength {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}

.strength-label {
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 700;
}

.strength-track {
  flex: 1;
  height: 4px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--line);
}

.strength-fill {
  height: 100%;
  border-radius: inherit;
  background: var(--seagrass);
  transition: width 0.25s ease;
}

.form-error {
  margin: -12px 0 20px;
  padding: 10px 14px;
  border: 1px solid var(--line);
  border-left: 3px solid var(--seagrass);
  border-radius: 12px;
  background: var(--field-bg);
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
  border: 2px solid color-mix(in srgb, var(--black) 25%, transparent);
  border-top-color: var(--black);
  border-radius: 50%;
  animation: btn-spin 0.7s linear infinite;
}

@keyframes btn-spin {
  to {
    transform: rotate(360deg);
  }
}

.switch {
  margin: 20px 0 0;
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 600;
  text-align: center;
}

.switch a {
  color: var(--seagrass);
  font-weight: 800;
  text-decoration: none;
}

.switch a:hover {
  text-decoration: underline;
}

/* Form content cross-fades with a blur while the panels slide. */
.form-swap-enter-active,
.form-swap-leave-active {
  transition: opacity 0.35s ease, filter 0.35s ease, transform 0.35s ease;
}

.form-swap-enter-from,
.form-swap-leave-to {
  opacity: 0;
  filter: blur(8px);
  transform: translateY(8px);
}

/* ---------- Narrow screens: stacked, no side swap ---------- */
@media (max-width: 860px) {
  .auth-page {
    display: flex;
    flex-direction: column;
    height: auto;
    min-height: 100dvh;
    overflow: visible;
  }

  .art,
  .form-side,
  .login .art,
  .login .form-side,
  .register .art,
  .register .form-side {
    position: static;
    width: auto;
    transform: none;
    will-change: auto;
  }

  /* Panels don't trade places on phones; the form content still blurs/fades
     via the form-swap transition. */
  .swapping .art,
  .swapping .form-side {
    animation: none;
  }

  /* Back + theme toggle sit in the top row; the logo below them. */
  .art {
    padding: 72px 16px 0;
  }

  .art-logo {
    width: 150px;
  }

  .form-side {
    flex: 1;
    padding: 16px 16px calc(24px + env(safe-area-inset-bottom));
  }

  .form-top {
    padding: max(16px, env(safe-area-inset-top)) 16px 0;
  }

  .form-wrap {
    place-items: start center;
    padding-top: 28px;
  }

  h1 {
    font-size: 1.55rem;
  }
}
</style>
