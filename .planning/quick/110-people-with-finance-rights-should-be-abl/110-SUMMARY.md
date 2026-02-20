---
phase: 110-finance-settings-access
plan: 01
subsystem: finance-settings-access
tags: [access-control, rest-api, finance, navigation]
dependency_graph:
  requires: []
  provides: [financieel-users-can-access-finance-settings]
  affects: [class-rest-base, class-rest-api, layout-navigation]
tech_stack:
  added: []
  patterns: [capability-based-permission-callback, requiresFinancieel-nav-filter]
key_files:
  created: []
  modified:
    - includes/class-rest-base.php
    - includes/class-rest-api.php
    - src/components/layout/Layout.jsx
decisions:
  - Added dedicated check_financieel_permission() to RestBase instead of reusing check_admin_permission — clean separation, follows existing pattern
  - Kept adminOnly filter logic in Layout.jsx untouched — other nav items may use it in future
metrics:
  duration: ~5 minutes
  completed: 2026-02-20T13:46:53Z
  tasks: 2
  files: 3
---

# Quick Task 110: Finance Settings Access for Financieel Users

**One-liner:** Grant `financieel` capability users access to Finance Settings API and sidebar nav without requiring WordPress admin.

## What Changed

### Task 1: check_financieel_permission in RestBase + REST route update

**`includes/class-rest-base.php`** — Added new permission method after `check_admin_permission()`:

```php
/**
 * Check if the current user has the financieel capability.
 *
 * @return bool True if user has financieel capability.
 */
public function check_financieel_permission() {
    return current_user_can( 'financieel' );
}
```

**`includes/class-rest-api.php`** — Updated the `/finance/settings` route registration:
- Comment changed from `// Finance settings (admin only)` to `// Finance settings (financieel capability required)`
- Both GET (`READABLE`) and POST (`CREATABLE`) `permission_callback` changed from `check_admin_permission` to `check_financieel_permission`

**Commit:** `a51fde9d`

### Task 2: Remove adminOnly from Instellingen nav item

**`src/components/layout/Layout.jsx`** — Removed `adminOnly: true` from the Financiën Instellingen nav entry:

Before:
```js
{ name: 'Instellingen', href: '/financien/instellingen', icon: Settings, indent: true, requiresFinancieel: true, adminOnly: true },
```

After:
```js
{ name: 'Instellingen', href: '/financien/instellingen', icon: Settings, indent: true, requiresFinancieel: true },
```

The `requiresFinancieel: true` guard already ensures only users with the `financieel` capability see the menu item. The `adminOnly` filter logic at line 107 was kept intact for future use by other items.

**Commit:** `a33f152a`

## Why

Finance officers hold the `financieel` capability but are not WordPress admins. Previously the Finance Settings page (both the sidebar entry and the REST API backing it) required `manage_options`, locking them out. This change aligns access control with the actual role model: `financieel` capability = access to all financial features including configuration.

## Verification

All checks passed:
- `check_financieel_permission` defined once in class-rest-base.php
- Two matches in class-rest-api.php (GET + POST on /finance/settings)
- `adminOnly` no longer present on the Instellingen/financien nav item
- `npm run lint` — 0 warnings
- `npm run build` — succeeded, 94 entries precached
- Deployed to production: https://rondo.svawc.nl/

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- `includes/class-rest-base.php` — modified, check_financieel_permission present
- `includes/class-rest-api.php` — modified, 2x check_financieel_permission on finance/settings
- `src/components/layout/Layout.jsx` — modified, adminOnly removed from Instellingen item
- Commits a51fde9d and a33f152a exist in git log
