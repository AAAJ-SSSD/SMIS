<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

onMounted(() => {
  auth.fetchOptions().catch(() => {})
})
</script>

<template>
  <v-system-bar
    v-if="auth.options.dev_login_enabled"
    color="warning"
    height="32"
    class="justify-center font-weight-bold"
    role="alert"
    data-test="dev-banner"
  >
    <v-icon icon="mdi-alert" class="me-2" />
    {{ $t('app.devMode') }}
  </v-system-bar>
</template>
