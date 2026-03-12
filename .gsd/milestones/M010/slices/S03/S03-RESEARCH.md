# S03: Frontend age-group filtering — Research

**Date:** 2026-03-12

## Summary

S03 wires the S02 backend age-group filtering to the frontend. The backend is already fully functional: the `/rondo/v1/people/filtered` endpoint (People list) and `/wp/v2/people` (standard REST) both filter results based on the user's `permitted_age_groups`; the `/wp/v2/people/:id` endpoint returns a 403 with error code `rest_forbidden_age_group` for non-permitted persons; and the `/rondo/v1/user/me` endpoint already includes `permitted_age_groups` in its response. The frontend API client (`src/api/client.js`) already has `getAgeGroupAccess()` and `updateAgeGroupAccess()` methods, and `useCurrentUser()` already returns the `permitted_age_groups` field.

The frontend work splits into three concerns: (1) **PersonDetail access denied** — detect the `rest_forbidden_age_group` error code and show a proper Dutch access-denied message instead of the generic error; (2) **People list awareness** — optionally show an informational banner when the user has age-group restrictions, so they understand why they see fewer members; (3) **Kaderlijst bypass** — the critical risk area. The Kaderlijst rebuilds its snapshot by calling `wpApi.getPeople()` (which hits `/wp/v2/people`), and for restricted users this will return only permitted people, producing an incomplete snapshot. The `$suppress_age_group_filter` static flag exists in PHP but is never set by any code path yet. A backend-side bypass mechanism is needed.

The most secure approach for the Kaderlijst bypass: add a `suppress_age_group` query parameter recognized by the `filter_rest_query()` method, but ONLY for person queries from authenticated users. This is safe because the Kaderlijst already displays public volunteer information (names, roles, contact details) — age-group filtering is for the People management list, not volunteer roster data. An alternative is a dedicated server-side rebuild endpoint, but this adds significant backend complexity for a flow that currently works well client-side.

## Recommendation

Split into 3 tasks:

**T01: Kaderlijst backend bypass** — Add `suppress_age_group_for_kaderlijst` REST param support in `filter_rest_query()` in `class-access-control.php`. When `$request->get_param('suppress_age_group')` is truthy AND the user is authenticated, set `self::$suppress_age_group_filter = true` for person queries. Update `fetchAllPeople()` in `Kaderlijst.jsx` to pass `suppress_age_group: true` as a param to `wpApi.getPeople()`. This is the riskiest part — it's a backend change affecting access control.

**T02: PersonDetail access denied** — In `PersonDetail.jsx`, check `error?.response?.status === 403` and `error?.response?.data?.code === 'rest_forbidden_age_group'` to show a distinct access-denied message: "Je hebt geen toegang tot dit lid. Dit lid valt buiten je toegewezen leeftijdsgroepen." with a back button to `/people`. Keep the generic error for other failures.

**T03: People list info banner + version/changelog/deploy** — When `currentUser?.permitted_age_groups` is a non-null array, show a subtle info banner above the People list: "Je ziet alleen leden uit de leeftijdsgroepen: {groups}." Use the existing blue info styling pattern (bg-blue-50, dark:bg-blue-900/20). Version bump, changelog, deploy.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Current user data | `useCurrentUser()` hook with TanStack Query caching | Already returns `permitted_age_groups` from S02, 5-min staleTime, deduped across components |
| Error code detection | Axios error interceptor in `src/api/client.js` | 403s pass through to caller with `error.response.data.code` available |
| Info banner styling | Blue info banner pattern used in TodosList (bg-blue-50 border-blue-200) | Consistent UX, already dark-mode compatible |
| Kaderlijst snapshot caching | `prmApi.getKaderlijstSnapshot()` and `updateKaderlijstSnapshot()` | Snapshot pattern means most Kaderlijst loads skip the people query entirely |

## Existing Code and Patterns

- `src/hooks/useCurrentUser.js` — Returns `data.permitted_age_groups` (null = unrestricted, string[] = restricted). Already used by PersonDetail, Layout, Dashboard, Settings, etc.
- `src/pages/People/PersonDetail.jsx:983-990` — Current error handler shows generic "Lid kon niet worden geladen." for ALL errors. Need to differentiate 403 age-group errors.
- `src/pages/Teams/Kaderlijst.jsx:240-260` — `fetchAllPeople()` calls `wpApi.getPeople()` for rebuild. This goes through `filter_rest_query()` which applies age-group filtering. Must add bypass.
- `includes/class-access-control.php:27` — `$suppress_age_group_filter` static flag exists, checked at 3 filter points (filter_queries, filter_rest_query, filter_rest_single_access). Currently never set to true.
- `includes/class-access-control.php:381` — `filter_rest_query()` receives `$request` as second param — can read custom query params for bypass.
- `src/pages/People/PeopleList.jsx` — Uses `useFilteredPeople()` which calls `/rondo/v1/people/filtered`. Backend already filters; no frontend changes needed for the list query.
- `src/api/client.js:118` — `getCurrentUser: () => api.get('/rondo/v1/user/me')` — endpoint already includes `permitted_age_groups` from S02.
- `includes/class-rest-api.php:3998` — `permitted_age_groups` is already in the `/me` response via `AccessControl::get_permitted_age_groups()`.
- `includes/class-access-control.php:463-476` — `filter_rest_single_access()` returns `WP_Error('rest_forbidden_age_group', ..., 403)` for individual person access.

## Constraints

- **Kaderlijst snapshot pattern**: The snapshot is stored as a wp_option. When it exists, no people query is needed. The bypass is only needed during snapshot rebuilds (first load or manual refresh).
- **Backend access control is already active**: The S02 deploy (v32.1.0) already filters queries. If an admin configures age-group restrictions for a role, users with that role are ALREADY seeing filtered People lists. S03 just improves the UX around this.
- **No Kaderlijst-specific endpoint for people**: The Kaderlijst rebuild uses the standard `/wp/v2/people` endpoint. The bypass mechanism must be safe for this standard endpoint.
- **The `filter_rest_query()` hook runs on ALL `/wp/v2/people` requests**: Any bypass param must not be exploitable from the People list or other contexts to circumvent filtering.
- **Global search also filtered**: `global_search()` uses `get_posts()` which triggers `filter_queries()`. This is correct behavior and should NOT be bypassed.

## Common Pitfalls

- **Bypass param security** — Don't create a bypass that a user could add to any people request to circumvent age-group filtering. The bypass should be narrowly scoped (e.g., only for authenticated users on person queries, and only suppresses age-group filtering, not other access controls). Since all authenticated users are already allowed to see the Kaderlijst (it shows public volunteer data), this is safe.
- **Stale suppress flag** — The `$suppress_age_group_filter` is a static property. Setting it to `true` in `filter_rest_query()` would affect ALL subsequent queries in the same PHP request. Must reset it after the query or scope it narrowly. Actually, for a REST request, there's typically only one main query, so this is fine — but be careful with any follow-up queries in the same request lifecycle. Consider setting it per-request rather than globally.
- **Error vs missing person** — A 403 for age-group restriction should show "access denied," not "not found." Don't merge these cases. A 404 means the person doesn't exist; a 403 means you can't view them.
- **Kaderlijst rebuild by restricted user** — If a restricted user triggers "Kaderlijst verversen" without the bypass, the snapshot would be overwritten with incomplete data. The bypass must be in place before this can happen.
- **Double-checking filter_queries vs filter_rest_query** — The `wpApi.getPeople()` call goes through the REST API, so both `filter_rest_query()` AND `filter_queries()` will fire. The `filter_rest_query` hook modifies the `$args` array before WP_Query runs, and `filter_queries` modifies the WP_Query object itself. The bypass needs to cover both. Setting `self::$suppress_age_group_filter = true` before the query covers both since both hooks check this flag.

## Open Risks

- **Bypass param could be misused in theory** — Even though the data exposed (Kaderlijst info) is already public to all authenticated users, having a REST API bypass param feels architecturally loose. Mitigation: validate that the param only affects age-group filtering (not VOG filtering or other access controls), and only for person queries. The flag is narrow (checked only in 3 specific age-group filter blocks).
- **Static flag not reset** — If `$suppress_age_group_filter` is set to `true` during one REST request and PHP runs multiple REST dispatches (batching), subsequent queries could leak data. Mitigation: in WordPress REST API, each request is typically independent. If concerned, reset the flag in `filter_rest_query` after use: set it before returning $args, but that won't help since `filter_queries` runs later. The static flag approach is inherently per-request-lifecycle safe in standard WordPress.
- **Snapshot staleness** — If a restricted user's rebuild with the bypass creates a snapshot, and then team assignments change, the snapshot could become stale. This is an existing issue unrelated to S03 — the snapshot is always stale until manually refreshed.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| React | vercel-labs/agent-skills@vercel-react-best-practices | available (201K installs) — not needed, codebase patterns well-established |
| WordPress/PHP | none found | N/A — WordPress REST API patterns follow existing codebase conventions |
| TanStack Query | none found | N/A — usage patterns clear from existing hooks |

## Sources

- S02 task summaries (T01-T04) — Backend implementation details for age-group filtering
- `includes/class-access-control.php` — All 3 filter hook implementations with `$suppress_age_group_filter` flag
- `includes/class-rest-api.php` — `/me` endpoint already returns `permitted_age_groups`
- `src/pages/People/PersonDetail.jsx` — Current error handling (generic, needs differentiation)
- `src/pages/Teams/Kaderlijst.jsx` — Snapshot pattern and `fetchAllPeople()` rebuild flow
- `src/hooks/useCurrentUser.js` — TanStack Query hook for current user data
- `src/api/client.js` — API client methods and 403 handling
