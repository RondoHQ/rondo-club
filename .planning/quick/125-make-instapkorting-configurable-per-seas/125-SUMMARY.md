---
phase: quick-125
plan: 01
subsystem: membership-fees
tags: [instapkorting, pro-rata, settings, contributie]
dependency_graph:
  requires: []
  provides: [configurable-instapkorting-per-season]
  affects: [membership-fee-calculation, contributie-settings-ui]
tech_stack:
  added: []
  patterns: [wp_options per season, configurable-periods-array]
key_files:
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
    - src/pages/Settings/FeeCategorySettings.jsx
decisions:
  - Default config uses 4 quarterly periods matching previous hardcoded behavior (backward compatible)
  - discount_percent represents how much discount the member GETS (75% discount = 25% of fee paid)
  - No-match fallback in get_prorata_percentage returns 1.0 (full fee - safe default)
  - EntryDiscountSection uses amber-50 background to differentiate from blue-50 FamilyDiscountSection
metrics:
  duration: ~20 min
  completed: 2026-03-10
  tasks: 2
  files: 3
---

# Quick Task 125: Make Instapkorting Configurable Per Season

Configurable instapkorting (pro-rata entry discount) periods per season stored in wp_options, replacing the previous hardcoded quarterly 0/25/50/75% structure.

## What Was Built

### Backend (PHP)

**`MembershipFees` class (`includes/class-membership-fees.php`):**
- Added `get_entry_discount_config(?string $season): array` — reads from `rondo_entry_discount_{season}` wp_option, falls back to 4 default quarterly periods matching previous hardcoded behavior
- Added `save_entry_discount_config(array $config, string $season): bool` — persists config to wp_options
- Refactored `get_prorata_percentage()` — now iterates over configured periods instead of using hardcoded `if/elseif` blocks. Falls back to 1.0 (full fee) if no period matches the registration month.

**`Api` class (`includes/class-rest-api.php`):**
- `get_membership_fee_settings()` — includes `entry_discount` in both `current_season` and `next_season` response objects
- `update_membership_fee_settings()` — reads `entry_discount` param, validates via `validate_entry_discount_config()`, saves if provided, returns updated config in response
- `copy_season_categories()` — copies entry discount config alongside categories and family discount, returns `entry_discount` in response
- Added `validate_entry_discount_config($config)` private method — validates period structure (start_month/end_month 1-12, discount_percent 0-100), errors on overlapping months, warns when not all 12 months are covered

### Frontend (React)

**`FeeCategorySettings.jsx` (`src/pages/Settings/FeeCategorySettings.jsx`):**
- Added `DEFAULT_ENTRY_DISCOUNT_PERIODS` constant and `DUTCH_MONTHS` array at module level
- Added `EntryDiscountSection` component — amber-50 card, period list with start/end month dropdowns (Dutch month names) and discount percentage input, add/remove period buttons, Opslaan/Herstel standaard buttons with dirty tracking
- Added `entryDiscountMutation` — calls `prmApi.updateMembershipFeeSettings({ entry_discount }, season)`, sets query data and success message "Instapkorting opgeslagen"
- Added `handleEntryDiscountSave` handler
- Derived `activeEntryDiscount` from season data
- Rendered `<EntryDiscountSection>` below `<FamilyDiscountSection>` in the Contributie settings tab

## Backward Compatibility

The default config exactly matches the previous hardcoded quarterly behavior:
- Jul-Sep: 0% discount (full fee = 1.0)
- Oct-Dec: 25% discount (0.75 prorata)
- Jan-Mar: 50% discount (0.50 prorata)
- Apr-Jun: 75% discount (0.25 prorata)

Until a club explicitly saves a custom config, the defaults apply — existing invoices and fee calculations are unaffected.

## Commits

- `19076f0e` — feat(quick-125): make instapkorting configurable per season

## Self-Check: PASSED

- `includes/class-membership-fees.php` — exists with `get_entry_discount_config` and `save_entry_discount_config`
- `includes/class-rest-api.php` — exists with `validate_entry_discount_config` and `entry_discount` in all 3 endpoints
- `src/pages/Settings/FeeCategorySettings.jsx` — exists with `EntryDiscountSection` component
- Commit `19076f0e` — verified in git log
- Build: passed (no errors)
- Lint: passed (0 warnings)
- Deployed to production: https://rondo.svawc.nl/
