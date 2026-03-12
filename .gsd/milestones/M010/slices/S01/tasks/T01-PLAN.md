---
estimated_steps: 5
estimated_files: 5
---

# T01: Backend — REST endpoints, register_role fix, and role-name check fixes

**Slice:** S01 — Role-capability matrix backend & UI
**Milestone:** M010

## Description

Create the REST API for reading and writing the role×capability matrix, fix the `register_role()` method that would silently re-add capabilities removed via the matrix, and replace all 6 hardcoded `current_user_can('administrator')` checks with proper `current_user_can('manage_options')` capability checks. This task provides the complete backend foundation that the frontend UI (T02) consumes.

## Steps

1. **Add capability-matrix REST routes in `class-rest-api.php`** — Register `GET /rondo/v1/settings/capability-matrix` and `POST /rondo/v1/settings/capability-matrix` following the exact `functie-capability-map` route registration pattern (lines 1317-1380). Both use `check_admin_permission()` for permissions. The GET handler reads `wp_roles()->roles`, filters to Rondo roles (from `UserRoles::ROLES` keys + `administrator`), and builds a response: `{ roles: { slug: { label: string, capabilities: { cap: bool } } } }` for the 5 custom capabilities (fairplay, vog, financieel, toegangscontrole, manage_clothing). The POST handler accepts the same shape, validates it's an array, diffs each role's desired capabilities against current state, and calls `$role->add_cap()` / `$role->remove_cap()` accordingly. POST returns the fresh matrix state. **Constraint:** administrator's `manage_options` is never removable — if the POST payload tries to set it to false, ignore it.

2. **Fix `register_role()` in `class-user-roles.php`** — The current `register_role()` method (line 78-82) runs `add_cap()` on ALL roles including existing ones, which would re-add capabilities that an admin removed via the matrix. Fix: remove the `add_cap()` loop entirely. The `add_role()` call already sets capabilities when creating a new role. For existing roles, `add_role()` is a no-op (WordPress behavior), which is correct — the matrix is now the ongoing management tool. Keep the administrator-specific `add_cap()` calls (lines 86-91) — those ensure the admin always has custom Rondo capabilities on fresh install.

3. **Replace 6 `current_user_can('administrator')` checks** — In `class-rest-people.php` (lines 594, 630, 763), `class-rest-teams.php` (line 486), and `class-rest-commissies.php` (lines 254, 378): change `current_user_can( 'administrator' )` to `current_user_can( 'manage_options' )`. This is a direct string replacement — no logic changes needed.

4. **Add the GET and POST handler methods** — Implement `get_capability_matrix()` and `update_capability_matrix()` as methods on the REST API class. The GET handler should define the 5 capabilities with human-readable labels for the UI: `{ fairplay: 'FairPlay', vog: 'VOG', financieel: 'Financieel', toegangscontrole: 'Toegangscontrole', manage_clothing: 'Kledingbeheer' }`. Use `UserRoles::ROLES` to get role slugs and display names. Include administrator with label 'Administrator'.

5. **Verify all changes** — Run `php -l` on all 5 modified files. Run `grep` to confirm zero remaining `current_user_can( 'administrator' )` usages. Review the diff for correctness.

## Must-Haves

- [ ] `GET /rondo/v1/settings/capability-matrix` returns complete matrix of roles × capabilities
- [ ] `POST /rondo/v1/settings/capability-matrix` writes changes via `add_cap()`/`remove_cap()`
- [ ] Both endpoints use `check_admin_permission()` (requires `manage_options`)
- [ ] `register_role()` no longer re-adds capabilities to existing roles
- [ ] All 6 `current_user_can( 'administrator' )` replaced with `current_user_can( 'manage_options' )`
- [ ] Administrator `manage_options` cannot be removed via POST endpoint
- [ ] PHP syntax valid on all 5 files

## Verification

- `php -l includes/class-rest-api.php includes/class-user-roles.php includes/class-rest-people.php includes/class-rest-teams.php includes/class-rest-commissies.php` — all pass
- `grep -c "current_user_can( 'administrator' )" includes/class-rest-people.php includes/class-rest-teams.php includes/class-rest-commissies.php` — all return 0
- Visual diff review: matrix endpoint methods follow `functie-capability-map` pattern, `register_role()` fix is minimal and correct

## Observability Impact

- Signals added/changed: REST endpoints return standard WP_Error on failure with descriptive messages; POST endpoint returns updated matrix state for immediate UI verification
- How a future agent inspects this: `GET /rondo/v1/settings/capability-matrix` shows current state; `wp role list --fields=name,capabilities` via WP-CLI shows raw WP state
- Failure state exposed: HTTP 403 if not admin, HTTP 500 with WP_Error message if role manipulation fails

## Inputs

- `includes/class-rest-api.php` — existing REST route patterns (functie-capability-map at lines 1317-1380)
- `includes/class-user-roles.php` — `ROLES` constant for role definitions, `register_role()` method to fix
- `includes/class-rest-base.php` — `check_admin_permission()` method (already uses `manage_options`)
- S01-RESEARCH.md — 6 role-name check locations confirmed with exact line numbers

## Expected Output

- `includes/class-rest-api.php` — two new REST routes registered, two new handler methods (`get_capability_matrix`, `update_capability_matrix`)
- `includes/class-user-roles.php` — `register_role()` no longer has `add_cap()` loop for existing roles
- `includes/class-rest-people.php` — 3 instances of `administrator` replaced with `manage_options`
- `includes/class-rest-teams.php` — 1 instance replaced
- `includes/class-rest-commissies.php` — 2 instances replaced
