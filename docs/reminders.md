# Email Reminders

This document describes the email reminder system that notifies users about upcoming important dates.

## Overview

Caelis includes an automated reminder system that:
- Runs daily via WordPress cron
- Sends email notifications for upcoming dates
- Respects the `reminder_days_before` setting on each date
- Notifies all users who have access to the related people

## How It Works

### Reminder Flow

1. **Daily cron job** runs `process_daily_reminders()`
2. Fetches dates where the reminder date is today
3. Determines which users should be notified
4. Sends personalized email to each user

### Reminder Timing

Each important date has a `reminder_days_before` field (default: 7 days):

| Setting | Behavior |
|---------|----------|
| `0` | Reminder on the same day as the event |
| `7` | Reminder 7 days before the event |
| `30` | Reminder 30 days before the event |

**Example:** Birthday on January 15th with `reminder_days_before = 7`:
- Reminder email sent on January 8th

## Implementation

### Class: `PRM_Reminders`

Located in `includes/class-reminders.php`.

### Cron Configuration

**Cron Hook:** `prm_daily_reminder_check`

**Custom Schedule:**
```php
'prm_twice_daily' => [
    'interval' => 12 * HOUR_IN_SECONDS,
    'display'  => 'Twice Daily',
]
```

**Scheduling** (in `functions.php` during theme activation):
```php
if (!wp_next_scheduled('prm_daily_reminder_check')) {
    wp_schedule_event(time(), 'daily', 'prm_daily_reminder_check');
}
```

## Key Methods

### `get_upcoming_reminders($days_ahead)`

Returns all reminders within the specified window.

**Parameters:**
- `$days_ahead` - Number of days to look ahead (default: 30)

**Returns:** Array of reminder objects:
```php
[
    'id'              => 123,
    'title'           => "John's Birthday",
    'date_value'      => '1985-06-15',
    'next_occurrence' => '2025-06-15',
    'remind_on'       => '2025-06-08',
    'days_until'      => 5,
    'is_recurring'    => true,
    'date_type'       => ['birthday'],
    'related_people'  => [
        ['id' => 456, 'name' => 'John Doe', 'thumbnail' => '...']
    ],
]
```

### `calculate_next_occurrence($date_string, $is_recurring)`

Calculates when a date will next occur.

**Non-recurring dates:**
- Returns the date if it's today or in the future
- Returns `null` if it has passed

**Recurring dates:**
- Calculates occurrence for current year
- If already passed, returns next year's occurrence

### `process_daily_reminders()`

Main cron handler:
1. Gets reminders due today (`days_ahead = 0`)
2. Sends notifications for each
3. Also runs `update_expired_work_history()`

### `update_expired_work_history()`

Background maintenance task that runs with reminders:
- Finds people with `is_current = true` work history entries
- Checks if `end_date` has passed
- Automatically sets `is_current = false`

### `send_reminder_email($user_id, $reminder)`

Sends the actual email notification.

**Email Format:**
```
Subject: [Site Name] Reminder: John's Birthday

Hello User,

This is a reminder about: John's Birthday

Date: June 15, 2025
People: John Doe

Visit Caelis to see more details.

https://your-site.com
```

## User Notification Logic

### Who Gets Notified

For each reminder, notifications go to:

1. **Post authors** - Creators of the related people posts

```php
// Collect user IDs
$user_ids = [];
foreach ($related_people as $person) {
    $post = get_post($person['id']);
    if ($post) {
        $user_ids[] = (int) $post->post_author;
    }
}
```

### Access-Filtered Reminders

The `get_user_reminders($user_id, $days_ahead)` method returns only reminders where the user can access at least one related person.

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

### Dashboard Integration

The dashboard shows upcoming reminders (next 14 days) via the `/prm/v1/dashboard` endpoint.

## Configuration

### Date Settings

Each important date can configure:

| Field | Purpose |
|-------|---------|
| `reminder_days_before` | When to send reminder (0-365 days) |
| `is_recurring` | Whether date repeats yearly |

### Server Requirements

- WordPress cron must be functioning
- Email (wp_mail) must be configured
- Consider using SMTP plugin for reliability

## Testing Reminders

### Manual Trigger

To test the reminder system:

```php
// In wp-cli or custom code
$reminders = new PRM_Reminders();
$reminders->process_daily_reminders();
```

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

## Troubleshooting

### Emails Not Sending

1. **Check cron is running** - WordPress cron requires page visits or server cron
2. **Check email configuration** - Use an SMTP plugin like WP Mail SMTP
3. **Check spam folder** - Emails may be filtered
4. **Check user emails** - Users must have valid email addresses

### Wrong Reminder Dates

1. **Check timezone** - Uses WordPress timezone (`wp_timezone()`)
2. **Check `reminder_days_before`** - Verify the setting on the date
3. **Check recurring setting** - Non-recurring past dates won't trigger

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

## Related Documentation

- [Data Model](./data-model.md) - Important date post type
- [iCal Feed](./ical-feed.md) - Calendar subscription
- [Access Control](./access-control.md) - User permissions

