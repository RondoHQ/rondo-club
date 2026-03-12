# S02: Age-group access filtering — Research

**Date:** 2026-03-12

## Summary

Age-group access filtering must restrict which people are visible to users based on their role's permitted leeftijdsgroep values. The codebase has two independent people-query paths: (1) the custom SQL endpoint `GET /rondo/v1/people/filtered` in `class-rest-people.php`, used by the People list, and (2) `WP_Query`-based queries used by search, dashboard, and the standard REST API (`GET /wp/v2/people`). Both need age-group filtering, but the Kaderlijst (which rebuilds from the standard REST API) must be exempted.

The biggest architectural question is **where to apply filtering to avoid breaking the Kaderlijst**. The Kaderlijst rebuild uses `wpApi.getPeople()` (`GET /wp/v2/people`) which routes through `AccessControl::filter_rest_query()` and `AccessControl::filter_queries()`. If we add age-group filtering at those levels, Kaderlijst rebuilds by restricted users would be incomplete. The solution: use the existing `suppress_filters` / context pattern — apply filtering at the `pre_get_posts` and `rest_person_query` filter hooks in `AccessControl`, but add a `suppress_age_group_filter` mechanism for the Kaderlijst snapshot rebuild endpoint to set before invoking person queries. Since the Kaderlijst snapshot is **server-side and shared** (stored in `wp_options`), the snapshot endpoint itself doesn't query people — it reads from options. The rebuild happens client-side. A simpler approach: filter only at the `get_filtered_people()` custom SQL level and the `rest_prepare_person` single-access level, NOT at the `WP_Query` level. This keeps the standard REST API and Kaderlijst rebuild unfiltered, while the People list and person detail are properly restricted.

However, the search endpoint (`global_search` in `class-rest-api.php`) uses `get_posts()` which goes through `WP_Query` → `filter_queries()`. If we don't filter at `WP_Query` level, search results would leak restricted people. The recommended approach: add age-group filtering at the `WP_Query` level (`filter_queries` and `filter_rest_query`) to cover search/dashboard/standard REST, and also in the custom SQL endpoint (`get_filtered_people`). For the Kaderlijst bypass, since the rebuild happens **client-side** and the Kaderlijst only reads the snapshot from the server, the practical concern is: what if a restricted user refreshes the Kaderlijst? They'd trigger `buildKaderlijstSnapshot()` which fetches people through `GET /wp/v2/people` → gets filtered → stores partial snapshot. The fix for this is in S03 (frontend slice) — either prevent restricted users from rebuilding, or add a `bypass_age_group` parameter. For S02, we implement the filtering and add a method to check/bypass it.

## Recommendation

**Implement age-group filtering at THREE points:**

1. **`AccessControl::filter_queries()`** — Add leeftijdsgroep meta_query for `person` post type when user has age-group restrictions. This covers `WP_Query`-based queries: search, dashboard counts, standard REST API.

2. **`AccessControl::filter_rest_query()`** — Same filtering for REST-specific queries.

3. **`RONDO_REST_People::get_filtered_people()`** — Add SQL JOIN + WHERE for leeftijdsgroep filtering in the custom People list query.

4. **`AccessControl::filter_rest_single_access()`** — Check leeftijdsgroep on single person access and return 403 if not permitted.

**Storage:** `rondo_age_group_access` wp_option storing `{ role_slug: ['Onder 7', 'Onder 8', ...] }`. Empty array or missing entry = no restriction for that role. This is the same pattern as `rondo_functie_capability_map` — a single JSON option keyed by role slug.

**Bypass for Kaderlijst:** Add a static flag `AccessControl::$suppress_age_group_filter = false` that the Kaderlijst snapshot rebuild endpoint sets to `true` before querying. But since Kaderlijst rebuild happens **client-side** (not server-side), this bypass isn't needed in S02. The Kaderlijst snapshot endpoint (`get_kaderlijst_snapshot`) reads from `wp_options` — no person query involved. The S03 frontend slice will handle ensuring restricted users don't trigger incomplete rebuilds.

**Management capability bypass:** Users with ANY of `manage_options`, `fairplay`, `vog`, `financieel`, `toegangscontrole`, `manage_clothing` should see all people (no age-group restriction). Age-group filtering only applies when the user has NONE of these management capabilities. This is consistent with the existing pattern for `is_vog_only_user()`.

**`/rondo/v1/me` endpoint:** Add `permitted_age_groups: string[] | null` field. `null` means no restriction (user has management capabilities or role has no age-group restriction). Array means restricted to those values.

**Matrix UI:** Add a "Ledendata" column to the CapabilitiesTab. When clicked, show a multi-select dropdown/popover with available leeftijdsgroep values (fetched from the filter-options endpoint). Store the selections via a separate `POST /rondo/v1/settings/age-group-access` endpoint (or extend the existing matrix endpoint).

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Available leeftijdsgroep values | `GET /rondo/v1/people/filter-options` returns `age_groups` | Dynamic, comes from actual data, already sorted |
| WP role capability checking | `user_can($user_id, $cap)` / `current_user_can($cap)` | Standard WordPress pattern |
| WP option storage for per-role config | `get_option('rondo_functie_capability_map')` pattern | Established pattern in codebase |
| VOG-only user detection | `AccessControl::is_vog_only_user()` | Same bypass pattern for management caps |
| Custom SQL person query filtering | JOIN + WHERE in `get_filtered_people()` | Leeftijdsgroep filter already exists here as a user-filter; age-group access is the same pattern |

## Existing Code and Patterns

- `includes/class-access-control.php` — Core filtering class. `is_vog_only_user()` pattern is the model for age-group restriction detection. `filter_queries()` and `filter_rest_query()` are where WP_Query-level filtering goes. `filter_rest_single_access()` handles single-item access control.
- `includes/class-rest-api.php:7131-7239` — `get_capability_matrix()` and `update_capability_matrix()` methods. The matrix currently returns `{ roles: { slug: { label, capabilities } }, capability_labels }`. Need to extend this (or add separate endpoints) for age-group access.
- `includes/class-rest-api.php:3909-3985` — `get_current_user()` method returns user capabilities. Add `permitted_age_groups` field here.
- `includes/class-rest-people.php:970-1030` — Custom SQL query in `get_filtered_people()`. VOG-only filtering pattern on line ~1025 is the model for age-group filtering: JOIN + WHERE clause.
- `includes/class-rest-people.php:1478-1545` — `get_filter_options()` returns available leeftijdsgroep values. Use this endpoint (or its query) to populate the matrix UI dropdown.
- `includes/class-user-roles.php` — `ROLES` constant defines all 7 custom roles. The age-group access option is keyed by these slugs plus `administrator`.
- `src/pages/Settings/Settings.jsx:2959-3072` — `CapabilitiesTab` component. Needs a "Ledendata" column with multi-select per role.
- `src/api/client.js:118` — `getCurrentUser()` calls `/rondo/v1/user/me`. Frontend consumes `permitted_age_groups` from here.
- `src/api/client.js:293-294` — `getCapabilityMatrix()` / `updateCapabilityMatrix()`. Need new methods for age-group access.
- `src/hooks/useCurrentUser.js` — Hook that caches current user data. Frontend will read `permitted_age_groups` from here.

## Constraints

- **WP capabilities are boolean only** — Cannot store arrays of leeftijdsgroep values as capabilities. Must use `wp_option` for storage.
- **`get_filtered_people()` uses raw SQL** — Cannot use `WP_Query` meta_query for this endpoint. Must add manual JOIN + WHERE clauses.
- **Kaderlijst rebuild is client-side** — The `buildKaderlijstSnapshot()` function runs in the browser, fetching people via the standard REST API. If age-group filtering is applied at the REST API level, Kaderlijst rebuilds by restricted users would be incomplete.
- **`filter_queries` receives a `WP_Query` object** — Must use `meta_query` for filtering, consistent with existing VOG filter pattern.
- **Management capability list** — The set of "management" capabilities that bypass age-group filtering must be consistent. Currently `manage_options`, `fairplay`, `vog`, `financieel`, `toegangscontrole`, `manage_clothing` are the 6 capabilities that indicate a management role.
- **Option key naming** — Must follow `rondo_` prefix pattern. Recommended: `rondo_age_group_access`.
- **No custom database tables** — Rule 0 mandates WordPress native storage only.

## Common Pitfalls

- **Filtering WP_Query for non-person post types** — Always guard age-group filtering with `post_type === 'person'` check. Applying it to other CPTs would break todos, teams, etc.
- **Kaderlijst rebuild by restricted users** — If age-group filtering applies at `rest_person_query` level, a restricted user rebuilding the Kaderlijst gets a partial snapshot that overwrites the full one. S03 must handle this (prevent rebuild or bypass filtering).
- **Empty age-group config vs missing config** — An empty array `[]` means "no restriction" (role can see all people). Distinguish from a configured restriction with specific values. Only apply filtering when the user's roles have explicit non-empty leeftijdsgroep arrays.
- **Multi-role users** — A user can have multiple roles (e.g., `rondo_user` + `rondo_vog`). The `rondo_vog` role has the `vog` capability which bypasses age-group filtering entirely. Must check ALL user roles for management capabilities before applying restrictions.
- **Performance of meta_query on large datasets** — The leeftijdsgroep meta_query uses `IN` comparison with multiple values. With ~500 people and ~20 leeftijdsgroep values, this is fine. No index concerns.
- **Race condition on option save** — The matrix UI saves capabilities AND age-group access. If these are separate endpoints, ensure the frontend saves them correctly (the matrix saves, then the age-group access saves separately).

## Open Risks

- **Kaderlijst bypass strategy** — S02 implements filtering at `WP_Query` level, which affects `GET /wp/v2/people`. The Kaderlijst rebuild uses this endpoint. S03 must handle the bypass (either prevent restricted users from rebuilding, or add a server-side bypass parameter). If this isn't handled in S03, restricted users rebuilding the Kaderlijst will corrupt the shared snapshot. **Mitigation:** Document this risk clearly in the S02→S03 boundary. Consider adding a `_bypass_age_group=1` REST parameter that only admins can use.
- **Dashboard counts leaking** — The dashboard shows `total_people` count. With age-group filtering at `WP_Query` level, restricted users would see a lower count (only their permitted members). This could be confusing ("Why does it say 150 members when the club has 500?"). **Mitigation:** Accept this behavior — showing the restricted count is actually correct for the user's view. It's consistent with VOG-only users seeing a lower count.
- **Search results scope** — Global search uses `get_posts()` which goes through `filter_queries()`. Restricted users would only find people in their permitted age groups. This is the correct behavior per the requirements.
- **age_group_access option size** — With 8 roles × up to 20 leeftijdsgroep values each, the option is small (~2KB max). No concern.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | N/A | Built-in knowledge sufficient — WP role/capability system, meta_query, options API are standard |
| React / TanStack Query | N/A | Built-in knowledge sufficient — existing codebase patterns cover all needs |
| Tailwind CSS v4 | Available in `<available_skills>` as `frontend-design` | Installed but not needed — UI follows existing CapabilitiesTab pattern |

## Sources

- `includes/class-access-control.php` — VOG-only filtering pattern (lines 107-130) is the exact model for age-group filtering
- `includes/class-rest-api.php` — Capability matrix endpoints (lines 7131-7239) establish the REST pattern for role configuration
- `includes/class-rest-people.php` — Custom SQL query with VOG filtering (lines 1020-1025) and leeftijdsgroep filter (lines 1096-1101) show both filtering patterns
- `src/pages/Teams/Kaderlijst.jsx` — Client-side rebuild flow (lines 267-315) confirms Kaderlijst uses standard REST API, not custom endpoint
- M010 Roadmap boundary map — Defines exact interface contract for S02→S03 handoff
