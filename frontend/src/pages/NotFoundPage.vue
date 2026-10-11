<template>
  <v-main class="d-flex align-center justify-center">
    <v-empty-state icon="mdi-map-marker-question-outline" headline="404" :title="$t('errors.notFound')" data-test="not-found">
      <template #actions>
        <v-btn color="primary" :to="{ name: 'dashboard' }">{{ $t('errors.home') }}</v-btn>
      </template>
    </v-empty-state>
  </v-main>
</template>
