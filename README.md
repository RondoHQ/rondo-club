<!-- Member administration CRM for sports clubs, built as a WordPress theme with React SPA frontend -->
# Stadion

A member administration CRM for sports clubs, built as a React-powered WordPress theme. Tracks contacts, team and committee roles, volunteer status, and VOG compliance — with [Sportlink sync](https://github.com/jdevalk/sportlink-sync), calendar integration, and multi-user support.
## Requirements

- WordPress 6.0+
- PHP 8.0+
- [Advanced Custom Fields Pro](https://www.advancedcustomfields.com/pro/)
- Node.js 18+ (for development only)

## Installation

### For Users (Pre-built)

1. Download the latest release (includes pre-built `/dist` folder)
2. Upload to `/wp-content/themes/stadion-theme/`
3. Activate the theme in WordPress
4. Make sure ACF Pro is installed and activated

### For Developers

```bash
# Clone the repository
git clone https://github.com/yourusername/stadion-theme.git
cd stadion-theme

# Install dependencies
npm install

# Development mode (with hot reload)
npm run dev

# Build for production
npm run build
```

## Configuration

Stadion uses PHP constants defined in `wp-config.php` for configuration. This is the standard WordPress approach and ensures settings are secure and environment-specific.

### Quick Setup with WP-CLI

```bash
# Required: Generate and set encryption key
wp config set STADION_ENCRYPTION_KEY "$(php -r 'echo bin2hex(random_bytes(16));')" --type=constant

# Optional: Slack integration
wp config set STADION_SLACK_CLIENT_ID 'your-client-id' --type=constant
wp config set STADION_SLACK_CLIENT_SECRET 'your-client-secret' --type=constant
wp config set STADION_SLACK_SIGNING_SECRET 'your-signing-secret' --type=constant

# Optional: Google integration (Calendar + Contacts)
wp config set GOOGLE_OAUTH_CLIENT_ID 'your-client-id.apps.googleusercontent.com' --type=constant
wp config set GOOGLE_OAUTH_CLIENT_SECRET 'your-client-secret' --type=constant
```

### Configuration Constants Reference

| Constant | Purpose | Required | Generation/Source |
|----------|---------|----------|-------------------|
| `STADION_ENCRYPTION_KEY` | Encryption for OAuth tokens | Yes | `php -r "echo bin2hex(random_bytes(16));"` |
| `STADION_SLACK_CLIENT_ID` | Slack OAuth app client ID | Optional | Slack API Dashboard |
| `STADION_SLACK_CLIENT_SECRET` | Slack OAuth app client secret | Optional | Slack API Dashboard |
| `STADION_SLACK_SIGNING_SECRET` | Slack webhook verification | Optional | Slack API Dashboard |
| `GOOGLE_OAUTH_CLIENT_ID` | Google OAuth client ID (Calendar + Contacts) | Optional | Google Cloud Console |
| `GOOGLE_OAUTH_CLIENT_SECRET` | Google OAuth client secret | Optional | Google Cloud Console |

### Encryption (Required)

The encryption key is required for storing OAuth tokens securely. Generate a 32-byte key:

```bash
php -r "echo bin2hex(random_bytes(16));"
```

Add to `wp-config.php`:

```php
define('STADION_ENCRYPTION_KEY', 'your-generated-32-byte-key-here');
```

### Slack Integration (Optional)

To enable Slack notifications for reminders:

1. Create a Slack app at [https://api.slack.com/apps](https://api.slack.com/apps)
2. Under **OAuth & Permissions**, add the `chat:write` scope
3. Install the app to your workspace
4. Copy credentials from **Basic Information**

```php
define('STADION_SLACK_CLIENT_ID', '1234567890.1234567890');
define('STADION_SLACK_CLIENT_SECRET', 'your-client-secret');
define('STADION_SLACK_SIGNING_SECRET', 'your-signing-secret');
```

### Google Integration (Optional)

Google OAuth credentials are shared between Calendar sync and Contacts sync.

**Setup steps:**

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create or select a project
3. Enable APIs (APIs & Services > Library):
   - **Google Calendar API** (for calendar sync)
   - **Google People API** (for contacts sync)
4. Configure **OAuth consent screen** (APIs & Services > OAuth consent screen)
   - Add scopes: `.../auth/calendar.readonly`, `.../auth/contacts`
5. Create **OAuth 2.0 credentials** (APIs & Services > Credentials)
   - Application type: Web application
   - Authorized redirect URIs:
     - `https://your-domain.com/wp-json/stadion/v1/calendar/auth/google/callback`
     - `https://your-domain.com/wp-json/stadion/v1/contacts/auth/google/callback`

```php
define('GOOGLE_OAUTH_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_OAUTH_CLIENT_SECRET', 'your-client-secret');
```

**Note:** These credentials are shared between Calendar and Contacts sync. Scopes are requested incrementally when each feature is first used.

### CalDAV Integration (Optional)

CalDAV integration requires no server-side configuration. Users add their own calendar connections via **Settings > Calendars > Add CalDAV Calendar** in the application.

Supported providers:
- **iCloud** - Use `https://caldav.icloud.com` with an app-specific password
- **Fastmail** - Use `https://caldav.fastmail.com/dav/calendars/user/{email}/`
- **Nextcloud** - Use `https://yourserver.com/remote.php/dav/calendars/{user}/`
- **Generic CalDAV** - Any server supporting the CalDAV standard

### Example wp-config.php

```php
// Stadion Configuration
// ====================

// Required: Encryption key (32 bytes)
define('STADION_ENCRYPTION_KEY', 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4');

// Optional: Slack Integration
define('STADION_SLACK_CLIENT_ID', '1234567890.1234567890');
define('STADION_SLACK_CLIENT_SECRET', 'abcdef1234567890abcdef1234567890');
define('STADION_SLACK_SIGNING_SECRET', '1234abcd5678efgh9012ijkl3456mnop');

// Optional: Google Integration (Calendar + Contacts)
define('GOOGLE_OAUTH_CLIENT_ID', '123456789-abc123def456.apps.googleusercontent.com');
define('GOOGLE_OAUTH_CLIENT_SECRET', 'GOCSPX-abcdefghijklmnop');
```

## Development

### File Structure

```
stadion-theme/
├── style.css           # Theme metadata (required by WordPress)
├── functions.php       # Theme functions and asset loading
├── index.php           # Single template that loads React
├── package.json        # NPM dependencies
├── vite.config.js      # Vite build configuration
├── tailwind.config.js  # Tailwind CSS configuration
├── src/
│   ├── main.jsx        # React entry point
│   ├── App.jsx         # Main app with routing
│   ├── index.css       # Global styles + Tailwind
│   ├── api/
│   │   └── client.js   # API client for WordPress REST
│   ├── components/
│   │   ├── layout/
│   │   │   └── Layout.jsx
│   │   └── ui/         # Reusable UI components
│   ├── hooks/
│   │   ├── useAuth.js
│   │   ├── usePeople.js
│   │   └── useDashboard.js
│   ├── pages/
│   │   ├── Dashboard.jsx
│   │   ├── Login.jsx
│   │   ├── People/
│   │   ├── Teams/
│   │   ├── Dates/
│   │   └── Settings/
│   ├── stores/         # Zustand stores (if needed)
│   └── utils/          # Helper functions
└── dist/               # Built files (generated)
```

### Development Server

The theme supports Vite's development server with hot module replacement:

1. Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   ```

2. Start the dev server:
   ```bash
   npm run dev
   ```

3. The theme will load React from `http://localhost:5173`

### Building for Production

```bash
npm run build
```

This generates:
- `dist/assets/index-[hash].js` - Main JavaScript bundle
- `dist/assets/index-[hash].css` - Compiled Tailwind CSS
- `dist/manifest.json` - Asset manifest for WordPress

### WordPress Integration

The theme automatically:
- Passes WordPress configuration to React via `window.stadionConfig`
- Handles authentication state
- Sets up REST API nonces for secure requests

Configuration available in React:
```javascript
window.stadionConfig = {
  apiUrl: '/wp-json/',
  nonce: 'abc123...',
  siteUrl: 'https://example.com',
  siteName: 'My CRM',
  userId: 1,
  isLoggedIn: true,
  loginUrl: '/wp-login.php',
  logoutUrl: '/wp-login.php?action=logout',
  adminUrl: '/wp-admin/',
  themeUrl: '/wp-content/themes/stadion-theme/',
};
```

## Tech Stack

- **React 18** - UI framework
- **React Router 6** - Client-side routing
- **TanStack Query** - Server state management
- **Tailwind CSS** - Styling
- **Vite** - Build tool
- **Axios** - HTTP client
- **date-fns** - Date formatting
- **Lucide React** - Icons
- **React Hook Form** - Form handling

## API Integration

The theme uses two API namespaces:

### WordPress REST API (`/wp/v2/`)
- Standard CRUD for people, teams, dates
- Built-in WordPress authentication
- ACF fields included in responses

### Custom PRM API (`/stadion/v1/`)
- Dashboard summary
- Timeline (notes + activities)
- Global search
- Upcoming reminders
- Team employees

## Authentication

The theme relies on WordPress authentication:
- Users must be logged into WordPress
- REST API requests include `X-WP-Nonce` header
- Unauthenticated users are redirected to wp-login.php

## Customization

### Tailwind Theme

Edit `tailwind.config.js` to customize:
- Colors (primary palette)
- Fonts
- Spacing
- Breakpoints

### Component Styling

Global styles and component classes are in `src/index.css`:
- `.btn`, `.btn-primary`, `.btn-secondary`
- `.input`, `.label`
- `.card`

## Troubleshooting

### Assets not loading
- Check that `npm run build` completed successfully
- Verify `dist/manifest.json` exists
- Check browser console for 404 errors

### API errors
- Ensure you're logged into WordPress
- Check that ACF Pro is active
- Verify REST API is accessible at `/wp-json/`

### CORS issues in development
- Make sure Vite dev server is running on port 5173
- Check `WP_DEBUG` is set to `true`

## Documentation

Comprehensive documentation is available:

- **[Configuration](#configuration)** - wp-config.php constants for integrations (in this document)
- **[Relationships](docs/relationships.md)** - How the bidirectional relationship system works
- **[Relationship Types](docs/relationship-types.md)** - Guide to configuring relationship types and inverse mappings
- **[Architecture](docs/architecture.md)** - Technical architecture and extension points

## License

GPL v2 or later
