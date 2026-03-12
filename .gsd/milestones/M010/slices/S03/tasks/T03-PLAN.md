---
estimated_steps: 5
estimated_files: 4
---

# T03: Add People list info banner, version bump, changelog, and deploy

**Slice:** S03 — Frontend age-group filtering
**Milestone:** M010

## Description

Users with age-group restrictions see a filtered People list but have no indication why they see fewer members. This task adds a subtle info banner above the People list showing which leeftijdsgroepen the user can see. It also handles the milestone closeout: version bump to 32.2.0, changelog update, and production deploy.

## Steps

1. In `src/pages/People/PeopleList.jsx`, import `useCurrentUser` from `@/hooks/useCurrentUser`. Inside the component, call `const { data: currentUser } = useCurrentUser()`. Before the main table content (after the toolbar, before the table), add a conditional info banner: when `currentUser?.permitted_age_groups` is a non-null array, render a div with blue info styling (`bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm text-blue-700 dark:text-blue-300`) containing: "Je ziet alleen leden uit de leeftijdsgroepen: {groups.join(', ')}." Import `Info` icon from lucide-react for the banner icon.
2. Bump version to `32.2.0` in both `package.json` (version field) and `style.css` (Version: line).
3. Add changelog entry under `## [32.2.0] - 2026-03-12` in `CHANGELOG.md` with:
   - Added: Age-group info banner on People list showing permitted leeftijdsgroepen for restricted users
   - Added: Access-denied message on PersonDetail for age-group restricted persons (distinct from generic errors)
   - Added: Kaderlijst bypass for age-group filtering — snapshot rebuild works correctly for all users
4. Run `npm run build && npm run lint` to verify.
5. Deploy to production with `bin/deploy.sh`.

## Must-Haves

- [ ] Info banner shown only when `permitted_age_groups` is a non-null array (not shown for unrestricted users)
- [ ] Banner uses existing blue info styling pattern with dark mode support
- [ ] Banner shows the actual leeftijdsgroep values comma-separated
- [ ] Version bumped to 32.2.0 in `style.css` and `package.json`
- [ ] Changelog entry documents all S03 changes
- [ ] `npm run build` and `npm run lint` pass
- [ ] Deployed to production

## Verification

- `npm run build && npm run lint` — zero errors
- `grep -n "permitted_age_groups" src/pages/People/PeopleList.jsx` — shows banner logic
- `grep "32.2.0" package.json style.css` — version bumped in both files
- `grep "32.2.0" CHANGELOG.md` — changelog entry present
- Production site loads after deploy

## Observability Impact

- Signals added/changed: None — uses existing `permitted_age_groups` from the `/me` endpoint
- How a future agent inspects this: Check People list page for presence/absence of info banner; check `useCurrentUser()` data for `permitted_age_groups` value
- Failure state exposed: None — banner is purely informational

## Inputs

- `src/pages/People/PeopleList.jsx` — Main People list component, needs `useCurrentUser` import and banner rendering
- `src/hooks/useCurrentUser.js` — Returns `data.permitted_age_groups` (null = unrestricted, string[] = restricted)
- Existing blue info banner pattern from `FeeCategorySettings.jsx` and `FinanceSettings.jsx`
- T01 and T02 complete — all code changes in place for the version/changelog

## Expected Output

- `src/pages/People/PeopleList.jsx` — Info banner rendered conditionally for restricted users
- `style.css` — Version: 32.2.0
- `package.json` — version: "32.2.0"
- `CHANGELOG.md` — New [32.2.0] section with all S03 changes
- Production deployment successful
