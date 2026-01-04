/**
 * =============================================================================
 * Al-Sabiqoon - Authentication Store
 * =============================================================================
 * Pinia store for managing authentication state.
 *
 * Features:
 *   - Reactive auth state (user, tokens, loading)
 *   - Login/register/logout actions
 *   - Automatic token refresh integration
 *   - Token restoration on app load
 *   - Computed getters for auth status
 * =============================================================================
 */

import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/api/client'
import { authService } from '@/services/auth.service'
import type { User, LoginCredentials, RegisterData, StoredTokens } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  // ---------------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------------

  /** Current authenticated user */
  const user = ref<User | null>(null)

  /** Current tokens */
  const tokens = ref<StoredTokens | null>(null)

  /** Loading state for async operations */
  const loading = ref(false)

  /** Whether initial auth check has completed */
  const initialized = ref(false)

  /** Error message from last failed operation */
  const error = ref<string | null>(null)

  // ---------------------------------------------------------------------------
  // Getters
  // ---------------------------------------------------------------------------

  /** Check if user is authenticated */
  const isAuthenticated = computed(() => !!tokens.value && !!user.value)

  /** Get user's display name */
  const displayName = computed(() => user.value?.name || 'Guest')

  /** Check if user's email is verified */
  const isEmailVerified = computed(() => !!user.value?.email_verified_at)

  /** Get access token */
  const accessToken = computed(() => tokens.value?.access_token || null)

  // ---------------------------------------------------------------------------
  // Actions
  // ---------------------------------------------------------------------------

  /**
   * Set up the token refresh callback for the API client
   */
  function setupTokenRefresh(): void {
    api.setTokenRefreshCallback(async () => {
      try {
        const response = await authService.refreshToken()
        tokens.value = {
          access_token: response.access_token,
          refresh_token: response.refresh_token,
          expires_at: Date.now() + response.expires_in * 1000,
        }
        return response.access_token
      } catch {
        // Refresh failed, logout
        await logout()
        return null
      }
    })
  }

  /**
   * Initialize auth state from storage (call on app mount)
   */
  async function initialize(): Promise<void> {
    if (initialized.value) return

    loading.value = true
    error.value = null

    try {
      // Set up token refresh callback
      setupTokenRefresh()

      // Restore from storage
      const stored = authService.initializeAuth()
      tokens.value = stored.tokens
      user.value = stored.user

      // Validate token with server if we have one
      if (tokens.value) {
        const validUser = await authService.checkAuth()
        if (validUser) {
          user.value = validUser
          // Update tokens in case they were refreshed
          tokens.value = authService.getTokens()
        } else {
          // Token was invalid
          tokens.value = null
          user.value = null
        }
      }
    } catch {
      // Clear state on error
      tokens.value = null
      user.value = null
    } finally {
      loading.value = false
      initialized.value = true
    }
  }

  /**
   * Login with credentials
   */
  async function login(credentials: LoginCredentials): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await authService.login(credentials)
      user.value = response.user
      tokens.value = {
        access_token: response.access_token,
        refresh_token: response.refresh_token,
        expires_at: Date.now() + response.expires_in * 1000,
      }
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Login failed'
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Register a new user
   */
  async function register(data: RegisterData): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await authService.register(data)
      user.value = response.user
      tokens.value = {
        access_token: response.access_token,
        refresh_token: response.refresh_token,
        expires_at: Date.now() + response.expires_in * 1000,
      }
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Registration failed'
      throw e
    } finally {
      loading.value = false
    }
  }

  /**
   * Logout current user
   */
  async function logout(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      await authService.logout()
    } finally {
      user.value = null
      tokens.value = null
      loading.value = false
    }
  }

  /**
   * Refresh the access token
   */
  async function refreshToken(): Promise<void> {
    try {
      const response = await authService.refreshToken()
      tokens.value = {
        access_token: response.access_token,
        refresh_token: response.refresh_token,
        expires_at: Date.now() + response.expires_in * 1000,
      }
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Token refresh failed'
      await logout()
      throw e
    }
  }

  /**
   * Clear error state
   */
  function clearError(): void {
    error.value = null
  }

  // ---------------------------------------------------------------------------
  // Return
  // ---------------------------------------------------------------------------

  return {
    // State
    user,
    tokens,
    loading,
    initialized,
    error,

    // Getters
    isAuthenticated,
    displayName,
    isEmailVerified,
    accessToken,

    // Actions
    initialize,
    login,
    register,
    logout,
    refreshToken,
    clearError,
  }
})
