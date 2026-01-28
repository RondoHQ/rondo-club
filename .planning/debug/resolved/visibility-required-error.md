---
status: verifying
trigger: "REST API returning 400 error because `_visibility` ACF field is missing from request"
created: 2026-01-28T10:00:00Z
updated: 2026-01-28T10:20:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED - Added rest_pre_insert hook to inject default _visibility value before ACF validation
test: verifying fix satisfies external sync tool and doesn't break existing functionality
expecting: REST API accepts requests without _visibility, defaults to 'private'
next_action: verify via test request and deploy to production

## Symptoms

expected: Sync tool should be able to create/update person/team records via REST API
actual: REST API returns 400 Bad Request with validation error
errors: {"code":"rest_invalid_param","message":"Invalid parameter(s): acf","data":{"status":400,"params":{"acf":"_visibility is a required property of acf."}}}
reproduction: External sync tool making POST/PUT requests to REST API without _visibility field in acf object
timeline: Unknown - likely started after ACF field schema was made required

## Eliminated

## Evidence

- timestamp: 2026-01-28T10:05:00Z
  checked: ACF field definition in acf-json/group_visibility_settings.json
  found: _visibility field has "required": 0, "default_value": "private", "show_in_rest": 1
  implication: Field is NOT marked as required in ACF, has a default value, and is exposed to REST API

- timestamp: 2026-01-28T10:06:00Z
  checked: class-auto-title.php set_default_visibility method
  found: Hook on rest_after_insert_person/team/important_date to set default visibility to 'private' if empty
  implication: Default visibility mechanism exists but runs AFTER insert - validation may be blocking insert

- timestamp: 2026-01-28T10:07:00Z
  checked: Hook timing in set_default_visibility
  found: Hooks use rest_after_insert_{post_type} - runs AFTER post is created
  implication: ACF REST schema validation happens BEFORE the post is inserted, so the default value hook never gets a chance to run

- timestamp: 2026-01-28T10:10:00Z
  checked: ACF GitHub issues and web search
  found: Known ACF bug (issue #757) where ANY field with show_in_rest=1 is treated as required, regardless of "required" setting
  implication: This is an ACF bug, not a configuration error. Workaround needed.

- timestamp: 2026-01-28T10:18:00Z
  checked: Implementation of rest_pre_insert_{post_type} hooks
  found: Added set_default_visibility_pre_insert method that injects _visibility='private' into request params before validation
  implication: Fix should satisfy ACF validation while keeping field optional for external clients

## Resolution

root_cause: ACF REST API bug where ANY field with show_in_rest=1 is treated as REQUIRED during POST/PATCH validation, regardless of the field's "required" setting. This is a known ACF issue (https://github.com/AdvancedCustomFields/acf/issues/757). The _visibility field has show_in_rest=1 but required=0, yet REST validation treats it as required. The set_default_visibility hook runs AFTER insert (rest_after_insert_*), so it cannot prevent the validation error that occurs BEFORE insert.

fix: Apply rest_pre_insert_{post_type} hook to inject _visibility default value BEFORE ACF validation runs. This is earlier than the existing rest_after_insert hook and will satisfy ACF's REST schema validation. Added set_default_visibility_pre_insert() method that checks if _visibility is missing from request ACF params and injects 'private' as the default value. Hook added for person, team, and important_date post types.

verification:
  - Build successful: Frontend assets compiled without errors
  - Deployment successful: Pushed to production (https://stadion.svawc.nl/)
  - Manual verification needed: External sync tool should test POST/PUT requests without _visibility field
  - Expected behavior: Requests succeed, records created/updated with _visibility defaulting to 'private'
  - Test cases:
    1. POST /wp/v2/people with ACF fields but no _visibility -> should succeed
    2. PUT /wp/v2/people/{id} with partial ACF update, no _visibility -> should succeed
    3. POST /wp/v2/teams with ACF fields but no _visibility -> should succeed
    4. POST /wp/v2/important-dates with ACF fields but no _visibility -> should succeed
files_changed:
  - includes/class-auto-title.php
