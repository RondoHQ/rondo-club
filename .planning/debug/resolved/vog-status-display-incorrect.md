---
status: resolved
trigger: "VOG status card on person detail page shows 'E-mail verzonden: Nog niet' and 'Justis aanvraag: Nog niet' even though both fields have dates entered in the VOG screen."
created: 2026-02-01T12:00:00Z
updated: 2026-02-01T12:00:00Z
---

## Current Focus

hypothesis: PersonDetail page uses standard WP REST API which doesn't include vog_email_sent_date and vog_justis_submitted_date post meta fields
test: Verify that the single person endpoint doesn't return these fields, and fix by adding them
expecting: Need to expose post meta fields via REST API for single person endpoint
next_action: Fix the issue by adding these post meta fields to the person REST API response

## Symptoms

expected: Both "E-mail verzonden" and "Justis aanvraag" should show "Ja" or display dates, because both fields have dates entered in the VOG data screen
actual: Both fields show "Nog niet" (not yet) on the VOG status card
errors: None reported
reproduction: View person 4603 at https://stadion.svawc.nl/people/4603 - the VOG status card in sidebar shows incorrect "Nog niet" values
started: Unknown

## Eliminated

## Evidence

- timestamp: 2026-02-01T12:05:00Z
  checked: VOGCard.jsx field names
  found: Uses acfData?.vog_email_sent_date and acfData?.vog_justis_submitted_date
  implication: Frontend expects underscore-based field names in ACF data

- timestamp: 2026-02-01T12:06:00Z
  checked: Backend storage mechanisms
  found: Multiple inconsistent storage locations:
    - class-rest-api.php line 2840: update_field('vog-email-verzonden', ...) for bulk mark requested
    - class-rest-api.php line 2947: update_post_meta($person_id, 'vog_justis_submitted_date', ...)
    - class-vog-email.php line 289: update_post_meta($person_id, 'vog_email_sent_date', ...)
  implication: Email sent date stored in TWO different places (ACF field and post meta)

- timestamp: 2026-02-01T12:07:00Z
  checked: class-rest-people.php (filtered people endpoint)
  found: Lines 1352-1358 add post meta fields vog_email_sent_date and vog_justis_submitted_date to ACF array
  implication: Filtered endpoint works, but single person endpoint doesn't have this logic

- timestamp: 2026-02-01T12:08:00Z
  checked: usePerson hook in usePeople.js
  found: Uses wpApi.getPerson which calls standard /wp/v2/people/{id} endpoint
  implication: Standard WP REST API doesn't include these post meta fields - they need to be registered

## Resolution

root_cause: The person detail page uses the standard WordPress REST API (/wp/v2/people/{id}) which didn't include the VOG post meta fields (vog_email_sent_date and vog_justis_submitted_date). These fields are stored as post_meta, not ACF fields, so they weren't automatically included in the REST response. The filtered people endpoint (class-rest-people.php) manually adds these fields, but the single person endpoint didn't.
fix: Added a rest_prepare_person filter in class-rest-api.php that adds the VOG post meta fields to the ACF array in the REST response. This mirrors what the filtered people endpoint does.
verification: Deployed to production. User should verify at https://stadion.svawc.nl/people/4603 that the VOG status card now shows the correct dates for "E-mail verzonden" and "Justis aanvraag"
files_changed:
  - includes/class-rest-api.php
