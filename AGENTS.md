# AGENTS.md

This file provides guidance to Agents when working with code in this repository.

## Project Overview

**Stadion** is a personal CRM system built as a WordPress theme with integrated backend functionality and a modern React SPA frontend. Tracks contacts, teams, important dates, and interactions with user-specific access control.

**Tech Stack:**
- Backend: WordPress 6.0+, PHP 8.0+, ACF Pro (required)
- Frontend: React 18, React Router 6, TanStack Query, Tailwind CSS 3.4
- Build: Vite 5.0

## Development Commands

All commands run from `stadion/`:

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
- `STADION_Post_Types` - Registers Person, Team, Important Date CPTs
- `STADION_Taxonomies` - Registers labels and relationship types
- `STADION_Auto_Title` - Auto-generates post titles
- `STADION_Access_Control` - Row-level user data filtering at query and REST levels
- `STADION_User_Roles` - Registers custom "Stadion User" role with minimal permissions
- `STADION_REST_API` - Custom `/stadion/v1/` endpoints (dashboard, search, timeline, reminders)
- `STADION_Comment_Types` - Notes and Activities system using comments
- `STADION_Reminders` - Daily digest reminder system with multi-channel support (Email, Slack)
- `STADION_Notification_Channel` - Abstract base class for notification channels
- `STADION_Email_Channel` - Email notification implementation
- `STADION_Slack_Channel` - Slack webhook notification implementation
- `STADION_Monica_Import` - Monica CRM import functionality

**ACF field groups** are stored as JSON in `acf-json/` for version control.

### Frontend (React SPA)

Entry point: `src/main.jsx`

**React app structure:**
- `App.jsx` - Routing with ProtectedRoute wrapper
- `api/client.js` - Axios client with WordPress nonce injection
- `hooks/` - Custom hooks (useAuth, usePeople, useDashboard)
- `pages/` - Route components for People, Teams, Dates, Settings

**State management:**
- TanStack Query for server state/caching
- WordPress config via window globals (`wpApiSettings`)
- Zustand available for client state

**API client uses two namespaces:**
- `/wp/v2/` - Standard WordPress REST (people, teams, important-dates)
- `/stadion/v1/` - Custom endpoints (dashboard, search, timeline)

## Data Model

**Custom Post Types:**
- `person` - Contact records with relationships, work history, photo gallery
- `team` - Teams with logo, industry, contact info (post type slug remains `team` for backward compatibility)
- `important_date` - Recurring dates linked to people (reminders sent via daily digest)

**Taxonomies:**
- `person_label`, `team_label` - Tags
- `relationship_type` - Relationship classifications
- `date_type` - Date categorization

## Access Control

- Users only see posts they created themselves
- Admins are restricted on the frontend (like regular users) but have full access in WordPress admin area
- Filtering applied at WP_Query level and REST API response level
- Authentication via WordPress session with REST nonce (`X-WP-Nonce` header)

## User Roles

- **Stadion User** - Custom role created automatically on theme activation
  - Minimal permissions: can create/edit/delete their own people and teams, upload files
  - Cannot access WordPress admin settings, manage other users, or install plugins/themes
  - Role is automatically removed on theme deactivation (users reassigned to Subscriber)

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

**Adding REST endpoints:** Extend `STADION_REST_API` class in `includes/class-rest-api.php`

**Adding React pages:** Create component in `src/pages/`, add route in `src/App.jsx`

**Adding PHP classes:** Create new class file in `includes/`, load it in `functions.php` via `stadion_init()`

## Required rules for every change

### Rule 0: Use WordPress & ACF native data models

**NEVER create custom database tables.** Always use WordPress native data storage:

| Data Type | Storage Method |
|-----------|---------------|
| Entities (contacts, events, etc.) | Custom Post Types (`register_post_type`) |
| Entity metadata | Post meta (`update_post_meta`, `get_post_meta`) |
| Complex/repeatable fields | ACF field groups (stored in `acf-json/`) |
| Categories/tags | Custom Taxonomies (`register_taxonomy`) |
| User settings | User meta (`update_user_meta`, `get_user_meta`) |
| Site-wide settings | Options API (`update_option`, `get_option`) |
| Temporary/cached data | Transients API (`set_transient`, `get_transient`) |

**Use WordPress native functions:**
- Queries: `WP_Query`, `get_posts()`, `get_users()` - never raw SQL
- CRUD: `wp_insert_post`, `wp_update_post`, `wp_delete_post`
- Cron: `wp_schedule_event`, `wp_cron` hooks
- REST: Extend `WP_REST_Controller` or register via `register_rest_route`
- Caching: Use WordPress object cache, transients, or WP_Query caching

**ACF for complex fields:**
- Use ACF for repeaters, flexible content, relationships
- Store field groups in `acf-json/` for version control
- Access via `get_field()`, `update_field()`, or native `get_post_meta()` with ACF field keys

### Rule 1: Semantic Versioning

### What is it?

*Semantic Versioning* follows the format: ⁠ MAJOR.MINOR.PATCH ⁠ (bijv. ⁠ 1.10.7 ⁠)

* ⁠*MAJOR* (x.0.0): Breaking changes which break existing functionality
* ⁠*MINOR* (0.x.0): New features that are backward compatible
* ⁠*PATCH* (0.0.x): Bug fixes and small improvements

Update the version of the theme in style.css and package.json after every milestone, following the semantic versioning system.

## Rule 2: Update the Changelog

After each milestone, add a changelog entry in in [Keep a Changelog](https://keepachangelog.com/) format:

* ⁠*Added*: New features
* ⁠*Changed*: Changes in existing functionality
* ⁠*Fixed*: Bug fixes
* ⁠*Removed*: Removed features

You will find the changelog in /CHANGELOG.md

### Rule 3: Don't Repeat Yourself (DRY)

Apply DRY principles to all coding. If you see multiple changes you're doing are the same code, make sure you properly apply DRY principles and clean up the code where possible.

### Rule 4: 95% sure rule

If you're less than 95% sure about the changes you're going to make: *ASK QUESTIONS!*

*When you should ask:*
* Before making big changes
* For architectural decisions
* When you have unclear requirements
* When there are trade-offs between options
* Before making big architectural changes

*How you should ask:*
* ⁠Present up to 3 options with pros and cons
* Select your recommended option and tell me why
* Wait for approval before implementing
* When making big architectural changes, explain what you're going to do and ask for confirmation.

### Rule 5: Self testing

Test your changes as much as you can before claiming something works.

### Rule 6: Update documentation

Update the documentation in the /docs folder with all relevant information from the change. If the (sub-)system you made changes to is not documented yet, document that system too.

### Rule 7: Git Commit & Push

#### What is it?

*ALWAYS* commit & push after every milestone phase, with clear commit messages:

*Format*: ⁠ 

type: Description ⁠
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

### Rule 8: Deploy to Production

**ALWAYS deploy to production BEFORE asking for verification or UAT.** The user tests on production, not locally. Deploy after every phase execution, before presenting verification checklists.

Use the deployment script in `bin/deploy.sh`:

```bash
# Standard deploy (excludes node_modules)
bin/deploy.sh

# Deploy including node_modules (after npm install/update)
bin/deploy.sh --with-node-modules
```

The script reads server credentials from `.env` (see `.env.example` for required variables). It:
1. Syncs `dist/` folder with `--delete` to remove stale build artifacts
2. Syncs remaining theme files (excluding `.git`, `node_modules`, `dist`)
3. Clears WordPress and SiteGround caches

**Production URL:** See `DEPLOY_PRODUCTION_URL` in `.env`
