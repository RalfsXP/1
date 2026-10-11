import { ref } from 'vue'
import api from './api'
import { design, setDesign } from './design'
import { locale, setLocale } from './i18n'

/** The signed-in user, `null` when signed out, `undefined` until first checked. */
export const user = ref(undefined)

// Laravel Sanctum issues the XSRF-TOKEN cookie here; axios echoes it back on writes.
const csrf = () => api.get('/sanctum/csrf-cookie', { baseURL: '/' })

// A signed-in user's saved language wins over this device's last choice.
function applyUser(data) {
  user.value = data
  if (data?.locale) setLocale(data.locale)
  if (data?.design) setDesign(data.design)
  return data
}

/** Ask the backend who is signed in (once per page load unless forced). */
export async function fetchUser({ force = false } = {}) {
  if (user.value !== undefined && !force) return user.value
  try {
    const { data } = await api.get('/user')
    applyUser(data)
  } catch (e) {
    if (e.response?.status !== 401) throw e
    user.value = null
  }
  return user.value
}

export async function login({ email, password, remember = true }) {
  await csrf()
  const { data } = await api.post('/login', { email, password, remember })
  return applyUser(data)
}

export async function register({ name, email, password, passwordConfirmation }) {
  await csrf()
  const { data } = await api.post('/register', {
    name,
    email,
    password,
    password_confirmation: passwordConfirmation,
    locale: locale.value,
    design: design.value,
  })
  return applyUser(data)
}

/** Switch language now and, when signed in, save it to the account. */
export async function changeLocale(next) {
  setLocale(next)
  if (!user.value || user.value.locale === next) return
  try {
    const { data } = await api.patch('/user/locale', { locale: next })
    user.value = data
  } catch {
    // Saving failed (e.g. offline); the language is still switched on this device.
  }
}

/** Switch design now and, when signed in, save it to the account. */
export async function changeDesign(next) {
  setDesign(next)
  if (!user.value || user.value.design === next) return
  try {
    const { data } = await api.patch('/user/design', { design: next })
    user.value = data
  } catch {
    // Saving failed (e.g. offline); the design is still switched on this device.
  }
}

export async function logout() {
  await api.post('/logout')
  user.value = null
}
