---
status: resolved
trigger: "custom-field-settings-not-persisting"
created: 2026-01-29T10:00:00Z
updated: 2026-01-29T10:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - ACF JSON sync is the problem
test: Delete JSON file OR trigger JSON export after field updates
expecting: After removing JSON file or properly syncing it, updates will persist
next_action: Implement fix to properly handle ACF JSON sync for custom fields

## Symptoms

expected: After saving settings, "Tonen als kolom in lijstweergave" should remain checked AND the custom field should appear as a column in the PeopleList
actual: Settings appear to save momentarily but reset/revert when the page is reloaded - neither the checkbox stays checked nor does the column appear
errors: None mentioned
reproduction: Go to settings, check the "Tonen als kolom in lijstweergave" checkbox for a custom field, save, then reload the page - the setting is lost
started: Was working before, recently broke

## Eliminated

## Evidence

- timestamp: 2026-01-29T10:05:00Z
  checked: Data flow from frontend to backend
  found: FieldFormPanel submits show_in_list_view and list_view_order in submitData (lines 363-366). REST API accepts these params (lines 697-706 in class-rest-custom-fields.php). Manager class has these in UPDATABLE_PROPERTIES (lines 100-101 in class-manager.php). Data flow appears complete.
  implication: The save mechanism should work - need to verify what's actually being stored

- timestamp: 2026-01-29T10:08:00Z
  checked: ACF JSON sync files in acf-json/ directory
  found: No group_custom_fields_person.json or group_custom_fields_team.json files exist. Other field groups (person_fields, team_fields, etc.) have JSON files. Commit 18ea76c removed visibility-related ACF JSON file.
  implication: Custom fields groups might be database-only (not synced to JSON) OR there's a JSON/database conflict where JSON is overwriting database changes

- timestamp: 2026-01-29T10:12:00Z
  checked: group_person_fields.json content
  found: This is the built-in person fields group, not the user-created custom fields. Custom fields are in a separate group (group_custom_fields_person).
  implication: Need to check production database to see if custom fields are being created and if show_in_list_view is stored

- timestamp: 2026-01-29T10:15:00Z
  checked: Production database field content (KNVB ID field, ID 1063)
  found: Field HAS show_in_list_view and list_view_order properties in database: s:17:"show_in_list_view";b:0; and s:15:"list_view_order";i:999;
  implication: Data IS being saved to database. Problem must be on the READ side - either API not returning these properties, or frontend not reading them correctly

- timestamp: 2026-01-29T10:18:00Z
  checked: Production API response for /rondo/v1/custom-fields/person
  found: API correctly returns "show_in_list_view": false, "list_view_order": 999 for KNVB ID field
  implication: Both database and API are working. Problem must be in frontend form state or update logic

- timestamp: 2026-01-29T10:25:00Z
  checked: All custom person fields in production database
  found: ALL fields have show_in_list_view set to false (b:0). None are set to true.
  implication: Either updates are failing silently, or there's a default value overriding user input, or ACF is filtering out the property on save

- timestamp: 2026-01-29T10:28:00Z
  checked: Sent PUT request to API with show_in_list_view=true
  found: API responded with success {"show_in_list_view": true, "list_view_order": 1}, BUT database still has b:0 and i:999 (unchanged!)
  implication: ACF's acf_update_field() is NOT persisting show_in_list_view and list_view_order to the database. These properties are being stripped during save.

- timestamp: 2026-01-29T10:45:00Z
  checked: After deploying fix with improved sync_field_group_to_json()
  found: JSON file now has 10 fields with correct show_in_list_view and list_view_order values. KNVB ID field shows show_in_list_view: true, list_view_order: 1 in both JSON and API response.
  implication: Fix is working! JSON sync now properly includes fields when writing to acf-json/group_custom_fields_person.json

## Resolution

root_cause: ACF Local JSON feature is loading field definitions from acf-json/group_custom_fields_person.json which has show_in_list_view=false for all fields. JSON takes precedence over database, so any updates made via the Settings UI are immediately overwritten when ACF loads the JSON file. The JSON file existed on production but not in the git repository, and the sync_field_group_to_json() method was not properly loading fields before writing to JSON.
fix: Added and improved sync_field_group_to_json() method to Manager class that properly loads all fields from database using acf_get_fields() and includes them in the group array before calling acf_write_json_field_group(). This is called after every create, update, deactivate, reactivate, and reorder operation. Also added the JSON file to version control.
verification: Tested by updating KNVB ID field via API with show_in_list_view=true and list_view_order=1. Confirmed JSON file was updated with correct values and subsequent API calls return the updated values. Fix is working correctly.
files_changed: ['includes/customfields/class-manager.php', 'acf-json/group_custom_fields_person.json']
