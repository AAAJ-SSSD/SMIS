<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { fieldErrors, usersApi } from '@/api/users'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({ id: { type: String, default: null } })

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const form = reactive({ name: '', email: '', phone: '', locale: 'ar', roles: [] })
const roles = ref([])
const errors = ref({})
const saving = ref(false)
const loading = ref(true)
const loadError = ref(null)

const isEdit = computed(() => props.id !== null)
const roleOptions = computed(() => roles.value
  .filter((r) => r.assignable || form.roles.includes(r.name))
  .map((r) => ({ title: t(`roles.${r.name}`), value: r.name, props: { disabled: !r.assignable } })))
const localeOptions = [{ title: 'العربية', value: 'ar' }, { title: 'English', value: 'en' }]
const domain = computed(() => auth.options.school_domain)

onMounted(async () => {
  try {
    roles.value = await usersApi.roles()
    if (isEdit.value) {
      const user = await usersApi.get(props.id)
      Object.assign(form, { name: user.name, email: user.email, phone: user.phone ?? '', locale: user.locale, roles: user.roles })
    }
  } catch (e) {
    loadError.value = e.response?.status === 403 ? t('errors.forbidden') : t('errors.generic')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  errors.value = {}
  const payload = { ...form, phone: form.phone || null }
  try {
    if (isEdit.value) await usersApi.update(props.id, payload)
    else await usersApi.create(payload)
    router.push({ name: 'users' })
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) {
      errors.value = { _form: e.response?.status === 403 ? t('errors.forbidden') : t('errors.generic') }
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <h1 class="text-h5 mb-4">{{ isEdit ? $t('users.edit') : $t('users.new') }}</h1>

  <v-progress-linear v-if="loading" indeterminate />
  <v-alert v-else-if="loadError" type="error" variant="tonal">{{ loadError }}</v-alert>

  <v-card v-else max-width="720" variant="outlined">
    <v-card-text>
      <v-alert v-if="errors._form" type="error" variant="tonal" class="mb-4">{{ errors._form }}</v-alert>
      <v-form @submit.prevent="save">
        <v-text-field v-model="form.name" :label="$t('users.name')" :error-messages="errors.name" required />
        <v-text-field
          v-model="form.email"
          :label="$t('users.email')"
          :error-messages="errors.email"
          :hint="domain ? $t('users.emailHint', { domain }) : ''"
          persistent-hint
          type="email"
          dir="ltr"
          required
          class="mb-2"
        />
        <v-text-field v-model="form.phone" :label="$t('users.phone')" :error-messages="errors.phone" type="tel" dir="ltr" />
        <v-select v-model="form.locale" :items="localeOptions" :label="$t('users.locale')" :error-messages="errors.locale" />
        <v-select
          v-model="form.roles"
          :items="roleOptions"
          :label="$t('users.roles')"
          :error-messages="errors.roles ?? errors['roles.0']"
          multiple
          chips
          closable-chips
        />
        <div class="d-flex ga-2 mt-2">
          <v-btn type="submit" color="primary" :loading="saving">{{ $t('users.save') }}</v-btn>
          <v-btn :to="{ name: 'users' }" variant="text">{{ $t('users.cancel') }}</v-btn>
        </div>
      </v-form>
    </v-card-text>
  </v-card>
</template>
