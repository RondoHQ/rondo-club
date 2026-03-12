# S01: Role-Capability Matrix Backend & UI

**Goal:** Admin can view a matrix of all roles × Rondo capabilities in Settings → Beheer → Capabilities, toggle capabilities per role, save changes that update WordPress role definitions, and all 6 hardcoded `current_user_can('administrator')` checks are replaced with `current_user_can('manage_options')`.

**Demo:** Navigate to Settings → Beheer → Capabilities. See a table with Rondo roles as rows and 5 custom capabilities (fairplay, vog, financieel, toegangscontrole, manage_clothing) as columns. Checkboxes reflect current role capabilities. Toggle a checkbox, click Save, and the change persists. The `register_role()` method no longer re-adds capabilities to existing roles, so matrix changes stick across page loads.

## Must-Haves

- REST `GET /rondo/v1/settings/capability-matrix` returns `{ roles: { slug: { label, capabilities: { cap: bool } } } }`
- REST `POST /rondo/v1/settings/capability-matrix` accepts the same shape and writes via `add_cap()`/`remove_cap()`
- Both endpoints require `manage_options` permission
- "Capabilities" subtab appears in Settings → Beheer after "Functies"
- Matrix UI shows roles as rows, capabilities as columns, with toggle checkboxes
- Save button persists changes and shows success/error feedback
- Administrator role's `manage_options` capability is NOT toggleable (prevent lockout)
- `register_role()` no longer re-adds capabilities to already-existing roles
- All 6 `current_user_can('administrator')` instances replaced with `current_user_can('manage_options')`
- `npm run build` and `npm run lint` pass with zero warnings
- Deployed to production and verified working

## Proof Level

- This slice proves: operational (deployed to production, admin can use the matrix)
- Real runtime required: yes (WordPress production environment for verification)
- Human/UAT required: yes (admin tests matrix save/load, capability changes reflected)

## Verification

- `npm run build` — frontend compiles without errors
- `npm run lint` — zero warnings/errors
- `php -l includes/class-rest-api.php includes/class-user-roles.php includes/class-rest-people.php includes/class-rest-teams.php includes/class-rest-commissies.php` — PHP syntax valid
- `grep -c "current_user_can( 'administrator' )" includes/class-rest-people.php includes/class-rest-teams.php includes/class-rest-commissies.php` — returns 0 for all files
- Production: Settings → Beheer → Capabilities tab visible and functional
- Production: Save matrix → reload page → same state persists

## Observability / Diagnostics

- Runtime signals: WordPress REST API returns standard HTTP status codes (200 success, 403 forbidden, 500 error) with descriptive error messages via `WP_Error`
- Inspection surfaces: `GET /rondo/v1/settings/capability-matrix` returns current role×capability state; `wp_roles()->roles` in WP-CLI shows raw role definitions
- Failure visibility: REST error responses include message field; PHP errors logged to WordPress error log; frontend shows error message string next to Save button
- Redaction constraints: none (no secrets or PII in capability data)

## Integration Closure

- Upstream surfaces consumed: `UserRoles::ROLES` constant for role labels, `wp_roles()` WordPress API for capability state
- New wiring introduced in this slice: REST route registration in `class-rest-api.php`, subtab entry in `ADMIN_SUBTABS`, API client methods in `client.js`, `CapabilitiesTab` component in `Settings.jsx`
- What remains before the milestone is truly usable end-to-end: S02 (age-group access filtering backend) and S03 (frontend age-group filtering) — the matrix is fully functional standalone but the age-group column comes in S02

## Tasks

- [x] **T01: Backend — REST endpoints, register_role fix, and role-name check fixes** `est:45m`
  - Why: Provides the data layer the UI depends on, fixes the critical `register_role()` cap-override bug, and replaces all 6 hardcoded administrator role checks with capability checks
  - Files: `includes/class-rest-api.php`, `includes/class-user-roles.php`, `includes/class-rest-people.php`, `includes/class-rest-teams.php`, `includes/class-rest-commissies.php`
  - Do: (1) Add `GET/POST /rondo/v1/settings/capability-matrix` routes in `class-rest-api.php` following the `functie-capability-map` pattern. GET reads `wp_roles()->roles` and builds matrix of Rondo roles × 5 custom capabilities. POST diffs desired vs current state and calls `add_cap()`/`remove_cap()`. Both use `check_admin_permission()`. (2) Fix `register_role()` in `class-user-roles.php` — remove the `add_cap()` loop on existing roles (lines 78-82) so it only sets caps when creating a NEW role via `add_role()`. (3) Replace all 6 `current_user_can( 'administrator' )` with `current_user_can( 'manage_options' )` in the 3 REST controller files.
  - Verify: `php -l` on all 5 files passes; `grep` confirms zero `current_user_can( 'administrator' )` remaining
  - Done when: All endpoints registered, role fix applied, all 6 checks fixed, PHP syntax valid

- [x] **T02: Frontend — Capabilities subtab with matrix UI and API client wiring** `est:45m`
  - Why: Delivers the admin-facing UI that reads/writes the matrix via the T01 endpoints
  - Files: `src/pages/Settings/Settings.jsx`, `src/api/client.js`
  - Do: (1) Add `getCapabilityMatrix` and `updateCapabilityMatrix` methods to `prmApi` in `client.js`. (2) Add `{ id: 'capabilities', label: 'Capabilities' }` to `ADMIN_SUBTABS` after 'functies'. (3) Create `CapabilitiesTab` component following `FunctiesTab` pattern: roles as rows, capabilities as columns, checkboxes, save button with loading/success/error feedback. Administrator `manage_options` shown as checked+disabled. (4) Add state variables and useEffect data fetching in Settings component. (5) Wire subtab rendering in `AdminTabWithSubtabs`.
  - Verify: `npm run build` and `npm run lint` pass with zero warnings
  - Done when: Capabilities subtab renders in Beheer, matrix loads from API, save writes to API, build clean

- [x] **T03: Version bump, changelog, deploy, and production verification** `est:20m`
  - Why: Ships the feature to production and verifies it works end-to-end with real data
  - Files: `style.css`, `package.json`, `CHANGELOG.md`
  - Do: (1) Bump version to 32.0.0 (MAJOR — role system architecture change: matrix replaces hardcoded caps, administrator checks replaced with capability checks). (2) Update CHANGELOG.md with Added/Changed entries. (3) Run `npm run build` final check. (4) Deploy via `bin/deploy.sh`. (5) Verify on production: Capabilities tab loads, matrix shows current state, save works, page reload preserves changes.
  - Verify: Production Settings → Beheer → Capabilities works end-to-end
  - Done when: Deployed, matrix functional on production, version 32.0.0 live

## Files Likely Touched

- `includes/class-rest-api.php` — new capability-matrix REST routes
- `includes/class-user-roles.php` — fix register_role() cap override bug
- `includes/class-rest-people.php` — 3 administrator→manage_options fixes
- `includes/class-rest-teams.php` — 1 administrator→manage_options fix
- `includes/class-rest-commissies.php` — 2 administrator→manage_options fixes
- `src/pages/Settings/Settings.jsx` — CapabilitiesTab component + subtab wiring
- `src/api/client.js` — getCapabilityMatrix/updateCapabilityMatrix methods
- `style.css` — version bump
- `package.json` — version bump
- `CHANGELOG.md` — release notes
