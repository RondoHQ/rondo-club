# S03: Frontend age-group filtering

**Goal:** The People list, PersonDetail, and Kaderlijst all correctly handle age-group restrictions in the frontend — restricted users see filtered members with clear UX feedback, unrestricted users see everything, and the Kaderlijst works for all users.
**Demo:** A user with age-group restrictions sees an info banner on the People list explaining their visible leeftijdsgroepen, gets a clear Dutch access-denied message when navigating to a non-permitted person's detail page, and can rebuild the Kaderlijst without corrupting the snapshot.

## Must-Haves

- Kaderlijst `fetchAllPeople()` passes `suppress_age_group=true` to bypass age-group filtering during rebuild
- PHP `filter_rest_query()` recognizes `suppress_age_group` param for authenticated person queries and sets the static flag
- PersonDetail shows "Je hebt geen toegang tot dit lid" message for `rest_forbidden_age_group` 403 errors, distinct from generic errors
- People list shows a blue info banner with permitted leeftijdsgroepen when user has age-group restrictions
- Kaderlijst page works normally for all users (no filtering applied to volunteer roster)
- `npm run build` and `npm run lint` pass
- Version bumped, changelog updated, deployed to production

## Proof Level

- This slice proves: final-assembly
- Real runtime required: yes (deployed to production WordPress)
- Human/UAT required: yes (admin must configure age-group restrictions for a test role, verify filtered/unfiltered behavior)

## Verification

- `npm run build` — frontend compiles without errors
- `npm run lint` — zero warnings/errors
- `grep -n "suppress_age_group" includes/class-access-control.php` — shows bypass handling in `filter_rest_query`
- `grep -n "suppress_age_group" src/pages/Teams/Kaderlijst.jsx` — shows param passed in `fetchAllPeople`
- `grep -n "rest_forbidden_age_group" src/pages/People/PersonDetail.jsx` — shows error code differentiation
- `grep -n "permitted_age_groups" src/pages/People/PeopleList.jsx` — shows info banner logic
- Production deploy succeeds and site loads correctly

## Observability / Diagnostics

- Runtime signals: PHP `filter_rest_query()` logs no errors; the `$suppress_age_group_filter` flag is set/checked silently. Frontend 403 errors are caught and displayed as user-facing messages.
- Inspection surfaces: `/rondo/v1/user/me` endpoint returns `permitted_age_groups` (null = unrestricted, string[] = restricted) — this is the diagnostic surface for checking a user's state. The Kaderlijst rebuild passes `suppress_age_group=true` visibly in network requests.
- Failure visibility: PersonDetail shows `rest_forbidden_age_group` error code as a distinct access-denied message rather than a generic failure, making the cause immediately clear. The People list info banner shows exactly which age groups the user can see.
- Redaction constraints: None — age group values are not sensitive.

## Integration Closure

- Upstream surfaces consumed: `AccessControl::$suppress_age_group_filter` static flag (S02), `filter_rest_query()` with `$request` param (S02), `filter_rest_single_access()` returning `rest_forbidden_age_group` error (S02), `/rondo/v1/user/me` with `permitted_age_groups` (S02), `useCurrentUser()` hook (existing)
- New wiring introduced in this slice: PHP bypass param handling in `filter_rest_query()`, frontend `suppress_age_group` param in Kaderlijst rebuild, PersonDetail error differentiation, PeopleList info banner
- What remains before the milestone is truly usable end-to-end: nothing — this is the final slice

## Tasks

- [x] **T01: Add Kaderlijst age-group bypass in PHP and frontend** `est:30m`
  - Why: Without a bypass, restricted users rebuilding the Kaderlijst would create an incomplete snapshot, corrupting it for everyone. The `$suppress_age_group_filter` static flag exists but is never set. Both PHP (recognize the param) and JS (send the param) must change together.
  - Files: `includes/class-access-control.php`, `src/pages/Teams/Kaderlijst.jsx`
  - Do: In `filter_rest_query()`, check `$request->get_param('suppress_age_group')` for person queries from authenticated users and set `self::$suppress_age_group_filter = true` before applying age-group filtering. In Kaderlijst's `fetchAllPeople()`, add `suppress_age_group: true` to the params object. The bypass only affects age-group filtering — VOG filtering, clothing access, and other controls remain untouched.
  - Verify: `npm run build && npm run lint` pass; `grep` confirms both changes present
  - Done when: Kaderlijst rebuild queries include `suppress_age_group=true` and the PHP backend recognizes and honors it

- [x] **T02: Show access-denied message for age-group restricted persons** `est:20m`
  - Why: PersonDetail currently shows a generic "Lid kon niet worden geladen" for all errors. Users with age-group restrictions hitting a 403 need to understand WHY they can't see this person, not get a confusing generic error.
  - Files: `src/pages/People/PersonDetail.jsx`
  - Do: In the error handler (around line 983), differentiate between `error?.response?.status === 403 && error?.response?.data?.code === 'rest_forbidden_age_group'` and all other errors. Show "Je hebt geen toegang tot dit lid. Dit lid valt buiten je toegewezen leeftijdsgroepen." with a back link to `/people`. Keep the generic error message for other failures.
  - Verify: `npm run build && npm run lint` pass; `grep` confirms error code check present
  - Done when: PersonDetail shows distinct Dutch access-denied message for age-group 403s

- [x] **T03: Add People list info banner, version bump, changelog, and deploy** `est:30m`
  - Why: Users with age-group restrictions need to understand why they see fewer members than expected. This task also closes the milestone with version bump, changelog, and production deploy.
  - Files: `src/pages/People/PeopleList.jsx`, `style.css`, `package.json`, `CHANGELOG.md`
  - Do: Import `useCurrentUser` in PeopleList. When `currentUser?.permitted_age_groups` is a non-null array, render a blue info banner above the list: "Je ziet alleen leden uit de leeftijdsgroepen: {groups joined with comma}." using the existing `bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800` pattern. Bump version to 32.2.0 in `style.css` and `package.json`. Add changelog entry. Run `npm run build && npm run lint`. Deploy with `bin/deploy.sh`.
  - Verify: Production site loads; People list shows banner for restricted users; `npm run build && npm run lint` pass
  - Done when: Version 32.2.0 deployed to production with info banner, access-denied messages, and Kaderlijst bypass all functional

## Files Likely Touched

- `includes/class-access-control.php`
- `src/pages/Teams/Kaderlijst.jsx`
- `src/pages/People/PersonDetail.jsx`
- `src/pages/People/PeopleList.jsx`
- `style.css`
- `package.json`
- `CHANGELOG.md`
