<!--
  =============================================================================
  Guest Layout
  =============================================================================
  Layout for unauthenticated pages (login, register, landing).
  Features a centered card with gradient background.
  =============================================================================
-->

<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-primary-950 dark:via-gray-900 dark:to-accent-950 flex flex-col">
    <!-- Header -->
    <header class="p-4 flex justify-between items-center">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center">
          <span class="text-white font-bold text-sm">AS</span>
        </div>
        <span class="font-semibold text-primary dark:text-white">Al-Sabiqoon</span>
      </div>

      <!-- Theme Toggle -->
      <button
        @click="toggleTheme"
        class="p-2 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
      >
        <!-- Sun icon (dark mode) -->
        <svg v-if="isDark" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
        </svg>
        <!-- Moon icon (light mode) -->
        <svg v-else class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
          <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
        </svg>
      </button>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex items-center justify-center p-4">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="p-4 text-center text-sm text-muted">
      &copy; {{ new Date().getFullYear() }} Al-Sabiqoon. All rights reserved.
    </footer>
  </div>
</template>

<script setup lang="ts">
import { useTheme } from '@/composables/useTheme'

const { isDark, toggleTheme } = useTheme()
</script>
