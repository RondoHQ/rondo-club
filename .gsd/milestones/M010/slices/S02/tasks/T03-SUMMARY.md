---
id: T03
parent: S02
milestone: M010
provides:
  - "Ledendata column in CapabilitiesTab with per-role multi-select dropdown"
  - "Combined save handler for capability matrix + age-group access"
  - "Auto-clear age-group restriction when management cap added"
key_files:
  - src/pages/Settings/Settings.jsx
key_decisions:
  - "Single 'Opslaan' button saves both capability matrix and age-group access sequentially, rather than separate save buttons"
  - "MANAGEMENT_CAPS list mirrored from PHP AGE_GROUP_BYPASS_CAPS constant in JS for UI-side management cap detection"
  - "Click-outside handler via useRef + mousedown event listener for dropdown dismissal"
  - "Display format: ≤3 items shown comma-separated; >3 items shown as 'N groepen'; empty = 'Alle leden'"
patterns_established:
  - "Age-group access state (ageGroupAccess, availableAgeGroups, ageGroupAccessLoading) managed at Settings root level, passed through AdminTabWithSubtabs to CapabilitiesTab"
  - "Parallel fetch: capability matrix and age-group access loaded together in single Promise.all when capabilities subtab activates"
observability_surfaces:
  - "Success/error message below save button covers both matrix and age-group saves"
  - "Network tab shows sequential POST to /settings/capability-matrix then /settings/age-group-access on save"
duration: 25m
verification_result: passed
completed_at: 2026-03-12T22:19:00+01:00
blocker_discovered: false
---

# T03: CapabilitiesTab UI — "Ledendata" column with multi-select

**Added "Ledendata" column with per-role age-group multi-select dropdown to the CapabilitiesTab, with combined save that persists both capability matrix and age-group access.**

## What Happened

Extended the CapabilitiesTab component in Settings to include a "Ledendata" column after all capability columns. Each role row shows either:
- **"Alle leden"** in italic gray text — for roles with any management capability (fairplay, vog, financieel, toegangscontrole, manage_clothing, manage_options), indicating they bypass age-group filtering
- **Multi-select dropdown** — for roles without management capabilities, showing all available leeftijdsgroep values as checkboxes

The dropdown button displays selected groups as comma-separated text (≤3 items), "N groepen" (>3 items), or "Alle leden" (no selection = no restriction). Click-outside closes the dropdown.

State management: `ageGroupAccess`, `availableAgeGroups`, and `ageGroupAccessLoading` are managed at the Settings component root level, fetched in parallel with the capability matrix via `Promise.all`, and passed down through `AdminTabWithSubtabs` to `CapabilitiesTab`. When a management capability is toggled ON for a role, the role's age-group restriction is auto-cleared.

The single "Opslaan" button saves both the capability matrix and age-group access config sequentially, showing a combined success message.

## Verification

- `npm run build` — zero errors ✅
- `npm run lint` — zero warnings ✅
- Production: CapabilitiesTab shows "Ledendata" column header ✅
- Production: Administrator row shows italic "Alle leden" ✅
- Production: All roles with management caps show italic "Alle leden" ✅
- Production: Rondo User row shows multi-select dropdown ✅
- Production: Selecting "Onder 7", "Onder 8", "Onder 9" and saving persists correctly ✅
- Production: After save, GET /rondo/v1/settings/age-group-access returns `{"roles":{"rondo_user":["Onder 7","Onder 8","Onder 9"]}}` ✅
- Production: After page reload, selected groups still displayed correctly ✅
- Browser assertions: "Ledendata" text visible, "Alle leden" text visible, dropdown button visible, "Capabilities" heading visible — all PASS ✅

### Slice-level verification status (T03 is task 3 of 4):
- `php -l includes/class-access-control.php` — ✅ (T01)
- `php -l includes/class-rest-api.php` — ✅ (T02)
- `php -l includes/class-rest-people.php` — ✅ (T01)
- `npm run build` — ✅
- `npm run lint` — ✅
- Production: `GET /rondo/v1/settings/age-group-access` returns config — ✅
- Production: `POST /rondo/v1/settings/age-group-access` saves and returns updated config — ✅
- Production: `GET /rondo/v1/user/me` includes `permitted_age_groups` — ✅ (T02)
- Production: CapabilitiesTab shows "Ledendata" column with multi-select — ✅
- WPUnit test: `tests/Wpunit/AgeGroupAccessTest.php` — ✅ (T01)

## Diagnostics

- Open Settings → Beheer → Capabilities in browser to see "Ledendata" column
- Network tab shows `age-group-access` API calls on save
- Error message below save button on API failure (consistent with existing matrix save pattern)

## Deviations

None — implementation followed the task plan.

## Known Issues

None.

## Files Created/Modified

- `src/pages/Settings/Settings.jsx` — Added `useRef` import; added `ageGroupAccess`/`availableAgeGroups`/`ageGroupAccessLoading` state; updated capability matrix fetch to use `Promise.all` with age-group access; updated save handler to save both matrix and age-group access sequentially; passed age-group props through `AdminTabWithSubtabs` to `CapabilitiesTab`; rewrote `CapabilitiesTab` with "Ledendata" column, multi-select dropdown, management cap detection, click-outside handler, and display formatting
