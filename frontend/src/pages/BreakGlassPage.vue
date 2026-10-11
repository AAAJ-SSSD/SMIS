<script setup>
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

const form = reactive({ email: '', password: '', code: '' })
const busy = ref(false)
const error = ref(null)

async function submit() {
  busy.value = true
  error.value = null
  try {
    await auth.breakGlassLogin({ ...form })
    router.push({ name: 'dashboard' })
  } catch (e) {
    const status = e.response?.status
    error.value = status === 423 ? t('breakGlass.locked')
      : status === 422 ? t('breakGlass.invalid')
        : e.response?.data?.message ?? t('errors.generic')
  } finally {
    form.password = ''
    form.code = ''
    busy.value = false
  }
}
</script>

<template>
  <v-main class="d-flex align-center justify-center bg-grey-lighten-4">
    <v-card width="420" max-width="94vw" class="pa-6" elevation="2">
      <h1 class="text-h6 mb-2"><v-icon icon="mdi-shield-key" class="me-2" />{{ $t('breakGlass.title') }}</h1>
      <v-alert type="warning" variant="tonal" density="compact" class="mb-4">{{ $t('breakGlass.warning') }}</v-alert>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

      <v-form @submit.prevent="submit">
        <v-text-field v-model="form.email" :label="$t('breakGlass.email')" type="email" autocomplete="username" dir="ltr" required />
        <v-text-field v-model="form.password" :label="$t('breakGlass.password')" type="password" autocomplete="current-password" dir="ltr" required />
        <v-text-field
          v-model="form.code"
          :label="$t('breakGlass.code')"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="6"
          dir="ltr"
          required
        />
        <v-btn type="submit" color="primary" block :loading="busy">{{ $t('breakGlass.submit') }}</v-btn>
      </v-form>
    </v-card>
  </v-main>
</template>
