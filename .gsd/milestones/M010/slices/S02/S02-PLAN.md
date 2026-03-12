# S02: Age-group access filtering

**Goal:** Users with age-group-restricted roles (and no management capabilities) see only members matching their permitted leeftijdsgroep values. The capability matrix UI has a "Ledendata" column with per-role multi-select for age groups. The `/rondo/v1/me` endpoint exposes `permitted_age_groups` for the frontend.
**Demo:** Admin opens Settings → Beheer → Capabilities, sees "Ledendata" column, assigns specific leeftijdsgroep values to `rondo_user`. A user with only the `rondo_user` role and those age-group restrictions sees a filtered People list (via the custom SQL endpoint) and filtered standard REST API results. The `/rondo/v1/me` endpoint returns the correct `permitted_age_groups` array. Users with management capabilities (manage_options, fairplay, vog, financieel, toegangscontrole, manage_clothing) bypass filtering entirely.

## Must-Haves

- `rondo_age_group_access` wp_option stores per-role arrays of permitted leeftijdsgroep values
- `AccessControl::get_permitted_age_groups($user_id)` returns `string[]|null` (null = no restriction)
- Age-group filtering applied at 3 points: `filter_queries()` (WP_Query), `filter_rest_query()` (REST), `get_filtered_people()` (custom SQL)
- `filter_rest_single_access()` returns 403 for person posts outside permitted age groups
- Users with ANY management capability bypass age-group filtering entirely
- `GET/POST /rondo/v1/settings/age-group-access` REST endpoints for CRUD
- `/rondo/v1/me` includes `permitted_age_groups: string[] | null`
- CapabilitiesTab has "Ledendata" column with multi-select per role
- Static `$suppress_age_group_filter` flag for Kaderlijst bypass (S03 will use)
- `npm run build` and `npm run lint` pass
- PHP syntax valid on all modified files

## Proof Level

- This slice proves: integration
- Real runtime required: yes (production deploy + verification with real user accounts)
- Human/UAT required: yes (admin should verify matrix UI and member visibility)

## Verification

- `php -l includes/class-access-control.php` — no syntax errors
- `php -l includes/class-rest-api.php` — no syntax errors
- `php -l includes/class-rest-people.php` — no syntax errors
- `npm run build` — zero errors
- `npm run lint` — zero warnings
- Production: `GET /rondo/v1/settings/age-group-access` returns current config
- Production: `POST /rondo/v1/settings/age-group-access` saves and returns updated config
- Production: `GET /rondo/v1/user/me` includes `permitted_age_groups` field
- Production: CapabilitiesTab shows "Ledendata" column with multi-select
- WPUnit test: `tests/Wpunit/AgeGroupAccessTest.php` — `get_permitted_age_groups()` returns correct results for various role/capability combinations

## Observability / Diagnostics

- Runtime signals: `error_log()` when age-group option read fails (corrupted data); AccessControl logs nothing on successful filtering (expected to be silent)
- Inspection surfaces: `GET /rondo/v1/settings/age-group-access` — shows current per-role age-group config; `GET /rondo/v1/user/me` — shows `permitted_age_groups` for current user; `wp option get rondo_age_group_access --format=json` via WP-CLI
- Failure visibility: `permitted_age_groups: null` in `/me` response means no restriction (either management caps present or no config); empty array in option means no restriction for that role (distinct from configured restriction with specific values)
- Redaction constraints: none (age-group config is not PII)

## Integration Closure

- Upstream surfaces consumed: Capability matrix REST endpoints from S01, `UserRoles::ROLES` constant for role slug enumeration, `get_filter_options()` endpoint for available leeftijdsgroep values
- New wiring introduced in this slice: `AccessControl` age-group filtering hooks into existing `filter_queries`, `filter_rest_query`, `filter_rest_single_access` methods and `get_filtered_people` custom SQL; new REST endpoints for age-group access config; `/me` endpoint extended; CapabilitiesTab UI extended
- What remains before the milestone is truly usable end-to-end: S03 frontend filtering (People list auto-filter, person detail access denied, Kaderlijst bypass, prevent restricted users from corrupting Kaderlijst snapshot)

## Tasks

- [x] **T01: Backend storage, helper methods, and query filtering** `est:45m`
  - Why: Core backend — stores age-group config, provides `get_permitted_age_groups()`, and applies filtering at all 3 query points plus single-access control. This is the foundation everything else depends on.
  - Files: `includes/class-access-control.php`, `includes/class-rest-people.php`, `tests/Wpunit/AgeGroupAccessTest.php`
  - Do: Add `rondo_age_group_access` option read/write, `get_permitted_age_groups()` static method with management capability bypass, `has_age_group_restriction()` helper. Modify `filter_queries()` to add leeftijdsgroep meta_query for person queries. Modify `filter_rest_query()` to add same meta_query for REST person queries. Modify `get_filtered_people()` to add SQL JOIN+WHERE for leeftijdsgroep. Modify `filter_rest_single_access()` to check age-group on person posts. Add static `$suppress_age_group_filter` flag. Write WPUnit test for `get_permitted_age_groups()`.
  - Verify: `php -l includes/class-access-control.php && php -l includes/class-rest-people.php` — no syntax errors. Test file exists with assertions.
  - Done when: All 3 filtering points have age-group logic, `filter_rest_single_access` blocks restricted person access, suppress flag exists, WPUnit test written.

- [x] **T02: REST endpoints for age-group access and /me extension** `est:30m`
  - Why: Exposes age-group config to admin UI (GET/POST settings) and user's permitted age groups to frontend (/me). These are the S02→S03 boundary contract surfaces.
  - Files: `includes/class-rest-api.php`, `src/api/client.js`
  - Do: Register `GET/POST /rondo/v1/settings/age-group-access` routes. GET reads `rondo_age_group_access` option and returns per-role arrays with available age groups from filter-options. POST validates and saves. Add `permitted_age_groups` to `get_current_user()` response using `AccessControl::get_permitted_age_groups()`. Add `getAgeGroupAccess()` and `updateAgeGroupAccess()` to frontend API client.
  - Verify: `php -l includes/class-rest-api.php` — no syntax errors. `npm run build` passes.
  - Done when: Both REST endpoints work, `/me` includes `permitted_age_groups`, API client has both methods.

- [x] **T03: CapabilitiesTab UI — "Ledendata" column with multi-select** `est:40m`
  - Why: Admin needs to configure which age groups each role can access. This is the user-facing configuration surface.
  - Files: `src/pages/Settings/Settings.jsx`
  - Do: Extend CapabilitiesTab to fetch age-group access config alongside capability matrix. Add "Ledendata" column after capability columns. Each cell shows a multi-select dropdown/popover with available leeftijdsgroep values (fetched from filter-options endpoint). Save age-group access separately from capability matrix. Handle loading/saving states.
  - Verify: `npm run build && npm run lint` — zero errors/warnings. Visual inspection of Ledendata column.
  - Done when: CapabilitiesTab shows Ledendata column, multi-select works per role, save persists selections.

- [x] **T04: Version bump, changelog, deploy, and production verification** `est:15m`
  - Why: Rule 8 requires deploy before UAT. Version bump and changelog per Rules 1 & 2.
  - Files: `style.css`, `package.json`, `CHANGELOG.md`
  - Do: Bump version to 32.1.0 (minor — new feature, backward compatible). Update CHANGELOG.md with S02 changes. Run build, lint, deploy. Verify on production: age-group access endpoints work, /me includes permitted_age_groups, CapabilitiesTab shows Ledendata column.
  - Verify: Production `GET /rondo/v1/settings/age-group-access` returns config. Production `GET /rondo/v1/user/me` includes `permitted_age_groups`. CapabilitiesTab visible and functional on production.
  - Done when: v32.1.0 live on production, all endpoints respond correctly, UI renders.

## Files Likely Touched

- `includes/class-access-control.php`
- `includes/class-rest-people.php`
- `includes/class-rest-api.php`
- `src/api/client.js`
- `src/pages/Settings/Settings.jsx`
- `tests/Wpunit/AgeGroupAccessTest.php`
- `style.css`
- `package.json`
- `CHANGELOG.md`
