# Phase 10: Collaborative Features - Research

**Researched:** 2026-01-13
**Domain:** @mentions, notifications, activity feeds, iCal generation for multi-user WordPress CRM
**Confidence:** HIGH

<research_summary>
## Summary

Researched the ecosystem for implementing collaborative features in a WordPress/React CRM. The phase requires @mentions in notes with notifications, workspace iCal feeds, activity digests, and per-workspace notification preferences.

**Key findings:**
- Caelis already has robust notification infrastructure (`PRM_Notification_Channel`, `PRM_Email_Channel`, `PRM_Slack_Channel`)
- Existing per-user cron scheduling via `PRM_Reminders` can be extended for workspace digests
- Existing iCal feed (`PRM_ICal_Feed`) uses manual generation - works well, no need for sabre/vobject
- Notes system (`PRM_Comment_Types`) stores content in `wp_comments` with meta - ideal for adding mention metadata
- **react-mentions** is the standard library for @mention UI in React textareas

**Primary recommendation:** Extend existing notification and comment infrastructure rather than introducing new systems. Use react-mentions for frontend, store mentioned user IDs in comment meta, trigger notifications via existing channels.
</research_summary>

<standard_stack>
## Standard Stack

### Core (Already in Caelis)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress comments | WP core | Note/activity storage | Native, versioned, queryable |
| wp_cron | WP core | Digest scheduling | Per-user scheduling already implemented |
| wp_mail | WP core | Email delivery | Existing channel implementation |

### New Frontend Libraries
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| react-mentions | 4.4.10 | @mention autocomplete in textarea | 7+ years maintained, 180+ dependents |
| DOMPurify | 3.x | XSS sanitization for rendered mentions | Industry standard, already conceptually used server-side |

### Supporting (Already in Caelis)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PRM_Notification_Channel | custom | Abstract notification base | Extend for new notification types |
| PRM_Email_Channel | custom | Email digest delivery | Reuse for workspace digests |
| PRM_Slack_Channel | custom | Slack notifications | Extend for @mention alerts |
| PRM_ICal_Feed | custom | Manual iCal generation | Extend for workspace feeds |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| react-mentions | Downshift | Downshift is lower-level, more setup for mentions UI |
| Manual iCal | sabre/vobject | Already have working implementation, no need to change |
| Custom notifications | BuddyPress notifications | Heavy dependency for simple use case |

**Installation:**
```bash
npm install react-mentions
```
</standard_stack>

<architecture_patterns>
## Architecture Patterns

### Recommended Project Structure
```
# PHP (extends existing structure)
includes/
├── class-mentions.php           # Parse/store/query mentions
├── class-mention-notifications.php  # Trigger notifications on mention
├── class-workspace-digest.php   # Workspace-scoped digest generation
└── class-rest-workspaces.php    # Add iCal endpoint to existing class

# React (extends existing structure)
src/
├── components/
│   ├── MentionInput/
│   │   ├── MentionInput.jsx    # Wrapper around react-mentions
│   │   └── index.js
│   └── Timeline/
│       └── TimelineItem.jsx    # Updated to render mentions as links
├── hooks/
│   └── useWorkspaceMembers.js  # For mention suggestions
└── api/
    └── mentions.js             # API calls for mentions
```

### Pattern 1: Mention Storage in Comment Meta
**What:** Store parsed mention user IDs as comment meta
**When to use:** When saving notes/activities with @mentions
**Example:**
```php
// When saving a note with mentions
$comment_id = wp_insert_comment([...]);
$mentioned_user_ids = $this->parse_mentions($content);
update_comment_meta($comment_id, '_mentioned_users', $mentioned_user_ids);
```

### Pattern 2: react-mentions Controlled Component
**What:** Use MentionsInput as controlled component with async user search
**When to use:** Note/activity input fields
**Example:**
```jsx
// Source: react-mentions documentation
import { MentionsInput, Mention } from 'react-mentions';

function NoteInput({ value, onChange, workspaceId }) {
  const fetchUsers = async (query, callback) => {
    const members = await api.getWorkspaceMembers(workspaceId, query);
    callback(members.map(u => ({ id: u.id, display: u.name })));
  };

  return (
    <MentionsInput value={value} onChange={onChange}>
      <Mention
        trigger="@"
        data={fetchUsers}
        markup="@[__display__](__id__)"
      />
    </MentionsInput>
  );
}
```

### Pattern 3: Notification on Mention (Hook-based)
**What:** Trigger notifications via WordPress action hook when mention is saved
**When to use:** After saving comment with mentions
**Example:**
```php
// In class-comment-types.php create_note()
do_action('prm_user_mentioned', $comment_id, $mentioned_user_ids, $author_id);

// In class-mention-notifications.php
add_action('prm_user_mentioned', [$this, 'send_mention_notification'], 10, 3);
```

### Pattern 4: Workspace iCal Feed Extension
**What:** Extend existing PRM_ICal_Feed with workspace scope
**When to use:** Workspace calendar subscriptions
**Example:**
```php
// Add rewrite rule
add_rewrite_rule(
    '^workspace/([0-9]+)/calendar/([a-f0-9]+)\.ics$',
    'index.php?prm_workspace_ical=1&prm_workspace_id=$matches[1]&prm_ical_token=$matches[2]',
    'top'
);
```

### Anti-Patterns to Avoid
- **Parsing mentions on every render:** Parse once on save, store IDs in meta
- **Custom regex for mentions:** Use react-mentions built-in markup serializer
- **Sending immediate notifications:** Queue via existing cron system to batch
- **Separate notification tables:** Use existing comment meta + user meta patterns
</architecture_patterns>

<dont_hand_roll>
## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| @mention UI | Custom textarea with regex | react-mentions | Autocomplete, keyboard nav, mobile support |
| Mention parsing | Custom regex | react-mentions markup serializer | Handles edge cases, escaping, cursor position |
| iCal format | String concatenation | Existing PRM_ICal_Feed patterns | RFC compliance, escaping, timezone handling |
| Email HTML | Inline styles manually | Existing PRM_Email_Channel | Already handles formatting, from name/email |
| Notification routing | Custom notification system | Extend PRM_Notification_Channel | Multi-channel support, user preferences |
| Cron scheduling | Custom scheduling | wp_schedule_event | WordPress handles persistence, deduplication |

**Key insight:** Caelis already has 80% of the notification infrastructure. The @mentions feature is primarily:
1. A UI component (react-mentions)
2. Comment meta storage for mentioned user IDs
3. A hook that triggers existing notification channels

Don't rebuild what exists.
</dont_hand_roll>

<common_pitfalls>
## Common Pitfalls

### Pitfall 1: Notification Spam
**What goes wrong:** User gets notified for every mention immediately, overwhelming them
**Why it happens:** Sending notifications synchronously on mention save
**How to avoid:**
- Batch mention notifications into existing digest system
- Or use rate limiting: max 1 notification per user per hour for mentions
- Add user preference: "Notify me of mentions: Immediately / In digest / Never"
**Warning signs:** Users complaining about too many notifications, unsubscribing from all notifications

### Pitfall 2: Mention Rendering XSS
**What goes wrong:** Stored mention markup renders as executable HTML
**Why it happens:** Rendering user-generated @mention markup without sanitization
**How to avoid:**
- Server-side: wp_kses_post() on content (already done in class-comment-types.php)
- Client-side: Render mentions as React components, not dangerouslySetInnerHTML
- Or use DOMPurify before rendering
**Warning signs:** Ability to inject `<script>` tags via mention display names

### Pitfall 3: Performance with Large Teams
**What goes wrong:** Autocomplete lags with 100+ workspace members
**Why it happens:** Loading all members upfront, filtering client-side
**How to avoid:**
- Use async data fetching in react-mentions
- Debounce search queries (300ms)
- Limit results to 10-15 suggestions
- Server-side search with SQL LIKE
**Warning signs:** Typing delay in mention input, high memory usage

### Pitfall 4: iCal Feed Permissions
**What goes wrong:** Workspace calendar exposed to non-members via token URL
**Why it happens:** Not checking workspace membership on calendar access
**How to avoid:**
- Token must belong to user who is workspace member
- Check membership on every feed request
- Invalidate/regenerate tokens when user removed from workspace
**Warning signs:** Dates visible to users who shouldn't see them

### Pitfall 5: Digest Timing Complexity
**What goes wrong:** Workspace digests sent at wrong times for different user timezones
**Why it happens:** Using workspace-level schedule instead of per-user schedule
**How to avoid:**
- Continue using per-user cron (already implemented in PRM_Reminders)
- Include workspace activity in user's existing digest
- Don't create separate workspace-level cron jobs
**Warning signs:** Users in different timezones getting digests at odd hours
</common_pitfalls>

<code_examples>
## Code Examples

Verified patterns from official sources and existing Caelis code:

### react-mentions Basic Setup
```jsx
// Source: react-mentions npm documentation
import { MentionsInput, Mention } from 'react-mentions';

// Markup format stores: @[Display Name](user_id)
const defaultMentionStyle = {
  backgroundColor: '#cee4e5',
};

function MentionableTextarea({ value, onChange }) {
  return (
    <MentionsInput
      value={value}
      onChange={(e, newValue) => onChange(newValue)}
      placeholder="Add a note... Use @ to mention someone"
    >
      <Mention
        trigger="@"
        data={fetchUsers}
        markup="@[__display__](__id__)"
        style={defaultMentionStyle}
        appendSpaceOnAdd
      />
    </MentionsInput>
  );
}
```

### Parse Mentions from Stored Content (PHP)
```php
// Source: Caelis pattern, react-mentions markup format
class PRM_Mentions {
    /**
     * Parse user IDs from mention markup
     * Markup format: @[Display Name](user_id)
     */
    public static function parse_mention_ids($content) {
        $pattern = '/@\[[^\]]+\]\((\d+)\)/';
        preg_match_all($pattern, $content, $matches);
        return array_map('intval', $matches[1] ?? []);
    }

    /**
     * Convert mention markup to linked HTML
     */
    public static function render_mentions($content, $site_url) {
        $pattern = '/@\[([^\]]+)\]\((\d+)\)/';
        return preg_replace_callback($pattern, function($matches) use ($site_url) {
            $name = esc_html($matches[1]);
            $user_id = intval($matches[2]);
            $user = get_userdata($user_id);
            if (!$user) return '@' . $name;
            return sprintf(
                '<a href="%s/team/%d" class="mention">@%s</a>',
                $site_url,
                $user_id,
                $name
            );
        }, $content);
    }
}
```

### Notification Hook Integration (PHP)
```php
// Source: Existing PRM_Notification_Channel pattern
class PRM_Mention_Notifications {
    public function __construct() {
        add_action('prm_user_mentioned', [$this, 'queue_mention_notification'], 10, 3);
    }

    public function queue_mention_notification($comment_id, $mentioned_user_ids, $author_id) {
        foreach ($mentioned_user_ids as $user_id) {
            // Don't notify yourself
            if ($user_id === $author_id) continue;

            // Check user preference
            $pref = get_user_meta($user_id, 'caelis_mention_notifications', true);
            if ($pref === 'never') continue;

            if ($pref === 'immediate') {
                $this->send_immediate_notification($comment_id, $user_id, $author_id);
            } else {
                // Queue for next digest (default)
                $this->queue_for_digest($comment_id, $user_id);
            }
        }
    }
}
```

### Workspace iCal Feed Extension (PHP)
```php
// Source: Existing PRM_ICal_Feed pattern
// Add to register_rewrite_rules()
add_rewrite_rule(
    '^workspace/(?P<workspace_id>\d+)/calendar/(?P<token>[a-f0-9]+)\.ics$',
    'index.php?prm_workspace_ical=1&prm_workspace_id=$matches[1]&prm_ical_token=$matches[2]',
    'top'
);

// In handle_feed_request()
if (get_query_var('prm_workspace_ical')) {
    $workspace_id = get_query_var('prm_workspace_id');
    $token = get_query_var('prm_ical_token');

    // Verify token belongs to workspace member
    $user_id = $this->get_user_by_token($token);
    if (!$user_id || !PRM_Workspace_Members::is_member($workspace_id, $user_id)) {
        status_header(403);
        exit('Access denied');
    }

    $this->output_workspace_feed($workspace_id, $user_id);
    exit;
}
```
</code_examples>

<sota_updates>
## State of the Art (2025-2026)

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual mention regex | react-mentions library | 2020+ | Handles edge cases, mobile, accessibility |
| Immediate notifications | Batched/digest notifications | 2023+ | Reduces notification fatigue |
| Global notification settings | Per-channel, per-workspace preferences | 2024+ | User control over notification volume |
| Single digest for all | Activity-type filtered digests | 2024+ | Relevant notifications only |

**New tools/patterns to consider:**
- **Action Scheduler** (WooCommerce): More robust than wp_cron for high-volume async tasks. Consider if notification volume becomes high.
- **Web Push API**: For immediate mention notifications without email. Future enhancement.

**Deprecated/outdated:**
- **BuddyPress for notifications**: Overkill for simple mention system, heavy dependency
- **Custom notification tables**: WordPress meta is sufficient and integrates better
</sota_updates>

<open_questions>
## Open Questions

1. **Mention notification preference UI location**
   - What we know: Need per-workspace notification preferences
   - What's unclear: Should this be in Settings page, workspace settings, or inline?
   - Recommendation: Start in Settings page under existing notification preferences, iterate based on user feedback

2. **Workspace digest aggregation**
   - What we know: Users may be in multiple workspaces
   - What's unclear: Single combined digest or per-workspace digest?
   - Recommendation: Combined digest with workspace sections (like existing today/tomorrow/week sections)

3. **Mention rendering in existing notes**
   - What we know: Existing notes don't have mentions
   - What's unclear: Should we retroactively parse old notes for potential mentions?
   - Recommendation: No. Only new notes get mention functionality. Cleaner migration.
</open_questions>

<sources>
## Sources

### Primary (HIGH confidence)
- Caelis codebase: `class-notification-channels.php`, `class-reminders.php`, `class-ical-feed.php`, `class-comment-types.php`
- [react-mentions npm](https://www.npmjs.com/package/react-mentions) - official documentation
- [sabre/vobject documentation](https://sabre.io/vobject/icalendar/) - iCal patterns (not used but referenced)

### Secondary (MEDIUM confidence)
- [React Mentions Implementation Guide 2025](https://gauravadhikari.com/react-mentions-implementation-guide-with-typescript-in-2025/) - verified patterns
- [WordPress Cron Best Practices](https://medium.com/@jauresazata/demystifying-wordpress-cron-jobs-best-practices-and-implementation-tips-ee627a56d3e5) - verified with WP docs
- [Smashing Magazine: WordPress Notification System](https://www.smashingmagazine.com/2015/05/building-wordpress-notification-system/) - architecture patterns

### Tertiary (LOW confidence - needs validation)
- Activity feed aggregation patterns - community best practices, validate during implementation
</sources>

<metadata>
## Metadata

**Research scope:**
- Core technology: WordPress comments, wp_cron, React
- Ecosystem: react-mentions, existing Caelis notification infrastructure
- Patterns: Mention parsing, notification queuing, iCal extension
- Pitfalls: Notification spam, XSS, performance, permissions

**Confidence breakdown:**
- Standard stack: HIGH - extends existing Caelis code
- Architecture: HIGH - follows established WordPress/React patterns
- Pitfalls: HIGH - based on real notification system challenges
- Code examples: HIGH - from Caelis codebase and official docs

**Research date:** 2026-01-13
**Valid until:** 2026-02-13 (30 days - WordPress ecosystem stable)
</metadata>

---

*Phase: 10-collaborative-features*
*Research completed: 2026-01-13*
*Ready for planning: yes*
