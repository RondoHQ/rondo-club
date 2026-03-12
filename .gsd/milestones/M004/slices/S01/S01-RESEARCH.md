# S01: Confirmation, Refresh & Email Notification — Research

**Date:** 2026-03-12

## Summary

This slice adds three improvements to the "Uitsluiten van contributie" toggle on the person detail FinancesCard: (1) a `window.confirm()` dialog before both exclude and re-include actions, (2) proper query invalidation so the FinancesCard refreshes immediately, and (3) an email notification to the Secretaris and Penningmeester when the toggle fires.

All three changes follow well-established patterns already in the codebase. The confirmation dialog uses `window.confirm()` (20+ existing usages across the app). The email uses `EmailTemplate::render()` with `wp_mail()` (same as MentionNotifications). The role-finding logic mirrors `get_secretaris_user_ids()` in LettermintWebhook.

This is a low-risk, well-scoped change touching one React component and one PHP class, plus a small helper extraction.

## Recommendation

**Approach: Minimal changes in two files + one helper extraction**

1. **FinancesCard.jsx** — Add `window.confirm()` guard in `handleToggleExclusion()` with Dutch confirmation messages; also invalidate `peopleKeys.detail(personId)` alongside `feeKeys.person(personId)` in `onSuccess` to ensure immediate re-render.

2. **class-fee-cache-invalidator.php** — Add email sending logic inside `log_contributie_exclusion_toggle()` after the timeline comment is created. This method already has `$post_id`, `$is_excluded`, `$actor_id`, and `$actor_name` — everything needed for the email.

3. **Role lookup helper** — Extract the `get_secretaris_user_ids()` / `person_has_current_secretaris_role()` / `is_current_work_history_entry()` pattern from `class-lettermint-webhook.php` into a reusable static helper (e.g., `class-role-finder.php` in `includes/`) that accepts a role keyword parameter. Both LettermintWebhook and FeeCacheInvalidator then call `RoleFinder::get_user_ids_by_role('secretaris')` and `RoleFinder::get_user_ids_by_role('penningmeester')`.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Branded HTML email | `EmailTemplate::render()` in `class-email-template.php` | Consistent email appearance across all notifications |
| Confirmation dialogs | `window.confirm()` pattern (20+ uses) | Simple, consistent, no new UI components needed |
| Finding users by work_history role | `get_secretaris_user_ids()` in LettermintWebhook | Proven pattern for finding Secretaris by job_title; extend for Penningmeester |
| Email from address/domain | `wp_parse_url(home_url(), PHP_URL_HOST)` pattern in MentionNotifications | Derives `notifications@domain` from site URL |
| Query invalidation | TanStack Query `invalidateQueries` with key arrays | Already used throughout the app |

## Existing Code and Patterns

- `src/components/FinancesCard.jsx` — Contains `handleToggleExclusion()` at line 73. Currently fires `updatePerson.mutate()` without confirmation. `onSuccess` invalidates only `feeKeys.person(personId)`. Fix: add `window.confirm()` guard before `mutate()`, add `peopleKeys.detail(personId)` invalidation.
- `includes/class-fee-cache-invalidator.php` — `log_contributie_exclusion_toggle()` at line 193 fires on `added_post_meta`/`updated_post_meta`/`deleted_post_meta` for `_exclude_from_contributie`. Already has `$post_id`, `$is_excluded`, `$actor_id`, `$actor_name`. This is the right place to add the email send.
- `includes/class-lettermint-webhook.php` — `get_secretaris_user_ids()` (line ~455) and `person_has_current_secretaris_role()` (line ~477) are private methods that search `work_history` for "secretaris". Extract to a shared helper with parameterized job_title search. `is_current_work_history_entry()` (line ~497) checks if a work_history row is currently active.
- `includes/class-email-template.php` — `EmailTemplate::render()` accepts `brand_name`, `preheader`, `eyebrow`, `heading`, `body_html`, `cta_url`, `cta_label`. Used by MentionNotifications for simple notification emails.
- `includes/class-mention-notifications.php` — Lines 84-112 show the email sending pattern: `EmailTemplate::render()` for body, `wp_mail()` with `Content-Type: text/html` header and `From: SiteName <notifications@domain>`.
- `src/hooks/usePeople.js` — `useUpdatePerson()` at line 265. Its own `onSuccess` invalidates `peopleKeys.detail(id)`, `peopleKeys.lists()`, and `['dashboard']`. The FinancesCard's per-call `onSuccess` adds fee key invalidation on top.
- `src/hooks/useFees.js` — `feeKeys.person(personId, params)` generates the cache key for person fee data. The current invalidation `feeKeys.person(personId)` correctly prefix-matches `feeKeys.person(personId, {})`.

## Constraints

- **`window.confirm()` for confirmation** — Context explicitly states this for simplicity, consistent with 20+ existing usages in the codebase.
- **Role lookup must search `work_history` for job_title** — Case-insensitive `stripos()` for "secretaris" and "penningmeester", checking `is_current` flag or date-range validity. Must fall back to administrators if no matching users found.
- **Email uses `EmailTemplate::render()`** — For consistent branding.
- **Person link in email uses `home_url('/people/' . $person_id)`** — Rondo SPA route.
- **Email subject format specified** — "{name} uitgesloten van contributiebetaling" or "{name} opgenomen in contributiebetaling".
- **`log_contributie_exclusion_toggle()` fires on meta hooks** — No current user context guaranteed (could be cron), but the actor_id check already handles this.
- **The `added_post_meta` hook fires on first-time set** — `updated_post_meta` fires on subsequent changes. Both are already hooked. `deleted_post_meta` also hooked for completeness.

## Common Pitfalls

- **Race condition: `get_post_meta` inside hook** — The `log_contributie_exclusion_toggle()` method reads `_exclude_from_contributie` with `get_post_meta()` after the meta has been set. For `updated_post_meta` the new value is already saved. For `added_post_meta` the new value is already saved. For `deleted_post_meta` the value has been deleted so `get_post_meta` returns `''` (falsy), which correctly means "not excluded". This is already handled correctly.
- **Duplicate emails on rapid toggle** — If someone toggles exclusion twice quickly, two meta updates fire two hook calls, sending two emails. This is acceptable behavior (reflects what actually happened).
- **No current user in cron context** — `get_current_user_id()` returns 0 in cron. The existing code already handles this with "Systeem" fallback. The email should also use this fallback.
- **Query invalidation timing** — `useUpdatePerson` and the per-call `onSuccess` both fire on success. TanStack Query deduplicates concurrent invalidations, so invalidating the same key in both places is safe.

## Open Risks

- **Secretaris/Penningmeester with no linked person** — If the users with those roles don't have `rondo_linked_person_id` set, they won't be found by the work_history lookup. Falls back to administrators — acceptable behavior.
- **Email delivery** — Depends on WordPress mail transport (Lettermint). If Lettermint is down, emails silently fail. This is existing behavior for all wp_mail calls. Consider wrapping email send in try/catch and logging errors.
- **Extracting helper from LettermintWebhook** — The private methods need to become public static on a new class. LettermintWebhook must be refactored to call the new helper instead. Low risk since the logic is pure (no side effects, no state).

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress/PHP | N/A | No specialized skills needed — standard WP patterns |
| React/TanStack Query | vercel-react-best-practices | installed (in available_skills) |
| Tailwind CSS | N/A | No changes to styling needed |

## Sources

- `src/components/FinancesCard.jsx` — Current exclusion toggle implementation
- `includes/class-fee-cache-invalidator.php` — Hook where email send should be added
- `includes/class-lettermint-webhook.php` — Role-finding pattern to extract and reuse
- `includes/class-mention-notifications.php` — Email sending pattern with `EmailTemplate::render()`
- `includes/class-email-template.php` — Branded HTML email rendering
- `.gsd/milestones/M004/M004-CONTEXT.md` — Milestone context with scope and constraints
