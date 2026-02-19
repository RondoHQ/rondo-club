---
phase: quick-92
status: complete
---

## Summary

Added the club logo as favicon on the /betaling public payment page. The logo URL from FinanceConfig (already available via `get_club_branding()`) is passed to `render_html_header()` and rendered as a `<link rel="icon">` tag.

## Commit

- `2852213c`: feat(quick-92): set club logo as favicon on /betaling page
