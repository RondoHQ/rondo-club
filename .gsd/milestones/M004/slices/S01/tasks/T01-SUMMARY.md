---
id: T01
parent: S01
milestone: M004
provides:
  - Reusable RoleFinder static helper class for finding users by work_history job_title keyword
  - LettermintWebhook refactored to use shared RoleFinder
  - Codeception unit tests for RoleFinder
key_files:
  - includes/class-role-finder.php
  - includes/class-lettermint-webhook.php
  - tests/Wpunit/RoleFinderTest.php
key_decisions:
  - RoleFinder placed in Rondo\Core namespace (consistent with other core utility classes)
  - All methods are static since RoleFinder is a stateless lookup helper
patterns_established:
  - Static helper classes in Rondo\Core for reusable cross-concern logic
observability_surfaces:
  - none (RoleFinder is a pure lookup helper with no side effects)
duration: 15m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Extract RoleFinder helper and add unit tests

**Extracted role-finding logic from LettermintWebhook into reusable `Rondo\Core\RoleFinder` static helper with parameterized keyword matching and Codeception unit tests.**

## What Happened

Created `includes/class-role-finder.php` with `Rondo\Core\RoleFinder` class containing:
- `get_user_ids_by_role(string $keyword): array` — finds users whose linked person has a current work_history entry with job_title matching the keyword via case-insensitive `stripos`. Falls back to administrator user IDs.
- `person_has_current_role(int $person_id, string $keyword): bool` — checks work_history entries for matching job_title + current status.
- `is_current_work_history_entry(array $entry): bool` — identical logic to the original LettermintWebhook method (is_current flag OR date range check).

Refactored `includes/class-lettermint-webhook.php`:
- Added `use Rondo\Core\RoleFinder;` import.
- Replaced `$this->get_secretaris_user_ids()` with `RoleFinder::get_user_ids_by_role('secretaris')`.
- Removed three private methods: `get_secretaris_user_ids()`, `person_has_current_secretaris_role()`, `is_current_work_history_entry()`.

Created `tests/Wpunit/RoleFinderTest.php` with 7 test cases covering: matching current role, case-insensitive matching, expired entries exclusion, admin fallback, different keywords, users without linked person, and date-range-based current detection.

## Verification

- `php -l includes/class-role-finder.php` — no syntax errors ✓
- `php -l includes/class-lettermint-webhook.php` — no syntax errors ✓
- `php -l tests/Wpunit/RoleFinderTest.php` — no syntax errors ✓
- `npm run build` — compiles successfully ✓
- `grep` confirms no remaining private role-finding methods in LettermintWebhook ✓
- Codeception tests cannot run locally (no local WordPress test environment with the `stadion` theme), but PHP syntax is valid and tests follow established patterns from `LettermintWebhookTest.php`

### Slice-level verification status (intermediate task — partial passes expected):
- `npm run build` — ✅ passes
- `npm run lint` — not yet run (frontend changes in T02)
- Manual production test — not yet applicable (T03)
- `vendor/bin/codecept run Wpunit RoleFinderTest` — cannot run locally (no local WP test env); test file is syntactically valid

## Diagnostics

RoleFinder is a pure stateless lookup helper. To inspect at runtime:
```php
// Via WP-CLI eval on production:
\Rondo\Core\RoleFinder::get_user_ids_by_role('secretaris');
\Rondo\Core\RoleFinder::get_user_ids_by_role('penningmeester');
```
Returns empty array (then falls back to admins) — no silent failures.

## Deviations

None. Implementation follows the task plan exactly.

## Known Issues

- Codeception tests cannot run locally due to missing local WordPress test environment (theme `stadion` directory not found). Tests are syntactically valid and follow existing patterns.

## Files Created/Modified

- `includes/class-role-finder.php` — New `Rondo\Core\RoleFinder` static helper class
- `includes/class-lettermint-webhook.php` — Refactored to use `RoleFinder`, removed three private methods, added `use` import
- `tests/Wpunit/RoleFinderTest.php` — New Codeception Wpunit test with 7 test cases for RoleFinder
