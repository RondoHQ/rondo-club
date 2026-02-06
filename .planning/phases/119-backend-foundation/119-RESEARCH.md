# Phase 119: Backend Foundation - Research

**Researched:** 2026-01-30
**Domain:** WordPress email sending, ACF fields, Options API for site settings
**Confidence:** HIGH

## Summary

This phase establishes the email infrastructure for VOG (Verklaring Omtrent Gedrag) compliance workflows. The codebase already has a proven email sending pattern via `RONDO_Email_Channel` that uses `wp_mail()` with configurable from addresses. VOG settings should be stored as site-wide options (not user meta) since they're organization-level configuration shared by all admins.

The approach follows established patterns: use WordPress Options API for settings storage, add a new Settings tab for VOG configuration, create ACF fields for tracking email-sent dates per person, and build a dedicated VOG email service class for template handling and sending.

**Primary recommendation:** Follow the existing `class-email-channel.php` pattern for email sending, store VOG settings in options table with `stadion_vog_*` prefix, add VOG email tracking via standard post meta on person posts.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress wp_mail() | Core | Email sending | WordPress native, uses configured SMTP |
| WordPress Options API | Core | Site-wide settings | Standard for shared configuration |
| ACF Pro | Latest | Custom field management | Already used for person fields |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress filters | Core | Email from/name customization | wp_mail_from, wp_mail_from_name hooks |
| WordPress sanitization | Core | Input validation | sanitize_email(), sanitize_textarea_field() |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Options API | User meta | User meta is per-user; VOG settings are organization-wide |
| wp_mail | PHPMailer directly | wp_mail abstracts SMTP config, follows WP conventions |
| ACF field | Custom meta | ACF already handles person fields, maintains consistency |

**Installation:**
No additional packages needed - all functionality uses existing WordPress core and ACF Pro.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-vog-email.php         # VOG email service (send, templates, tracking)

src/
  pages/Settings/
    Settings.jsx              # Add 'VOG' tab to existing tabs
  api/client.js               # Add VOG settings API methods
  hooks/useVOGSettings.js     # TanStack Query hook for settings
```

### Pattern 1: Site-Wide Settings via Options API
**What:** Store VOG configuration (from email, templates) as WordPress options
**When to use:** Settings shared across all users, not per-user preferences
**Example:**
```php
// Source: Existing codebase pattern for storing settings
// Store settings
update_option( 'stadion_vog_from_email', sanitize_email( $email ) );
update_option( 'stadion_vog_template_new', sanitize_textarea_field( $template ) );
update_option( 'stadion_vog_template_renewal', sanitize_textarea_field( $template ) );

// Retrieve settings
$from_email = get_option( 'stadion_vog_from_email', get_bloginfo( 'admin_email' ) );
$template_new = get_option( 'stadion_vog_template_new', '' );
```

### Pattern 2: Email Sending with Custom From Address
**What:** Use wp_mail() with filters for from address
**When to use:** Sending transactional emails with custom sender
**Example:**
```php
// Source: includes/class-email-channel.php lines 82-94
class RONDO_VOG_Email {
    private string $from_email;

    public function send( int $person_id, string $template_type ): bool {
        $this->from_email = get_option( 'stadion_vog_from_email', get_bloginfo( 'admin_email' ) );

        add_filter( 'wp_mail_from', [ $this, 'get_from_email' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $result = wp_mail( $to, $subject, $message, $headers );

        remove_filter( 'wp_mail_from', [ $this, 'get_from_email' ] );
        remove_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );

        return $result;
    }

    public function get_from_email(): string {
        return $this->from_email;
    }

    public function get_from_name(): string {
        return get_bloginfo( 'name' );
    }
}
```

### Pattern 3: Template Variable Substitution
**What:** Replace placeholders like {first_name} in email templates
**When to use:** User-configurable email templates with dynamic content
**Example:**
```php
// Source: Standard WordPress pattern
private function substitute_variables( string $template, array $vars ): string {
    $replacements = [
        '{first_name}'       => $vars['first_name'] ?? '',
        '{previous_vog_date}' => $vars['previous_vog_date'] ?? '',
    ];
    return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
}
```

### Pattern 4: Tracking Dates via Post Meta
**What:** Store VOG email sent date as post meta on person posts
**When to use:** Recording timestamps for individual records
**Example:**
```php
// Source: Standard WordPress/ACF pattern
// Store when email was sent
update_post_meta( $person_id, 'vog_email_sent_date', current_time( 'Y-m-d H:i:s' ) );

// Retrieve for display/filtering
$sent_date = get_post_meta( $person_id, 'vog_email_sent_date', true );
```

### Anti-Patterns to Avoid
- **Storing templates in user meta:** VOG templates are organization-wide, not per-user
- **Direct PHPMailer usage:** Always use wp_mail() for WordPress integration
- **Global filter registration:** Add/remove filters around each send to avoid affecting other emails
- **Unescaped template output:** Always escape HTML output, sanitize stored values

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email sending | Custom SMTP code | wp_mail() | Respects server config, plugins can enhance |
| Settings storage | Custom table | Options API | Standard WordPress pattern, cached |
| Date formatting | Custom formatters | date_i18n(), wp_date() | Respects site locale settings |
| Input sanitization | Custom validation | sanitize_email(), sanitize_textarea_field() | Proven WordPress sanitizers |

**Key insight:** The existing codebase already has all the patterns needed. Follow class-email-channel.php for email sending, follow notification settings pattern for REST API, follow custom fields pattern for meta storage.

## Common Pitfalls

### Pitfall 1: Email From Address Deliverability
**What goes wrong:** Emails sent from mismatched domain end up in spam
**Why it happens:** SPF/DKIM checks fail when from-address domain doesn't match server domain
**How to avoid:** Default to notifications@{site-domain}, recommend users configure SMTP plugin
**Warning signs:** Users report not receiving VOG emails

### Pitfall 2: Filter Pollution
**What goes wrong:** wp_mail_from filter affects all site emails
**Why it happens:** Filter added but never removed, or exception interrupts removal
**How to avoid:** Add filter, send, remove filter in try/finally pattern
**Warning signs:** All WordPress emails start coming from VOG address

### Pitfall 3: Template Injection
**What goes wrong:** User-provided template contains malicious code
**Why it happens:** Template stored/displayed without sanitization
**How to avoid:** sanitize_textarea_field() on save, esc_html() on display in admin
**Warning signs:** XSS vulnerabilities in Settings page

### Pitfall 4: Missing First Email Address
**What goes wrong:** System tries to send VOG email but person has no email
**Why it happens:** Not checking for email before attempting send
**How to avoid:** Validate person has email address before send, return appropriate error
**Warning signs:** wp_mail() errors in logs, failed sends

### Pitfall 5: Date Format Inconsistency
**What goes wrong:** VOG dates display differently across the system
**Why it happens:** Using inconsistent date formatting functions
**How to avoid:** Use date_i18n( get_option( 'date_format' ) ) consistently
**Warning signs:** Dates showing in different formats in list vs detail views

## Code Examples

Verified patterns from the existing codebase:

### REST API Route Registration
```php
// Source: includes/class-rest-api.php pattern
register_rest_route(
    'rondo/v1',
    '/vog/settings',
    [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_vog_settings' ],
            'permission_callback' => [ $this, 'admin_permissions_check' ],
        ],
        [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'update_vog_settings' ],
            'permission_callback' => [ $this, 'admin_permissions_check' ],
        ],
    ]
);
```

### Settings Tab Pattern (React)
```jsx
// Source: src/pages/Settings/Settings.jsx TABS array
const TABS = [
  // ... existing tabs ...
  { id: 'vog', label: 'VOG', icon: Shield, adminOnly: true },
];
```

### Options Retrieval with Defaults
```php
// Source: includes/class-rest-api.php notification settings pattern
public function get_vog_settings( $request ) {
    return rest_ensure_response( [
        'from_email'       => get_option( 'stadion_vog_from_email', get_bloginfo( 'admin_email' ) ),
        'template_new'     => get_option( 'stadion_vog_template_new', $this->get_default_new_template() ),
        'template_renewal' => get_option( 'stadion_vog_template_renewal', $this->get_default_renewal_template() ),
    ] );
}
```

### API Client Extension
```javascript
// Source: src/api/client.js prmApi pattern
export const prmApi = {
  // ... existing methods ...

  // VOG Settings
  getVOGSettings: () => apiClient.get('/rondo/v1/vog/settings'),
  updateVOGSettings: (settings) => apiClient.post('/rondo/v1/vog/settings', settings),

  // VOG Email sending (for Phase 121)
  sendVOGEmail: (personIds, templateType) =>
    apiClient.post('/rondo/v1/vog/send', { person_ids: personIds, template_type: templateType }),
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| mail() function | wp_mail() | WordPress 1.0+ | SMTP integration, filters |
| Custom options tables | Options API | WordPress 1.0+ | Caching, standardization |
| Manual meta_key queries | ACF get_field/update_field | ACF Pro | Type-safe, UI integration |

**Deprecated/outdated:**
- Direct PHPMailer: Use wp_mail() wrapper instead
- Custom email tables: Use post meta for per-record tracking

## Open Questions

Things that couldn't be fully resolved:

1. **Default template content**
   - What we know: Templates need {first_name} and optionally {previous_vog_date}
   - What's unclear: Exact Dutch wording for default templates
   - Recommendation: Provide sensible defaults, user can customize

2. **Email tracking granularity**
   - What we know: Need to store when VOG email was sent
   - What's unclear: Should we track each send (history) or just last send?
   - Recommendation: Start with single date (last send), add history in Phase 122 if needed

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-email-channel.php` - Existing email sending pattern
- `/Users/joostdevalk/Code/stadion/includes/class-rest-api.php` - REST API patterns, notification settings
- `/Users/joostdevalk/Code/stadion/src/pages/Settings/Settings.jsx` - Settings UI tab pattern
- https://developer.wordpress.org/reference/functions/wp_mail/ - Official wp_mail documentation
- https://developer.wordpress.org/reference/functions/get_option/ - Official Options API documentation

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/.planning/REQUIREMENTS.md` - Phase requirements
- `/Users/joostdevalk/Code/stadion/.planning/ROADMAP.md` - Milestone context

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Uses existing WordPress core features documented officially
- Architecture: HIGH - Follows established codebase patterns exactly
- Pitfalls: HIGH - Based on actual codebase issues and WordPress best practices

**Research date:** 2026-01-30
**Valid until:** 60 days (WordPress core APIs are stable)
