# Daily Digest Reminders

This document describes the daily digest reminder system that notifies users about upcoming important dates via multiple channels.

## Overview

Caelis includes an automated reminder system that:
- Runs via **per-user WordPress cron jobs** at each user's preferred notification time
- Sends a **daily digest** email/notification listing:
  - Important dates **today** + todos due today (including overdue)
  - Important dates **tomorrow** + todos due tomorrow
  - Important dates for the **rest of the week** (days 3-7) + todos due in that period
- Supports multiple notification channels (Email, Slack)
- Respects user preferences for which channels to use
- Notifies all users who have access to the related people

## How It Works

### Per-User Cron Scheduling

**Assumption:** A real server cron job triggers WordPress cron every 5 minutes:
```bash
*/5 * * * * wget -q -O - https://cael.is/wp-cron.php?doing_wp_cron
```

Each user has an individual cron job scheduled at their preferred notification time:
- **Cron Hook:** `prm_user_reminder` (with user ID as argument)
- **Schedule:** Daily recurring at user's preferred time (default: 09:00 UTC)
- **Arguments:** `[$user_id]`

### Scheduling Events

Cron jobs are scheduled when:
- Theme is activated (for all existing users with dates)
- User updates their notification time preference in Settings

Cron jobs are unscheduled when:
- User changes their notification time (old time is replaced with new)
- User account is deleted (via WordPress `delete_user` hook)
- Theme is deactivated (all user cron jobs cleared)

### Daily Digest Flow

1. **Per-user cron job** triggers `process_user_reminders($user_id)` at user's preferred time
2. Generates weekly digest for that user (today/tomorrow/rest of week)
3. Sends digest via all enabled notification channels for that user
4. Work history update runs once per day (via transient check)

### Digest Format

Each user receives one notification per day containing:

- **TODAY** - Dates occurring today + todos due today (including overdue)
- **TOMORROW** - Dates occurring tomorrow + todos due tomorrow
- **THIS WEEK** - Dates occurring in the next 5 days (days 3-7) + todos due in that period

**Example:** If today is Monday, June 15th:
- **TODAY**: John's Birthday (June 15), ☐ Call about project → John Doe
- **TOMORROW**: Mom's Birthday (June 16), ☐ Send gift → Mom
- **THIS WEEK**: Friend's Birthday (June 18), ☐ Schedule meeting (Jun 18) → Friend

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

**Per-User Cron Hook:** `prm_user_reminder` (with user ID argument)

**Scheduling** (in `PRM_Reminders` class):
```php
// Schedule individual user's cron at their preferred time
$reminders->schedule_user_reminder($user_id);

// Schedule all users during theme activation
$reminders->schedule_all_user_reminders();
```

**Legacy Cron Hook:** `prm_daily_reminder_check` (deprecated, kept for backward compatibility)

## Key Methods

### `schedule_user_reminder($user_id)`

Schedules a cron job for a specific user at their preferred notification time.

**Parameters:**
- `$user_id` - User ID

**Returns:** `true` on success, `WP_Error` on failure

**Behavior:**
- Gets user's preferred time from `caelis_notification_time` user meta (default: `09:00`)
- Calculates next occurrence (if time passed today, schedules for tomorrow)
- Unschedules any existing cron for this user
- Schedules new daily recurring cron with `prm_user_reminder` hook

### `unschedule_user_reminder($user_id)`

Unschedules a user's reminder cron job.

**Parameters:**
- `$user_id` - User ID

**Returns:** `true` on success

### `schedule_all_user_reminders()`

Schedules reminder cron jobs for all users who should receive reminders.

**Returns:** Number of users scheduled

**Usage:** Called during theme activation and by admin "Reschedule cron jobs" button.

### `process_user_reminders($user_id)`

Processes reminders for a specific user (called by per-user cron).

**Parameters:**
- `$user_id` - User ID

**Behavior:**
1. Verifies user exists
2. Gets weekly digest for user
3. Sends via all enabled notification channels
4. Runs `update_expired_work_history()` once per day (via transient check)

### `process_daily_reminders()` [DEPRECATED]

**DEPRECATED:** Use `process_user_reminders()` with per-user cron jobs instead.

This method is kept for backward compatibility. When called, it:
- Calls `schedule_all_user_reminders()` to reschedule per-user cron jobs
- Runs `update_expired_work_history()`

### `get_weekly_digest($user_id)`

Returns weekly digest for a specific user, including both important dates and todos.

**Parameters:**
- `$user_id` - User ID

**Returns:** Array with four keys:
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
    'todos' => [
        'today' => [
            [
                'id' => 456,
                'content' => 'Call about project',
                'due_date' => '2025-06-15',
                'person_id' => 789,
                'person_name' => 'John Doe',
                'is_overdue' => false,
            ],
        ],
        'tomorrow' => [...],
        'rest_of_week' => [...],
    ],
]
```

### `get_all_users_to_notify()`

Gets all users who should receive reminders (users who have created people with important dates).

**Returns:** Array of user IDs

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
- Uses transient to ensure it only runs once per day across all users

## Notification Channels

### Email Channel

**User Meta:** `caelis_notification_channels` (array containing `'email'`)

**Email Format:**
```
Subject: [Caelis] Your Reminders & Todos - June 15, 2025

Hello User,

Here are your important dates and to-dos for this week:

📅 TODAY
• John's Birthday - June 15, 2025
  John Doe
• Wedding Anniversary - June 15, 2025
  Jane Doe, John Doe
☐ Call about project (overdue)
  → John Doe

📅 TOMORROW
• Mom's Birthday - June 16, 2025
  Mom
☐ Send gift
  → Mom

📅 THIS WEEK
• Friend's Birthday - June 18, 2025
  Friend
☐ Schedule meeting (June 18, 2025)
  → Friend

Visit Caelis to see more details.

https://your-site.com
```

### Slack Channel

**User Meta:**
- `caelis_notification_channels` (array containing `'slack'`)
- `caelis_slack_bot_token` (Bot token from OAuth)
- `caelis_slack_workspace_id` (Workspace ID)
- `caelis_slack_workspace_name` (Workspace name)
- `caelis_slack_user_id` (User's Slack ID for DMs)
- `caelis_slack_targets` (Array of channel/user IDs to send to)

**Slack Format:**
- Uses Slack Block Kit format with `chat.postMessage` API
- Section blocks for each date category (Today, Tomorrow, Rest of the week)
- Links person names to their Caelis profile

**OAuth Configuration:**
- Users connect Slack via OAuth flow in Settings
- Bot token stored for API calls
- User can select which channels/users to receive notifications

## User Preferences

Users can enable/disable notification channels in Settings:

1. **Email** - Always available (default enabled)
2. **Slack** - Requires OAuth connection

**User Meta Keys:**
- `caelis_notification_channels` - Array of enabled channels: `['email', 'slack']`
- `caelis_notification_time` - Preferred notification time in HH:MM format, 5-minute increments (default: `09:00`)
- `caelis_slack_bot_token` - Slack bot token (from OAuth)
- `caelis_slack_targets` - Array of Slack channel/user IDs for notifications

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

### Reschedule All Cron Jobs (Admin Only)

**POST** `/prm/v1/reminders/reschedule-cron`

Reschedules all user reminder cron jobs based on their notification time preferences.

**Response:**
```json
{
  "success": true,
  "message": "Successfully rescheduled reminder cron jobs for 5 user(s).",
  "users_scheduled": 5
}
```

### Get Cron Status (Admin Only)

**GET** `/prm/v1/reminders/cron-status`

Returns status of all user reminder cron jobs.

**Response:**
```json
{
  "total_users": 5,
  "scheduled_users": 5,
  "users": [
    {
      "user_id": 1,
      "display_name": "Admin User",
      "next_run": "2026-01-08 09:00:00",
      "next_run_timestamp": 1736326800
    }
  ],
  "current_time": "2026-01-07 15:30:00",
  "current_timestamp": 1736263800,
  "legacy_cron_scheduled": false,
  "legacy_next_run": null
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

### Update Notification Time

**POST** `/prm/v1/user/notification-time`

**Body:**
```json
{
  "time": "09:00"
}
```

**Note:** When notification time is updated, the user's cron job is automatically rescheduled to the new time.

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

Look for `prm_user_reminder` events in the output. Each user should have their own scheduled event.

**Check specific user's cron (PHP):**
```php
$user_id = 123;
$next_run = wp_next_scheduled('prm_user_reminder', [$user_id]);
if ($next_run) {
    echo "User $user_id next reminder: " . date('Y-m-d H:i:s', $next_run);
} else {
    echo "No cron scheduled for user $user_id";
}
```

### Verify Email

Test that `wp_mail()` works:

```php
wp_mail('test@example.com', 'Test Subject', 'Test message');
```

### Test Slack Connection

Slack connection is established via OAuth in Settings. After connecting:
1. Enable the Slack channel toggle
2. Select notification targets (channels/users)
3. Use "Trigger reminders" button to test

## Troubleshooting

### Emails Not Sending

1. **Check cron is running** - WordPress cron requires page visits or server cron
2. **Check email configuration** - Use an SMTP plugin like WP Mail SMTP
3. **Check spam folder** - Emails may be filtered
4. **Check user emails** - Users must have valid email addresses
5. **Check user preferences** - User must have email channel enabled

### Slack Notifications Not Sending

1. **Check OAuth connection** - User must have connected Slack via OAuth
2. **Check user preferences** - User must have Slack channel enabled
3. **Check notification targets** - User must have selected channels/users to receive notifications
4. **Check bot permissions** - Slack app needs `chat:write` and `chat:write.public` scopes

### Wrong Reminder Dates

1. **Check timezone** - Uses WordPress timezone (`wp_timezone()`)
2. **Check recurring setting** - Non-recurring past dates won't appear
3. **Check date format** - Dates must be in Y-m-d format

### Cron Not Running

**Server cron requirement:** Caelis requires a real server cron job that triggers WordPress cron every 5 minutes for precise notification timing:

```bash
# crontab -e
*/5 * * * * wget -q -O - https://cael.is/wp-cron.php?doing_wp_cron
```

Disable WordPress's pseudo-cron in `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

### Reschedule User Cron Jobs

If cron jobs are not running for users, admins can reschedule all user cron jobs from Settings → Administration → "Reschedule cron jobs" button.

**Via WP-CLI:**
```bash
# Process reminders for all users (ignores timing)
wp prm reminders trigger --force

# Process reminders for specific user
wp prm reminders trigger --user=123
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
