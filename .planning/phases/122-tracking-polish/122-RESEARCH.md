# Phase 122: Tracking & Polish - Research

**Researched:** 2026-01-30
**Domain:** Email tracking infrastructure, VOG list filtering, timeline integration
**Confidence:** HIGH

## Summary

This phase adds email tracking and filtering capabilities to the VOG management system. The research focused on understanding the existing codebase patterns for comment-based activities, API endpoints, and frontend filtering mechanisms.

The existing infrastructure is well-suited for this implementation. The codebase already has a robust comment-based activity system (`stadion_note`, `stadion_activity` types) with a timeline view that can be extended to support a new `stadion_email` type. The VOG email sending functionality already stores a basic `vog_email_sent_date` in post meta, but a more comprehensive email tracking system using comments will provide richer history with actual content snapshots.

The VOG list currently uses a filtered people API endpoint that supports server-side filtering. Adding an email status filter requires extending this endpoint with a new parameter and implementing the filter logic based on comment metadata.

**Primary recommendation:** Extend the existing comment types system with `TYPE_EMAIL` and store sent email content as comment meta, then add a filter parameter to the `/rondo/v1/people/filtered` endpoint for email status filtering.

## Standard Stack

The established libraries/tools for this domain:

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Comments API | 6.0+ | Email history storage | Native WP, already used for notes/activities |
| TanStack Query | 5.x | Data fetching/caching | Already used throughout app |
| Lucide React | 0.x | Icons | Already used, has Mail/Send icons |
| date-fns | 3.x | Date formatting | Already used via `@/utils/dateFormat` |

### Supporting (Already in Use)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Tailwind CSS | 3.4 | Styling | All UI components |
| React Router | 6.x | Navigation | Linking to person profiles |

### No New Dependencies Required

This phase leverages existing infrastructure entirely. No new packages needed.

## Architecture Patterns

### Backend: Comment Types Extension

The existing `class-comment-types.php` defines `TYPE_NOTE` and `TYPE_ACTIVITY` constants. Follow this pattern:

```php
// Existing pattern in includes/class-comment-types.php
const TYPE_NOTE     = 'stadion_note';
const TYPE_ACTIVITY = 'stadion_activity';

// Add new type
const TYPE_EMAIL    = 'stadion_email';
```

Email-specific meta fields to register:
- `email_template_type`: 'new' | 'renewal' (VOG-specific, but extensible)
- `email_recipient`: Email address sent to
- `email_content_snapshot`: Actual rendered HTML content
- `email_subject`: Subject line

### Frontend: Timeline Type Extension

The `TimelineView.jsx` component already handles multiple item types (`note`, `activity`, `todo`). Add `email` type:

```jsx
// Existing pattern in src/components/Timeline/TimelineView.jsx
const getIconForItem = (item) => {
  if (item.type === 'todo') { ... }
  if (item.type === 'activity') { ... }
  if (item.type === 'note') { ... }
  // Add: email type
  if (item.type === 'email') return Mail;
  return Circle;
};
```

### API Filtering Pattern

The `/rondo/v1/people/filtered` endpoint uses JOIN-based filtering. Follow existing patterns:

```php
// Pattern from class-rest-people.php for custom field filters
'vog_email_status' => [
    'description'       => 'Filter by VOG email status (sent, not_sent, all)',
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => function ( $value ) {
        return in_array( $value, [ '', 'sent', 'not_sent' ], true );
    },
],
```

### Filter UI Pattern

VOG list header uses dropdown select pattern. Reference existing filter dropdowns in `PeopleList.jsx`:

```jsx
// Standard dropdown filter pattern
<select
  value={filterValue}
  onChange={(e) => setFilterValue(e.target.value)}
  className="text-sm border-gray-300 rounded-md ..."
>
  <option value="">Alle</option>
  <option value="not_sent">Niet verzonden ({notSentCount})</option>
  <option value="sent">Wel verzonden ({sentCount})</option>
</select>
```

### Expandable Content Pattern

For showing email content on click, use disclosure/accordion pattern:

```jsx
// Expandable content in timeline
const [expanded, setExpanded] = useState(false);

<div onClick={() => setExpanded(!expanded)}>
  {/* Summary row */}
</div>
{expanded && (
  <div className="mt-2 p-3 bg-gray-50 rounded border">
    <div dangerouslySetInnerHTML={{ __html: item.email_content }} />
  </div>
)}
```

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email history storage | Custom database table | WordPress Comments API | ACF/WP native, already used for notes/activities |
| Date formatting | Custom date formatting | `@/utils/dateFormat` (date-fns wrapper) | Already standardized across app |
| Icon selection | Custom icons | Lucide React | Already used, consistent design |
| API filtering | String manipulation | WP_Query meta_query | Proper escaping, performant |
| Loading states | Custom spinners | Existing patterns from VOGList | Consistent UX |

**Key insight:** The existing comment types system was designed to be extensible. Adding email tracking follows the same pattern as notes and activities, reducing implementation complexity and maintaining consistency.

## Common Pitfalls

### Pitfall 1: Forgetting to Exclude Email Type from Regular Comment Queries
**What goes wrong:** Email comments appear in WordPress admin comment listings
**Why it happens:** Comment types need explicit exclusion
**How to avoid:** Add `TYPE_EMAIL` to the `exclude_from_regular_queries` filter in `class-comment-types.php`
**Warning signs:** Seeing email entries in WP Admin > Comments

### Pitfall 2: Not Storing Content Snapshot
**What goes wrong:** Email content changes if templates are updated, history becomes inaccurate
**Why it happens:** Only storing template type reference, not actual content
**How to avoid:** Store the fully rendered HTML content at send time as `email_content_snapshot` meta
**Warning signs:** Email history showing current template instead of what was actually sent

### Pitfall 3: Filter Count Mismatch
**What goes wrong:** Dropdown shows counts that don't match actual filtered results
**Why it happens:** Count query uses different criteria than filter query
**How to avoid:** Use same JOIN/WHERE logic for counts and filtering, or compute counts from main query result
**Warning signs:** "Niet verzonden (12)" but only 10 results shown

### Pitfall 4: Missing ACF Field for Email Sent Column
**What goes wrong:** VOG list shows `vog-email-verzonden` but field doesn't exist
**Why it happens:** Field was referenced in code but never created in ACF
**How to avoid:** Verify `vog-email-verzonden` field exists in ACF custom fields, or use comment-based data instead
**Warning signs:** Undefined field errors, empty column values

### Pitfall 5: Stale Email Status After Sending
**What goes wrong:** User sends email but list doesn't update
**Why it happens:** Query invalidation missing after mutation
**How to avoid:** Ensure `queryClient.invalidateQueries` includes the filtered people query key
**Warning signs:** Need to manually refresh to see updated status

## Code Examples

Verified patterns from the existing codebase:

### Creating Email Comment (PHP)
```php
// Source: Based on class-comment-types.php create_note/create_activity patterns
public function create_email_log( $person_id, $data ) {
    $comment_id = wp_insert_comment([
        'comment_post_ID'  => $person_id,
        'comment_content'  => $data['subject'], // Summary for display
        'comment_type'     => self::TYPE_EMAIL,
        'user_id'          => get_current_user_id(),
        'comment_approved' => 1,
    ]);

    if ( ! $comment_id ) {
        return new \WP_Error( 'create_failed', 'Failed to log email.' );
    }

    // Save email-specific meta
    update_comment_meta( $comment_id, 'email_template_type', $data['template_type'] );
    update_comment_meta( $comment_id, 'email_recipient', $data['recipient'] );
    update_comment_meta( $comment_id, 'email_content_snapshot', $data['rendered_content'] );
    update_comment_meta( $comment_id, 'email_subject', $data['subject'] );

    return $comment_id;
}
```

### Fetching Timeline with Emails (PHP)
```php
// Source: Based on class-comment-types.php get_timeline method
$comments = get_comments([
    'post_id'  => $person_id,
    'type__in' => [ self::TYPE_NOTE, self::TYPE_ACTIVITY, self::TYPE_EMAIL ],
    'status'   => 'approve',
    'orderby'  => 'comment_date',
    'order'    => 'DESC',
]);
```

### Email Timeline Item Rendering (JSX)
```jsx
// Source: Based on TimelineView.jsx renderTimelineItem pattern
if (item.type === 'email') {
  return (
    <div className="relative pl-8 pb-6 group">
      <div className="absolute left-0 top-1">
        <div className="w-4 h-4 rounded-full border-2 bg-green-500 border-green-500" />
      </div>
      <div className="flex items-center gap-2 mb-1">
        <Mail className="w-4 h-4 text-green-600 dark:text-green-400" />
        <span className="text-xs font-medium text-gray-600 dark:text-gray-300">
          VOG Email ({item.email_template_type === 'new' ? 'nieuw' : 'vernieuwing'})
        </span>
        <span className="text-xs text-gray-400">•</span>
        <span className="text-xs text-gray-500">{formattedDate}</span>
      </div>
      <p className="text-sm">Verzonden naar {item.email_recipient}</p>
      {/* Expandable content section */}
    </div>
  );
}
```

### Filter Dropdown with Counts (JSX)
```jsx
// Source: Based on existing select patterns in PeopleList.jsx
function EmailStatusFilter({ value, onChange, counts }) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="text-sm border-gray-300 dark:border-gray-600 rounded-md shadow-sm
                 focus:border-accent-500 focus:ring-accent-500
                 dark:bg-gray-700 dark:text-gray-200"
    >
      <option value="">Alle ({counts.total})</option>
      <option value="not_sent">Niet verzonden ({counts.notSent})</option>
      <option value="sent">Wel verzonden ({counts.sent})</option>
    </select>
  );
}
```

### API Filter Implementation (PHP)
```php
// Source: Based on class-rest-people.php get_filtered_people patterns
// Email status filter (VOG emails sent/not sent)
$vog_email_status = $request->get_param( 'vog_email_status' );
if ( ! empty( $vog_email_status ) ) {
    // Subquery to find people with email comments
    $email_subquery = "SELECT DISTINCT comment_post_ID FROM {$wpdb->comments}
                       WHERE comment_type = 'stadion_email'";

    if ( $vog_email_status === 'sent' ) {
        $where_clauses[] = "p.ID IN ($email_subquery)";
    } elseif ( $vog_email_status === 'not_sent' ) {
        $where_clauses[] = "p.ID NOT IN ($email_subquery)";
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `vog_email_sent_date` post_meta | Comment-based email logging | This phase | Full history with content snapshots |
| Simple boolean sent/not sent | Rich email history | This phase | Audit trail capability |

**Existing data migration:**
- The `vog_email_sent_date` post meta set by `class-vog-email.php` can be kept for backward compatibility
- New email sends should create both: comment log AND update the legacy meta field
- This ensures both the VOG list filter and the timeline display work correctly

## Open Questions

None - all implementation details are clear from the codebase analysis.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-comment-types.php` - Existing comment types pattern
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-vog-email.php` - Current VOG email sending implementation
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-people.php` - Filtered people API endpoint
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/VOG/VOGList.jsx` - Current VOG list implementation
- `/Users/joostdevalk/Code/rondo/rondo-club/src/components/Timeline/TimelineView.jsx` - Timeline rendering patterns

### Secondary (MEDIUM confidence)
- WordPress Comments API documentation
- ACF field storage patterns observed in `acf-json/` files

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All existing libraries, verified in codebase
- Architecture: HIGH - Direct extension of existing patterns
- Pitfalls: HIGH - Based on actual code review

**Research date:** 2026-01-30
**Valid until:** 60 days (stable infrastructure, no expected changes)
