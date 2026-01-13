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

**v1.0 Tech Debt Cleanup:**
- ✓ Split `class-rest-api.php` into domain-specific classes — v1.0
- ✓ Remove 48 `console.error()` calls from production code — v1.0
- ✓ Create `.env.example` documenting required environment variables — v1.0
- ✓ Consolidate duplicated `decodeHtml()` logic — v1.0
- ✓ Encrypt Slack tokens with sodium — v1.0
- ✓ Add server-side XSS protection with wp_kses — v1.0
- ✓ Validate Slack webhook URLs (whitelist hooks.slack.com) — v1.0
- ✓ Document public REST endpoints with security rationale — v1.0

### Active

(None - milestone complete)

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
| Split REST API by domain | 107KB file is unmaintainable, domains are clear (people, companies, slack, import/export) | ✓ Good — 5 domain classes created |
| Use sodium for token encryption | Built into PHP 7.2+, no external dependency needed | ✓ Good — implemented with fallback |
| Use WordPress native XSS functions | Server-side sanitization with wp_kses(), no client dependency needed | ✓ Good — 3 helper methods added |
| Restrict webhook URLs to hooks.slack.com | Prevent SSRF attacks via webhook configuration | ✓ Good — domain whitelist enforced |

---
*Last updated: 2026-01-13 after v1.0 milestone*
