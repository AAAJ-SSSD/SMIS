import axios from 'axios'
import { i18n } from '@/i18n'

/**
 * Sanctum SPA authentication: the session lives in an httpOnly cookie set by
 * the backend. Nothing auth-related is ever stored in localStorage.
 * axios copies the XSRF-TOKEN cookie into the X-XSRF-TOKEN header (CSRF).
 */
export const API_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000').replace(/\/$/, '')

export const http = axios.create({
  baseURL: API_URL,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

http.interceptors.request.use((config) => {
  config.headers['Accept-Language'] = i18n.global.locale.value
  return config
})

let onUnauthenticated = () => {}

/** Registered by the auth store so a 401 anywhere sends the user to /login. */
export function setUnauthenticatedHandler(handler) {
  onUnauthenticated = handler
}

export function csrfCookie() {
  return http.get('/sanctum/csrf-cookie')
}

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error

    // CSRF token expired: fetch a fresh one and retry once.
    if (response?.status === 419 && !config._retried) {
      config._retried = true
      await csrfCookie()
      return http(config)
    }

    if (response?.status === 401 && !config.skipAuthRedirect) {
      onUnauthenticated(response.data?.code)
    }

    return Promise.reject(error)
  },
)
