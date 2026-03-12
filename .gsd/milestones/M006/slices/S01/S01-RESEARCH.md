# S01: Manual paid audit trail + display — Research

**Date:** 2026-03-12

## Summary

This slice adds an audit trail when a user manually marks an invoice as paid via the "Markeer als betaald" button. The change touches three layers: (1) store `_manually_marked_paid_at` and `_manually_marked_paid_by` post meta in the `update_invoice_status()` PHP method when transitioning to `paid`, (2) return these fields in `format_invoice_detail()`, and (3) update the Betaalgegevens card in `FactuurDetail.jsx` to render for both Mollie-paid and manually-paid invoices.

The implementation is straightforward with zero risk. The existing `_invoice_sent_by_user_id` pattern provides a direct precedent for user tracking via post meta. The Mollie webhook (`class-mollie-webhook.php`) uses a completely separate code path (`wp_update_post` directly) and never calls `update_invoice_status()`, so the manual-paid meta will only be stored for genuine manual actions.

## Recommendation

Follow the established meta storage pattern exactly. Three surgical edits:

1. **PHP backend** (`includes/class-rest-invoices.php` line ~1044): Add two `update_post_meta` calls in the `$status === 'paid'` block inside `update_invoice_status()` — store timestamp and current user ID.
2. **PHP REST response** (`includes/class-rest-invoices.php` `format_invoice_detail()` around line 2480): Add `manually_marked_paid_at` and `manually_marked_paid_by` fields to the return array, using `get_user_summary_by_id()` for the user field (returns `{id, name}` or `null`).
3. **Frontend** (`src/pages/Finance/FactuurDetail.jsx` line ~831): Change the Betaalgegevens card condition from `invoice.mollie_payment_method` to `invoice.mollie_payment_method || invoice.manually_marked_paid_at`. Add a section for manually-paid display showing "Handmatig gemarkeerd als betaald" with date/time and user name.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Get current user ID | `get_current_user_id()` (WordPress core) | Reliable, returns authenticated user from REST nonce |
| Current timestamp | `current_time('mysql')` (WordPress core) | Returns server-local time consistently |
| User display name from ID | `$this->get_user_summary_by_id()` (line 2097) | Already exists in the same class, returns `{id, name}` or `null` |
| Date formatting in React | `format()` from `@/utils/dateFormat` | Already imported in FactuurDetail.jsx, uses Dutch locale |

## Existing Code and Patterns

- `includes/class-rest-invoices.php:1028-1033` — The `sent` transition stores `_invoice_sent_by_user_id` and `_invoice_last_sent_by_user_id` using `get_current_user_id()`. Follow this exact pattern for the `paid` transition.
- `includes/class-rest-invoices.php:1044-1050` — The `paid` transition block currently removes payment artifacts (payment_link, mollie_payment_link_id, rabobank_payment_request_id, QR code). Add manual-paid meta storage here, BEFORE the artifact cleanup.
- `includes/class-rest-invoices.php:2097-2112` — `get_user_summary_by_id(int $user_id)` returns `['id' => ..., 'name' => ...]` or `null`. Reuse for the `manually_marked_paid_by` field.
- `includes/class-rest-invoices.php:2472-2486` — `format_invoice_detail()` already returns Mollie payment fields (`mollie_payment_method`, `mollie_paid_at`, etc.). Add the two new fields alongside.
- `includes/class-mollie-webhook.php:172-184` — Mollie webhook transitions to `rondo_paid` via `wp_update_post()` directly — does NOT call `update_invoice_status()`. This confirms no collision.
- `src/pages/Finance/FactuurDetail.jsx:831-880` — Betaalgegevens card currently conditional on `invoice.mollie_payment_method`. Widen condition and add manual-paid section.

## Constraints

- Must use post meta (not ACF fields) for `_manually_marked_paid_at` and `_manually_marked_paid_by` — consistent with `_invoice_sent_by_user_id` pattern and all other `_mollie_*` meta keys.
- `get_current_user_id()` is available in REST context because the route uses `check_financieel_permission` callback (authenticated).
- The `format_invoice_detail()` return must include both new fields even when null/empty so the frontend has a consistent contract.
- The Betaalgegevens card must show Mollie data when available (no regression) and show manual-paid data only when Mollie data is absent.

## Common Pitfalls

- **Don't store manual-paid meta for Mollie-paid invoices** — The `update_invoice_status()` method is only called from the REST endpoint (user action), never from the Mollie webhook. So this is inherently safe. However, if a user clicks "Markeer als betaald" on an invoice that was already partially paid via Mollie, the manual meta would be stored alongside Mollie meta. The frontend should prioritize Mollie data when both exist.
- **Timestamp format consistency** — Use `current_time('mysql')` which returns `Y-m-d H:i:s` format. This matches `_mollie_paid_at` format (ISO 8601 from Mollie). The frontend `format()` with `new Date()` handles both.
- **Return `null` not empty string for missing values** — Follow the Mollie fields pattern: cast to string then `?: null`. This way the frontend condition `invoice.manually_marked_paid_at` is falsy when not set.

## Open Risks

- None. All patterns are established, the code paths are isolated, and the change is additive only.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | — | Not needed (established patterns in codebase) |
| React | vercel-react-best-practices | installed (not needed for this small UI change) |
| TanStack Query | — | Not needed (no new hooks or queries) |

## Sources

- Existing codebase analysis (all references above from `includes/class-rest-invoices.php` and `src/pages/Finance/FactuurDetail.jsx`)
