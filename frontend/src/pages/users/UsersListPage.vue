<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usersApi } from '@/api/users'

const { t, locale } = useI18n()

const ROLES = ['super-admin', 'it', 'admin', 'hod', 'teacher', 'student', 'parent', 'guard', 'driver', 'helper']

const filters = reactive({ search: '', role: null, status: null })
const options = reactive({ page: 1, itemsPerPage: 25 })
const items = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)
const confirming = ref(null)

const headers = computed(() => [
  { title: t('users.name'), key: 'name', sortable: false },
  { title: t('users.email'), key: 'email', sortable: false },
  { title: t('users.roles'), key: 'roles', sortable: false },
  { title: t('users.status'), key: 'is_active', sortable: false },
  { title: t('users.lastLogin'), key: 'last_login_at', sortable: false },
  { title: t('users.actions'), key: 'actions', sortable: false, align: 'end' },
])

const roleOptions = computed(() => ROLES.map((r) => ({ title: t(`roles.${r}`), value: r })))
const statusOptions = computed(() => [
  { title: t('users.active'), value: 'active' },
  { title: t('users.inactive'), value: 'inactive' },
])

async function load() {
  loading.value = true
  error.value = null
  try {
    const params = { page: options.page, per_page: options.itemsPerPage }
    if (filters.search) params.search = filters.search
    if (filters.role) params.role = filters.role
    if (filters.status) params.status = filters.status

    const result = await usersApi.list(params)
    items.value = result.data
    total.value = result.meta.total
  } catch {
    error.value = t('errors.generic')
  } finally {
    loading.value = false
  }
}

let debounce
watch(() => ({ ...filters }), () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    options.page = 1
    load()
  }, 300)
})

function onOptions({ page, itemsPerPage }) {
  options.page = page
  options.itemsPerPage = itemsPerPage
  load()
}

async function toggle(user) {
  const updated = user.is_active ? await usersApi.deactivate(user.id) : await usersApi.activate(user.id)
  Object.assign(user, updated)
  confirming.value = null
}

function formatDate(value) {
  return value ? new Date(value).toLocaleString(locale.value === 'ar' ? 'ar' : 'en-GB') : t('users.never')
}
</script>

<template>
  <div class="d-flex flex-wrap align-center mb-4 ga-2">
    <h1 class="text-h5 me-auto">{{ $t('users.title') }}</h1>
    <v-btn :to="{ name: 'users-import' }" variant="outlined" prepend-icon="mdi-file-upload-outline">{{ $t('nav.import') }}</v-btn>
    <v-btn :to="{ name: 'users-new' }" color="primary" prepend-icon="mdi-account-plus">{{ $t('users.new') }}</v-btn>
  </div>

  <v-row dense>
    <v-col cols="12" md="6">
      <v-text-field v-model="filters.search" :label="$t('users.search')" prepend-inner-icon="mdi-magnify" clearable hide-details />
    </v-col>
    <v-col cols="6" md="3">
      <v-select v-model="filters.role" :items="roleOptions" :label="$t('users.role')" clearable hide-details />
    </v-col>
    <v-col cols="6" md="3">
      <v-select v-model="filters.status" :items="statusOptions" :label="$t('users.status')" clearable hide-details />
    </v-col>
  </v-row>

  <v-alert v-if="error" type="error" variant="tonal" class="my-4">{{ error }}</v-alert>

  <v-data-table-server
    class="mt-4"
    :headers="headers"
    :items="items"
    :items-length="total"
    :loading="loading"
    :items-per-page="options.itemsPerPage"
    :items-per-page-options="[10, 25, 50, 100]"
    item-value="id"
    @update:options="onOptions"
  >
    <template #item.email="{ item }"><span dir="ltr">{{ item.email }}</span></template>
    <template #item.roles="{ item }">
      <v-chip v-for="role in item.roles" :key="role" size="small" class="me-1">{{ $t(`roles.${role}`) }}</v-chip>
    </template>
    <template #item.is_active="{ item }">
      <v-chip :color="item.is_active ? 'success' : 'error'" size="small" variant="tonal">
        {{ item.is_active ? $t('users.active') : $t('users.inactive') }}
      </v-chip>
    </template>
    <template #item.last_login_at="{ item }">{{ formatDate(item.last_login_at) }}</template>
    <template #item.actions="{ item }">
      <v-btn
        v-if="item.can?.update"
        icon="mdi-pencil"
        variant="text"
        size="small"
        :to="{ name: 'users-edit', params: { id: item.id } }"
        :aria-label="$t('users.edit')"
      />
      <v-btn
        v-if="item.can?.toggle_active"
        :icon="item.is_active ? 'mdi-account-off-outline' : 'mdi-account-check-outline'"
        :color="item.is_active ? 'error' : 'success'"
        variant="text"
        size="small"
        :aria-label="item.is_active ? $t('users.deactivate') : $t('users.activate')"
        @click="item.is_active ? (confirming = item) : toggle(item)"
      />
    </template>
  </v-data-table-server>

  <v-dialog :model-value="confirming !== null" max-width="420" @update:model-value="confirming = null">
    <v-card v-if="confirming">
      <v-card-title>{{ $t('users.deactivate') }}</v-card-title>
      <v-card-text>{{ $t('users.deactivateConfirm', { name: confirming.name }) }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="confirming = null">{{ $t('users.cancel') }}</v-btn>
        <v-btn color="error" variant="flat" @click="toggle(confirming)">{{ $t('users.deactivate') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
