<!--
  =============================================================================
  Login View
  =============================================================================
  User login page with email/password form.
  =============================================================================
-->

<template>
  <GuestLayout>
    <div class="w-full max-w-md">
      <!-- Card -->
      <div class="glass-strong rounded-2xl p-8 animate-fade-in">
        <!-- Header -->
        <div class="text-center mb-8">
          <h1 class="text-2xl font-bold text-primary">Welcome back</h1>
          <p class="text-secondary mt-2">Sign in to your account</p>
        </div>

        <!-- Error Alert -->
        <div
          v-if="generalError"
          class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
        >
          <p class="text-sm text-red-600 dark:text-red-400">{{ generalError }}</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-primary mb-1">
              Email address
            </label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              required
              class="input"
              :class="{ 'border-red-500': errors.email }"
              placeholder="you@example.com"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-500">
              {{ errors.email[0] }}
            </p>
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-primary mb-1">
              Password
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              class="input"
              :class="{ 'border-red-500': errors.password }"
              placeholder="Enter your password"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-500">
              {{ errors.password[0] }}
            </p>
          </div>

          <!-- Remember me -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.remember"
                type="checkbox"
                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <span class="text-sm text-secondary">Remember me</span>
            </label>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="authStore.loading"
            class="btn-primary w-full py-3"
          >
            <span v-if="authStore.loading" class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
              Signing in...
            </span>
            <span v-else>Sign in</span>
          </button>
        </form>

        <!-- Footer -->
        <p class="mt-6 text-center text-sm text-secondary">
          Don't have an account?
          <router-link
            :to="{ name: 'register' }"
            class="text-primary-600 hover:text-primary-700 font-medium"
          >
            Sign up
          </router-link>
        </p>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ApiError } from '@/api/client'
import GuestLayout from '@/layouts/GuestLayout.vue'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const errors = ref<Record<string, string[]>>({})
const generalError = ref('')

async function handleSubmit() {
  errors.value = {}
  generalError.value = ''

  try {
    await authStore.login(form)
    router.push({ name: 'home' })
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.status === 422 && e.data) {
        const data = e.data as { errors?: Record<string, string[]> }
        errors.value = data.errors || {}
      } else if (e.status === 401) {
        generalError.value = 'Invalid email or password'
      } else {
        generalError.value = e.message
      }
    } else {
      generalError.value = 'An unexpected error occurred'
    }
  }
}
</script>
