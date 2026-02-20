---
phase: 204-functie-to-role-mapping-config
verified: 2026-02-20T17:14:15Z
status: passed
score: 5/5 must-haves verified
re_verification: false
---

# Phase 204: Functie-to-Role Mapping Config Verification Report

**Phase Goal:** Admin can configure which Sportlink Functies grant which Rondo capabilities, with Functies populated automatically
**Verified:** 2026-02-20T17:14:15Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Admin sees a checkbox matrix in Settings > Beheer > Functies with known Sportlink Functies as rows and Rondo roles as columns | VERIFIED | `ADMIN_SUBTABS` has `{ id: 'functies', label: 'Functies' }` at line 32; `FunctiesTab` renders a table with `allFuncties.map` as rows and `roles.map` as columns (lines 1587, 1578) |
| 2 | Admin can check/uncheck cells to define which Functie grants which role and save the mapping | VERIFIED | `handleCheckboxChange` updates local state (line 1541-1549); `handleFunctiesSave` POSTs to `updateFunctieCapabilityMap` (line 200); save button wired at line 1618 |
| 3 | Known Functies appear automatically in the matrix (populated from work_history job_title data, not typed manually) | VERIFIED | `useEffect` on mount calls `prmApi.getAvailableWerkfuncties()` in `Promise.all` alongside the map fetch (lines 179-182); `setAvailableFuncties(werkfunctiesResponse.data)` at line 183 |
| 4 | The mapping persists across page refreshes and browser sessions | VERIFIED | `FunctieCapabilityMap::update_map()` calls `update_option(self::OPTION_KEY, $map)` (WordPress Options API); on save response, state is refreshed from server response (lines 201-202) |
| 5 | Functies that exist in the saved map but are no longer in the database still appear in the matrix with a '(niet meer actief)' label | VERIFIED | `isStale = !availableFuncties.includes(functie)` (line 1588); stale span renders at line 1593-1595; `allFuncties` is a union of available + `Object.keys(functieMapState)` (lines 1536-1539) |

**Score:** 5/5 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-functie-capability-map.php` | Config class with `get_map()`, `update_map()`, `get_roles_for_functie()` static methods | VERIFIED | All three static methods present with correct implementations (lines 44, 55, 68); `OPTION_KEY = 'rondo_functie_capability_map'`; ABSPATH guard present |
| `includes/class-rest-api.php` | GET and POST `/rondo/v1/functie-capability-map` endpoints | VERIFIED | Route registered at line 963; GET callback `get_functie_capability_map` at line 4264; POST callback `update_functie_capability_map` at line 4279; `get_rondo_roles_list()` helper at line 4250; both use `check_admin_permission` |
| `src/api/client.js` | `getFunctieCapabilityMap` and `updateFunctieCapabilityMap` API methods | VERIFIED | Both methods at lines 261-262 using correct paths |
| `src/pages/Settings/Settings.jsx` | `FunctiesTab` component with checkbox matrix UI and Functies subtab in ADMIN_SUBTABS | VERIFIED | `FunctiesTab` function at line 1525, fully implemented (not a stub); 6 state variables declared at lines 101-106; `ADMIN_SUBTABS` at lines 29-33 includes `{ id: 'functies', label: 'Functies' }` |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `src/pages/Settings/Settings.jsx` | `/rondo/v1/functie-capability-map` | `prmApi.getFunctieCapabilityMap()` and `prmApi.updateFunctieCapabilityMap()` | WIRED | Both calls confirmed at lines 181 and 200; responses consumed at lines 184-185, 201-202 |
| `src/pages/Settings/Settings.jsx` | `/rondo/v1/werkfuncties/available` | `prmApi.getAvailableWerkfuncties()` | WIRED | Called at line 180 in `Promise.all`; result consumed at line 183 |
| `includes/class-rest-api.php` | `includes/class-functie-capability-map.php` | `\Rondo\Config\FunctieCapabilityMap::get_map()` and `::update_map()` | WIRED | Static calls at lines 4267, 4297, 4301 |

### Requirements Coverage

| Requirement | Status | Notes |
|-------------|--------|-------|
| Admin-configurable Functie-to-role mapping | SATISFIED | Full checkbox matrix in Settings > Beheer > Functies |
| Functies auto-populated from work_history data | SATISFIED | Reuses existing `GET /rondo/v1/werkfuncties/available` endpoint |
| Mapping persists across sessions | SATISFIED | WordPress Options API via `FunctieCapabilityMap::update_map()` |
| Phase 206 callable via `get_roles_for_functie()` | SATISFIED | Static method implemented correctly at line 68 of class file |
| Stale Functies remain visible with label | SATISFIED | Union row list + `isStale` detection + "(niet meer actief)" label |
| Developer documentation updated | SATISFIED | `developer/src/content/docs/features/access-control.md` has full "Functie-to-Role Mapping" section |

### Anti-Patterns Found

None found. No TODO/FIXME/placeholder comments, no empty implementations, no stub return values in the phase-modified files.

### Human Verification Required

#### 1. Checkbox matrix renders with real Sportlink Functies

**Test:** Log in as admin on production, navigate to Settings > Beheer > Functies
**Expected:** Matrix shows actual Functies from `work_history` post meta as rows, four Rondo role columns (Rondo User, Rondo FairPlay, Rondo VOG, Rondo Bestuur)
**Why human:** Requires database to have work_history data and a logged-in admin session

#### 2. Save-and-persist roundtrip

**Test:** Check several cells, click Opslaan, then hard-refresh the page (Ctrl+Shift+R)
**Expected:** Checkboxes remain in the same state after refresh; success message "Functietoewijzing opgeslagen." appears after save
**Why human:** Requires browser interaction and page reload to confirm state persistence

#### 3. Stale Functie label

**Test:** After saving a mapping with at least one Functie, verify a stale Functie (one not currently in work_history) shows "(niet meer actief)" — this may require temporarily saving a mapping with a manually crafted POST, or waiting for the UI to show a Functie that was later removed from sync data
**Why human:** Requires controlled data state to trigger the stale condition

### Gaps Summary

No gaps found. All five observable truths are verified. All four required artifacts exist with substantive implementations. All three key links are wired end-to-end. No anti-patterns detected.

The FunctiesTab component (lines 1524-1641) is fully implemented with:
- Real stale-row detection logic
- Checkbox state management with correct onChange handlers
- Promise.all data fetch on mount (admin-only)
- Save handler that refreshes state from server response
- Empty-state message for no Functies scenario
- Loading spinner

The PHP class and REST endpoints are complete with proper sanitization, admin permission checks, and correct use of WordPress Options API.

---

_Verified: 2026-02-20T17:14:15Z_
_Verifier: Claude (gsd-verifier)_
