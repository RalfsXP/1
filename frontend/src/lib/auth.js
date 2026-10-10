import { ref } from 'vue'
import api from './api'

/** The signed-in user, `null` when signed out, `undefined` until first checked. */
export const user = ref(undefined)

// Laravel Sanctum issues the XSRF-TOKEN cookie here; axios echoes it back on writes.
const csrf = () => api.get('/sanctum/csrf-cookie', { baseURL: '/' })

/** Ask the backend who is signed in (once per page load unless forced). */
export async function fetchUser({ force = false } = {}) {
  if (user.value !== undefined && !force) return user.value
  try {
    const { data } = await api.get('/user')
    user.value = data
  } catch (e) {
    if (e.response?.status !== 401) throw e
    user.value = null
  }
  return user.value
}

export async function login({ email, password, remember = true }) {
  await csrf()
  const { data } = await api.post('/login', { email, password, remember })
  user.value = data
  return data
}

export async function register({ name, email, password, passwordConfirmation }) {
  await csrf()
  const { data } = await api.post('/register', {
    name,
    email,
    password,
    password_confirmation: passwordConfirmation,
  })
  user.value = data
  return data
}

export async function logout() {
  await api.post('/logout')
  user.value = null
}
