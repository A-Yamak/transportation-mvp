<!--
  =============================================================================
  Home View (Dashboard)
  =============================================================================
  Authenticated home page / dashboard.
  =============================================================================
-->

<template>
  <AuthenticatedLayout>
    <!-- Welcome Section -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-primary">
        Welcome back, {{ authStore.user?.name?.split(' ')[0] }}!
      </h1>
      <p class="text-secondary mt-1">Here's what's happening with your account today.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- Stat Card 1 -->
      <div class="glass rounded-xl p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted">Total Projects</p>
            <p class="text-2xl font-bold text-primary mt-1">12</p>
          </div>
          <div class="w-12 h-12 rounded-lg bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
          </div>
        </div>
        <p class="text-xs text-green-600 mt-3">+2 this month</p>
      </div>

      <!-- Stat Card 2 -->
      <div class="glass rounded-xl p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted">Active Tasks</p>
            <p class="text-2xl font-bold text-primary mt-1">28</p>
          </div>
          <div class="w-12 h-12 rounded-lg bg-accent-100 dark:bg-accent-900/50 flex items-center justify-center">
            <svg class="w-6 h-6 text-accent-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </div>
        </div>
        <p class="text-xs text-muted mt-3">8 due today</p>
      </div>

      <!-- Stat Card 3 -->
      <div class="glass rounded-xl p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted">Team Members</p>
            <p class="text-2xl font-bold text-primary mt-1">5</p>
          </div>
          <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/50 flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
        <p class="text-xs text-muted mt-3">All active</p>
      </div>

      <!-- Stat Card 4 -->
      <div class="glass rounded-xl p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted">Completion Rate</p>
            <p class="text-2xl font-bold text-primary mt-1">94%</p>
          </div>
          <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
        </div>
        <p class="text-xs text-green-600 mt-3">+5% from last week</p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Recent Activity -->
      <div class="lg:col-span-2 glass rounded-xl p-6">
        <h2 class="text-lg font-semibold text-primary mb-4">Recent Activity</h2>
        <div class="space-y-4">
          <div class="flex items-start gap-4 p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-primary">Task completed</p>
              <p class="text-sm text-muted truncate">Updated user authentication flow</p>
              <p class="text-xs text-muted mt-1">2 hours ago</p>
            </div>
          </div>

          <div class="flex items-start gap-4 p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-primary">New project created</p>
              <p class="text-sm text-muted truncate">Al-Sabiqoon Platform v2</p>
              <p class="text-xs text-muted mt-1">5 hours ago</p>
            </div>
          </div>

          <div class="flex items-start gap-4 p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
            <div class="w-10 h-10 rounded-full bg-accent-100 dark:bg-accent-900/50 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-accent-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-primary">New comment</p>
              <p class="text-sm text-muted truncate">Ahmed mentioned you in a comment</p>
              <p class="text-xs text-muted mt-1">1 day ago</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="glass rounded-xl p-6">
        <h2 class="text-lg font-semibold text-primary mb-4">Quick Actions</h2>
        <div class="space-y-3">
          <button class="w-full btn-primary justify-start gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            New Project
          </button>
          <button class="w-full btn-secondary justify-start gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            Create Task
          </button>
          <button class="w-full btn-secondary justify-start gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Invite Member
          </button>
          <button class="w-full btn-secondary justify-start gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Settings
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'

const authStore = useAuthStore()
</script>
