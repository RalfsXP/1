import { createRouter, createWebHistory } from 'vue-router'
import { fetchUser } from './lib/auth'
import LandingView from './views/LandingView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'landing', component: LandingView },
    {
      path: '/login',
      name: 'login',
      component: () => import('./views/AuthView.vue'),
      // Same view key for both: the page stays mounted and its panels swap sides.
      meta: { mode: 'login', guestOnly: true, viewKey: 'auth' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('./views/AuthView.vue'),
      meta: { mode: 'register', guestOnly: true, viewKey: 'auth' },
    },
    {
      path: '/home',
      name: 'home',
      component: () => import('./views/HomeView.vue'),
      meta: { requiresAuth: true },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  if (!to.meta.requiresAuth && !to.meta.guestOnly) return true

  let current = null
  try {
    current = await fetchUser()
  } catch {
    // API unreachable: treat as signed out rather than blocking navigation.
  }

  if (to.meta.requiresAuth && !current) return { name: 'login' }
  if (to.meta.guestOnly && current) return { name: 'home' }
  return true
})

export default router
