# Phase 82: Delta Sync - Research

**Researched:** 2026-01-17
**Domain:** Google People API sync, WP-Cron scheduling, bidirectional change detection
**Confidence:** HIGH

## Summary

Delta sync enables automatic bidirectional synchronization between Stadion and Google Contacts. The Google People API provides a `syncToken` mechanism for efficient change detection on the Google side, returning only modified/deleted contacts since the last sync. For Stadion changes, we compare `post_modified` timestamps against the stored `_google_last_export` meta to detect which contacts need pushing to Google.

The existing codebase already has:
1. A working cron pattern in `class-calendar-sync.php` with user-configurable frequencies
2. Export functionality in `class-google-contacts-export.php` that handles single contact export
3. Import functionality in `class-google-contacts-api-import.php` for full imports
4. Connection storage in `class-google-contacts-connection.php` with a `sync_token` field already defined

**Primary recommendation:** Build a new `GoogleContactsSync` class following the `Stadion\Calendar\Sync` pattern, leveraging Google's syncToken for pull operations and post_modified comparison for push operations.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Google People API | v1 | Contact sync via syncToken | Official Google API with delta sync support |
| WP-Cron | Built-in | Background scheduling | WordPress native, no external dependencies |
| google/apiclient | Already installed | PHP client for Google APIs | Already used by import/export classes |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PeopleService | via google/apiclient | API wrapper | All Google API calls |
| Transients API | Built-in | Rate limiting, cycle tracking | Round-robin user processing |
| User Meta | Built-in | Frequency settings | User preference storage |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP-Cron | Action Scheduler | More powerful but adds dependency |
| post_modified | Custom hook on save | post_modified is automatic and reliable |
| User-level frequency | Global frequency | User-level matches existing calendar pattern |

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-google-contacts-sync.php       # New: Main sync orchestrator
├── class-google-contacts-export.php     # Existing: Export to Google
├── class-google-contacts-api-import.php # Existing: Import from Google
└── class-google-contacts-connection.php # Existing: Connection storage
```

### Pattern 1: Sync Token Workflow
**What:** Use Google's syncToken for efficient delta detection from Google
**When to use:** Every scheduled sync (hourly by default)
**Example:**
```php
// Source: https://developers.google.com/people/api/rest/v1/people.connections/list

// Initial full sync (no syncToken)
$params = [
    'personFields'     => 'names,emailAddresses,phoneNumbers,...',
    'pageSize'         => 100,
    'requestSyncToken' => true,  // Request a token for next time
];
$response = $service->people_connections->listPeopleConnections('people/me', $params);
$sync_token = $response->getNextSyncToken();  // Store this

// Subsequent delta sync
$params = [
    'personFields' => 'names,emailAddresses,phoneNumbers,...',
    'syncToken'    => $stored_sync_token,
];
$response = $service->people_connections->listPeopleConnections('people/me', $params);

// Handle deleted contacts
foreach ($response->getConnections() as $person) {
    $metadata = $person->getMetadata();
    if ($metadata && $metadata->getDeleted()) {
        // Contact was deleted in Google - unlink in Stadion
        $this->handle_google_deletion($person->getResourceName());
    }
}
```

### Pattern 2: Stadion Change Detection
**What:** Compare post_modified with _google_last_export to find local changes
**When to use:** Every sync cycle to push changes to Google
**Example:**
```php
// Find contacts modified since last export
$args = [
    'post_type'      => 'person',
    'post_status'    => 'publish',
    'author'         => $user_id,
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => '_google_contact_id',
            'compare' => 'EXISTS',  // Only synced contacts
        ],
    ],
];

$query = new WP_Query($args);
foreach ($query->posts as $post) {
    $last_export = get_post_meta($post->ID, '_google_last_export', true);
    $post_modified = $post->post_modified;

    // Compare timestamps - export if modified after last export
    if (empty($last_export) || strtotime($post_modified) > strtotime($last_export)) {
        $this->export_contact($post->ID);
    }
}
```

### Pattern 3: Round-Robin User Processing (Existing Pattern)
**What:** Process one user per cron run to spread API load
**When to use:** Background sync with multiple users
**Example:**
```php
// Source: class-calendar-sync.php lines 88-114
const USER_INDEX_TRANSIENT = 'stadion_contacts_sync_last_user_index';

public function run_background_sync() {
    $users = $this->get_users_with_connections();
    if (empty($users)) return;

    $last_index = (int) get_transient(self::USER_INDEX_TRANSIENT);
    $next_index = ($last_index + 1) % count($users);
    $user_id = $users[$next_index];

    set_transient(self::USER_INDEX_TRANSIENT, $next_index, HOUR_IN_SECONDS);

    $this->sync_user($user_id);
}
```

### Pattern 4: Configurable Frequency (Existing Pattern)
**What:** Check if sync is due based on user's frequency setting
**When to use:** Before processing each user
**Example:**
```php
// Source: class-calendar-sync.php lines 237-259
private function is_sync_due(array $connection): bool {
    $last_sync = $connection['last_sync'] ?? null;
    if (empty($last_sync)) return true;  // No last sync - always due

    $frequency_minutes = isset($connection['sync_frequency'])
        ? absint($connection['sync_frequency'])
        : 60;  // Default 60 for contacts

    $last_sync_time = strtotime($last_sync);
    if ($last_sync_time === false) return true;

    $seconds_since_sync = time() - $last_sync_time;
    $required_seconds = $frequency_minutes * 60;

    return $seconds_since_sync >= $required_seconds;
}
```

### Anti-Patterns to Avoid
- **Full sync every time:** Always use syncToken when available - full syncs are expensive
- **Immediate read-after-write:** Google has propagation delay, don't verify exports via sync
- **Parallel user requests:** Process users sequentially, Google API is per-user rate limited
- **Ignoring expired tokens:** Always handle EXPIRED_SYNC_TOKEN error with graceful fallback

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Change detection (Google) | Field-by-field comparison | Google syncToken | API provides deleted flag, etag, efficient delta |
| Change detection (Stadion) | Custom modified tracking | post_modified vs _google_last_export | WordPress already tracks post_modified |
| Recurring schedule | Manual timestamp tracking | WP-Cron with custom schedule | Reliable, survives restarts |
| User round-robin | Custom queue | Transient with modulo index | Proven pattern in class-calendar-sync.php |
| Token refresh | Manual refresh logic | Google\Client::isAccessTokenExpired() | Already implemented in export/import classes |

**Key insight:** The existing calendar sync implementation has solved most of the scheduling and rate-limiting problems. Follow that pattern rather than inventing new approaches.

## Common Pitfalls

### Pitfall 1: Sync Token Expiration
**What goes wrong:** syncToken expires after 7 days, causing 410 Gone errors
**Why it happens:** User doesn't visit site for a week, sync doesn't run
**How to avoid:** Catch HTTP 410 errors, fall back to full sync, store new token
**Warning signs:** Error logs showing "EXPIRED_SYNC_TOKEN" reason

### Pitfall 2: Propagation Delay on Read-After-Write
**What goes wrong:** Export contact, immediately sync, contact appears unchanged
**Why it happens:** Google API has "several minutes" propagation delay for writes
**How to avoid:** Don't verify exports via delta sync; trust export success
**Warning signs:** Contacts keep re-exporting every sync cycle

### Pitfall 3: Event-Triggered Export Conflicts with Background Sync
**What goes wrong:** Contact saved in Stadion triggers immediate export AND background sync exports it
**Why it happens:** Race condition between save_post hook and cron
**How to avoid:** Check _google_last_export timestamp before background push
**Warning signs:** Duplicate exports, etag conflicts

### Pitfall 4: Round-Robin Starvation
**What goes wrong:** User with many contacts monopolizes sync, other users never sync
**Why it happens:** No limit on contacts processed per user per cycle
**How to avoid:** Limit contacts per sync cycle (e.g., 50), continue next cycle
**Warning signs:** Some users never see updates, last_sync timestamps stale

### Pitfall 5: Missing Frequency Options in Cron
**What goes wrong:** User selects frequency that doesn't exist in cron_schedules
**Why it happens:** Custom schedules not registered before wp_schedule_event
**How to avoid:** Register all frequency options in cron_schedules filter early
**Warning signs:** wp_schedule_event returns false silently

## Code Examples

Verified patterns from official sources and existing codebase:

### Sync Token Request Parameters
```php
// Source: https://developers.google.com/people/api/rest/v1/people.connections/list
$params = [
    'personFields'     => 'names,emailAddresses,phoneNumbers,addresses,teams,photos,metadata',
    'pageSize'         => 100,
    'requestSyncToken' => true,
];

// For delta sync, add stored token
if ($sync_token) {
    $params['syncToken'] = $sync_token;
    unset($params['requestSyncToken']); // Not needed when using syncToken
}
```

### Handling Deleted Contacts from Google
```php
// Source: https://developers.google.com/people/api/rest/v1/people.connections/list
// "resources deleted since the last sync will be returned as a person with PersonMetadata.deleted set to true"

foreach ($response->getConnections() as $person) {
    $metadata = $person->getMetadata();
    if ($metadata && $metadata->getDeleted()) {
        $resource_name = $person->getResourceName();
        // Unlink in Stadion - find post by _google_contact_id
        $posts = get_posts([
            'post_type'   => 'person',
            'meta_key'    => '_google_contact_id',
            'meta_value'  => $resource_name,
            'numberposts' => 1,
        ]);
        if (!empty($posts)) {
            // Delete meta to unlink, preserve Stadion data
            delete_post_meta($posts[0]->ID, '_google_contact_id');
            delete_post_meta($posts[0]->ID, '_google_etag');
        }
    }
}
```

### Expired Token Handling
```php
// Source: https://developers.google.com/people/api/rest/v1/people.connections/list
// "A request with an expired sync token will get an error with google.rpc.ErrorInfo with reason EXPIRED_SYNC_TOKEN"

try {
    $response = $service->people_connections->listPeopleConnections('people/me', $params);
} catch (\Google\Service\Exception $e) {
    if ($e->getCode() === 410) {
        // Sync token expired - clear token and do full sync
        GoogleContactsConnection::update_connection($user_id, ['sync_token' => null]);
        return $this->full_sync($user_id);
    }
    throw $e;
}
```

### Custom Cron Schedule Registration
```php
// Source: class-calendar-sync.php lines 55-61
add_filter('cron_schedules', function($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display'  => __('Every 15 Minutes', 'stadion'),
    ];
    $schedules['hourly'] = [
        'interval' => 3600,
        'display'  => __('Every Hour', 'stadion'),
    ];
    // Note: 'daily' is built-in
    return $schedules;
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Full re-import every sync | syncToken delta sync | People API v1 | 90%+ reduction in API calls |
| Manual field comparison | etag-based change detection | Always available | Simpler, more reliable |
| Webhook-based real-time | Polling with syncToken | Google Contacts has no webhooks | Must poll, design for delay |

**Deprecated/outdated:**
- Google Contacts API v2/v3: Replaced by People API v1
- gdata library: Use google/apiclient instead

## Open Questions

Things that couldn't be fully resolved:

1. **Optimal polling frequency balance**
   - What we know: 15min to daily are reasonable options
   - What's unclear: What's the sweet spot for typical usage patterns
   - Recommendation: Default to hourly, offer 15min/hourly/6hr/daily as options (matching CONTEXT.md decision)

2. **Sync direction priority when both sides changed**
   - What we know: This is a conflict scenario for Phase 83
   - What's unclear: Which to process first - pull or push
   - Recommendation: Pull first (get Google state), then push changes, mark conflicts for Phase 83

3. **Rate limit buffer for bulk operations**
   - What we know: 100ms delay works in bulk export (10 req/sec)
   - What's unclear: Optimal delay for mixed read/write operations
   - Recommendation: Keep 100ms delay, monitor for 429 errors

## Sources

### Primary (HIGH confidence)
- [Google People API people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list) - syncToken parameters, deleted contacts handling
- [Google People API Contacts Guide](https://developers.google.com/people/v1/contacts) - Best practices for read/write operations
- Stadion codebase: `class-calendar-sync.php` - Proven cron and round-robin patterns
- Stadion codebase: `class-google-contacts-export.php` - Export implementation
- Stadion codebase: `class-google-contacts-api-import.php` - Import implementation
- Stadion codebase: `class-google-contacts-connection.php` - Connection storage with sync_token field

### Secondary (MEDIUM confidence)
- [WordPress Cron Scheduling](https://developer.wordpress.org/plugins/cron/understanding-wp-cron-scheduling/) - Custom schedules, best practices
- [cron_schedules filter](https://developer.wordpress.org/reference/hooks/cron_schedules/) - Adding custom intervals

### Tertiary (LOW confidence)
- None - all critical findings verified with official documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, patterns proven in codebase
- Architecture: HIGH - Following existing calendar sync pattern exactly
- Pitfalls: HIGH - Documented in official Google API docs and verified in codebase

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (30 days - stable APIs, established patterns)
