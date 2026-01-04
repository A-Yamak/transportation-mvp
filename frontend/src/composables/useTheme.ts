/**
 * =============================================================================
 * Al-Sabiqoon - Theme Composable
 * =============================================================================
 * Provides reactive light/dark mode switching with system preference support.
 *
 * Features:
 *   - Three modes: 'light', 'dark', 'system' (follows OS preference)
 *   - Persists user preference to localStorage
 *   - Listens for system theme changes when in 'system' mode
 *   - Reactive state using Vue's Composition API
 *
 * Usage:
 *   import { useTheme } from '@/composables/useTheme'
 *
 *   const { theme, isDark, setTheme, toggleTheme } = useTheme()
 *
 *   // Toggle between light and dark
 *   toggleTheme()
 *
 *   // Set specific theme
 *   setTheme('dark')
 *
 *   // Check current state
 *   if (isDark.value) { ... }
 * =============================================================================
 */

import { ref, computed, watch, onMounted } from 'vue'

/**
 * Available theme options
 * - 'light': Always light mode
 * - 'dark': Always dark mode
 * - 'system': Follow operating system preference
 */
export type Theme = 'light' | 'dark' | 'system'

/** LocalStorage key for persisting theme preference */
const STORAGE_KEY = 'alsabiqoon-theme'

/** Singleton state - shared across all component instances */
const theme = ref<Theme>('system')
const isDark = ref(false)
let isInitialized = false

/**
 * Apply the theme to the DOM
 * Adds or removes 'dark' class from the html element
 */
function applyTheme(newTheme: Theme): void {
  const root = document.documentElement
  const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches

  // Determine if we should show dark mode
  if (newTheme === 'system') {
    isDark.value = systemPrefersDark
  } else {
    isDark.value = newTheme === 'dark'
  }

  // Apply the class to the root element
  root.classList.toggle('dark', isDark.value)

  // Update meta theme-color for mobile browsers
  const metaThemeColor = document.querySelector('meta[name="theme-color"]')
  if (metaThemeColor) {
    metaThemeColor.setAttribute('content', isDark.value ? '#1a1a1a' : '#ffffff')
  }
}

/**
 * Initialize theme from localStorage or system preference
 * Only runs once when the first component mounts
 */
function initializeTheme(): void {
  if (isInitialized) return

  // Try to get stored preference
  const stored = localStorage.getItem(STORAGE_KEY) as Theme | null

  if (stored && ['light', 'dark', 'system'].includes(stored)) {
    theme.value = stored
  } else {
    theme.value = 'system'
  }

  // Apply the theme
  applyTheme(theme.value)

  // Listen for system theme changes
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaQuery.addEventListener('change', (e) => {
    if (theme.value === 'system') {
      applyTheme('system')
    }
  })

  isInitialized = true
}

/**
 * Theme composable hook
 * Returns reactive theme state and control functions
 */
export function useTheme() {
  /**
   * Set the theme and persist to localStorage
   * @param newTheme - The theme to set ('light', 'dark', or 'system')
   */
  function setTheme(newTheme: Theme): void {
    theme.value = newTheme
    localStorage.setItem(STORAGE_KEY, newTheme)
    applyTheme(newTheme)
  }

  /**
   * Toggle between light and dark themes
   * If currently in system mode, switches to the opposite of the current appearance
   */
  function toggleTheme(): void {
    if (isDark.value) {
      setTheme('light')
    } else {
      setTheme('dark')
    }
  }

  /**
   * Get the icon name for the current theme state
   * Useful for theme toggle buttons
   */
  const themeIcon = computed(() => {
    if (theme.value === 'system') return 'computer'
    return isDark.value ? 'moon' : 'sun'
  })

  /**
   * Get the label for the current theme state
   * Useful for accessibility and tooltips
   */
  const themeLabel = computed(() => {
    switch (theme.value) {
      case 'light':
        return 'Light mode'
      case 'dark':
        return 'Dark mode'
      case 'system':
        return 'System preference'
      default:
        return 'Toggle theme'
    }
  })

  // Initialize on first mount
  onMounted(() => {
    initializeTheme()
  })

  return {
    /** Current theme setting ('light', 'dark', or 'system') */
    theme,

    /** Whether the UI is currently showing dark mode */
    isDark,

    /** Set the theme explicitly */
    setTheme,

    /** Toggle between light and dark */
    toggleTheme,

    /** Icon name for current theme state */
    themeIcon,

    /** Accessible label for current theme state */
    themeLabel,
  }
}
