---
phase: 134-01
subsystem: api
tags: [discipline-cases, seizoen, tanstack-query, react-hooks, rest-api]

dependency_graph:
  requires: [132-01]
  provides: [discipline-cases-hooks, seasons-api, current-season-endpoint]
  affects: [134-02, 134-03]

tech_stack:
  added: []
  patterns: [tanstack-query-hooks, client-side-filtering]

key_files:
  created:
    - src/hooks/useDisciplineCases.js
  modified:
    - src/api/client.js
    - includes/class-rest-api.php

decisions:
  - id: client-side-person-filtering
    choice: Filter discipline cases by person client-side
    rationale: ACF meta queries can be unreliable, client-side filtering is more robust

metrics:
  duration: 2m 15s
  completed: 2026-02-03
---

# Phase 134 Plan 01: API Integration for Discipline Cases Summary

**One-liner:** TanStack Query hooks for discipline cases with season filtering via REST API

## What Was Built

### 1. API Client Methods (src/api/client.js)

Added to `wpApi`:
- `getDisciplineCases(params)` - Fetch discipline cases from WordPress REST API
- `getDisciplineCase(id, params)` - Fetch single discipline case
- `getSeasons(params)` - Fetch seizoen taxonomy terms (sorted by name desc)

Added to `prmApi`:
- `getCurrentSeason()` - Fetch current season term from custom endpoint

### 2. React Hooks (src/hooks/useDisciplineCases.js)

Created four TanStack Query hooks:

1. **useDisciplineCases({ seizoen, enabled })** - Fetch discipline cases with optional season filter
2. **usePersonDisciplineCases(personId, { enabled })** - Fetch cases for specific person (client-side filtered)
3. **useSeasons()** - Fetch all seizoen taxonomy terms
4. **useCurrentSeason()** - Fetch current season term

Includes query key factories for proper cache management:
```javascript
disciplineCaseKeys = {
  all: ['discipline-cases'],
  lists: () => [...disciplineCaseKeys.all, 'list'],
  list: (filters) => [...disciplineCaseKeys.lists(), filters],
  byPerson: (personId) => [...disciplineCaseKeys.all, 'person', personId],
  seasons: ['seasons'],
  currentSeason: ['current-season'],
}
```

### 3. Backend Endpoint (includes/class-rest-api.php)

Added `GET /rondo/v1/current-season` endpoint:
- Returns current season term data (id, name, slug) or null
- Requires user approval (check_user_approved)
- Uses RONDO_Taxonomies::get_current_season() from Phase 132

## Decisions Made

### Client-Side Person Filtering
**Decision:** Filter discipline cases by person on the client side rather than using ACF meta queries.

**Rationale:** ACF post_object meta queries can be unreliable in WordPress REST API. Client-side filtering ensures consistent results and is acceptable for the expected volume of discipline cases (typically <100 per season).

## Verification Results

1. npm run lint passes (no new errors in added files)
2. src/api/client.js contains getDisciplineCases, getSeasons methods
3. src/hooks/useDisciplineCases.js exports all four hooks
4. Backend /rondo/v1/current-season endpoint is registered and responds correctly
5. WordPress REST API exposes /wp/v2/discipline-cases and /wp/v2/seizoen endpoints

## Commits

| Hash | Description |
|------|-------------|
| ae5ecf52 | feat(134-01): add discipline cases and seasons API methods |
| 047fa1c0 | feat(134-01): create useDisciplineCases hook with TanStack Query |
| 1985848d | feat(134-01): add current-season REST endpoint |

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

Ready for Plan 02 (List Page UI):
- Hooks available for data fetching
- Season filtering ready for dropdown
- Current season endpoint available for default filter
