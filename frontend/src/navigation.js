/**
 * Sidebar entries. `permission` hides an item the user cannot use; the server
 * still enforces every permission on its own — hiding is not security.
 */
export const MENU = [
  { key: 'nav.dashboard', icon: 'mdi-view-dashboard-outline', to: { name: 'dashboard' } },
  { key: 'nav.users', icon: 'mdi-account-multiple-outline', to: { name: 'users' }, permission: 'manage-users' },
  { key: 'nav.import', icon: 'mdi-file-upload-outline', to: { name: 'users-import' }, permission: 'import-users' },
  { key: 'nav.auditLog', icon: 'mdi-shield-search', to: { name: 'audit-log' }, permission: 'view-audit-log' },
]

export function buildMenu(permissions = [], menu = MENU) {
  return menu.filter((item) => !item.permission || permissions.includes(item.permission))
}
