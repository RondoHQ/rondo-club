---
status: resolved
trigger: "The endpoint /wp-json/rondo/v1/custom-fields/person/metadata returns a 404 error on production"
created: 2026-01-20T10:00:00Z
updated: 2026-01-20T10:15:00Z
---

## Current Focus

hypothesis: The metadata route is registered AFTER the single-item route, causing "metadata" to match the field_key pattern [a-z0-9_-]+ instead of the dedicated metadata route
test: Verify route registration order and test theory that moving metadata route before single-item route fixes the issue
expecting: Moving metadata route registration before single-item route should fix the 404
next_action: Reorder route registrations in class-rest-custom-fields.php

## Symptoms

expected: The endpoint should return metadata about custom fields for person post type
actual: Returns 404 Not Found
errors: HTTP 404 on GET https://cael.is/wp-json/rondo/v1/custom-fields/person/metadata
reproduction: Visit the URL directly or trigger it from the frontend
started: User just noticed while debugging previous meetings issue

## Eliminated

## Evidence

- timestamp: 2026-01-20T10:05:00Z
  checked: includes/class-rest-custom-fields.php route registration
  found: The metadata route (line 117-127) is registered AFTER the single-item route (line 93-114). The single-item pattern (?P<field_key>[a-z0-9_-]+) matches "metadata" string, so WordPress routes the request there first.
  implication: Route order matters in WordPress REST API - more specific routes must be registered before more general ones

- timestamp: 2026-01-20T10:05:00Z
  checked: frontend API client src/api/client.js
  found: getCustomFieldsMetadata (line 273) calls /rondo/v1/custom-fields/{postType}/metadata
  implication: Frontend is calling the correct endpoint, but server routes it to wrong handler

## Resolution

root_cause: WordPress REST API route registration order matters. The metadata route was registered AFTER the single-item route which uses a catch-all pattern (?P<field_key>[a-z0-9_-]+). Since "metadata" matches this pattern, WordPress routed requests to get_item() instead of get_field_metadata(), resulting in a 404 ("Field not found").
fix: Reordered route registrations in class-rest-custom-fields.php to register the /metadata route BEFORE the single-item route with the generic field_key pattern.
verification: Endpoint now returns 401 (Unauthorized) instead of 404, confirming the route is correctly matched. Authentication is expected behavior for this endpoint.
files_changed: [includes/class-rest-custom-fields.php]
