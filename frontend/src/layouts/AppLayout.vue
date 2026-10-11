<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay, useLocale } from 'vuetify'
import { LOGO_URL } from '@/branding'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import { applyLocale } from '@/i18n'
import { buildMenu } from '@/navigation'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { locale } = useI18n()
const vuetifyLocale = useLocale()
const { mdAndUp } = useDisplay()

const drawer = ref(mdAndUp.value)
const menu = computed(() => buildMenu(auth.permissions))

onMounted(() => {
  // Follow the language saved on the account.
  if (auth.user?.locale && auth.user.locale !== locale.value) {
    applyLocale(auth.user.locale, { locale: vuetifyLocale })
  }
})

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-navigation-drawer v-model="drawer" :permanent="mdAndUp" data-test="sidebar">
    <v-list-item class="py-4" :title="$t('app.name')">
      <template #prepend>
        <img :src="LOGO_URL" :alt="$t('app.logoAlt')" width="44" height="36" class="me-3" data-test="sidebar-logo" />
      </template>
    </v-list-item>
    <v-divider />
    <v-list nav density="comfortable">
      <v-list-item
        v-for="item in menu"
        :key="item.key"
        :to="item.to"
        :prepend-icon="item.icon"
        :title="$t(item.key)"
        exact
      />
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat border>
    <v-app-bar-nav-icon v-if="!mdAndUp" @click="drawer = !drawer" />
    <v-app-bar-title>{{ $t('app.name') }}</v-app-bar-title>

    <template #append>
      <LanguageSwitch class="me-3" />
      <v-menu>
        <template #activator="{ props }">
          <v-btn v-bind="props" variant="text" class="text-none">
            <v-avatar size="28" class="me-2" color="primary">
              <v-img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" referrerpolicy="no-referrer" />
              <span v-else>{{ auth.user?.name?.charAt(0) }}</span>
            </v-avatar>
            <span class="d-none d-sm-inline">{{ auth.user?.name }}</span>
          </v-btn>
        </template>
        <v-list density="compact">
          <v-list-item :subtitle="auth.user?.email" :title="auth.user?.name" />
          <v-divider />
          <v-list-item prepend-icon="mdi-logout" :title="$t('app.logout')" @click="logout" />
        </v-list>
      </v-menu>
    </template>
  </v-app-bar>

  <v-main>
    <v-container fluid class="pa-4 pa-md-6">
      <router-view />
    </v-container>
  </v-main>
</template>
