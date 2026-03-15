<!-- Member administration CRM for sports clubs, built as a WordPress theme with React SPA frontend -->

<div align="center">

# Rondo Club

[![CI](https://github.com/RondoHQ/rondo-club/actions/workflows/ci.yml/badge.svg)](https://github.com/RondoHQ/rondo-club/actions/workflows/ci.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg)](https://www.php.net/)
[![React 18](https://img.shields.io/badge/React-18-61dafb.svg)](https://react.dev/)

**Member administration for sports clubs, built as a React-powered WordPress theme.**

[Website](https://rondo.club/) &bull; [Documentation](https://developer.rondo.club/) &bull; [Changelog](CHANGELOG.md)

</div>

---

Rondo Club replaces spreadsheets and scattered admin tools with a modern single-page application for managing your sports club. It runs as a WordPress theme — install it, activate ACF Pro, and your club board has a real web app for member management.

## Features

- **People management** — contacts, photos, work history, and custom fields
- **Teams & committees** — organize members into teams with logos, staff, and contact info
- **Dashboard** — birthday reminders, upcoming events, activity timeline
- **Volunteer tracking** — VOG (background check) compliance and volunteer status
- **Membership fees** — per-season fee categories with Mollie payment integration and installment plans
- **Discipline cases** — incident tracking with invoicing
- **Calendar** — Google Calendar and CalDAV sync (iCloud, Fastmail, Nextcloud)
- **Feedback system** — collect and process user feedback with agent workflow
- **Todos** — task management linked to people
- **Search** — global search across all people, teams, and committees
- **iCal feeds** — subscribe to club calendars from any calendar app
- **Multi-user** — shared access model with WordPress authentication and role-based access
- **Dark mode** — automatic theme switching
- **PWA** — installable as a mobile app with offline support

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [Advanced Custom Fields Pro](https://www.advancedcustomfields.com/pro/)
- Node.js 18+ (for development only)

## Quick Start

```bash
# Clone the repository
git clone https://github.com/RondoHQ/rondo-club.git
cd rondo-club

# Install dependencies
npm install
composer install

# Start development server (requires WP_DEBUG = true in wp-config.php)
npm run dev
```

The theme auto-detects the Vite dev server at `http://localhost:5173` when `WP_DEBUG` is enabled.

For production, `npm run build` generates optimized assets in `dist/`.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 18, React Router 6, TanStack Query, Tailwind CSS v4 |
| **Backend** | WordPress, PHP 8.0+, ACF Pro |
| **Build** | Vite 5.0 |
| **Payments** | Mollie (Payment Links API) |
| **Sync** | [Rondo Sync](https://github.com/jdevalk/sportlink-sync) (Sportlink integration) |

## Configuration

Rondo Club uses PHP constants in `wp-config.php`. The only required constant is the encryption key:

```bash
# Generate and set encryption key via WP-CLI
wp config set RONDO_ENCRYPTION_KEY "$(php -r 'echo bin2hex(random_bytes(16));')" --type=constant
```

<details>
<summary>All configuration constants</summary>

| Constant | Purpose | Required |
|----------|---------|----------|
| `RONDO_ENCRYPTION_KEY` | Encryption for OAuth tokens | Yes |
| `GOOGLE_OAUTH_CLIENT_ID` | Google OAuth client ID (Calendar + Contacts) | No |
| `GOOGLE_OAUTH_CLIENT_SECRET` | Google OAuth client secret | No |

### Google Integration (Optional)

1. Enable **Google Calendar API** and **Google People API** in [Google Cloud Console](https://console.cloud.google.com/)
2. Create OAuth 2.0 credentials with redirect URIs:
   - `https://your-domain.com/wp-json/rondo/v1/calendar/auth/google/callback`
   - `https://your-domain.com/wp-json/rondo/v1/contacts/auth/google/callback`
3. Add to `wp-config.php`:

```php
define('GOOGLE_OAUTH_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_OAUTH_CLIENT_SECRET', 'your-client-secret');
```

### CalDAV Integration (Optional)

No server-side configuration needed. Users add calendar connections via **Settings > Calendars** in the app. Supports iCloud, Fastmail, Nextcloud, and any CalDAV-compatible server.

</details>

## Development

```bash
npm run dev      # Start Vite dev server (port 5173, HMR)
npm run build    # Production build to dist/
npm run lint     # ESLint (zero warnings policy)
npm run preview  # Preview production build
```

### Project Structure

```
rondo-club/
├── functions.php       # Theme init, class loading, asset enqueuing
├── includes/           # ~50 PHP classes (REST controllers, post types, etc.)
├── acf-json/           # ACF field group definitions (version controlled)
├── src/
│   ├── main.jsx        # React entry point
│   ├── router.jsx      # Routes with lazy loading
│   ├── api/client.js   # Axios client with WP nonce injection
│   ├── hooks/          # Custom React hooks (useAuth, usePeople, etc.)
│   ├── pages/          # Route components
│   └── components/     # Shared UI components
├── bin/deploy.sh       # Production deployment script
└── dist/               # Built assets (generated)
```

### API

The theme exposes two REST API namespaces:

- **`/wp/v2/`** — Standard WordPress REST for people, teams, committees (with ACF fields)
- **`/rondo/v1/`** — Custom endpoints for dashboard, search, timeline, reminders, payments

Full API reference: [developer.rondo.club/api](https://developer.rondo.club/api/)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, coding standards, and the PR process.

## Security

To report a vulnerability, see [SECURITY.md](SECURITY.md).

## License

[GPL v2 or later](LICENSE)
