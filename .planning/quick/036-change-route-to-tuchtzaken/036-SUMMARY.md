---
phase: quick
plan: 036
subsystem: frontend-routing
tags: [react, routing, i18n, discipline-cases]
completed: 2026-02-03
duration: 40s

dependencies:
  requires:
    - discipline-cases-ui-implementation
  provides:
    - dutch-route-for-discipline-cases
  affects:
    - navigation
    - deep-links

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - src/App.jsx
    - src/components/layout/Layout.jsx

decisions:
  - decision: "Keep API endpoints as /discipline-cases while changing frontend route to /tuchtzaken"
    rationale: "Backend REST API endpoints don't need to match frontend routes; separation allows i18n flexibility"
    alternatives:
      - "Change both frontend and backend routes (would require PHP changes)"
      - "Use route aliases with redirects (adds unnecessary complexity)"
    choice: "Separate frontend/backend routes"
    impact: "Frontend route Dutch, backend API remains English for consistency with WordPress REST conventions"

metrics:
  files_modified: 2
  lines_changed: 4
  commits: 1
---

# Quick Task 036: Change Route to Tuchtzaken

**One-liner:** Changed frontend route from `/discipline-cases` to `/tuchtzaken` for Dutch UI consistency

## Overview

Changed the discipline cases frontend route from English `/discipline-cases` to Dutch `/tuchtzaken` to match the Dutch navigation label "Tuchtzaken". Backend REST API endpoints remain unchanged as `/wp/v2/discipline-cases`.

## Tasks Completed

### Task 1: Update route path and navigation link

**Status:** Complete
**Commit:** 3fd7dfe9

Updated the route definition and navigation link:

1. **App.jsx:** Changed route path from `/discipline-cases` to `/tuchtzaken` (line 258)
2. **Layout.jsx:** Changed navigation href from `/discipline-cases` to `/tuchtzaken` (line 52)

**Files modified:**
- `src/App.jsx` - Route definition
- `src/components/layout/Layout.jsx` - Navigation link

**Verification:**
- ✓ `npm run lint` passes (no new errors introduced)
- ✓ `npm run build` succeeds
- ✓ Only API references to `discipline-cases` remain (client.js, hooks)

## Implementation Details

### Route Structure

**Frontend routing:**
- Route: `/tuchtzaken`
- Navigation label: "Tuchtzaken" (already Dutch)
- Component: `DisciplineCasesList` (unchanged)

**Backend API (unchanged):**
- Endpoint: `/wp/v2/discipline-cases`
- Hook: `useDisciplineCases` with `queryKey: ['discipline-cases']`
- Client methods: `getDisciplineCases()`, `getDisciplineCase(id)`

### Search Results

Verified no hardcoded `/discipline-cases` references in frontend navigation:
```bash
$ grep -r "discipline-cases" src/
src/pages/DisciplineCases/DisciplineCasesList.jsx:97:    await queryClient.invalidateQueries({ queryKey: ['discipline-cases'] });
src/api/client.js:98:  getDisciplineCases: (params) => api.get('/wp/v2/discipline-cases', { params }),
src/api/client.js:99:  getDisciplineCase: (id, params = {}) => api.get(`/wp/v2/discipline-cases/${id}`, { params }),
src/hooks/useDisciplineCases.js:6:  all: ['discipline-cases'],
```

All remaining references are API-related (expected and correct).

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

### Blockers

None.

### Recommendations

1. **Consider route consistency audit:** Review other routes for Dutch/English consistency
2. **Deep link testing:** Test bookmarked URLs or shared links with old `/discipline-cases` path
3. **User communication:** If users have bookmarked old route, consider 404 handling or redirect

### Known Limitations

- Old `/discipline-cases` URLs will now 404 (Navigate to "/" fallback in App.jsx)
- No automatic redirect from old route to new route
- Browser history may contain old URLs

## Commits

| Commit | Type | Description |
|--------|------|-------------|
| 3fd7dfe9 | feat | Change discipline cases route from /discipline-cases to /tuchtzaken |

## Testing Notes

**Manual verification required:**
1. Navigate to `/tuchtzaken` - should display discipline cases list
2. Click "Tuchtzaken" in navigation - should navigate to `/tuchtzaken`
3. Verify URL in browser address bar shows `/tuchtzaken`
4. Test fairplay permission check still works (non-fairplay users get blocked)

## Documentation Updates

No documentation files exist for frontend routing. Consider creating routing reference if more i18n routes are planned.

---

**Summary:** Successfully changed discipline cases route to Dutch `/tuchtzaken` while maintaining English backend API endpoints. No issues encountered, all verification passed.
