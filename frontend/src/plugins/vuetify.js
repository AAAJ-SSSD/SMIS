import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import { ar, en } from 'vuetify/locale'

export function makeVuetify(locale = 'ar') {
  return createVuetify({
    locale: {
      locale,
      fallback: 'en',
      messages: { ar, en },
      rtl: { ar: true, en: false },
    },
    theme: {
      defaultTheme: 'school',
      themes: {
        school: {
          dark: false,
          colors: {
            primary: '#1E5A8A',
            secondary: '#4A6572',
            error: '#B3261E',
            warning: '#B26A00',
            success: '#2E7D32',
          },
        },
      },
    },
    defaults: {
      VTextField: { variant: 'outlined', density: 'comfortable' },
      VSelect: { variant: 'outlined', density: 'comfortable' },
      VFileInput: { variant: 'outlined', density: 'comfortable' },
    },
  })
}
