# Al-Sabiqoon Frontend

> Vue 3 SPA with TypeScript + Tailwind CSS 4

## Quick Start

```bash
# From project root
make up

# Access at http://localhost:5173
```

## Development URLs

| Service | URL |
|---------|-----|
| Frontend (Vite HMR) | http://localhost:5173 |
| Backend API | http://localhost:8000 |

## Project Structure

```
src/
├── api/           # API client with auto-refresh
│   └── client.ts
├── assets/        # CSS & theme
├── layouts/       # Guest & Authenticated layouts
│   ├── GuestLayout.vue
│   └── AuthenticatedLayout.vue
├── router/        # Vue Router with auth guards
├── services/      # Business logic services
│   └── auth.service.ts
├── stores/        # Pinia stores
│   └── auth.ts
├── types/         # TypeScript definitions
│   └── auth.ts
└── views/         # Page components
    ├── auth/
    │   ├── LoginView.vue
    │   └── RegisterView.vue
    └── HomeView.vue
```

## Authentication

The frontend uses OAuth2 Password Grant with automatic token refresh.

### Auth Store (`stores/auth.ts`)

```typescript
const authStore = useAuthStore()

// Login
await authStore.login({ email, password })

// Register
await authStore.register({ name, email, password, password_confirmation })

// Logout
await authStore.logout()

// Check auth status
if (authStore.isAuthenticated) { ... }

// Get current user
const user = authStore.user
```

### Router Guards

Routes are protected using meta fields:

```typescript
// Requires authentication
{ path: '/home', meta: { requiresAuth: true } }

// Guest only (redirects if authenticated)
{ path: '/login', meta: { requiresGuest: true } }
```

### Token Refresh

The API client automatically:
- Stores tokens in localStorage
- Adds Bearer token to requests
- Refreshes expired tokens on 401 responses
- Queues concurrent requests during refresh

## Commands

```bash
# Development
npm run dev         # Start Vite dev server
npm run build       # Build for production

# Testing
npm run test:unit   # Run Vitest
npm run type-check  # TypeScript check

# Code Quality
npm run lint        # ESLint
npm run format      # Prettier
```

## Environment Variables

Create `.env.local`:

```env
VITE_API_URL=http://localhost:8000
VITE_APP_NAME=Al-Sabiqoon
```

Variables must be prefixed with `VITE_` to be accessible in code.

## IDE Setup

- [VS Code](https://code.visualstudio.com/) + [Vue (Official)](https://marketplace.visualstudio.com/items?itemName=Vue.volar)
- [Vue.js devtools](https://chromewebstore.google.com/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd) browser extension

## Documentation

See [frontend/docs/v1/](./docs/v1/) for detailed documentation.
