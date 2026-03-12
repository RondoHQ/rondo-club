---
id: T02
parent: S03
milestone: M010
provides:
  - Age-group 403 error differentiation in PersonDetail with distinct Dutch access-denied message
key_files:
  - src/pages/People/PersonDetail.jsx
key_decisions:
  - Age-group 403 check placed before generic error block so it takes priority; generic block still catches all other errors
patterns_established:
  - Axios error shape `error.response.status` + `error.response.data.code` used to match specific WP_Error codes from REST API
observability_surfaces:
  - PersonDetail renders distinct amber-styled card with "rest_forbidden_age_group" message text, making the restriction cause immediately visible to users
duration: 5m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Show access-denied message for age-group restricted persons

**Added distinct Dutch access-denied message in PersonDetail for age-group 403 errors, differentiated from generic load failures.**

## What Happened

Added an early-return error block in PersonDetail.jsx (line 983) that checks `error?.response?.status === 403` and `error?.response?.data?.code === 'rest_forbidden_age_group'`. When matched, renders an amber-styled card with the message "Je hebt geen toegang tot dit lid. Dit lid valt buiten je toegewezen leeftijdsgroepen." and a back link to `/people`. The existing generic error block remains unchanged below it, catching all other error types (network failures, 500s, 404s).

## Verification

- `npm run build` — passes, PersonDetail chunk compiled (`PersonDetail-CFj0aRcH.js`, 147.95 KB)
- `npm run lint` — zero warnings/errors
- `grep -n "rest_forbidden_age_group" src/pages/People/PersonDetail.jsx` — confirms error code check at line 983
- Visual inspection: two separate error blocks — amber for age-group 403, red for generic errors
- Slice-level checks: `suppress_age_group` grep in PHP and Kaderlijst both pass (T01); `rest_forbidden_age_group` grep passes (T02)

## Diagnostics

- When a user with age-group restrictions navigates to a restricted person, the backend returns `403` with `code: 'rest_forbidden_age_group'`. The frontend catches this and renders an amber card instead of the red generic error card.
- To inspect: visit `/people/{id}` for a restricted person while logged in as a user with age-group restrictions. The amber card with "leeftijdsgroepen" text should appear.
- Generic errors (network, 500, etc.) still show the red "Lid kon niet worden geladen" message.

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/pages/People/PersonDetail.jsx` — Added age-group 403 error differentiation block before generic error handler (lines 983-990)
