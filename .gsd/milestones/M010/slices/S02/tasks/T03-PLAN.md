---
estimated_steps: 5
estimated_files: 1
---

# T03: CapabilitiesTab UI — "Ledendata" column with multi-select

**Slice:** S02 — Age-group access filtering
**Milestone:** M010

## Description

Extend the CapabilitiesTab component in Settings to show a "Ledendata" column in the role×capability matrix. Each role row gets a multi-select control showing available leeftijdsgroep values. The admin can select which age groups each role is permitted to see. Selections are saved separately from the capability matrix via the `updateAgeGroupAccess()` API endpoint. Users with management capabilities in a role show "Alle leden" (non-configurable, since they bypass filtering).

## Steps

1. **Fetch age-group access data when CapabilitiesTab is active:**
   - In the existing `useEffect` that fetches capability matrix (around line ~233), also fetch `prmApi.getAgeGroupAccess()` in parallel
   - Store in new state: `ageGroupAccess` (per-role config), `availableAgeGroups` (list of available values), `ageGroupAccessLoading`, `ageGroupAccessSaving`, `ageGroupAccessMessage`
   - Pass these to CapabilitiesTab as props

2. **Add "Ledendata" column header to the matrix table:**
   - After the capability column headers, add a "Ledendata" column header
   - Use wider column (w-48) since it contains a multi-select, not just a checkbox

3. **Add per-role age-group cell with multi-select:**
   - For each role row, add a Ledendata cell
   - If the role has ANY management capability (check `roleData.capabilities` for fairplay, vog, financieel, toegangscontrole, manage_clothing, or role is administrator): show "Alle leden" text (non-editable, gray text)
   - Otherwise: show a multi-select dropdown using a button that toggles a dropdown panel
   - Dropdown shows all `availableAgeGroups` as checkboxes
   - Selected values shown as comma-separated abbreviated text in the cell (e.g., "O7, O8, O9" or "3 groepen" if more than 3)
   - No selection = "Alle leden" (no restriction)

4. **Implement age-group selection state management:**
   - Track `ageGroupState` separately from `capabilityMatrix` (different save endpoint)
   - On checkbox toggle in dropdown, update local state immediately
   - On capability matrix change that adds a management cap to a role, auto-clear that role's age-group restriction (since they now bypass)

5. **Add save handler for age-group access:**
   - Separate "Opslaan" handles both saves (capability matrix first, then age-group access) or save age-group access alongside capability matrix in a single save flow
   - Better UX: single "Opslaan" button saves both matrix and age-group access sequentially
   - Show combined saving state and unified success/error message
   - On save success, update local state from response

## Must-Haves

- [ ] "Ledendata" column visible in CapabilitiesTab table
- [ ] Roles with management capabilities show "Alle leden" (not configurable)
- [ ] Roles without management capabilities show multi-select with available age groups
- [ ] Multi-select shows available leeftijdsgroep values as checkboxes
- [ ] Selected values display as abbreviated text in cell
- [ ] Save button persists age-group access via `updateAgeGroupAccess()` API
- [ ] Dark mode support consistent with existing CapabilitiesTab styling
- [ ] Loading state while fetching age-group access data

## Verification

- `npm run build` — zero errors
- `npm run lint` — zero warnings
- Visual: CapabilitiesTab table shows "Ledendata" column header
- Visual: Administrator row shows "Alle leden" in Ledendata column
- Visual: rondo_user row shows multi-select dropdown in Ledendata column
- Visual: Selecting age groups and saving persists the selection

## Observability Impact

- Signals added/changed: None (UI-only changes; save errors shown via existing message pattern)
- How a future agent inspects this: Open Settings → Beheer → Capabilities in browser; check for "Ledendata" column header; inspect network tab for `age-group-access` API calls
- Failure state exposed: Error message below save button on API failure (consistent with existing matrix save pattern)

## Inputs

- `src/pages/Settings/Settings.jsx` — Existing CapabilitiesTab component (lines 2959-3072) and state management (lines 142-246)
- `src/api/client.js` — `getAgeGroupAccess()` and `updateAgeGroupAccess()` methods from T02
- T02's `GET /rondo/v1/settings/age-group-access` response shape: `{ roles: { slug: string[] }, available_age_groups: string[] }`

## Expected Output

- `src/pages/Settings/Settings.jsx` — Extended CapabilitiesTab with "Ledendata" column, multi-select dropdown per role, combined save handler, and age-group access state management
