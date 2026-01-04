/**
 * =============================================================================
 * Al-Sabiqoon - Vue Router Configuration
 * =============================================================================
 * Defines application routes with authentication guards.
 *
 * Route metadata:
 *   - requiresAuth: Route requires authenticated user
 *   - requiresGuest: Route is only for unauthenticated users
 * =============================================================================
 */

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// ---------------------------------------------------------------------------
// Route Definitions
// ---------------------------------------------------------------------------

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // -------------------------------------------------------------------------
    // Public Routes (Guest Only)
    // -------------------------------------------------------------------------
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { requiresGuest: true },
    },

    // -------------------------------------------------------------------------
    // Protected Routes (Authenticated Only)
    // -------------------------------------------------------------------------
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
      meta: { requiresAuth: true },
    },

    // -------------------------------------------------------------------------
    // Catch-all redirect
    // -------------------------------------------------------------------------
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

// ---------------------------------------------------------------------------
// Navigation Guards
// ---------------------------------------------------------------------------

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()

  // Initialize auth state if not already done
  if (!authStore.initialized) {
    await authStore.initialize()
  }

  const isAuthenticated = authStore.isAuthenticated
  const requiresAuth = to.meta.requiresAuth
  const requiresGuest = to.meta.requiresGuest

  // Route requires authentication but user is not logged in
  if (requiresAuth && !isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Route is for guests only but user is logged in
  if (requiresGuest && isAuthenticated) {
    return next({ name: 'home' })
  }

  // Allow navigation
  next()
})

export default router
