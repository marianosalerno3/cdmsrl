import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/stores/auth'

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
  {
    path: '/register-b2b',
    name: 'register-b2b',
    component: () => import('@/views/RegisterB2BView.vue'),
    meta: { public: true },
  },
  { path: '/dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
  { path: '/catalog', name: 'catalog', component: () => import('@/views/CatalogView.vue') },
  { path: '/product/:id', name: 'product', component: () => import('@/views/ProductDetailView.vue') },
  { path: '/cart', name: 'cart', component: () => import('@/views/CartView.vue') },
  {
    path: '/agent/new-client',
    name: 'agent-new-client',
    component: () => import('@/views/AgentNewClientView.vue'),
  },
  { path: '/sostituzioni', name: 'sostituzioni', component: () => import('@/views/SostituzioniView.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to) => {
  const { isAuthenticated } = useAuth()
  if (!to.meta.public && !isAuthenticated.value) {
    return { name: 'login', query: to.fullPath !== '/dashboard' ? { redirect: to.fullPath } : {} }
  }
  if (to.meta.public && isAuthenticated.value && to.name === 'login') {
    return { name: 'dashboard' }
  }
})

export default router
