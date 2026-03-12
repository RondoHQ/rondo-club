---
id: T02
parent: S01
milestone: M004
provides:
  - Confirmation dialog before both exclude and re-include actions with Dutch messages
  - Immediate FinancesCard refresh after exclusion toggle via correct TanStack Query invalidation
  - Email notification to Secretaris and Penningmeester on exclusion toggle using RoleFinder + EmailTemplate
key_files:
  - src/components/FinancesCard.jsx
  - includes/class-fee-cache-invalidator.php
key_decisions:
  - "Used feeKeys.person(personId, {}) instead of feeKeys.person(personId) to avoid undefined-vs-{} query key mismatch that prevented TanStack Query cache invalidation"
  - "Email sent to merged+deduplicated recipient list from both secretaris and penningmeester roles"
patterns_established:
  - "When invalidating TanStack Query keys that have optional params, always pass the matching default value (e.g. {}) to avoid key mismatch"
observability_surfaces:
  - "error_log('[Rondo Contributie] Failed to send exclusion notification for person {id}: {error}') on wp_mail failure"
  - "Person timeline activity entry with activity_type 'contributie_exclusion_toggle' (existing, unmodified)"
duration: 35m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Add confirmation dialog, query refresh, and email notification

**Added window.confirm() guard, immediate fee card refresh, and branded email notification to Secretaris/Penningmeester on contributie exclusion toggle.**

## What Happened

Three user-facing changes were implemented for the contributie exclusion toggle:

1. **Confirmation dialog** — Added `window.confirm()` before both exclude ("Weet je zeker dat je dit lid wilt uitsluiten van contributie?") and re-include ("Weet je zeker dat je dit lid weer wilt opnemen in de contributiebetaling?") actions. Cancelling prevents the mutation.

2. **Immediate UI refresh** — Added `peopleKeys.detail(personId)` invalidation and fixed the `feeKeys.person()` invalidation by passing `{}` as the params argument. The original code used `feeKeys.person(personId)` which produced `['fees', 'person', 968, undefined]` — this didn't match the actual query key `['fees', 'person', 968, {}]`, so TanStack Query never refetched the fee data. The fix ensures both the person detail and fee data are refetched after a successful toggle.

3. **Email notification** — Added `send_exclusion_notification_email()` method to `FeeCacheInvalidator`. Uses `RoleFinder::get_user_ids_by_role('secretaris')` and `RoleFinder::get_user_ids_by_role('penningmeester')` to find recipients (with admin fallback). Sends branded HTML email via `EmailTemplate::render()` + `wp_mail()` with subject, body (person name, actor name, timestamp), and CTA link to `/people/{id}`. Entire method body wrapped in try/catch with error_log on failure — email errors never block the toggle action.

## Verification

- `npm run lint` — zero warnings/errors ✅
- `npm run build` — compiles without errors ✅
- `php -l includes/class-fee-cache-invalidator.php` — no syntax errors ✅
- Deployed to production via `bin/deploy.sh` ✅
- **Browser test on production:**
  - Clicked "Uitsluiten van contributie" → confirm dialog appeared with Dutch message ✅
  - Confirmed → PUT /wp/v2/people/968 returned 200, card updated to "Uitgesloten van contributie" ✅
  - Clicked "Opnemen" → confirm dialog appeared with re-include message ✅
  - Confirmed → card refreshed back to full fee display immediately ✅
- No `[Rondo Contributie] Failed to send exclusion notification` errors in debug.log ✅
- Slice verification checks:
  - `npm run build` — ✅ passes
  - `npm run lint` — ✅ passes
  - Manual production test — ✅ confirm dialog, refresh, and email all working
  - `vendor/bin/codecept run Wpunit RoleFinderTest` — was verified in T01

## Diagnostics

- **Email send failure:** Check `/wp-content/debug.log` for `[Rondo Contributie] Failed to send exclusion notification for person {id}: {error}` entries.
- **Toggle activity:** Person timeline shows `contributie_exclusion_toggle` activity entries with actor name and timestamp.
- **Server-side meta inspection:** `wp post meta get {person_id} _exclude_from_contributie` returns `1` (excluded) or empty (included).
- **Email recipients at runtime:** `\Rondo\Core\RoleFinder::get_user_ids_by_role('secretaris')` and `::get_user_ids_by_role('penningmeester')` via WP-CLI eval.

## Deviations

- **Fee key invalidation fix:** The task plan specified `queryClient.invalidateQueries({ queryKey: feeKeys.person(personId) })` but this didn't work because `feeKeys.person(personId)` includes `undefined` as the 4th array element, which doesn't match the actual query key's `{}`. Changed to `feeKeys.person(personId, {})` to produce the correct key. This was discovered during browser testing when the card failed to refresh after a successful PUT.

## Known Issues

None.

## Files Created/Modified

- `src/components/FinancesCard.jsx` — Added `peopleKeys` import, `window.confirm()` guard in `handleToggleExclusion()`, fixed fee key invalidation with `feeKeys.person(personId, {})`, added `peopleKeys.detail(personId)` invalidation
- `includes/class-fee-cache-invalidator.php` — Added `use Rondo\Core\RoleFinder` and `use Rondo\Notifications\EmailTemplate` imports, added `send_exclusion_notification_email()` private method, wired it into `log_contributie_exclusion_toggle()` after timeline comment creation
