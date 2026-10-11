import { defineStore } from 'pinia'
import { API_URL, csrfCookie, http } from '@/api/http'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loaded: false,
    options: { google_enabled: false, dev_login_enabled: false },
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
    permissions: (state) => state.user?.permissions ?? [],
    roles: (state) => state.user?.roles ?? [],
  },

  actions: {
    can(permission) {
      return this.permissions.includes(permission)
    },

    async fetchOptions() {
      const { data } = await http.get('/auth/config')
      this.options = data
      return data
    },

    /** Load the current session's user once; null when signed out. */
    async fetchUser() {
      try {
        const { data } = await http.get('/api/me', { skipAuthRedirect: true })
        this.user = data.data
      } catch (error) {
        if (error.response?.status !== 401) throw error
        this.user = null
      } finally {
        this.loaded = true
      }
      return this.user
    },

    googleRedirectUrl() {
      return `${API_URL}/auth/google/redirect`
    },

    async fetchDevUsers() {
      const { data } = await http.get('/auth/dev/users')
      return data.data
    },

    async devLogin(ulid) {
      await csrfCookie()
      await http.post('/auth/dev/login', { ulid })
      return this.fetchUser()
    },

    async breakGlassLogin(payload) {
      await csrfCookie()
      await http.post('/auth/break-glass', payload, { skipAuthRedirect: true })
      return this.fetchUser()
    },

    async logout() {
      try {
        await http.post('/auth/logout', null, { skipAuthRedirect: true })
      } finally {
        this.user = null
      }
    },

    async setLocale(locale) {
      if (!this.user) return
      const { data } = await http.patch('/api/me/locale', { locale })
      this.user = data.data
    },

    clear() {
      this.user = null
    },
  },
})
