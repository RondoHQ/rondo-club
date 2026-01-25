# Phase 83: Conflict & Deletion - Research

**Researched:** 2026-01-17
**Domain:** Bidirectional sync conflict detection, resolution strategies, Google People API deletion
**Confidence:** HIGH

## Summary

Phase 83 adds conflict detection and deletion handling to the existing delta sync infrastructure from Phase 82. The CONTEXT.md specifies field-level conflict detection (conflicts occur only when the SAME field is modified in both systems), with Stadion-always-wins resolution strategy and activity log entries for audit.

The existing sync infrastructure already has:
1. `import_delta()` in GoogleContactsAPI that detects Google deletions via `getMetadata()->getDeleted()`
2. `push_changed_contacts()` in GoogleContactsSync that exports modified Stadion contacts
3. `_google_last_import` and `_google_last_export` timestamps for change tracking
4. Activity logging via `wp_insert_comment()` with `TYPE_ACTIVITY` comment type

For deletion handling: Stadion deletions propagate to Google via `before_delete_post` hook calling the People API `deleteContact` method. Google deletions are already handled in Phase 82 (unlink only, preserve Stadion data).

**Primary recommendation:** Add field snapshot storage (`_google_synced_fields` post meta), conflict detection in pull phase, activity logging for resolved conflicts, and deletion propagation via WordPress hook.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Google People API v1 | Current | deleteContact method | Official Google API |
| WordPress Hooks | Built-in | before_delete_post for deletion | Native, reliable |
| WordPress Comments | Built-in | Activity logging | Existing pattern |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Post Meta | Built-in | Store synced field snapshot | Track last-synced values |
| STADION_Comment_Types | Existing | Activity type constant | Log conflict resolutions |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Post meta for snapshots | Custom table | Post meta is WordPress-native, no migration needed |
| Activity entries | Separate audit log | Activities already shown in timeline, fits existing UI |
| Immediate deletion | Soft delete with delay | CONTEXT.md specifies immediate propagation |

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-google-contacts-sync.php       # Modified: conflict detection in sync_user()
├── class-google-contacts-api-import.php # Modified: store field snapshots
├── class-google-contacts-export.php     # Modified: delete hook, update snapshots
└── class-google-contacts-connection.php # Unchanged
```

### Pattern 1: Field Snapshot Storage
**What:** Store last-synced field values to detect what changed
**When to use:** After every successful sync (import or export)
**Example:**
```php
// Source: Based on ServiceMax field-level sync conflict pattern
// Store snapshot of synced fields in post meta
$snapshot = [
    'first_name'   => get_field('first_name', $post_id),
    'last_name'    => get_field('last_name', $post_id),
    'email'        => $this->get_primary_email_value($post_id),
    'phone'        => $this->get_primary_phone_value($post_id),
    'address'      => $this->get_primary_address_value($post_id),
    'team' => $this->get_current_team($post_id),
    'birthday'     => $this->get_birthday_value($post_id),
    'synced_at'    => current_time('c'),
];
update_post_meta($post_id, '_google_synced_fields', $snapshot);
```

### Pattern 2: Field-Level Conflict Detection
**What:** Compare Google values against both current Stadion values AND last-synced snapshot
**When to use:** During pull phase in import_delta()
**Example:**
```php
// Source: Based on CONTEXT.md decision - conflict only if SAME field modified
private function detect_field_conflicts(int $post_id, object $google_person): array {
    $snapshot = get_post_meta($post_id, '_google_synced_fields', true) ?: [];
    $conflicts = [];

    // Get Google values
    $google_name = $this->extract_google_name($google_person);

    // Get current Stadion values
    $stadion_first = get_field('first_name', $post_id);
    $stadion_last = get_field('last_name', $post_id);

    // Check first_name: conflict if BOTH changed from snapshot
    $snapshot_first = $snapshot['first_name'] ?? '';
    if ($google_name['first'] !== $snapshot_first && $stadion_first !== $snapshot_first) {
        // Both changed - this is a conflict
        $conflicts[] = [
            'field'        => 'first_name',
            'google_value' => $google_name['first'],
            'stadion_value' => $stadion_first,
            'kept_value'   => $stadion_first, // Stadion wins
        ];
    }

    // Similar checks for other fields...

    return $conflicts;
}
```

### Pattern 3: Activity Logging for Conflicts
**What:** Log conflict resolutions as activity entries on the person
**When to use:** When conflicts are detected and resolved
**Example:**
```php
// Source: Existing pattern from class-calendar-sync.php and class-comment-types.php
private function log_conflict_resolution(int $post_id, array $conflicts, int $user_id): void {
    if (empty($conflicts)) {
        return;
    }

    // Format conflicts into readable content
    $lines = [];
    foreach ($conflicts as $conflict) {
        $lines[] = sprintf(
            '%s: Google had "%s", kept "%s"',
            ucfirst(str_replace('_', ' ', $conflict['field'])),
            $conflict['google_value'] ?: '(empty)',
            $conflict['kept_value'] ?: '(empty)'
        );
    }

    $content = sprintf(
        __('Sync conflict resolved (Stadion wins):\n%s', 'stadion'),
        implode("\n", $lines)
    );

    wp_insert_comment([
        'comment_post_ID'  => $post_id,
        'comment_content'  => $content,
        'comment_type'     => 'stadion_activity',
        'user_id'          => $user_id,
        'comment_approved' => 1,
    ]);

    // Set activity meta
    update_comment_meta($comment_id, 'activity_type', 'sync_conflict');
    update_comment_meta($comment_id, 'activity_date', current_time('Y-m-d'));
}
```

### Pattern 4: Deletion Hook for Stadion -> Google
**What:** Hook into WordPress post deletion to delete corresponding Google contact
**When to use:** When a linked person post is permanently deleted
**Example:**
```php
// Source: https://developer.wordpress.org/reference/hooks/before_delete_post/
// Use before_delete_post to access post meta before WordPress deletes it
add_action('before_delete_post', function($post_id, $post) {
    if ($post->post_type !== 'person') {
        return;
    }

    $google_id = get_post_meta($post_id, '_google_contact_id', true);
    if (empty($google_id)) {
        return; // Not linked to Google
    }

    $user_id = (int) $post->post_author;

    // Verify user has readwrite connection
    $connection = GoogleContactsConnection::get_connection($user_id);
    if (!$connection || ($connection['access_mode'] ?? '') !== 'readwrite') {
        return;
    }

    try {
        $service = $this->get_people_service_for_user($user_id);
        $service->people->deleteContact($google_id);
    } catch (\Exception $e) {
        // Log error but don't block deletion
        error_log('PRM: Failed to delete Google contact ' . $google_id . ': ' . $e->getMessage());
    }
}, 10, 2);
```

### Pattern 5: Google People API deleteContact
**What:** Delete a contact from Google Contacts
**When to use:** When Stadion contact is permanently deleted
**Example:**
```php
// Source: https://developers.google.com/people/api/rest/v1/people/deleteContact
// DELETE https://people.googleapis.com/v1/{resourceName}:deleteContact

$service = new PeopleService($client);
$service->people->deleteContact($resource_name);
// Returns empty response on success
// Throws exception on error
```

### Anti-Patterns to Avoid
- **Comparing entire objects:** Only compare individual fields to detect conflicts
- **Blocking post deletion on API error:** Always allow local deletion, log Google API failures
- **Hash-based comparison:** Direct value comparison is more debuggable and per CONTEXT.md
- **Auto-resolving without logging:** All conflicts must be logged for audit

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Activity logging | Custom audit table | WordPress comments with TYPE_ACTIVITY | Existing UI, proven pattern |
| Deletion detection | Polling for missing IDs | before_delete_post hook | WordPress-native, reliable |
| Field comparison | Custom diff algorithm | Direct string comparison | Simpler, matches CONTEXT.md requirement |
| Google deletion | Custom API calls | PeopleService::deleteContact | Official library method |

**Key insight:** The existing activity logging infrastructure is perfect for conflict audit. No need for separate audit system.

## Common Pitfalls

### Pitfall 1: Wrong Deletion Hook
**What goes wrong:** Using `delete_post` instead of `before_delete_post`
**Why it happens:** Both sound similar
**How to avoid:** Use `before_delete_post` - meta data still available
**Warning signs:** `get_post_meta()` returns empty for `_google_contact_id`

### Pitfall 2: Blocking Stadion Deletion on Google Error
**What goes wrong:** User can't delete contact if Google API fails
**Why it happens:** Throwing exception in hook
**How to avoid:** Wrap Google API call in try/catch, log errors, allow deletion
**Warning signs:** Contacts stuck in Stadion when Google is unreachable

### Pitfall 3: Snapshot Not Updated After Sync
**What goes wrong:** Conflicts detected incorrectly because snapshot is stale
**Why it happens:** Forgetting to update snapshot after import/export
**How to avoid:** Always update `_google_synced_fields` after successful field sync
**Warning signs:** Same conflict logged repeatedly on every sync

### Pitfall 4: Google Deletion Already Handled
**What goes wrong:** Implementing duplicate unlink logic
**Why it happens:** Forgetting Phase 82 already handles this
**How to avoid:** Google deletions are handled in `import_delta()` via `getMetadata()->getDeleted()`
**Warning signs:** Code duplication, N/A for Phase 83

### Pitfall 5: Trash vs Permanent Delete
**What goes wrong:** Deleting Google contact when Stadion contact is just trashed
**Why it happens:** Hooking wrong action
**How to avoid:** `before_delete_post` only fires on permanent delete (empty trash)
**Warning signs:** Contacts deleted from Google when trashed in Stadion

## Code Examples

### Google deleteContact API Call
```php
// Source: https://developers.google.com/people/api/rest/v1/people/deleteContact
// Requires https://www.googleapis.com/auth/contacts scope

$service = new \Google\Service\PeopleService($client);

try {
    // DELETE /v1/people/c1234567890:deleteContact
    $service->people->deleteContact('people/c1234567890');
    // Success: empty response
} catch (\Google\Service\Exception $e) {
    // Handle error (404 = already deleted, 403 = permission denied)
    error_log('Delete failed: ' . $e->getMessage());
}
```

### Activity Entry Creation
```php
// Source: Existing pattern from class-calendar-sync.php line 467
use Stadion\Collaboration\CommentTypes;

$comment_id = wp_insert_comment([
    'comment_post_ID'  => $person_id,
    'comment_content'  => $content,
    'comment_type'     => CommentTypes::TYPE_ACTIVITY,
    'user_id'          => $user_id,
    'comment_approved' => 1,
]);

if ($comment_id) {
    update_comment_meta($comment_id, 'activity_type', 'sync_conflict');
    update_comment_meta($comment_id, 'activity_date', current_time('Y-m-d'));
}
```

### Field Extraction for Comparison
```php
// Get primary email from contact_info repeater
private function get_primary_email_value(int $post_id): string {
    $contact_info = get_field('contact_info', $post_id) ?: [];
    foreach ($contact_info as $info) {
        if (($info['contact_type'] ?? '') === 'email') {
            return strtolower($info['contact_value'] ?? '');
        }
    }
    return '';
}
```

### WordPress Deletion Hook
```php
// Source: https://developer.wordpress.org/reference/hooks/before_delete_post/
// Fires at start of wp_delete_post(), before any data is deleted

add_action('before_delete_post', [$this, 'handle_person_deletion'], 10, 2);

public function handle_person_deletion(int $post_id, \WP_Post $post): void {
    // Only handle person posts
    if ($post->post_type !== 'person') {
        return;
    }

    // Check if linked to Google
    $google_id = get_post_meta($post_id, '_google_contact_id', true);
    if (empty($google_id)) {
        return;
    }

    // Attempt Google deletion (non-blocking)
    $this->delete_google_contact($google_id, (int) $post->post_author);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Timestamp-only conflict | Field-level comparison | Modern sync patterns | More precise conflict detection |
| Manual resolution | Auto-resolve with audit | Per CONTEXT.md | Simpler UX, maintains transparency |
| Cascade delete both ways | Asymmetric (Stadion authoritative) | Per CONTEXT.md | Preserves user data |

**Current state in Stadion:**
- Phase 82 handles Google deletions (unlink only)
- Phase 82 has delta sync infrastructure
- Activity logging infrastructure exists
- No field snapshot storage yet
- No Stadion -> Google deletion propagation yet

## Open Questions

1. **Multiple field conflicts in single sync**
   - What we know: CONTEXT.md says batch into single activity entry
   - What's unclear: Maximum readability for multi-field conflicts
   - Recommendation: Use bullet list format in activity content

2. **Retry logic for Google API delete failures**
   - What we know: CONTEXT.md mentions Claude's discretion for error retry
   - What's unclear: How many retries, what delay
   - Recommendation: Single async retry via WP-Cron if immediate fails

3. **Birthday field comparison complexity**
   - What we know: Birthday is stored as important_date CPT linked to person
   - What's unclear: How to compare and track birthday changes
   - Recommendation: Skip birthday from conflict detection initially, add in Polish phase if needed

## Sources

### Primary (HIGH confidence)
- [Google People API deleteContact](https://developers.google.com/people/api/rest/v1/people/deleteContact) - API endpoint, parameters, scopes
- [WordPress before_delete_post hook](https://developer.wordpress.org/reference/hooks/before_delete_post/) - Hook timing, parameters, when meta is available
- Stadion codebase: `class-google-contacts-sync.php` - Current sync infrastructure
- Stadion codebase: `class-google-contacts-api-import.php` - Delta import with deletion detection
- Stadion codebase: `class-comment-types.php` - Activity logging patterns
- CONTEXT.md - User decisions on conflict strategy and deletion behavior

### Secondary (MEDIUM confidence)
- [WordPress delete_post vs before_delete_post](https://developer.wordpress.org/reference/hooks/delete_post/) - Hook ordering and data availability
- [ServiceMax Field-Level Sync Conflict](https://support.ptc.com/help/servicemaxcore/en/articles/core/advanced-sync-conflict-with-field-level-sync-conflict.html) - Field-level comparison pattern

### Tertiary (LOW confidence)
- None - all critical patterns verified with official documentation or existing codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using existing WordPress/Google infrastructure
- Architecture: HIGH - Based on existing Stadion patterns and CONTEXT.md decisions
- Pitfalls: HIGH - Documented in WordPress codex and verified against codebase

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (30 days - stable APIs, established patterns)
