---
status: resolved
trigger: "finance-settings-email-not-persisting"
created: 2026-02-16T00:00:00Z
updated: 2026-02-16T00:30:00Z
---

## Current Focus

hypothesis: CONFIRMED - Field name mismatch between frontend (org_email) and backend (contact_email)
test: Code review shows frontend sends org_email, backend expects contact_email
expecting: Fix will change frontend to send contact_email instead
next_action: Apply fix to FinanceSettings.jsx

## Symptoms

expected: After entering an email in the "Factuur e-mailadres" field and clicking save, the email should persist and be visible on page reload.
actual: The save appears to succeed (green success message), but the email field is empty again after reloading the page.
errors: No error messages visible.
reproduction: Go to /financien/instellingen, enter an email in the "Factuur e-mailadres" field, save, reload the page — the field is empty.
started: This is part of the v26.0 invoice settings (Phase 178), likely always been this way since the field was added.

## Eliminated

## Evidence

- timestamp: 2026-02-16T00:10:00Z
  checked: Frontend FinanceSettings.jsx save handler (line 59-68)
  found: Frontend sends payload with field name "org_email" (line 62)
  implication: Frontend uses org_email as the field name

- timestamp: 2026-02-16T00:12:00Z
  checked: FinanceConfig class storage/retrieval (lines 27-44)
  found: Backend constant is OPTION_CONTACT_EMAIL = 'rondo_finance_contact_email' and DEFAULTS uses 'contact_email' key
  implication: Backend expects field name "contact_email", not "org_email"

- timestamp: 2026-02-16T00:14:00Z
  checked: REST API update handler (line 3167-3170)
  found: update_finance_settings() passes request params directly to FinanceConfig->update_settings()
  implication: Backend receives org_email but ignores it because update_settings() only processes contact_email

- timestamp: 2026-02-16T00:16:00Z
  checked: FinanceConfig->get_all_settings() (lines 148-162)
  found: Returns 'contact_email' => $this->get_contact_email() (line 154)
  implication: Backend returns contact_email field, which frontend correctly loads (line 32 of FinanceSettings.jsx)

- timestamp: 2026-02-16T00:18:00Z
  checked: Frontend form state initialization (lines 26-43 of FinanceSettings.jsx)
  found: Line 32 maps settings.org_email to formData.org_email (should be settings.contact_email)
  implication: Frontend has TWO bugs - wrong field name on save AND wrong field name on load (but load works because backend doesn't send org_email, so it defaults to empty)

## Resolution

root_cause: Field name mismatch - Frontend sends "org_email" but backend expects "contact_email". The FinanceConfig->update_settings() method (line 209) only processes $data['contact_email'], so when frontend sends org_email it's silently ignored. Production database confirms rondo_finance_contact_email option doesn't exist.
fix: Changed all instances of "org_email" to "contact_email" in FinanceSettings.jsx (form state, save payload, load from API, input field id/name)
verification: |
  - Built and deployed fix to production
  - Tested backend storage: wp option update/get rondo_finance_contact_email works correctly
  - Frontend now sends contact_email field which backend processes
  - Ready for user testing: go to /financien/instellingen, enter email, save, reload - should persist
files_changed:
  - src/pages/Finance/FinanceSettings.jsx
