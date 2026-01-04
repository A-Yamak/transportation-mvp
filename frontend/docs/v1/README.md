# Al-Sabiqoon Frontend Documentation v1

> **Last Updated:** December 2025
> **Folder:** `/frontend/`
> **Framework:** Vue 3 + TypeScript + Tailwind CSS 4

## Overview

The frontend is a modern single-page application (SPA) built with Vue 3's Composition API. It features a glassy light/dark theme system using Tailwind CSS 4 with OKLCH colors.

---

## Technology Stack

| Package | Version | Purpose |
|---------|---------|---------|
| Vue | 3.5 | Reactive UI framework |
| TypeScript | 5.9 | Type-safe JavaScript |
| Vite | 7.x | Build tool & dev server |
| Tailwind CSS | 4.x | Utility-first CSS |
| Pinia | 3.x | State management |
| Vue Router | 4.x | Client-side routing |
| Vitest | 4.x | Unit testing |

---

## Folder Structure

```
frontend/
├── src/
│   ├── api/
│   │   └── client.ts          # API client (fetch wrapper)
│   │
│   ├── assets/
│   │   └── main.css           # Tailwind + theme system
│   │
│   ├── components/            # Reusable Vue components
│   │   ├── common/            # Buttons, inputs, etc.
│   │   └── layout/            # Header, footer, sidebar
│   │
│   ├── composables/
│   │   └── useTheme.ts        # Dark mode composable
│   │
│   ├── router/
│   │   └── index.ts           # Vue Router configuration
│   │
│   ├── stores/
│   │   └── user.ts            # Pinia stores
│   │
│   ├── views/                 # Page-level components
│   │   ├── HomeView.vue
│   │   └── AboutView.vue
│   │
│   ├── App.vue                # Root component
│   └── main.ts                # Application entry
│
├── public/                    # Static assets (favicon, etc.)
├── docs/v1/                   # This documentation
├── index.html                 # HTML entry point
├── vite.config.ts             # Vite configuration
├── tsconfig.json              # TypeScript configuration
└── package.json               # Dependencies & scripts
```

---

## Key Concepts for Beginners

### Vue 3 and the Composition API

**What it is:** Vue is a framework for building user interfaces. The Composition API is Vue 3's modern way of organizing component logic.

**The problem it solves:** Without a framework, you'd manually manipulate the DOM and track state changes. Vue handles this automatically through "reactivity."

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

// Reactive state - changes automatically update the UI
const count = ref(0)
const name = ref('Al-Sabiqoon')

// Computed property - recalculates when dependencies change
const message = computed(() => `${name.value} has ${count.value} items`)

// Function that modifies state
function increment() {
  count.value++  // UI automatically updates!
}

// Lifecycle hook - runs when component is added to page
onMounted(() => {
  console.log('Component is visible')
})
</script>

<template>
  <div>
    <h1>{{ message }}</h1>
    <button @click="increment">Add Item</button>
  </div>
</template>
```

### TypeScript

**What it is:** TypeScript adds types to JavaScript, catching errors before your code runs.

```typescript
// JavaScript - errors only appear at runtime
function greet(user) {
  return 'Hello ' + user.name  // Crashes if user is undefined!
}

// TypeScript - errors appear in your editor
interface User {
  name: string
  email: string
}

function greet(user: User): string {
  return 'Hello ' + user.name
}

greet()  // ❌ Error: Expected 1 argument
greet({ name: 'John', email: 'john@test.com' })  // ✅ Works
```

### Single File Components (.vue)

**What it is:** Vue's file format that combines template, script, and styles in one file.

```vue
<!-- MyComponent.vue -->
<script setup lang="ts">
// TypeScript logic
const message = ref('Hello!')
</script>

<template>
  <!-- HTML template -->
  <div class="card">{{ message }}</div>
</template>

<style scoped>
/* CSS - "scoped" means styles only apply to THIS component */
.card { padding: 1rem; }
</style>
```

### Vite

**What it is:** A fast build tool and development server. When you save a file, changes appear in the browser instantly.

```bash
npm run dev      # Start dev server (instant!)
npm run build    # Build for production
npm run preview  # Preview production build
```

**Hot Module Replacement (HMR):** Your app updates without losing state. If you're typing in a form and save a file, your typed text remains.

### Tailwind CSS 4

**What it is:** A utility-first CSS framework. Instead of writing custom CSS, you apply pre-built classes.

```html
<!-- Traditional CSS -->
<style>
.card {
  padding: 1rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
<div class="card">Content</div>

<!-- Tailwind CSS -->
<div class="p-4 bg-white rounded-lg shadow">Content</div>
```

**Tailwind CSS 4 features:**
- `@theme` directive for design tokens
- Native CSS cascade layers
- OKLCH color support

### OKLCH Colors

**What it is:** A color space where colors with the same lightness value look equally bright to humans.

```css
/* Problem with RGB/HSL: Different colors look different brightness */
--blue: hsl(220, 100%, 50%);   /* Looks darker */
--yellow: hsl(60, 100%, 50%);  /* Looks brighter */

/* OKLCH: Same lightness = same perceived brightness */
--blue: oklch(60% 0.18 250);   /* 60% lightness */
--yellow: oklch(60% 0.18 85);  /* 60% lightness */
/* Both look equally bright! */
```

### Pinia (State Management)

**What it is:** Vue's official state management library for sharing data between components.

**The problem:** Passing data between distant components requires "prop drilling" - passing props through many levels. Pinia provides a central store any component can access.

```typescript
// stores/user.ts
import { defineStore } from 'pinia'

export const useUserStore = defineStore('user', () => {
  const user = ref<User | null>(null)
  const isLoggedIn = computed(() => user.value !== null)

  async function login(email: string, password: string) {
    const response = await api.post('/login', { email, password })
    user.value = response.user
  }

  function logout() {
    user.value = null
  }

  return { user, isLoggedIn, login, logout }
})

// In any component
const userStore = useUserStore()
await userStore.login('email@test.com', 'password')
console.log(userStore.isLoggedIn)  // true
```

### Vue Router

**What it is:** Client-side routing that maps URLs to components without page reloads.

```typescript
// router/index.ts
const routes = [
  { path: '/', component: HomeView },
  { path: '/about', component: AboutView },
  { path: '/users/:id', component: UserProfile }
]

// In a component
<script setup>
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

// Read URL parameter
console.log(route.params.id)  // From /users/:id

// Navigate programmatically
router.push('/about')
</script>

<template>
  <RouterLink to="/">Home</RouterLink>
  <RouterLink to="/about">About</RouterLink>
  <RouterView />  <!-- Current route renders here -->
</template>
```

### Composables

**What they are:** Functions that encapsulate reusable logic with Vue's reactivity.

```typescript
// composables/useTheme.ts
export function useTheme() {
  const theme = ref<'light' | 'dark' | 'system'>('system')

  const isDark = computed(() => {
    if (theme.value === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    return theme.value === 'dark'
  })

  function toggleTheme() {
    theme.value = isDark.value ? 'light' : 'dark'
  }

  return { theme, isDark, toggleTheme }
}

// Use in any component
const { isDark, toggleTheme } = useTheme()
```

### The @ Import Alias

**What it is:** `@` is an alias for the `src` folder, providing clean imports.

```typescript
// Without alias - messy relative paths
import { api } from '../../../api/client'

// With alias - always clean
import { api } from '@/api/client'
```

Configured in `vite.config.ts`:
```typescript
resolve: {
  alias: {
    '@': fileURLToPath(new URL('./src', import.meta.url))
  }
}
```

### Vitest

**What it is:** A fast testing framework for Vite projects.

```typescript
// components/__tests__/MyComponent.spec.ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MyComponent from '../MyComponent.vue'

describe('MyComponent', () => {
  it('renders greeting', () => {
    const wrapper = mount(MyComponent, {
      props: { name: 'World' }
    })
    expect(wrapper.text()).toContain('Hello, World!')
  })

  it('increments on click', async () => {
    const wrapper = mount(MyComponent)
    await wrapper.find('button').trigger('click')
    expect(wrapper.text()).toContain('Count: 1')
  })
})
```

```bash
npm run test:unit         # Run tests
npm run test:unit -- --watch  # Watch mode
```

---

## Theme System

### Configuration

The theme is defined in `src/assets/main.css`:

```css
@import "tailwindcss";

@theme {
  /* Primary colors (blue) */
  --color-primary-500: oklch(60% 0.18 250);
  --color-primary-600: oklch(50% 0.18 250);

  /* Accent colors (gold) */
  --color-accent-500: oklch(70% 0.20 85);
}

:root {
  /* Light mode */
  --glass-bg: oklch(100% 0 0 / 0.7);
  --text-primary: oklch(15% 0 0);
}

.dark {
  /* Dark mode */
  --glass-bg: oklch(20% 0 0 / 0.8);
  --text-primary: oklch(95% 0 0);
}
```

### Glass Components

```html
<!-- Glass card -->
<div class="glass rounded-xl p-6">
  <h2 class="text-xl font-semibold">Card Title</h2>
  <p class="text-secondary">Card content</p>
</div>

<!-- Glass variants -->
<div class="glass-subtle">Light glass</div>
<div class="glass-strong">Prominent glass</div>
```

### Buttons

```html
<button class="btn-primary">Primary Action</button>
<button class="btn-secondary">Secondary</button>
<button class="btn-glass">Glass Button</button>
```

---

## API Client

### Usage

```typescript
import { api, ApiError } from '@/api/client'

// GET request
const users = await api.get<User[]>('/users')

// POST request
const user = await api.post<User>('/users', { name: 'John' })

// With query parameters
const posts = await api.get<Post[]>('/posts', {
  params: { page: 2, per_page: 10 }
})

// Error handling
try {
  await api.get('/protected')
} catch (error) {
  if (error instanceof ApiError) {
    if (error.status === 401) {
      router.push('/login')
    }
  }
}
```

### Authentication

```typescript
// After login
api.setAuthToken(response.access_token)

// Check if authenticated
if (api.isAuthenticated()) { ... }

// On logout
api.clearAuthToken()
```

---

## Development Commands

```bash
# Development
npm run dev         # Start Vite dev server
npm run build       # Build for production
npm run preview     # Preview production build

# Testing
npm run test:unit   # Run unit tests
npm run type-check  # TypeScript check

# Code Quality
npm run lint        # ESLint
npm run format      # Prettier
```

---

## Environment Variables

Create `.env.local` for local development:

```bash
VITE_API_URL=http://localhost:8000
VITE_APP_NAME=Al-Sabiqoon
```

**Important:** Variables must be prefixed with `VITE_` to be accessible in code.

```typescript
const apiUrl = import.meta.env.VITE_API_URL
```

---

## Docker Configuration

### Development

```dockerfile
# Dockerfile.dev
FROM node:22-alpine
WORKDIR /app
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
```

### Production

```dockerfile
# Dockerfile (multi-stage)
FROM node:22-alpine AS builder
RUN npm ci && npm run build

FROM nginx:alpine
COPY --from=builder /app/dist /usr/share/nginx/html
```

---

## Styling Guidelines

### Use Tailwind For
- Layout: `flex`, `grid`, `p-*`, `m-*`
- Typography: `text-*`, `font-*`
- Colors: `bg-*`, `text-*`
- Responsive: `sm:`, `md:`, `lg:`
- States: `hover:`, `focus:`, `dark:`

### Use Vanilla CSS For
- Complex animations (`@keyframes`)
- Pseudo-elements (`::before`, `::after`)
- Third-party overrides

---

## Related Documentation

- **Root:** [/docs/v1/](../../docs/v1/)
- **Infrastructure:** [/infrastructure/docs/v1/](../../infrastructure/docs/v1/)
- **Backend:** [/backend/docs/v1/](../../backend/docs/v1/)
