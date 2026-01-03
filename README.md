# Personal CRM Theme

A React-powered WordPress theme for the Personal CRM system. This theme provides a modern, single-page application interface for managing contacts, companies, and important dates.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [Personal CRM Plugin](https://github.com/yourusername/personal-crm-plugin)
- [Advanced Custom Fields Pro](https://www.advancedcustomfields.com/pro/)
- Node.js 18+ (for development only)

## Installation

### For Users (Pre-built)

1. Download the latest release (includes pre-built `/dist` folder)
2. Upload to `/wp-content/themes/personal-crm-theme/`
3. Activate the theme in WordPress
4. Make sure the Personal CRM plugin is installed and activated

### For Developers

```bash
# Clone the repository
git clone https://github.com/yourusername/personal-crm-theme.git
cd personal-crm-theme

# Install dependencies
npm install

# Development mode (with hot reload)
npm run dev

# Build for production
npm run build
```

## Development

### File Structure

```
personal-crm-theme/
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
│   │   ├── Companies/
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
- Passes WordPress configuration to React via `window.prmConfig`
- Handles authentication state
- Sets up REST API nonces for secure requests

Configuration available in React:
```javascript
window.prmConfig = {
  apiUrl: '/wp-json/',
  nonce: 'abc123...',
  siteUrl: 'https://example.com',
  siteName: 'My CRM',
  userId: 1,
  isLoggedIn: true,
  loginUrl: '/wp-login.php',
  logoutUrl: '/wp-login.php?action=logout',
  adminUrl: '/wp-admin/',
  themeUrl: '/wp-content/themes/personal-crm-theme/',
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
- Standard CRUD for people, companies, dates
- Built-in WordPress authentication
- ACF fields included in responses

### Custom PRM API (`/prm/v1/`)
- Dashboard summary
- Timeline (notes + activities)
- Global search
- Upcoming reminders
- Company employees

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
- Check that the Personal CRM plugin is active
- Verify REST API is accessible at `/wp-json/`

### CORS issues in development
- Make sure Vite dev server is running on port 5173
- Check `WP_DEBUG` is set to `true`

## License

GPL v2 or later
