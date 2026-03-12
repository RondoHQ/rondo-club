---
estimated_steps: 5
estimated_files: 3
---

# T01: Extract RoleFinder helper and add unit tests

**Slice:** S01 — Confirmation, Refresh & Email Notification
**Milestone:** M004

## Description

Extract the private role-finding methods from `LettermintWebhook` into a reusable `Rondo\Core\RoleFinder` static helper class. The new class takes a job_title keyword (e.g. "secretaris", "penningmeester") and returns matching user IDs by searching `work_history` ACF fields on linked person records. Falls back to administrator user IDs when no matching users are found.

Refactor `LettermintWebhook` to call the new shared helper, removing its private `get_secretaris_user_ids()`, `person_has_current_secretaris_role()`, and `is_current_work_history_entry()` methods.

Write Codeception Wpunit tests for the RoleFinder class.

## Steps

1. Create `includes/class-role-finder.php` with namespace `Rondo\Core` and class `RoleFinder`:
   - Public static method `get_user_ids_by_role(string $keyword): array` — finds users whose linked person has a current work_history entry with job_title matching the keyword (case-insensitive `stripos`). Falls back to administrator user IDs.
   - Private static method `person_has_current_role(int $person_id, string $keyword): bool` — checks work_history entries for matching job_title + current status.
   - Private static method `is_current_work_history_entry(array $entry): bool` — checks `is_current` flag or date range validity (copy from LettermintWebhook).
   - Logic is identical to existing LettermintWebhook methods but parameterized for any keyword.

2. Refactor `includes/class-lettermint-webhook.php`:
   - Add `use Rondo\Core\RoleFinder;` at top.
   - Replace `$this->get_secretaris_user_ids()` call (line ~206) with `RoleFinder::get_user_ids_by_role('secretaris')`.
   - Delete the three private methods: `get_secretaris_user_ids()`, `person_has_current_secretaris_role()`, `is_current_work_history_entry()`.

3. Create `tests/Wpunit/RoleFinderTest.php`:
   - Test `get_user_ids_by_role()` returns correct user IDs when a user's linked person has matching work_history.
   - Test fallback to administrator IDs when no matching role users exist.
   - Test case-insensitive matching (e.g., "Secretaris" matches "secretaris").
   - Test that expired work_history entries (is_current=false, past end_date) are excluded.

4. Run `vendor/bin/codecept run Wpunit RoleFinderTest` to verify tests pass.

5. Run `npm run build` to verify no PHP autoloader or frontend issues.

## Must-Haves

- [ ] `RoleFinder::get_user_ids_by_role('secretaris')` returns same results as old `LettermintWebhook::get_secretaris_user_ids()`
- [ ] `RoleFinder::get_user_ids_by_role('penningmeester')` works for new keyword
- [ ] Falls back to administrator user IDs when no matching users found
- [ ] `is_current_work_history_entry()` logic is identical to the original
- [ ] LettermintWebhook uses `RoleFinder` instead of private methods
- [ ] Codeception tests pass for RoleFinder

## Verification

- `vendor/bin/codecept run Wpunit RoleFinderTest` — all tests pass
- `npm run build` — compiles successfully
- grep confirms no remaining private role-finding methods in LettermintWebhook

## Observability Impact

- Signals added/changed: None (RoleFinder is a pure lookup helper, no side effects)
- How a future agent inspects this: `RoleFinder::get_user_ids_by_role('secretaris')` can be called from WP-CLI eval for debugging
- Failure state exposed: Returns empty array (then falls back to admins) — no silent failures

## Inputs

- `includes/class-lettermint-webhook.php` — contains the three private methods to extract (lines 676-770)
- `tests/Wpunit/LettermintWebhookTest.php` — reference for test patterns

## Expected Output

- `includes/class-role-finder.php` — new reusable static helper class
- `includes/class-lettermint-webhook.php` — refactored to use RoleFinder, three private methods removed
- `tests/Wpunit/RoleFinderTest.php` — passing unit tests for the helper
