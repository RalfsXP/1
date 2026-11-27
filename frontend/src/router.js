import { createRouter, createWebHistory } from 'vue-router'
import { fetchUser } from './lib/auth'
import LandingView from './views/LandingView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'landing', component: LandingView },
    // Same view key for both: the page stays mounted and only the form swaps.
    { path: '/login', name: 'login', component: () => import('./views/AuthView.vue'), meta: { mode: 'login', guestOnly: true, viewKey: 'auth' } },
    // Music prototype: lyric snippet → songs + previews. Public, not in any menu.
    { path: '/music', name: 'music', component: () => import('./views/MusicView.vue') },
    { path: '/register', name: 'register', component: () => import('./views/AuthView.vue'), meta: { mode: 'register', guestOnly: true, viewKey: 'auth' } },
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
        { path: '/guide', name: 'guide', component: () => import('./views/GuideView.vue') },
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

// iOS scrolls the whole page to show a focused field (login keyboard) and leaves it there. The app shell
// never scrolls the window (only <main> scrolls), so start every page from the top — a leftover offset
// hid the greeting under the top bar and let Safari collapse its toolbar mid-page.
router.afterEach(() => {
  if (window.scrollY) window.scrollTo(0, 0)
})

export default router
