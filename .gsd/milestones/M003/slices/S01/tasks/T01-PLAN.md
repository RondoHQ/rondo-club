---
estimated_steps: 6
estimated_files: 2
---

# T01: Add credit email template to FinanceConfig and wire REST API

**Slice:** S01 — Credit Invoice Email Template & Status Fix
**Milestone:** M003

## Description

Add the 9th email template type to FinanceConfig following the exact established pattern used by the 8 existing templates. This includes option constants, default values, getter method, heading support, get_all_settings/update_settings exposure, and REST API arg registration. Also wire the test email system to support the new credit template type.

## Steps

1. In `class-finance-config.php`, add two new constants after the existing heading constants:
   - `const OPTION_CREDIT_EMAIL_TEMPLATE = 'rondo_finance_credit_email_template';`
   - `const OPTION_CREDIT_EMAIL_HEADING = 'rondo_finance_credit_email_heading';`

2. In `DEFAULTS` array, add:
   - `'credit_email_template'` — A Dutch HTML template explaining the credit (money owed TO the person), referencing `{naam}`, `{voornaam}`, `{factuur_nummer}`, `{totaal_bedrag}`, `{tuchtzaken_lijst}`, `{organisatie_naam}` but **NO** `{betaallink}`, `{qr_code}`, or `{betaalknop}`. Something like: "Beste {naam}, Bijgevoegd vindt u de creditfactuur {factuur_nummer}. ... Het totaal creditbedrag is {totaal_bedrag}. ... Dit bedrag wordt verrekend met een openstaande factuur of aan u terugbetaald."
   - `'credit_email_heading'` — Default value `'Creditfactuur'`

3. Add `get_credit_email_template()` getter method (copy pattern from `get_membership_email_template()`). Add `'credit'` case to `get_email_heading()` match expression pointing to `self::OPTION_CREDIT_EMAIL_HEADING`.

4. In `get_all_settings()`, add `'credit_email_template'` and `'credit_email_heading'` entries. In `get_setting()`, add `'credit_email_template'` case. In `update_settings()`, add `isset($data['credit_email_template'])` block with `wp_kses_post()`, and add `'credit_email_heading'` to the `$heading_fields` array.

5. In `class-rest-api.php`, register `credit_email_template` (`sanitize_callback => 'wp_kses_post'`) and `credit_email_heading` (`sanitize_callback => 'sanitize_text_field'`) as args on the finance/settings POST route (around line 1140). Add `'credit'` to the `template_type` validator whitelist in the test-email route (around line 1189). Add `case 'credit':` to the `send_finance_test_email()` switch (around line 5610) calling `$config->get_credit_email_template()`.

6. Verify the changes compile: search for the new constants and confirm they appear in all required locations.

## Must-Haves

- [ ] `OPTION_CREDIT_EMAIL_TEMPLATE` and `OPTION_CREDIT_EMAIL_HEADING` constants defined
- [ ] Default credit template has NO `{betaallink}`, `{qr_code}`, or `{betaalknop}` variables
- [ ] `get_credit_email_template()` getter method exists
- [ ] `'credit'` case in `get_email_heading()` match expression
- [ ] Credit template and heading exposed in `get_all_settings()`
- [ ] Credit template handled in `update_settings()` with `wp_kses_post()`
- [ ] Credit heading in `$heading_fields` array in `update_settings()`
- [ ] `credit_email_template` and `credit_email_heading` registered as REST args
- [ ] `'credit'` in test-email validator whitelist
- [ ] `case 'credit'` in `send_finance_test_email()` switch

## Verification

- `grep -c 'OPTION_CREDIT_EMAIL_TEMPLATE' includes/class-finance-config.php` returns ≥ 3
- `grep -c 'OPTION_CREDIT_EMAIL_HEADING' includes/class-finance-config.php` returns ≥ 2
- `grep "'credit'" includes/class-rest-api.php` shows in both validator array and switch
- `grep 'credit_email_template' includes/class-rest-api.php` shows REST arg registration
- Default template does not contain `betaallink`, `qr_code`, or `betaalknop`

## Observability Impact

- Signals added/changed: None — follows identical pattern to 8 existing templates
- How a future agent inspects this: `grep OPTION_CREDIT includes/class-finance-config.php` to find all references
- Failure state exposed: None — storage/retrieval via WordPress Options API with defaults

## Inputs

- `includes/class-finance-config.php` — existing template patterns (constants, DEFAULTS, getters, get_all_settings, update_settings)
- `includes/class-rest-api.php` — existing REST arg registration (~line 1100-1175), test-email validator (~line 1189), send_finance_test_email switch (~line 5610)

## Expected Output

- `includes/class-finance-config.php` — 2 new constants, 2 new DEFAULTS entries, 1 new getter, 1 updated match, updated get_all_settings/get_setting/update_settings
- `includes/class-rest-api.php` — 2 new REST args, 1 updated validator whitelist, 1 new switch case
