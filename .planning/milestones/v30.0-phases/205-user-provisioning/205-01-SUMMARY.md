---
phase: 205-user-provisioning
plan: 01
subsystem: api
tags: [user-provisioning, wordpress-users, welcome-email, rest-api, php]

# Dependency graph
requires:
  - phase: 204-functie-to-role-mapping-config
    provides: FunctieCapabilityMap::get_roles_for_functie() for role assignment at provisioning time

provides:
  - UserProvisioning service class with provision(), send_welcome_email(), get_settings(), update_settings()
  - POST /rondo/v1/people/{id}/provision — admin-triggered user account creation
  - GET /rondo/v1/provisioning/settings — configurable email template fields
  - POST /rondo/v1/provisioning/settings — persist template values
  - Person REST response now includes linked_user_id and welcome_email_sent_at
  - Users list (GET /rondo/v1/users) now includes linked_person_id and linked_person_name

affects:
  - 205-02 (frontend AccountCard reads linked_user_id and welcome_email_sent_at)
  - 206-capability-sync (rondo-sync uses bidirectional link established here)
  - 208-avatar (depends on rondo_linked_person_id user meta set here)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "UserProvisioning: pure service class, no hooks, PSR-4 autoloaded, instantiated on-demand by REST callbacks"
    - "Email from address: add/remove wp_mail_from filter pattern (same as VOGEmail)"
    - "Idempotency check: read _rondo_wp_user_id post meta before creating user, return already_exists if valid user found"
    - "Password reset: scoped 7-day expiry via add/remove password_reset_expiration filter"
    - "Non-blocking email: user is created even if email fails; failure is logged, not rolled back"

key-files:
  created:
    - includes/class-user-provisioning.php
  modified:
    - includes/class-rest-api.php
    - includes/class-rest-people.php

key-decisions:
  - "UserProvisioning is a pure service class with no constructor hooks — no functions.php entry needed"
  - "Welcome email failure is non-blocking: user is created successfully even if wp_mail() returns false"
  - "Stale META_USER_ID meta (user deleted) is cleaned up at idempotency-check time, then provisioning proceeds"
  - "7-day password reset expiry scoped via add/remove filter (not global config change)"
  - "Roles from work_history Functies are assigned with add_role() on top of base rondo_user set_role()"

patterns-established:
  - "REST provisioning pattern: register_rest_route in class-rest-api.php register_routes(), callback instantiates service class with FQCN"
  - "Person response enrichment: add computed/meta fields in class-rest-people.php add_person_computed_fields()"

# Metrics
duration: 3min
completed: 2026-02-20
---

# Phase 205 Plan 01: UserProvisioning Backend Summary

**UserProvisioning PHP service with admin REST endpoint, bidirectional person-user link, KNVB ID storage, 7-day branded welcome email, and configurable template settings**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-20T18:55:59Z
- **Completed:** 2026-02-20T18:59:40Z
- **Tasks:** 2
- **Files created/modified:** 3

## Accomplishments

- Full UserProvisioning service class with idempotency, role assignment from FunctieCapabilityMap, bidirectional linking, KNVB ID storage, scoped 7-day password reset, and branded welcome email
- Three new admin-only REST endpoints: provision, settings GET, settings POST
- Person REST response enriched with `linked_user_id` and `welcome_email_sent_at` for Plan 02 AccountCard
- Users list enriched with `linked_person_id` and `linked_person_name` for admin UI

## Task Commits

1. **Task 1: Create UserProvisioning class** - `afde0eff` (feat)
2. **Task 2: Register REST endpoints and enrich responses** - `3fc82a8a` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `includes/class-user-provisioning.php` — UserProvisioning service: provision(), send_welcome_email(), generate_username(), get_settings(), update_settings(), filter callbacks
- `includes/class-rest-api.php` — Three new route registrations + three callback methods + get_users() enriched with linked_person_id/linked_person_name
- `includes/class-rest-people.php` — add_person_computed_fields() now includes linked_user_id and welcome_email_sent_at

## Decisions Made

- UserProvisioning is a pure service class with no constructor hooks — no functions.php entry needed (same pattern as VOGEmail)
- Welcome email failure is non-blocking: user created successfully even if wp_mail() returns false, failure logged to PHP error_log
- Stale META_USER_ID meta (user deleted externally) cleaned up at idempotency-check time, provisioning proceeds
- 7-day password reset expiry applied via scoped add/remove filter on `password_reset_expiration` (not a global config change)
- Roles from work_history Functies assigned via add_role() on top of base rondo_user set_role(), deduped in-loop

## Deviations from Plan

None — plan executed exactly as written.

The plan noted `functions.php` might need modification, then self-corrected to say it was not needed. Confirmed: UserProvisioning has no hooks, is PSR-4 autoloaded, and instantiated on-demand by REST callbacks using FQCN.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required for the backend endpoints.

Note: Before using in production, verify Lettermint from-address is verified in the Lettermint dashboard (existing blocker from STATE.md).

## Next Phase Readiness

- All three REST endpoints are live on production
- `POST /rondo/v1/people/{id}/provision` is callable by any administrator
- Person REST response includes `linked_user_id` and `welcome_email_sent_at` for Plan 02 AccountCard to consume
- Plan 02 (frontend AccountCard UI) can proceed immediately

---
*Phase: 205-user-provisioning*
*Completed: 2026-02-20*
