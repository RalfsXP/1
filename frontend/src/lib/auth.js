import { ref } from 'vue'
import api from './api'
import { setLocale } from './i18n'

/** The signed-in user, `null` when signed out, `undefined` until first checked. */
export const user = ref(undefined)

// Laravel Sanctum issues the XSRF-TOKEN cookie here; axios echoes it back on writes.
const csrf = () => api.get('/sanctum/csrf-cookie', { baseURL: '/' })

// A signed-in user's saved language wins over this device's last choice.
function applyUser(data) {
  user.value = data
  if (data?.locale) setLocale(data.locale)
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

export async function logout() {
  await api.post('/logout')
  user.value = null
}
