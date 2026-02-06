# Frontend Architecture

This document describes the React Single Page Application (SPA) that powers the Stadion frontend.

## Technology Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| React | 18 | UI library |
| React Router | 6 | Client-side routing |
| TanStack Query | Latest | Server state management and caching |
| Axios | Latest | HTTP client |
| Tailwind CSS | 3.4 | Styling |
| Vite | 5.0 | Build tool and dev server |

## Directory Structure

```
src/
├── api/
│   └── client.js         # Axios instance and API helpers
├── components/
│   ├── family-tree/      # Family tree visualization components
│   ├── import/           # Import wizard components
│   └── layout/           # Layout wrapper component
├── constants/
│   └── app.js            # Application-wide constants
├── hooks/                # Custom React hooks
├── pages/                # Route page components
│   ├── Teams/
│   ├── People/
│   ├── Settings/
│   ├── Todos/
│   └── Workspaces/
├── utils/                # Utility functions
├── App.jsx               # Main routing component
├── main.jsx              # Application entry point
└── index.css             # Global styles (Tailwind imports)
```

## Entry Points

### `src/main.jsx`

Application bootstrap:
- Creates React root with StrictMode
- Configures TanStack Query client
- Wraps app in BrowserRouter and QueryClientProvider

```jsx
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});
```

### `src/App.jsx`

Main routing component:
- Defines all application routes
- Implements `ProtectedRoute` wrapper for authentication
- Wraps protected routes in `Layout` component

## Routing

### Route Structure

| Path | Component | Description |
|------|-----------|-------------|
| `/login` | `Login` | Public login page |
| `/` | `Dashboard` | Home dashboard |
| `/people` | `PeopleList` | Contact list |
| `/people/new` | `PersonForm` | Create contact |
| `/people/:id` | `PersonDetail` | View contact |
| `/people/:id/edit` | `PersonForm` | Edit contact |
| `/people/:id/family-tree` | `FamilyTree` | Family tree visualization |
| `/people/:personId/contact/new` | `ContactDetailForm` | Add contact method |
| `/people/:personId/work-history/new` | `WorkHistoryForm` | Add work history |
| `/people/:personId/relationship/new` | `RelationshipForm` | Add relationship |
| `/teams` | `TeamsList` | Team list |
| `/teams/new` | `TeamForm` | Create team |
| `/teams/:id` | `TeamDetail` | View team |
| `/teams/:id/edit` | `TeamForm` | Edit team |
| `/settings` | `Settings` | Settings page |
| `/settings/relationship-types` | `RelationshipTypes` | Manage relationship types |
| `/settings/import` | `Import` | Import contacts |
| `/workspaces` | `WorkspacesList` | Workspace list |
| `/workspaces/:id` | `WorkspaceDetail` | View workspace, manage members |

### Authentication

The `ProtectedRoute` component checks authentication state:
- Shows loading spinner while checking
- Redirects to `/login` if not authenticated
- Renders children if authenticated

```jsx
function ProtectedRoute({ children }) {
  const { isLoggedIn, isLoading } = useAuth();
  
  if (isLoading) return <LoadingSpinner />;
  if (!isLoggedIn) return <Navigate to="/login" replace />;
  
  return children;
}
```

## API Client

### `src/api/client.js`

Configures Axios for WordPress REST API communication.

**Configuration:**
```jsx
const api = axios.create({
  baseURL: config.apiUrl || '/wp-json',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': config.nonce || '',
  },
});
```

**Interceptors:**
- **Request:** Updates nonce from `window.stadionConfig` before each request
- **Response:** Handles 401 (redirect to login) and 403 (log error)

### API Helpers

Two exported objects wrap common API calls:

**`wpApi`** - WordPress standard endpoints:
```js
wpApi.getPeople(params)
wpApi.getPerson(id, params)
wpApi.createPerson(data)
wpApi.updatePerson(id, data)
wpApi.deletePerson(id)
// ... similar for teams, dates, taxonomies
```

**`prmApi`** - Custom PRM endpoints:
```js
prmApi.getDashboard()
prmApi.search(query)
prmApi.getReminders(daysAhead)
prmApi.getCurrentUser()
prmApi.sideloadGravatar(personId, email)
prmApi.uploadPersonPhoto(personId, file)
// ... and more
```

## WordPress Configuration

The app receives configuration from WordPress via `window.stadionConfig`:

```js
window.stadionConfig = {
  apiUrl: '/wp-json',
  nonce: 'abc123...',
  isLoggedIn: true,
  userId: 1,
  loginUrl: '/wp-login.php',
  logoutUrl: '/wp-login.php?action=logout',
  siteName: 'My CRM',
};
```

This is injected by `functions.php` during page load.

## Custom Hooks

### `useAuth` (`src/hooks/useAuth.js`)

Returns authentication state from WordPress config:
```js
const { isLoggedIn, userId, loginUrl, logoutUrl, isLoading } = useAuth();
```

### `usePeople` (`src/hooks/usePeople.js`)

People data hooks with TanStack Query:

| Hook | Purpose |
|------|---------|
| `usePeople(params)` | Fetch all people (paginated) |
| `usePerson(id)` | Fetch single person |
| `usePersonTimeline(id)` | Fetch person's timeline |
| `usePersonDates(id)` | Fetch person's dates |
| `useCreatePerson()` | Create person mutation |
| `useUpdatePerson()` | Update person mutation |
| `useDeletePerson()` | Delete person mutation |
| `useCreateNote()` | Create note mutation |
| `useDeleteNote()` | Delete note mutation |
| `useCreateActivity()` | Create activity mutation |
| `useDeleteDate()` | Delete date mutation |

**Query Key Structure:**
```js
peopleKeys = {
  all: ['people'],
  lists: () => [...all, 'list'],
  list: (filters) => [...lists(), filters],
  details: () => [...all, 'detail'],
  detail: (id) => [...details(), id],
  timeline: (id) => [...detail(id), 'timeline'],
  dates: (id) => [...detail(id), 'dates'],
};
```

### `useDashboard` (`src/hooks/useDashboard.js`)

Dashboard and utility hooks:

| Hook | Purpose |
|------|---------|
| `useDashboard()` | Fetch dashboard summary |
| `useReminders(daysAhead)` | Fetch upcoming reminders |
| `useSearch(query)` | Global search (min 2 chars) |

### `useDocumentTitle` (`src/hooks/useDocumentTitle.js`)

Document title management:

| Hook | Purpose |
|------|---------|
| `useDocumentTitle(title)` | Set specific page title |
| `useRouteTitle(customTitle)` | Auto-set title based on route |

### `useWorkspaces` (`src/hooks/useWorkspaces.js`)

Workspace and sharing hooks for multi-user collaboration:

| Hook | Purpose |
|------|---------|
| `useWorkspaces()` | Fetch all workspaces for current user |
| `useWorkspace(id)` | Fetch single workspace with members |
| `useCreateWorkspace()` | Create workspace mutation |
| `useUpdateWorkspace()` | Update workspace mutation |
| `useDeleteWorkspace()` | Delete workspace mutation |
| `useAddWorkspaceMember()` | Add member to workspace |
| `useRemoveWorkspaceMember()` | Remove member from workspace |
| `useUpdateWorkspaceMember()` | Update member role |
| `useWorkspaceInvites(workspaceId)` | Fetch pending invites |
| `useCreateWorkspaceInvite()` | Create and send invite |
| `useRevokeWorkspaceInvite()` | Revoke pending invite |
| `useValidateInvite(token)` | Validate invite token (public) |
| `useAcceptInvite()` | Accept invite and join workspace |

**Query Key Structure:**
```js
['workspaces']                          // List all workspaces
['workspaces', id]                      // Single workspace
['workspaces', workspaceId, 'invites']  // Workspace invites
['invite', token]                       // Invite validation
```

### `useSharing` (`src/hooks/useSharing.js`)

Direct sharing hooks for sharing individual posts with specific users:

| Hook | Purpose |
|------|---------|
| `useShares(postType, postId)` | Fetch users a post is shared with |
| `useAddShare()` | Share post with a user mutation |
| `useRemoveShare()` | Remove share from a user mutation |
| `useUserSearch(query)` | Search users for sharing (min 2 chars) |

**Query Key Structure:**
```js
['shares', postType, postId]  // Shares for a specific post
['users', 'search', query]    // User search results
```

**Usage Example:**
```js
const { data: shares } = useShares('people', personId);
const addShare = useAddShare();
const removeShare = useRemoveShare();
const { data: users } = useUserSearch('john');

// Add a share
await addShare.mutateAsync({
  postType: 'people',
  postId: 123,
  userId: 456,
  permission: 'view' // or 'edit'
});

// Remove a share
await removeShare.mutateAsync({
  postType: 'people',
  postId: 123,
  userId: 456
});
```

### `useVersionCheck` (`src/hooks/useVersionCheck.js`)

Version checking for PWA/mobile app cache invalidation:

```js
const { hasUpdate, currentVersion, latestVersion, reload, checkVersion } = useVersionCheck({
  checkInterval: 5 * 60 * 1000, // Check every 5 minutes (default)
});
```

| Property | Type | Description |
|----------|------|-------------|
| `hasUpdate` | boolean | True when a new version is available |
| `currentVersion` | string | Version loaded with the current page |
| `latestVersion` | string | Latest version from server (when update available) |
| `reload` | function | Triggers a page reload to get new version |
| `checkVersion` | function | Manually trigger a version check |

**Check triggers:**
- Initial check 5 seconds after mount
- Periodic check every 5 minutes (configurable)
- When tab becomes visible (user returns to app)

**Backend endpoint:** `/rondo/v1/version` returns `{ version: "1.42.0" }`

## Utility Functions

### `src/utils/formatters.js`

| Function | Purpose |
|----------|---------|
| `decodeHtml(html)` | Decode HTML entities |
| `getTeamName(team)` | Get decoded team name |
| `getPersonName(person)` | Get decoded person name |
| `getPersonInitial(person)` | Get first initial for avatars |
| `sanitizePersonAcf(acfData, overrides)` | Sanitize ACF data for API |

### `src/utils/familyTreeBuilder.js`

Builds vis.js network data from person relationships. See [Family Tree](./family-tree.md).

## Constants

### `src/constants/app.js`

```js
export const APP_NAME = 'Stadion';
```

## State Management

### Server State (TanStack Query)

All server data is managed via TanStack Query:
- **Automatic caching** - 5 minute stale time by default
- **Cache invalidation** - Mutations automatically invalidate related queries
- **Background refetching** - Data stays fresh
- **Loading/error states** - Handled consistently

### Client State

Minimal client state via:
- **Component state** - `useState` for form inputs, UI state
- **URL state** - Route parameters, search params
- **Zustand** - Available for complex client state (currently unused)

## Build Configuration

### Development

```bash
npm run dev
```

- Vite dev server at `http://localhost:5173`
- Hot Module Replacement (HMR) enabled
- Theme auto-detects dev server when `WP_DEBUG` is true

### Production

```bash
npm run build
```

- Output to `dist/` directory
- Generates `manifest.json` for WordPress asset loading
- CSS and JS are hashed for cache busting

### Vite Configuration

Key settings from `vite.config.js`:
- Path alias: `@` → `src/`
- Output: `dist/assets/`
- Manifest: Enabled for WordPress integration

## Styling

### Tailwind CSS

All styling uses Tailwind utility classes. Configuration in `tailwind.config.js`.

### Global Styles

`src/index.css` includes:
- Tailwind directives (`@tailwind base/components/utilities`)
- Custom component classes
- CSS variables for theming

## PWA/Mobile App Support

### Version Check & Cache Invalidation

When the app is installed as a PWA or loaded in a mobile browser (Add to Home Screen), browser caching can prevent users from receiving updates. The version check system addresses this:

1. **Version Endpoint**: `/rondo/v1/version` returns the current theme version
2. **Periodic Checking**: `useVersionCheck` hook polls for new versions
3. **Update Banner**: When a new version is detected, a banner appears at the top of the screen with a "Reload" button

**How it works:**
1. On app load, the current version is stored from `window.stadionConfig.version`
2. Every 5 minutes (and when the user returns to the tab), the hook fetches `/rondo/v1/version`
3. If the server version differs from the loaded version, `hasUpdate` becomes true
4. The `UpdateBanner` component renders at the top of `App.jsx` when an update is available
5. User clicks "Reload" → `window.location.reload(true)` forces a fresh load

**Note:** The version is embedded in both the HTML response (via `stadionConfig`) and the asset filenames (via Vite's hash-based naming), ensuring a reload fetches all new assets.

## Related Documentation

- [REST API](./rest-api.md) - Backend API reference
- [Data Model](./data-model.md) - Post types and fields
- [Family Tree](./family-tree.md) - Visualization feature
- [Architecture](./architecture.md) - Overall system architecture

