<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import { safeRedirect } from '@/router/guard'
import { useAuthStore } from '@/stores/auth'

const KNOWN_ERRORS = [
  'not_registered', 'wrong_domain', 'account_inactive', 'email_not_verified', 'identity_mismatch',
  'invalid_state', 'google_cancelled', 'google_error', 'session_expired',
]

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const devUsers = ref([])
const busy = ref(null)
const failure = ref(null)
const optionsLoaded = ref(false)

const errorMessage = computed(() => {
  const code = failure.value ?? route.query.error
  if (!code) return null
  return KNOWN_ERRORS.includes(code) ? t(`login.errors.${code}`) : t('login.errors.generic')
})

onMounted(async () => {
  try {
    await auth.fetchOptions()
    if (auth.options.dev_login_enabled) devUsers.value = await auth.fetchDevUsers()
  } finally {
    optionsLoaded.value = true
  }
})

async function loginAs(user) {
  busy.value = user.id
  failure.value = null
  try {
    await auth.devLogin(user.id)
    router.push(safeRedirect(route.query.redirect))
  } catch (error) {
    failure.value = error.response?.data?.code ?? 'generic'
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <v-main class="d-flex align-center justify-center bg-grey-lighten-4">
    <v-card width="440" max-width="94vw" class="pa-6" elevation="2">
      <div class="d-flex justify-end mb-2"><LanguageSwitch /></div>

      <div class="text-center mb-6">
        <v-icon icon="mdi-school" size="48" color="primary" />
        <h1 class="text-h5 mt-2">{{ $t('app.name') }}</h1>
        <p class="text-medium-emphasis">{{ $t('login.subtitle') }}</p>
      </div>

      <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" data-test="login-error">
        {{ errorMessage }}
      </v-alert>

      <v-btn
        v-if="auth.options.google_enabled"
        :href="auth.googleRedirectUrl()"
        block
        size="large"
        color="primary"
        prepend-icon="mdi-google"
        data-test="google-button"
      >
        {{ $t('login.google') }}
      </v-btn>

      <template v-if="auth.options.dev_login_enabled">
        <v-divider class="my-5" />
        <div class="text-subtitle-2 mb-2">{{ $t('login.devUsers') }}</div>
        <v-list density="compact" border rounded data-test="dev-users">
          <v-list-item
            v-for="user in devUsers"
            :key="user.id"
            :title="user.name"
            :subtitle="user.email"
            :disabled="busy !== null"
            @click="loginAs(user)"
          >
            <template #append>
              <v-progress-circular v-if="busy === user.id" indeterminate size="18" width="2" />
              <v-chip v-else-if="!user.is_active" size="x-small" color="error">{{ $t('users.inactive') }}</v-chip>
              <v-chip v-else size="x-small">{{ $t(`roles.${user.roles?.[0]}`) }}</v-chip>
            </template>
          </v-list-item>
        </v-list>
      </template>

      <v-alert
        v-if="optionsLoaded && !auth.options.google_enabled && !auth.options.dev_login_enabled"
        type="info"
        variant="tonal"
      >
        {{ $t('login.noMethods') }}
      </v-alert>
    </v-card>
  </v-main>
</template>
