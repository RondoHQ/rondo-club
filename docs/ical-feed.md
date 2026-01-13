# iCal Calendar Feed

This document describes the iCal calendar subscription feature that allows users to subscribe to their important dates in external calendar applications.

## Overview

Caelis generates iCal feeds for users containing important dates. There are two types of feeds:

1. **Personal Feed** - Contains all important dates accessible to the user
2. **Workspace Feed** - Contains important dates for contacts shared with a workspace

Both feed types use a secret token for authentication, allowing calendar apps to fetch updates without requiring login credentials.

## Features

- **Token-based authentication** - No password needed for calendar apps
- **User-specific feeds** - Only shows dates you can access
- **Workspace feeds** - Subscribe to dates for all workspace contacts
- **Recurring events** - Yearly dates (like birthdays) repeat automatically
- **Real-time updates** - Calendar apps refresh periodically to get new dates
- **Universal compatibility** - Works with Apple Calendar, Google Calendar, Outlook, and any iCal-compatible app

## Feed URL Formats

### Personal Feed

```
https://your-site.com/calendar/{token}.ics
```

### Workspace Feed

```
https://your-site.com/workspace/{workspace_id}/calendar/{token}.ics
```

The token is a 64-character hexadecimal string (32 bytes) stored in user meta. The same token is used for both personal and workspace feeds.

**webcal:// Protocol:**

For one-click subscription, use the `webcal://` protocol:

```
webcal://your-site.com/calendar/{token}.ics
webcal://your-site.com/workspace/{workspace_id}/calendar/{token}.ics
```

## Implementation

### Class: `PRM_ICal_Feed`

Located in `includes/class-ical-feed.php`.

**Key Components:**

| Component | Purpose |
|-----------|---------|
| `TOKEN_META_KEY` | User meta key: `prm_ical_token` |
| `TOKEN_LENGTH` | 32 bytes (64 hex characters) |
| Personal Rewrite Rule | `^calendar/([a-f0-9]+)\.ics$` |
| Workspace Rewrite Rule | `^workspace/([0-9]+)/calendar/([a-f0-9]+)\.ics$` |
| REST Endpoints | URL retrieval and token regeneration |

### Token Management

**Token Generation:**
```php
bin2hex(random_bytes(32))
```

**Token Storage:**
```php
update_user_meta($user_id, 'prm_ical_token', $token);
```

**Token Lookup:**
```sql
SELECT user_id FROM wp_usermeta 
WHERE meta_key = 'prm_ical_token' AND meta_value = '{token}'
```

## REST API Endpoints

### Get Calendar URL

**GET** `/prm/v1/user/ical-url`

Returns the current user's iCal feed URL.

**Response:**
```json
{
  "url": "https://your-site.com/calendar/abc123...def.ics",
  "webcal_url": "webcal://your-site.com/calendar/abc123...def.ics",
  "token": "abc123...def"
}
```

The `token` field can be used to construct workspace calendar URLs on the frontend.

### Regenerate Token

**POST** `/prm/v1/user/regenerate-ical-token`

Creates a new token, invalidating the old URL.

**Response:**
```json
{
  "success": true,
  "url": "https://your-site.com/calendar/new456...xyz.ics",
  "webcal_url": "webcal://your-site.com/calendar/new456...xyz.ics",
  "message": "Your calendar URL has been regenerated. Update any calendar subscriptions with the new URL."
}
```

**Important:** Regenerating the token invalidates all existing calendar subscriptions. Users must update their calendar apps with the new URL.

## iCal Format

### Calendar Structure

```ical
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Caelis//Site Name//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Site Name - Important Dates
X-WR-TIMEZONE:UTC

[VEVENT entries...]

END:VCALENDAR
```

### Event Structure

```ical
BEGIN:VEVENT
UID:date-123@your-site.com
DTSTAMP:20250104T120000Z
DTSTART;VALUE=DATE:19850615
DTEND;VALUE=DATE:19850616
SUMMARY:John Doe's Birthday
DESCRIPTION:Related to: John Doe
URL:https://your-site.com/people/456
RRULE:FREQ=YEARLY
CATEGORIES:Birthday
END:VEVENT
```

**Field Mapping:**

| iCal Field | Source |
|------------|--------|
| `UID` | `date-{post_id}@{domain}` |
| `DTSTAMP` | Post modified date (GMT) |
| `DTSTART` | `date_value` ACF field |
| `DTEND` | Start date + 1 day |
| `SUMMARY` | Post title |
| `DESCRIPTION` | Related people names |
| `URL` | Link to first related person |
| `RRULE` | `FREQ=YEARLY` if recurring |
| `CATEGORIES` | Date type taxonomy term |

### All-Day Events

Dates are rendered as all-day events using `VALUE=DATE` format:

```ical
DTSTART;VALUE=DATE:20250615
DTEND;VALUE=DATE:20250616
```

### Recurring Events

When `is_recurring` is true, events include:

```ical
RRULE:FREQ=YEARLY
```

This makes the event repeat annually on the same day.

## Subscribing to the Feed

### Apple Calendar (macOS/iOS)

1. Open Calendar app
2. File → New Calendar Subscription (or tap Add Subscription)
3. Paste the feed URL
4. Adjust refresh interval (recommended: every day)
5. Click Subscribe

### Google Calendar

1. Open Google Calendar (web)
2. Click + next to "Other calendars"
3. Select "From URL"
4. Paste the feed URL
5. Click "Add calendar"

**Note:** Google Calendar may take several hours to initially sync.

### Microsoft Outlook

1. Open Outlook
2. File → Account Settings → Internet Calendars
3. Click "New"
4. Paste the feed URL
5. Click OK

### Other Apps

Any app supporting iCal/ICS format can subscribe using the feed URL.

## Security

### Token Security

- **Random generation** - Uses `random_bytes()` for cryptographic randomness
- **Sufficient length** - 32 bytes provides 256 bits of entropy
- **Direct DB lookup** - No timing attacks via user enumeration

### Best Practices

1. **Don't share URLs** - Treat the calendar URL as a password
2. **Regenerate if compromised** - Use the regenerate endpoint
3. **HTTPS required** - Feed URLs should always use HTTPS

### Access Control

The feed respects the same access control as the web interface:
- Only shows dates the user owns or that are shared with them
- Administrators see all dates

### Workspace Feed Security

Workspace feeds include additional security checks:
1. **Token validation** - User's iCal token must be valid
2. **Workspace existence** - Workspace must exist and be published
3. **Membership verification** - User must be a member of the workspace (any role)

If any check fails, the request is rejected with an appropriate HTTP error:
- `403 Forbidden` - Invalid token or not a workspace member
- `404 Not Found` - Workspace does not exist

## Workspace Feeds

### How It Works

Workspace feeds aggregate important dates from all contacts shared with a workspace:

1. Find all people (`person` posts) tagged with `workspace-{id}` in the `workspace_access` taxonomy
2. Query all important dates linked to those people via the `related_people` field
3. Generate iCal events for all dates found

### Accessing Workspace Feeds

The workspace calendar URL is shown on the WorkspaceDetail page in the frontend. Users can copy the URL and subscribe in their calendar app.

**URL Format:**
```
https://your-site.com/workspace/{workspace_id}/calendar/{token}.ics
```

### Calendar Name

Workspace calendars are named "Caelis - {Workspace Name}" to help users identify them in their calendar app.

## Technical Details

### Rewrite Rules

The feed uses WordPress rewrite rules:

**Personal Feed:**
```php
add_rewrite_rule(
    '^calendar/([a-f0-9]+)\.ics$',
    'index.php?prm_ical_feed=1&prm_ical_token=$matches[1]',
    'top'
);
```

**Workspace Feed:**
```php
add_rewrite_rule(
    '^workspace/([0-9]+)/calendar/([a-f0-9]+)\.ics$',
    'index.php?prm_workspace_ical=1&prm_workspace_id=$matches[1]&prm_ical_token=$matches[2]',
    'top'
);
```

**Query Variables:**
- `prm_ical_feed` - Triggers personal feed handler
- `prm_workspace_ical` - Triggers workspace feed handler
- `prm_ical_token` - User's authentication token
- `prm_workspace_id` - Workspace post ID

**Note:** After theme activation, rewrite rules are flushed to register these rules.

### Headers

```php
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="caelis.ics"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
```

### Text Escaping

Special characters are escaped per iCal spec:

| Character | Escaped |
|-----------|---------|
| `\` | `\\` |
| `,` | `\,` |
| `;` | `\;` |
| newline | `\n` |

## Troubleshooting

### Calendar not updating

- Most calendar apps refresh every 24 hours
- Try removing and re-adding the subscription
- Check that the feed URL is accessible

### 404 Error

- Rewrite rules may need flushing
- Visit Settings → Permalinks in WordPress admin (saves/flushes rules)

### Events not appearing

- Verify dates have the `date_value` field set
- Check access control - user may not have access to those dates

## Related Documentation

- [Data Model](./data-model.md) - Important date post type
- [Reminders](./reminders.md) - Email reminder system
- [Access Control](./access-control.md) - How date visibility works

