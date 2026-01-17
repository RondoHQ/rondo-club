# Phase 79: OAuth Foundation - Context

**Gathered:** 2026-01-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Extend Google OAuth to include Contacts API scope, building on existing Calendar integration infrastructure. Users can connect Google Contacts via OAuth. Tokens are stored using existing Sodium encryption. This phase does NOT include import, export, or sync functionality — only the OAuth connection.

</domain>

<decisions>
## Implementation Decisions

### Permission upgrade flow
- Just-in-time prompting: users are prompted to add Contacts scope only when they click "Sync with Google Contacts"
- Entry point is Settings > Connections only (no buttons in People list or person profiles)
- After granting Contacts scope, auto-start initial import in background
- If user denies Contacts scope but keeps Calendar: show toast "Contacts permission not granted" with "Try Again" link

### Connection states & UI
- Separate cards for Google Calendar and Google Contacts in Settings > Connections
- Connected card shows: status, last sync time, contact count, error count, "Sync Now" button, Disconnect button
- Connected Google account email shown in smaller/subtle text
- Disconnected state shows simple "Connect Google Contacts" button only (no feature descriptions)

### Error handling
- Token refresh happens silently — no user notification unless refresh fails
- If refresh token is invalid (user revoked access in Google): send email notification "Your Google Contacts connection was disconnected"
- Log full error details to WordPress debug log for all failed OAuth attempts

### Scope bundling (new users)
- Single combined OAuth flow asking for both Calendar + Contacts scopes together
- If user denies consent entirely: modal explaining features that require Google, offer retry
- Read-only vs full-access is offered as an option (read-only = import only)
- Access choice presented during a guided setup wizard flow

### Claude's Discretion
- Exact error messaging for transient API failures
- Wizard flow design details
- Token storage implementation details (within existing Sodium encryption pattern)

</decisions>

<specifics>
## Specific Ideas

- Existing Google Calendar OAuth infrastructure should be extended, not replaced
- Setup wizard should feel lightweight, not like a lengthy onboarding process

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 79-oauth-foundation*
*Context gathered: 2026-01-17*
