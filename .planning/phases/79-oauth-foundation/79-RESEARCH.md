# Phase 79: OAuth Foundation - Research

**Researched:** 2026-01-17
**Domain:** Google OAuth, People API, Incremental Authorization
**Confidence:** HIGH

## Summary

This phase extends the existing Google Calendar OAuth implementation to support Google Contacts (People API) scopes. The codebase already has a well-structured OAuth foundation in `class-google-oauth.php` that handles token exchange, refresh, and storage using Sodium encryption. The Google API PHP client library is already installed and includes the `PeopleService` class with all necessary scope constants.

The key technical challenge is implementing Google's incremental authorization pattern, which allows users with existing Calendar tokens to add Contacts scope without losing their Calendar access. This requires using `setIncludeGrantedScopes(true)` on the Google Client before generating authorization URLs. The existing OAuth infrastructure stores tokens per-connection (Calendar), but Contacts needs user-level token storage since it's not a "connection" but an account-wide capability.

**Primary recommendation:** Extend `GoogleOAuth` class to support multiple scope sets with incremental authorization. Store Contacts-specific connection data in dedicated user meta keys (separate from calendar connections array). Reuse existing token encryption and refresh logic.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| google/apiclient | 2.x | Google API PHP Client | Already installed, handles OAuth flow |
| google/apiclient-services | 0.3xx | Service definitions | Already installed, includes PeopleService |
| Sodium (PHP) | Built-in | Token encryption | Already implemented in CredentialEncryption |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Google\Service\PeopleService | v1 | Contacts API access | For all People API operations |
| Google\Client | 2.x | OAuth client | Already configured in GoogleOAuth |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PeopleService | Direct REST API | More control but more code, no benefit |
| User meta storage | Custom table | Unnecessary complexity for single-user data |

**Installation:**
No new packages needed - all dependencies already installed.

```bash
# Verify installation
composer show google/apiclient
composer show google/apiclient-services
```

## Architecture Patterns

### Existing OAuth Architecture

The current implementation follows this pattern:
```
User clicks "Connect Google Calendar"
    |
    v
GET /stadion/v1/calendar/auth/google
    |
    v
GoogleOAuth::get_auth_url($user_id)
    - Creates Google\Client with Calendar scope
    - Stores state token in transient
    - Returns authorization URL
    |
    v
User redirected to Google, grants access
    |
    v
GET /stadion/v1/calendar/auth/google/callback
    - Validates state from transient
    - Exchanges code for tokens via GoogleOAuth::handle_callback()
    - Creates calendar connection with encrypted credentials
    - Redirects to Settings with success param
```

### Recommended Extension Pattern

For Contacts support, extend the existing pattern:

```
src/
includes/
├── class-google-oauth.php         # MODIFY: Add scope constants, incremental auth
├── class-calendar-connections.php # NO CHANGE (calendar-specific)
├── class-rest-calendar.php        # MODIFY: Separate auth endpoints for contacts
├── class-rest-google-contacts.php # NEW: Contacts-specific REST endpoints
└── class-google-contacts-connection.php # NEW: User meta storage for contacts
```

### Pattern 1: Incremental Authorization
**What:** Add scopes to existing grants without re-authenticating from scratch
**When to use:** User has Calendar connected, wants to add Contacts
**Example:**
```php
// Source: https://github.com/googleapis/google-api-php-client/blob/main/docs/oauth-web.md
$client = new \Google\Client();
$client->setClientId(GOOGLE_CALENDAR_CLIENT_ID);
$client->setClientSecret(GOOGLE_CALENDAR_CLIENT_SECRET);
$client->setRedirectUri($redirect_uri);

// CRITICAL: Enable incremental authorization
$client->setIncludeGrantedScopes(true);

// Request only the NEW scope - existing scopes preserved
$client->setScopes([
    \Google\Service\PeopleService::CONTACTS_READONLY,
    // or for read/write:
    // \Google\Service\PeopleService::CONTACTS,
]);

$client->setAccessType('offline');
$client->setPrompt('consent');

$auth_url = $client->createAuthUrl();
```

### Pattern 2: Scope Detection
**What:** Check what scopes a token has before attempting API calls
**When to use:** Determine if user needs to authorize Contacts scope
**Example:**
```php
// Decode token scope from stored credentials
$credentials = CredentialEncryption::decrypt($encrypted);
$granted_scopes = explode(' ', $credentials['scope'] ?? '');

$has_calendar = in_array('https://www.googleapis.com/auth/calendar.readonly', $granted_scopes);
$has_contacts = in_array('https://www.googleapis.com/auth/contacts.readonly', $granted_scopes)
             || in_array('https://www.googleapis.com/auth/contacts', $granted_scopes);

// User needs to authorize if they don't have contacts scope
$needs_contacts_auth = !$has_contacts;
```

### Pattern 3: Separate Connection Storage
**What:** Store Contacts connection separately from Calendar connections
**When to use:** Contacts is user-level, not per-calendar
**Example:**
```php
// User meta keys for contacts
const CONTACTS_META_KEY = '_stadion_google_contacts_connection';
const CONTACTS_SYNC_TOKEN = '_stadion_google_contacts_sync_token';
const CONTACTS_LAST_SYNC = '_stadion_google_contacts_last_sync';

// Connection structure (stored encrypted in user meta)
$contacts_connection = [
    'enabled'       => true,
    'access_mode'   => 'readonly', // or 'readwrite'
    'credentials'   => $encrypted_tokens,
    'email'         => 'user@gmail.com',
    'connected_at'  => '2026-01-17T10:00:00Z',
    'last_sync'     => null,
    'last_error'    => null,
    'contact_count' => 0,
];
```

### Anti-Patterns to Avoid
- **Storing contacts token in calendar connections array:** Calendar connections are per-calendar, contacts is per-account
- **Requesting all scopes upfront:** Violates incremental auth principle and user trust
- **Using same callback endpoint:** Needs different logic for calendar vs contacts completion
- **Ignoring existing token scopes:** Must check before prompting for re-auth

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Token encryption | Custom crypto | `CredentialEncryption::encrypt/decrypt` | Already tested, uses Sodium |
| Token refresh | Manual HTTP | `$client->fetchAccessTokenWithRefreshToken()` | Handles edge cases |
| Scope constants | Hardcoded strings | `PeopleService::CONTACTS_READONLY` | Type-safe, auto-complete |
| State validation | Session storage | Transients with token | Works across redirects |

**Key insight:** The existing OAuth infrastructure handles 90% of what's needed. The main work is adapting the flow for incremental scopes and separate storage.

## Common Pitfalls

### Pitfall 1: Refresh Token Not Returned
**What goes wrong:** On incremental auth, Google may not return a new refresh_token
**Why it happens:** Refresh token is only issued on first authorization or when `prompt=consent`
**How to avoid:** Always set `setPrompt('consent')` when requesting new scopes. Also preserve existing refresh_token if new one not returned:
```php
if (empty($new_credentials['refresh_token'])) {
    $new_credentials['refresh_token'] = $existing_credentials['refresh_token'];
}
```
**Warning signs:** Token refresh fails after adding scopes

### Pitfall 2: Scope Accumulation Confusion
**What goes wrong:** Unclear which scopes the user has granted
**Why it happens:** `setIncludeGrantedScopes(true)` accumulates but doesn't inform what's already there
**How to avoid:** Always check the `scope` field in the returned token to see actual granted scopes:
```php
$token = $client->fetchAccessTokenWithAuthCode($code);
$actual_scopes = explode(' ', $token['scope']);
```
**Warning signs:** API calls fail with 403 scope errors

### Pitfall 3: Mixed Calendar/Contacts Tokens
**What goes wrong:** Using calendar connection token for contacts API
**Why it happens:** Both are "Google" connections, easy to grab wrong one
**How to avoid:** Store and retrieve contacts credentials from dedicated user meta, not calendar connections array
**Warning signs:** Scope errors, wrong permissions

### Pitfall 4: Callback State Confusion
**What goes wrong:** Can't tell if OAuth callback is for calendar or contacts
**Why it happens:** Both use similar flow, same Google redirect
**How to avoid:** Include purpose in state parameter or use separate callback endpoints:
```php
// Option 1: Encode purpose in state
$state = json_encode(['token' => $random_token, 'purpose' => 'contacts']);

// Option 2: Separate endpoints (recommended)
// /stadion/v1/calendar/auth/google/callback - calendar
// /stadion/v1/google-contacts/callback      - contacts
```
**Warning signs:** Calendar connections created when contacts intended

### Pitfall 5: Read-Only vs Read-Write Scope Mismatch
**What goes wrong:** User grants read-only but UI shows write features
**Why it happens:** Not checking which scope was actually granted
**How to avoid:** Store and check the access_mode based on actual granted scope:
```php
$has_write = in_array(\Google\Service\PeopleService::CONTACTS, $granted_scopes);
$access_mode = $has_write ? 'readwrite' : 'readonly';
```
**Warning signs:** Write operations fail silently

## Code Examples

Verified patterns from official sources and existing codebase:

### Extending GoogleOAuth for Multiple Scopes
```php
// Source: Existing class-google-oauth.php pattern + Google API docs
class GoogleOAuth {
    // Existing
    private const CALENDAR_SCOPES = ['https://www.googleapis.com/auth/calendar.readonly'];

    // NEW: Add contacts scopes
    public const CONTACTS_SCOPE_READONLY = 'https://www.googleapis.com/auth/contacts.readonly';
    public const CONTACTS_SCOPE_READWRITE = 'https://www.googleapis.com/auth/contacts';

    /**
     * Get client configured for contacts authorization
     *
     * @param bool $include_granted_scopes Enable incremental auth
     * @param bool $readonly Request read-only or read-write access
     */
    public static function get_contacts_client(
        bool $include_granted_scopes = true,
        bool $readonly = true
    ): ?\Google\Client {
        if (!self::is_configured()) {
            return null;
        }

        $client = new \Google\Client();
        $client->setClientId(GOOGLE_CALENDAR_CLIENT_ID);
        $client->setClientSecret(GOOGLE_CALENDAR_CLIENT_SECRET);
        $client->setRedirectUri(rest_url('stadion/v1/google-contacts/callback'));

        // Set contacts scope based on access mode
        $scope = $readonly ? self::CONTACTS_SCOPE_READONLY : self::CONTACTS_SCOPE_READWRITE;
        $client->setScopes([$scope]);

        // CRITICAL: Preserve existing scopes (Calendar)
        $client->setIncludeGrantedScopes($include_granted_scopes);

        $client->setAccessType('offline');
        $client->setPrompt('consent'); // Ensure refresh token returned

        return $client;
    }
}
```

### Checking Granted Scopes
```php
// Source: Token structure from Google API
public static function has_contacts_scope(array $credentials): bool {
    $scope_string = $credentials['scope'] ?? '';
    $scopes = explode(' ', $scope_string);

    return in_array(self::CONTACTS_SCOPE_READONLY, $scopes)
        || in_array(self::CONTACTS_SCOPE_READWRITE, $scopes);
}

public static function get_contacts_access_mode(array $credentials): string {
    $scope_string = $credentials['scope'] ?? '';
    $scopes = explode(' ', $scope_string);

    if (in_array(self::CONTACTS_SCOPE_READWRITE, $scopes)) {
        return 'readwrite';
    }
    if (in_array(self::CONTACTS_SCOPE_READONLY, $scopes)) {
        return 'readonly';
    }
    return 'none';
}
```

### Google Contacts Connection Storage
```php
// Source: Pattern from class-calendar-connections.php adapted for contacts
class GoogleContactsConnection {
    const META_KEY = '_stadion_google_contacts_connection';

    public static function get_connection(int $user_id): ?array {
        $connection = get_user_meta($user_id, self::META_KEY, true);
        return is_array($connection) ? $connection : null;
    }

    public static function save_connection(int $user_id, array $connection): void {
        // Encrypt credentials before storage
        if (!empty($connection['credentials']) && is_array($connection['credentials'])) {
            $connection['credentials'] = CredentialEncryption::encrypt($connection['credentials']);
        }
        update_user_meta($user_id, self::META_KEY, $connection);
    }

    public static function delete_connection(int $user_id): void {
        delete_user_meta($user_id, self::META_KEY);
    }

    public static function is_connected(int $user_id): bool {
        $connection = self::get_connection($user_id);
        return !empty($connection['credentials']);
    }
}
```

### REST Endpoint for Contacts Auth Init
```php
// Source: Pattern from class-rest-calendar.php google_auth_init
public function google_contacts_auth_init($request) {
    // Check if already connected
    $user_id = get_current_user_id();
    if (GoogleContactsConnection::is_connected($user_id)) {
        return new \WP_Error(
            'already_connected',
            __('Google Contacts is already connected.', 'stadion'),
            ['status' => 400]
        );
    }

    // Get requested access mode
    $readonly = $request->get_param('readonly') !== false;

    // Get client with incremental auth enabled
    $client = GoogleOAuth::get_contacts_client(true, $readonly);
    if (!$client) {
        return new \WP_Error(
            'not_configured',
            __('Google integration is not configured.', 'stadion'),
            ['status' => 400]
        );
    }

    // Generate state with user ID and access mode
    $token = wp_generate_password(32, false);
    $state_data = [
        'token' => $token,
        'readonly' => $readonly,
    ];
    set_transient('google_contacts_oauth_' . $token, [
        'user_id' => $user_id,
        'readonly' => $readonly,
    ], 10 * MINUTE_IN_SECONDS);

    $client->setState($token);

    return rest_ensure_response(['auth_url' => $client->createAuthUrl()]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Contacts API | People API v1 | Jan 2022 | Must use PeopleService, not Contacts API |
| Request all scopes | Incremental auth | 2019 | Better UX, user trust |
| Single scope storage | Track all granted | 2019+ | Must check scope field in token |

**Deprecated/outdated:**
- Google Contacts API (deprecated 2019, shutdown 2022) - replaced by People API
- `setApprovalPrompt('force')` - replaced by `setPrompt('consent')`

## Open Questions

Things that couldn't be fully resolved:

1. **Combined Calendar+Contacts flow for new users**
   - What we know: CONTEXT.md specifies new users get both scopes in one flow
   - What's unclear: Exact wizard UI flow not specified
   - Recommendation: Implement as a single auth request with both scope sets, defer wizard to later phase

2. **Email notification on token revocation**
   - What we know: CONTEXT.md requires email when user revokes access in Google
   - What's unclear: How to detect revocation (polling vs on-failure detection)
   - Recommendation: Detect on token refresh failure, check for specific error codes

3. **Auto-start import after granting scope**
   - What we know: CONTEXT.md specifies auto-start import after connect
   - What's unclear: Import is Phase 80, this phase is OAuth only
   - Recommendation: Phase 79 stores flag `pending_initial_import: true`, Phase 80 checks and acts

## Sources

### Primary (HIGH confidence)
- Existing codebase: `/Users/joostdevalk/Code/stadion/includes/class-google-oauth.php`
- Existing codebase: `/Users/joostdevalk/Code/stadion/includes/class-calendar-connections.php`
- Existing codebase: `/Users/joostdevalk/Code/stadion/includes/class-rest-calendar.php`
- Google API PHP Client: `/Users/joostdevalk/Code/stadion/vendor/google/apiclient-services/src/PeopleService.php`
- [Google API PHP Client OAuth docs](https://github.com/googleapis/google-api-php-client/blob/main/docs/oauth-web.md)

### Secondary (MEDIUM confidence)
- [Google OAuth2 Web Server Flow](https://developers.google.com/identity/protocols/oauth2/web-server)
- [Google People API Contacts docs](https://developers.google.com/people/v1/contacts)
- [Google Incremental Authorization guide](https://developers.google.com/identity/sign-in/web/incremental-auth)

### Tertiary (LOW confidence)
- [Google OAuth Best Practices](https://developers.google.com/identity/protocols/oauth2/resources/best-practices)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Already installed and verified in codebase
- Architecture: HIGH - Pattern clear from existing Calendar OAuth implementation
- Pitfalls: HIGH - Well-documented in Google API docs and real-world experience

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (stable, Google OAuth rarely changes)

---

## Implementation Guidance for Planner

### Files to Modify
1. `includes/class-google-oauth.php` - Add contacts scope constants, get_contacts_client method
2. `includes/class-rest-calendar.php` - Add contacts auth endpoints (or create new class)
3. `src/api/client.js` - Add prmApi methods for contacts auth
4. `src/pages/Settings/Settings.jsx` - Add Google Contacts card to ConnectionsTab

### New Files to Create
1. `includes/class-google-contacts-connection.php` - User meta storage for contacts connection
2. `includes/class-rest-google-contacts.php` - REST API endpoints for contacts (status, connect, disconnect)
3. `src/pages/Settings/GoogleContactsCard.jsx` - UI component for connection status

### User Meta Keys to Add
- `_stadion_google_contacts_connection` - Connection data (credentials, status, etc.)
- `_stadion_google_contacts_pending_import` - Flag for auto-start import (Phase 80)

### API Endpoints to Add
- `GET /stadion/v1/google-contacts/status` - Check connection status
- `GET /stadion/v1/google-contacts/auth` - Initiate OAuth flow
- `GET /stadion/v1/google-contacts/callback` - Handle OAuth callback
- `DELETE /stadion/v1/google-contacts` - Disconnect and revoke
