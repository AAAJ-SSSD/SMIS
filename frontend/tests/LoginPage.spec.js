import { beforeEach, describe, expect, it, vi } from 'vitest'
import { http } from '@/api/http'
import LoginPage from '@/pages/LoginPage.vue'
import { flush, mountInApp, plugins } from './helpers'

function mockBackend({ google = false, dev = false, users = [] } = {}) {
  vi.spyOn(http, 'get').mockImplementation(async (url) => {
    if (url === '/auth/config') return { data: { google_enabled: google, dev_login_enabled: dev, school_domain: null } }
    if (url === '/auth/dev/users') return { data: { data: users } }
    throw new Error(`unexpected GET ${url}`)
  })
}

async function mountLogin(query = {}) {
  const { list, router } = plugins()
  await router.push({ path: '/login', query })
  const wrapper = mountInApp(LoginPage, list)
  await flush()
  return wrapper
}

describe('LoginPage', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('shows the school logo', async () => {
    mockBackend()
    const logo = (await mountLogin()).find('[data-test="login-logo"]')

    expect(logo.attributes('src')).toBe('/logo.png')
    expect(logo.attributes('alt')).toBe('شعار أكاديمية جدة العالمية')
  })

  it('hides the Google button when Google login is off', async () => {
    mockBackend({ google: false })
    const wrapper = await mountLogin()

    expect(wrapper.find('[data-test="google-button"]').exists()).toBe(false)
  })

  it('shows the Google button when Google login is on', async () => {
    mockBackend({ google: true })
    const wrapper = await mountLogin()

    const button = wrapper.find('[data-test="google-button"]')
    expect(button.exists()).toBe(true)
    expect(button.attributes('href')).toBe('http://localhost:8000/auth/google/redirect')
  })

  it('lists the seeded test users in dev mode', async () => {
    mockBackend({ dev: true, users: [{ id: '01J0', name: 'Test Teacher', email: 'teacher@example.test', is_active: true, roles: ['teacher'] }] })
    const wrapper = await mountLogin()

    expect(wrapper.find('[data-test="dev-users"]').text()).toContain('teacher@example.test')
  })

  it('does not show the dev user list when dev mode is off', async () => {
    mockBackend({ google: true, dev: false })
    const wrapper = await mountLogin()

    expect(wrapper.find('[data-test="dev-users"]').exists()).toBe(false)
  })

  it('explains why sign-in was refused', async () => {
    mockBackend({ google: true })
    const wrapper = await mountLogin({ error: 'not_registered' })

    expect(wrapper.find('[data-test="login-error"]').text()).toContain('غير مسجل')
  })

  it('does not render arbitrary error text from the URL', async () => {
    mockBackend({ google: true })
    const wrapper = await mountLogin({ error: '<script>alert(1)</script>' })

    const text = wrapper.find('[data-test="login-error"]').text()
    expect(text).not.toContain('script')
  })
})
