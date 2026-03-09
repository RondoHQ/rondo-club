# Quick Task 122: Make welkomstmail Bericht field rich text

**Date:** 2026-03-09
**Commit:** 94846a8b

## Changes

- **Frontend:** Replaced `<textarea>` with `<RichTextEditor>` (tiptap) for the welcome email body field on `/settings/admin/welkomstmail`
- **Backend:** Changed `sanitize_textarea_field()` → `wp_kses_post()` in `update_settings()` so HTML is preserved when saving
- **Backend:** Added `is_html()` helper to detect rich text content; `send_welcome_email()` now passes HTML directly to `body_html` instead of running it through `format_plain_text()` which would double-escape

## Files Modified

- `src/pages/Settings/Settings.jsx` — import RichTextEditor, replace textarea
- `includes/class-user-provisioning.php` — wp_kses_post sanitization, is_html detection
