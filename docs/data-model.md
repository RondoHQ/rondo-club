# Data Model

This document describes the custom post types, taxonomies, and ACF field groups that make up the Caelis data model.

## Custom Post Types

The CRM uses three custom post types, registered in `includes/class-post-types.php`.

### Person (`person`)

Represents individual contacts in the CRM.

| Property | Value |
|----------|-------|
| REST Base | `/wp/v2/people` |
| Menu Icon | `dashicons-groups` |
| Supports | title, thumbnail, comments, author |
| Public | No (private, accessed via REST API) |

**ACF Fields** (from `acf-json/group_person_fields.json`):

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| First Name | `first_name` | text | Required. Person's first name |
| Last Name | `last_name` | text | Person's last name |
| Nickname | `nickname` | text | Informal name or alias |
| Gender | `gender` | select | Options: male, female, non_binary, other, prefer_not_to_say |
| Photo Gallery | `photo_gallery` | gallery | Up to 50 photos, first is profile photo |
| Favorite | `is_favorite` | true_false | Mark as favorite contact |
| How We Met | `how_we_met` | textarea | Story of how you met |
| When We Met | `met_date` | date_picker | Date you first met (Y-m-d) |
| Contact Info | `contact_info` | repeater | Contact methods (see below) |
| Addresses | `addresses` | repeater | Physical addresses (see below) |
| Work History | `work_history` | repeater | Employment history (see below) |
| Relationships | `relationships` | repeater | Connections to other people (see below) |

**Contact Info Sub-fields:**

| Field | Key | Type | Options |
|-------|-----|------|---------|
| Type | `contact_type` | select | email, phone, mobile, website, calendar, linkedin, twitter, instagram, facebook, other |
| Label | `contact_label` | text | e.g., "Work", "Personal" |
| Value | `contact_value` | text | The actual contact value |

**Addresses Sub-fields:**

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Label | `address_label` | text | e.g., "Home", "Work" |
| Street | `street` | text | Street address |
| Postal Code | `postal_code` | text | ZIP or postal code |
| City | `city` | text | City name |
| State/Province | `state` | text | State or province |
| Country | `country` | text | Country name |

**Work History Sub-fields:**

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Organization | `company` | post_object | Link to Organization post |
| Job Title | `job_title` | text | Position title |
| Description | `description` | textarea | Role description |
| Start Date | `start_date` | date_picker | Employment start (Y-m-d) |
| End Date | `end_date` | date_picker | Employment end (Y-m-d) |
| Current | `is_current` | true_false | Currently employed here |

**Relationships Sub-fields:**

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Person | `related_person` | post_object | Link to related Person |
| Type | `relationship_type` | taxonomy | From relationship_type taxonomy |
| Custom Label | `relationship_label` | text | Override label (e.g., "Brother-in-law") |

---

### Organization (`company`)

Represents organizations where contacts work. Note: The post type slug remains `company` for backward compatibility, but the user-facing label is "Organization".

| Property | Value |
|----------|-------|
| REST Base | `/wp/v2/companies` |
| Menu Icon | `dashicons-building` |
| Supports | title, editor, thumbnail, author |
| Public | No (private, accessed via REST API) |

**ACF Fields** (from `acf-json/group_company_fields.json`):

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Website | `website` | url | Organization website URL |
| Industry | `industry` | text | Industry or sector |
| Contact Info | `contact_info` | repeater | Organization contact methods |
| Investors | `investors` | relationship | People or organizations that have invested in this company |

**Contact Info Sub-fields:**

| Field | Key | Type | Options |
|-------|-----|------|---------|
| Type | `contact_type` | select | phone, email, address, other |
| Label | `contact_label` | text | e.g., "HQ", "Support" |
| Value | `contact_value` | text | The actual contact value |

---

### Important Date (`important_date`)

Represents significant dates to remember (birthdays, anniversaries, etc.).

| Property | Value |
|----------|-------|
| REST Base | `/wp/v2/important-dates` |
| Menu Icon | `dashicons-calendar-alt` |
| Supports | title, editor, author |
| Public | No (private, accessed via REST API) |

**ACF Fields** (from `acf-json/group_important_date_fields.json`):

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Date | `date_value` | date_picker | Required. The actual date (Y-m-d) |
| Recurring | `is_recurring` | true_false | Repeats yearly (default: true) |
| Related People | `related_people` | post_object | Required. People linked to this date |
| Remind Days Before | `reminder_days_before` | number | Days before to send reminder (0-365, default: 7) |
| Custom Label | `custom_label` | text | Override auto-generated title |

---

## Taxonomies

The CRM uses four custom taxonomies, registered in `includes/class-taxonomies.php`.

### Person Label (`person_label`)

Tags for categorizing people.

| Property | Value |
|----------|-------|
| Hierarchical | No (tag-like) |
| Attached To | person |
| REST Enabled | Yes |

**Example labels:** Family, Work, School Friends, Neighbors

---

### Organization Label (`company_label`)

Tags for categorizing organizations.

| Property | Value |
|----------|-------|
| Hierarchical | No (tag-like) |
| Attached To | company |
| REST Enabled | Yes |

**Example labels:** Clients, Vendors, Partners, Past Employers

---

### Relationship Type (`relationship_type`)

Defines the types of relationships between people.

| Property | Value |
|----------|-------|
| Hierarchical | Yes |
| Attached To | person |
| REST Enabled | Yes |

**ACF Fields** (from `acf-json/group_relationship_type_fields.json`):

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Inverse Type | `inverse_relationship_type` | taxonomy | The reciprocal relationship type |
| Gender Dependent | `is_gender_dependent` | true_false | Varies by gender (e.g., aunt/uncle) |
| Gender Group | `gender_dependent_group` | text | Group name for gender variants |

**Default Relationship Types:**

The system creates these relationship types on activation:

| Category | Types |
|----------|-------|
| Basic | partner, spouse, friend, colleague, acquaintance, ex |
| Immediate Family | parent, child, sibling |
| Extended Family | grandparent, grandchild, uncle, aunt, nephew, niece, cousin |
| Step/In-law | stepparent, stepchild, stepsibling, inlaw |
| Other Family | godparent, godchild |
| Professional | boss, subordinate, mentor, mentee |

**Inverse Mappings:**

- **Symmetric:** spouse↔spouse, friend↔friend, colleague↔colleague, sibling↔sibling, cousin↔cousin, partner↔partner
- **Asymmetric:** parent↔child, grandparent↔grandchild, stepparent↔stepchild, godparent↔godchild, boss↔subordinate, mentor↔mentee
- **Gender-dependent:** aunt/uncle↔niece/nephew (resolves based on related person's gender)

For more details, see [Relationship Types](./relationship-types.md) and [Relationships](./relationships.md).

---

### Date Type (`date_type`)

Categorizes important dates by event type.

| Property | Value |
|----------|-------|
| Hierarchical | Yes |
| Attached To | important_date |
| REST Enabled | Yes |

**Default Date Types** (aligned with Monica CRM):

| Category | Types |
|----------|-------|
| Core | birthday, memorial, first-met |
| Family & Relationships | new-relationship, engagement, wedding, marriage, expecting-a-baby, new-child, new-family-member, new-pet, end-of-relationship, loss-of-a-loved-one |
| Work & Education | new-job, retirement, new-school, study-abroad, volunteer-work, published-book-or-paper, military-service |
| Home & Living | moved, bought-a-home, home-improvement, holidays, new-vehicle, new-roommate |
| Health & Wellness | overcame-an-illness, quit-a-habit, new-eating-habits, weight-loss, surgery |
| Travel & Experiences | new-sport, new-hobby, new-instrument, new-language, travel, achievement-or-award, first-word, first-kiss |
| Fallback | other |

---

### Workspace (`workspace`)

Represents collaborative workspaces for multi-user features (Phase 7+).

| Property | Value |
|----------|-------|
| REST Base | `/wp/v2/workspaces` |
| Menu Icon | `dashicons-networking` |
| Supports | title, editor, author, thumbnail |
| Public | No (private, accessed via REST API) |

**Membership:** Stored in user meta via `PRM_Workspace_Members` class (see below).

---

## Visibility Settings

All three main post types (Person, Organization, Important Date) include visibility settings.

**ACF Fields** (from `acf-json/group_visibility_settings.json`):

| Field | Key | Type | Description |
|-------|-----|------|-------------|
| Visibility | `_visibility` | select | Control who can see this record |

**Visibility Options:**

| Value | Description |
|-------|-------------|
| `private` | Only the post author can see (default) |
| `workspace` | Visible to workspace members |
| `shared` | Shared with specific users |

**Shared With Post Meta:**

For `shared` visibility, the `_shared_with` post meta stores sharing details:

```json
[
  {
    "user_id": 5,
    "permission": "edit",
    "shared_by": 1,
    "shared_at": "2026-01-15T10:30:00Z"
  },
  {
    "user_id": 8,
    "permission": "view",
    "shared_by": 1,
    "shared_at": "2026-01-16T14:00:00Z"
  }
]
```

**Helper Class:** `PRM_Visibility` provides static methods for managing visibility:

| Method | Description |
|--------|-------------|
| `get_visibility($post_id)` | Get visibility value (returns 'private' if not set) |
| `set_visibility($post_id, $visibility)` | Set visibility value |
| `get_shares($post_id)` | Get array of share objects |
| `add_share($post_id, $user_id, $permission, $shared_by)` | Add or update a share |
| `remove_share($post_id, $user_id)` | Remove a user's share |
| `user_has_share($post_id, $user_id)` | Check if user has share access |
| `get_share_permission($post_id, $user_id)` | Get permission level ('view', 'edit', or false) |

---

## ACF JSON Sync

ACF field groups are version-controlled in `acf-json/`:

| File | Purpose |
|------|---------|
| `group_person_fields.json` | Person post type fields |
| `group_company_fields.json` | Organization post type fields |
| `group_important_date_fields.json` | Important date post type fields |
| `group_relationship_type_fields.json` | Relationship type taxonomy fields |
| `group_visibility_settings.json` | Visibility settings for all main post types |

**How it works:**

1. When `WP_DEBUG` is `true`, field changes in WordPress admin auto-save to these JSON files
2. Production loads field definitions from JSON (faster than database)
3. Changes sync via Git, keeping all environments consistent

---

## Data Access

All data is subject to row-level access control:

- Users can only see posts they created themselves
- Administrators are restricted on the frontend but have full access in WordPress admin area
- Access filtering is applied at both `WP_Query` level and REST API responses

For details, see [Access Control](./access-control.md).

---

## Related Documentation

- [Access Control](./access-control.md) - How row-level security works
- [REST API](./rest-api.md) - API endpoints for accessing data
- [Relationships](./relationships.md) - Bidirectional relationship system
- [Relationship Types](./relationship-types.md) - Configuring relationship types

