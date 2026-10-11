<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { fieldErrors, usersApi } from '@/api/users'

const { t } = useI18n()

const file = ref(null)
const preview = ref(null)
const result = ref(null)
const busy = ref(false)
const error = ref(null)

const headers = computed(() => [
  { title: t('import.line'), key: 'line', width: 80 },
  { title: t('users.name'), key: 'data.name' },
  { title: t('users.email'), key: 'data.email' },
  { title: t('users.roles'), key: 'data.roles' },
  { title: t('import.errors'), key: 'errors' },
])

function selectedFile() {
  return Array.isArray(file.value) ? file.value[0] : file.value
}

async function runPreview() {
  if (!selectedFile()) return
  busy.value = true
  error.value = null
  result.value = null
  try {
    preview.value = await usersApi.importPreview(selectedFile())
  } catch (e) {
    preview.value = null
    error.value = fieldErrors(e).file ?? t('errors.generic')
  } finally {
    busy.value = false
  }
}

async function commit() {
  busy.value = true
  error.value = null
  try {
    result.value = await usersApi.importCommit(preview.value.token)
    preview.value = { ...preview.value, rows: result.value.rows, summary: result.value.summary, token: null }
  } catch (e) {
    error.value = fieldErrors(e).token ?? t('errors.generic')
  } finally {
    busy.value = false
  }
}

async function downloadTemplate() {
  const blob = await usersApi.importTemplate()
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), { href: url, download: 'users-template.csv' })
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="d-flex align-center mb-2">
    <h1 class="text-h5 me-auto">{{ $t('import.title') }}</h1>
    <v-btn variant="text" prepend-icon="mdi-download" @click="downloadTemplate">{{ $t('import.template') }}</v-btn>
  </div>
  <p class="text-medium-emphasis mb-4">{{ $t('import.help') }}</p>

  <div class="d-flex flex-wrap ga-2 align-start">
    <v-file-input
      v-model="file"
      :label="$t('import.file')"
      accept=".csv,text/csv"
      prepend-icon="mdi-file-delimited-outline"
      style="min-width: 280px; max-width: 480px"
      @update:model-value="preview = null; result = null"
    />
    <v-btn color="primary" variant="outlined" :disabled="!file" :loading="busy && !preview" @click="runPreview">
      {{ $t('import.preview') }}
    </v-btn>
  </div>

  <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
  <v-alert v-if="result" type="success" variant="tonal" class="mb-4">{{ $t('import.done', { count: result.created }) }}</v-alert>

  <template v-if="preview">
    <div class="d-flex align-center flex-wrap ga-2 mb-2">
      <span>{{ $t('import.summary', preview.summary) }}</span>
      <v-spacer />
      <v-btn
        v-if="preview.token"
        color="primary"
        :disabled="preview.summary.valid === 0"
        :loading="busy"
        @click="commit"
      >
        {{ $t('import.commit', { count: preview.summary.valid }) }}
      </v-btn>
    </div>

    <v-data-table :headers="headers" :items="preview.rows" item-value="line" density="compact" :items-per-page="50">
      <template #item.data.email="{ item }"><span dir="ltr">{{ item.data.email }}</span></template>
      <template #item.data.roles="{ item }">
        <v-chip v-for="role in item.data.roles" :key="role" size="x-small" class="me-1">{{ role }}</v-chip>
      </template>
      <template #item.errors="{ item }">
        <v-chip v-if="!item.errors.length" color="success" size="small" variant="tonal">{{ $t('import.ok') }}</v-chip>
        <ul v-else class="text-error text-body-2 ps-4">
          <li v-for="message in item.errors" :key="message">{{ message }}</li>
        </ul>
      </template>
    </v-data-table>
  </template>
</template>
