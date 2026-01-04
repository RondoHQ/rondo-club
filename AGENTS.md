# AGENTS.md

This file provides guidance to Agents when working with code in this repository.

## Project Overview

**Caelis** is a personal CRM system built as a WordPress theme with integrated backend functionality and a modern React SPA frontend. Tracks contacts, companies, important dates, and interactions with user-specific access control.

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

**Important:** Always run `npm run build` after making changes to the frontend to ensure production assets are updated.

## Development Setup

1. Set `WP_DEBUG = true` in `wp-config.php` for development mode
2. Theme auto-detects Vite dev server at `http://localhost:5173` when debug is enabled
3. Production mode loads assets from `dist/` via manifest.json

## Architecture

### Backend (Theme Functions)

Entry point: `functions.php`

**Initialization flow:**
- Checks for ACF Pro dependency
- Loads 8 classes from `includes/` on `after_setup_theme` and `plugins_loaded`
- Registers activation/deactivation hooks for rewrites and cron via theme activation hooks

**Key classes:**
- `PRM_Post_Types` - Registers Person, Company, Important Date CPTs
- `PRM_Taxonomies` - Registers labels and relationship types
- `PRM_Auto_Title` - Auto-generates post titles
- `PRM_Access_Control` - Row-level user data filtering at query and REST levels
- `PRM_REST_API` - Custom `/prm/v1/` endpoints (dashboard, search, timeline, reminders)
- `PRM_Comment_Types` - Notes and Activities system using comments
- `PRM_Reminders` - Daily cron job for upcoming date notifications
- `PRM_Monica_Import` - Monica CRM import functionality

**ACF field groups** are stored as JSON in `acf-json/` for version control.

### Frontend (React SPA)

Entry point: `src/main.jsx`

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

**Backend (PHP):**
- `functions.php` - Main theme initialization, asset loading, SPA routing setup
- `includes/class-rest-api.php` - Custom API endpoints
- `includes/class-access-control.php` - Permission logic
- `includes/class-post-types.php` - Custom post type registration
- `includes/class-taxonomies.php` - Taxonomy registration

**Frontend (React):**
- `src/main.jsx` - React app entry point
- `src/App.jsx` - Main routing
- `src/api/client.js` - API request configuration
- `src/hooks/` - Custom React hooks
- `src/pages/` - Route components
- `vite.config.js` - Build configuration

## Git Workflow

This is a single repository containing both backend (PHP) and frontend (React) code. All changes should be committed together to keep the system in sync.

## Extending the System

**Adding ACF fields:** Edit in WordPress admin when `WP_DEBUG` is true; changes auto-save to `acf-json/`

**Adding REST endpoints:** Extend `PRM_REST_API` class in `includes/class-rest-api.php`

**Adding React pages:** Create component in `src/pages/`, add route in `src/App.jsx`

**Adding PHP classes:** Create new class file in `includes/`, load it in `functions.php` via `prm_init()`

## Required rules for every change

### Rule 1: Semantic Versioning

### What is it?

*Semantic Versioning* follows the format: ⁠ MAJOR.MINOR.PATCH ⁠ (bijv. ⁠ 1.10.7 ⁠)

* ⁠*MAJOR* (x.0.0): Breaking changes which break existing functionality
* ⁠*MINOR* (0.x.0): New features that are backward compatible
* ⁠*PATCH* (0.0.x): Bug fixes and small improvements

Update the version of the theme in style.css and package.json with every change, following the semantic versioning system.

## Rule 2: Update the Changelog

Always add a changelog entry in in [Keep a Changelog](https://keepachangelog.com/) format:

* ⁠*Added*: New features
* ⁠*Changed*: Changes in existing functionality
* ⁠*Fixed*: Bug fixes
* ⁠*Removed*: Removed features

You will find the changelog in /CHANGELOG.md

### Rule 3: Update documentation

Update the documentation in the /docs folder with all relevant information from the change. If the (sub-)system you made changes to is not documented yet, document that system too.

### Rule 4: Git Commit & Push

#### What is it?

*ALWAYS* commit & push after every change, with clear commit messages:

*Format*: ⁠ type: Description ⁠
* ⁠⁠ feat: ⁠ - New feature
* ⁠⁠ fix: ⁠ - Bug fix
* ⁠⁠ chore: ⁠ - Maintenance (version updates, config changes)
* ⁠⁠ docs: ⁠ - Documentation
* ⁠⁠ refactor: ⁠ - Code refactoring
* ⁠⁠ perf: ⁠ - Performance improvement
* ⁠⁠ style: ⁠ - Code formatting

*Workflow:*
```⁠ bash
cd "<project directory>"
git add -A
git commit -m "<git commit summary>"
git push
```

### Rule 5: Deploy to Production

After every commit & push, deploy the changes to the production server:

```bash
# Sync theme files to production (excludes .git and node_modules)
rsync -avz --exclude='.git' --exclude='node_modules' -e "ssh -p 18765" /Users/joostdevalk/Code/joost-crm/personal-crm-theme/ u25-eninwxjgiulh@c1130624.sgvps.net:~/www/cael.is/public_html/wp-content/themes/caelis/

# Clear caches on production
ssh u25-eninwxjgiulh@c1130624.sgvps.net -p 18765 "cd ~/www/cael.is/public_html && wp cache flush && wp sg purge"
```

**Production server details:**
- Host: `c1130624.sgvps.net`
- Port: `18765`
- User: `u25-eninwxjgiulh`
- WordPress path: `~/www/cael.is/public_html/`
- Theme path: `~/www/cael.is/public_html/wp-content/themes/caelis/`
- Production URL: `https://cael.is/`

### Rule 6: 95% sure rule

If you're less than 95% sure about the changes you're going to make: *ASK QUESTIONS!*

*When you should ask:*
* Before making big changes
* For architectural decisions
* When you have unclear requirements
* When there are trade-offs between options

*How you should ask:*
* ⁠Present up to 3 options with pros and cons
* Select your recommended option and tell me why
* Wait for approval before implementing

### Rule 7: Self testing

Test your changes as much as you can before claiming something works.
