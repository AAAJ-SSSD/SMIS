<script setup>
import DevModeBanner from '@/components/DevModeBanner.vue'
</script>

<template>
  <v-app>
    <DevModeBanner />
    <router-view />
  </v-app>
</template>
