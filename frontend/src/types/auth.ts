/**
 * =============================================================================
 * Al-Sabiqoon - Authentication Types
 * =============================================================================
 * TypeScript interfaces for authentication-related data structures.
 * =============================================================================
 */

/**
 * User model returned from the API
 */
export interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string
  updated_at: string
}

/**
 * Login request payload
 */
export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

/**
 * Registration request payload
 */
export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
}

/**
 * Authentication response from login/register
 */
export interface AuthResponse {
  user: User
  access_token: string
  refresh_token: string
  expires_in: number
  token_type: string
  message?: string
}

/**
 * Token refresh response
 */
export interface TokenResponse {
  access_token: string
  refresh_token: string
  expires_in: number
  token_type: string
}

/**
 * Stored token data with expiration
 */
export interface StoredTokens {
  access_token: string
  refresh_token: string
  expires_at: number // Unix timestamp in milliseconds
}

/**
 * Validation error response from Laravel
 */
export interface ValidationErrors {
  message: string
  errors: Record<string, string[]>
}
