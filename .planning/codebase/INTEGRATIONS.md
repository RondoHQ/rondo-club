# External Integrations

**Analysis Date:** 2025-01-13

## APIs & External Services

**WordPress REST API:**
- Standard endpoints: `/wp/v2/` for posts, people, companies, dates
- Custom namespace: `/prm/v1/` for dashboard, search, reminders, user management
- Client: `src/api/client.js` (Axios with nonce handling)

**Slack Integration:**
- OAuth 2.0 integration for user authorization
- Endpoints:
  - `/prm/v1/slack/oauth/authorize` - OAuth flow initiation
  - `/prm/v1/slack/oauth/callback` - OAuth callback handling
  - `/prm/v1/slack/disconnect` - User disconnect
  - `/prm/v1/slack/channels` - List available channels
  - `/prm/v1/slack/targets` - Get/update notification targets
  - `/prm/v1/slack/commands` - Slash command handling
  - `/prm/v1/slack/events` - Event subscriptions
- Environment variables:
  - `CAELIS_SLACK_CLIENT_ID`
  - `CAELIS_SLACK_CLIENT_SECRET`
  - `CAELIS_SLACK_SIGNING_SECRET`
- Implementation: `includes/class-notification-channels.php`

**Gravatar:**
- Avatar fetching by email
- Endpoint: `/prm/v1/people/{personId}/gravatar`
- Implementation: `includes/class-rest-api.php`

## Data Storage

**Databases:**
- WordPress MySQL/MariaDB - Primary data store
- Custom post types: person, company, important_date
- Custom taxonomies: person_label, company_label, relationship_type, date_type
- Custom fields via ACF Pro

**File Storage:**
- WordPress Media Library for photos and logos
- Photo upload: `/prm/v1/people/{personId}/photo`
- Logo upload: `/prm/v1/companies/{companyId}/logo/upload`

**Caching:**
- React Query client-side caching for API responses
- No server-side caching layer detected

## Authentication & Identity

**Auth Provider:**
- WordPress native authentication
- Session-based login with X-WP-Nonce header validation
- 401/403 redirects to `wp-login.php`
- Implementation: `src/hooks/useAuth.js`, `src/api/client.js`

**User Management:**
- Admin approval workflow for new users
- Endpoints:
  - `/prm/v1/users` - List pending users
  - `/prm/v1/users/{id}/approve` - Approve user
  - `/prm/v1/users/{id}/deny` - Deny user
- Implementation: `includes/class-user-roles.php`

**Application Passwords:**
- WordPress application passwords for CardDAV access
- Endpoint: `/wp/v2/users/{userId}/application-passwords`

## Import/Export Services

**Google Contacts Import:**
- CSV import from Google Contacts export
- Endpoints:
  - `/prm/v1/import/google-contacts/validate`
  - `/prm/v1/import/google-contacts`
- Photo importing capability
- Implementation: `includes/class-google-contacts-import.php`

**Monica CRM Import:**
- SQL database export import
- Endpoints:
  - `/prm/v1/import/monica/validate`
  - `/prm/v1/import/monica`
- Photo sideloading from Monica instance
- Implementation: `includes/class-monica-import.php`

**vCard Import/Export:**
- Standard vCard format (2.1, 3.0, 4.0)
- Import: `includes/class-vcard-import.php`
- Export: `/prm/v1/export/vcard`
- Implementation: `includes/class-vcard-export.php`

**CSV Export:**
- Google Contacts CSV format
- Endpoint: `/prm/v1/export/google-csv`

## Calendar & Sync Services

**CardDAV Server:**
- Contact synchronization protocol
- Auto-discovery: `/.well-known/carddav` (redirects to `/carddav/`)
- Powered by Sabre/DAV library
- Implementation:
  - `includes/class-carddav-server.php`
  - `includes/carddav/class-carddav-backend.php`
  - `includes/carddav/class-principal-backend.php`
  - `includes/carddav/class-auth-backend.php`

**iCal Feed:**
- Calendar feed for important dates
- Route: `/prm-ical/{userId}`
- Implementation: `includes/class-ical-feed.php`

## Monitoring & Observability

**Error Tracking:**
- Console.error() logging in React components
- PHP error_log() for backend errors
- No external error tracking service detected

**Analytics:**
- Not detected

**Logs:**
- Standard WordPress/PHP logging
- No structured logging detected

## Notifications

**Email:**
- WordPress wp_mail() integration
- HTML email formatting
- Digest delivery system
- Endpoints:
  - `/prm/v1/user/notification-channels`
  - `/prm/v1/user/notification-time`
- Implementation: `includes/class-notification-channels.php`

**Reminders:**
- WordPress cron for scheduled delivery
- Multi-channel dispatch (Email, Slack)
- Endpoints:
  - `/prm/v1/reminders`
  - `/prm/v1/reminders/trigger`
  - `/prm/v1/reminders/reschedule-cron`
- Implementation: `includes/class-reminders.php`

## Environment Configuration

**Development:**
- Vite dev server on `http://localhost:5173`
- WordPress local environment required
- Config passed via `window.prmConfig`

**Production:**
- Static assets in `dist/` served by WordPress
- Asset manifest for cache busting
- Environment constants in `wp-config.php`

## Webhooks & Callbacks

**Incoming:**
- Slack events: `/prm/v1/slack/events`
  - Signature verification via Slack signing secret
- Slack commands: `/prm/v1/slack/commands`

**Outgoing:**
- Slack webhook notifications to user-configured channels
- Endpoint: `/prm/v1/user/slack-webhook`

## Version Management

**PWA Cache Invalidation:**
- Version check endpoint: `/prm/v1/version`
- Theme version from `style.css`
- Implementation: `src/hooks/useVersionCheck.js`

---

*Integration audit: 2025-01-13*
*Update when adding/removing external services*
