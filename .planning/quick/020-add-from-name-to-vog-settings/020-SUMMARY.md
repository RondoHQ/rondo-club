# Quick Task 020: Add From Name to VOG Settings

## Summary

Added the ability to customize the "From name" displayed in VOG emails, complementing the existing "From email" setting.

## Changes Made

### Backend (`includes/class-vog-email.php`)
- Added `OPTION_FROM_NAME` constant for WordPress option storage
- Added `get_from_name()` method - returns stored value or falls back to site name
- Added `update_from_name()` method - sanitizes input with `sanitize_text_field()`
- Updated `get_all_settings()` to include `from_name` in returned array
- Updated `filter_mail_from_name()` to use stored setting instead of hardcoded `get_bloginfo('name')`

### Backend (`includes/class-rest-api.php`)
- Added `from_name` parameter to `/stadion/v1/vog/settings` route args
- Updated `update_vog_settings()` to handle `from_name` parameter

### Frontend (`src/pages/Settings/Settings.jsx`)
- Added `from_name` to initial `vogSettings` state
- Added new "Afzender naam" input field in VOG settings tab

## Commit

`df867973` - feat(020): add From name setting to VOG email configuration

## Verification

- [x] Build successful
- [x] Deployed to production
- [x] Available in Settings > VOG tab
