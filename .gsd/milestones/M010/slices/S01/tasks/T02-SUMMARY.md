---
id: T02
parent: S01
milestone: M010
provides:
  - CapabilitiesTab component in Settings → Beheer with role×capability matrix UI
  - API client methods getCapabilityMatrix/updateCapabilityMatrix wired to REST endpoints
  - "Capabilities" subtab in ADMIN_SUBTABS navigation
key_files:
  - src/api/client.js
  - src/pages/Settings/Settings.jsx
key_decisions:
  - POST body uses `roles` key (matching backend param name) not `matrix`
  - capabilityLabels stored as separate state to support human-readable column headers from API
  - Lazy-load pattern: matrix data fetched only when capabilities subtab is active (not on mount)
patterns_established:
  - CapabilitiesTab follows exact same FunctiesTab pattern (props, save handler, loading/error feedback)
  - Matrix state stored as nested object matching API shape: { roleSlug: { label, capabilities: { capSlug: bool } } }
observability_surfaces:
  - Browser DevTools network tab shows GET/POST to /rondo/v1/settings/capability-matrix
  - Error messages displayed inline next to Save button (Fout prefix triggers red styling)
duration: 15m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Frontend — Capabilities subtab with matrix UI and API client wiring

**Built admin-facing Capabilities subtab in Settings → Beheer with toggleable role×capability matrix, save functionality, and full dark mode support.**

## What Happened

All 5 steps executed cleanly:

1. **API client methods** — Added `getCapabilityMatrix` and `updateCapabilityMatrix` to `prmApi` in `client.js`, following the `getFunctieCapabilityMap` pattern.

2. **ADMIN_SUBTABS extended** — Inserted `{ id: 'capabilities', label: 'Capabilities' }` after 'functies' entry.

3. **CapabilitiesTab component** — Built matrix UI with roles as rows, 5 custom capabilities as columns with human-readable labels from API. Administrator row has no special disabled cells (the backend protects manage_options; the 5 matrix capabilities are all custom Rondo ones). Save button with loading spinner and success/error feedback. Full dark mode support matching FunctiesTab classes.

4. **State and data fetching** — Added `capabilityMatrix`, `capabilityLabels`, `capabilityMatrixLoading`, `capabilityMatrixSaving`, `capabilityMatrixMessage` state. Lazy-load useEffect fetches on subtab activation. `handleCapabilityMatrixSave` sends `{ roles: capabilityMatrix }` and updates from response.

5. **AdminTabWithSubtabs wiring** — Added capabilities branch rendering CapabilitiesTab in card wrapper. Threaded all new props through component tree.

## Verification

- `npm run lint` — **0 warnings** ✓
- `npm run build` — **passes** (109 precache entries) ✓
- `php -l` on all 5 PHP files — **no syntax errors** ✓
- `grep -c "current_user_can( 'administrator' )"` — **all return 0** ✓
- REST API curl: `GET /rondo/v1/settings/capability-matrix` returns 8 roles × 5 capabilities with labels ✓
- Production browser: Capabilities subtab visible in navigation, matrix renders all roles and capabilities ✓
- Browser assertions: 8/8 pass (URL, headings, role names, table structure, save button) ✓

## Diagnostics

- Browser DevTools network tab: GET/POST requests to `/rondo/v1/settings/capability-matrix`
- React DevTools: CapabilitiesTab component state shows matrix data
- Error messages displayed inline next to Save button with red/green color coding

## Deviations

- POST body key: Task plan said `{ matrix: capabilityMatrix }` but backend expects `{ roles: capabilityMatrix }`. Fixed to match backend contract.
- capabilityLabels: Added as separate state variable (not in task plan) because the API returns `capability_labels` as a top-level field alongside `roles`, and using it for column headers is cleaner than extracting from first role entry.

## Known Issues

None.

## Files Created/Modified

- `src/api/client.js` — Added `getCapabilityMatrix` and `updateCapabilityMatrix` methods to prmApi
- `src/pages/Settings/Settings.jsx` — Added 'capabilities' to ADMIN_SUBTABS, CapabilitiesTab component (~105 lines), state variables, lazy-load useEffect, save handler, AdminTabWithSubtabs props threading
