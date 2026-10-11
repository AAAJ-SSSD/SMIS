import { describe, expect, it } from 'vitest'
import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { flush, mountInApp, plugins } from './helpers'

const stub = { template: '<div />' }
const routes = [
  { path: '/', name: 'dashboard', component: stub },
  { path: '/users', name: 'users', component: stub },
  { path: '/users/import', name: 'users-import', component: stub },
  { path: '/audit-log', name: 'audit-log', component: stub },
  { path: '/login', name: 'login', component: stub },
]

async function sidebarFor(permissions) {
  const { list, router } = plugins({ routes })
  useAuthStore().user = { name: 'U', email: 'u@example.test', locale: 'ar', roles: [], permissions }
  await router.push('/')
  const wrapper = mountInApp(AppLayout, list)
  await flush()
  return wrapper.find('[data-test="sidebar"]').text()
}

describe('AppLayout sidebar', () => {
  it('is built from the user permissions — teacher sees no admin items', async () => {
    const text = await sidebarFor([])

    expect(text).toContain('لوحة التحكم')
    expect(text).not.toContain('المستخدمون')
    expect(text).not.toContain('سجل التدقيق')
  })

  it('shows admin items to a user with admin permissions', async () => {
    const text = await sidebarFor(['manage-users', 'import-users', 'view-audit-log'])

    expect(text).toContain('المستخدمون')
    expect(text).toContain('استيراد المستخدمين')
    expect(text).toContain('سجل التدقيق')
  })
})
