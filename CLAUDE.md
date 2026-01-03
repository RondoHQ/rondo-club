# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Personal CRM system built as a WordPress plugin with a modern React SPA frontend theme. Tracks contacts, companies, important dates, and interactions with user-specific access control.

**Tech Stack:**
- Backend: WordPress 6.0+, PHP 8.0+, ACF Pro (required)
- Frontend: React 18, React Router 6, TanStack Query, Tailwind CSS 3.4
- Build: Vite 5.0

## Development Commands

All commands run from `personal-crm-theme/`:

```bash
npm run dev      # Start Vite dev server (port 5173, HMR enabled)
npm run build    # Production build to dist/
npm run lint     # ESLint check (max-warnings: 0)
npm run preview  # Preview production build
```

**Important:** Always run `npm run build` after making changes to the theme to ensure production assets are updated.

The plugin (`personal-crm-plugin/`) requires no build step.

## Development Setup

1. Set `WP_DEBUG = true` in `wp-config.php` for development mode
2. Theme auto-detects Vite dev server at `http://localhost:5173` when debug is enabled
3. Production mode loads assets from `dist/` via manifest.json

## Architecture

### Plugin (Backend)

Entry point: `personal-crm-plugin/personal-crm.php`

**Initialization flow:**
- Checks for ACF Pro dependency
- Loads 7 classes from `includes/` on `plugins_loaded`
- Registers activation/deactivation hooks for rewrites and cron

**Key classes:**
- `PRM_Post_Types` - Registers Person, Company, Important Date CPTs
- `PRM_Taxonomies` - Registers labels and relationship types
- `PRM_Access_Control` - Row-level user data filtering at query and REST levels
- `PRM_REST_API` - Custom `/prm/v1/` endpoints (dashboard, search, timeline, reminders)
- `PRM_Comment_Types` - Notes and Activities system using comments
- `PRM_Reminders` - Daily cron job for upcoming date notifications

**ACF field groups** are stored as JSON in `personal-crm-plugin/acf-json/` for version control.

### Theme (Frontend)

Entry point: `personal-crm-theme/src/main.jsx`

**React app structure:**
- `App.jsx` - Routing with ProtectedRoute wrapper
- `api/client.js` - Axios client with WordPress nonce injection
- `hooks/` - Custom hooks (useAuth, usePeople, useDashboard)
- `pages/` - Route components for People, Companies, Dates, Settings

**State management:**
- TanStack Query for server state/caching
- WordPress config via window globals (`wpApiSettings`)
- Zustand available for client state

**API client uses two namespaces:**
- `/wp/v2/` - Standard WordPress REST (people, companies, important-dates)
- `/prm/v1/` - Custom endpoints (dashboard, search, timeline)

## Data Model

**Custom Post Types:**
- `person` - Contact records with relationships, work history, photo gallery
- `company` - Organizations with logo, industry, contact info
- `important_date` - Recurring dates with reminder configuration, linked to people

**Taxonomies:**
- `person_label`, `company_label` - Tags
- `relationship_type` - Relationship classifications
- `date_type` - Date categorization

## Access Control

- Users only see posts they created or are explicitly shared with via ACF "shared_with" field
- Admins bypass all restrictions
- Filtering applied at WP_Query level and REST API response level
- Authentication via WordPress session with REST nonce (`X-WP-Nonce` header)

## Key Files

**Plugin:**
- `personal-crm-plugin/personal-crm.php` - Main plugin initialization
- `personal-crm-plugin/includes/class-rest-api.php` - Custom API endpoints
- `personal-crm-plugin/includes/class-access-control.php` - Permission logic

**Theme:**
- `personal-crm-theme/functions.php` - Asset loading, SPA routing setup
- `personal-crm-theme/src/api/client.js` - API request configuration
- `personal-crm-theme/src/App.jsx` - Main routing
- `personal-crm-theme/vite.config.js` - Build configuration

## Git Workflow

This project contains two separate git repositories:
- `personal-crm-plugin/` - Plugin repository
- `personal-crm-theme/` - Theme repository

**When committing changes, always commit to both repositories if changes span both.** Many features require coordinated changes (e.g., adding a REST endpoint in the plugin and consuming it in the theme). Ensure both repos are committed together to keep them in sync.

## Extending the System

**Adding ACF fields:** Edit in WordPress admin when `WP_DEBUG` is true; changes auto-save to `acf-json/`

**Adding REST endpoints:** Extend `PRM_REST_API` class in `includes/class-rest-api.php`

**Adding React pages:** Create component in `src/pages/`, add route in `App.jsx`
