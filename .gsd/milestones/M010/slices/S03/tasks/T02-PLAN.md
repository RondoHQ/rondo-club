---
estimated_steps: 3
estimated_files: 1
---

# T02: Show access-denied message for age-group restricted persons

**Slice:** S03 — Frontend age-group filtering
**Milestone:** M010

## Description

PersonDetail currently shows a generic "Lid kon niet worden geladen." for all errors including 403s from age-group restrictions. The backend (S02) already returns `WP_Error('rest_forbidden_age_group', ..., 403)` for non-permitted persons. This task differentiates that specific error from other failures and shows a clear Dutch access-denied message with context about why access is denied.

## Steps

1. In `src/pages/People/PersonDetail.jsx`, find the error handler block (around line 983) that currently shows the generic error message for `error || !person`.
2. Add a check before the generic error: if `error?.response?.status === 403` and `error?.response?.data?.code === 'rest_forbidden_age_group'`, render a distinct card with an access-denied message: "Je hebt geen toegang tot dit lid. Dit lid valt buiten je toegewezen leeftijdsgroepen." with a `Link` back to `/people` using `btn-tertiary`. Use amber/warning styling (`bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300`) to differentiate from the red error styling.
3. Run `npm run build && npm run lint` to verify.

## Must-Haves

- [ ] 403 with `rest_forbidden_age_group` code shows distinct Dutch access-denied message
- [ ] Generic errors (network failures, 500s, 404s) still show existing "Lid kon niet worden geladen" message
- [ ] Back link navigates to `/people`
- [ ] `npm run build` and `npm run lint` pass

## Verification

- `npm run build && npm run lint` — zero errors
- `grep -n "rest_forbidden_age_group" src/pages/People/PersonDetail.jsx` — shows error code differentiation
- Visual code inspection: two separate error blocks — one for age-group 403, one for generic errors

## Observability Impact

- Signals added/changed: None — the error code already exists in the response, this just surfaces it visually
- How a future agent inspects this: Check the PersonDetail render output when a 403 with `rest_forbidden_age_group` code is returned
- Failure state exposed: Access-denied state is now clearly communicated to the user with the specific reason (age group restriction)

## Inputs

- `src/pages/People/PersonDetail.jsx` — Current error block at line ~983 shows generic message for all errors
- S02 backend — `filter_rest_single_access()` returns `WP_Error('rest_forbidden_age_group', ..., ['status' => 403])` for non-permitted persons
- T01 complete — Kaderlijst bypass in place (no dependency, but establishes the pattern of the slice)

## Expected Output

- `src/pages/People/PersonDetail.jsx` — Error handling block differentiates age-group 403 from other errors with distinct messaging and styling
