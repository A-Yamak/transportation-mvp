/**
 * =============================================================================
 * Al-Sabiqoon - Authentication Service
 * =============================================================================
 * Handles all authentication-related API calls and token management.
 *
 * Features:
 *   - User login and registration
 *   - Access token and refresh token management
 *   - Automatic token refresh before expiration
 *   - Token persistence in localStorage
 *   - Logout with token revocation
 * =============================================================================
 */

import { api, ApiError } from '@/api/client'
import type {
  User,
  LoginCredentials,
  RegisterData,
  AuthResponse,
  TokenResponse,
  StoredTokens,
} from '@/types/auth'

/** Local storage keys */
const TOKENS_KEY = 'auth_tokens'
const USER_KEY = 'auth_user'

/** Buffer time before token expiration to trigger refresh (5 minutes) */
const REFRESH_BUFFER_MS = 5 * 60 * 1000

/**
 * Authentication Service
 */
class AuthService {
  private refreshPromise: Promise<TokenResponse> | null = null

  /**
   * Login with email and password
   */
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/v1/auth/login', credentials)

    // Store tokens and user
    this.setTokens({
      access_token: response.access_token,
      refresh_token: response.refresh_token,
      expires_at: Date.now() + response.expires_in * 1000,
    })
    this.setUser(response.user)

    // Set token in API client
    api.setAuthToken(response.access_token)

    return response
  }

  /**
   * Register a new user
   */
  async register(data: RegisterData): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/v1/auth/register', data)

    // Store tokens and user
    this.setTokens({
      access_token: response.access_token,
      refresh_token: response.refresh_token,
      expires_at: Date.now() + response.expires_in * 1000,
    })
    this.setUser(response.user)

    // Set token in API client
    api.setAuthToken(response.access_token)

    return response
  }

  /**
   * Logout the current user
   */
  async logout(): Promise<void> {
    try {
      await api.post('/v1/auth/logout')
    } catch {
      // Ignore logout errors - we'll clear local state anyway
    } finally {
      this.clearAuth()
    }
  }

  /**
   * Get the current authenticated user from API
   */
  async getUser(): Promise<User> {
    return api.get<User>('/v1/auth/user')
  }

  /**
   * Refresh the access token using the refresh token
   */
  async refreshToken(): Promise<TokenResponse> {
    // If already refreshing, return the existing promise to prevent multiple requests
    if (this.refreshPromise) {
      return this.refreshPromise
    }

    const tokens = this.getTokens()
    if (!tokens?.refresh_token) {
      throw new Error('No refresh token available')
    }

    this.refreshPromise = api
      .post<TokenResponse>('/v1/auth/refresh', {
        refresh_token: tokens.refresh_token,
      })
      .then((response) => {
        // Store new tokens
        this.setTokens({
          access_token: response.access_token,
          refresh_token: response.refresh_token,
          expires_at: Date.now() + response.expires_in * 1000,
        })

        // Update API client
        api.setAuthToken(response.access_token)

        return response
      })
      .finally(() => {
        this.refreshPromise = null
      })

    return this.refreshPromise
  }

  /**
   * Check if the access token needs refresh
   */
  shouldRefreshToken(): boolean {
    const tokens = this.getTokens()
    if (!tokens) return false

    // Refresh if token will expire within buffer time
    return tokens.expires_at - Date.now() < REFRESH_BUFFER_MS
  }

  /**
   * Check if user is authenticated (has valid tokens)
   */
  async checkAuth(): Promise<User | null> {
    const tokens = this.getTokens()

    if (!tokens) {
      return null
    }

    // Set token in API client
    api.setAuthToken(tokens.access_token)

    // Check if token needs refresh
    if (this.shouldRefreshToken()) {
      try {
        await this.refreshToken()
      } catch {
        this.clearAuth()
        return null
      }
    }

    try {
      const user = await this.getUser()
      this.setUser(user)
      return user
    } catch (error) {
      // Token is invalid, try to refresh
      if (error instanceof ApiError && error.status === 401) {
        try {
          await this.refreshToken()
          const user = await this.getUser()
          this.setUser(user)
          return user
        } catch {
          this.clearAuth()
        }
      }
      return null
    }
  }

  /**
   * Initialize auth state from storage
   */
  initializeAuth(): { tokens: StoredTokens | null; user: User | null } {
    const tokens = this.getTokens()
    const user = this.getStoredUser()

    if (tokens) {
      api.setAuthToken(tokens.access_token)
    }

    return { tokens, user }
  }

  /**
   * Store authentication tokens
   */
  setTokens(tokens: StoredTokens): void {
    localStorage.setItem(TOKENS_KEY, JSON.stringify(tokens))
  }

  /**
   * Get stored authentication tokens
   */
  getTokens(): StoredTokens | null {
    const data = localStorage.getItem(TOKENS_KEY)
    return data ? JSON.parse(data) : null
  }

  /**
   * Get the current access token
   */
  getAccessToken(): string | null {
    return this.getTokens()?.access_token || null
  }

  /**
   * Store user data
   */
  setUser(user: User): void {
    localStorage.setItem(USER_KEY, JSON.stringify(user))
  }

  /**
   * Get stored user data
   */
  getStoredUser(): User | null {
    const userData = localStorage.getItem(USER_KEY)
    return userData ? JSON.parse(userData) : null
  }

  /**
   * Clear all auth data
   */
  clearAuth(): void {
    localStorage.removeItem(TOKENS_KEY)
    localStorage.removeItem(USER_KEY)
    api.clearAuthToken()
  }
}

/** Singleton instance */
export const authService = new AuthService()
