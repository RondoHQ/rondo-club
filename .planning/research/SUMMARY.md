# Project Research Summary

**Project:** Caelis v5.0 - Google Contacts Two-Way Sync
**Domain:** Personal CRM - Google Contacts Integration
**Researched:** 2026-01-17
**Confidence:** HIGH

## Executive Summary

Google Contacts bidirectional sync is a well-established integration pattern with clear user expectations and comprehensive official documentation. Caelis is uniquely positioned to implement this because the core infrastructure already exists: the `google/apiclient` library is installed, OAuth token encryption (Sodium) is working, and WP-Cron background sync patterns are proven in the Calendar integration. **No new Composer dependencies are required** - only OAuth scope expansion and new PHP classes following established patterns.

The recommended approach is to treat Caelis as the source of truth, implement comprehensive field mapping (not just basic name/email/phone like most CRMs), and use Google's syncToken mechanism for efficient delta synchronization. The user's stated preferences (Caelis wins conflicts, photos preserve on initial sync, deletions propagate from Caelis but only unlink from Google) align perfectly with industry best practices for a CRM-centric workflow.

Key risks center on OAuth configuration (testing mode 7-day token expiry), API behavior (syncToken expiration, ETag conflicts, write propagation delays), and WordPress constraints (WP-Cron timeouts). All are mitigable with proper error handling, chunked processing, and following the documented patterns. The 8-phase architecture research provides a clear build order with explicit dependencies.

## Key Findings

### Recommended Stack

Caelis already has everything needed. The existing `google/apiclient ^2.15` includes `Google_Service_PeopleService` for all People API operations. The OAuth flow, Sodium-based token encryption, and WP-Cron patterns transfer directly from Calendar integration.

**Core technologies:**
- **google/apiclient (existing):** Google API PHP client - already installed, provides People API service classes
- **Sodium encryption (existing):** Token storage - reuse CredentialEncryption class for contacts tokens
- **WP-Cron (WordPress native):** Background sync scheduling - matches Calendar sync pattern exactly
- **Google People API v1:** Contacts CRUD + delta sync via syncToken - official, well-documented API

**No new dependencies needed.** This significantly reduces risk and maintains consistency with existing codebase patterns.

### Expected Features

**Must have (table stakes):**
- OAuth-based one-click connection (extend existing Calendar OAuth)
- Bidirectional sync (one-way is considered "broken" by users)
- Comprehensive field mapping (name, email, phone, address, birthday, photo, work history)
- Duplicate detection on import (match by email first, then name)
- Sync status visibility ("Last synced" timestamp, sync badges)
- Manual sync trigger and error reporting
- Disconnect option that removes tokens but preserves data

**Should have (competitive differentiation):**
- Clear source of truth model ("Caelis wins" by default)
- Delta sync with syncToken (only sync changes, not full re-import)
- Per-contact sync toggle (privacy control for sensitive contacts)
- Configurable conflict resolution strategies
- Sync audit log for accountability
- Work history auto-creation from Google organization data

**Defer (v2+):**
- Contact group/label sync (complex mapping, unclear requirements)
- Custom field sync (requires defining what "custom fields" means)
- Multi-account support (massive complexity, merge rule confusion)
- Real-time sync (Google Contacts has no webhook support)

### Architecture Approach

The architecture follows existing Caelis patterns under a new `Caelis\Contacts` namespace. Five core classes handle distinct responsibilities: GoogleContactsProvider (API client with rate limiting), GoogleContactsSync (orchestrator with state management), GoogleContactsMapper (bidirectional field mapping), GoogleContactsConflict (conflict detection and resolution), and REST\GoogleContacts (API endpoints for UI). This separation enables isolated testing and matches the proven Calendar architecture.

**Major components:**
1. **GoogleContactsProvider** - Low-level API operations, error handling, rate limiting with exponential backoff
2. **GoogleContactsSync** - Sync orchestration, WP-Cron scheduling, lock management, state tracking
3. **GoogleContactsMapper** - Bidirectional field mapping, single source of truth for Google<->Caelis conversion
4. **GoogleContactsConflict** - Conflict detection, resolution strategies (newest/caelis/google/manual wins)
5. **REST\GoogleContacts** - REST API endpoints for connection management, sync triggers, conflict resolution UI

### Critical Pitfalls

1. **OAuth Testing Mode (CRITICAL)** - Refresh tokens expire after 7 days in testing mode. Must switch to production mode BEFORE any users connect, then generate NEW OAuth credentials. Existing tokens will still expire; users re-auth once.

2. **SyncToken Expiration** - Sync tokens expire after exactly 7 days. If background sync fails for a week, next attempt requires full resync that may exceed quotas. Prevention: automatic full-sync fallback on 410 error, proactive resync if approaching 7 days.

3. **ETag Conflicts** - Updates fail silently if contact changed between read and write. Prevention: always fetch fresh before updating, implement retry logic (fetch-merge-retry max 3 times), never cache contacts for deferred updates.

4. **Write Propagation Delays** - Google states writes have "propagation delay of several minutes." Never rely on read-after-write consistency. Use update response as source of truth, show "syncing..." state in UI.

5. **WP-Cron Timeouts** - Default 60-second timeout kills large syncs. Prevention: chunked processing (50-100 contacts per run), store progress for resume, consider Action Scheduler for reliable background jobs.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: OAuth Foundation
**Rationale:** All subsequent phases depend on authenticated API access. OAuth misconfiguration (testing mode) is the highest-risk pitfall.
**Delivers:** Working OAuth connection with contacts scope, token storage, connection status UI
**Addresses:** OAuth-based connection (table stakes)
**Avoids:** Testing mode 7-day token expiry, insecure token storage

### Phase 2: One-Way Import
**Rationale:** Import-only allows validating field mapping, duplicate detection, and API behavior before tackling bidirectional complexity.
**Delivers:** Full sync of Google contacts to Caelis, field mapping, duplicate detection, photo sideloading
**Uses:** GoogleContactsProvider, GoogleContactsMapper (Google->Caelis direction)
**Implements:** Basic sync infrastructure, personFields mask, pagination handling

### Phase 3: One-Way Export
**Rationale:** Export depends on working import (field mapping tested) and establishes the Caelis->Google direction.
**Delivers:** Create new Caelis contacts in Google, link existing contacts, bulk export
**Uses:** GoogleContactsMapper (Caelis->Google direction), ETag for conflict prevention
**Implements:** Contact creation in Google, resourceName tracking

### Phase 4: Delta Sync
**Rationale:** Full sync on every run is inefficient and hits rate limits. Delta sync is required for sustainable background operation.
**Delivers:** syncToken-based incremental sync, change detection for local edits, WP-Cron scheduling
**Uses:** GoogleContactsSync orchestrator
**Avoids:** SyncToken expiration, WP-Cron timeouts (via chunked processing)

### Phase 5: Conflict Resolution
**Rationale:** With bidirectional delta sync working, conflicts become possible and must be handled explicitly.
**Delivers:** Conflict detection, resolution strategies, manual conflict queue, audit logging
**Uses:** GoogleContactsConflict class
**Implements:** "Caelis wins" default strategy, field-level merge option

### Phase 6: Settings UI
**Rationale:** Backend must be complete before exposing configuration options to users.
**Delivers:** Google Contacts settings card, sync preferences, connection management, sync status display
**Uses:** REST\GoogleContacts endpoints
**Addresses:** Sync status visibility, manual sync trigger (table stakes)

### Phase 7: Person Detail UI
**Rationale:** Per-contact features depend on global sync being stable and Settings UI providing context.
**Delivers:** Sync status indicator on person detail, per-contact sync toggle, conflict resolution modal, "View in Google" link
**Addresses:** Per-contact sync control (differentiator)

### Phase 8: Polish and Edge Cases
**Rationale:** Final hardening after all features work. Edge cases discovered during earlier phases addressed here.
**Delivers:** Deletion handling, error recovery, WP-CLI commands, performance optimization, documentation
**Avoids:** All remaining pitfalls (deletion markers, birthday date inconsistency, resourceName changes)

### Phase Ordering Rationale

- **Dependencies flow downward:** OAuth must work before import, import before export, delta sync before conflict resolution
- **Risk reduction:** OAuth pitfall is addressed first; API behavior validated in Phase 2 before building bidirectional
- **UI comes last:** Backend must be stable before exposing controls that depend on it
- **Matches architecture components:** Each phase roughly maps to one major component being built

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2 (Import):** Field mapping edge cases (multiple emails, address formats, birthday without year)
- **Phase 5 (Conflict Resolution):** Conflict merge algorithms, field-level comparison logic

Phases with standard patterns (skip research-phase):
- **Phase 1 (OAuth):** Well-documented Google OAuth, existing Caelis patterns
- **Phase 3 (Export):** Reverse of import, same API patterns
- **Phase 4 (Delta Sync):** Google's syncToken is well-documented, follow existing Calendar pattern
- **Phase 6-8 (UI/Polish):** Standard React patterns, Caelis UI conventions established

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Official Google documentation, existing Caelis implementation proves patterns work |
| Features | HIGH | Multiple CRM comparisons, user community feedback, clear competitive landscape |
| Architecture | HIGH | Based on existing Caelis patterns + official Google API documentation |
| Pitfalls | HIGH | Official documentation warnings + verified community reports of issues |

**Overall confidence:** HIGH

All research derives from official Google documentation (People API docs, OAuth docs) or verified Caelis codebase patterns. Competitive analysis used multiple CRM documentation sources that agree on user expectations.

### Gaps to Address

- **Photo upload size limits:** Google docs don't specify maximum size. Recommend testing with 5MB limit, adjust if needed.
- **Batch operation error handling:** Partial batch failures need explicit handling strategy - define during Phase 2 implementation.
- **"Other Contacts" sync scope:** User mentioned syncing from "Other Contacts" (auto-created from email). May need additional scope (`contacts.other.readonly`). Verify during Phase 1.

## Sources

### Primary (HIGH confidence)
- [Google People API - Read and Manage Contacts](https://developers.google.com/people/v1/contacts)
- [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)
- [Google People API - updateContact](https://developers.google.com/people/api/rest/v1/people/updateContact)
- [Google People API - updateContactPhoto](https://developers.google.com/people/api/rest/v1/people/updateContactPhoto)
- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Google OAuth Best Practices](https://developers.google.com/identity/protocols/oauth2/resources/best-practices)

### Secondary (MEDIUM confidence)
- [HubSpot Community - Google Contacts Sync Issues](https://community.hubspot.com/t5/CRM/google-contacts-sync-wrong-with-crm-Hubspot/td-p/964692) - User expectations and pain points
- [Less Annoying CRM - Syncing with Google Contacts](https://www.lessannoyingcrm.com/help/how-do-i-sync-with-google-contacts) - Competitive feature analysis
- [RealSynch - How to Sync Google Contacts with Any CRM](https://www.realsynch.com/how-to-sync-google-contacts-with-any-crm-without-losing-data/) - Industry best practices

### Tertiary (LOW confidence)
- [Action Scheduler Performance](https://actionscheduler.org/perf/) - WP-Cron alternative if timeout issues emerge

---
*Research completed: 2026-01-17*
*Ready for roadmap: yes*
