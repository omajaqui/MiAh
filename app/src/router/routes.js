const routes = [
  {
    path: '/login',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { guest: true },
    children: [
      { path: '', name: 'login', component: () => import('@/pages/LoginPage.vue') },
    ],
  },
  {
    path: '/admin',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true, role: 'admin' },
    children: [
      { path: '', name: 'admin-dashboard', component: () => import('@/pages/admin/DashboardPage.vue') },
      { path: 'personas', name: 'admin-people', component: () => import('@/pages/admin/PeoplePage.vue') },
      { path: 'ciclos', name: 'admin-cycles', component: () => import('@/pages/admin/CyclesPage.vue') },
      { path: 'ciclos/:id', name: 'admin-cycle-detail', component: () => import('@/pages/admin/CycleDetailPage.vue') },
      { path: 'pagos-reportados', name: 'admin-payment-reports', component: () => import('@/pages/admin/PaymentReportsPage.vue') },
    ],
  },
  {
    path: '/socio',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true, role: 'socio' },
    children: [
      { path: '', name: 'member-dashboard', component: () => import('@/pages/member/DashboardPage.vue') },
      { path: 'ciclos', name: 'member-cycles', component: () => import('@/pages/member/CyclesPage.vue') },
      { path: 'ciclos/:id', name: 'member-cycle-detail', component: () => import('@/pages/member/CycleDetailPage.vue') },
    ],
  },
  {
    path: '/',
    redirect: '/login',
  },
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
]

export default routes
