import axios from 'axios'
import { locale } from './i18n'

/**
 * Shared HTTP client for the Laravel backend.
 *
 * In development the base URL is a relative `/api`, which Vite proxies to the
 * Laravel dev server (see vite.config.js). That keeps requests same-origin, so
 * there is no CORS preflight and session cookies work as-is. In production set
 * VITE_API_BASE_URL to the deployed API origin, e.g. https://api.example.com/api
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Laravel answers (validation/auth messages) in the language on screen.
api.interceptors.request.use((config) => {
  config.headers['Accept-Language'] = locale.value
  return config
})

/**
 * The session ran out while the app was open (common for a Home Screen app left open for days): any
 * signed-in request answered 401/419 tells the app, which goes back to the login page instead of showing
 * errors. (lib/auth.js listens; kept as an event so this module doesn't import the router.)
 */
api.interceptors.response.use(undefined, (error) => {
  const status = error.response?.status
  const url = error.config?.url ?? ''
  if ((status === 401 || status === 419) && !['/user', '/login', '/register', '/logout'].includes(url)) {
    window.dispatchEvent(new Event('merkis:signed-out'))
  }
  return Promise.reject(error)
})

export default api
