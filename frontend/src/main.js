import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { setUnauthenticatedHandler } from './api/http'
import { applyLocale, i18n } from './i18n'
import { makeVuetify } from './plugins/vuetify'
import { makeRouter } from './router'
import { useAuthStore } from './stores/auth'

const vuetify = makeVuetify(i18n.global.locale.value)
const router = makeRouter()
const app = createApp(App)

app.use(createPinia()).use(i18n).use(vuetify).use(router)

applyLocale(i18n.global.locale.value, vuetify)

// Session ended server-side (logout elsewhere, deactivation, expiry).
setUnauthenticatedHandler((code) => {
  useAuthStore().clear()
  router.push({ name: 'login', query: { error: code === 'account_inactive' ? code : 'session_expired' } })
})

app.mount('#app')
