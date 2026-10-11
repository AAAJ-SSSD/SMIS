import { describe, expect, it } from 'vitest'
import { resolveNavigation, safeRedirect } from '@/router/guard'

const route = (meta, fullPath = '/x') => ({ meta, fullPath })
const guest = { isAuthenticated: false, permissions: [] }
const teacher = { isAuthenticated: true, permissions: [] }
const admin = { isAuthenticated: true, permissions: ['manage-users'] }

describe('router guard', () => {
  it('sends guests to login, remembering where they were going', () => {
    expect(resolveNavigation(route({ requiresAuth: true }, '/users'), guest))
      .toEqual({ name: 'login', query: { redirect: '/users' } })
  })

  it('shows 403 when the user lacks the page permission', () => {
    expect(resolveNavigation(route({ requiresAuth: true, permission: 'manage-users' }), teacher))
      .toEqual({ name: 'forbidden' })
  })

  it('lets users with the permission through', () => {
    expect(resolveNavigation(route({ requiresAuth: true, permission: 'manage-users' }), admin)).toBe(true)
  })

  it('keeps signed-in users away from the login page', () => {
    expect(resolveNavigation(route({ guestOnly: true }), teacher)).toEqual({ name: 'dashboard' })
  })

  it('only follows in-app redirects', () => {
    expect(safeRedirect('/users')).toBe('/users')
    expect(safeRedirect('//evil.example')).toBe('/')
    expect(safeRedirect('https://evil.example')).toBe('/')
    expect(safeRedirect(undefined)).toBe('/')
  })
})
