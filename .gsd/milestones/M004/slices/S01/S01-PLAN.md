# S01: Confirmation, Refresh & Email Notification

**Goal:** The exclusion toggle confirms before acting, immediately refreshes the FinancesCard, and sends email notifications to Secretaris and Penningmeester.
**Demo:** Toggle "Uitsluiten van contributie" → confirmation dialog appears → after confirm, FinancesCard updates instantly without page reload → Secretaris and Penningmeester receive branded HTML email with person name (linked), actor name, and timestamp.

## Must-Haves

- `window.confirm()` dialog before both exclude and re-include actions with Dutch messages
- FinancesCard immediately reflects the new state after toggling (no page reload)
- Reusable `RoleFinder` helper class that finds users by work_history job_title keyword
- LettermintWebhook refactored to use shared RoleFinder instead of private methods
- Email sent to Secretaris and Penningmeester using `EmailTemplate::render()` and `wp_mail()`
- Email contains person name (linked to `/people/{id}`), actor name, and timestamp
- Email subject follows format: "{name} uitgesloten van contributiebetaling" / "{name} opgenomen in contributiebetaling"
- Falls back to administrators if no Secretaris/Penningmeester found
- Handles "Systeem" actor fallback when no current user (cron context)

## Proof Level

- This slice proves: integration
- Real runtime required: yes (email delivery and UI confirmation verified on production)
- Human/UAT required: yes (verify email content, link correctness, and confirmation dialog UX)

## Verification

- `npm run build` — frontend compiles without errors
- `npm run lint` — zero warnings/errors
- Manual test on production: toggle exclusion → confirm dialog appears → FinancesCard refreshes → email received by Secretaris/Penningmeester
- `vendor/bin/codecept run Wpunit RoleFinderTest` — unit tests for the RoleFinder helper

## Observability / Diagnostics

- Runtime signals: `error_log()` on email send failure (try/catch around `wp_mail()`), existing timeline activity comment for exclusion toggle
- Inspection surfaces: Person timeline shows "Contributie uitgesloten door {actor}" / "Contributie opnieuw opgenomen door {actor}" entries (existing behavior)
- Failure visibility: `error_log` message with person ID and recipient emails on wp_mail failure; email send errors do not block the toggle action
- Redaction constraints: Email addresses logged for debugging, no sensitive data in email body

## Integration Closure

- Upstream surfaces consumed: `EmailTemplate::render()` (Rondo\Notifications), `wp_mail()`, `window.confirm()`, TanStack Query invalidation, `useUpdatePerson` mutation
- New wiring introduced in this slice: `RoleFinder` static helper class, email send in `FeeCacheInvalidator::log_contributie_exclusion_toggle()`, confirmation guard in `FinancesCard.handleToggleExclusion()`
- What remains before the milestone is truly usable end-to-end: nothing — this is the only slice in M004

## Tasks

- [x] **T01: Extract RoleFinder helper and add unit tests** `est:45m`
  - Why: The role-finding logic for Secretaris is duplicated in LettermintWebhook as private methods. Extracting to a shared helper enables reuse for both Secretaris and Penningmeester email recipients, and provides a testable unit.
  - Files: `includes/class-role-finder.php`, `includes/class-lettermint-webhook.php`, `tests/Wpunit/RoleFinderTest.php`
  - Do: Create `Rondo\Core\RoleFinder` with static `get_user_ids_by_role(string $keyword)` and supporting `person_has_current_role()` / `is_current_work_history_entry()` methods. Refactor LettermintWebhook to call `RoleFinder::get_user_ids_by_role('secretaris')`. Write Codeception unit test for RoleFinder covering: users with matching role found, fallback to administrators, no users at all.
  - Verify: `vendor/bin/codecept run Wpunit RoleFinderTest` passes; `npm run build` still succeeds
  - Done when: RoleFinder class exists with tests passing, LettermintWebhook uses it instead of private methods

- [x] **T02: Add confirmation dialog, query refresh, and email notification** `est:45m`
  - Why: This is the core feature — the three user-facing changes: confirmation before toggle, immediate UI refresh, and email notification to board members.
  - Files: `src/components/FinancesCard.jsx`, `includes/class-fee-cache-invalidator.php`
  - Do: (1) In FinancesCard.jsx, add `window.confirm()` guard in `handleToggleExclusion()` with Dutch messages ("Weet je zeker dat je dit lid wilt uitsluiten van contributie?" / "Weet je zeker dat je dit lid weer wilt opnemen in de contributiebetaling?"); also add `peopleKeys.detail(personId)` invalidation in `onSuccess`. (2) In FeeCacheInvalidator, add `send_exclusion_notification_email()` method called from `log_contributie_exclusion_toggle()` after the timeline comment. Use `RoleFinder::get_user_ids_by_role('secretaris')` and `RoleFinder::get_user_ids_by_role('penningmeester')` to find recipients. Use `EmailTemplate::render()` with CTA link to person page and `wp_mail()` with HTML content type. Wrap in try/catch with error_log on failure.
  - Verify: `npm run build` passes; `npm run lint` passes; manual test on production confirms all three behaviors
  - Done when: Confirmation dialog shows on both exclude/include; FinancesCard refreshes immediately; email arrives at Secretaris and Penningmeester

- [ ] **T03: Version bump, changelog, docs, deploy, and verify** `est:30m`
  - Why: Every milestone requires version update, changelog entry, documentation update, production deployment, and verification per project rules.
  - Files: `style.css`, `package.json`, `CHANGELOG.md`, `../developer/src/content/docs/features/membership-fees.md`
  - Do: Bump version to 31.11.0 (minor — new feature). Add changelog entry under [31.11.0] with Added section for confirmation, refresh, and email notification. Update developer docs membership-fees page to document the exclusion notification behavior. Git commit and push. Deploy to production via `bin/deploy.sh`. Verify on production: toggle exclusion, confirm dialog, UI refresh, email delivery.
  - Verify: Production site shows new version; toggle exclusion works end-to-end with confirmation, refresh, and email
  - Done when: Deployed to production, all three behaviors verified working, version and changelog updated

## Files Likely Touched

- `includes/class-role-finder.php` (new)
- `includes/class-fee-cache-invalidator.php`
- `includes/class-lettermint-webhook.php`
- `src/components/FinancesCard.jsx`
- `tests/Wpunit/RoleFinderTest.php` (new)
- `style.css`
- `package.json`
- `CHANGELOG.md`
- `../developer/src/content/docs/features/membership-fees.md`
