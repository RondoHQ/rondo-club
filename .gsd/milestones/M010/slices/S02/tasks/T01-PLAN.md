---
estimated_steps: 7
estimated_files: 3
---

# T01: Backend storage, helper methods, and query filtering

**Slice:** S02 — Age-group access filtering
**Milestone:** M010

## Description

Implement the core backend for age-group access filtering. This creates the `rondo_age_group_access` wp_option storage, the `get_permitted_age_groups()` helper method with management capability bypass, and applies filtering at all 3 query points (WP_Query `filter_queries`, REST `filter_rest_query`, custom SQL `get_filtered_people`) plus single-item access control in `filter_rest_single_access`. Also adds a static `$suppress_age_group_filter` flag for S03's Kaderlijst bypass.

## Steps

1. **Add `get_permitted_age_groups()` and `has_age_group_restriction()` to AccessControl:**
   - Add static `$suppress_age_group_filter = false` property
   - Add `public static function get_permitted_age_groups( $user_id = null )` that:
     - Returns `null` if user has ANY management capability: `manage_options`, `fairplay`, `vog`, `financieel`, `toegangscontrole`, `manage_clothing`
     - Reads `rondo_age_group_access` option (JSON-decoded array keyed by role slug)
     - Iterates user's roles, collects all permitted age groups from the option
     - Returns `null` if no roles have age-group config (or all are empty arrays)
     - Returns merged unique array of permitted values if any role has non-empty config
   - Add `private function has_age_group_restriction()` instance helper that calls `get_permitted_age_groups()` and returns bool

2. **Add `apply_age_group_filter()` private method to AccessControl:**
   - Takes a `WP_Query` object and adds `meta_query` for `leeftijdsgroep` with `IN` comparison using permitted values
   - Pattern: same as `apply_vog_filter()` — append to existing meta_query array

3. **Modify `filter_queries()` in AccessControl:**
   - After the existing VOG-only check (line ~219), add: if `$post_type === 'person'` and `!self::$suppress_age_group_filter` and user has age-group restriction, call `apply_age_group_filter()`
   - Guard: only applies when user is logged in and post_type is 'person'

4. **Modify `filter_rest_query()` in AccessControl:**
   - After the existing checks, add person-specific age-group filtering
   - If `$post_type === 'person'` and `!self::$suppress_age_group_filter` and user has age-group restriction:
     - Add `meta_query` to `$args` array with `leeftijdsgroep` IN permitted values
   - Return modified `$args`

5. **Modify `filter_rest_single_access()` in AccessControl:**
   - After existing checks, for person post type only:
     - If `self::$suppress_age_group_filter` is true, skip
     - Call `get_permitted_age_groups()` — if null, allow
     - Get person's `leeftijdsgroep` meta value
     - If person's leeftijdsgroep is not in the permitted array, return WP_Error 403

6. **Modify `get_filtered_people()` in RONDO_REST_People:**
   - After the VOG-only filtering block (around line ~1025), add age-group access filtering
   - Call `AccessControl::get_permitted_age_groups($current_user_id)`
   - If not null: JOIN `{$wpdb->postmeta} ag ON p.ID = ag.post_id AND ag.meta_key = 'leeftijdsgroep'` and add WHERE `ag.meta_value IN (...)` with prepared placeholders
   - Use the existing `lg` alias if leeftijdsgroep user-filter is also active; otherwise use a new `ag` alias

7. **Write WPUnit test `tests/Wpunit/AgeGroupAccessTest.php`:**
   - Test `get_permitted_age_groups()` returns null for admin user (has manage_options)
   - Test returns null for user with fairplay capability (management bypass)
   - Test returns null when no age-group config exists in option
   - Test returns null when user's role has empty array in config
   - Test returns correct array when user's role has configured age groups
   - Test returns merged array when user has multiple roles with different configs
   - Note: WP_Query-level filtering tested via production verification (requires full WP context with posts)

## Must-Haves

- [ ] `get_permitted_age_groups()` returns null for users with any of 6 management capabilities
- [ ] `get_permitted_age_groups()` reads from `rondo_age_group_access` wp_option
- [ ] `get_permitted_age_groups()` returns merged unique array for multi-role users
- [ ] `get_permitted_age_groups()` returns null when config is empty/missing
- [ ] `filter_queries()` adds leeftijdsgroep meta_query for person queries when restricted
- [ ] `filter_rest_query()` adds leeftijdsgroep meta_query for REST person queries when restricted
- [ ] `get_filtered_people()` adds SQL JOIN+WHERE for age-group filtering when restricted
- [ ] `filter_rest_single_access()` returns 403 for person posts outside permitted age groups
- [ ] Static `$suppress_age_group_filter` flag exists and is respected by all filtering points
- [ ] Only `person` post type is affected (no filtering on teams, todos, etc.)

## Verification

- `php -l includes/class-access-control.php` — no syntax errors
- `php -l includes/class-rest-people.php` — no syntax errors
- `tests/Wpunit/AgeGroupAccessTest.php` exists with meaningful assertions
- `grep -c 'suppress_age_group_filter' includes/class-access-control.php` — returns ≥3 (declaration + usage in 3 methods)
- `grep -c 'get_permitted_age_groups' includes/class-access-control.php` — returns ≥2 (declaration + usage)
- `grep -c 'leeftijdsgroep' includes/class-access-control.php` — returns ≥2 (meta_query references)

## Observability Impact

- Signals added/changed: Age-group filtering silently narrows queries; no explicit logging for normal operation (consistent with existing VOG pattern). `filter_rest_single_access` returns structured WP_Error 403 with code `rest_forbidden_age_group` for clear identification.
- How a future agent inspects this: `wp option get rondo_age_group_access --format=json` to see current config; check `/rondo/v1/user/me` for `permitted_age_groups`; look for `rest_forbidden_age_group` error code in REST responses.
- Failure state exposed: 403 response with `rest_forbidden_age_group` code when single-access blocked; null option returns gracefully (no restriction = fail-open, safe default).

## Inputs

- `includes/class-access-control.php` — Current access control with VOG-only pattern to follow
- `includes/class-rest-people.php` — Custom SQL query with VOG filtering pattern at line ~1020
- `includes/class-user-roles.php` — `ROLES` constant for role slug enumeration
- S01 capability matrix — management capabilities are defined and assigned via WP roles

## Expected Output

- `includes/class-access-control.php` — Extended with `get_permitted_age_groups()`, `has_age_group_restriction()`, `apply_age_group_filter()`, suppress flag, and modified `filter_queries()`, `filter_rest_query()`, `filter_rest_single_access()`
- `includes/class-rest-people.php` — Extended `get_filtered_people()` with age-group SQL filtering
- `tests/Wpunit/AgeGroupAccessTest.php` — New test file with assertions for the helper method
