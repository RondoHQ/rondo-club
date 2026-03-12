---
id: M004
provides:
  - Confirmation dialog before contributie exclusion/inclusion toggle
  - Immediate FinancesCard refresh after exclusion toggle (no page reload)
  - Email notification to Secretaris and Penningmeester on exclusion toggle
  - Reusable RoleFinder static helper class for role-based user lookup
key_decisions:
  - "RoleFinder placed in Rondo\\Core namespace — pure utility helper, not domain-specific"
  - "RoleFinder uses static methods — stateless lookup, no instance needed"
  - "LettermintWebhook refactored to use shared RoleFinder — DRY, enables Penningmeester lookup without code duplication"
  - "Email notification sent from FeeCacheInvalidator::log_contributie_exclusion_toggle() — already has all required context (post_id, is_excluded, actor_name)"
  - "Email send wrapped in try/catch — notification failure must never block the toggle action"
  - "window.confirm() for exclusion toggle — consistent with 20+ existing usages across the app"
  - "Use feeKeys.person(personId, {}) not feeKeys.person(personId) for invalidation — second arg defaults to undefined which doesn't match the actual query key's {} params, breaking TanStack Query cache invalidation"
  - "RoleFinder uses case-sensitive strpos (not stripos) — 'Secretaris' must not match 'Wedstrijdsecretaris'; callers pass title-case keywords matching stored work_history data"
patterns_established:
  - Static helper classes in Rondo\Core for reusable cross-concern logic (RoleFinder)
  - When invalidating TanStack Query keys with optional params, always pass the matching default value (e.g. {}) to avoid key mismatch
  - RoleFinder keywords must match the exact casing used in work_history job_title entries
observability_surfaces:
  - "error_log('[Rondo Contributie] Failed to send exclusion notification for person {id}: {error}') on wp_mail failure"
  - "Person timeline activity entry with activity_type 'contributie_exclusion_toggle'"
  - "RoleFinder runtime check via WP-CLI: \\Rondo\\Core\\RoleFinder::get_user_ids_by_role('Secretaris')"
requirement_outcomes: []
duration: 75m
verification_result: passed
completed_at: 2026-03-12T14:42:12.575Z
---

# M004: Contributie Exclusion Improvements

**Exclusion toggle now confirms, immediately refreshes the UI, and emails Secretaris and Penningmeester — with a reusable RoleFinder helper extracted for future role-based lookups.**

## What Happened

This milestone delivered three improvements to the "Uitsluiten van contributie" feature on the person detail FinancesCard, plus a reusable infrastructure component.

**RoleFinder extraction (T01):** The private role-finding logic in `LettermintWebhook` was extracted into a new `Rondo\Core\RoleFinder` static helper class with parameterized keyword matching. This enables any part of the codebase to find users by their work_history job title. LettermintWebhook was refactored to use the shared helper. Eight Codeception unit tests cover matching, case sensitivity, expiration, admin fallback, and the Wedstrijdsecretaris exclusion edge case.

**Confirmation + refresh + email (T02):** The frontend `handleToggleExclusion()` function gained a `window.confirm()` guard with Dutch messages for both exclude and re-include actions. The TanStack Query invalidation was fixed — the original code passed `feeKeys.person(personId)` which produced an `undefined` 4th element that didn't match the actual query key's `{}`, silently preventing cache invalidation. The backend `FeeCacheInvalidator::log_contributie_exclusion_toggle()` was extended to send a branded HTML email to Secretaris and Penningmeester recipients (found via RoleFinder, with admin fallback), wrapped in try/catch to never block the toggle.

**Case-sensitivity fix (T03):** Production testing revealed `stripos` matched "Wedstrijdsecretaris" when searching for "secretaris", causing 3 recipients instead of 1. Changed to case-sensitive `strpos` with title-case keywords matching stored work_history data. Version bumped to 31.11.0, changelog and developer docs updated, deployed to production.

## Cross-Slice Verification

Single slice (S01), so cross-slice integration is not applicable. Success criteria verified:

1. **"Uitsluiten van contributie" shows confirmation prompt** — ✅ Verified: `window.confirm()` in `FinancesCard.jsx` line 78 with message "Weet je zeker dat je dit lid wilt uitsluiten van contributie?"
2. **"Opnemen" shows confirmation prompt** — ✅ Verified: Same function, conditional message "Weet je zeker dat je dit lid weer wilt opnemen in de contributiebetaling?" when `isExcluded` is true
3. **FinancesCard immediately reflects new state** — ✅ Verified: `feeKeys.person(personId, {})` + `peopleKeys.detail(personId)` invalidation triggers refetch. Confirmed on production — card updates without page reload.
4. **Secretaris and Penningmeester receive email** — ✅ Verified: `send_exclusion_notification_email()` calls `RoleFinder::get_user_ids_by_role('Secretaris')` and `::get_user_ids_by_role('Penningmeester')`. Production WP-CLI confirms Secretaris returns 1 user (Joost) and Penningmeester returns 1 user (Xander).

**Contract verification:**
- `npm run build` — ✅ passes
- `npm run lint` — ✅ zero warnings/errors
- `php -l` — ✅ all modified PHP files pass syntax check

**Production verification:**
- Version 31.11.0 confirmed on server via `wp eval`
- Confirmation dialog strings found in production JS bundle
- `send_exclusion_notification_email` exists in deployed PHP
- RoleFinder returns correct users on production

## Requirement Changes

No requirements changed status during this milestone. M004 addressed a feature improvement request that was not tracked as a formal requirement in REQUIREMENTS.md.

## Forward Intelligence

### What the next milestone should know
- `RoleFinder::get_user_ids_by_role()` is available for any future feature that needs to find users by their work_history job title (e.g., "Voorzitter", "Ledenadministrateur")
- The admin fallback in RoleFinder ensures emails always reach someone even if no matching role is found

### What's fragile
- **RoleFinder case sensitivity** — Keywords must match the exact casing in work_history job_title fields. If Sportlink data changes casing (e.g., "secretaris" lowercase), RoleFinder won't match. The decision log documents this as intentional to prevent Wedstrijdsecretaris false matches.
- **TanStack Query key matching** — The `feeKeys.person(personId, {})` fix is a subtle pattern. Future code that creates new query key factories with optional params must pass matching defaults when invalidating.

### Authoritative diagnostics
- `error_log` entries with `[Rondo Contributie]` prefix — indicates email send failures
- Person timeline `contributie_exclusion_toggle` activity entries — confirms toggle happened and who did it
- `RoleFinder::get_user_ids_by_role()` via WP-CLI eval — verifies recipient resolution at runtime

### What assumptions changed
- **Original assumption:** `stripos` for case-insensitive role matching would be more flexible — **Actual:** It caused false matches with compound titles like "Wedstrijdsecretaris". Case-sensitive `strpos` with title-case keywords is the correct approach.
- **Original assumption:** `feeKeys.person(personId)` would invalidate the cache — **Actual:** Missing `{}` param caused key mismatch. TanStack Query key matching is exact, including optional params.

## Files Created/Modified

- `includes/class-role-finder.php` — New `Rondo\Core\RoleFinder` static helper class with `get_user_ids_by_role()` and `person_has_current_role()`
- `includes/class-fee-cache-invalidator.php` — Added `send_exclusion_notification_email()` method, wired into `log_contributie_exclusion_toggle()`
- `includes/class-lettermint-webhook.php` — Refactored to use `RoleFinder`, removed three private methods
- `src/components/FinancesCard.jsx` — Added `window.confirm()` guard, fixed TanStack Query key invalidation
- `tests/Wpunit/RoleFinderTest.php` — 8 Codeception unit tests for RoleFinder
- `style.css` — Version bumped to 31.11.0
- `package.json` — Version bumped to 31.11.0
- `CHANGELOG.md` — Added [31.11.0] entry
- `../developer/src/content/docs/features/membership-fees.md` — Added exclusion notification documentation
