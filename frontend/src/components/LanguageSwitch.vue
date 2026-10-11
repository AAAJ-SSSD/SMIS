<script setup>
import { useI18n } from 'vue-i18n'
import { useLocale } from 'vuetify'
import { applyLocale } from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const { locale, t } = useI18n()
const vuetifyLocale = useLocale()
const auth = useAuthStore()

const languages = [
  { code: 'ar', label: 'العربية' },
  { code: 'en', label: 'English' },
]

async function switchTo(code) {
  if (code === locale.value) return
  applyLocale(code, { locale: vuetifyLocale })
  // Remember the choice on the account too, so server messages follow it.
  await auth.setLocale(code).catch(() => {})
}
</script>

<template>
  <v-btn-toggle
    :model-value="locale"
    density="compact"
    variant="outlined"
    divided
    mandatory
    :aria-label="t('app.language')"
    @update:model-value="switchTo"
  >
    <v-btn v-for="lang in languages" :key="lang.code" :value="lang.code" :lang="lang.code" size="small">
      {{ lang.label }}
    </v-btn>
  </v-btn-toggle>
</template>
