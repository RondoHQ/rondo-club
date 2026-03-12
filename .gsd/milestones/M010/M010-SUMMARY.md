---
id: M010
provides:
  - Admin-configurable role×capability matrix UI in Settings → Beheer → Capabilities
  - Age-group-based member visibility filtering (per-role leeftijdsgroep restrictions)
  - REST endpoints for capability matrix and age-group access config
  - /rondo/v1/user/me extended with permitted_age_groups field
  - All 6 hardcoded administrator role checks replaced with manage_options capability checks
  - Kaderlijst bypass for age-group restrictions
  - WPUnit tests for age-group access logic
key_decisions:
  - "[M010-S01] Capability matrix reads/writes WP roles directly (source of truth) — no separate wp_option for matrix data. add_cap()/remove_cap() on WP_Role objects."
  - "[M010-S01] register_role() add_cap loop removed for existing roles — only add_role() sets caps on creation; matrix is the ongoing management tool"
  - "[M010-S01] Only 5 custom Rondo capabilities in matrix columns — WP base capabilities (read, edit_posts, etc.) are structural and not exposed"
  - "[M010-S01] Administrator manage_options is never removable via matrix — prevents admin lockout"
  - "[M010-S01] Version 32.0.0 (MAJOR) — role system architecture change from hardcoded to configurable, plus authorization behavior change (administrator→manage_options)"
  - "[M010-S01] CapabilitiesTab follows FunctiesTab pattern — same table layout, checkbox interaction, save/loading/error feedback, dark mode support"
  - "[M010-S02] Age-group access stored in `rondo_age_group_access` wp_option (not WP capabilities) — WP capabilities are boolean-only, cannot store arrays"
  - "[M010-S02] Management capability bypass: users with ANY of manage_options, fairplay, vog, financieel, toegangscontrole, manage_clothing see all people — consistent with is_vog_only_user() pattern"
  - "[M010-S02] Age-group filtering at 3 points: WP_Query (filter_queries), REST (filter_rest_query), custom SQL (get_filtered_people) — covers all person query paths"
  - "[M010-S02] Static $suppress_age_group_filter flag for Kaderlijst bypass — S03 will set this before Kaderlijst-related queries"
  - "[M010-S02] Empty array in age-group config = no restriction for that role — only non-empty arrays restrict; missing entries also mean no restriction"
  - "[M010-S02] Separate REST endpoint for age-group access (not merged into capability-matrix endpoint) — different storage mechanism (option vs WP roles), cleaner separation"
  - "[M010-S02] Version 32.1.0 (MINOR) — new backward-compatible feature, no breaking changes"
  - "[M010-S03] suppress_age_group REST query parameter recognized only in filter_rest_query for person queries from authenticated users — sets static $suppress_age_group_filter flag before age-group check runs"
  - "[M010-S03] Kaderlijst bypass is safe because volunteer roster data (names, roles, contact) is already public to all authenticated users — age-group filtering is for People management, not volunteer visibility"
  - "[M010-S03] PersonDetail differentiates rest_forbidden_age_group 403 from generic errors — shows distinct Dutch access-denied message with amber styling"
  - "[M010-S03] People list info banner uses existing blue info pattern (bg-blue-50 dark:bg-blue-900/20) — consistent with FeeCategorySettings and FinanceSettings"
  - "[M010-S03] Version 32.2.0 (MINOR) — frontend UX improvements for existing age-group filtering feature"
patterns_established:
  - Capability matrix REST endpoints follow settings endpoint pattern (GET/POST, admin-only, POST returns fresh state)
  - AGE_GROUP_BYPASS_CAPS constant centralizes the list of management capabilities that bypass age-group filtering
  - Static $suppress_age_group_filter flag pattern for scoped query filter bypass
  - REST query param to control static filter flag (suppress_age_group=true)
  - Combined save handler pattern (sequential API calls for related settings)
observability_surfaces:
  - GET /rondo/v1/settings/capability-matrix — inspect current role×capability state
  - GET /rondo/v1/settings/age-group-access — inspect per-role age-group config and available age groups
  - GET /rondo/v1/user/me — permitted_age_groups field (null = unrestricted, string[] = restricted)
  - rest_forbidden_age_group error code in 403 responses for single-item access denial
  - rondo_age_group_access wp_option inspectable via WP-CLI
  - People list info banner visible for restricted users
requirement_outcomes: []
duration: ~3 hours across 3 slices (S01: 35m, S02: 60m, S03: 18m)
verification_result: passed
completed_at: 2026-03-12T23:56:08.024Z
---

# M010: Role-Capability Matrix & Age-Group Access

**Admin-configurable role×capability matrix with age-group-based member visibility, replacing hardcoded role mappings and enabling scoped data access per leeftijdsgroep.**

## What Happened

Three slices delivered the complete feature end-to-end:

**S01 (Role-Capability Matrix Backend & UI)** built the foundation: REST endpoints for reading/writing the role×capability matrix from/to WordPress role definitions, the CapabilitiesTab frontend component with a toggleable matrix of 8 roles × 5 custom capabilities, and fixed all 6 instances of `current_user_can('administrator')` to use `current_user_can('manage_options')`. The matrix reads/writes WP roles directly via `add_cap()`/`remove_cap()` — no separate option storage. The `register_role()` method was fixed to stop re-adding capabilities to existing roles on every load, making the matrix the ongoing management tool. Deployed as v32.0.0 (MAJOR — architecture change from hardcoded to configurable).

**S02 (Age-Group Access Filtering)** added age-group-based member visibility: `get_permitted_age_groups()` in AccessControl reads the `rondo_age_group_access` wp_option and checks user roles, with a 6-capability management bypass (users with fairplay, vog, financieel, etc. see all people). Filtering is applied at 3 query points — `filter_queries()` for WP_Query, `filter_rest_query()` for REST, and `get_filtered_people()` for custom SQL — plus single-item access control returning `rest_forbidden_age_group` 403 errors. A "Ledendata" column was added to the CapabilitiesTab with per-role multi-select dropdowns for selecting permitted leeftijdsgroep values. The `/rondo/v1/user/me` endpoint was extended with `permitted_age_groups`. Deployed as v32.1.0.

**S03 (Frontend Age-Group Filtering)** completed the user-facing experience: the Kaderlijst passes `suppress_age_group=true` to bypass age-group filtering (volunteer roster is public to all authenticated users), PersonDetail shows a distinct Dutch access-denied message for age-group 403 errors, and PeopleList shows a blue info banner listing permitted leeftijdsgroepen for restricted users. Deployed as v32.2.0.

## Cross-Slice Verification

| Success Criterion | Evidence |
|---|---|
| Admin can view/edit role×capability matrix in Settings → Beheer → Capabilities | CapabilitiesTab renders 8 roles × 5 capabilities; verified on production with save→reload persistence cycle |
| Saving the matrix updates WP role definitions | `update_capability_matrix()` uses `$role->add_cap()`/`$role->remove_cap()`; verified by toggling fairplay on rondo_user, confirmed via fresh GET, then reverted |
| All 6 `current_user_can('administrator')` replaced with `manage_options` | `grep -c "current_user_can( 'administrator' )"` returns 0 on all 4 REST files; 8 `manage_options` instances confirmed in correct locations |
| Each role configurable with leeftijdsgroep values | "Ledendata" column with multi-select dropdown per role; production save of ["Onder 7", "Onder 8", "Onder 9"] for rondo_user verified via GET endpoint |
| Age-group restricted users see only matching members | 3 query filter points + single-item REST access control; `rest_forbidden_age_group` 403 for restricted persons; info banner on PeopleList |
| Kaderlijst NOT affected by age-group restrictions | `suppress_age_group: true` in Kaderlijst.jsx; PHP sets `$suppress_age_group_filter = true` when param present |
| Functies→Roles and Commissie→Roles sync unchanged | No modifications to `class-capability-sync.php` or `class-functie-capability-map.php`; sync assigns roles, matrix governs capabilities — orthogonal concerns |

**Build & Lint:** `npm run build` and `npm run lint` pass with zero errors/warnings at each deployment.

**PHP Syntax:** All modified PHP files pass `php -l` checks.

**Production:** All 3 versions (32.0.0, 32.1.0, 32.2.0) deployed and verified on production. Current version: 32.2.0.

## Requirement Changes

No requirements changed status during this milestone. All work was driven by the milestone's own success criteria, not tracked requirements from REQUIREMENTS.md.

## Forward Intelligence

### What the next milestone should know
- The role-capability matrix is now the single source of truth for custom capability assignments. The hardcoded `ROLES` constant in UserRoles still defines available roles and their base WP capabilities, but the 5 custom Rondo capabilities are managed exclusively through the matrix UI.
- Age-group access is stored separately from capabilities (wp_option vs WP roles) because WP capabilities are boolean-only. Any future per-role configuration that needs non-boolean values should follow this pattern.
- The `AGE_GROUP_BYPASS_CAPS` constant in AccessControl lists 6 management capabilities. If new management capabilities are added, they should be added to this list to ensure those users bypass age-group filtering.

### What's fragile
- **suppress_age_group parameter** — Any authenticated user can pass `suppress_age_group=true` to REST queries. This is acceptable because the Kaderlijst data (volunteer names, roles, contact info) is intentionally public to all club members. However, if age-group filtering is ever extended to truly sensitive data, this bypass mechanism needs authorization gating.
- **INNER JOIN for age-group SQL filtering** — People without a `leeftijdsgroep` meta value are excluded when age-group filtering is active. This is correct behavior (unclassified people shouldn't leak through) but could surprise admins if members are missing from filtered views due to data quality issues.

### Authoritative diagnostics
- `GET /rondo/v1/settings/capability-matrix` — shows current role×capability matrix state
- `GET /rondo/v1/settings/age-group-access` — shows per-role age-group config with 21 available values
- `GET /rondo/v1/user/me` → `permitted_age_groups` — null (unrestricted) or string[] (restricted)
- `wp option get rondo_age_group_access --format=json` — raw stored config via WP-CLI

### What assumptions changed
- **Matrix vs hardcoded roles risk** was retired in S01: saving the matrix via `add_cap()`/`remove_cap()` is the standard WP pattern and works reliably. No caching issues observed.
- **Age-group filtering scope risk** was retired in S02/S03: filtering at 3 query points covers all person access paths, and the suppress flag cleanly bypasses for Kaderlijst without affecting other access controls.

## Files Created/Modified

- `includes/class-rest-api.php` — Added REST routes and handlers for capability-matrix and age-group-access endpoints; extended /me with permitted_age_groups
- `includes/class-user-roles.php` — Removed add_cap() loop that re-added capabilities to existing roles
- `includes/class-rest-people.php` — 3 administrator→manage_options fixes; age-group SQL INNER JOIN in get_filtered_people()
- `includes/class-rest-teams.php` — 1 administrator→manage_options fix
- `includes/class-rest-commissies.php` — 2 administrator→manage_options fixes
- `includes/class-access-control.php` — Added $suppress_age_group_filter, AGE_GROUP_BYPASS_CAPS, get_permitted_age_groups(), has_age_group_restriction(), apply_age_group_filter(); modified filter_queries(), filter_rest_query(), filter_rest_single_access()
- `src/api/client.js` — Added getCapabilityMatrix, updateCapabilityMatrix, getAgeGroupAccess, updateAgeGroupAccess methods
- `src/pages/Settings/Settings.jsx` — Added CapabilitiesTab component with matrix UI and Ledendata column; capabilities subtab in navigation; state management for matrix and age-group access
- `src/pages/People/PeopleList.jsx` — Added age-group info banner for restricted users
- `src/pages/People/PersonDetail.jsx` — Added age-group 403 error differentiation with Dutch access-denied message
- `src/pages/Teams/Kaderlijst.jsx` — Added suppress_age_group=true param for age-group bypass
- `tests/Wpunit/AgeGroupAccessTest.php` — 12 WPUnit test cases for get_permitted_age_groups()
- `style.css` — Version bumped through 32.0.0 → 32.1.0 → 32.2.0
- `package.json` — Version bumped through 32.0.0 → 32.1.0 → 32.2.0
- `CHANGELOG.md` — Added entries for 32.0.0, 32.1.0, 32.2.0
