---
estimated_steps: 4
estimated_files: 2
---

# T01: Add Kaderlijst age-group bypass in PHP and frontend

**Slice:** S03 — Frontend age-group filtering
**Milestone:** M010

## Description

The Kaderlijst page rebuilds its snapshot by calling `wpApi.getPeople()` which hits `/wp/v2/people`. With S02's age-group filtering active, restricted users would get an incomplete set of people, creating a corrupted Kaderlijst snapshot for everyone. The `$suppress_age_group_filter` static flag exists in `AccessControl` (checked at 3 filter points) but is never set to `true`. This task adds a REST query parameter `suppress_age_group` that, when passed by an authenticated user on person queries, sets the flag before age-group filtering runs. The frontend Kaderlijst page passes this param during rebuilds.

## Steps

1. In `includes/class-access-control.php`, modify `filter_rest_query()`: before the age-group filtering block for person queries, check if `$request->get_param('suppress_age_group')` is truthy AND the user is authenticated (`is_user_logged_in()` — already checked above). If so, set `self::$suppress_age_group_filter = true`. This must be placed BEFORE the existing age-group filtering block so the flag is already set when the check runs. The flag also covers `filter_queries()` (which fires during WP_Query execution) since it reads the same static property.
2. In `src/pages/Teams/Kaderlijst.jsx`, modify `fetchAllPeople()`: add `suppress_age_group: true` to the `params` object. This adds `?suppress_age_group=true` to all paginated people requests during Kaderlijst rebuild.
3. Run `npm run build && npm run lint` to verify no errors.
4. Verify with grep that both changes are in place.

## Must-Haves

- [ ] `filter_rest_query()` reads `suppress_age_group` param and sets static flag for person queries only
- [ ] Bypass only affects age-group filtering — VOG filtering, clothing access, todo visibility remain unchanged
- [ ] `fetchAllPeople()` in Kaderlijst passes `suppress_age_group: true`
- [ ] `npm run build` and `npm run lint` pass

## Verification

- `npm run build && npm run lint` — zero errors
- `grep -n "suppress_age_group" includes/class-access-control.php` — shows param check in `filter_rest_query`
- `grep -n "suppress_age_group" src/pages/Teams/Kaderlijst.jsx` — shows param in `fetchAllPeople`

## Observability Impact

- Signals added/changed: The `suppress_age_group=true` parameter is visible in network requests during Kaderlijst rebuild, making it easy to verify in browser DevTools
- How a future agent inspects this: Check network tab for `/wp/v2/people?...&suppress_age_group=true` during Kaderlijst "Verversen" action
- Failure state exposed: If the bypass fails, the Kaderlijst snapshot will have fewer people than expected — visible by comparing member count in snapshot vs actual member count

## Inputs

- `includes/class-access-control.php` — S02 added `$suppress_age_group_filter` static flag, `has_age_group_restriction()`, `get_permitted_age_groups()`, and the age-group filtering blocks in all 3 filter methods
- `src/pages/Teams/Kaderlijst.jsx` — `fetchAllPeople()` at line ~240 calls `wpApi.getPeople()` with params for paginated person fetch
- S02 Research — confirms the bypass approach is safe since Kaderlijst shows public volunteer data (names, roles, contact) already visible to all authenticated users

## Expected Output

- `includes/class-access-control.php` — `filter_rest_query()` has 3-4 new lines checking `suppress_age_group` param and setting static flag
- `src/pages/Teams/Kaderlijst.jsx` — `fetchAllPeople()` params include `suppress_age_group: true`
