# Technology Stack

**Analysis Date:** 2025-01-13

## Languages

**Primary:**
- JavaScript (JSX) - All frontend application code (`src/**/*.jsx`)
- PHP 8.0+ - WordPress theme backend (`includes/**/*.php`, `functions.php`)

**Secondary:**
- CSS - Tailwind utilities and custom styles (`src/index.css`)

## Runtime

**Environment:**
- Node.js - Development and build tooling
- PHP 8.0+ - Required by `composer.json`
- WordPress 6.0+ - Platform foundation (`style.css`)

**Package Manager:**
- npm - Node package manager (`package.json`, `package-lock.json`)
- Composer - PHP package manager (`composer.json`, `composer.lock`)

## Frameworks

**Core:**
- React 18.2.0 - UI framework (`package.json`, `src/main.jsx`)
- WordPress 6.0+ - Backend CMS platform (`style.css`)

**Routing:**
- React Router 6.21.0 - Client-side SPA routing (`src/App.jsx`)

**Styling:**
- Tailwind CSS 3.4.0 - Utility-first CSS framework (`tailwind.config.js`)
- PostCSS 8.4.0 - CSS transformation (`postcss.config.js`)

**Build/Dev:**
- Vite 5.0.0 - Build tool and dev server (`vite.config.js`)
- Autoprefixer 10.4.0 - CSS vendor prefixing

## Key Dependencies

**Critical:**
- @tanstack/react-query 5.17.0 - Server state management (`src/main.jsx`)
- axios 1.6.0 - HTTP client for REST API (`src/api/client.js`)
- zustand 4.4.0 - Client state management
- react-hook-form 7.49.0 - Form handling and validation

**UI Components:**
- lucide-react 0.309.0 - Icon library (`src/App.jsx`)
- @tiptap/react 3.15.3 - Rich text editor (`src/components/RichTextEditor.jsx`)
- vis-network 10.0.2 - Network graph visualization for family tree
- vis-data 8.0.3 - Data handling for visualization

**Utilities:**
- date-fns 3.2.0 - Date formatting and manipulation
- clsx 2.1.0 - Conditional CSS class composition

**PHP Infrastructure:**
- sabre/dav ^4.6 - CardDAV/WebDAV server implementation (`composer.json`)

**Code Quality:**
- eslint 8.55.0 - JavaScript linter
- eslint-plugin-react - React-specific linting rules
- eslint-plugin-react-hooks - Hooks linting rules
- eslint-plugin-react-refresh - Fast refresh linting

## Configuration

**Environment:**
- WordPress constants for Slack integration:
  - `STADION_SLACK_CLIENT_ID`
  - `STADION_SLACK_CLIENT_SECRET`
  - `STADION_SLACK_SIGNING_SECRET`
- Runtime config passed via `window.stadionConfig` object

**Build:**
- `vite.config.js` - Vite bundler configuration with WordPress integration
- `tailwind.config.js` - Tailwind theme customization
- `postcss.config.js` - PostCSS plugins (tailwindcss, autoprefixer)

## Platform Requirements

**Development:**
- macOS/Linux/Windows (any platform with Node.js and PHP)
- Node.js with npm
- PHP 8.0+ with Composer
- WordPress local development environment

**Production:**
- WordPress 6.0+ installation
- PHP 8.0+ runtime
- MySQL/MariaDB database
- ACF Pro plugin (required dependency)
- Web server (Apache/Nginx)

---

*Stack analysis: 2025-01-13*
*Update after major dependency changes*
