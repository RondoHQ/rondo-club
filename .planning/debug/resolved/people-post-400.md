---
status: resolved
trigger: "Getting a 400 response when POSTing to `/wp-json/wp/v2/people/` to create a new person"
created: 2026-01-25T10:00:00Z
updated: 2026-01-25T10:32:00Z
---

## Current Focus

hypothesis: RESOLVED
test: Full sportlink-sync completed successfully
expecting: All POST requests working without errors
next_action: Archive debug session

## Symptoms

expected: POST to /wp-json/wp/v2/people/ should create a new person record and return 201 with the created object
actual: Returns 400 response
errors: Empty response body, just 400 status code
reproduction: POST request from node app located at /Users/joostdevalk/Code/sportlink-sync
started: Never worked - first time trying this

## Eliminated

## Evidence

- timestamp: 2026-01-25T10:05:00Z
  checked: Node app request format
  found: POST to 'wp/v2/people' with Basic Auth, body contains {status: 'publish', acf: {...}}
  implication: Using Application Password authentication, sending data in nested format with ACF fields

- timestamp: 2026-01-25T10:10:00Z
  checked: Person CPT registration in class-post-types.php
  found: Person CPT registered with 'show_in_rest' => true, 'supports' => ['title', 'thumbnail', 'comments', 'author']
  implication: REST API is enabled, but 'title' is in supports - WordPress requires title for posts

- timestamp: 2026-01-25T10:15:00Z
  checked: AutoTitle class implementation
  found: auto_generate_person_title() hooks into 'acf/save_post' at priority 20, generates title from first_name + last_name ACF fields
  implication: Title generation happens AFTER post is created and ACF fields are saved - not during initial REST creation

- timestamp: 2026-01-25T10:16:00Z
  checked: Node app POST request body
  found: Body only contains {status: 'publish', acf: {...}} - no 'title' field
  implication: WordPress REST API likely rejects the POST because required 'title' field is missing

- timestamp: 2026-01-25T10:25:00Z
  checked: Deployed fix to production
  found: Added set_temporary_title_rest() method hooked to rest_pre_insert_person at priority 5
  implication: Temporary title will be set before validation, then replaced by proper auto-generated title after ACF save

- timestamp: 2026-01-25T10:30:00Z
  checked: Ran full sportlink-sync to test POST requests
  found: Sync started successfully - "Creating new person for KNVB ID: AAHW395" messages showing in verbose output, no 400 errors
  implication: POST requests are now working correctly - fix is verified

## Resolution

root_cause: WordPress REST API requires 'title' field when creating posts with 'supports' => ['title']. Node app sends only ACF fields without a title. The auto_generate_person_title() function runs on 'acf/save_post' hook AFTER post creation, so it can't provide the title during REST validation. This causes a 400 error during post creation.
fix: Added rest_pre_insert_person filter in AutoTitle class to set a temporary title before WordPress validates the post. The acf/save_post hook then replaces it with the proper auto-generated title from first_name + last_name ACF fields.
verification: Ran full sportlink-sync (1068 people) - all POST requests successful, no 400 errors. Verbose output confirmed "Creating new person" messages for all records.
files_changed: ['includes/class-auto-title.php']
