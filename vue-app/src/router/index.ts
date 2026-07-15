import { createRouter, createWebHistory } from '@ionic/vue-router'
import type { RouteRecordRaw } from 'vue-router'
import TabsPage from '@/views/TabsPage.vue'
import { isConfigured, loadSettings } from '@/services/storage'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/tabs/inbox',
  },
  {
    path: '/login',
    component: () => import('@/views/LoginPage.vue'),
    meta: { guest: true },
  },
  {
    path: '/register',
    component: () => import('@/views/RegisterPage.vue'),
    meta: { guest: true },
  },
  {
    path: '/tabs/',
    component: TabsPage,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/tabs/inbox',
      },
      {
        path: 'inbox',
        component: () => import('@/views/InboxPage.vue'),
      },
      {
        path: 'inbox/:id',
        component: () => import('@/views/NotificationDetailPage.vue'),
      },
      {
        path: 'approvals',
        component: () => import('@/views/ApprovalsPage.vue'),
      },
      {
        path: 'settings',
        component: () => import('@/views/SettingsPage.vue'),
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const settings = await loadSettings()
  const authenticated = isConfigured(settings)

  if (to.meta.requiresAuth && !authenticated) {
    return '/login'
  }

  if (to.meta.guest && authenticated) {
    return '/tabs/inbox'
  }

  return true
})

export default router
