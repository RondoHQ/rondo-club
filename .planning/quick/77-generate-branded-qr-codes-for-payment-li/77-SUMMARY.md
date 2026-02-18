---
phase: quick-77
plan: 01
subsystem: payments
tags: [qr-code, mollie, chillerlan, gd-image, branding, invoice]

# Dependency graph
requires:
  - phase: quick-76
    provides: Mollie payment flow with reset payment state
  - phase: phase-190
    provides: FinanceConfig with get_accent_color() and get_club_logo_id()
provides:
  - Branded QR code generation (accent color + logo overlay) for Mollie payment URLs
  - chillerlan/php-qrcode ^5.0 library installed
  - QrCodeGenerator::generate() static method (Rondo\Finance namespace)
affects: [phase-181, phase-183, phase-184, quick-75]

# Tech tracking
tech-stack:
  added: [chillerlan/php-qrcode ^5.0, chillerlan/php-settings-container ^3.2]
  patterns: [Static utility class for QR generation, ECC-H for logo overlay tolerance, returnResource for post-processing]

key-files:
  created:
    - includes/class-qr-code-generator.php
  modified:
    - composer.json
    - composer.lock
    - functions.php
    - includes/class-rest-invoices.php

key-decisions:
  - "Use chillerlan/php-qrcode v5 with QROutputInterface::GDIMAGE_PNG and returnResource=true to get GdImage for logo overlay"
  - "ECC level H (30%) required to allow logo overlay without destroying QR readability"
  - "moduleValues map all dark module types to accent RGB, light types to white [255,255,255]"
  - "Logo overlay uses white rectangle background (12% padding) so logo stands out on colored modules"
  - "QR generation is non-blocking: errors logged but do not block invoice send/regenerate"
  - "clear_qr_code() removed from Mollie send/regenerate paths; still called in reset_payment_state()"

patterns-established:
  - "QR code PHP: use returnResource=true, overlay logo manually via GD, capture with ob_start()/imagepng()"

# Metrics
duration: 8min
completed: 2026-02-18
---

# Quick Task 77: Generate Branded QR Codes for Payment Links Summary

**chillerlan/php-qrcode generates accent-colored PNG QR codes with optional logo overlay for Mollie payment links, saved to uploads/invoices/ and embedded in invoice PDFs**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-02-18
- **Completed:** 2026-02-18
- **Tasks:** 2 completed
- **Files modified:** 4 (+ 1 created)

## Accomplishments
- Installed chillerlan/php-qrcode ^5.0 via Composer and updated autoload
- Created `QrCodeGenerator::generate()` that applies club accent color to all dark QR modules and optionally overlays the club logo with a white background rectangle
- Wired QR generation into both Mollie paths: `send_invoice()` and `regenerate_payment_link()`
- Removed the `clear_qr_code()` calls from Mollie paths — QR is now generated instead of cleared

## Task Commits

1. **Task 1: Add chillerlan/php-qrcode library and create QrCodeGenerator class** - `dddb2ac6` (feat)
2. **Task 2: Wire QrCodeGenerator into Mollie flow and remove clear_qr_code() calls** - `e2547b83` (feat)

## Files Created/Modified
- `includes/class-qr-code-generator.php` - New class with static `generate(string $url, int $invoice_id)` method
- `composer.json` - Added `chillerlan/php-qrcode: ^5.0` dependency
- `composer.lock` - Updated with two new packages (php-qrcode + php-settings-container)
- `functions.php` - Added `use Rondo\Finance\QrCodeGenerator` import
- `includes/class-rest-invoices.php` - Wired QrCodeGenerator into both Mollie payment branches

## Decisions Made
- Used `returnResource = true` on QROptions to get a GdImage back from the library so we can overlay the logo manually via GD before saving
- Used ECC level H (30% error correction) so the logo overlay (20% area) doesn't break QR readability
- `moduleValues` in chillerlan v5 uses `[R, G, B]` int arrays keyed by `QRMatrix::M_*` constants; all dark variants map to accent RGB, all light variants map to `[255, 255, 255]`
- White rounded-rect background (12% padding) drawn behind logo so it stands out against colored modules
- QR generation is non-blocking: errors are logged to error_log but don't return an error to the client

## Deviations from Plan

None - plan executed exactly as written.

The constraints note mentioned checking the actual library API in vendor/ before implementing — this was done. The v5 API matched the plan's description closely: `moduleValues` as `[R, G, B]` arrays, `returnResource = true` for GdImage output, and `outputType = QROutputInterface::GDIMAGE_PNG` all worked as documented.

## Issues Encountered
None - PHP syntax clean, build passed, library API matched plan expectations.

## User Setup Required
None - no external service configuration required. Deploys automatically with the next deployment.

## Next Phase Readiness
- QR codes will appear in Mollie invoices after the next deployment
- The PDF generation (InvoicePdfGenerator) already reads `qr_code_path` from the ACF field, so QR codes will be embedded in PDFs automatically
- Rabobank QR flow is unchanged

---
*Phase: quick-77*
*Completed: 2026-02-18*
