# Phase 153: Wire Up Role Settings - Research

**Researched:** 2026-02-08
**Domain:** React frontend data fetching with TanStack Query
**Confidence:** HIGH

## Summary

Phase 153 requires replacing the last hardcoded role array in the frontend (TeamDetail.jsx line 330) with a settings-driven lookup. The backend implementation is complete in v19.1.0 - the `VolunteerStatus` PHP class reads from WordPress options and the REST API endpoint `/rondo/v1/volunteer-roles/settings` exposes this configuration.

The standard approach is to create a custom hook using TanStack Query that fetches role settings from the existing API endpoint. The codebase has well-established patterns for this: queryKey factories, 5-minute staleTime for rarely-changing data, and centralized API client methods.

**Primary recommendation:** Create a `useVolunteerRoleSettings` hook following the established pattern in `src/hooks/useFees.js` and `src/hooks/usePeople.js`, fetch settings in TeamDetail, and use the `player_roles` array to split members into players and staff.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @tanstack/react-query | 5.x | Server state management, caching | Project standard for all API data fetching |
| axios | Latest | HTTP client | Existing `src/api/client.js` wraps all API calls |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| React hooks | 18.x | Component state/lifecycle | Standard React pattern |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| TanStack Query | useEffect + useState | Would lose caching, invalidation, loading states - not recommended |
| Inline API call | Custom hook | Less reusable if settings needed elsewhere - hook is preferred pattern |

**Installation:**
No new packages needed - all dependencies already in project.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── hooks/              # Custom React hooks (one per domain)
│   ├── useVolunteerRoleSettings.js  # NEW - role settings hook
│   ├── usePeople.js    # EXISTING - people data hooks
│   └── useFees.js      # EXISTING - fee data hooks
├── api/
│   └── client.js       # EXISTING - API methods already defined
└── pages/Teams/
    └── TeamDetail.jsx  # UPDATE - consume useVolunteerRoleSettings
```

### Pattern 1: Custom Hook with QueryKey Factory
**What:** Centralized hook for fetching volunteer role settings via TanStack Query
**When to use:** For any settings/configuration data that rarely changes but must be fetched from API

**Example:**
```typescript
// Source: src/hooks/useFees.js (established pattern)
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Query keys for volunteer role settings
 */
export const volunteerRoleKeys = {
  all: ['volunteer-roles'],
  settings: () => [...volunteerRoleKeys.all, 'settings'],
};

/**
 * Hook for fetching volunteer role classification settings
 *
 * @returns {Object} Query result with data: { player_roles, excluded_roles, ... }
 */
export function useVolunteerRoleSettings() {
  return useQuery({
    queryKey: volunteerRoleKeys.settings(),
    queryFn: async () => {
      const response = await prmApi.getVolunteerRoleSettings();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - settings rarely change
  });
}
```

### Pattern 2: Component Usage with Loading States
**What:** Consume hook in TeamDetail, handle loading/error states, use data for player/staff split
**When to use:** When displaying data that depends on async configuration

**Example:**
```typescript
// Source: Derived from TeamDetail.jsx existing patterns
import { useVolunteerRoleSettings } from '@/hooks/useVolunteerRoleSettings';

export default function TeamDetail() {
  const { data: roleSettings, isLoading: isLoadingSettings } = useVolunteerRoleSettings();

  // Use fetched settings instead of hardcoded array
  const playerRoles = roleSettings?.player_roles || [];
  const isPlayerRole = (jobTitle) => playerRoles.includes(jobTitle);

  const players = employees?.current?.filter(p => isPlayerRole(p.job_title)) || [];
  const staff = employees?.current?.filter(p => !isPlayerRole(p.job_title)) || [];
}
```

### Anti-Patterns to Avoid
- **Fetching on every render:** Don't use `useEffect` with empty deps - TanStack Query handles this
- **Duplicate API client methods:** The `prmApi.getVolunteerRoleSettings()` method already exists in client.js line 291
- **Inline API calls:** Don't call `prmApi` directly in components - always wrap in custom hook
- **Hardcoded fallbacks that diverge:** If settings fail to load, fall back to empty array `[]` not a hardcoded list (backend has defaults)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Fetching API data | Custom fetch + useState + useEffect | TanStack Query (useQuery) | Handles caching, loading states, refetching, deduplication automatically |
| API client methods | Direct axios calls in components | Existing prmApi methods | Centralized auth, error handling, nonce injection |
| Query key management | Ad-hoc strings | QueryKey factory pattern | Consistent invalidation, namespacing, avoiding collisions |
| Loading states | Manual boolean flags | useQuery isLoading | Built-in, handles race conditions |

**Key insight:** TanStack Query eliminates 90% of the boilerplate for data fetching - don't recreate its features with useState/useEffect.

## Common Pitfalls

### Pitfall 1: Not Handling Loading State
**What goes wrong:** Component tries to use `roleSettings.player_roles` before data loads, causing `undefined` errors
**Why it happens:** API calls are async but component renders immediately
**How to avoid:** Use optional chaining (`roleSettings?.player_roles`) or check `isLoading` before rendering dependent UI
**Warning signs:** Console errors "Cannot read property 'player_roles' of undefined"

### Pitfall 2: Divergent Fallback Values
**What goes wrong:** Using hardcoded fallback array that doesn't match backend defaults when settings fail to load
**Why it happens:** Developer copies old hardcoded array as "safe" fallback
**How to avoid:** Use empty array `[]` as fallback - backend `VolunteerStatus::get_player_roles()` already has defaults. If frontend uses wrong fallback, UI shows different split than volunteer status calculation.
**Warning signs:** Player/staff split differs from volunteer status in unexpected ways

### Pitfall 3: Forgetting StaleTime
**What goes wrong:** Settings refetch on every component mount, causing unnecessary API calls
**Why it happens:** TanStack Query default is 0ms staleTime
**How to avoid:** Always set `staleTime: 5 * 60 * 1000` for rarely-changing configuration data (established pattern in codebase)
**Warning signs:** Network tab shows repeated calls to `/volunteer-roles/settings` on navigation

### Pitfall 4: Admin-Only API Permissions
**What goes wrong:** Regular users can't fetch settings, hook fails with 403 error
**Why it happens:** `/rondo/v1/volunteer-roles/settings` endpoint has `check_admin_permission` callback (class-rest-api.php line 763)
**How to avoid:** **CRITICAL** - endpoint must be changed to allow all authenticated users to read settings. Only POST (update) should require admin. This is a prerequisite for this phase.
**Warning signs:** 403 Forbidden errors in console for non-admin users

## Code Examples

Verified patterns from official sources:

### Complete Custom Hook
```typescript
// Source: Derived from src/hooks/useFees.js and src/hooks/usePeople.js patterns
// File: src/hooks/useVolunteerRoleSettings.js

import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

/**
 * Query keys for volunteer role settings
 */
export const volunteerRoleKeys = {
  all: ['volunteer-roles'],
  settings: () => [...volunteerRoleKeys.all, 'settings'],
  available: () => [...volunteerRoleKeys.all, 'available'],
};

/**
 * Hook for fetching volunteer role classification settings
 *
 * Returns current configuration for player roles and excluded roles.
 * These settings control volunteer status calculation and team member display.
 *
 * @returns {Object} Query result with:
 *   - data.player_roles: Array of role names that identify players
 *   - data.excluded_roles: Array of honorary/membership roles
 *   - data.default_player_roles: Backend defaults for player roles
 *   - data.default_excluded_roles: Backend defaults for excluded roles
 */
export function useVolunteerRoleSettings() {
  return useQuery({
    queryKey: volunteerRoleKeys.settings(),
    queryFn: async () => {
      const response = await prmApi.getVolunteerRoleSettings();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - settings rarely change
  });
}
```

### TeamDetail.jsx Usage
```typescript
// Source: Modified from src/pages/Teams/TeamDetail.jsx lines 328-336
import { useVolunteerRoleSettings } from '@/hooks/useVolunteerRoleSettings';

export default function TeamDetail() {
  const { id } = useParams();

  // Existing queries
  const { data: team, isLoading, error } = useQuery({ /* ... */ });
  const { data: employees } = useQuery({ /* ... */ });

  // NEW: Fetch role settings
  const { data: roleSettings } = useVolunteerRoleSettings();

  // ... rest of component

  // Members section (updated lines 328-336)
  {(() => {
    // Use fetched settings instead of hardcoded array
    const playerRoles = roleSettings?.player_roles || [];
    const isPlayerRole = (jobTitle) => playerRoles.includes(jobTitle);

    // Split current members into players and staff
    const players = employees?.current?.filter(p => isPlayerRole(p.job_title)) || [];
    const staff = employees?.current?.filter(p => !isPlayerRole(p.job_title)) || [];

    const hasAnyMembers = players.length > 0 || staff.length > 0;
    if (!hasAnyMembers) return null;

    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {/* Staff and Players sections render as before */}
      </div>
    );
  })()}
}
```

### API Client Method (Already Exists)
```typescript
// Source: src/api/client.js lines 289-292
// File: src/api/client.js (NO CHANGES NEEDED)

export const prmApi = {
  // ... other methods

  // Volunteer Role Classification (admin only)
  getAvailableRoles: () => api.get('/rondo/v1/volunteer-roles/available'),
  getVolunteerRoleSettings: () => api.get('/rondo/v1/volunteer-roles/settings'),
  updateVolunteerRoleSettings: (settings) => api.post('/rondo/v1/volunteer-roles/settings', settings),

  // ... other methods
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded arrays in components | API-driven settings | v19.1.0 (2026-02-07) | Settings UI can change role classifications without code changes |
| Separate arrays in frontend vs backend | Single source of truth (WordPress options) | v19.1.0 | Frontend player/staff split now matches backend volunteer calculation |
| Duplicate hardcoded lists | Backend VolunteerStatus class + REST API | v19.1.0 | Changes to role classification apply consistently across all features |

**Deprecated/outdated:**
- Hardcoded `playerRoles` arrays: Backend now provides this via `/rondo/v1/volunteer-roles/settings`
- Direct VolunteerStatus constant references: Constants now private, accessed via `get_player_roles()` method

## Open Questions

Things that couldn't be fully resolved:

1. **Endpoint permission level**
   - What we know: `/rondo/v1/volunteer-roles/settings` GET endpoint currently has `check_admin_permission` (class-rest-api.php line 763)
   - What's unclear: Whether this is intentional or needs to be changed to allow all authenticated users
   - Recommendation: Change GET endpoint to use `check_permission` (all authenticated users) instead of `check_admin_permission`. Only the POST (update) endpoint should require admin. This is likely an oversight since non-admin users need to see the correct player/staff split in TeamDetail.

2. **Error handling strategy**
   - What we know: Empty array fallback `[]` is safe - results in "all members are staff" display
   - What's unclear: Should UI show error message if settings fail to load, or silently degrade?
   - Recommendation: Silent degradation with empty array fallback. Settings failures are rare, and showing error would confuse users. Log to console for debugging but don't block UI.

3. **Cache invalidation trigger**
   - What we know: Settings change when admin updates role classifications
   - What's unclear: Should Settings page invalidate TeamDetail queries, or rely on 5-minute staleTime?
   - Recommendation: Don't add cross-page invalidation. 5-minute staleTime is sufficient - admin can refresh browser tab to see changes immediately if needed.

## Sources

### Primary (HIGH confidence)
- src/includes/class-volunteer-status.php - Backend implementation of role classification
- src/includes/class-rest-api.php lines 758-788 - REST endpoint registration
- src/api/client.js lines 289-292 - Existing API client methods
- src/hooks/useFees.js - Established custom hook pattern
- src/hooks/usePeople.js lines 170-179 - Filter options hook pattern (5min staleTime for settings)
- src/main.jsx lines 23-33 - TanStack Query global configuration
- src/pages/Teams/TeamDetail.jsx line 330 - Hardcoded playerRoles array to replace
- CHANGELOG.md lines 25-32 - v19.1.0 feature description

### Secondary (MEDIUM confidence)
- None required - all findings verified from codebase

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - TanStack Query and axios already in use throughout codebase
- Architecture: HIGH - Custom hook pattern verified from multiple existing hooks
- Pitfalls: HIGH - Admin permission issue identified from source code, other pitfalls are TanStack Query best practices

**Research date:** 2026-02-08
**Valid until:** 2026-03-08 (30 days for stable React/TanStack Query patterns)
