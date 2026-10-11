<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usersApi } from '@/api/users'

const { t, locale } = useI18n()

const filters = reactive({ log_name: null, event: '', from: '', to: '' })
const options = reactive({ page: 1, itemsPerPage: 25 })
const items = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)

const headers = computed(() => [
  { title: t('audit.when'), key: 'created_at', sortable: false },
  { title: t('audit.who'), key: 'causer', sortable: false },
  { title: t('audit.event'), key: 'event', sortable: false },
  { title: t('audit.subject'), key: 'subject', sortable: false },
  { title: '', key: 'data-table-expand' },
])

const logOptions = [
  { title: 'auth', value: 'auth' },
  { title: 'users', value: 'users' },
]

async function load() {
  loading.value = true
  error.value = null
  try {
    const params = { page: options.page, per_page: options.itemsPerPage }
    for (const [key, value] of Object.entries(filters)) if (value) params[key] = value
    const result = await usersApi.auditLogs(params)
    items.value = result.data
    total.value = result.meta.total
  } catch (e) {
    error.value = e.response?.data?.message ?? t('errors.generic')
  } finally {
    loading.value = false
  }
}

watch(() => ({ ...filters }), () => {
  options.page = 1
  load()
})

function onOptions({ page, itemsPerPage }) {
  options.page = page
  options.itemsPerPage = itemsPerPage
  load()
}

function formatDate(value) {
  return new Date(value).toLocaleString(locale.value === 'ar' ? 'ar' : 'en-GB')
}

function changedKeys(item) {
  return [...new Set([...Object.keys(item.old ?? {}), ...Object.keys(item.new ?? {})])]
}

function show(value) {
  return value === null || value === undefined ? '—' : typeof value === 'object' ? JSON.stringify(value) : String(value)
}
</script>

<template>
  <h1 class="text-h5 mb-4">{{ $t('audit.title') }}</h1>

  <v-row dense>
    <v-col cols="6" md="3"><v-select v-model="filters.log_name" :items="logOptions" :label="$t('audit.log')" clearable hide-details /></v-col>
    <v-col cols="6" md="3"><v-text-field v-model.lazy="filters.event" :label="$t('audit.event')" clearable hide-details dir="ltr" /></v-col>
    <v-col cols="6" md="3"><v-text-field v-model="filters.from" :label="$t('audit.from')" type="date" hide-details /></v-col>
    <v-col cols="6" md="3"><v-text-field v-model="filters.to" :label="$t('audit.to')" type="date" hide-details /></v-col>
  </v-row>

  <v-alert v-if="error" type="error" variant="tonal" class="my-4">{{ error }}</v-alert>

  <v-data-table-server
    class="mt-4"
    :headers="headers"
    :items="items"
    :items-length="total"
    :loading="loading"
    :items-per-page="options.itemsPerPage"
    item-value="id"
    show-expand
    @update:options="onOptions"
  >
    <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
    <template #item.causer="{ item }">
      <span v-if="item.causer">{{ item.causer.name }} <small class="text-medium-emphasis" dir="ltr">{{ item.causer.email }}</small></span>
      <span v-else class="text-medium-emphasis">{{ item.context?.attempted_email ?? $t('audit.system') }}</span>
    </template>
    <template #item.event="{ item }">
      <v-chip size="small" :color="item.event === 'login_failed' ? 'error' : undefined" variant="tonal">
        {{ item.log_name }} · {{ item.event }}
      </v-chip>
      <small v-if="item.context?.reason" class="ms-1 text-error">{{ item.context.reason }}</small>
    </template>
    <template #item.subject="{ item }">{{ item.subject?.name ?? '—' }}</template>

    <template #expanded-row="{ columns, item }">
      <tr>
        <td :colspan="columns.length" class="py-3">
          <v-table v-if="changedKeys(item).length" density="compact" class="mb-2">
            <thead>
              <tr><th /><th>{{ $t('audit.old') }}</th><th>{{ $t('audit.new') }}</th></tr>
            </thead>
            <tbody>
              <tr v-for="key in changedKeys(item)" :key="key">
                <td><code>{{ key }}</code></td>
                <td dir="ltr">{{ show(item.old?.[key]) }}</td>
                <td dir="ltr">{{ show(item.new?.[key]) }}</td>
              </tr>
            </tbody>
          </v-table>
          <pre v-if="item.context" class="text-caption" dir="ltr">{{ JSON.stringify(item.context, null, 2) }}</pre>
        </td>
      </tr>
    </template>
  </v-data-table-server>
</template>
