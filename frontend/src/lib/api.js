import axios from 'axios'

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

export default api
