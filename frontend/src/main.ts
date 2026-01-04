/**
 * =============================================================================
 * Al-Sabiqoon Frontend - Application Entry Point
 * =============================================================================
 * Initializes the Vue 3 application with Pinia state management and Vue Router.
 * =============================================================================
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'

// Import global styles (Tailwind CSS + theme)
import './assets/main.css'

import App from './App.vue'
import router from './router'

// Create Vue application instance
const app = createApp(App)

// Register plugins
app.use(createPinia()) // State management
app.use(router) // Routing

// Mount application to DOM
app.mount('#app')
