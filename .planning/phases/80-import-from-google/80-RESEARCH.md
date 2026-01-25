# Phase 80: Import from Google - Research

**Researched:** 2026-01-17
**Domain:** Google People API Integration, WordPress Import Patterns
**Confidence:** HIGH

## Summary

This phase implements one-directional import of Google Contacts into Stadion via the Google People API. Phase 79 has already established the OAuth connection infrastructure including token storage, refresh handling, and a `has_pending_import` flag mechanism.

The codebase has extensive prior art for contact imports (Monica CRM, Google Contacts CSV, vCard) that establishes clear patterns for: batch processing, photo sideloading, team creation/matching, birthday as important_date, and field mapping to ACF repeater fields.

Key decisions from CONTEXT.md: match by email only (skip contacts without email), fill gaps only (never overwrite existing Stadion data), import all emails/phones to repeaters, skip notes/labels.

**Primary recommendation:** Build a new `GoogleContactsAPIImport` class following the established import patterns, triggered via REST endpoint that checks `has_pending_import` flag or manual trigger.

## Google People API Response Structure

### API Endpoint
`people.connections.list` with `resourceName: people/me`

### Request Configuration
```php
$personFields = 'names,emailAddresses,phoneNumbers,addresses,teams,birthdays,photos,biographies,urls,metadata';
$pageSize = 100; // Valid: 1-1000, default 100
```

### Response Structure
```json
{
  "connections": [Person, Person, ...],
  "nextPageToken": "string|null",
  "nextSyncToken": "string",
  "totalItems": 123
}
```

### Person Resource (relevant fields only)

**resourceName** (string)
- Format: `people/{personId}`
- Store as `google_contact_id` for linking
- Example: `people/c1234567890123456789`

**etag** (string)
- Change detection marker
- Store as `google_etag`

**names** (array)
```json
{
  "givenName": "John",
  "familyName": "Doe",
  "displayName": "John Doe" // output only, fallback
}
```

**emailAddresses** (array)
```json
{
  "value": "john@example.com",
  "type": "home|work|other|custom",
  "metadata": { "primary": true }
}
```

**phoneNumbers** (array)
```json
{
  "value": "+1234567890",
  "canonicalForm": "+1234567890", // E.164 format
  "type": "home|work|mobile|homeFax|workFax|otherFax|pager|workMobile|workPager|main|googleVoice|other"
}
```

**addresses** (array)
```json
{
  "streetAddress": "123 Main St",
  "extendedAddress": "Apt 4B",
  "city": "New York",
  "region": "NY",           // state/province
  "postalCode": "10001",
  "country": "USA",
  "countryCode": "US",      // ISO 3166-1 alpha-2
  "type": "home|work|other"
}
```

**teams** (array)
```json
{
  "name": "Acme Inc",
  "title": "Software Engineer",
  "department": "Engineering",
  "current": true,
  "startDate": { "year": 2020, "month": 1, "day": 15 },
  "endDate": { "year": null, "month": null, "day": null }
}
```

**birthdays** (array)
```json
{
  "date": {
    "year": 1990,  // may be 0 if unknown
    "month": 10,
    "day": 31
  }
}
```

**photos** (array)
```json
{
  "url": "https://lh3.googleusercontent.com/...",
  "default": false,
  "metadata": { "primary": true }
}
```
Note: Append `?sz=400` to URL for larger size (default is small).

**biographies** (array)
```json
{
  "value": "Biography text...",
  "contentType": "TEXT_PLAIN|TEXT_HTML"
}
```
Per CONTEXT.md: **Skip biographies/notes** - not imported.

**urls** (array)
```json
{
  "value": "https://example.com",
  "type": "home|work|blog|profile|homePage|ftp|custom"
}
```

### Source: [Google People API Person Resource](https://developers.google.com/people/api/rest/v1/people#Person)

## Stadion Person Data Model

### ACF Fields Structure (from group_person_fields.json)

**Basic Info:**
| Field Key | Field Name | Type | Notes |
|-----------|------------|------|-------|
| `field_first_name` | `first_name` | text | Required |
| `field_last_name` | `last_name` | text | Optional |
| `field_nickname` | `nickname` | text | |
| `field_gender` | `gender` | select | male, female, non_binary, other, prefer_not_to_say |
| `field_pronouns` | `pronouns` | text | |
| `field_is_favorite` | `is_favorite` | true_false | |
| `field_how_we_met` | `how_we_met` | textarea | |
| `field_met_date` | `met_date` | date_picker | Y-m-d |

**Contact Info (repeater):**
| Field Key | Field Name | Type | Choices |
|-----------|------------|------|---------|
| `field_contact_info` | `contact_info` | repeater | |
| `field_contact_type` | `contact_type` | select | email, phone, mobile, website, calendar, linkedin, twitter, bluesky, threads, instagram, facebook, slack, other |
| `field_contact_label` | `contact_label` | text | e.g., "Work", "Personal" |
| `field_contact_value` | `contact_value` | text | |

**Addresses (repeater):**
| Field Key | Field Name | Type |
|-----------|------------|------|
| `field_addresses` | `addresses` | repeater |
| `field_address_label` | `address_label` | text |
| `field_address_street` | `street` | text |
| `field_address_postal_code` | `postal_code` | text |
| `field_address_city` | `city` | text |
| `field_address_state` | `state` | text |
| `field_address_country` | `country` | text |

**Work History (repeater):**
| Field Key | Field Name | Type |
|-----------|------------|------|
| `field_work_history` | `work_history` | repeater |
| `field_work_team` | `team` | post_object (team CPT) |
| `field_work_job_title` | `job_title` | text |
| `field_work_description` | `description` | textarea |
| `field_work_start_date` | `start_date` | date_picker |
| `field_work_end_date` | `end_date` | date_picker |
| `field_work_is_current` | `is_current` | true_false |

## Stadion Team Data Model

### ACF Fields (from group_team_fields.json)

| Field Key | Field Name | Type |
|-----------|------------|------|
| `field_team_website` | `website` | url |
| `field_team_industry` | `industry` | text |
| `field_team_contact_info` | `contact_info` | repeater |

**Team Matching Strategy:**
Per existing imports, teams are matched by exact title using `get_page_by_title($name, OBJECT, 'team')`.

## Important Date Structure

### ACF Fields (from group_important_date_fields.json)

| Field Key | Field Name | Type | Notes |
|-----------|------------|------|-------|
| `field_date_value` | `date_value` | date_picker | Required, Y-m-d format |
| `field_year_unknown` | `year_unknown` | true_false | Set if year is 0 |
| `field_is_recurring` | `is_recurring` | true_false | Default true for birthdays |
| `field_related_people` | `related_people` | post_object (person) | Array of IDs |
| `field_custom_label` | `custom_label` | text | |

**Taxonomy:** `date_type` with term `birthday` (slug: `birthday`)

### Birthday Creation Pattern (from existing imports)
```php
// Check if birthday already exists
$existing = get_posts([
    'post_type' => 'important_date',
    'posts_per_page' => 1,
    'meta_query' => [
        ['key' => 'related_people', 'value' => '"' . $post_id . '"', 'compare' => 'LIKE']
    ],
    'tax_query' => [
        ['taxonomy' => 'date_type', 'field' => 'slug', 'terms' => 'birthday']
    ],
]);

if (!empty($existing)) return; // Already has birthday

// Create the important_date post
$date_post_id = wp_insert_post([...]);
update_field('date_value', $date_formatted, $date_post_id);
update_field('is_recurring', true, $date_post_id);
update_field('related_people', [$post_id], $date_post_id);
wp_set_post_terms($date_post_id, [$term_id], 'date_type');
```

## GoogleOAuth/GoogleContacts Infrastructure (Phase 79)

### OAuth Classes

**`Stadion\Calendar\GoogleOAuth`** (`includes/class-google-oauth.php`)
- `is_configured()` - Checks GOOGLE_CALENDAR_CLIENT_ID/SECRET
- `get_contacts_client($include_granted_scopes, $readonly)` - Returns configured Google\Client for contacts
- `get_contacts_auth_url($user_id, $readonly)` - Generates OAuth URL
- `handle_contacts_callback($code, $user_id, $readonly)` - Exchanges code for tokens
- `CONTACTS_SCOPE_READONLY = 'https://www.googleapis.com/auth/contacts.readonly'`
- `CONTACTS_SCOPE_READWRITE = 'https://www.googleapis.com/auth/contacts'`

**`Stadion\Contacts\GoogleContactsConnection`** (`includes/class-google-contacts-connection.php`)
- `META_KEY = '_stadion_google_contacts_connection'`
- `PENDING_IMPORT_KEY = '_stadion_google_contacts_pending_import'`
- `get_connection($user_id)` - Returns connection array
- `is_connected($user_id)` - Returns bool
- `get_decrypted_credentials($user_id)` - Returns decrypted OAuth tokens
- `update_connection($user_id, $updates)` - Partial update
- `has_pending_import($user_id)` - Check pending flag
- `set_pending_import($user_id, $pending)` - Set/clear flag

### Connection Data Structure
```php
[
    'enabled' => true,
    'access_mode' => 'readwrite', // or 'readonly'
    'credentials' => '...encrypted...', // access_token, refresh_token, expires_at
    'email' => 'user@gmail.com',
    'connected_at' => '2026-01-17T12:00:00+00:00',
    'last_sync' => null, // ISO 8601 timestamp
    'last_error' => null,
    'contact_count' => 0,
    'sync_token' => null, // For incremental sync (future phases)
]
```

### REST Endpoints (`includes/class-rest-google-contacts.php`)
- `GET /stadion/v1/google-contacts/status` - Connection status
- `GET /stadion/v1/google-contacts/auth` - Initiate OAuth
- `GET /stadion/v1/google-contacts/callback` - OAuth callback (public)
- `DELETE /stadion/v1/google-contacts` - Disconnect

## WordPress Photo Sideloading

### Established Pattern (from existing imports)
```php
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

private function sideload_image(string $url, int $post_id, string $description): ?int {
    // Download to temp file
    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        return null;
    }

    // Create filename from person name
    $filename = sanitize_title(strtolower($description)) . '.jpg';

    $file_array = [
        'name' => $filename,
        'tmp_name' => $tmp,
    ];

    // Sideload and attach to post
    $attachment_id = media_handle_sideload($file_array, $post_id, $description);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return null;
    }

    return $attachment_id;
}
```

### Google Photos URL Modification
Google photo URLs support `sz={size}` query parameter:
```php
$photo_url = $photo['url'];
// Remove existing size param if present
$photo_url = preg_replace('/[?&]sz=\d+/', '', $photo_url);
// Add desired size
$photo_url .= (strpos($photo_url, '?') !== false ? '&' : '?') . 'sz=400';
```

## Field Mapping Table

| Google Field | Stadion Field | Notes |
|--------------|--------------|-------|
| `names[0].givenName` | `first_name` | Use primary or first |
| `names[0].familyName` | `last_name` | |
| `resourceName` | `_google_contact_id` (meta) | people/c123... |
| `etag` | `_google_etag` (meta) | For change detection |
| `emailAddresses[]` | `contact_info[]` with type=email | All emails |
| `phoneNumbers[]` | `contact_info[]` with type=phone/mobile | Map type |
| `addresses[]` | `addresses[]` | All addresses |
| `teams[]` | `work_history[]` + team lookup/create | All orgs |
| `birthdays[0].date` | `important_date` post | Only first, as birthday |
| `photos[0].url` | Featured image | Only if no existing photo |
| `biographies[]` | **SKIP** | Per CONTEXT.md |
| `urls[]` | `contact_info[]` with detected type | |
| `memberships` | **SKIP** | Per CONTEXT.md (groups/labels) |

### Phone Type Mapping
```php
$phone_type_map = [
    'mobile' => 'mobile',
    'workMobile' => 'mobile',
    'home' => 'phone',
    'work' => 'phone',
    'main' => 'phone',
    'homeFax' => 'phone',
    'workFax' => 'phone',
    'otherFax' => 'phone',
    'pager' => 'phone',
    'workPager' => 'phone',
    'googleVoice' => 'phone',
    'other' => 'phone',
];
```

### URL Type Detection (from existing imports)
```php
private function detect_url_type(string $url): string {
    $url_lower = strtolower($url);
    if (strpos($url_lower, 'linkedin.com') !== false) return 'linkedin';
    if (strpos($url_lower, 'twitter.com') !== false || strpos($url_lower, 'x.com') !== false) return 'twitter';
    if (strpos($url_lower, 'facebook.com') !== false) return 'facebook';
    if (strpos($url_lower, 'instagram.com') !== false) return 'instagram';
    return 'website';
}
```

## Duplicate Detection Strategy

Per CONTEXT.md: **Match by email only**, skip contacts without email.

```php
private function find_existing_person_by_email(string $email, int $user_id): ?int {
    global $wpdb;

    // Get person IDs accessible by this user
    $accessible_ids = $this->get_user_accessible_person_ids($user_id);
    if (empty($accessible_ids)) return null;

    // Search contact_info repeater for matching email
    // ACF stores repeater data as: contact_info_0_contact_type, contact_info_0_contact_value, etc.
    $meta_query = $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id
         INNER JOIN {$wpdb->postmeta} pm_value ON p.ID = pm_value.post_id
         WHERE p.post_type = 'person'
         AND p.post_status = 'publish'
         AND p.ID IN (" . implode(',', array_map('intval', $accessible_ids)) . ")
         AND pm_type.meta_key LIKE 'contact_info_%_contact_type'
         AND pm_type.meta_value = 'email'
         AND pm_value.meta_key = REPLACE(pm_type.meta_key, '_contact_type', '_contact_value')
         AND LOWER(pm_value.meta_value) = LOWER(%s)
         LIMIT 1",
        $email
    );

    return $wpdb->get_var($meta_query);
}
```

## Batch Processing Pattern

### From Existing Imports
```php
// Increase limits
@set_time_limit(600);
wp_raise_memory_limit('admin');

// Track statistics
private array $stats = [
    'contacts_imported' => 0,
    'contacts_updated' => 0,
    'contacts_skipped' => 0,
    'teams_created' => 0,
    'dates_created' => 0,
    'photos_imported' => 0,
    'errors' => [],
];

// Process in batches
$pageToken = null;
do {
    $response = $service->people_connections->listPeopleConnections(
        'people/me',
        ['personFields' => $personFields, 'pageSize' => 100, 'pageToken' => $pageToken]
    );

    foreach ($response->getConnections() as $person) {
        $this->import_single_contact($person);
    }

    $pageToken = $response->getNextPageToken();
} while ($pageToken);
```

## Implementation Architecture

### Recommended Class Structure
```php
namespace Stadion\Import;

class GoogleContactsAPI {
    private array $stats = [...];
    private array $team_map = []; // name => ID cache

    // Entry points
    public function import_all(int $user_id): array;
    public function import_single(int $user_id, string $resource_name): array;

    // Core processing
    private function fetch_contacts(int $user_id, ?string $page_token = null): object;
    private function process_contact(object $person, int $user_id): void;

    // Duplicate handling
    private function find_by_email(string $email, int $user_id): ?int;
    private function should_skip_contact(object $person): bool;

    // Field imports (fill gaps only)
    private function import_names(int $post_id, object $person): void;
    private function import_contact_info(int $post_id, object $person): void;
    private function import_addresses(int $post_id, object $person): void;
    private function import_work_history(int $post_id, object $person): void;
    private function import_birthday(int $post_id, object $person, string $full_name): void;
    private function import_photo(int $post_id, object $person, string $full_name): void;

    // Helpers
    private function get_or_create_team(string $name, int $user_id): int;
    private function store_google_ids(int $post_id, string $resource_name, string $etag): void;
}
```

### REST Endpoint Extension
Add to `class-rest-google-contacts.php`:
```php
// POST /stadion/v1/google-contacts/import - Trigger import
register_rest_route('stadion/v1', '/google-contacts/import', [
    'methods' => 'POST',
    'callback' => [$this, 'trigger_import'],
    'permission_callback' => [$this, 'check_user_approved'],
]);
```

## Key Implementation Notes

### 1. Email-Only Matching (CONTEXT.md Decision)
- Skip any contact without at least one emailAddress
- Match existing Stadion contacts by email only
- When match found: link to existing, fill empty fields only
- Never overwrite existing data

### 2. Fill Gaps Strategy
```php
// Example for first_name
$existing_first = get_field('first_name', $post_id);
if (empty($existing_first) && !empty($google_given_name)) {
    update_field('first_name', $google_given_name, $post_id);
}
```

### 3. Google ID Storage
Store as post meta (not ACF) for simpler querying:
```php
update_post_meta($post_id, '_google_contact_id', $resource_name);
update_post_meta($post_id, '_google_etag', $etag);
update_post_meta($post_id, '_google_last_import', current_time('c'));
```

### 4. Photo Sideloading Rules
- Only import if person has no existing featured image
- On failure: skip silently, log error, continue
- Use sz=400 for reasonable quality

### 5. Token Refresh Handling
The existing GoogleOAuth class handles token refresh via `get_access_token()`. For API imports:
```php
$credentials = GoogleContactsConnection::get_decrypted_credentials($user_id);
$client = GoogleOAuth::get_contacts_client(false, false);
$client->setAccessToken($credentials);

// Check/refresh token
if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        // Save updated tokens
        GoogleContactsConnection::update_credentials($user_id, $client->getAccessToken());
    } else {
        throw new Exception('Token expired and no refresh token available');
    }
}

$service = new \Google\Service\PeopleService($client);
```

### 6. Google API PHP Library
The project already uses `google/apiclient` (used by Google Calendar). Use:
```php
use Google\Service\PeopleService;
use Google\Service\PeopleService\Person;
use Google\Service\PeopleService\ListConnectionsResponse;
```

## Don't Hand-Roll

| Problem | Use Instead | Why |
|---------|-------------|-----|
| Image sideloading | `media_handle_sideload()` | Handles MIME detection, attachment metadata, all edge cases |
| Token refresh | GoogleOAuth pattern | Already handles refresh, error states |
| Team matching | `get_page_by_title()` | Established pattern in all imports |
| Email search in repeater | Direct meta query | ACF stores repeater with numbered keys |

## Common Pitfalls

### 1. ACF Repeater Meta Structure
**Problem:** ACF repeaters store data as `field_0_subfield`, not as serialized array.
**Solution:** Use direct meta queries or `get_field()` to iterate.

### 2. Access Control on Import
**Problem:** Import must respect user boundaries - users can only see their own contacts.
**Solution:** Always pass `$user_id`, use `get_user_accessible_person_ids()` pattern from CSV import.

### 3. Google Photo URL Expiration
**Problem:** Google photo URLs may expire or require authentication.
**Solution:** Sideload immediately during import, not lazily.

### 4. Rate Limiting
**Problem:** Google People API has quotas.
**Solution:** Use standard page size (100), don't parallelize requests.

### 5. Partial Data in Google Contacts
**Problem:** Contact may have name but no email, or only team.
**Solution:** Per CONTEXT.md, skip contacts without email entirely.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/acf-json/group_person_fields.json` - ACF field structure
- `/Users/joostdevalk/Code/stadion/includes/class-google-oauth.php` - OAuth handling
- `/Users/joostdevalk/Code/stadion/includes/class-google-contacts-connection.php` - Connection storage
- `/Users/joostdevalk/Code/stadion/includes/class-google-contacts-import.php` - CSV import patterns
- [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)
- [Google People API - Person Resource](https://developers.google.com/people/api/rest/v1/people#Person)

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-monica-import.php` - Batch import patterns
- `/Users/joostdevalk/Code/stadion/includes/class-vcard-import.php` - Photo sideloading patterns

## Metadata

**Confidence breakdown:**
- Google API structure: HIGH - Official documentation verified
- Stadion data model: HIGH - Direct codebase inspection
- Import patterns: HIGH - Multiple existing implementations
- Photo sideloading: HIGH - Established WordPress patterns

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (API structures stable)
