<template>
  <v-empty-state
    icon="mdi-lock-outline"
    headline="403"
    :title="$t('errors.forbidden')"
    data-test="forbidden"
  >
    <template #actions>
      <v-btn color="primary" :to="{ name: 'dashboard' }">{{ $t('errors.home') }}</v-btn>
    </template>
  </v-empty-state>
</template>
