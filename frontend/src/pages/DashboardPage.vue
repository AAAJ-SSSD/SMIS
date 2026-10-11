<script setup>
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <h1 class="text-h5 mb-4">{{ $t('dashboard.welcome', { name: auth.user?.name }) }}</h1>

  <div class="mb-4">
    <v-chip v-for="role in auth.roles" :key="role" class="me-2" color="primary" variant="tonal">
      {{ $t(`roles.${role}`) }}
    </v-chip>
  </div>

  <v-card variant="outlined">
    <v-card-text class="d-flex align-center">
      <v-icon icon="mdi-hammer-wrench" class="me-3" />
      {{ $t('dashboard.placeholder') }}
    </v-card-text>
  </v-card>
</template>
