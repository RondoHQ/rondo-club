# Architecture Research: Google Contacts Sync

**Project:** Stadion v5.0 - Google Contacts Two-Way Sync
**Researched:** 2026-01-17
**Confidence:** HIGH (based on existing Stadion patterns + authoritative Google API documentation)

## Executive Summary

This document defines the architecture for bidirectional Google Contacts synchronization. The design follows established Stadion patterns (from Calendar sync) while adding the complexity of true bidirectional sync with conflict resolution. Key architectural decisions:

1. **Follow existing patterns** - Reuse `Stadion\Calendar` namespace structure, OAuth infrastructure, and WP-Cron scheduling
2. **Sync token for delta sync** - Use Google People API's `syncToken` mechanism (7-day expiry, 410 error triggers full resync)
3. **ETag for optimistic locking** - Prevent concurrent modification conflicts using Google's etag field
4. **Field-level conflict resolution** - Merge non-conflicting fields, apply strategy only to conflicting fields
5. **Stadion as source of truth** - When in doubt, Stadion data wins (configurable)

## Component Design

### Namespace Structure

Following PSR-4 autoloading established in Stadion, new classes go under `Stadion\Contacts`:

```
src/
  Contacts/
    GoogleOAuth.php          # Extends calendar OAuth with contacts scopes
    GoogleContactsProvider.php  # People API client (CRUD operations)
    GoogleContactsSync.php      # Sync orchestration
    GoogleContactsMapper.php    # Field mapping bidirectional
    GoogleContactsConflict.php  # Conflict detection and resolution

  REST/
    GoogleContacts.php       # REST API endpoints for UI
```

### Component Responsibilities

#### 1. GoogleContactsProvider (API Client)

**Purpose:** Low-level Google People API operations with proper error handling.

**Responsibilities:**
- Authenticate using existing OAuth tokens (reuse `Stadion\Calendar\GoogleOAuth`)
- CRUD operations: `get()`, `create()`, `update()`, `delete()`
- List contacts with pagination and syncToken support
- Handle rate limiting with exponential backoff
- Validate etag before mutations to prevent lost updates

**Key Methods:**
```php
class GoogleContactsProvider {
    // List all contacts (initial sync)
    public function listContacts(int $pageSize = 100, ?string $pageToken = null): array;

    // Delta sync using syncToken
    public function syncContacts(string $syncToken): array;

    // CRUD with etag validation
    public function getContact(string $resourceName): ?array;
    public function createContact(array $contactData): array;
    public function updateContact(string $resourceName, array $contactData, string $etag): array;
    public function deleteContact(string $resourceName, string $etag): bool;
}
```

**Error Handling:**
- `410 Gone` - syncToken expired, trigger full resync
- `409 Conflict` - etag mismatch, fetch fresh data and retry
- `429 Too Many Requests` - exponential backoff (1s, 2s, 4s, 8s, max 60s)
- `401 Unauthorized` - token refresh failed, mark connection as needing reauth

#### 2. GoogleContactsSync (Orchestrator)

**Purpose:** Coordinate sync operations, manage sync state, handle scheduling.

**Responsibilities:**
- Determine sync direction (full vs delta)
- Track sync state per contact (`_google_sync_status` post meta)
- Manage sync locks (prevent concurrent syncs)
- Schedule background sync via WP-Cron
- Log sync operations for debugging

**Sync Loop:**
```php
class GoogleContactsSync {
    public function sync(int $userId): SyncResult {
        // 1. Acquire lock
        if (!$this->acquireLock($userId)) {
            return SyncResult::skipped('Sync already in progress');
        }

        try {
            // 2. Determine sync type
            $syncToken = $this->getSyncToken($userId);

            if ($syncToken) {
                return $this->deltaSync($userId, $syncToken);
            } else {
                return $this->fullSync($userId);
            }
        } finally {
            $this->releaseLock($userId);
        }
    }
}
```

**State Transitions (per contact):**
```
UNLINKED  --> SYNCED      (after first export to Google)
SYNCED    --> PENDING_PUSH (Stadion edit detected)
SYNCED    --> PENDING_PULL (Google edit detected via delta sync)
PENDING_* --> CONFLICT     (both sides edited since last sync)
CONFLICT  --> SYNCED       (after resolution)
SYNCED    --> UNLINKED     (user unlinks or deletes)
```

#### 3. GoogleContactsMapper (Field Mapping)

**Purpose:** Bidirectional field mapping between Google People API format and Stadion ACF fields.

**Design Principle:** Single source of truth for field mappings - both directions derive from same config.

**Mapping Structure:**
```php
class GoogleContactsMapper {
    private const FIELD_MAP = [
        'names' => [
            'google_path' => 'names[0]',
            'stadion_fields' => [
                'givenName'  => 'first_name',
                'familyName' => 'last_name',
            ],
        ],
        'nicknames' => [
            'google_path' => 'nicknames[0].value',
            'stadion_fields' => ['value' => 'nickname'],
        ],
        'emailAddresses' => [
            'google_path' => 'emailAddresses',
            'stadion_field' => 'contact_info',
            'filter' => ['contact_type' => 'email'],
            'repeater' => true,
        ],
        // ... etc
    ];

    public function toGoogle(int $personId): array;
    public function toStadion(array $googleContact): array;
    public function getChangedFields(array $before, array $after): array;
}
```

**Complex Mappings:**
| Google Field | Stadion Field | Notes |
|--------------|--------------|-------|
| `names[0].givenName` | `first_name` | ACF text field |
| `names[0].familyName` | `last_name` | ACF text field |
| `photos[0].url` | `_thumbnail_id` | Sideload as attachment |
| `emailAddresses[]` | `contact_info[]` (type: email) | ACF repeater |
| `phoneNumbers[]` | `contact_info[]` (type: phone/mobile) | ACF repeater |
| `addresses[]` | `addresses[]` | ACF repeater |
| `birthdays[0]` | `important_date` CPT | Link via `related_people` |
| `teams[0]` | `work_history[]` | Create team if needed |
| `biographies[0].value` | `story` | ACF WYSIWYG |

#### 4. GoogleContactsConflict (Conflict Resolution)

**Purpose:** Detect and resolve conflicts when both systems modify the same contact.

**Conflict Detection:**
```php
class GoogleContactsConflict {
    public function detectConflict(int $personId, array $googleData): ?Conflict {
        $lastSynced = get_post_meta($personId, '_google_last_synced', true);
        $stadionModified = get_post_modified_time('U', false, $personId);
        $googleModified = strtotime($googleData['metadata']['sources'][0]['updateTime']);

        // Both modified since last sync = conflict
        if ($stadionModified > $lastSynced && $googleModified > $lastSynced) {
            return new Conflict($personId, $googleData, $this->getChangedFields(...));
        }

        return null;
    }
}
```

**Resolution Strategies:**
1. **newest_wins** (default) - Compare `post_modified` vs Google `updateTime`
2. **stadion_wins** - Always prefer Stadion data
3. **google_wins** - Always prefer Google data
4. **field_level_merge** - Non-conflicting fields merged, conflicting fields use newest
5. **manual** - Queue for user review

**Field-Level Merge Algorithm:**
```php
public function mergeFields(array $stadion, array $google, array $conflicts): array {
    $merged = [];

    foreach (self::FIELD_MAP as $field => $config) {
        if (in_array($field, $conflicts)) {
            // Conflicting field - apply strategy
            $merged[$field] = $this->resolveFieldConflict($field, $stadion, $google);
        } else {
            // Non-conflicting - use whichever has data (prefer newest if both)
            $merged[$field] = $stadion[$field] ?? $google[$field] ?? null;
        }
    }

    return $merged;
}
```

## Data Flow

### Initial Full Sync Flow

```
User clicks "Connect Google Contacts"
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ OAuth Flow (reuse existing GoogleOAuth)                 │
│ - Add contacts scope to existing calendar scopes        │
│ - Store tokens encrypted in user meta                   │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Full Sync (no syncToken)                                │
│ 1. Call people.connections.list with requestSyncToken  │
│ 2. Paginate through all contacts (1000 per page max)   │
│ 3. For each Google contact:                            │
│    - Check for existing Stadion match (by email/name)   │
│    - If match: link and merge data                     │
│    - If new: create person or queue for review         │
│ 4. Store nextSyncToken in user meta                    │
│ 5. For unlinked Stadion contacts: export to Google      │
└─────────────────────────────────────────────────────────┘
         │
         ▼
    Store syncToken
```

### Delta Sync Flow (Background)

```
WP-Cron triggers sync
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ 1. Pull Changes from Google                             │
│    - Call connections.list with stored syncToken        │
│    - If 410 error: clear token, trigger full sync       │
│    - Process changed/deleted contacts                   │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Detect Local Changes                                 │
│    - Query persons where post_modified > last_synced    │
│    - Query persons with _google_sync_status = pending   │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Conflict Detection                                   │
│    - For each changed contact (both sides)              │
│    - Compare modification timestamps                    │
│    - Identify conflicting fields                        │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Apply Changes                                        │
│    - Non-conflicting: apply directly                    │
│    - Conflicting: apply resolution strategy             │
│    - Manual conflicts: queue for review                 │
└─────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Push Changes to Google                               │
│    - For each PENDING_PUSH contact                      │
│    - Include etag in update request                     │
│    - Handle 409 (etag mismatch) with fetch-merge-retry  │
└─────────────────────────────────────────────────────────┘
         │
         ▼
    Store new syncToken
    Update last_synced timestamps
```

### Conflict Resolution Flow

```
Conflict Detected
         │
         ▼
┌─────────────────────────────────────────────────────────┐
│ Check User's Configured Strategy                        │
│ (user meta: stadion_google_contacts_conflict_mode)       │
└─────────────────────────────────────────────────────────┘
         │
    ┌────┴────┬─────────┬─────────┐
    ▼         ▼         ▼         ▼
 newest    stadion    google    manual
   wins      wins      wins    review
    │         │         │         │
    ▼         ▼         ▼         ▼
Compare    Use       Use      Queue in
 times    Stadion    Google   _google_sync_status
    │         │         │      = 'conflict'
    └────┬────┴─────────┘         │
         ▼                        ▼
    Apply winner              Show in UI
    Update both sides         Wait for user
    Log resolution            decision
```

## Sync State Machine

### Per-Contact States

```
                    ┌──────────────┐
                    │   UNLINKED   │◄──────────────────────────────┐
                    │ (Stadion only)│                               │
                    └──────┬───────┘                               │
                           │                                       │
                    Export │ to Google                      Unlink │
                           │                                       │
                           ▼                                       │
                    ┌──────────────┐                               │
           ┌───────►│    SYNCED    │◄───────┐                     │
           │        │  (in sync)   │        │                     │
           │        └──────┬───────┘        │                     │
           │               │                │                     │
           │     ┌─────────┼─────────┐      │                     │
           │     │         │         │      │                     │
           │  Stadion     Both     Google    │                     │
           │  edited    edited    edited    │                     │
           │     │         │         │      │                     │
           │     ▼         ▼         ▼      │                     │
           │ ┌────────┐ ┌────────┐ ┌────────┐                     │
           │ │PENDING │ │CONFLICT│ │PENDING │                     │
           │ │ _PUSH  │ │        │ │ _PULL  │                     │
           │ └────┬───┘ └────┬───┘ └────┬───┘                     │
           │      │          │          │                         │
           │   Push to    Resolve    Pull from                    │
           │   Google    conflict     Google                      │
           │      │          │          │                         │
           └──────┴──────────┴──────────┘                         │
                                                                  │
                                    Delete on either side ────────┘
```

### Per-User States

```
┌─────────────────┐
│  DISCONNECTED   │
│ (no OAuth token)│
└────────┬────────┘
         │ Connect
         ▼
┌─────────────────┐
│   CONNECTING    │
│ (OAuth in flow) │
└────────┬────────┘
         │ Token received
         ▼
┌─────────────────┐
│  INITIAL_SYNC   │
│(full sync running)│
└────────┬────────┘
         │ Complete
         ▼
┌─────────────────┐
│    CONNECTED    │◄──────┐
│ (periodic sync) │       │
└────────┬────────┘       │
         │                │
    ┌────┴────┐           │
    │         │           │
  Error    Sync OK        │
    │         │           │
    ▼         └───────────┘
┌─────────────────┐
│     ERROR       │
│(needs attention)│
└─────────────────┘
```

## Integration with Existing Architecture

### Reusing Calendar Infrastructure

The Google Contacts sync should leverage existing patterns from Calendar sync:

| Existing Component | How Contacts Sync Uses It |
|--------------------|---------------------------|
| `Stadion\Calendar\GoogleOAuth` | Extend with contacts scopes (additive) |
| `Stadion\Calendar\Sync` (cron scheduling) | Same pattern, different hook |
| `Stadion\Data\CredentialEncryption` | Same encryption for tokens |
| Transient-based sync locks | Same pattern for preventing concurrent syncs |
| User meta for connection state | Same storage pattern |

### OAuth Scope Extension

Current calendar-only scopes:
```php
private const SCOPES = ['https://www.googleapis.com/auth/calendar.readonly'];
```

Extended for contacts:
```php
private const CALENDAR_SCOPES = ['https://www.googleapis.com/auth/calendar.readonly'];
private const CONTACTS_SCOPES = [
    'https://www.googleapis.com/auth/contacts',        // Full access
    // OR 'https://www.googleapis.com/auth/contacts.readonly'  // Import-only
];

public static function getScopes(array $features = ['calendar', 'contacts']): array {
    $scopes = [];
    if (in_array('calendar', $features)) {
        $scopes = array_merge($scopes, self::CALENDAR_SCOPES);
    }
    if (in_array('contacts', $features)) {
        $scopes = array_merge($scopes, self::CONTACTS_SCOPES);
    }
    return $scopes;
}
```

### Post Meta Schema

New meta fields on `person` post type:
```php
// Sync identification
'_google_contact_id'     // string: resourceName (e.g., "people/c12345")
'_google_etag'           // string: for optimistic locking
'_google_sync_enabled'   // bool: whether this contact syncs

// Sync state
'_google_sync_status'    // enum: synced|pending_push|pending_pull|conflict|unlinked
'_google_last_synced'    // datetime: last successful sync
'_google_raw_data'       // json: cached Google data for conflict resolution

// Conflict tracking
'_google_conflict_data'  // json: {stadion: {...}, google: {...}, fields: [...]}
```

### User Meta Schema

```php
// Connection state
'stadion_google_contacts_enabled'     // bool: master switch
'stadion_google_contacts_sync_token'  // string: for delta sync

// Preferences
'stadion_google_contacts_sync_frequency'  // string: 15min|1hour|6hours|daily
'stadion_google_contacts_sync_direction'  // string: bidirectional|import|export
'stadion_google_contacts_conflict_mode'   // string: newest|stadion|google|manual
'stadion_google_contacts_delete_mode'     // string: unlink|propagate

// Status tracking
'stadion_google_contacts_last_sync'       // datetime
'stadion_google_contacts_last_error'      // string
'stadion_google_contacts_stats'           // json: {synced: N, pending: M, conflicts: K}
```

### REST API Endpoints

Following existing `Stadion\REST` patterns:

```php
namespace Stadion\REST;

class GoogleContacts extends Base {
    public function register_routes() {
        // Connection management
        register_rest_route('stadion/v1', '/google-contacts/status', [...]);
        register_rest_route('stadion/v1', '/google-contacts/connect', [...]);
        register_rest_route('stadion/v1', '/google-contacts/disconnect', [...]);

        // Sync operations
        register_rest_route('stadion/v1', '/google-contacts/sync', [...]);
        register_rest_route('stadion/v1', '/google-contacts/preferences', [...]);

        // Conflict resolution
        register_rest_route('stadion/v1', '/google-contacts/conflicts', [...]);
        register_rest_route('stadion/v1', '/google-contacts/conflicts/(?P<id>\d+)', [...]);

        // Per-contact operations
        register_rest_route('stadion/v1', '/people/(?P<id>\d+)/google-sync', [...]);
        register_rest_route('stadion/v1', '/people/(?P<id>\d+)/google-link', [...]);
    }
}
```

## Build Order (Recommended Implementation Sequence)

Based on dependencies between components:

### Phase 1: Foundation (No UI needed)
**Dependencies:** None
**Deliverables:**
1. Extend `GoogleOAuth` for contacts scopes
2. Create `GoogleContactsProvider` with basic CRUD
3. Add post meta fields to person CPT
4. Basic REST endpoints for status/connect

### Phase 2: One-Way Import
**Dependencies:** Phase 1
**Deliverables:**
1. Create `GoogleContactsMapper` (Google -> Stadion direction)
2. Implement full sync (import all contacts)
3. Duplicate detection logic
4. Photo sideloading
5. Birthday -> important_date creation

### Phase 3: One-Way Export
**Dependencies:** Phase 2
**Deliverables:**
1. Extend `GoogleContactsMapper` (Stadion -> Google direction)
2. Implement contact creation in Google
3. Link existing Stadion contacts to Google
4. Bulk export functionality

### Phase 4: Delta Sync
**Dependencies:** Phase 3
**Deliverables:**
1. Create `GoogleContactsSync` orchestrator
2. Implement syncToken-based delta sync
3. Change detection for local edits
4. Background sync via WP-Cron

### Phase 5: Conflict Resolution
**Dependencies:** Phase 4
**Deliverables:**
1. Create `GoogleContactsConflict` class
2. Implement resolution strategies
3. Conflict queuing for manual review
4. Audit logging

### Phase 6: Settings UI
**Dependencies:** Phases 1-5 (backend must be complete)
**Deliverables:**
1. Google Contacts card in Settings
2. Preferences panel
3. Sync status display
4. Manual sync button

### Phase 7: Person Detail UI
**Dependencies:** Phase 6
**Deliverables:**
1. Sync status indicator
2. Per-contact sync toggle
3. Conflict resolution modal
4. "View in Google" link

### Phase 8: Polish & Edge Cases
**Dependencies:** All previous
**Deliverables:**
1. Deletion handling
2. Error recovery
3. WP-CLI commands
4. Performance optimization
5. Documentation

## Performance Considerations

### Batch Processing

For initial sync with many contacts:
```php
// Process in batches of 50 to avoid memory issues
foreach (array_chunk($googleContacts, 50) as $batch) {
    foreach ($batch as $contact) {
        $this->processContact($contact);
    }
    // Allow garbage collection between batches
    gc_collect_cycles();
}
```

### Rate Limiting

Google People API limits:
- 90 requests per minute per user
- 10 requests per second per user

Implementation:
```php
class RateLimiter {
    private const MAX_PER_MINUTE = 90;
    private const MAX_PER_SECOND = 10;

    public function throttle(): void {
        $key = 'google_contacts_rate_' . get_current_user_id();
        $count = (int) get_transient($key);

        if ($count >= self::MAX_PER_MINUTE) {
            sleep(60); // Wait for rate limit window to reset
        }

        set_transient($key, $count + 1, 60);
    }
}
```

### Caching

Cache Google contact data during sync to avoid re-fetching:
```php
// Cache raw Google data for conflict resolution
update_post_meta($personId, '_google_raw_data', wp_json_encode($googleContact));

// Use etag for conditional requests (304 Not Modified)
$headers = ['If-None-Match' => $storedEtag];
```

## Security Considerations

1. **Token Storage:** Use existing `CredentialEncryption` class (Sodium)
2. **Minimal Scopes:** Request only contacts scope, not full Google account
3. **User Isolation:** Each user has their own sync state, no cross-user data access
4. **Audit Trail:** Log all sync operations with timestamps

## Sources

- [Google People API - Read and Manage Contacts](https://developers.google.com/people/v1/contacts)
- [Google Calendar API - Synchronize Resources Efficiently](https://developers.google.com/workspace/calendar/api/guides/sync)
- [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)
- [Two-Way Sync Architecture - StackSync](https://www.stacksync.com/blog/two-way-sync-architecture-essential-knowledge-for-data-professionals)
- [ETags and Optimistic Concurrency Control](https://fideloper.com/etags-and-optimistic-concurrency-control)
- [Martin Fowler - Optimistic Offline Lock](https://martinfowler.com/eaaCatalog/optimisticOfflineLock.html)
- [Data Synchronization Patterns (McCormick & Schmidt)](https://www.dre.vanderbilt.edu/~schmidt/PDF/PatternPaperv11.pdf)
- [WP Background Processing Library](https://github.com/deliciousbrains/wp-background-processing)
- [Action Scheduler for WordPress](https://actionscheduler.org/)
