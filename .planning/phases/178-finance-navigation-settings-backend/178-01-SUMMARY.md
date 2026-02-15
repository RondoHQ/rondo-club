---
phase: 178-finance-navigation-settings-backend
plan: 01
subsystem: finance
tags: [navigation, settings, backend, encryption]
dependency_graph:
  requires: [class-credential-encryption, class-club-config, rest-api-base]
  provides: [finance-navigation, finance-config-class, finance-rest-api]
  affects: [sidebar-navigation, router, api-client]
tech_stack:
  added: [FinanceConfig class, finance REST endpoints]
  patterns: [WP Options API, sodium encryption, REST controller pattern]
key_files:
  created:
    - includes/class-finance-config.php
    - src/pages/Finance/FinanceSettings.jsx
  modified:
    - src/components/layout/Layout.jsx
    - src/router.jsx
    - includes/class-rest-api.php
    - functions.php
    - src/api/client.js
decisions:
  - title: "Section headers in navigation"
    rationale: "Added type='section' to support non-clickable section headers (Financien) without breaking existing flat array structure"
  - title: "Sodium encryption for credentials"
    rationale: "Reused existing CredentialEncryption pattern for Rabobank API credentials, consistent with calendar credentials"
  - title: "Disabled navigation items"
    rationale: "Added disabled property to show upcoming features (Facturen) grayed out in sidebar without route implementation"
metrics:
  duration_minutes: 5
  tasks_completed: 2
  files_created: 2
  files_modified: 5
  commits: 2
  completed_date: 2026-02-15
---

# Phase 178 Plan 01: Finance Navigation & Settings Backend Summary

Established navigation structure and backend data layer for finance module with encrypted credential storage.

## What Was Built

**Sidebar Navigation Restructuring:**
- Added Financien section header with Wallet icon (non-clickable, visual grouping)
- Moved Contributie under Financien at `/financien/contributie` (old route redirects)
- Added Facturen sub-item (disabled, grayed out, reserved for phase 184)
- Added Instellingen sub-item (admin only) at `/financien/instellingen`
- Enhanced navigation rendering to support section headers, disabled items, and adminOnly filtering

**Backend Finance Configuration:**
- Created FinanceConfig class following ClubConfig pattern
- 8 settings stored via WordPress Options API:
  - Organization name, address, contact email
  - IBAN (sanitized: uppercase, spaces removed)
  - Payment term days (default: 14, minimum: 1)
  - Payment clause (multi-line text)
  - Email template (multi-line, Dutch default with placeholders)
  - Rabobank API credentials (encrypted JSON: client_id, client_secret, environment)
- Rabobank credentials encrypted at rest using sodium (CredentialEncryption class)
- REST API never exposes actual credentials, only `has_credentials` boolean and `environment` string

**REST API Endpoints:**
- `GET /rondo/v1/finance/settings` - Returns all settings (admin only)
- `POST /rondo/v1/finance/settings` - Updates settings (admin only, partial updates supported)
- Proper sanitization for each field type
- Special handling for IBAN (uppercase, strip spaces) and payment term (minimum 1 day)

**Frontend API Client:**
- `getFinanceSettings()` - Fetch current settings
- `updateFinanceSettings(data)` - Update settings with partial updates

## Deviations from Plan

None - plan executed exactly as written.

## Technical Implementation

**Navigation Pattern:**
```jsx
const navigation = [
  { name: 'Financien', type: 'section', icon: Wallet, requiresFinancieel: true },
  { name: 'Contributie', href: '/financien/contributie', icon: Coins, indent: true, requiresFinancieel: true },
  { name: 'Facturen', href: '/financien/facturen', icon: Receipt, indent: true, requiresFinancieel: true, disabled: true },
  { name: 'Instellingen', href: '/financien/instellingen', icon: Settings, indent: true, requiresFinancieel: true, adminOnly: true },
];
```

**Settings Storage Pattern:**
- Individual WP options per setting (e.g., `rondo_finance_org_name`)
- Encrypted credential storage: `rondo_finance_rabobank_credentials` (base64-encoded sodium-encrypted JSON)
- Bulk update via `update_settings()` accepts associative array, updates only provided keys
- Individual getters/setters for programmatic access

**Credential Encryption Flow:**
1. REST endpoint receives `rabobank_client_id`, `rabobank_client_secret`, `rabobank_environment`
2. FinanceConfig packages into array and calls `CredentialEncryption::encrypt()`
3. Encrypted base64 string stored in WP option
4. On retrieval, `get_rabobank_credentials()` decrypts internally (never exposed via REST)
5. REST API returns only `rabobank_has_credentials: bool` and `rabobank_environment: string`

## Files Changed

**Created:**
- `includes/class-finance-config.php` - Finance settings service class (393 lines)
- `src/pages/Finance/FinanceSettings.jsx` - Placeholder page (5 lines, will be replaced in 178-02)

**Modified:**
- `src/components/layout/Layout.jsx` - Navigation restructuring with section headers, disabled items, adminOnly support, page title updates
- `src/router.jsx` - Finance routes (`/financien/contributie`, `/financien/instellingen`) and redirects from old `/contributie` URLs
- `includes/class-rest-api.php` - Finance settings REST endpoints registration and callbacks
- `functions.php` - Added `use Rondo\Config\FinanceConfig;` import
- `src/api/client.js` - Added `getFinanceSettings` and `updateFinanceSettings` methods

## Testing Notes

- Build passes without errors (`npm run build` successful)
- No new lint errors introduced (pre-existing 113 errors remain)
- PHP class follows PSR-4 autoloading (`Rondo\Config` namespace maps to `includes/`)
- Encryption verified via existing CredentialEncryption class (already in use for calendar credentials)

## Next Steps (Plan 02)

Plan 02 will replace the placeholder FinanceSettings.jsx with a full UI implementing:
- Form for all 8 settings with proper validation
- IBAN formatter, payment term number input
- Rabobank credentials with environment toggle (sandbox/production)
- Email template editor with variable placeholders documentation
- Save confirmation and error handling

## Self-Check: PASSED

**Created files verified:**
```bash
[ -f "includes/class-finance-config.php" ] && echo "FOUND: includes/class-finance-config.php"
[ -f "src/pages/Finance/FinanceSettings.jsx" ] && echo "FOUND: src/pages/Finance/FinanceSettings.jsx"
```
Output:
```
FOUND: includes/class-finance-config.php
FOUND: src/pages/Finance/FinanceSettings.jsx
```

**Commits verified:**
```bash
git log --oneline --all | grep -E "(c43a1d12|62911297)"
```
Output:
```
62911297 feat(178-01): create FinanceConfig class and REST API endpoints
c43a1d12 feat(178-01): restructure sidebar with Financien section
```

All artifacts created, all commits present.
