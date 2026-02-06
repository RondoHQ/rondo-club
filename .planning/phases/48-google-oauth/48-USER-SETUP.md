# Phase 48: User Setup Required

**Generated:** 2026-01-15
**Phase:** 48-google-oauth
**Status:** Incomplete

Complete these items for Google Calendar integration to function. Claude automated everything possible; these items require human access to Google Cloud Console.

## Environment Variables

| Status | Variable | Source | Add to |
|--------|----------|--------|--------|
| [ ] | `GOOGLE_CALENDAR_CLIENT_ID` | Google Cloud Console -> APIs & Services -> Credentials -> OAuth 2.0 Client IDs -> Client ID | `wp-config.php` |
| [ ] | `GOOGLE_CALENDAR_CLIENT_SECRET` | Google Cloud Console -> APIs & Services -> Credentials -> OAuth 2.0 Client IDs -> Client secret | `wp-config.php` |

## Account Setup

- [ ] **Create Google Cloud project** (if needed)
  - URL: https://console.cloud.google.com/
  - Skip if: Already have Google Cloud project

## Dashboard Configuration

- [ ] **Enable Google Calendar API**
  - Location: Google Cloud Console -> APIs & Services -> Library -> Search "Google Calendar API"
  - Action: Click "Enable"
  - Required for calendar read access

- [ ] **Configure OAuth consent screen**
  - Location: Google Cloud Console -> APIs & Services -> OAuth consent screen
  - User type: External (or Internal for Google Workspace teams)
  - App name: Stadion (or your preferred name)
  - User support email: Your email
  - Scopes: Add `.../auth/calendar.readonly`
  - Save and continue

- [ ] **Create OAuth 2.0 Client ID**
  - Location: Google Cloud Console -> APIs & Services -> Credentials -> Create Credentials -> OAuth client ID
  - Application type: Web application
  - Name: Stadion Web Client
  - Authorized JavaScript origins: `https://your-domain.com`
  - Authorized redirect URIs: `https://your-domain.com/wp-json/rondo/v1/calendar/auth/google/callback`
  - Click Create
  - Copy Client ID and Client secret

## WordPress Configuration

Add these constants to your `wp-config.php`:

```php
// Google Calendar OAuth
define('GOOGLE_CALENDAR_CLIENT_ID', 'your-client-id-here.apps.googleusercontent.com');
define('GOOGLE_CALENDAR_CLIENT_SECRET', 'your-client-secret-here');
```

## Verification

After completing setup, verify by:

1. Log in to Stadion
2. Navigate to Settings -> Calendars
3. Click "Connect Google Calendar"
4. Complete OAuth flow
5. Should redirect back to settings with "connected=google" in URL

Expected: Connection appears in calendar connections list.

---

**Once all items complete:** Mark status as "Complete" at top of file.
