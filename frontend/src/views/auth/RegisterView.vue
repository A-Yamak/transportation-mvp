<!--
  =============================================================================
  Register View
  =============================================================================
  User registration page with name/email/password form.
  =============================================================================
-->

<template>
  <GuestLayout>
    <div class="w-full max-w-md">
      <!-- Card -->
      <div class="glass-strong rounded-2xl p-8 animate-fade-in">
        <!-- Header -->
        <div class="text-center mb-8">
          <h1 class="text-2xl font-bold text-primary">Create account</h1>
          <p class="text-secondary mt-2">Join Al-Sabiqoon today</p>
        </div>

        <!-- Error Alert -->
        <div
          v-if="generalError"
          class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800"
        >
          <p class="text-sm text-red-600 dark:text-red-400">{{ generalError }}</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-primary mb-1">
              Full name
            </label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              autocomplete="name"
              required
              class="input"
              :class="{ 'border-red-500': errors.name }"
              placeholder="John Doe"
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-500">
              {{ errors.name[0] }}
            </p>
          </div>

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
              autocomplete="new-password"
              required
              class="input"
              :class="{ 'border-red-500': errors.password }"
              placeholder="Create a password"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-500">
              {{ errors.password[0] }}
            </p>
          </div>

          <!-- Confirm Password -->
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-primary mb-1">
              Confirm password
            </label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="input"
              :class="{ 'border-red-500': errors.password_confirmation }"
              placeholder="Confirm your password"
            />
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-500">
              {{ errors.password_confirmation[0] }}
            </p>
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
              Creating account...
            </span>
            <span v-else>Create account</span>
          </button>
        </form>

        <!-- Footer -->
        <p class="mt-6 text-center text-sm text-secondary">
          Already have an account?
          <router-link
            :to="{ name: 'login' }"
            class="text-primary-600 hover:text-primary-700 font-medium"
          >
            Sign in
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
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const errors = ref<Record<string, string[]>>({})
const generalError = ref('')

async function handleSubmit() {
  errors.value = {}
  generalError.value = ''

  try {
    await authStore.register(form)
    router.push({ name: 'home' })
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.status === 422 && e.data) {
        const data = e.data as { errors?: Record<string, string[]> }
        errors.value = data.errors || {}
      } else {
        generalError.value = e.message
      }
    } else {
      generalError.value = 'An unexpected error occurred'
    }
  }
}
</script>
