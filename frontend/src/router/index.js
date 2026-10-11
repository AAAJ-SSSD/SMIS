import { createRouter, createWebHistory } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { resolveNavigation } from './guard'

export const routes = [
  { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { guestOnly: true } },
  { path: '/break-glass', name: 'break-glass', component: () => import('@/pages/BreakGlassPage.vue'), meta: { guestOnly: true } },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue') },
      { path: 'users', name: 'users', component: () => import('@/pages/users/UsersListPage.vue'), meta: { permission: 'manage-users' } },
      { path: 'users/new', name: 'users-new', component: () => import('@/pages/users/UserFormPage.vue'), meta: { permission: 'manage-users' } },
      { path: 'users/import', name: 'users-import', component: () => import('@/pages/users/UserImportPage.vue'), meta: { permission: 'import-users' } },
      { path: 'users/:id/edit', name: 'users-edit', component: () => import('@/pages/users/UserFormPage.vue'), meta: { permission: 'manage-users' }, props: true },
      { path: 'audit-log', name: 'audit-log', component: () => import('@/pages/AuditLogPage.vue'), meta: { permission: 'view-audit-log' } },
      { path: '403', name: 'forbidden', component: () => import('@/pages/ForbiddenPage.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') },
]

export function makeRouter(history = createWebHistory()) {
  const router = createRouter({ history, routes })

  router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.loaded) await auth.fetchUser()

    return resolveNavigation(to, auth)
  })

  return router
}
