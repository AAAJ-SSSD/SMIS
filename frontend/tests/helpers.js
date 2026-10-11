import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { h } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { VApp } from 'vuetify/components'
import { i18n } from '@/i18n'
import { makeVuetify } from '@/plugins/vuetify'

/**
 * Global plugins for mounting a component the way main.js does.
 */
export function plugins({ routes = [{ path: '/:any(.*)*', component: { template: '<div />' } }] } = {}) {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({ history: createMemoryHistory(), routes })

  return { pinia, router, list: [pinia, i18n, makeVuetify(), router] }
}

/** Mount inside <v-app>, which Vuetify layout components require. */
export function mountInApp(component, pluginList) {
  return mount({ render: () => h(VApp, () => h(component)) }, { global: { plugins: pluginList } })
}

export function flush() {
  return new Promise((resolve) => setTimeout(resolve, 0))
}
