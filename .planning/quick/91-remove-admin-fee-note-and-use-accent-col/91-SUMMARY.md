---
phase: quick-91
status: complete
---

## Summary

Removed the admin fee note ("Per termijn wordt € X administratiekosten in rekening gebracht.") from the /betaling page. Updated primary buttons to use the club's accent color from FinanceConfig instead of hardcoded electric-cyan.

## Changes

- **`includes/class-public-payment-page.php`**
  - Removed admin fee note paragraph and `.admin-fee-note` CSS
  - Extended `get_club_branding()` to return `accent_color` (falls back to `#0891b2`)
  - Added `$accent_color` parameter to `render_html_header()`, injected as `--accent-color` CSS variable
  - `.btn-primary` now uses `var(--accent-color)` for background

## Commit

- `46d7bf06`: feat(quick-91): remove admin fee note and use accent color on /betaling
