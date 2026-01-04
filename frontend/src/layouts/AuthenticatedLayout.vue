<!--
  =============================================================================
  Authenticated Layout
  =============================================================================
  Layout for authenticated pages with navigation and user menu.
  =============================================================================
-->

<template>
  <div class="min-h-screen bg-surface flex flex-col">
    <!-- Header -->
    <header class="glass border-b border-default sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <!-- Logo -->
          <router-link to="/" class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-primary-600 flex items-center justify-center">
              <span class="text-white font-bold text-sm">AS</span>
            </div>
            <span class="font-semibold text-primary dark:text-white">Al-Sabiqoon</span>
          </router-link>

          <!-- Navigation -->
          <nav class="hidden md:flex items-center gap-6">
            <router-link
              to="/"
              class="text-secondary hover:text-primary transition-colors"
              active-class="text-primary-600 font-medium"
            >
              Dashboard
            </router-link>
          </nav>

          <!-- Right side -->
          <div class="flex items-center gap-4">
            <!-- Theme Toggle -->
            <button
              @click="toggleTheme"
              class="p-2 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
              :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
              <svg v-if="isDark" class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
              </svg>
              <svg v-else class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
              </svg>
            </button>

            <!-- User Menu -->
            <div class="relative">
              <button
                @click="showUserMenu = !showUserMenu"
                class="flex items-center gap-2 p-2 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 transition-colors"
              >
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                  <span class="text-primary-700 dark:text-primary-300 text-sm font-medium">
                    {{ authStore.displayName.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <span class="hidden sm:block text-sm font-medium text-primary">
                  {{ authStore.displayName }}
                </span>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Dropdown -->
              <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="transform opacity-0 scale-95"
                enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95"
              >
                <div
                  v-if="showUserMenu"
                  class="absolute right-0 mt-2 w-48 glass rounded-lg shadow-lg py-1 z-50"
                  @click="showUserMenu = false"
                >
                  <div class="px-4 py-2 border-b border-default">
                    <p class="text-sm font-medium text-primary">{{ authStore.user?.name }}</p>
                    <p class="text-xs text-muted truncate">{{ authStore.user?.email }}</p>
                  </div>
                  <button
                    @click="handleLogout"
                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                  >
                    Sign out
                  </button>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <slot />
      </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-default py-4">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-muted">
        &copy; {{ new Date().getFullYear() }} Al-Sabiqoon. All rights reserved.
      </div>
    </footer>

    <!-- Click outside to close menu -->
    <div
      v-if="showUserMenu"
      class="fixed inset-0 z-40"
      @click="showUserMenu = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'

const router = useRouter()
const authStore = useAuthStore()
const { isDark, toggleTheme } = useTheme()

const showUserMenu = ref(false)

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>
