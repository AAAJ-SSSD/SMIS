/**
 * Pure navigation decision, kept separate from the router for testing.
 *
 * @param {import('vue-router').RouteLocationNormalized} to
 * @param {{ isAuthenticated: boolean, permissions: string[] }} auth
 * @returns {true | object} true to continue, or a route to redirect to
 */
export function resolveNavigation(to, auth) {
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }

  if (to.meta.permission && !auth.permissions.includes(to.meta.permission)) {
    return { name: 'forbidden' }
  }

  return true
}

/** Only allow in-app redirects after login (no open redirects). */
export function safeRedirect(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : '/'
}
