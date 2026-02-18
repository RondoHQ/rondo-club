---
phase: 190-finance-settings-ui-mollie-configuration
verified: 2026-02-18T07:15:03Z
status: passed
score: 6/6 must-haves verified
re_verification: false
---

# Phase 190: Finance Settings UI — Mollie Configuration Verification Report

**Phase Goal:** Finance Settings page includes Mollie API key input, payment provider selector, and test/live mode badge — using existing settings REST endpoint.
**Verified:** 2026-02-18T07:15:03Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Finance Settings Mollie section shows API key input (masked, save button) | VERIFIED | `FinanceSettings.jsx` line 768–817: `activeTab === 'mollie'` section renders `type="password"` input for `mollie_api_key` with save button in the form footer |
| 2 | Payment provider selector (Rabobank / Mollie) visible and saves correctly | VERIFIED | `FinanceSettings.jsx` line 509–540: Betaling tab has radio group `active_payment_provider` with `rabobank` / `mollie` options, wired to `formData.active_payment_provider` and included in submit payload (line 262) |
| 3 | Test/Live mode badge derived from key prefix displayed in settings | VERIFIED | `FinanceSettings.jsx` lines 777–789: Badge rendered only when `settings?.mollie_has_api_key` is true, shows "Live" (green) when `settings.mollie_environment === 'live'`, "Test" (yellow) otherwise |
| 4 | Full API key never returned by REST GET endpoint — only `mollie_has_api_key` bool and `mollie_environment` string | VERIFIED | `class-finance-config.php` `get_all_settings()` lines 210–211: returns `mollie_has_api_key` (bool) and `mollie_environment` (string); raw key is never in the return array. `FinanceSettings.jsx` `useEffect` explicitly does not populate `mollie_api_key` from API response (lines 175–176) |
| 5 | Version bumped to 27.0.0 in `style.css` and `package.json` | VERIFIED | `style.css` line 7: `Version: 27.0.0`; `package.json` line 3: `"version": "27.0.0"` |
| 6 | CHANGELOG.md updated with v27.0 Mollie additions | VERIFIED | `CHANGELOG.md` lines 10–19: `## [27.0.0] - 2026-02-18` section with 7 Added items covering the full Mollie milestone |

**Score:** 6/6 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `src/pages/Finance/FinanceSettings.jsx` | Mollie tab UI, provider selector in Betaling tab | VERIFIED | Contains `{ id: 'mollie', label: 'Mollie' }` in TABS array; Mollie section at line 768; Betaling provider radio at lines 509–540 |
| `includes/class-rest-api.php` | REST args for `mollie_api_key` and `active_payment_provider` | VERIFIED | Lines 752–759: both args registered in `update_finance_settings` POST route with sanitize and validate callbacks |
| `style.css` | Version 27.0.0 | VERIFIED | Line 7: `Version: 27.0.0` |
| `package.json` | Version 27.0.0 | VERIFIED | Line 3: `"version": "27.0.0"` |
| `CHANGELOG.md` | v27.0.0 changelog entry | VERIFIED | Line 10: `## [27.0.0] - 2026-02-18` with complete Mollie milestone coverage |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `FinanceSettings.jsx` | `/rondo/v1/finance/settings` | `formData.mollie_api_key` and `formData.active_payment_provider` sent in payload | WIRED | `handleSubmit` line 262 always includes `active_payment_provider`; line 272–274 conditionally includes `mollie_api_key` when non-empty |
| `FinanceSettings.jsx` | `settings.mollie_has_api_key` | Conditional rendering of environment badge and existing key notice | WIRED | Lines 777 and 792: both `settings?.mollie_has_api_key` guards are present and functional |

### Anti-Patterns Found

No anti-patterns found. No TODO/FIXME/placeholder comments in modified files. No stub implementations. Key credential handling follows the established pattern: empty init, no population from API response, conditional payload inclusion, clear after save.

### Human Verification Required

#### 1. Mollie tab navigation and rendering

**Test:** Log in as admin to Finance Settings, verify Mollie tab appears as the fifth tab, click it, and confirm the API key input renders.
**Expected:** Five tabs visible (Organisatie, Betaling, E-mail, Rabobank, Mollie); Mollie tab content shows the password input with placeholder `live_... of test_...`.
**Why human:** Visual tab rendering and navigation cannot be verified programmatically.

#### 2. Save and environment badge

**Test:** Enter a `test_...` key in the Mollie API key field and save. Then reload the page.
**Expected:** After reload, the "Test" yellow badge appears next to "Omgeving:", and the green existing key notice appears. The input field is empty (masked with `••••••••` placeholder).
**Why human:** Requires an actual Mollie test key and end-to-end round-trip through the REST API.

#### 3. Payment provider selector persistence

**Test:** On the Betaling tab, switch from Rabobank to Mollie and save. Reload the page.
**Expected:** The Mollie radio button is pre-selected after reload, reflecting the stored value.
**Why human:** Requires end-to-end save-and-reload cycle.

### Gaps Summary

No gaps found. All six observable truths are fully verified against the actual codebase.

The implementation correctly:
- Adds a fifth Mollie tab to Finance Settings with a type=password API key input
- Shows Test/Live environment badge derived from `settings.mollie_environment`, guarded by `settings.mollie_has_api_key`
- Places Rabobank/Mollie radio selector in the Betaling tab, wired to `active_payment_provider`
- Never returns the raw Mollie API key from the GET endpoint — only the bool and environment string
- Conditionally includes `mollie_api_key` in the save payload only when non-empty, and clears it after save
- Registers both `mollie_api_key` and `active_payment_provider` as REST args with proper sanitize/validate callbacks
- Ships version 27.0.0 in `style.css` and `package.json` with a complete v27.0.0 CHANGELOG entry

Task commits `51f35498` (feat) and `91ccb0d2` (chore) are confirmed in git history.

---

_Verified: 2026-02-18T07:15:03Z_
_Verifier: Claude (gsd-verifier)_
