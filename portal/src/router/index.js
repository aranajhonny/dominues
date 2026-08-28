import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
  { path: '/register', component: () => import('../views/RegisterView.vue'), meta: { guest: true } },
  { path: '/dashboard', component: () => import('../views/DashboardView.vue'), meta: { auth: true } },
  { path: '/deposits', component: () => import('../views/DepositsView.vue'), meta: { auth: true } },
  { path: '/withdrawals', component: () => import('../views/WithdrawalsView.vue'), meta: { auth: true } },
  { path: '/kyc', component: () => import('../views/KycView.vue'), meta: { auth: true } },
  { path: '/transactions', component: () => import('../views/TransactionsView.vue'), meta: { auth: true } },
  { path: '/profile', component: () => import('../views/ProfileView.vue'), meta: { auth: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to) => {
  const token = localStorage.getItem('dominues_token')
  if (to.meta.auth && !token) return '/login'
  if (to.meta.guest && token) return '/dashboard'
})

export default router