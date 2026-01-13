# Caelis Tech Debt Cleanup

## What This Is

A focused cleanup milestone for the Caelis personal CRM, addressing code quality and security issues identified during codebase mapping. The goal is to improve maintainability and security without changing functionality or breaking existing API contracts.

## Core Value

Split the 107KB `class-rest-api.php` into manageable, domain-specific files while maintaining full backward compatibility with all existing API endpoints.

## Requirements

### Validated

<!-- Existing functionality from codebase that must continue working -->

- ✓ Personal CRM with people, companies, dates management — existing
- ✓ WordPress theme with React SPA frontend — existing
- ✓ REST API communication (wp/v2 + prm/v1 namespaces) — existing
- ✓ Slack integration for notifications (OAuth, webhooks) — existing
- ✓ CardDAV sync support via Sabre/DAV — existing
- ✓ Import from Google Contacts, Monica CRM, vCard — existing
- ✓ Export to vCard and Google CSV — existing
- ✓ User-scoped data isolation — existing
- ✓ Email and Slack notification channels — existing
- ✓ iCal feed generation — existing

### Active

<!-- Tech debt cleanup scope -->

**Code Quality:**
- [ ] Split `class-rest-api.php` into domain-specific classes
- [ ] Remove 30+ `console.error()` calls from production code
- [ ] Create `.env.example` documenting required environment variables
- [ ] Consolidate duplicated `decodeHtml()` logic

**Security:**
- [ ] Encrypt Slack tokens properly (replace base64 with sodium)
- [ ] Add client-side XSS protection (DOMPurify for dangerouslySetInnerHTML)
- [ ] Validate Slack webhook URLs (whitelist hooks.slack.com domain)
- [ ] Review and document public REST endpoints (permission_callback = __return_true)

### Out of Scope

- Testing setup — deferred to future milestone, not blocking this cleanup
- Performance fixes (N+1 queries, pagination) — separate optimization work
- New features — pure cleanup, no functionality changes
- Refactoring React components — focus is PHP backend and security

## Context

**Codebase State:**
- WordPress theme (PHP 8.0+) with React 18 SPA
- ~107KB monolithic REST API class handling all endpoints
- Security patterns in place but some gaps (token storage, XSS)
- Well-structured frontend but debug logging left in production
- Full codebase map available in `.planning/codebase/`

**Key Files:**
- `includes/class-rest-api.php` — main target for splitting (~2800 lines)
- `src/components/Timeline/TimelineView.jsx` — XSS concern
- `src/pages/People/PersonDetail.jsx` — console.error cleanup
- `src/utils/formatters.js` — HTML decode consolidation

## Constraints

- **Backward Compatibility**: All existing REST API endpoints must continue working exactly as before. No breaking changes to request/response contracts.
- **No New Dependencies** (except security): Avoid adding packages unless required for security (e.g., sodium for encryption is acceptable).

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Split REST API by domain | 107KB file is unmaintainable, domains are clear (people, companies, slack, import/export) | — Pending |
| Use sodium for token encryption | Built into PHP 7.2+, no external dependency needed | — Pending |
| Add DOMPurify for XSS | Industry standard, lightweight, React-compatible | — Pending |

---
*Last updated: 2025-01-13 after initialization*
