---
phase: quick-003
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - docs/api-leden-crud.md
  - docs/api-important-dates.md
  - docs/api-custom-fields.md
autonomous: true

must_haves:
  truths:
    - "People API docs do not reference is_favorite field"
    - "Important Dates API has complete documentation"
    - "Custom Fields API has complete documentation"
  artifacts:
    - path: "docs/api-leden-crud.md"
      provides: "People API reference without is_favorite"
    - path: "docs/api-important-dates.md"
      provides: "Important Dates API reference"
    - path: "docs/api-custom-fields.md"
      provides: "Custom Fields API reference"
  key_links: []
---

<objective>
Update API documentation to reflect current system state and document new APIs.

Purpose: Keep API documentation accurate after v7.1.0 favorites removal and document previously undocumented APIs (Important Dates, Custom Fields)
Output: Three accurate API documentation files
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@docs/api-leden-crud.md
@includes/class-rest-custom-fields.php
@acf-json/group_important_date_fields.json
</context>

<tasks>

<task type="auto">
  <name>Task 1: Remove is_favorite from People API docs</name>
  <files>docs/api-leden-crud.md</files>
  <action>
Remove all is_favorite references from the People API documentation:

1. Line 82: Remove row from Basic Information table:
   `| acf.is_favorite | boolean | Mark as favorite | true or false |`

2. Line 222: Remove from Create example JSON:
   `"is_favorite": false,`

3. Lines 264-265: Remove from Response example:
   `"is_favorite": false,`

4. Lines 294-295: Remove from Update example:
   Change the update example to update contact_info (which is already there) - remove the is_favorite line

5. Line 337: Remove from Get response:
   `"is_favorite": true,`

6. Lines 577-579: Remove from JavaScript usage example:
   The updatePerson call that sets is_favorite

7. Line 610: Remove from PHP example:
   `update_field('is_favorite', true, $person_id);`

8. Lines 634-638: Remove the update curl example that sets is_favorite - replace with a more useful example like updating the name
  </action>
  <verify>grep -c "is_favorite" docs/api-leden-crud.md returns 0</verify>
  <done>People API docs contain no is_favorite references</done>
</task>

<task type="auto">
  <name>Task 2: Create Important Dates API documentation</name>
  <files>docs/api-important-dates.md</files>
  <action>
Create new API documentation file following the same structure as api-leden-crud.md:

1. Header with base URL and authentication (same as People API)

2. Endpoints Overview table:
   | Method | Endpoint | Description |
   | GET | /wp/v2/important-dates | List all accessible dates |
   | GET | /wp/v2/important-dates/{id} | Get single date |
   | POST | /wp/v2/important-dates | Create new date |
   | PUT | /wp/v2/important-dates/{id} | Update date |
   | DELETE | /wp/v2/important-dates/{id} | Delete date |

3. Field Reference section:
   Required fields:
   - acf.date_value (string, Y-m-d format)
   - acf.related_people (array of person post IDs)

   Optional fields:
   - acf.year_unknown (boolean, default false) - Whether the year is unknown
   - acf.is_recurring (boolean, default true) - Repeats yearly
   - acf.custom_label (string) - Override auto-generated title

4. CRUD examples (Create, Get, Update, Delete) with JSON bodies

5. Code examples in JavaScript and cURL (similar to People API)

6. Notes about:
   - Auto-generated title from date and related people
   - Access control (same as People - user only sees own dates)
   - Reminder system sends daily digest for upcoming dates
  </action>
  <verify>cat docs/api-important-dates.md shows complete documentation with all endpoints and fields</verify>
  <done>Important Dates API fully documented with CRUD examples</done>
</task>

<task type="auto">
  <name>Task 3: Create Custom Fields API documentation</name>
  <files>docs/api-custom-fields.md</files>
  <action>
Create new API documentation file for the Custom Fields management API:

1. Header explaining this is an admin-only API for managing custom field definitions

2. Authentication section noting admin-only access (manage_options capability)

3. Endpoints Overview table:
   Admin endpoints:
   | Method | Endpoint | Description |
   | GET | /stadion/v1/custom-fields/{post_type} | List all fields |
   | POST | /stadion/v1/custom-fields/{post_type} | Create new field |
   | GET | /stadion/v1/custom-fields/{post_type}/{key} | Get single field |
   | PUT | /stadion/v1/custom-fields/{post_type}/{key} | Update field |
   | DELETE | /stadion/v1/custom-fields/{post_type}/{key} | Deactivate field |
   | PUT | /stadion/v1/custom-fields/{post_type}/order | Reorder fields |

   User endpoint:
   | GET | /stadion/v1/custom-fields/{post_type}/metadata | Read-only field metadata |

   Note: {post_type} is either "person" or "team"

4. Supported field types list:
   text, textarea, number, url, email, select, checkbox, radio, true_false, date, image, file, relationship, color_picker

5. Create field parameters table with all options:
   Required: label, type
   Core: name, instructions, required, choices, default_value, placeholder
   Number: min, max, step, prepend, append
   Date: display_format, return_format, first_day
   Select/Checkbox: allow_null, multiple, ui, layout, toggle, allow_custom, save_custom
   Text/Textarea: maxlength
   True/False: ui_on_text, ui_off_text
   Image/File: preview_size, library, min_width, max_width, min_height, max_height, min_size, max_size, mime_types
   Relationship: relation_post_types, filters
   Color: enable_opacity
   Display: show_in_list_view, list_view_order
   Validation: unique

6. CRUD examples for creating, updating, listing, deleting fields

7. Metadata endpoint example showing the read-only format

8. Notes about:
   - Soft delete (deactivation) preserves data
   - Type cannot be changed after creation
   - Field key is auto-generated from label
  </action>
  <verify>cat docs/api-custom-fields.md shows complete documentation with all endpoints and field types</verify>
  <done>Custom Fields API fully documented with all field types and options</done>
</task>

</tasks>

<verification>
- [ ] grep "is_favorite" docs/api-leden-crud.md returns no matches
- [ ] docs/api-important-dates.md exists with all CRUD endpoints documented
- [ ] docs/api-custom-fields.md exists with all admin and user endpoints documented
</verification>

<success_criteria>
1. People API docs have no is_favorite references
2. Important Dates API has complete CRUD documentation
3. Custom Fields API has complete documentation for all 6 admin endpoints + metadata endpoint
4. All docs follow consistent structure with api-leden-crud.md
</success_criteria>

<output>
After completion, update STATE.md quick tasks table with this task.
</output>
