---
phase: 119
plan: 01
subsystem: backend/vog
tags: [php, rest-api, email, vog, compliance]
dependencies:
  requires: []
  provides: ["VOG email service", "VOG settings REST endpoints"]
  affects: ["120-frontend-ui", "121-frontend-admin"]
tech-stack:
  added: []
  patterns: ["service class pattern", "wp_mail with filters"]
key-files:
  created:
    - includes/class-vog-email.php
  modified:
    - includes/class-rest-api.php
    - functions.php
decisions: []
metrics:
  duration: "~10 minutes"
  completed: "2026-01-30"
---

# Phase 119 Plan 01: Backend Foundation Summary

**One-liner:** VOG email service class with wp_mail integration, customizable Dutch templates, and REST API settings endpoints.

## What Was Built

### VOG Email Service Class (`includes/class-vog-email.php`)

A complete email service for VOG (Verklaring Omtrent Gedrag) compliance management:

**Settings Management:**
- `get_from_email()` - Returns custom from email or site admin email as fallback
- `get_template_new()` - Returns template for new volunteer VOG requests
- `get_template_renewal()` - Returns template for VOG renewal requests
- `get_all_settings()` - Returns all settings in one call
- `update_from_email()` - Validates and stores custom from email
- `update_template_new()` - Stores new volunteer template
- `update_template_renewal()` - Stores renewal template

**Email Sending:**
- `send(int $person_id, string $template_type)` - Sends VOG email to person
  - Gets person's email from `contact_info` ACF field (first email type)
  - Returns WP_Error if no email found
  - Uses wp_mail filters for custom from address (pattern from EmailChannel)
  - Sends HTML email with Content-Type header
  - Records `vog_email_sent_date` in post meta on success

**Variable Substitution:**
- `{first_name}` - Person's first name
- `{previous_vog_date}` - Previous VOG date for renewal emails

**Default Templates (Dutch):**
- New volunteer template requesting VOG application via KNVB
- Renewal template citing expired VOG date and requesting renewal

### REST API Endpoints

Added to `includes/class-rest-api.php`:

**GET /stadion/v1/vog/settings**
- Permission: Admin only (`check_admin_permission`)
- Returns: `{ from_email, template_new, template_renewal }`

**POST /stadion/v1/vog/settings**
- Permission: Admin only (`check_admin_permission`)
- Args: `from_email` (optional, email), `template_new` (optional), `template_renewal` (optional)
- Returns: Updated settings

### Class Registration

Added to `functions.php`:
- `use Stadion\VOG\VOGEmail` import statement
- `STADION_VOG_Email` class alias for backward compatibility
- Class autoloaded via Composer classmap

## Technical Decisions

| Decision | Rationale |
|----------|-----------|
| Use WordPress Options API for settings | Consistent with existing settings patterns, simple key-value storage |
| wp_mail filters for from address | Pattern from existing EmailChannel class, allows temporary override |
| HTML email with nl2br | Simple solution, preserves template formatting |
| Post meta for sent date | Easy to query per-person, integrates with existing person data |

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

- PHP syntax check: Passed (no errors in class-vog-email.php)
- Line count: 334 lines (exceeds minimum 100)
- Class structure: Verified namespace, class, and all required methods
- REST endpoints: Routes registered at lines 503-530, callbacks at lines 2305-2340
- Build: npm run build completed successfully
- Deploy: Production deployment successful

## Commit History

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Create VOG Email Service Class | b1e64a6 |
| 2 | Add VOG Settings REST Endpoints | d01ff35 |
| 3 | Register VOG Email Class | d7ef28c |

## Files Changed

**Created:**
- `includes/class-vog-email.php` (334 lines)

**Modified:**
- `includes/class-rest-api.php` (+73 lines: route registration and callbacks)
- `functions.php` (+7 lines: use statement and class alias)

## Next Phase Readiness

**Prerequisites delivered for Phase 120:**
- VOG email service class ready for frontend integration
- Settings endpoints available for admin UI
- Email tracking via `vog_email_sent_date` post meta

**No blockers identified.**
