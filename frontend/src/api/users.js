import { http } from './http'

export const usersApi = {
  list: (params) => http.get('/api/users', { params }).then((r) => r.data),
  get: (id) => http.get(`/api/users/${encodeURIComponent(id)}`).then((r) => r.data.data),
  create: (payload) => http.post('/api/users', payload).then((r) => r.data.data),
  update: (id, payload) => http.put(`/api/users/${encodeURIComponent(id)}`, payload).then((r) => r.data.data),
  activate: (id) => http.post(`/api/users/${encodeURIComponent(id)}/activate`).then((r) => r.data.data),
  deactivate: (id) => http.post(`/api/users/${encodeURIComponent(id)}/deactivate`).then((r) => r.data.data),
  roles: () => http.get('/api/roles').then((r) => r.data.data),

  importPreview(file) {
    const form = new FormData()
    form.append('file', file)
    return http.post('/api/users/import/preview', form).then((r) => r.data)
  },
  importCommit: (token) => http.post('/api/users/import/commit', { token }).then((r) => r.data),
  importTemplate: () => http.get('/api/users/import/template', { responseType: 'blob' }).then((r) => r.data),

  auditLogs: (params) => http.get('/api/audit-logs', { params }).then((r) => r.data),
}

/** Laravel 422 → { field: 'first message' } for v-text-field error-messages. */
export function fieldErrors(error) {
  const errors = error.response?.status === 422 ? error.response.data.errors ?? {} : {}
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}
