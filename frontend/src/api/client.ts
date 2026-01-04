/**
 * =============================================================================
 * Al-Sabiqoon - API Client
 * =============================================================================
 * HTTP client for communicating with the Laravel backend API.
 *
 * Features:
 *   - TypeScript-first with full type inference
 *   - Automatic JSON handling
 *   - Bearer token authentication support (Passport)
 *   - Automatic token refresh on 401 errors
 *   - Request/response interceptors
 *   - Error handling with typed errors
 *   - Request timeout support
 *
 * Usage:
 *   import { api } from '@/api/client'
 *
 *   // GET request
 *   const users = await api.get<User[]>('/users')
 *
 *   // POST request with body
 *   const user = await api.post<User>('/users', { name: 'John' })
 *
 *   // Set auth token after login
 *   api.setAuthToken(token)
 * =============================================================================
 */

/** API base URL from environment variables */
const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000'

/** Request timeout in milliseconds */
const TIMEOUT = Number(import.meta.env.VITE_API_TIMEOUT) || 30000

/**
 * Custom API error class with structured error information
 */
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public data?: unknown,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

/**
 * Request configuration options
 */
interface RequestOptions {
  /** Request headers */
  headers?: Record<string, string>
  /** Query parameters */
  params?: Record<string, string | number | boolean | undefined>
  /** Request timeout in ms (overrides default) */
  timeout?: number
  /** AbortController signal for cancellation */
  signal?: AbortSignal
  /** Skip automatic token refresh on 401 */
  skipAuthRefresh?: boolean
}

/**
 * API response wrapper for consistent handling
 */
interface ApiResponse<T> {
  data: T
  message?: string
  meta?: {
    current_page?: number
    last_page?: number
    per_page?: number
    total?: number
  }
}

/**
 * Token refresh callback type
 */
type TokenRefreshCallback = () => Promise<string | null>

/**
 * Build URL with query parameters
 */
function buildUrl(endpoint: string, params?: RequestOptions['params']): string {
  const url = new URL(`${API_URL}/api${endpoint}`)

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined) {
        url.searchParams.append(key, String(value))
      }
    })
  }

  return url.toString()
}

/**
 * API Client singleton
 */
class ApiClient {
  private defaultHeaders: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  }

  private authToken: string | null = null
  private onTokenRefresh: TokenRefreshCallback | null = null
  private isRefreshing = false
  private refreshSubscribers: Array<(token: string | null) => void> = []

  /**
   * Set the authentication token (from Passport)
   * @param token - Bearer token from login
   */
  setAuthToken(token: string): void {
    this.authToken = token
  }

  /**
   * Clear the authentication token (on logout)
   */
  clearAuthToken(): void {
    this.authToken = null
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return this.authToken !== null
  }

  /**
   * Set callback for token refresh
   * This will be called when a 401 is received and refresh is needed
   */
  setTokenRefreshCallback(callback: TokenRefreshCallback): void {
    this.onTokenRefresh = callback
  }

  /**
   * Subscribe to token refresh completion
   */
  private subscribeToTokenRefresh(callback: (token: string | null) => void): void {
    this.refreshSubscribers.push(callback)
  }

  /**
   * Notify all subscribers that token refresh is complete
   */
  private onTokenRefreshed(token: string | null): void {
    this.refreshSubscribers.forEach((callback) => callback(token))
    this.refreshSubscribers = []
  }

  /**
   * Get merged headers including auth token
   */
  private getHeaders(customHeaders?: Record<string, string>): Record<string, string> {
    const headers = { ...this.defaultHeaders, ...customHeaders }

    if (this.authToken) {
      headers['Authorization'] = `Bearer ${this.authToken}`
    }

    return headers
  }

  /**
   * Perform HTTP request with error handling and auto-refresh
   */
  private async request<T>(
    method: string,
    endpoint: string,
    data?: unknown,
    options: RequestOptions = {},
  ): Promise<T> {
    const url = buildUrl(endpoint, options.params)
    const headers = this.getHeaders(options.headers)

    // Create abort controller for timeout
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), options.timeout || TIMEOUT)

    try {
      const response = await fetch(url, {
        method,
        headers,
        body: data ? JSON.stringify(data) : undefined,
        signal: options.signal || controller.signal,
        credentials: 'include', // Include cookies for CSRF
      })

      clearTimeout(timeoutId)

      // Handle non-JSON responses
      const contentType = response.headers.get('content-type')
      const isJson = contentType?.includes('application/json')

      if (!response.ok) {
        // Handle 401 Unauthorized - try to refresh token
        if (response.status === 401 && !options.skipAuthRefresh && this.onTokenRefresh) {
          return this.handleUnauthorized<T>(method, endpoint, data, options)
        }

        const errorData = isJson ? await response.json() : await response.text()
        throw new ApiError(
          errorData?.message || `HTTP error ${response.status}`,
          response.status,
          errorData,
        )
      }

      // Return empty for 204 No Content
      if (response.status === 204) {
        return undefined as T
      }

      return (isJson ? response.json() : response.text()) as T
    } catch (error) {
      clearTimeout(timeoutId)

      if (error instanceof ApiError) {
        throw error
      }

      if (error instanceof Error) {
        if (error.name === 'AbortError') {
          throw new ApiError('Request timeout', 408)
        }
        throw new ApiError(error.message, 0)
      }

      throw new ApiError('Unknown error', 0)
    }
  }

  /**
   * Handle 401 Unauthorized by refreshing token and retrying request
   */
  private async handleUnauthorized<T>(
    method: string,
    endpoint: string,
    data?: unknown,
    options: RequestOptions = {},
  ): Promise<T> {
    if (!this.onTokenRefresh) {
      throw new ApiError('Unauthorized', 401)
    }

    // If already refreshing, wait for the refresh to complete
    if (this.isRefreshing) {
      return new Promise<T>((resolve, reject) => {
        this.subscribeToTokenRefresh(async (token) => {
          if (token) {
            try {
              const result = await this.request<T>(method, endpoint, data, {
                ...options,
                skipAuthRefresh: true,
              })
              resolve(result)
            } catch (error) {
              reject(error)
            }
          } else {
            reject(new ApiError('Token refresh failed', 401))
          }
        })
      })
    }

    // Start token refresh
    this.isRefreshing = true

    try {
      const newToken = await this.onTokenRefresh()

      if (newToken) {
        this.setAuthToken(newToken)
        this.onTokenRefreshed(newToken)

        // Retry the original request
        return this.request<T>(method, endpoint, data, {
          ...options,
          skipAuthRefresh: true,
        })
      } else {
        this.onTokenRefreshed(null)
        throw new ApiError('Token refresh failed', 401)
      }
    } catch (error) {
      this.onTokenRefreshed(null)
      throw error
    } finally {
      this.isRefreshing = false
    }
  }

  /**
   * GET request
   */
  get<T>(endpoint: string, options?: RequestOptions): Promise<T> {
    return this.request<T>('GET', endpoint, undefined, options)
  }

  /**
   * POST request
   */
  post<T>(endpoint: string, data?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('POST', endpoint, data, options)
  }

  /**
   * PUT request
   */
  put<T>(endpoint: string, data?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('PUT', endpoint, data, options)
  }

  /**
   * PATCH request
   */
  patch<T>(endpoint: string, data?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('PATCH', endpoint, data, options)
  }

  /**
   * DELETE request
   */
  delete<T>(endpoint: string, options?: RequestOptions): Promise<T> {
    return this.request<T>('DELETE', endpoint, undefined, options)
  }
}

/** Singleton API client instance */
export const api = new ApiClient()

/**
 * Export types for use in other modules
 */
export type { RequestOptions, ApiResponse }
