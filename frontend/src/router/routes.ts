import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', redirect: '/agenda' },
      { path: 'agenda', component: () => import('@/pages/AgendaPage.vue') },
      { path: 'sessions', component: () => import('@/pages/SessionsPage.vue') },
      { path: 'sessions/:id', component: () => import('@/pages/SessionDetailPage.vue') },
      { path: 'live', component: () => import('@/pages/LivePage.vue') },
      {
        path: 'admin/users',
        component: () => import('@/pages/AdminUsersPage.vue'),
        meta: { requiresAdmin: true },
      },
      {
        path: 'admin/sync',
        component: () => import('@/pages/AdminSyncPage.vue'),
        meta: { requiresAdmin: true },
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/GuestLayout.vue'),
    children: [
      { path: 'login', component: () => import('@/pages/LoginPage.vue') },
      { path: 'login/code', component: () => import('@/pages/LoginCodePage.vue') },
    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
];

export default routes;
