# Quick Task 020: Add From Name to VOG Settings

## Goal
Allow users to customize the "From name" displayed in VOG emails, in addition to the existing "From email" setting.

## Current State
- VOG emails use `get_bloginfo('name')` as the from name (hardcoded)
- From email is already configurable via settings
- Settings UI has fields for: from_email, template_new, template_renewal

## Tasks

### Task 1: Add from_name support to backend (class-vog-email.php)
**Files:** `includes/class-vog-email.php`

Changes:
1. Add `OPTION_FROM_NAME` constant (line ~35)
2. Add `get_from_name()` method - returns stored value or falls back to `get_bloginfo('name')`
3. Add `update_from_name()` method - sanitizes with `sanitize_text_field()`
4. Update `get_all_settings()` to include `from_name`
5. Update `filter_mail_from_name()` to use stored setting instead of hardcoded value

### Task 2: Update REST API endpoint (class-rest-api.php)
**Files:** `includes/class-rest-api.php`

Changes:
1. Add `from_name` to route args (after `from_email`, ~line 520)
2. Update `update_vog_settings()` to handle `from_name` parameter

### Task 3: Add from_name field to Settings UI (Settings.jsx)
**Files:** `src/pages/Settings/Settings.jsx`

Changes:
1. Add `from_name` to initial `vogSettings` state (~line 120)
2. Add new input field in VOGTab component between From Email and Template New fields (~line 3354)
   - Label: "Afzender naam"
   - Placeholder: "Vereniging VOG"
   - Helper text: "De naam die als afzender wordt weergegeven voor VOG e-mails."

## Verification
- Build frontend: `npm run build`
- Deploy to production
- Test in Settings > VOG tab
