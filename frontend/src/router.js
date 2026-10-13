import { createRouter, createWebHistory } from 'vue-router'
import { fetchUser } from './lib/auth'
import LandingView from './views/LandingView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'landing', component: LandingView },
    { path: '/login', name: 'login', component: () => import('./views/AuthView.vue'), meta: { guestOnly: true } },
    // No sign-up (personal app): old links land on the login page.
    { path: '/register', redirect: '/login' },
    {
      // Signed-in app: side nav + one shared shell (viewKey keeps it mounted between pages).
      path: '/home',
      component: () => import('./components/AppShell.vue'),
      meta: { requiresAuth: true, viewKey: 'app' },
      children: [
        { path: '', name: 'home', component: () => import('./views/DashboardView.vue') },
        { path: '/calendar', name: 'calendar', component: () => import('./views/CalendarView.vue') },
        { path: '/events', name: 'events', component: () => import('./views/EventsView.vue') },
        {
          path: '/goals/:period(daily|weekly|monthly|yearly|custom)',
          name: 'goals',
          component: () => import('./views/GoalsView.vue'),
        },
        { path: '/whiteboards', name: 'whiteboards', component: () => import('./views/WhiteboardsView.vue') },
        // The editor chunk carries Fabric.js; nothing else loads it.
        { path: '/whiteboards/:id(\\d+)', name: 'whiteboard', component: () => import('./views/WhiteboardView.vue') },
        { path: '/settings', name: 'settings', component: () => import('./views/SettingsView.vue') },
        // Every signed-in user; admins (ADMIN_EMAILS) also see internals and can run migrations.
        { path: '/health', name: 'health', component: () => import('./views/HealthView.vue') },
      ],
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
