---
estimated_steps: 5
estimated_files: 2
---

# T02: Add confirmation dialog, query refresh, and email notification

**Slice:** S01 — Confirmation, Refresh & Email Notification
**Milestone:** M004

## Description

Implement the three user-facing changes for this milestone:

1. **Confirmation dialog** — `window.confirm()` guard before both exclude and re-include actions in FinancesCard.jsx, with Dutch confirmation messages.
2. **Immediate UI refresh** — Add `peopleKeys.detail(personId)` to the `onSuccess` invalidation in FinancesCard so the card re-renders immediately after toggle.
3. **Email notification** — Send branded HTML email to Secretaris and Penningmeester when the exclusion toggle fires, using `EmailTemplate::render()` and `wp_mail()`.

## Steps

1. **FinancesCard.jsx — Add confirmation dialog:**
   - In `handleToggleExclusion()`, add `window.confirm()` check before `updatePerson.mutate()`.
   - Exclude message: `'Weet je zeker dat je dit lid wilt uitsluiten van contributie?'`
   - Re-include message: `'Weet je zeker dat je dit lid weer wilt opnemen in de contributiebetaling?'`
   - If user cancels, return early (no mutation).

2. **FinancesCard.jsx — Add query invalidation for immediate refresh:**
   - Import `peopleKeys` from `@/hooks/usePeople`.
   - In the `onSuccess` callback of the `updatePerson.mutate()` call, add `queryClient.invalidateQueries({ queryKey: peopleKeys.detail(personId) })` alongside the existing `feeKeys.person(personId)` invalidation.
   - Note: `useUpdatePerson` already invalidates `peopleKeys.detail(id)` in its own `onSuccess`, but the per-call invalidation ensures the fee-dependent UI also refreshes in the correct order.

3. **FeeCacheInvalidator — Add email send method:**
   - Add `use Rondo\Core\RoleFinder;` and `use Rondo\Notifications\EmailTemplate;` at top.
   - Create private method `send_exclusion_notification_email(int $post_id, bool $is_excluded, string $actor_name): void`.
   - Use `RoleFinder::get_user_ids_by_role('secretaris')` and `RoleFinder::get_user_ids_by_role('penningmeester')` to collect recipient user IDs. Merge and deduplicate.
   - Get recipient emails from user objects (skip users without email).
   - Build email using `EmailTemplate::render()`:
     - `eyebrow`: `'Contributie'`
     - `heading`: `'{name} uitgesloten van contributiebetaling'` or `'{name} opgenomen in contributiebetaling'`
     - `body_html`: Paragraph with actor name and timestamp (`current_time('d-m-Y H:i')`)
     - `cta_url`: `home_url('/people/' . $post_id)`
     - `cta_label`: `'Bekijk lid'`
   - Send via `wp_mail()` with `Content-Type: text/html; charset=UTF-8` and `From: {site_name} <notifications@{root_domain}>` headers.
   - Wrap entire method body in try/catch, `error_log()` on failure.

4. **FeeCacheInvalidator — Wire email into toggle handler:**
   - At the end of `log_contributie_exclusion_toggle()`, after the timeline comment is created, call `$this->send_exclusion_notification_email($post_id, $is_excluded, $actor_name)`.

5. **Verify:**
   - Run `npm run build` and `npm run lint`.
   - Check that FinancesCard changes compile and lint correctly.
   - Check that PHP changes have no syntax errors (deploy will validate).

## Must-Haves

- [ ] `window.confirm()` fires before both exclude and re-include actions
- [ ] Cancelling the confirm dialog prevents the mutation
- [ ] FinancesCard refreshes immediately after successful toggle (no page reload needed)
- [ ] Email sent to Secretaris and Penningmeester with correct subject, body, and CTA link
- [ ] Email subject: "{name} uitgesloten van contributiebetaling" or "{name} opgenomen in contributiebetaling"
- [ ] Email body includes actor name and timestamp
- [ ] CTA links to `/people/{id}` on the site
- [ ] Falls back to administrators if no Secretaris/Penningmeester found
- [ ] Email send failure does not block the toggle action
- [ ] `npm run build` and `npm run lint` pass

## Verification

- `npm run build` — compiles without errors
- `npm run lint` — zero warnings
- Manual: toggle exclusion on production → confirm dialog appears → card refreshes → email received

## Observability Impact

- Signals added/changed: `error_log('[Rondo Contributie] Failed to send exclusion notification for person {id}: {error}')` on wp_mail failure
- How a future agent inspects this: Check PHP error log for `[Rondo Contributie]` entries; check person timeline for `contributie_exclusion_toggle` activity entries
- Failure state exposed: Error log message with person ID and exception message; email failure is silent to the user (non-blocking)

## Inputs

- `includes/class-role-finder.php` — from T01, provides `RoleFinder::get_user_ids_by_role()`
- `includes/class-email-template.php` — existing `EmailTemplate::render()` for branded HTML emails
- `includes/class-mention-notifications.php` — reference for `wp_mail()` call pattern with From header and root domain extraction
- `src/hooks/usePeople.js` — provides `peopleKeys` export for query invalidation

## Expected Output

- `src/components/FinancesCard.jsx` — modified with confirmation dialog and additional query invalidation
- `includes/class-fee-cache-invalidator.php` — modified with `send_exclusion_notification_email()` method and call from toggle handler
