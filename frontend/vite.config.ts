import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

// ==============================================================================
// Al-Sabiqoon Frontend - Vite Configuration
// ==============================================================================
// Build tool configuration for Vue 3 + TypeScript + Tailwind CSS
// https://vite.dev/config/
// ==============================================================================

export default defineConfig({
  plugins: [
    // Vue 3 support with SFC compilation
    vue(),

    // Vue DevTools integration for development
    vueDevTools(),

    // Tailwind CSS 4 - processes @import "tailwindcss" in CSS files
    tailwindcss(),
  ],

  resolve: {
    alias: {
      // Path alias: @/ resolves to src/
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },

  // Development server configuration
  server: {
    // Allow connections from Docker containers
    host: true,
    port: 5173,
    // Strict port - fail if port is already in use
    strictPort: true,
    // CORS configuration for API calls during development
    cors: true,
  },

  // Build configuration
  build: {
    // Generate source maps for production debugging
    sourcemap: false,
    // Target modern browsers for smaller bundles
    target: 'esnext',
    // Chunk size warning threshold (in KB)
    chunkSizeWarningLimit: 500,
  },

  // Environment variable prefix (Vite default is VITE_)
  envPrefix: 'VITE_',
})
