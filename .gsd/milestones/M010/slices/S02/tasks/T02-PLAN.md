---
estimated_steps: 5
estimated_files: 2
---

# T02: REST endpoints for age-group access and /me extension

**Slice:** S02 — Age-group access filtering
**Milestone:** M010

## Description

Create REST endpoints for reading and writing the per-role age-group access configuration, extend the `/rondo/v1/user/me` endpoint with `permitted_age_groups`, and add frontend API client methods. These endpoints form the S02→S03 boundary contract — the frontend needs them to know the user's restrictions and to let admins configure age-group access.

## Steps

1. **Register `GET/POST /rondo/v1/settings/age-group-access` routes in `class-rest-api.php`:**
   - Add route registration in the existing settings route block (near line ~1461 where capability-matrix is registered)
   - GET: admin-only (`manage_options`), callback `get_age_group_access`
   - POST: admin-only (`manage_options`), callback `update_age_group_access`

2. **Implement `get_age_group_access()` method:**
   - Read `rondo_age_group_access` option (default empty array)
   - Get available age groups by querying distinct `leeftijdsgroep` meta values (reuse the same query from `get_filter_options()` — `SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key = 'leeftijdsgroep'` with sorting)
   - Return `{ roles: { slug: string[] }, available_age_groups: string[] }` where `roles` has entries only for roles that have configured age groups

3. **Implement `update_age_group_access()` method:**
   - Accept `{ roles: { slug: string[] } }` — each role slug maps to array of permitted leeftijdsgroep values
   - Validate role slugs against `UserRoles::ROLES` + 'administrator'
   - Sanitize values: `array_map('sanitize_text_field', ...)` on each array
   - Remove entries with empty arrays (empty = no restriction, don't store)
   - Save to `rondo_age_group_access` option via `update_option()`
   - Return fresh state via `get_age_group_access()`

4. **Extend `get_current_user()` with `permitted_age_groups`:**
   - After the existing capability checks (around line ~3975), call `AccessControl::get_permitted_age_groups()`
   - Add `'permitted_age_groups' => $permitted_age_groups` to the response array
   - Value is `null` (no restriction) or `string[]` (array of permitted leeftijdsgroep values)

5. **Add API client methods in `src/api/client.js`:**
   - `getAgeGroupAccess: () => api.get('/rondo/v1/settings/age-group-access')`
   - `updateAgeGroupAccess: (data) => api.post('/rondo/v1/settings/age-group-access', data)`
   - Place near existing `getCapabilityMatrix` / `updateCapabilityMatrix` methods (around line ~293)

## Must-Haves

- [ ] `GET /rondo/v1/settings/age-group-access` returns current config with available age groups
- [ ] `POST /rondo/v1/settings/age-group-access` validates, saves, and returns updated config
- [ ] Both endpoints require `manage_options` capability
- [ ] Role slug validation prevents arbitrary keys in the option
- [ ] `/rondo/v1/user/me` includes `permitted_age_groups: string[] | null`
- [ ] Frontend API client has `getAgeGroupAccess()` and `updateAgeGroupAccess()` methods
- [ ] Empty arrays removed from stored option (empty = no restriction)

## Verification

- `php -l includes/class-rest-api.php` — no syntax errors
- `npm run build` — zero errors
- `npm run lint` — zero warnings
- `grep 'age-group-access' includes/class-rest-api.php` — finds route registration and both handler methods
- `grep 'permitted_age_groups' includes/class-rest-api.php` — finds field in get_current_user response
- `grep 'getAgeGroupAccess\|updateAgeGroupAccess' src/api/client.js` — finds both methods

## Observability Impact

- Signals added/changed: REST endpoints return structured JSON; validation errors return WP_Error with descriptive messages
- How a future agent inspects this: `GET /rondo/v1/settings/age-group-access` shows current config; `GET /rondo/v1/user/me` shows `permitted_age_groups` for authenticated user
- Failure state exposed: 403 for non-admin access; 400 for invalid role slugs; corrupted option returns empty config (fail-open)

## Inputs

- `includes/class-rest-api.php` — Existing route registration pattern at line ~1461, `get_current_user()` at line ~3911
- `includes/class-access-control.php` — `get_permitted_age_groups()` method from T01
- `includes/class-user-roles.php` — `ROLES` constant for slug validation
- `src/api/client.js` — Existing API client pattern at line ~293

## Expected Output

- `includes/class-rest-api.php` — Extended with `get_age_group_access()`, `update_age_group_access()`, route registration, and `permitted_age_groups` in `/me` response
- `src/api/client.js` — Extended with `getAgeGroupAccess()` and `updateAgeGroupAccess()` methods
