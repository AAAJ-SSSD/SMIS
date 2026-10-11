import { createI18n } from 'vue-i18n'
import ar from './ar'
import en from './en'

export const SUPPORTED_LOCALES = ['ar', 'en']
export const RTL_LOCALES = ['ar']
const STORAGE_KEY = 'locale' // a UI preference only — never auth data

function initialLocale() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (SUPPORTED_LOCALES.includes(saved)) return saved
  } catch {
    // storage unavailable: fall back to the default
  }
  return 'ar'
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: 'en',
  messages: { ar, en },
})

export function isRtl(locale) {
  return RTL_LOCALES.includes(locale)
}

/**
 * Switch the UI language: messages, <html lang/dir> and (if passed) Vuetify.
 */
export function applyLocale(locale, vuetify) {
  if (!SUPPORTED_LOCALES.includes(locale)) return

  i18n.global.locale.value = locale
  document.documentElement.setAttribute('lang', locale)
  document.documentElement.setAttribute('dir', isRtl(locale) ? 'rtl' : 'ltr')

  if (vuetify) vuetify.locale.current.value = locale

  try {
    localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    // ignore
  }
}
