# Stack Research: Google Contacts Sync

**Project:** Stadion Google Contacts Two-Way Sync (v5.0)
**Researched:** 2026-01-17
**Confidence:** HIGH (verified with official Google documentation)

## Executive Summary

Stadion already has the core infrastructure needed for Google Contacts sync. The existing `google/apiclient ^2.15` dependency includes the `Google_Service_PeopleService` class required for the People API. The OAuth flow, token encryption (Sodium), and WP-Cron patterns from Calendar integration transfer directly. **No new Composer dependencies are needed** - only OAuth scope expansion and new PHP classes using existing patterns.

---

## Recommended Stack

### Core Library (Already Installed)

| Technology | Version | Purpose | Status |
|------------|---------|---------|--------|
| `google/apiclient` | ^2.15 (current: 2.19.0 available) | Google API PHP client | **Already in composer.json** |
| `Google_Service_PeopleService` | Included | People API service class | **Available via google/apiclient-services** |

**Recommendation:** Keep `^2.15` constraint. The current installation works. Optionally upgrade to `^2.19` for latest features, but not required.

### OAuth Scopes (New)

| Scope | Purpose | Required |
|-------|---------|----------|
| `https://www.googleapis.com/auth/contacts` | Read/write contacts | **YES** (bidirectional sync) |
| `https://www.googleapis.com/auth/contacts.readonly` | Read-only contacts | OPTIONAL (import-only mode) |

**Current Calendar scope:** `https://www.googleapis.com/auth/calendar.readonly`

**Implementation:** Extend `GoogleOAuth::SCOPES` constant to be configurable per-service, or request additional scopes incrementally.

### Existing Infrastructure (Reuse)

| Component | Location | Reuse Strategy |
|-----------|----------|----------------|
| OAuth2 flow | `class-google-oauth.php` | Extend for multi-scope support |
| Token encryption | `class-credential-encryption.php` | Direct reuse (Sodium-based) |
| Token refresh | `GoogleOAuth::get_access_token()` | Direct reuse |
| Background sync | `class-calendar-sync.php` | Follow same WP-Cron patterns |
| Transient locking | `GoogleProvider::sync()` | Copy pattern for race condition prevention |

---

## Libraries

### Primary: google/apiclient (ALREADY INSTALLED)

```json
{
  "require": {
    "google/apiclient": "^2.15"
  }
}
```

**Version:** Current constraint `^2.15` is sufficient. Latest is v2.19.0.

**Why this library:**
- Official Google library with full People API support
- Already installed and working for Calendar integration
- Includes `Google_Service_PeopleService` for all CRUD operations
- Automatic token refresh handling
- Well-documented, actively maintained

**Key classes you'll use:**
```php
use Google\Service\PeopleService;
use Google\Service\PeopleService\Person;
use Google\Service\PeopleService\Name;
use Google\Service\PeopleService\EmailAddress;
use Google\Service\PeopleService\PhoneNumber;
use Google\Service\PeopleService\Address;
use Google\Service\PeopleService\Birthday;
use Google\Service\PeopleService\Organization;
use Google\Service\PeopleService\Photo;
```

### NOT Recommended: rapidwebltd/php-google-people-api

| Aspect | Assessment |
|--------|------------|
| Latest version | v1.0.1 (from 2019!) |
| Maintenance | Effectively abandoned |
| Dependency | Adds extra OAuth handler layer |
| Benefit | Minor convenience over raw google/apiclient |

**Verdict:** SKIP. The official `google/apiclient` already installed provides everything needed. Adding this wrapper creates an unnecessary dependency on unmaintained code.

### NOT Recommended: yidas/google-apiclient-helper

| Aspect | Assessment |
|--------|------------|
| Purpose | Convenience wrapper for google/apiclient |
| Added value | Slightly simpler syntax |
| Downside | Extra abstraction, potential breaking changes |

**Verdict:** SKIP. Stadion already has established patterns with direct google/apiclient usage. Adding a helper library creates inconsistency with Calendar implementation.

---

## APIs

### Google People API v1

**Base URL:** `https://people.googleapis.com/v1`

**Core Endpoints for Contacts Sync:**

| Operation | Method | Endpoint | Rate Limit |
|-----------|--------|----------|------------|
| List contacts | GET | `/people/me/connections` | 1 critical read per contact |
| Get contact | GET | `/people/{resourceName}` | 1 critical read |
| Create contact | POST | `/people:createContact` | 1 critical write |
| Update contact | PATCH | `/people/{resourceName}:updateContact` | 1 critical write |
| Delete contact | DELETE | `/people/{resourceName}:deleteContact` | 1 write request |
| Update photo | PATCH | `/people/{resourceName}:updateContactPhoto` | 1 critical write |

**Batch Endpoints (for bulk operations):**

| Operation | Method | Endpoint | Max Items |
|-----------|--------|----------|-----------|
| Batch create | POST | `/people:batchCreateContacts` | 200 contacts |
| Batch update | POST | `/people:batchUpdateContacts` | 200 contacts |
| Batch delete | POST | `/people:batchDeleteContacts` | 500 contacts |
| Batch get | GET | `/people:batchGet` | 200 contacts |

### Delta Sync with syncToken

**How it works:**
1. First sync: Call `connections.list` with `requestSyncToken=true`
2. Store returned `nextSyncToken` in user meta
3. Subsequent syncs: Call with `syncToken` parameter
4. API returns only changed contacts since last sync
5. Deleted contacts returned with `metadata.deleted=true`

**Critical constraints:**
- Sync tokens expire after **7 days**
- Expired token returns HTTP 410 with `EXPIRED_SYNC_TOKEN` reason
- When expired: perform full sync without syncToken
- All request parameters must match the first call when using syncToken

**PHP implementation pattern:**
```php
$params = [
    'personFields' => 'metadata,names,emailAddresses,phoneNumbers,addresses,birthdays,organizations,photos,biographies,nicknames,urls',
    'pageSize' => 1000,
    'requestSyncToken' => true,
];

// Add syncToken for incremental sync
if ($stored_sync_token) {
    $params['syncToken'] = $stored_sync_token;
}

$response = $people_service->people_connections->listPeopleConnections('people/me', $params);
$contacts = $response->getConnections();
$next_sync_token = $response->getNextSyncToken();
```

### ETag for Conflict Detection

**Required for updates/deletes.** The ETag prevents overwriting concurrent modifications.

**Flow:**
1. Fetch contact: `GET /people/{resourceName}?personFields=metadata,...`
2. Extract ETag from `person.metadata.sources[0].etag`
3. Include in update: `person.metadata.sources[0].etag = stored_etag`
4. If ETag mismatch: API returns 400 `failedPrecondition`
5. On conflict: fetch latest, merge changes, retry

### Photo Upload

**Method:** `people.updateContactPhoto`
**Format:** Base64-encoded photo bytes in request body

```php
$photo_request = new UpdateContactPhotoRequest();
$photo_request->setPhotoBytes(base64_encode($image_data));
$photo_request->setPersonFields('photos');

$people_service->people->updateContactPhoto($resource_name, $photo_request);
```

**No explicit size limit documented**, but recommend keeping under 5MB for reliability.

---

## What NOT to Use

### 1. Google Contacts API v3 (DEPRECATED)

| Issue | Details |
|-------|---------|
| Status | **Turned down January 19, 2022** |
| Replacement | People API v1 |
| Risk | Will not work |

**Verdict:** Do not use. The old `https://www.google.com/m8/feeds` endpoints are dead.

### 2. Service Account Authentication

| Issue | Details |
|-------|---------|
| Problem | People API does not support service accounts for user contacts |
| Why | Contacts are user-specific, not domain-wide |
| Alternative | OAuth2 with user consent |

**Verdict:** Not applicable. Personal contacts require user OAuth flow.

### 3. Generic HTTP Batch (Google_Http_Batch)

| Issue | Details |
|-------|---------|
| Problem | Returns 404 errors with People API |
| Why | Batch endpoints are dedicated paths, not generic batching |
| Alternative | Use dedicated batch endpoints directly |

**Verdict:** Use `batchCreateContacts`, `batchUpdateContacts`, `batchDeleteContacts` endpoints directly rather than generic batching.

### 4. Webhook-based Real-time Sync

| Issue | Details |
|-------|---------|
| Problem | People API has no push notification/webhook support |
| Alternative | Polling with syncToken (efficient delta sync) |
| Alignment | Matches Calendar integration pattern |

**Verdict:** Not available. Use WP-Cron polling with syncToken for efficient sync.

### 5. Parallel Mutate Requests

| Issue | Details |
|-------|---------|
| Problem | Causes increased latency and failures |
| Source | Official Google documentation warning |
| Alternative | Sequential mutate requests per user |

**Verdict:** Always serialize create/update/delete operations for the same user.

---

## Integration Notes

### Extending Existing OAuth

The current `GoogleOAuth` class has hardcoded scopes:

```php
private const SCOPES = [ 'https://www.googleapis.com/auth/calendar.readonly' ];
```

**Recommended changes:**

1. **Option A: Service-specific scopes** (cleanest)
   ```php
   public const CALENDAR_SCOPES = ['https://www.googleapis.com/auth/calendar.readonly'];
   public const CONTACTS_SCOPES = ['https://www.googleapis.com/auth/contacts'];

   public static function get_auth_url(int $user_id, array $scopes): string
   ```

2. **Option B: Incremental scope request**
   - User connects Calendar first
   - Later adds Contacts scope
   - Google handles incremental consent
   - Existing tokens gain new scope

**Recommendation:** Option A for cleaner separation. Each connection (Calendar, Contacts) has its own credential set.

### Credential Storage Pattern

Follow existing `STADION_Calendar_Connections` pattern:

```php
// User meta structure for contacts
'_stadion_google_contacts_connection' => [
    'id' => 'google_contacts_' . uniqid(),
    'provider' => 'google_contacts',
    'credentials' => CredentialEncryption::encrypt([
        'access_token' => '...',
        'refresh_token' => '...',
        'expires_at' => time() + 3600,
        'scope' => 'https://www.googleapis.com/auth/contacts',
    ]),
    'sync_enabled' => true,
    'sync_token' => '...', // People API sync token
    'last_sync' => '2026-01-17T10:00:00Z',
    'last_error' => null,
];
```

### Contact Post Meta Schema

Store Google-specific metadata on `person` posts:

```php
'_google_contact_id'     // string: resourceName (e.g., "people/c12345678")
'_google_etag'           // string: ETag for conflict detection
'_google_sync_enabled'   // bool: Whether this contact syncs
'_google_last_synced'    // datetime: Last successful sync
'_google_sync_status'    // enum: 'synced', 'pending_push', 'pending_pull', 'conflict', 'error'
'_google_raw_data'       // json: Cached Google contact for conflict resolution
```

### Sync Lock Pattern

Copy from `GoogleProvider::sync()`:

```php
$lock_key = 'stadion_contacts_sync_lock_' . $user_id;
if (get_transient($lock_key)) {
    return ['skipped' => true];
}
set_transient($lock_key, true, 5 * MINUTE_IN_SECONDS);

try {
    // Sync logic
} finally {
    delete_transient($lock_key);
}
```

### Error Handling

Handle these Google API errors:

| HTTP Code | Reason | Action |
|-----------|--------|--------|
| 400 | `failedPrecondition` | ETag mismatch - fetch latest, merge, retry |
| 401 | Unauthorized | Token expired - refresh or re-auth |
| 403 | Quota exceeded | Exponential backoff |
| 404 | Contact deleted | Mark as deleted in Stadion |
| 410 | `EXPIRED_SYNC_TOKEN` | Full sync without syncToken |
| 429 | Rate limited | Exponential backoff |

### Field Mapping Quick Reference

| Google People API | Stadion ACF Field | Notes |
|-------------------|------------------|-------|
| `names[0].givenName` | `first_name` | Single value |
| `names[0].familyName` | `last_name` | Single value |
| `nicknames[0].value` | `nickname` | Single value |
| `emailAddresses[]` | `contact_info` repeater | type: 'email' |
| `phoneNumbers[]` | `contact_info` repeater | type: 'phone'/'mobile' |
| `addresses[]` | `addresses` repeater | Full address mapping |
| `birthdays[0]` | Create `important_date` post | type: 'birthday' |
| `organizations[0]` | `work_history` repeater | May create company |
| `photos[0].url` | `_thumbnail_id` | Sideload to media |
| `biographies[0].value` | `story` | WYSIWYG field |
| `urls[]` | `contact_info` repeater | Detect type from URL |

---

## Rate Limits & Quotas

### Default Quotas (per project)

| Quota | Limit | Period |
|-------|-------|--------|
| Critical read requests | 1,500 | Per minute |
| Critical write requests | 60 | Per minute |
| Daily writes | Varies | Per day |

### Recommendations

1. **Batch operations** for initial import (up to 200 contacts per request)
2. **Delta sync** for ongoing sync (syncToken reduces read load)
3. **Rate limiting** with exponential backoff on 429 errors
4. **Sequential writes** for the same user (no parallel mutations)

---

## Sources

### Official Documentation (HIGH confidence)

- [Google People API - Read and Manage Contacts](https://developers.google.com/people/v1/contacts)
- [people.connections.list Method](https://developers.google.com/people/api/rest/v1/people.connections/list)
- [people.updateContact Method](https://developers.google.com/people/api/rest/v1/people/updateContact)
- [people.updateContactPhoto Method](https://developers.google.com/people/api/rest/v1/people/updateContactPhoto)
- [Contacts API Migration Guide](https://developers.google.com/people/contacts-api-migration)
- [People API Introduction](https://developers.google.com/people)
- [OAuth 2.0 Scopes for Google APIs](https://developers.google.com/identity/protocols/oauth2/scopes)

### Library Documentation (HIGH confidence)

- [google/apiclient on Packagist](https://packagist.org/packages/google/apiclient)
- [google/apiclient GitHub Releases](https://github.com/googleapis/google-api-php-client/releases)
- [Google API PHP Client GitHub](https://github.com/googleapis/google-api-php-client)

### Implementation References (MEDIUM confidence)

- [Google People API batch mutates announcement](https://developers.googleblog.com/2021/03/google-people-api-now-supports-batch.html)
- [GitHub Issue: updateContactPhoto in PHP](https://github.com/googleapis/google-api-php-client-services/issues/220)

---

## Summary

| Decision | Recommendation | Rationale |
|----------|----------------|-----------|
| Primary library | `google/apiclient` (existing) | Already installed, official, full feature support |
| New dependencies | **None needed** | Existing stack sufficient |
| OAuth approach | Extend `GoogleOAuth` class | Follow established patterns |
| Sync approach | WP-Cron + syncToken | Matches Calendar pattern, efficient delta sync |
| Token storage | Sodium encryption (existing) | Reuse `CredentialEncryption` class |
| Photo handling | Sideload + updateContactPhoto | WordPress media library + API upload |
| Conflict resolution | ETag-based | Required by API, prevents overwrites |
