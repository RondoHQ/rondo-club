# Architecture

**Analysis Date:** 2025-01-13

## Pattern Overview

**Overall:** Full-Stack Hybrid Monolith (WordPress Theme + React SPA)

**Key Characteristics:**
- React SPA served by WordPress theme
- REST API communication between frontend and backend
- WordPress as CMS and authentication provider
- User-scoped data isolation
- Plugin-like extensibility via WordPress hooks

## Layers

**Frontend Layer (React SPA):**
- Purpose: User interface and client-side logic
- Contains: React components, hooks, pages, utilities
- Location: `src/`
- Depends on: WordPress REST API
- Used by: End users via browser

**API Integration Layer:**
- Purpose: HTTP communication with WordPress
- Contains: Axios client, API facades (`wpApi`, `prmApi`)
- Location: `src/api/client.js`
- Depends on: WordPress REST endpoints
- Used by: React hooks and components

**Backend Services Layer:**
- Purpose: Business logic, data access, external integrations
- Contains: PHP classes for each domain
- Location: `includes/`
- Depends on: WordPress core, ACF Pro, Sabre DAV
- Used by: REST API endpoints

**Data Layer:**
- Purpose: Persistent storage
- Contains: Custom post types, taxonomies, ACF fields
- Location: WordPress database
- Depends on: MySQL/MariaDB
- Used by: Backend services

## Data Flow

**Read Flow (People List):**

1. User navigates to `/people`
2. `PeopleList.jsx` renders, triggers `usePeople()` hook
3. React Query checks cache, initiates GET request
4. `src/api/client.js` sends request to `/wp/v2/people`
5. WordPress receives request, validates nonce
6. `STADION_Access_Control` filters query by current user
7. Database returns user's people posts
8. Response transformed by `usePeople` hook
9. UI renders `PersonCard` components

**Write Flow (Create Person):**

1. User fills form in `PersonEditModal`
2. Form submission triggers `useCreatePerson()` mutation
3. `prmApi.createPerson(data)` sends POST request
4. WordPress validates and creates post
5. `STADION_Auto_Title` generates post title
6. ACF fields saved to postmeta
7. Response with new post ID returned
8. React Query invalidates `['people']` cache
9. List refreshes with new person

**Scheduled Flow (Reminders):**

1. WordPress cron fires at user's notification time
2. `stadion_user_reminder` action triggered
3. `STADION_Reminders::process_user_reminders()` executes
4. Queries upcoming important dates
5. Formats notifications per channel
6. Dispatches via Email and/or Slack

**State Management:**
- Server state: React Query with query keys like `['people']`, `['dashboard']`
- Client state: Zustand (if needed), React useState for local state
- No persistent client-side state beyond session

## Key Abstractions

**React Hooks:**
- Purpose: Encapsulate data fetching and mutations
- Examples: `usePeople()`, `useAuth()`, `useDashboard()`, `useReminders()`
- Pattern: TanStack Query wrapper with cache invalidation
- Location: `src/hooks/`

**API Facades:**
- Purpose: Semantic API access organized by resource
- Examples: `wpApi.getPeople()`, `prmApi.getDashboard()`, `prmApi.search()`
- Pattern: Object with methods mapping to REST endpoints
- Location: `src/api/client.js`

**PHP Service Classes:**
- Purpose: Domain-specific business logic
- Examples: `STADION_Reminders`, `STADION_Access_Control`, `STADION_REST_API`
- Pattern: Constructor registers WordPress hooks, methods handle actions
- Location: `includes/class-*.php`

**Modal Components:**
- Purpose: CRUD operations in overlay dialogs
- Examples: `PersonEditModal`, `CompanyEditModal`, `ImportantDateModal`
- Pattern: Controlled component with props for state, callbacks for actions
- Location: `src/components/*Modal.jsx`

## Entry Points

**WordPress Template:**
- Location: `index.php`
- Triggers: Any WordPress page load
- Responsibilities: Render React mount point, enqueue assets

**React Bootstrap:**
- Location: `src/main.jsx`
- Triggers: DOM ready
- Responsibilities: Initialize QueryClient, wrap App in providers, mount to #root

**Application Router:**
- Location: `src/App.jsx`
- Triggers: React mount
- Responsibilities: Define routes, authentication guards, layout wrapper

**PHP Initialization:**
- Location: `functions.php`
- Triggers: `after_setup_theme`, `plugins_loaded` hooks
- Responsibilities: Conditionally instantiate service classes based on request type

**CardDAV Entry:**
- Location: `functions.php` (early return)
- Triggers: Request to `/carddav/` or `/.well-known/carddav`
- Responsibilities: Bootstrap Sabre DAV server, skip other initialization

**iCal Entry:**
- Location: `includes/class-ical-feed.php`
- Triggers: Request to `/prm-ical/{userId}`
- Responsibilities: Generate iCalendar feed for user's dates

## Error Handling

**Strategy:** Errors bubble up to boundaries with user-friendly messages

**Frontend Patterns:**
- React Query `onError` callbacks show toast/alert
- Axios interceptor handles 401/403 redirects to login
- `try/catch` in mutation handlers with `console.error()` logging

**Backend Patterns:**
- `WP_Error` objects returned from REST endpoints
- `wp_die()` for fatal errors
- `error_log()` for debugging

## Cross-Cutting Concerns

**Logging:**
- Frontend: `console.error()` for errors
- Backend: `error_log()` for PHP errors
- No structured logging or external service

**Validation:**
- Frontend: React Hook Form with inline rules
- Backend: WordPress REST schema validation, custom sanitization

**Authentication:**
- WordPress session-based auth
- X-WP-Nonce header for CSRF protection
- `is_user_logged_in()` checks in PHP
- `ProtectedRoute` component in React

**Access Control:**
- `STADION_Access_Control` filters all queries to user's own posts
- Admin users can see all data
- Capability checks on sensitive endpoints

---

*Architecture analysis: 2025-01-13*
*Update when major patterns change*
