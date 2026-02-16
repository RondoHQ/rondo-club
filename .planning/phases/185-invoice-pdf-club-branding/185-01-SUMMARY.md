---
phase: 185-invoice-pdf-club-branding
plan: 01
subsystem: finance
tags: [branding, customization, pdf-generation, ui]
dependencies:
  requires:
    - phase: 181
      reason: Uses InvoicePdfGenerator and FinanceConfig infrastructure
  provides:
    - club logo upload and storage via WordPress media library
    - accent color configuration for invoice PDFs
    - fallback branding (Rondo logo and electric cyan)
  affects:
    - invoice PDF appearance (logo and colors)
    - finance settings UI (new branding section)
tech_stack:
  added: []
  patterns:
    - WordPress media upload via REST API
    - Dynamic CSS injection in mPDF templates
    - Hex color validation with sanitize_hex_color()
key_files:
  created: []
  modified:
    - includes/class-finance-config.php
    - includes/class-invoice-pdf-generator.php
    - src/pages/Finance/FinanceSettings.jsx
    - style.css
    - package.json
    - CHANGELOG.md
decisions:
  - title: Logo storage via WordPress media library
    rationale: Reuse existing media upload infrastructure instead of custom file handling
    alternatives: [Custom file upload handler, Direct filesystem storage]
    chosen: WordPress media library
  - title: Accent color stored as hex string
    rationale: Simple storage, validated with sanitize_hex_color(), empty string = use default
    alternatives: [RGB array, Named color system]
    chosen: Hex string
  - title: PDF fallback to Rondo branding
    rationale: Ensures PDFs always render even if club settings incomplete
    alternatives: [Require branding before invoice creation, Show error message]
    chosen: Graceful fallback
metrics:
  duration: 336
  tasks_completed: 2
  files_modified: 6
  commits: 2
  completed_at: "2026-02-16"
---

# Phase 185 Plan 01: Invoice PDF Club Branding

**One-liner:** Club logo upload and accent color picker for invoice PDFs, replacing hardcoded Rondo branding with club's own identity.

## What Was Built

Added club branding customization to Finance Settings, allowing clubs to upload their logo and choose an accent color that appears on all invoice PDFs. Previously, all invoices used the Rondo platform logo and electric cyan color.

### Task 1: Backend Infrastructure (FinanceConfig + InvoicePdfGenerator)

**Duration:** ~180s | **Commit:** b38b0c51

Added two new finance settings:
- `club_logo_id` (WordPress attachment ID, integer)
- `accent_color` (hex color string like `#0891b2`)

**FinanceConfig changes:**
- Added `OPTION_CLUB_LOGO_ID` and `OPTION_ACCENT_COLOR` constants
- Added `get_club_logo_id()` and `get_accent_color()` getter methods
- Extended `get_all_settings()` to resolve attachment URL from logo ID
- Added validation in `update_settings()`: `absint()` for logo ID, `sanitize_hex_color()` for color
- Updated `get_setting()` switch to include new fields

**InvoicePdfGenerator changes:**
- Fetch club logo ID and accent color from FinanceConfig
- Fallback logic: if `club_logo_id > 0`, use `get_attached_file()`, else use Rondo logo
- Fallback logic: if accent color empty, default to `#0891b2`
- Pass `$accent_color` parameter to `build_html()`
- Replace all 4 hardcoded `#0891b2` values in CSS with dynamic `esc_attr($accent_color)`:
  - `.header` border-bottom
  - `.header .org-name` color
  - `h1` color (FACTUUR heading)
  - `.payment-section h2` color (Betaalgegevens heading)

### Task 2: Frontend UI + Version Bump (FinanceSettings.jsx)

**Duration:** ~156s | **Commit:** 249fd183

Added club branding fields to Finance Settings Organisatiegegevens section:

**Logo upload:**
- File input with `accept="image/*"`
- Preview with max-height 60px when logo exists
- "Verwijderen" button to clear logo
- Upload handler POSTs to `/wp/v2/media` using base `api` client
- Sets `club_logo_id` (attachment ID) and `club_logo_url` (source URL) from response

**Accent color picker:**
- Native `<input type="color">` synced with text input showing hex value
- Defaults to `#0891b2` when empty
- "Reset naar standaard" button clears value back to empty string
- Both inputs stay in sync via onChange handlers

**Version bump:**
- `style.css`: `26.0.0` → `26.1.0`
- `package.json`: `26.0.0` → `26.1.0`
- `CHANGELOG.md`: Added `[26.1.0]` section with branding features

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

1. ✅ `npm run build` passes — frontend compiles without errors
2. ✅ `npm run lint` passes — no new lint issues (132 pre-existing, 0 added)
3. ✅ No hardcoded `#0891b2` in PDF generator (only in fallback assignment and docblocks)
4. ✅ FinanceConfig has `get_club_logo_id()` and `get_accent_color()` methods
5. ✅ REST API returns `club_logo_id`, `club_logo_url`, and `accent_color` in settings
6. ✅ FinanceSettings UI shows logo upload field and color picker in Organisatiegegevens
7. ✅ Version is 26.1.0 in both `style.css` and `package.json`
8. ✅ CHANGELOG has 26.1.0 entry

## Testing Notes

**Manual testing required:**
1. Navigate to Finance Settings → Organisatiegegevens
2. Upload a club logo (PNG/JPG with transparent background recommended)
3. Verify preview appears with "Verwijderen" button
4. Choose an accent color using color picker or hex input
5. Save settings
6. Create an invoice (from Discipline Case detail)
7. Download PDF and verify:
   - Club logo appears in header (or Rondo logo if none set)
   - Accent color used for header border, org name, FACTUUR heading, Betaalgegevens heading
8. Test fallback: remove logo and reset color, verify PDF uses Rondo defaults

## Technical Implementation

**Storage pattern:**
- Club logo: WordPress attachment in media library, ID stored in options table
- Accent color: Hex string in options table, empty = use default

**API flow:**
1. Frontend uploads file → WordPress `/wp/v2/media` endpoint
2. WordPress returns attachment ID and source URL
3. Frontend saves attachment ID to finance settings via `/rondo/v1/finance/settings`
4. Backend stores ID in `rondo_finance_club_logo_id` option
5. PDF generator resolves file path from ID using `get_attached_file()`

**CSS injection:**
- Accent color passed as parameter to `build_html()`
- Inserted into CSS via PHP string interpolation: `color: ' . esc_attr($accent_color) . ';`
- Escaped with `esc_attr()` to prevent CSS injection

## Self-Check: PASSED

**Files created:** (none — only modified existing)

**Files modified:**
- ✅ includes/class-finance-config.php (exists, contains club_logo_id/accent_color)
- ✅ includes/class-invoice-pdf-generator.php (exists, uses dynamic accent color)
- ✅ src/pages/Finance/FinanceSettings.jsx (exists, contains logo upload and color picker)
- ✅ style.css (exists, version 26.1.0)
- ✅ package.json (exists, version 26.1.0)
- ✅ CHANGELOG.md (exists, contains 26.1.0 entry)

**Commits exist:**
- ✅ b38b0c51: Backend infrastructure (FinanceConfig + PDF generator)
- ✅ 249fd183: Frontend UI + version bump

All claims verified. Plan complete.
