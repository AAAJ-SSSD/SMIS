import { describe, expect, it } from 'vitest'
import { buildMenu } from '@/navigation'

const names = (menu) => menu.map((item) => item.to.name)

describe('sidebar menu', () => {
  it('shows only the dashboard to a user with no permissions (e.g. teacher)', () => {
    expect(names(buildMenu([]))).toEqual(['dashboard'])
  })

  it('shows admin items only for the permissions the user has', () => {
    expect(names(buildMenu(['manage-users']))).toEqual(['dashboard', 'users'])
    expect(names(buildMenu(['view-audit-log']))).toEqual(['dashboard', 'audit-log'])
  })

  it('shows everything to a user with all permissions', () => {
    const all = ['manage-users', 'manage-roles', 'manage-grading', 'view-audit-log', 'import-users']
    expect(names(buildMenu(all))).toEqual(['dashboard', 'users', 'users-import', 'audit-log'])
  })
})
