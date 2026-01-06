# Daily Digest Reminders

This document describes the daily digest reminder system that notifies users about upcoming important dates via multiple channels.

## Overview

Caelis includes an automated reminder system that:
- Runs daily via WordPress cron
- Sends a **daily digest** email/notification listing:
  - Important dates **today**
  - Important dates **tomorrow**
  - Important dates for the **rest of the week** (days 3-7)
- Supports multiple notification channels (Email, Slack)
- Respects user preferences for which channels to use
- Notifies all users who have access to the related people

## How It Works

### Daily Digest Flow

1. **Daily cron job** runs `process_daily_reminders()`
2. Gets all users who should receive reminders (users with dates they can access)
3. For each user, generates a weekly digest (today/tomorrow/rest of week)
4. Sends digest via all enabled notification channels for that user

### Digest Format

Each user receives one notification per day containing:

- **TODAY** - Dates occurring today
- **TOMORROW** - Dates occurring tomorrow
- **THIS WEEK** - Dates occurring in the next 5 days (days 3-7)

**Example:** If today is Monday, June 15th:
- **TODAY**: John's Birthday (June 15)
- **TOMORROW**: Mom's Birthday (June 16)
- **THIS WEEK**: Friend's Birthday (June 18), Wedding Anniversary (June 20)

## Implementation

### Classes

**PRM_Reminders** (`includes/class-reminders.php`)
- Main orchestrator for reminder processing
- Generates weekly digests per user
- Coordinates notification channels

**PRM_Notification_Channel** (`includes/class-notification-channels.php`)
- Abstract base class for notification channels
- Defines interface: `send()`, `is_enabled_for_user()`, `get_user_config()`

**PRM_Email_Channel** (`includes/class-notification-channels.php`)
- Email notification implementation
- Formats digest as plain text email

**PRM_Slack_Channel** (`includes/class-notification-channels.php`)
- Slack webhook notification implementation
- Formats digest as Slack message blocks

### Cron Configuration

**Cron Hook:** `prm_daily_reminder_check`

**Scheduling** (in `functions.php` during theme activation):
```php
if (!wp_next_scheduled('prm_daily_reminder_check')) {
    wp_schedule_event(time(), 'daily', 'prm_daily_reminder_check');
}
```

## Key Methods

### `get_weekly_digest($user_id)`

Returns weekly digest for a specific user.

**Parameters:**
- `$user_id` - User ID

**Returns:** Array with three keys:
```php
[
    'today' => [
        [
            'id' => 123,
            'title' => "John's Birthday",
            'next_occurrence' => '2025-06-15',
            'related_people' => [...],
            // ... more fields
        ],
    ],
    'tomorrow' => [...],
    'rest_of_week' => [...],
]
```

### `process_daily_reminders()`

Main cron handler:
1. Gets all users who should receive reminders
2. For each user, generates weekly digest
3. Sends digest via all enabled channels
4. Also runs `update_expired_work_history()`

### `get_upcoming_reminders($days_ahead)`

Legacy method for backward compatibility. Returns all reminders within specified window (used by REST API for dashboard/UI).

**Parameters:**
- `$days_ahead` - Number of days to look ahead (default: 30)

### `calculate_next_occurrence($date_string, $is_recurring)`

Calculates when a date will next occur.

**Non-recurring dates:**
- Returns the date if it's today or in the future
- Returns `null` if it has passed

**Recurring dates:**
- Calculates occurrence for current year
- If already passed, returns next year's occurrence

### `update_expired_work_history()`

Background maintenance task that runs with reminders:
- Finds people with `is_current = true` work history entries
- Checks if `end_date` has passed
- Automatically sets `is_current = false`

## Notification Channels

### Email Channel

**User Meta:** `caelis_notification_channels` (array containing `'email'`)

**Email Format:**
```
Subject: [Caelis] Your Important Dates - June 15, 2025

Hello User,

Here are your important dates for this week:

TODAY
• John's Birthday - June 15, 2025
  John Doe
• Wedding Anniversary - June 15, 2025
  Jane Doe, John Doe

TOMORROW
• Mom's Birthday - June 16, 2025
  Mom

THIS WEEK
• Friend's Birthday - June 18, 2025
  Friend
• Another Date - June 20, 2025
  Person

Visit Caelis to see more details.

https://your-site.com
```

### Slack Channel

**User Meta:**
- `caelis_notification_channels` (array containing `'slack'`)
- `caelis_slack_webhook` (Slack webhook URL)

**Slack Format:**
- Uses Slack Block Kit format
- Header block with date
- Section blocks for each date category
- Footer with link to Caelis

**Webhook Configuration:**
- Users configure their own Slack webhook URL in Settings
- Webhook is tested when saved
- If webhook is removed, Slack channel is automatically disabled

## User Preferences

Users can enable/disable notification channels in Settings:

1. **Email** - Always available (default enabled)
2. **Slack** - Requires webhook URL configuration

**User Meta Keys:**
- `caelis_notification_channels` - Array of enabled channels: `['email', 'slack']`
- `caelis_slack_webhook` - Slack webhook URL (optional)

## REST API Integration

### Get Upcoming Reminders

**GET** `/prm/v1/reminders`

**Parameters:**
- `days_ahead` - Days to look ahead (default: 30, max: 365)

**Response:**
```json
[
  {
    "id": 123,
    "title": "John's Birthday",
    "date_value": "1985-06-15",
    "next_occurrence": "2025-06-15",
    "days_until": 5,
    "is_recurring": true,
    "date_type": ["birthday"],
    "related_people": [
      { "id": 456, "name": "John Doe", "thumbnail": "..." }
    ]
  }
]
```

### Trigger Reminders Manually (Admin Only)

**POST** `/prm/v1/reminders/trigger`

**Response:**
```json
{
  "success": true,
  "message": "Processed 5 user(s), sent 8 notification(s).",
  "users_processed": 5,
  "notifications_sent": 8
}
```

### Get Notification Channels

**GET** `/prm/v1/user/notification-channels`

**Response:**
```json
{
  "channels": ["email", "slack"],
  "slack_webhook": "https://hooks.slack.com/services/..."
}
```

### Update Notification Channels

**POST** `/prm/v1/user/notification-channels`

**Body:**
```json
{
  "channels": ["email", "slack"]
}
```

### Update Slack Webhook

**POST** `/prm/v1/user/slack-webhook`

**Body:**
```json
{
  "webhook": "https://hooks.slack.com/services/..."
}
```

**Note:** Webhook is tested when saved. If test fails, webhook is not saved.

## Configuration

### Date Settings

Each important date can configure:

| Field | Purpose |
|-------|---------|
| `is_recurring` | Whether date repeats yearly |
| `date_value` | The date value (Y-m-d format) |

**Note:** The `reminder_days_before` field has been removed. All dates are included in the daily digest if they occur within the next 7 days.

### Server Requirements

- WordPress cron must be functioning
- Email (wp_mail) must be configured for email channel
- Slack webhook URL required for Slack channel
- Consider using SMTP plugin for email reliability

## Testing Reminders

### Manual Trigger (Admin Only)

Admins can manually trigger reminders from Settings → Administration → "Trigger Reminder Emails" button.

This sends reminders for all users who have dates occurring today.

### Check Cron Status

```bash
wp cron event list
```

Look for `prm_daily_reminder_check` in the output.

### Verify Email

Test that `wp_mail()` works:

```php
wp_mail('test@example.com', 'Test Subject', 'Test message');
```

### Test Slack Webhook

When configuring a Slack webhook in Settings, it's automatically tested. You can also test manually:

```bash
curl -X POST https://hooks.slack.com/services/YOUR/WEBHOOK/URL \
  -H 'Content-Type: application/json' \
  -d '{"text":"Test message"}'
```

## Troubleshooting

### Emails Not Sending

1. **Check cron is running** - WordPress cron requires page visits or server cron
2. **Check email configuration** - Use an SMTP plugin like WP Mail SMTP
3. **Check spam folder** - Emails may be filtered
4. **Check user emails** - Users must have valid email addresses
5. **Check user preferences** - User must have email channel enabled

### Slack Notifications Not Sending

1. **Check webhook URL** - Must be valid Slack webhook URL
2. **Check webhook test** - Webhook is tested when saved
3. **Check user preferences** - User must have Slack channel enabled
4. **Check Slack workspace** - Webhook must be active in Slack

### Wrong Reminder Dates

1. **Check timezone** - Uses WordPress timezone (`wp_timezone()`)
2. **Check recurring setting** - Non-recurring past dates won't appear
3. **Check date format** - Dates must be in Y-m-d format

### Cron Not Running

Set up a real server cron job:

```bash
# crontab -e
*/15 * * * * wget -q -O - https://your-site.com/wp-cron.php?doing_wp_cron
```

Disable WordPress's pseudo-cron in `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

## Adding New Notification Channels

To add a new notification channel:

1. Create a new class extending `PRM_Notification_Channel` in `includes/class-notification-channels.php`
2. Implement required methods: `send()`, `is_enabled_for_user()`, `get_user_config()`, `get_channel_id()`, `get_channel_name()`
3. Register the channel in `PRM_Reminders` constructor
4. Add UI toggle in Settings page
5. Add REST API endpoint if needed for configuration

**Example:**
```php
class PRM_Telegram_Channel extends PRM_Notification_Channel {
    public function get_channel_id() {
        return 'telegram';
    }
    
    public function get_channel_name() {
        return __('Telegram', 'personal-crm');
    }
    
    // ... implement other methods
}
```

## Related Documentation

- [Data Model](./data-model.md) - Important date post type
- [iCal Feed](./ical-feed.md) - Calendar subscription
- [Access Control](./access-control.md) - User permissions
