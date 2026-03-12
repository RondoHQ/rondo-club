---
estimated_steps: 5
estimated_files: 2
---

# T02: Frontend — Capabilities subtab with matrix UI and API client wiring

**Slice:** S01 — Role-capability matrix backend & UI
**Milestone:** M010

## Description

Build the admin-facing Capabilities subtab in Settings → Beheer that displays a role×capability matrix with toggleable checkboxes and save functionality. This follows the established `FunctiesTab` pattern nearly identically — roles as rows, capabilities as columns, with save/loading/error feedback. Wire it to the REST endpoints created in T01.

## Steps

1. **Add API client methods in `src/api/client.js`** — Add two methods to the `prmApi` object following the `getFunctieCapabilityMap`/`updateFunctieCapabilityMap` pattern:
   - `getCapabilityMatrix: () => api.get('/rondo/v1/settings/capability-matrix')`
   - `updateCapabilityMatrix: (data) => api.post('/rondo/v1/settings/capability-matrix', data)`

2. **Add "Capabilities" to `ADMIN_SUBTABS`** — Insert `{ id: 'capabilities', label: 'Capabilities' }` after the 'functies' entry in the `ADMIN_SUBTABS` array (after index 2, making it index 3).

3. **Create `CapabilitiesTab` component** — Build a new function component in `Settings.jsx` following the `FunctiesTab` pattern:
   - Props: `matrixState`, `setMatrixState`, `loading`, `saving`, `message`, `handleSave`
   - Layout: heading "Capabilities", description text, then a table with role labels as rows and capability labels as columns
   - Each cell has a checkbox reflecting `matrixState[roleSlug].capabilities[capSlug]`
   - The administrator row's `manage_options` cell (if shown) is checked and disabled
   - Only the 5 Rondo custom capabilities are columns (not WP base capabilities)
   - Save button with loading spinner and success/error message — same pattern as FunctiesTab
   - Full dark mode support using the same Tailwind classes as FunctiesTab

4. **Add state and data fetching in `Settings` component** — Add state variables: `capabilityMatrix` (object), `capabilityMatrixLoading` (bool), `capabilityMatrixSaving` (bool), `capabilityMatrixMessage` (string). Add a `useEffect` that fetches `prmApi.getCapabilityMatrix()` when admin is on the capabilities subtab (lazy load pattern matching `welcomeSettings`). Add `handleCapabilityMatrixSave` function that calls `prmApi.updateCapabilityMatrix({ matrix: capabilityMatrix })`, updates state from response, and shows success/error message.

5. **Wire subtab rendering in `AdminTabWithSubtabs`** — Add a conditional branch for `activeSubtab === 'capabilities'` that renders `<CapabilitiesTab>` inside a `<div className="card p-6">` wrapper, passing all required props. Thread the new state variables and handler through the `AdminTabWithSubtabs` props (same pattern as FunctiesTab props).

## Must-Haves

- [ ] `getCapabilityMatrix` and `updateCapabilityMatrix` API client methods added
- [ ] "Capabilities" subtab appears in Beheer tab navigation after "Functies"
- [ ] Matrix table shows all Rondo roles + administrator as rows
- [ ] Matrix table shows 5 custom capabilities as columns with human-readable labels
- [ ] Checkboxes toggle capability state per role
- [ ] Administrator `manage_options` is checked and disabled (cannot remove)
- [ ] Save button calls POST endpoint and shows loading/success/error feedback
- [ ] Data loads on subtab navigation (lazy fetch pattern)
- [ ] Full dark mode support
- [ ] `npm run build` passes
- [ ] `npm run lint` passes with zero warnings

## Verification

- `npm run build` — compiles successfully
- `npm run lint` — zero warnings/errors
- Code review: CapabilitiesTab follows FunctiesTab pattern, props threaded correctly through component tree

## Observability Impact

- Signals added/changed: None (frontend uses existing REST error handling patterns)
- How a future agent inspects this: Browser DevTools network tab shows GET/POST requests to `/rondo/v1/settings/capability-matrix`; React DevTools shows CapabilitiesTab state
- Failure state exposed: Error message displayed next to Save button (same pattern as FunctiesTab)

## Inputs

- `src/api/client.js` — existing `getFunctieCapabilityMap`/`updateFunctieCapabilityMap` pattern (lines 288-292)
- `src/pages/Settings/Settings.jsx` — `ADMIN_SUBTABS` array (lines 36-43), `FunctiesTab` component (lines 2645-2775), `AdminTabWithSubtabs` component (lines 1940+), state/useEffect patterns (lines 140-260)
- T01 output — REST endpoints at `/rondo/v1/settings/capability-matrix` returning `{ roles: { slug: { label, capabilities: { cap: bool } } } }`

## Expected Output

- `src/api/client.js` — two new `prmApi` methods for capability matrix CRUD
- `src/pages/Settings/Settings.jsx` — `ADMIN_SUBTABS` extended with 'capabilities' entry, `CapabilitiesTab` component defined, state variables and useEffect added, `AdminTabWithSubtabs` wired to render the new subtab
