# S01: Dynamic roles backend & default inversion

**Goal:** Replace the hardcoded `ROLES` constant with a dynamic method that merges base + custom roles, add REST endpoints for custom role CRUD, invert the Ledendata default to "see nobody", and update all downstream consumers.
**Demo:** Admin can create a custom role via REST, it appears in the capability matrix and functie/commissie mapping endpoints. A `rondo_user`-only user with no age-group config sees zero members. Management-cap users still see all.

## Must-Haves

- `UserRoles::ROLES` renamed to `BASE_ROLES`, new `get_all_roles()` merges base + custom from wp_option
- Custom roles stored in `rondo_custom_roles` wp_option as `[ slug => label ]`
- `add_custom_role($label)` creates WP role with base capabilities, stores in option, returns slug
- `remove_custom_role($slug)` removes WP role, removes from users, removes from option
- REST `POST /rondo/v1/settings/roles` creates custom role (admin only)
- REST `DELETE /rondo/v1/settings/roles/(?P<slug>[a-z0-9_]+)` deletes custom role (admin only)
- `get_permitted_age_groups()` returns `[]` (not `null`) when user has no management caps and no age-group config — meaning they see zero members
- All 6 `UserRoles::ROLES` reference sites updated to `get_all_roles()`
- `CapabilitySync::sync_user()` includes custom roles in syncable set
- `get_rondo_roles_list()` returns custom roles alongside base roles
- API client methods for role CRUD added to `client.js`

## Proof Level

- This slice proves: integration
- Real runtime required: yes (production deploy for default inversion verification)
- Human/UAT required: no (REST endpoint testing sufficient)

## Verification

- `php -l` on all modified PHP files — no syntax errors
- `npm run build` — zero errors
- `npm run lint` — zero warnings
- `grep -c "UserRoles::ROLES[^_]" includes/` — returns 0 (all replaced with `BASE_ROLES` or `get_all_roles()`)
- `grep -c "get_all_roles" includes/class-user-roles.php` — ≥2 (method definition + usages)
- `grep "rondo_custom_roles" includes/class-user-roles.php` — finds option key
- `grep "settings/roles" includes/class-rest-api.php` — finds route registration
- `grep "createCustomRole\|deleteCustomRole" src/api/client.js` — finds API methods
- Production: `POST /rondo/v1/settings/roles` with `{ label: "Test Role" }` returns slug
- Production: `DELETE /rondo/v1/settings/roles/{slug}` removes the role
- Production: `GET /rondo/v1/settings/capability-matrix` includes custom role
- Production: `GET /rondo/v1/user/me` for rondo_user-only user returns `permitted_age_groups: []` (not null)

## Observability / Diagnostics

- Runtime signals: custom role creation/deletion logged implicitly via wp_option changes
- Inspection surfaces: `wp option get rondo_custom_roles --format=json` via WP-CLI; `GET /rondo/v1/settings/capability-matrix` shows all roles including custom
- Failure visibility: REST endpoints return structured WP_Error with descriptive codes (role_exists, invalid_slug, base_role_protected, role_not_found)
- Redaction constraints: none (no secrets involved)

## Integration Closure

- Upstream surfaces consumed: M010's capability matrix endpoints, age-group access endpoints, AccessControl filtering
- New wiring introduced: custom role storage, dynamic role listing across all consumers
- What remains before milestone is truly usable end-to-end: S02 (UI for creating roles, sticky table columns, production setup)

## Tasks

- [ ] **T01: Replace ROLES constant with dynamic get_all_roles() and add custom role CRUD** `est:20m`
  - Why: The `ROLES` constant is the core blocker — it's hardcoded and referenced everywhere. This task makes roles dynamic and adds the storage/registration methods for custom roles.
  - Files: `includes/class-user-roles.php`
  - Do:
    1. Rename `ROLES` to `BASE_ROLES`
    2. Add `const CUSTOM_ROLES_OPTION = 'rondo_custom_roles'`
    3. Add `get_custom_roles(): array` — reads wp_option, returns `[ slug => label ]` or `[]`
    4. Add `get_all_roles(): array` — merges `BASE_ROLES` with custom roles (custom roles get empty extra_caps `[]`)
    5. Add `add_custom_role( string $label ): string|WP_Error` — sanitizes label to slug with `rondo_` prefix, checks for conflicts, calls `add_role()` with base capabilities, stores in option, returns slug
    6. Add `remove_custom_role( string $slug ): true|WP_Error` — validates it's not a base role, calls `remove_role()`, removes from users via `$user->remove_role($slug)`, removes from option
    7. Update `get_role_slugs()` to use `get_all_roles()`
    8. Update `ensure_role_exists()` to iterate `get_all_roles()` and also register custom roles that don't exist yet
    9. Update `remove_role()` (theme deactivation) to iterate `get_all_roles()` including custom
    10. Update `has_rondo_role()` to use `get_role_slugs()`
  - Verify: `php -l includes/class-user-roles.php` passes; `grep -c "const ROLES " includes/class-user-roles.php` returns 0; `grep -c "BASE_ROLES" includes/class-user-roles.php` ≥ 2
  - Done when: `BASE_ROLES` replaces `ROLES`, `get_all_roles()` exists and merges both sources, CRUD methods exist

- [ ] **T02: Update all ROLES reference sites and add REST endpoints** `est:20m`
  - Why: All downstream PHP code that reads `UserRoles::ROLES` must switch to `get_all_roles()`. REST endpoints needed for frontend to create/delete roles.
  - Files: `includes/class-rest-api.php`, `includes/class-capability-sync.php`
  - Do:
    1. In `class-rest-api.php`: update `get_rondo_roles_list()` to iterate `UserRoles::get_all_roles()`
    2. In `get_capability_matrix()`: replace `array_keys(UserRoles::ROLES)` with `array_keys(UserRoles::get_all_roles())`; replace `UserRoles::ROLES[$slug][0]` with `UserRoles::get_all_roles()[$slug][0]`
    3. In `update_capability_matrix()`: replace `array_keys(UserRoles::ROLES)` with `array_keys(UserRoles::get_all_roles())`
    4. In `update_age_group_access()`: replace `array_keys(UserRoles::ROLES)` with `array_keys(UserRoles::get_all_roles())`
    5. In `class-capability-sync.php`: replace `UserRoles::get_role_slugs()` call — this already goes through `get_role_slugs()` which we updated in T01, so verify it works
    6. Register `POST /rondo/v1/settings/roles` route: admin-only, accepts `{ label: string }`, calls `UserRoles::add_custom_role()`, returns `{ slug, label }` or WP_Error
    7. Register `DELETE /rondo/v1/settings/roles/(?P<slug>[a-z0-9_]+)` route: admin-only, calls `UserRoles::remove_custom_role()`, returns `{ deleted: true, slug }` or WP_Error
  - Verify: `php -l includes/class-rest-api.php` + `php -l includes/class-capability-sync.php` pass; `grep -c "UserRoles::ROLES[^_]" includes/class-rest-api.php` returns 0; `grep "settings/roles" includes/class-rest-api.php` finds route registrations
  - Done when: zero references to `UserRoles::ROLES` (only `BASE_ROLES`), REST endpoints for role CRUD registered

- [ ] **T03: Invert Ledendata default and add API client methods** `est:15m`
  - Why: The default "see all" behavior is a security issue. API client methods needed for S02 frontend.
  - Files: `includes/class-access-control.php`, `src/api/client.js`
  - Do:
    1. In `get_permitted_age_groups()`: change the final `if ( ! $has_config ) { return null; }` to `return [];` — an empty array means "see nobody" (the meta_query will match zero posts). Also change the early return for `! $raw` and empty `$config` to return `[]` instead of `null` when the user has no management capabilities.
    2. The key logic: if user has management caps → return `null` (unrestricted, as before). If user has NO management caps → check age-group config. If config exists and has entries → return those entries. If no config or empty → return `[]` (restricted to nothing).
    3. In `client.js`: add `createCustomRole: (data) => axios.post('/rondo/v1/settings/roles', data)` and `deleteCustomRole: (slug) => axios.delete(`/rondo/v1/settings/roles/${slug}`)` to `prmApi`
  - Verify: `php -l includes/class-access-control.php` passes; `npm run build` + `npm run lint` pass; `grep "return \[\]" includes/class-access-control.php` finds the new default returns
  - Done when: non-management users with no age-group config get `[]` (not `null`) from `get_permitted_age_groups()`

- [ ] **T04: Version bump, changelog, deploy, and production verification** `est:15m`
  - Why: Ship the backend changes. Verify default inversion and custom role CRUD on production.
  - Files: `style.css`, `package.json`, `CHANGELOG.md`
  - Do:
    1. Bump version to 32.3.0 (minor — new feature, backward-compatible management cap bypass)
    2. Add CHANGELOG entry for 32.3.0 with all S01 changes
    3. `npm run build` + `npm run lint`
    4. Deploy via `bin/deploy.sh`
    5. Verify on production: create test custom role via REST, confirm it appears in capability-matrix, delete it
    6. Verify on production: `GET /rondo/v1/user/me` for admin returns `permitted_age_groups: null` (management cap bypass still works)
    7. Verify on production: age-group access endpoint accepts custom role slugs
  - Verify: production REST calls return expected results; `style.css` shows 32.3.0
  - Done when: deployed, custom role CRUD works on production, default inversion live

## Files Likely Touched

- `includes/class-user-roles.php` — ROLES → BASE_ROLES, get_all_roles(), CRUD methods
- `includes/class-rest-api.php` — All ROLES refs updated, new REST endpoints for role CRUD
- `includes/class-capability-sync.php` — Verify get_role_slugs() pulls dynamic roles
- `includes/class-access-control.php` — Default inversion in get_permitted_age_groups()
- `src/api/client.js` — createCustomRole, deleteCustomRole methods
- `style.css` — Version bump
- `package.json` — Version bump
- `CHANGELOG.md` — Release notes
