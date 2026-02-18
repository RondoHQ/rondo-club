---
phase: quick-81
plan: 01
subsystem: ui, email
tags: [react, php, invoice, email-template, html-table]

# Dependency graph
requires:
  - phase: quick-79
    provides: HTML email template with template variables
provides:
  - Renamed reset button label in test mode
  - Cleaner invoice detail header (no duplicate member name)
  - HTML table for tuchtzaken list in invoice emails
affects: [invoice-email, invoice-detail]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "HTML table with inline CSS for email client compatibility"
    - "charge_codes field suffix determines card type (Geel/Rood)"

key-files:
  created: []
  modified:
    - src/pages/Finance/FactuurDetail.jsx
    - includes/class-invoice-email-sender.php

key-decisions:
  - "charge_codes ending in -1 maps to Geel, otherwise Rood"
  - "sanction_description 'uitsluiting' (case-insensitive) appends ' en schorsing'"
  - "Non-discipline fallback rows use colspan=3 for description"

patterns-established: []

# Metrics
duration: 2min 39s
completed: 2026-02-18
---

# Quick 81: Invoice UI Tweaks and Improved Tuchtzaken Email Table

**Renamed reset button, removed duplicate member name from invoice header, and replaced tuchtzaken email list with a structured HTML table showing Datum, Wedstrijd, Kaart, Bedrag columns.**

## Performance

- **Duration:** 2 min 39s
- **Started:** 2026-02-18T19:17:59Z
- **Completed:** 2026-02-18T19:20:38Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Reset button in test mode now shows "Reset factuur (test)" with matching confirm dialog
- Invoice detail header shows only invoice number and status badge (member name only in Lid card)
- Invoice email tuchtzaken section upgraded from `<ul>` to styled HTML `<table>` with 4 columns
- Card type (Geel/Rood) derived from charge_codes ACF field with schorsing detection

## Task Commits

Each task was committed atomically:

1. **Task 1: Invoice detail UI tweaks** - `6223785a` (feat)
2. **Task 2: Replace tuchtzaken email list with HTML table** - `13e3d383` (feat)
3. **Version bump to 27.1.1** - `e50dbd87` (chore)

## Files Created/Modified
- `src/pages/Finance/FactuurDetail.jsx` - Renamed reset button, removed duplicate person name from header
- `includes/class-invoice-email-sender.php` - Replaced `<ul>` tuchtzaken list with HTML `<table>` (Datum, Wedstrijd, Kaart, Bedrag)

## Decisions Made
- charge_codes field suffix `-1` maps to "Geel", all other values map to "Rood"
- sanction_description comparison uses `strcasecmp()` for case-insensitive "uitsluiting" check
- Non-discipline fallback line items use `colspan="3"` to span the first three columns
- Alternating row backgrounds (`#f9fafb`) for visual clarity in email tables

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

---
*Quick task: 81*
*Completed: 2026-02-18*
