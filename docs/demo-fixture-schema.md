# Demo Fixture Schema

This document defines the JSON fixture format used for importing/exporting demo data in Rondo Club. The fixture is a single, self-contained JSON file that captures all entities, their relationships, and site-wide settings needed for a working demo.

## Overview

The fixture format serves as a contract between:
- **Phase 171 (Export)**: Exports production data to this format
- **Phase 173 (Import)**: Imports fixture data into a fresh WordPress instance

The fixture is portable across WordPress installations using a reference ID system that eliminates dependencies on specific WordPress post IDs.

## Top-Level Structure

```json
{
  "meta": { ... },
  "people": [ ... ],
  "teams": [ ... ],
  "commissies": [ ... ],
  "discipline_cases": [ ... ],
  "todos": [ ... ],
  "comments": [ ... ],
  "taxonomies": { ... },
  "settings": { ... }
}
```

## Section Specifications

### `meta` (object)

Metadata about the fixture itself.

**Fields:**
- `version` (string, required): Schema version, e.g. `"1.0"`
- `exported_at` (string, required): ISO 8601 timestamp of when the fixture was created (e.g. `"2026-02-11T10:30:00Z"`)
- `source` (string, required): Description of the source (e.g. `"Production export, anonymized"`)
- `record_counts` (object, required): Count of each entity type for quick validation
  - `people` (number): Count of person records
  - `teams` (number): Count of team records
  - `commissies` (number): Count of commissie records
  - `discipline_cases` (number): Count of discipline case records
  - `todos` (number): Count of todo records
  - `comments` (number): Count of comment records (notes + activities + emails)

**Example:**
```json
{
  "version": "1.0",
  "exported_at": "2026-02-11T10:30:00Z",
  "source": "Production export, anonymized",
  "record_counts": {
    "people": 250,
    "teams": 45,
    "commissies": 12,
    "discipline_cases": 8,
    "todos": 15,
    "comments": 320
  }
}
```

---

### `people` (array of objects)

Each person object represents a contact record (custom post type `person`).

**Fields:**

- `_ref` (string, required): Temporary reference ID for cross-entity linking. Format: `"person:1"`, `"person:2"`, etc. These are NOT WordPress post IDs - they're fixture-internal references used during import to wire up relationships.

- `title` (string, required): Post title (auto-generated from name fields by WordPress)

- `status` (string, required): Post status. Always `"publish"` for demo fixtures.

- `acf` (object, required): All ACF and Sportlink-synced meta fields

  **Basic Information:**
  - `first_name` (string, required): First name
  - `infix` (string, nullable): Tussenvoegsel (e.g., "van", "de", "van der")
  - `last_name` (string, required): Last name
  - `nickname` (string, nullable): Preferred nickname
  - `gender` (string, nullable): One of `"male"`, `"female"`, `"non_binary"`, `"other"`, `"prefer_not_to_say"`
  - `pronouns` (string, nullable): Preferred pronouns (e.g., "they/them", "she/her")
  - `birthdate` (string, required): Date of birth in format `"Y-m-d"` (e.g., `"1985-03-15"`)
  - `former_member` (boolean, required): Whether person is a former member (default: `false`)
  - `lid-tot` (string, nullable): Date membership ended, format `"Y-m-d"`
  - `datum-overlijden` (string, nullable): Date of death, format `"Y-m-d"`

  **Contact Information:**
  - `contact_info` (array of objects): Contact methods
    - `contact_type` (string): One of `"email"`, `"phone"`, `"mobile"`, `"website"`, `"calendar"`, `"linkedin"`, `"twitter"`, `"bluesky"`, `"threads"`, `"instagram"`, `"facebook"`, `"other"`
    - `contact_label` (string): Label (e.g., "Work", "Personal")
    - `contact_value` (string): The actual contact value

  **Addresses:**
  - `addresses` (array of objects): Physical addresses
    - `address_label` (string): Label (e.g., "Home", "Work")
    - `street` (string): Street address
    - `postal_code` (string): Postal/ZIP code
    - `city` (string): City name
    - `state` (string): State/province
    - `country` (string): Country name

  **Work History:**
  - `work_history` (array of objects): Team/commissie positions
    - `team` (string, nullable): Reference to team or commissie (e.g., `"team:5"` or `"commissie:2"`)
    - `entity_type` (string): Type of entity, either `"team"` or `"commissie"`
    - `job_title` (string): Position title
    - `description` (string, nullable): Description of role
    - `start_date` (string, nullable): Start date in format `"Y-m-d"`
    - `end_date` (string, nullable): End date in format `"Y-m-d"`
    - `is_current` (boolean): Whether this is a current position

  **Relationships:**
  - `relationships` (array of objects): Relationships to other people
    - `related_person` (string): Reference to another person (e.g., `"person:5"`)
    - `relationship_type` (string): Reference to relationship type taxonomy term (e.g., `"relationship_type:parent"`)
    - `relationship_label` (string, nullable): Custom label for this relationship (e.g., "Brother-in-law")

  **Sportlink-Synced Fields** (all nullable):
  - `lid-sinds` (string): Member since date, format `"Y-m-d"`
  - `leeftijdsgroep` (string): Age class description (e.g., "Onder 10", "Senioren")
  - `datum-vog` (string): VOG (certificate of conduct) date, format `"Y-m-d"`
  - `datum-foto` (string): Photo date, format `"Y-m-d"`
  - `type-lid` (string): Member type
  - `huidig-vrijwilliger` (string): Current volunteer status, either `"0"` or `"1"`
  - `financiele-blokkade` (boolean): Financial block flag
  - `relatiecode` (string): KNVB relation code
  - `werkfuncties` (array of objects): Job functions synced from Sportlink
    - Same structure as `work_history` above
  - `freescout-id` (string): FreeScout customer ID
  - `factuur-adres` (string): Invoice address
  - `factuur-email` (string): Invoice email
  - `factuur-referentie` (string): Invoice reference

- `post_meta` (object, optional): Non-ACF post meta fields
  - `vog_email_sent_date` (string, nullable): Date VOG email was sent, format `"Y-m-d"`
  - `vog_justis_submitted_date` (string, nullable): Date Justis was submitted, format `"Y-m-d"`
  - `vog_reminder_sent_date` (string, nullable): Date VOG reminder was sent, format `"Y-m-d"`
  - `_nikki_{year}_total` (number, nullable): Nikki hours total for a given year (e.g., `_nikki_2024_total`)
  - `_nikki_{year}_saldo` (number, nullable): Nikki hours balance for a given year (e.g., `_nikki_2024_saldo`)
  - `_fee_snapshot_{season}` (object, nullable): Cached fee calculation for a season (serialized PHP object stored as JSON)
  - `_fee_forecast_{season}` (object, nullable): Cached fee forecast for a season (serialized PHP object stored as JSON)

**Note:** The `photo_gallery` field is explicitly EXCLUDED from fixtures per EXPORT-04. No thumbnail or featured image data is included.

---

### `teams` (array of objects)

Each team object represents a sports team (custom post type `team`). Teams are synced from Sportlink.

**Fields:**

- `_ref` (string, required): Temporary reference ID. Format: `"team:1"`, `"team:2"`, etc.

- `title` (string, required): Team name (preserved unchanged per EXPORT-06)

- `content` (string, nullable): Post content/description (WordPress editor content)

- `status` (string, required): Post status (typically `"publish"`)

- `parent` (string, nullable): Reference to parent team if hierarchical (e.g., `"team:3"`). Teams support parent-child relationships.

- `acf` (object, required): ACF fields
  - `website` (string, nullable): Team website URL
  - `contact_info` (array of objects): Contact methods (same structure as person contact_info)
    - `contact_type` (string): One of `"phone"`, `"email"`, `"address"`, `"other"`
    - `contact_label` (string): Label
    - `contact_value` (string): Value

---

### `commissies` (array of objects)

Each commissie object represents a committee (custom post type `commissie`). Commissies are synced from Sportlink.

**Fields:**

Same structure as teams, but with `_ref` format `"commissie:1"`, `"commissie:2"`, etc.

- `_ref` (string, required): e.g., `"commissie:1"`
- `title` (string, required): Commissie name (preserved unchanged per EXPORT-06)
- `content` (string, nullable): Post content/description
- `status` (string, required): Post status
- `parent` (string, nullable): Reference to parent commissie (e.g., `"commissie:2"`)
- `acf` (object, required): ACF fields
  - `website` (string, nullable): Website URL
  - `contact_info` (array of objects): Contact methods (same structure as teams)

---

### `discipline_cases` (array of objects)

Each discipline case represents a sports disciplinary action (custom post type `discipline_case`). Synced from Sportlink.

**Fields:**

- `_ref` (string, required): Temporary reference ID. Format: `"discipline_case:1"`, etc.

- `title` (string, required): Case title (auto-generated or manual)

- `status` (string, required): Post status

- `acf` (object, required): ACF fields
  - `dossier_id` (string, required): Unique identifier from Sportlink
  - `person` (string, nullable): Reference to linked person (e.g., `"person:42"`)
  - `match_date` (string, nullable): Date of match/incident in format `"Ymd"` (e.g., `"20240315"`)
  - `processing_date` (string, nullable): Date case was processed in format `"Ymd"`
  - `match_description` (string, nullable): Description of the match
  - `team_name` (string, nullable): Name of the involved team
  - `charge_codes` (string, nullable): Article number of the offense
  - `charge_description` (string, nullable): Description of the offense
  - `sanction_description` (string, nullable): Description of the sanction
  - `administrative_fee` (number, nullable): Administrative fee in euros
  - `is_charged` (boolean, required): Whether costs have been charged to the person

- `seizoen` (string, nullable): Season slug (e.g., `"2024-2025"`). This is a taxonomy term slug, not a reference ID.

---

### `todos` (array of objects)

Each todo represents a task (custom post type `rondo_todo`).

**Fields:**

- `_ref` (string, required): Temporary reference ID. Format: `"todo:1"`, etc.

- `title` (string, required): Todo title

- `content` (string, nullable): Todo body/description (WordPress editor content)

- `status` (string, required): Custom post status. One of:
  - `"rondo_open"`: Open todo
  - `"rondo_awaiting"`: Awaiting response
  - `"rondo_completed"`: Completed

- `date` (string, required): Post creation date in ISO 8601 format (e.g., `"2026-02-11T10:30:00"`)

- `acf` (object, required): ACF fields
  - `related_persons` (array of strings, required): References to related people (e.g., `["person:1", "person:5"]`)
  - `notes` (string, nullable): Additional notes (WYSIWYG HTML content)
  - `awaiting_since` (string, nullable): Timestamp when todo entered awaiting status, format `"Y-m-d H:i:s"`
  - `due_date` (string, nullable): Due date in format `"Y-m-d"`

---

### `comments` (array of objects)

Notes, activities, and email logs stored as WordPress comments on person posts. Comments use custom comment types to distinguish between types.

**Fields:**

- `type` (string, required): Comment type. One of:
  - `"rondo_note"`: User-written note
  - `"rondo_activity"`: Activity log (meeting, call, etc.)
  - `"rondo_email"`: Automated email log (VOG emails)

- `person` (string, required): Reference to the person post this comment belongs to (e.g., `"person:1"`)

- `content` (string, required): Comment content (HTML allowed)

- `date` (string, required): Comment date in ISO 8601 format (e.g., `"2026-02-11T10:30:00"`)

- `author_id` (number, nullable): WordPress user ID of the comment author. Set to `null` for system-generated comments. During import, this will be mapped to the importing user.

- `meta` (object, optional): Comment meta fields (varies by type)

  **For `rondo_activity` type:**
  - `activity_type` (string, nullable): Type of activity (e.g., "Meeting", "Phone call")
  - `activity_date` (string, nullable): Date of the activity, format `"Y-m-d"`
  - `activity_time` (string, nullable): Time of the activity (e.g., "14:30")
  - `participants` (array of numbers, nullable): WordPress post IDs of other people involved (imported as fixture refs during export, resolved during import)

  **For `rondo_email` type:**
  - `email_template_type` (string): Template type used (`"new"` or `"renewal"`)
  - `email_recipient` (string): Email recipient address
  - `email_subject` (string): Email subject line
  - `email_content_snapshot` (string): Full rendered HTML content of the email

  **For `rondo_note` type:**
  - `_note_visibility` (string): Note visibility setting (`"private"` or `"shared"`)

---

### `taxonomies` (object)

Taxonomy terms that need to exist for the fixture to work. Terms are organized by taxonomy.

**Fields:**

- `relationship_types` (array of objects): Relationship type taxonomy terms
  - `_ref` (string, required): Reference ID using slug format (e.g., `"relationship_type:parent"`, `"relationship_type:child"`)
  - `name` (string, required): Term name (e.g., "Parent")
  - `slug` (string, required): Term slug (e.g., "parent")
  - `acf` (object, optional): ACF fields for the term
    - `inverse_relationship_type` (string, nullable): Reference to the inverse relationship type (e.g., `"relationship_type:child"` for a parent relationship, `"relationship_type:parent"` for a child relationship, `"relationship_type:sibling"` for sibling - same as itself)

- `seizoenen` (array of objects): Season taxonomy terms
  - `name` (string, required): Season name (e.g., "2024-2025")
  - `slug` (string, required): Season slug (e.g., "2024-2025")
  - `is_current` (boolean, required): Whether this is the current season (stored as term meta `is_current_season`)

**Example:**
```json
{
  "relationship_types": [
    {
      "_ref": "relationship_type:parent",
      "name": "Parent",
      "slug": "parent",
      "acf": {
        "inverse_relationship_type": "relationship_type:child"
      }
    },
    {
      "_ref": "relationship_type:child",
      "name": "Child",
      "slug": "child",
      "acf": {
        "inverse_relationship_type": "relationship_type:parent"
      }
    },
    {
      "_ref": "relationship_type:sibling",
      "name": "Sibling",
      "slug": "sibling",
      "acf": {
        "inverse_relationship_type": "relationship_type:sibling"
      }
    }
  ],
  "seizoenen": [
    {
      "name": "2024-2025",
      "slug": "2024-2025",
      "is_current": true
    }
  ]
}
```

---

### `settings` (object)

WordPress options needed for the demo to function properly. All options are stored in the `wp_options` table.

**Fields:**

- `rondo_club_name` (string, required): Name of the sports club

- `rondo_membership_fees_{season}` (object, nullable): Fee category configuration per season. Keys are season slugs (e.g., `"rondo_membership_fees_2024-2025"`). Value is an object where keys are category slugs and values are category configurations:
  - `label` (string): Display label for the category (e.g., "Jeugd")
  - `amount` (number): Fee amount in euros
  - `age_classes` (array of strings): Matching age classes (e.g., `["Onder 10", "Onder 12"]`)
  - `matching_werkfuncties` (array of strings): Matching job function titles that qualify for this category

**Example:**
```json
{
  "jeugd": {
    "label": "Jeugd",
    "amount": 150,
    "age_classes": ["Onder 10", "Onder 12", "Onder 14"],
    "matching_werkfuncties": []
  },
  "senioren": {
    "label": "Senioren",
    "amount": 250,
    "age_classes": ["Senioren"],
    "matching_werkfuncties": []
  }
}
```

- `rondo_family_discount_{season}` (object, nullable): Family discount configuration per season. Keys are season slugs (e.g., `"rondo_family_discount_2024-2025"`). Value is an object:
  - `type` (string): Discount type, either `"fixed"` or `"percentage"`
  - `fixed_amount` (number, nullable): Fixed amount discount in euros (if type is `"fixed"`)
  - `percentage` (number, nullable): Percentage discount (if type is `"percentage"`)

- `rondo_player_roles` (array of objects): Player role configuration for fee calculations
  - Each object has `value` (role identifier) and `label` (display label)

- `rondo_excluded_roles` (array of objects): Excluded role configuration for fee calculations
  - Each object has `value` (role identifier) and `label` (display label)

- `rondo_vog_from_email` (string, nullable): VOG sender email address

- `rondo_vog_from_name` (string, nullable): VOG sender name

- `rondo_vog_template_new` (string, nullable): VOG email template for new VOG requests (HTML)

- `rondo_vog_template_renewal` (string, nullable): VOG email template for renewal requests (HTML)

- `rondo_vog_reminder_template_new` (string, nullable): VOG reminder email template for new requests (HTML)

- `rondo_vog_reminder_template_renewal` (string, nullable): VOG reminder email template for renewals (HTML)

- `rondo_vog_exempt_commissies` (array of strings, nullable): References to commissies exempt from VOG requirements (e.g., `["commissie:1", "commissie:3"]`). During import, these refs are resolved to WordPress post IDs.

---

## Reference ID System

All cross-entity references in the fixture use a temporary reference ID system instead of WordPress post IDs. This makes the fixture portable across WordPress installations.

### Reference Format

References use the format `"{entity_type}:{sequential_number}"` or `"{entity_type}:{slug}"` for taxonomies.

**Examples:**
- People: `"person:1"`, `"person:2"`, `"person:42"`
- Teams: `"team:1"`, `"team:5"`
- Commissies: `"commissie:1"`, `"commissie:3"`
- Discipline cases: `"discipline_case:1"`
- Todos: `"todo:1"`
- Relationship types: `"relationship_type:parent"`, `"relationship_type:sibling"`

### Resolution Flow

1. **Export (Phase 171)**: WordPress post IDs are converted to fixture refs
2. **Import (Phase 173)**: The importer maintains a mapping from fixture refs to newly created WordPress post IDs
3. As each entity is imported, its fixture ref is mapped to the new WordPress post ID
4. When importing relationships, the importer looks up the target's WordPress post ID from the mapping

**Example:**
- Person A has `work_history[0].team = "team:5"`
- During import:
  - Team with `_ref: "team:5"` is imported first → creates WordPress post ID 123
  - Mapping stores: `"team:5" => 123`
  - Person A is imported → when processing work_history, `"team:5"` is resolved to `123`
  - ACF field stores the actual WordPress post ID `123`

### Reference Scope

References are used for:
- Person → Person (relationships)
- Person → Team/Commissie (work history, werkfuncties)
- Person → Relationship Type taxonomy (relationships)
- Discipline Case → Person (linked person)
- Todo → Person (related persons, can be multiple)
- Comment → Person (which person the comment belongs to)
- Team → Team (parent hierarchy)
- Commissie → Commissie (parent hierarchy)
- Settings → Commissie (VOG exempt list)
- Relationship Type → Relationship Type (inverse mappings)

---

## Excluded Data

The following data types are explicitly **NOT** included in fixtures:

### Media & Attachments
- `photo_gallery` field on person posts (per EXPORT-04)
- Featured images / post thumbnails
- Any uploaded media files or attachments

### Removed Post Types & Taxonomies
- `important_dates` custom post type (being removed from codebase)
- `person_label` taxonomy (being removed)
- `team_label` taxonomy (being removed)
- `commissie_label` taxonomy (being removed)
- `date_types` taxonomy (being removed)

### OAuth & External Integrations
- OAuth tokens and refresh tokens
- Calendar connections (Google Calendar, CalDAV)
- CardDAV connection settings
- FreeScout synchronization state
- Laposta synchronization state

### User Accounts
- WordPress user accounts (users are created separately)
- User passwords
- User roles and capabilities (except the Rondo User role, which is created by the theme)
- User preferences and settings

### Internal/Development Data
- `rondo_feedback` posts (internal development feedback)
- `calendar_event` posts (per-user OAuth data, out of scope)
- Plugin settings not related to Rondo Club theme
- WordPress core settings (site URL, permalinks, etc.)

---

## Import Order Dependencies

When importing a fixture, entities must be imported in this order to satisfy dependencies:

1. **Taxonomies** (relationship_types, seizoenen)
2. **Teams** (resolve parent references after all teams imported)
3. **Commissies** (resolve parent references after all commissies imported)
4. **People** (resolve relationships and work_history after all people imported)
5. **Discipline Cases** (reference people and seizoenen)
6. **Todos** (reference people)
7. **Comments** (reference people)
8. **Settings** (reference commissies for VOG exempt list)

Within each entity type, forward references may exist (e.g., Person A references Person B who hasn't been imported yet). The importer must handle this by:
- Importing all entities of a type first (creating WordPress posts)
- Building the ref → post ID mapping
- Then resolving all references in a second pass

---

## Version History

### Version 1.0 (2026-02-11)
- Initial schema definition
- Covers all entity types: people, teams, commissies, discipline cases, todos, comments
- Includes settings and taxonomies
- Reference ID system for cross-entity linking
- Excludes photos, removed CPTs, OAuth data, and user accounts

---

## Notes for Implementers

### Export (Phase 171)
- Query all entities of each type
- Convert WordPress post IDs to fixture refs
- Serialize ACF data to the fixture format
- Handle repeater fields and nested structures
- Generate sequential reference IDs for each entity type
- Anonymize data if needed (names, emails, addresses)

### Import (Phase 173)
- Validate fixture format and version
- Create entities in dependency order
- Build ref → post ID mapping as entities are created
- Resolve references in a second pass after all entities of a type are imported
- Handle ACF field updates via `update_field()` function
- Create taxonomy terms before importing entities that reference them
- Store WordPress options for settings

### Testing
- Use the example fixture (`demo-fixture.example.json`) as a smoke test
- Verify all reference types resolve correctly
- Test with minimal fixture (few entities) and larger fixtures (hundreds of entities)
- Validate that imported data displays correctly in the Rondo Club UI
- Test that relationships are bidirectional (parent/child, inverse relationships)
