---
id: T01
parent: S01
milestone: M003
provides:
  - credit_email_template_config
  - credit_email_heading_config
  - credit_test_email_support
key_files:
  - includes/class-finance-config.php
  - includes/class-rest-api.php
key_decisions:
  - Followed exact pattern of existing 8 templates for the 9th credit template
patterns_established:
  - Credit template uses same constant/default/getter/settings/update pattern as all other email templates
observability_surfaces:
  - none — follows identical pattern to 8 existing templates, uses WordPress Options API with defaults
duration: 6 steps
verification_result: passed
completed_at: 2026-03-12T13:30:00+01:00
blocker_discovered: false
---

# T01: Add credit email template to FinanceConfig and wire REST API

**Added 9th email template type (credit invoice) to FinanceConfig with full REST API support including test email capability.**

## What Happened

Added the credit invoice email template following the exact established pattern used by the 8 existing templates:

1. **Constants:** Added `OPTION_CREDIT_EMAIL_TEMPLATE` and `OPTION_CREDIT_EMAIL_HEADING` constants in `class-finance-config.php`.

2. **Defaults:** Added `credit_email_template` (Dutch HTML template for credit invoices) and `credit_email_heading` (default: `'Creditfactuur'`) to the `DEFAULTS` array. The credit template intentionally omits `{betaallink}`, `{qr_code}`, and `{betaalknop}` placeholders since credit invoices represent money owed TO the member.

3. **Getter:** Added `get_credit_email_template()` method following the same pattern as `get_membership_email_template()`.

4. **Heading support:** Added `'credit'` case to the `get_email_heading()` match expression.

5. **Settings exposure:** Added `credit_email_template` and `credit_email_heading` to `get_all_settings()`, `get_setting()`, and `update_settings()` (with `wp_kses_post()` sanitization for template, `sanitize_text_field()` for heading via `$heading_fields` array).

6. **REST API:** Registered `credit_email_template` and `credit_email_heading` as args on the finance/settings POST route. Added `'credit'` to the test-email validator whitelist and `case 'credit':` to the `send_finance_test_email()` switch.

## Verification

- `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` → **3** (constant, getter, update_settings) ✅
- `grep -c 'OPTION_CREDIT_EMAIL_HEADING' includes/class-finance-config.php` → **3** (constant, heading match, heading_fields) ✅
- `grep "'credit'" includes/class-rest-api.php` → shows in both validator array and switch ✅
- `grep 'credit_email_template' includes/class-rest-api.php` → shows REST arg registration ✅
- Default template does NOT contain `betaallink`, `qr_code`, or `betaalknop` ✅
- `npm run build` → success ✅
- `npm run lint` → 0 warnings ✅

### Slice-level checks (partial — T01 only):
- `OPTION_CREDIT_EMAIL_TEMPLATE` count ≥ 3: **PASS**
- `'credit'` in rest-api.php validator and switch: **PASS** (2 occurrences)
- `credit_email_template` registered as REST arg: **PASS**
- Frontend FinanceSettings.jsx integration: **NOT YET** (T02/T03 scope)
- Creditfacturen sub-tab: **NOT YET** (T02/T03 scope)
- Auto-paid block removal: **NOT YET** (T03 scope)

## Diagnostics

Inspect credit email template config: `grep OPTION_CREDIT includes/class-finance-config.php` shows all references. The template and heading are stored via WordPress Options API with sensible defaults — no new runtime signals or failure states beyond the existing pattern.

## Deviations

None — implementation followed the task plan exactly.

## Known Issues

None.

## Files Created/Modified

- `includes/class-finance-config.php` — Added 2 constants, 2 defaults, 1 getter, 1 match case, and wired into get_all_settings/get_setting/update_settings
- `includes/class-rest-api.php` — Added 2 REST args, 1 validator whitelist entry, 1 switch case for test email
