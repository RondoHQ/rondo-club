# Calendar Integration Implementation Plan

**Stadion CRM - CalDAV & Google Calendar Integration for Meeting Matching**

---

## Scope

- **Goal:** Auto-log past meetings AND show upcoming meetings on contact profiles
- **Matching:** Email-first, with fallback to fuzzy name matching
- **Scope:** Per-user calendar connections (each user connects their own accounts)
- **Providers:** Google Calendar (OAuth2), generic CalDAV (for iCloud, Outlook, Fastmail, Nextcloud, etc.)

---

## Architecture Decision: Dual Approach

| Provider | Protocol | Rationale |
|----------|----------|-----------|
| Google Calendar | Google Calendar API (REST) | Better features, OAuth2 native, well-documented, JSON responses |
| Other calendars | CalDAV | Standard protocol works with iCloud, Outlook, Fastmail, Nextcloud, etc. |

**Why not CalDAV for Google?**
- Google's CalDAV is v2 (deprecated path), their REST API is v3
- CalDAV has limited documentation for Google
- OAuth2 is easier with REST API
- Google-specific features (Meet links, attendee details) only via REST API

---

## 1. Data Model (WordPress Native)

### 1.1 Calendar Connections → User Meta

Store calendar connections in **user meta** (one user can have multiple connections):

```php
// Meta key: _stadion_calendar_connections
// Value: array of connection objects
[
    [
        'id' => 'conn_abc123',              // Unique ID (uniqid)
        'provider' => 'google',             // google, caldav, icloud, outlook365
        'name' => 'Work Calendar',          // User-friendly name
        'calendar_id' => 'primary',         // Provider-specific calendar ID
        'credentials' => '...encrypted...', // OAuth tokens or CalDAV creds
        'sync_enabled' => true,
        'auto_log' => true,                 // Auto-create activities
        'sync_from_days' => 90,             // How far back to sync
        'last_sync' => '2026-01-15T10:00:00Z',
        'last_error' => null,
        'created_at' => '2026-01-01T00:00:00Z',
    ],
    // ... more connections
]
```

**Functions:**
- `get_user_meta($user_id, '_stadion_calendar_connections', true)`
- `update_user_meta($user_id, '_stadion_calendar_connections', $connections)`

### 1.2 Calendar Events → Custom Post Type

New CPT: `calendar_event`

```php
register_post_type('calendar_event', [
    'public' => false,
    'show_in_rest' => false,  // Custom endpoints only
    'supports' => ['title', 'author'],
    'capability_type' => 'post',
    'map_meta_cap' => true,
]);
```

**Post fields:**
- `post_title` → Event title
- `post_content` → Event description
- `post_author` → User who owns this event (synced from their calendar)
- `post_status` → 'publish' (active) or 'trash' (deleted from calendar)
- `post_date` → Event start time

**Post meta:**

| Meta Key | Description |
|----------|-------------|
| `_connection_id` | Reference to connection in user meta |
| `_event_uid` | Provider's unique event ID (for upsert) |
| `_calendar_id` | Which calendar this came from |
| `_start_time` | Event start (ISO 8601) |
| `_end_time` | Event end (ISO 8601) |
| `_all_day` | Boolean |
| `_location` | Event location |
| `_meeting_url` | Google Meet / Zoom / Teams link |
| `_organizer_email` | Event organizer |
| `_attendees` | JSON: `[{email, name, status}]` |
| `_matched_people` | JSON: `[{person_id, match_type, confidence}]` |
| `_activity_id` | If auto-logged, reference to activity post |
| `_raw_data` | Full event data from provider (JSON) |

**Querying events:**
```php
// Get upcoming events for a user
$events = get_posts([
    'post_type' => 'calendar_event',
    'author' => $user_id,
    'meta_query' => [
        [
            'key' => '_start_time',
            'value' => current_time('mysql'),
            'compare' => '>=',
            'type' => 'DATETIME',
        ],
    ],
    'orderby' => 'meta_value',
    'meta_key' => '_start_time',
    'order' => 'ASC',
]);

// Get events matching a person
$events = get_posts([
    'post_type' => 'calendar_event',
    'author' => $user_id,
    'meta_query' => [
        [
            'key' => '_matched_people',
            'value' => '"person_id":' . $person_id,
            'compare' => 'LIKE',
        ],
    ],
]);
```

### 1.3 Why This Approach?

| Aspect | User Meta (Connections) | CPT (Events) |
|--------|------------------------|--------------|
| Volume | Few per user (1-5) | Many per user (hundreds) |
| Queries | Simple lookup by user | Complex filtering by date, person |
| Caching | WP object cache | WP Query cache |
| REST | Custom endpoints | Can use WP REST if needed |
| Permissions | User owns their own | `post_author` = owner |

---

## 2. Contact Matching Logic

### 2.1 Matching Algorithm

```
For each event attendee:
  1. Try exact email match against contact_info emails
  2. If no match, try normalized email match (lowercase, trim)
  3. If still no match AND name exists, try fuzzy name match:
     a. Exact full name match (first + last)
     b. Partial match (first name only if unique)
     c. Levenshtein distance < 2 for typos
  4. Store match with confidence score and match_type
```

### 2.2 Match Types

| Type | Description | Confidence |
|------|-------------|------------|
| email_exact | Exact email match | 100% |
| email_normalized | Lowercase/trimmed match | 95% |
| name_exact | Full name exact match | 80% |
| name_partial | First name only (if unique) | 60% |
| name_fuzzy | Levenshtein distance match | 50% |

### 2.3 Matching Optimization

For large contact databases, use WordPress transients to cache email→person lookups:

```php
// Build lookup cache (refresh on contact save)
function stadion_build_email_lookup_cache($user_id) {
    $people = get_posts([
        'post_type' => 'person',
        'author' => $user_id,
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $lookup = [];
    foreach ($people as $person_id) {
        $contact_info = get_field('contact_info', $person_id);
        foreach ($contact_info as $info) {
            if ($info['contact_type'] === 'email') {
                $email = strtolower(trim($info['contact_value']));
                $lookup[$email] = $person_id;
            }
        }
    }

    set_transient("stadion_email_lookup_{$user_id}", $lookup, DAY_IN_SECONDS);
    return $lookup;
}
```

---

## 3. REST API Endpoints

### 3.1 Calendar Connections

```
GET    /stadion/v1/calendar/connections              # List user's connections
POST   /stadion/v1/calendar/connections              # Add new connection
GET    /stadion/v1/calendar/connections/{id}         # Get connection details
PUT    /stadion/v1/calendar/connections/{id}         # Update connection settings
DELETE /stadion/v1/calendar/connections/{id}         # Remove connection

POST   /stadion/v1/calendar/connections/{id}/sync    # Trigger manual sync
GET    /stadion/v1/calendar/connections/{id}/status  # Get sync status
```

### 3.2 OAuth Flows

```
GET    /stadion/v1/calendar/auth/google              # Initiate Google OAuth
GET    /stadion/v1/calendar/auth/google/callback     # OAuth callback (redirect)
POST   /stadion/v1/calendar/auth/caldav/test         # Test CalDAV credentials
```

### 3.3 Events & Matching

```
GET    /stadion/v1/calendar/events                   # List cached events
       ?from=2026-01-01&to=2026-02-01            # Date range filter
       &person_id=123                            # Filter by matched person

GET    /stadion/v1/people/{id}/meetings              # Get meetings for a person
       ?upcoming=true                            # Only future meetings
       ?past=true                                # Only past meetings
       &limit=10

POST   /stadion/v1/calendar/events/{id}/log          # Manually log event as activity
POST   /stadion/v1/calendar/events/{id}/match        # Manually match person to event
DELETE /stadion/v1/calendar/events/{id}/match/{pid}  # Remove person match
```

### 3.4 Example Responses

```json
// GET /stadion/v1/people/123/meetings?upcoming=true
{
  "upcoming": [
    {
      "id": 456,
      "title": "Quarterly Review",
      "start_time": "2026-01-20T14:00:00Z",
      "end_time": "2026-01-20T15:00:00Z",
      "location": "Google Meet",
      "meeting_url": "https://meet.google.com/abc-xyz",
      "match_type": "email_exact",
      "matched_attendee": "john@example.com",
      "other_attendees": ["jane@company.com", "bob@company.com"],
      "calendar": "Work Calendar"
    }
  ],
  "past": [],
  "total_upcoming": 3,
  "total_past": 12
}
```

---

## 4. Provider Implementations

### 4.1 Google Calendar (OAuth2 + REST API)

**Setup Requirements:**
- Google Cloud Console project
- OAuth 2.0 credentials (client ID, client secret)
- Authorized redirect URI
- Scopes: `https://www.googleapis.com/auth/calendar.readonly`

**OAuth Flow:**
1. User clicks "Connect Google Calendar"
2. Redirect to Google consent screen
3. Google redirects back with auth code
4. Exchange code for access + refresh tokens
5. Store encrypted tokens in `credentials` column
6. Fetch calendar list, let user select which to sync

**Sync Process:**
```php
class STADION_Google_Calendar_Provider {
    public function sync($user_id, $connection) {
        $client = $this->get_authenticated_client($connection);
        $service = new Google_Service_Calendar($client);

        $events = $service->events->listEvents($connection['calendar_id'], [
            'timeMin' => $this->get_sync_start_date($connection),
            'timeMax' => date('c', strtotime('+30 days')),
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ]);

        foreach ($events->getItems() as $event) {
            $this->upsert_event($user_id, $connection, $event);
        }
    }

    protected function upsert_event($user_id, $connection, $google_event) {
        // Check if event already exists
        $existing = get_posts([
            'post_type' => 'calendar_event',
            'author' => $user_id,
            'meta_query' => [
                ['key' => '_event_uid', 'value' => $google_event->getId()],
                ['key' => '_connection_id', 'value' => $connection['id']],
            ],
            'posts_per_page' => 1,
        ]);

        $post_data = [
            'post_type' => 'calendar_event',
            'post_title' => $google_event->getSummary(),
            'post_content' => $google_event->getDescription() ?? '',
            'post_author' => $user_id,
            'post_status' => 'publish',
            'post_date' => $this->parse_event_time($google_event->getStart()),
        ];

        if ($existing) {
            $post_data['ID'] = $existing[0]->ID;
            wp_update_post($post_data);
            $post_id = $existing[0]->ID;
        } else {
            $post_id = wp_insert_post($post_data);
        }

        // Update meta
        update_post_meta($post_id, '_connection_id', $connection['id']);
        update_post_meta($post_id, '_event_uid', $google_event->getId());
        update_post_meta($post_id, '_start_time', $this->parse_event_time($google_event->getStart()));
        update_post_meta($post_id, '_end_time', $this->parse_event_time($google_event->getEnd()));
        update_post_meta($post_id, '_location', $google_event->getLocation());
        update_post_meta($post_id, '_meeting_url', $this->extract_meeting_url($google_event));
        update_post_meta($post_id, '_attendees', wp_json_encode($this->extract_attendees($google_event)));
        update_post_meta($post_id, '_organizer_email', $google_event->getOrganizer()?->getEmail());

        // Run matching
        $matcher = new STADION_Calendar_Matcher();
        $matches = $matcher->match_attendees($user_id, $this->extract_attendees($google_event));
        update_post_meta($post_id, '_matched_people', wp_json_encode($matches));

        return $post_id;
    }

    protected function extract_attendees($event) {
        return array_map(fn($a) => [
            'email' => $a->getEmail(),
            'name' => $a->getDisplayName(),
            'status' => $a->getResponseStatus(),
        ], $event->getAttendees() ?? []);
    }
}
```

### 4.2 Generic CalDAV Provider

**Setup Requirements:**
- CalDAV server URL (e.g., `https://caldav.icloud.com`)
- Username (often email)
- App-specific password (for iCloud, Google legacy, etc.)

**Libraries:**
- `sabre/dav` - Most popular PHP CalDAV library
- Or lightweight: direct HTTP requests with iCalendar parsing

**Sync Process:**
```php
class STADION_CalDAV_Provider {
    public function sync($connection) {
        $client = new \Sabre\DAV\Client([
            'baseUri' => $connection->get_server_url(),
            'userName' => $connection->get_username(),
            'password' => $connection->get_decrypted_password(),
        ]);

        // REPORT query for events in date range
        $response = $client->request('REPORT', $connection->calendar_id,
            $this->build_calendar_query_xml($start, $end)
        );

        $events = $this->parse_icalendar_response($response);
        foreach ($events as $event) {
            $this->upsert_event($connection, $event);
        }
    }

    protected function parse_icalendar_response($response) {
        // Parse VCALENDAR/VEVENT components
        // Extract: SUMMARY, DTSTART, DTEND, ATTENDEE, ORGANIZER, LOCATION
    }
}
```

### 4.3 Provider-Specific Notes

| Provider | URL Pattern | Auth Notes |
|----------|-------------|------------|
| iCloud | `https://caldav.icloud.com` | Requires app-specific password |
| Google (CalDAV) | `https://apidata.googleusercontent.com/caldav/v2` | OAuth2 required |
| Outlook 365 | `https://outlook.office365.com/caldav/calendar` | OAuth2 or app password |
| Fastmail | `https://caldav.fastmail.com/dav` | App password |
| Nextcloud | `https://yourserver.com/remote.php/dav` | User credentials |

---

## 5. Auto-Logging Logic

### 5.1 When to Auto-Log

Events are auto-logged as activities when:
1. Event has ended (past event)
2. At least one attendee matches a known contact
3. User is an attendee OR organizer (not just invited)
4. Event hasn't already been logged (`activity_id` is null)

### 5.2 Activity Creation

```php
class STADION_Calendar_Activity_Logger {
    public function maybe_log_event($event_id) {
        $activity_id = get_post_meta($event_id, '_activity_id', true);
        if ($activity_id) return; // Already logged

        $end_time = get_post_meta($event_id, '_end_time', true);
        if (strtotime($end_time) > time()) return; // Future event

        $matched_people = json_decode(get_post_meta($event_id, '_matched_people', true), true);
        if (empty($matched_people)) return; // No matches

        $event = get_post($event_id);
        $start_time = get_post_meta($event_id, '_start_time', true);
        $location = get_post_meta($event_id, '_location', true);
        $meeting_url = get_post_meta($event_id, '_meeting_url', true);
        $attendees = json_decode(get_post_meta($event_id, '_attendees', true), true);

        foreach ($matched_people as $match) {
            // Create activity using existing activity system
            $activity_id = wp_insert_post([
                'post_type' => 'stadion_activity',
                'post_title' => $event->post_title,
                'post_content' => $this->format_description($event_id),
                'post_author' => $event->post_author,
                'post_status' => 'publish',
                'post_date' => $start_time,
            ]);

            // Activity meta
            update_post_meta($activity_id, '_activity_type', 'meeting');
            update_post_meta($activity_id, '_person_id', $match['person_id']);
            update_post_meta($activity_id, '_calendar_event_id', $event_id);
            update_post_meta($activity_id, '_duration_minutes', $this->calculate_duration($event_id));
            update_post_meta($activity_id, '_location', $location);
            update_post_meta($activity_id, '_meeting_url', $meeting_url);
        }

        // Mark event as logged
        update_post_meta($event_id, '_activity_id', $activity_id);
    }
}
```

### 5.3 Deduplication

Prevent duplicate activities:
- Check for existing activity with same `calendar_event_id` in metadata
- Check for activity with same person, date, and similar title
- Allow user to manually log if auto-log was skipped

---

## 6. UI Components

### 6.1 Settings: Calendar Connections Page

**Location:** `/settings/calendars` (new page, linked from Settings)

**Features:**
- List of connected calendars with sync status
- "Connect Google Calendar" button → OAuth flow
- "Connect CalDAV Calendar" button → opens modal for URL/credentials
- Per-connection settings:
  - Enable/disable sync
  - Sync date range (how far back)
  - Auto-log toggle
- Manual "Sync Now" button
- Last sync timestamp and error status

### 6.2 Person Detail: Meetings Section

**Location:** New section in `PersonDetail.jsx`

**Features:**
- "Upcoming Meetings" collapsible section
  - List of future calendar events involving this person
  - Shows: title, date/time, other attendees, meeting link
  - Quick action: "Add to calendar" if external link
- "Past Meetings" collapsible section
  - List of logged meetings
  - Shows: title, date, logged status
  - "Log as Activity" button for un-logged events
- Badge showing meeting count on contact card (optional)

### 6.3 Dashboard Widget (Optional)

- "Today's Meetings" widget showing contacts you're meeting today
- Quick links to their profiles

---

## 7. Background Sync

### 7.1 WP-Cron Jobs

```php
// Register cron schedules
add_filter('cron_schedules', function($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display' => 'Every 15 minutes'
    ];
    return $schedules;
});

// Schedule sync job
if (!wp_next_scheduled('stadion_calendar_sync')) {
    wp_schedule_event(time(), 'every_15_minutes', 'stadion_calendar_sync');
}

add_action('stadion_calendar_sync', function() {
    // Get all users with calendar connections
    $users = get_users([
        'meta_key' => '_stadion_calendar_connections',
        'meta_compare' => 'EXISTS',
    ]);

    foreach ($users as $user) {
        $connections = get_user_meta($user->ID, '_stadion_calendar_connections', true);
        if (!is_array($connections)) continue;

        foreach ($connections as &$conn) {
            if (!$conn['sync_enabled']) continue;

            try {
                $provider = STADION_Calendar_Provider_Factory::create($conn['provider']);
                $provider->sync($user->ID, $conn);
                $conn['last_sync'] = current_time('c');
                $conn['last_error'] = null;
            } catch (Exception $e) {
                $conn['last_error'] = $e->getMessage();
            }
        }

        update_user_meta($user->ID, '_stadion_calendar_connections', $connections);
    }
});
```

### 7.2 Sync Frequency

| Event Type | Sync Interval |
|------------|---------------|
| Upcoming (next 7 days) | Every 15 minutes |
| Recent past (last 7 days) | Every hour |
| Older events | Daily |

### 7.3 Rate Limiting

- Google Calendar API: 1,000,000 queries/day (generous)
- CalDAV: Respect server rate limits, implement exponential backoff
- Batch sync requests where possible

---

## 8. Security Considerations

### 8.1 Credential Storage

```php
class STADION_Credential_Encryption {
    public static function encrypt($data) {
        $key = self::get_encryption_key();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-CBC',
            $key,
            0,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($encrypted) {
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return json_decode(openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            0,
            $iv
        ), true);
    }

    private static function get_encryption_key() {
        // Use WordPress AUTH_KEY or dedicated key
        return hash('sha256', AUTH_KEY . 'stadion_calendar', true);
    }
}
```

### 8.2 OAuth Token Refresh

```php
class STADION_Google_Auth {
    public function get_valid_access_token($connection) {
        $creds = $connection->get_decrypted_credentials();

        if ($creds['expires_at'] < time() + 300) {
            // Token expires in < 5 minutes, refresh
            $new_tokens = $this->refresh_token($creds['refresh_token']);
            $connection->update_credentials($new_tokens);
            return $new_tokens['access_token'];
        }

        return $creds['access_token'];
    }
}
```

### 8.3 Permission Model

- Users can only see/manage their own calendar connections
- Calendar event data is only visible to the user who connected that calendar
- Matched contact data respects existing visibility rules

---

## 9. PHP Classes

### 9.1 Class Structure

```
includes/
├── class-prm-calendar-post-type.php          # Register calendar_event CPT
├── class-prm-calendar-rest.php               # REST endpoints
├── class-prm-calendar-sync.php               # Sync orchestration + cron
├── class-prm-calendar-matcher.php            # Contact matching logic
├── class-prm-calendar-activity-logger.php    # Auto-log activities
├── class-prm-credential-encryption.php       # Secure credential storage
│
├── calendar-providers/
│   ├── interface-prm-calendar-provider.php   # Provider interface
│   ├── class-prm-google-calendar.php         # Google implementation
│   ├── class-prm-caldav-provider.php         # Generic CalDAV
│   └── class-prm-provider-factory.php        # Provider factory
│
└── class-prm-google-oauth.php                # Google OAuth handler
```

### 9.2 Provider Interface

```php
interface STADION_Calendar_Provider {
    public function get_calendars(array $credentials): array;
    public function sync(int $user_id, array $connection): void;
    public function test_connection(array $credentials): bool;
}

interface STADION_OAuth_Calendar_Provider extends STADION_Calendar_Provider {
    public function get_auth_url(string $redirect_uri): string;
    public function handle_callback(string $code, string $redirect_uri): array;
    public function refresh_token(string $refresh_token): array;
}
```

### 9.3 Connection Helper Class

```php
class STADION_Calendar_Connections {
    public static function get_user_connections(int $user_id): array {
        return get_user_meta($user_id, '_stadion_calendar_connections', true) ?: [];
    }

    public static function get_connection(int $user_id, string $connection_id): ?array {
        $connections = self::get_user_connections($user_id);
        foreach ($connections as $conn) {
            if ($conn['id'] === $connection_id) return $conn;
        }
        return null;
    }

    public static function add_connection(int $user_id, array $connection): string {
        $connections = self::get_user_connections($user_id);
        $connection['id'] = 'conn_' . uniqid();
        $connection['created_at'] = current_time('c');
        $connections[] = $connection;
        update_user_meta($user_id, '_stadion_calendar_connections', $connections);
        return $connection['id'];
    }

    public static function update_connection(int $user_id, string $connection_id, array $updates): bool {
        $connections = self::get_user_connections($user_id);
        foreach ($connections as &$conn) {
            if ($conn['id'] === $connection_id) {
                $conn = array_merge($conn, $updates);
                update_user_meta($user_id, '_stadion_calendar_connections', $connections);
                return true;
            }
        }
        return false;
    }

    public static function delete_connection(int $user_id, string $connection_id): bool {
        $connections = self::get_user_connections($user_id);
        $connections = array_filter($connections, fn($c) => $c['id'] !== $connection_id);
        update_user_meta($user_id, '_stadion_calendar_connections', array_values($connections));

        // Also delete cached events for this connection
        $events = get_posts([
            'post_type' => 'calendar_event',
            'author' => $user_id,
            'meta_key' => '_connection_id',
            'meta_value' => $connection_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        foreach ($events as $event_id) {
            wp_delete_post($event_id, true);
        }

        return true;
    }
}
```

---

## 10. Frontend Components

### 10.1 New Files

```
src/
├── pages/
│   └── Settings/
│       └── Calendars.jsx                    # Calendar connections page
├── components/
│   ├── calendar/
│   │   ├── CalendarConnectionCard.jsx       # Single connection display
│   │   ├── AddCalendarModal.jsx             # Add CalDAV connection
│   │   ├── GoogleCalendarButton.jsx         # Initiate Google OAuth
│   │   └── CalendarSyncStatus.jsx           # Sync progress indicator
│   └── person/
│       └── PersonMeetings.jsx               # Meetings section in detail view
└── hooks/
    └── useCalendarConnections.js            # React Query hooks
```

### 10.2 API Client Additions

```javascript
// src/api/client.js additions
export const calendarApi = {
  // Connections
  getConnections: () => api.get('/stadion/v1/calendar/connections'),
  createConnection: (data) => api.post('/stadion/v1/calendar/connections', data),
  updateConnection: (id, data) => api.put(`/stadion/v1/calendar/connections/${id}`, data),
  deleteConnection: (id) => api.delete(`/stadion/v1/calendar/connections/${id}`),
  syncConnection: (id) => api.post(`/stadion/v1/calendar/connections/${id}/sync`),

  // OAuth
  getGoogleAuthUrl: () => api.get('/stadion/v1/calendar/auth/google'),
  testCalDAV: (data) => api.post('/stadion/v1/calendar/auth/caldav/test', data),

  // Events & Meetings
  getPersonMeetings: (personId, params) =>
    api.get(`/stadion/v1/people/${personId}/meetings`, { params }),
  logEventAsActivity: (eventId) =>
    api.post(`/stadion/v1/calendar/events/${eventId}/log`),
};
```

---

## 11. Implementation Phases

| Phase | Tasks | Estimate |
|-------|-------|----------|
| 1 | CPT registration + user meta helpers + REST structure | 0.5 week |
| 2 | Credential encryption + Google OAuth flow | 1 week |
| 3 | Google Calendar provider + sync logic | 1 week |
| 4 | CalDAV provider implementation | 1 week |
| 5 | Contact matching algorithm + transient cache | 0.5 week |
| 6 | Settings UI (connections management) | 1 week |
| 7 | Person detail meetings section | 0.5 week |
| 8 | Background sync (WP-Cron) + auto-logging | 1 week |
| 9 | Testing, error handling, polish | 1 week |
| **Total** | | **7.5 weeks** |

---

## 12. Dependencies

### PHP Dependencies (composer.json)

```json
{
  "require": {
    "google/apiclient": "^2.15",
    "sabre/dav": "^4.6"
  }
}
```

### Environment Variables

```env
# Google OAuth (add to .env)
GOOGLE_CALENDAR_CLIENT_ID=your-client-id
GOOGLE_CALENDAR_CLIENT_SECRET=your-client-secret
GOOGLE_CALENDAR_REDIRECT_URI=https://yoursite.com/wp-json/stadion/v1/calendar/auth/google/callback
```

---

## 13. Future Enhancements

- **Two-way sync:** Create calendar events from Stadion (e.g., schedule follow-up)
- **Meeting notes:** Add notes to logged meetings
- **Recurring event handling:** Better UX for recurring meetings
- **Calendar widget:** Dashboard showing today's meetings with contacts
- **Meeting prep:** Show contact info before upcoming meetings
- **Outlook OAuth:** Native OAuth for Outlook 365 (better than CalDAV)
- **Zoom/Teams integration:** Extract meeting links and join info

---

## Sources

- [Google Calendar API Documentation](https://developers.google.com/calendar)
- [Google CalDAV API Guide](https://developers.google.com/workspace/calendar/caldav/v2/guide)
- [CalDAV vs Google Calendar API Comparison](https://www.tutorialpedia.org/blog/difference-between-google-caldav-api-and-google-calendar-api/)
- [sabre/dav PHP Library](https://sabre.io/dav/)
