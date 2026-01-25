# Pitfalls Research: Google Contacts Sync

**Domain:** Bidirectional Google Contacts Sync via People API
**Researched:** 2026-01-17
**Confidence:** HIGH (based on official Google documentation and verified community reports)

## Critical Pitfalls

Mistakes that cause rewrites, data loss, or major integration failures.

### Pitfall 1: SyncToken Expiration Causes Full Re-sync Storms

**What goes wrong:** Sync tokens expire after exactly 7 days. If your background sync job fails for a week (server downtime, cron issues, rate limits), the next sync attempt fails with `EXPIRED_SYNC_TOKEN` error, forcing a full re-sync that may exceed quotas.

**Why it happens:** Teams assume sync tokens are permanent or very long-lived. They don't implement automatic fallback to full sync.

**Consequences:**
- 429 quota errors if full sync quota exceeded
- Sync completely stops until manually reset
- Users see stale data for extended periods

**Prevention:**
- Implement automatic full-sync fallback when `EXPIRED_SYNC_TOKEN` is detected
- Track last successful sync timestamp; proactively full-sync if approaching 7 days
- Monitor sync health with alerts for failures > 24 hours
- Store sync tokens with timestamps for debugging

**Detection:**
- Error logs showing `google.rpc.ErrorInfo` with reason `EXPIRED_SYNC_TOKEN`
- Sync jobs completing without processing any records
- Users reporting Google changes not appearing in Stadion

**Phase to address:** Phase 1 (Core Sync Infrastructure)

**Source:** [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)

---

### Pitfall 2: ETag Conflicts Cause Silent Update Failures

**What goes wrong:** Updates fail with 400 `failedPrecondition` error because the contact's etag changed between read and write. This happens frequently with active contacts that sync across multiple devices.

**Why it happens:** The People API uses optimistic concurrency control. If any change occurs between when you read a contact and when you write your update (even from Google's own auto-enrichment), your update fails.

**Consequences:**
- Updates silently fail in batch operations
- Data becomes inconsistent between Stadion and Google
- Users manually edit in Google, sync overwrites their changes
- Intermittent failures that are hard to reproduce

**Prevention:**
- Always fetch the latest contact immediately before updating
- Use the etag from the updateContact response for sequential updates
- Implement retry logic: on etag failure, re-fetch, re-merge, retry (max 3 times)
- Never cache contacts for deferred updates; always fetch fresh
- Send mutate requests sequentially, never in parallel for the same user

**Detection:**
- 400 errors with `failedPrecondition` reason
- Updates that succeed in testing but fail in production
- User complaints that edits keep reverting

**Phase to address:** Phase 2 (Conflict Resolution)

**Source:** [Google People API - updateContact](https://developers.google.com/people/api/rest/v1/people/updateContact)

---

### Pitfall 3: OAuth Testing Mode 7-Day Token Expiry

**What goes wrong:** Refresh tokens expire after 7 days, breaking sync for all users. The app stops working with `invalid_grant` errors.

**Why it happens:** OAuth consent screen is left in "Testing" mode during development. In testing mode, refresh tokens are intentionally short-lived.

**Consequences:**
- All users must re-authorize every 7 days
- Background sync completely stops
- Users blame your app for being broken
- If discovered late, requires re-generating all OAuth credentials

**Prevention:**
- Switch to "In production" mode before real users connect
- After switching to production, generate NEW OAuth credentials
- Existing tokens from testing mode still expire; users must re-authorize once
- Implement token health checks that alert before expiry
- Store token issue timestamps to predict expiry

**Detection:**
- `invalid_grant` errors with `error_description: "Token has been revoked"`
- All sync connections failing simultaneously around 7 days after setup
- Token refreshes failing despite valid credentials

**Phase to address:** Phase 1 (OAuth Setup) - BEFORE any user testing

**Source:** [Google OAuth Documentation](https://developers.google.com/identity/protocols/oauth2), [Nango OAuth Guide](https://nango.dev/blog/google-oauth-invalid-grant-token-has-been-expired-or-revoked)

---

### Pitfall 4: Write Propagation Delays Break Read-After-Write

**What goes wrong:** You write a contact to Google, immediately read it back for confirmation, and it returns stale data or 404. Code assumes the write failed.

**Why it happens:** Google explicitly states: "Writes may have a propagation delay of several minutes for sync requests. Incremental syncs are not intended for read-after-write use cases."

**Consequences:**
- Sync logic assumes write failed and retries endlessly
- Duplicate contacts created
- UI shows stale data to users
- Flaky tests that pass sometimes

**Prevention:**
- Never rely on immediate read-after-write consistency
- Use the response from create/update operations as source of truth
- Implement eventual consistency patterns: track pending changes locally
- Add delays before sync verification (or skip verification entirely)
- For UI, show "syncing..." state and update when next sync confirms

**Detection:**
- Intermittent "contact not found" errors after successful creates
- Tests that are flaky based on timing
- Duplicate contacts appearing in Google

**Phase to address:** Phase 2 (Sync Logic)

**Source:** [Google People API - Read and Manage Contacts](https://developers.google.com/people/v1/contacts)

---

## API Pitfalls

Issues specific to Google People API behavior.

### Pitfall 5: personFields Mask is Required (400 Errors)

**What goes wrong:** API calls fail with 400 error: "personFields mask is required."

**Why it happens:** Unlike many REST APIs, People API requires explicit field selection. You must specify exactly which fields to return.

**Prevention:**
- Always include `personFields` parameter in read operations
- Create a constant with all fields you need: `names,emailAddresses,phoneNumbers,addresses,birthdays,organizations,photos,memberships`
- Document which fields Stadion uses and sync that list

**Detection:** 400 errors mentioning "personFields mask is required"

**Phase to address:** Phase 1 (API Client Setup)

**Source:** [Google People API - RequestMask](https://developers.google.com/people/api/rest/v1/RequestMask)

---

### Pitfall 6: Parameter Consistency Required Across Pagination

**What goes wrong:** Paginated requests fail or return inconsistent data because parameters changed between pages.

**Why it happens:** "When the pageToken or syncToken is specified, all other request parameters must match the first call."

**Prevention:**
- Store the exact parameters used for initial request
- Reuse identical parameters for all subsequent pages
- Never adjust personFields, sortOrder, or filters mid-pagination

**Detection:**
- Pagination mysteriously stops working
- Inconsistent or duplicate results across pages

**Phase to address:** Phase 1 (Initial Sync Implementation)

**Source:** [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)

---

### Pitfall 7: Photo Updates Require Separate API Calls

**What goes wrong:** Contact updates succeed but photos don't change. Developers assume photos are included in regular contact updates.

**Why it happens:** "Standard contact mutations affect all contact fields except photos. You must update a contact photo using `people.updateContactPhoto`."

**Prevention:**
- Implement separate photo sync logic
- Use `updateContactPhoto` for photo updates
- Use `deleteContactPhoto` for photo removal
- Track photo changes independently from contact data changes

**Detection:**
- Photos not syncing despite successful contact updates
- No errors but photos remain unchanged

**Phase to address:** Phase 3 (Photo Sync)

**Source:** [Google People API - updateContactPhoto](https://developers.google.com/people/api/rest/v1/people/updateContactPhoto)

---

### Pitfall 8: Contact Group Membership Constraints

**What goes wrong:** Adding contacts to system groups fails. Removing contacts from all groups fails.

**Why it happens:**
- Only `contactGroups/myContacts` and `contactGroups/starred` can have members added
- Other system contact groups can only have contacts removed
- A contact must always have at least one group membership

**Consequences:**
- Sync fails when trying to replicate group structures
- Contacts cannot be "ungrouped" without alternative group

**Prevention:**
- Always ensure at least `myContacts` membership
- Don't try to add to system groups other than myContacts/starred
- For labels/categories, use user-created contact groups, not system groups

**Detection:**
- 400 errors when modifying memberships
- Error: "no contact group memberships specified"

**Phase to address:** Phase 4 (if implementing group sync)

**Source:** [Google People API - contactGroups.members.modify](https://developers.google.com/people/api/rest/v1/contactGroups.members/modify)

---

## Performance Pitfalls

Rate limits, timeouts, and scalability issues.

### Pitfall 9: Full Sync Quota is Fixed and Cannot Be Increased

**What goes wrong:** Full sync requests hit 429 quota errors. Unlike other Google quotas, this one cannot be increased.

**Why it happens:** "The first page of a full sync request has an additional quota. If the quota is exceeded, a 429 error will be returned. This quota is fixed and can not be increased."

**Consequences:**
- Users with many contacts cannot complete initial sync
- Sync stuck in permanent retry loop
- No workaround via quota increase requests

**Prevention:**
- Implement exponential backoff for 429 errors
- Space out full syncs across users (don't trigger all at once)
- Prefer incremental sync over full sync whenever possible
- Track sync token health to avoid unnecessary full syncs
- Consider time-of-day scheduling for full syncs (off-peak hours)

**Detection:**
- 429 errors specifically on first page of full sync
- Initial sync never completing for some users

**Phase to address:** Phase 1 (Rate Limiting Infrastructure)

**Source:** [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)

---

### Pitfall 10: Batch Operation Limits Vary by Method

**What goes wrong:** Batch operations fail because limits differ by method and developers assume uniform limits.

**Why it happens:** Different batch methods have different limits:
| Method | Limit |
|--------|-------|
| Generic batch requests | 1000 calls |
| batchCreateContacts | 200 contacts |
| batchUpdateContacts | 200 contacts |
| batchDeleteContacts | 500 resource names |
| getBatchGet | 200 resource names |

**Prevention:**
- Implement method-specific chunking
- Don't assume one limit fits all operations
- Add buffer below limits (e.g., 180 instead of 200) for safety

**Detection:**
- Some batch operations succeed, others fail
- Errors mentioning request size limits

**Phase to address:** Phase 2 (Batch Operations)

**Source:** [Google People API - Batching Requests](https://developers.google.com/people/v1/batch)

---

### Pitfall 11: WordPress WP-Cron Timeouts

**What goes wrong:** Sync jobs timeout after 60 seconds, leaving sync incomplete.

**Why it happens:** `WP_CRON_LOCK_TIMEOUT` defaults to 60 seconds. Large contact lists take longer to sync.

**Consequences:**
- Partial syncs that appear complete
- Same contacts processed repeatedly
- Sync never fully completes

**Prevention:**
- Use chunked processing: sync N contacts per cron run
- Store sync progress (last processed ID/offset)
- Schedule frequent small syncs rather than infrequent large ones
- Consider Action Scheduler for better background job handling
- Set reasonable chunk sizes (50-100 contacts per run)

**Detection:**
- Cron jobs consistently taking 60+ seconds
- Sync progress stuck at same point
- PHP timeout errors in logs

**Phase to address:** Phase 1 (Background Processing Infrastructure)

**Source:** [WordPress WP-Cron Documentation](https://developer.wordpress.org/plugins/cron/), [Action Scheduler](https://actionscheduler.org/perf/)

---

### Pitfall 12: Sequential Mutations Required for Same User

**What goes wrong:** Parallel update requests cause increased latency and random failures.

**Why it happens:** "Mutate requests for the same user should be sent sequentially to avoid increased latency and failures."

**Prevention:**
- Queue mutations per user
- Process user's mutations serially, even if other users can be parallel
- Avoid parallel batch updates for same contact list

**Detection:**
- Random failures in batch updates
- Higher than expected latency
- Intermittent etag conflicts

**Phase to address:** Phase 2 (Sync Queue Implementation)

**Source:** [Google People API - Read and Manage Contacts](https://developers.google.com/people/v1/contacts)

---

## Security Pitfalls

OAuth, token storage, and scope issues.

### Pitfall 13: Insecure Token Storage

**What goes wrong:** OAuth tokens stored in plaintext in database. Data breach exposes all users' Google account access.

**Why it happens:** Developers store tokens like any other data without encryption.

**Consequences:**
- Database breach = full access to all users' Google Contacts
- Compliance violations (GDPR, etc.)
- Reputation damage

**Prevention:**
- Encrypt tokens at rest (use WordPress encryption or dedicated secrets manager)
- Never log tokens or include in error messages
- Use short-lived access tokens, refresh only when needed
- Implement token revocation on user disconnect
- Store only refresh tokens; access tokens can be regenerated

**Detection:**
- Security audit finding plaintext tokens
- Tokens visible in logs or debug output

**Phase to address:** Phase 1 (OAuth Infrastructure)

**Source:** [Google OAuth Best Practices](https://developers.google.com/identity/protocols/oauth2/resources/best-practices)

---

### Pitfall 14: Refresh Token Limit Exceeded

**What goes wrong:** Old refresh tokens stop working. Users must re-authorize repeatedly.

**Why it happens:** "Limits apply to the number of refresh tokens that are issued per client-user combination... older refresh tokens stop working."

**Prevention:**
- Store only one active refresh token per user
- Revoke old tokens when issuing new ones
- Don't re-authorize users unnecessarily
- Implement proper disconnect/reconnect flow

**Detection:**
- Users frequently asked to re-authorize
- `invalid_grant` errors for seemingly valid tokens

**Phase to address:** Phase 1 (Token Management)

**Source:** [Google OAuth Best Practices](https://developers.google.com/identity/protocols/oauth2/resources/best-practices)

---

### Pitfall 15: User Revokes Access Silently

**What goes wrong:** User revokes access in Google Account settings. Sync silently fails. App never prompts re-authorization.

**Why it happens:** Token revocation happens outside your app. Without proper error handling, failures look like API issues.

**Consequences:**
- Sync data becomes stale without warning
- Users don't know to re-authorize
- Background jobs fail repeatedly

**Prevention:**
- Detect `invalid_grant` errors and mark connection as revoked
- Notify user via email or in-app that re-authorization needed
- Show connection status clearly in UI
- Implement periodic token validation (not just refresh)
- Handle revocation as normal condition, not error

**Detection:**
- `invalid_grant` errors with `error_description: "Token has been revoked"`
- HTTP 400/401 errors on token refresh
- Sync jobs failing consistently for specific users

**Phase to address:** Phase 1 (OAuth Error Handling)

**Source:** [Google People API - Troubleshoot Authentication](https://developers.google.com/people/v1/troubleshoot-authentication-authorization)

---

## Data Integrity Pitfalls

How to avoid data loss or corruption.

### Pitfall 16: No Duplicate Detection in People API

**What goes wrong:** Sync creates duplicate contacts in Google. Multiple entries for same person.

**Why it happens:** People API has no built-in duplicate detection. If you create a contact that matches an existing one, you get a duplicate.

**Consequences:**
- Users see duplicates in Google Contacts
- Sync state becomes confused (which duplicate is canonical?)
- Manual cleanup required

**Prevention:**
- Implement your own duplicate detection before create
- Search by email/phone before creating new contacts
- Store Google resourceName mapping to prevent re-creates
- Use unique identifier (Stadion person ID) in clientData field

**Detection:**
- Users reporting duplicates
- Multiple resourceNames mapping to same Stadion person

**Phase to address:** Phase 2 (Sync Logic)

**Source:** [Google Contacts Help - Merge duplicates](https://support.google.com/contacts/answer/7078226)

---

### Pitfall 17: Deleted Contacts Return as Markers, Not Removed

**What goes wrong:** Code treats deleted contact markers as regular contacts, or ignores them entirely.

**Why it happens:** "When the syncToken is specified, resources deleted since the last sync will be returned as a person with PersonMetadata.deleted set to true."

**Prevention:**
- Check `PersonMetadata.deleted` on every synced contact
- Implement deletion handling: remove mapping, optionally soft-delete in Stadion
- Test deletion sync path explicitly

**Detection:**
- Deleted contacts reappearing
- Mapping table growing indefinitely
- Ghost contacts in Stadion

**Phase to address:** Phase 2 (Deletion Sync)

**Source:** [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)

---

### Pitfall 18: Birthday Date vs Text Inconsistency

**What goes wrong:** Birthday displays incorrectly or loses year information.

**Why it happens:** Birthday has both `date` and `text` fields. "The date and text fields typically represent the same date, but are not guaranteed to."

**Prevention:**
- Always use `date` field for birthday logic
- Always set `date` field when writing birthdays
- Use year=0 for birthdays without known year (like anniversaries)
- Don't rely on `text` field for parsing

**Detection:**
- Birthdays showing wrong dates
- Years appearing/disappearing unexpectedly

**Phase to address:** Phase 2 (Field Mapping)

**Source:** [Google People API - Birthday](https://developers.google.com/resources/api-libraries/documentation/people/v1/java/latest/com/google/api/services/people/v1/model/Birthday.html)

---

### Pitfall 19: resourceName Can Change When Linking Contact to Profile

**What goes wrong:** Stored resourceName becomes invalid. Contact appears deleted then recreated.

**Why it happens:** "The resource name may change when adding or removing fields that link a contact and profile such as a verified email, verified phone number, or profile URL."

**Prevention:**
- Handle resourceName changes during sync
- Use etag changes as signal to re-verify resourceName
- Store additional identifiers for fuzzy matching (email, phone)
- Don't assume resourceName is immutable

**Detection:**
- Contacts appearing as new that were previously synced
- "Not found" errors for previously valid resourceNames
- Orphaned mapping records

**Phase to address:** Phase 2 (Resource Name Tracking)

**Source:** [Google People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)

---

## Sync Logic Pitfalls

Bidirectional sync specific issues.

### Pitfall 20: Conflict Resolution Without Clear Winner

**What goes wrong:** Both systems modified same contact. Sync overwrites one set of changes, losing data.

**Why it happens:** No clear "source of truth" definition or conflict detection.

**Consequences:**
- User edits lost
- Data appears to randomly change
- Users lose trust in sync

**Prevention:**
- Define clear source of truth (spec says Stadion is master)
- Track last-modified timestamps on both sides
- Implement conflict detection: if both modified since last sync, flag for review
- Consider field-level merging for non-conflicting changes
- Log all conflict resolutions for debugging

**Detection:**
- User complaints about lost edits
- Data changing unexpectedly
- Same contact flip-flopping between values

**Phase to address:** Phase 2 (Conflict Resolution) - CRITICAL

**Source:** [Merge.dev - Bidirectional Sync Guide](https://www.merge.dev/blog/bidirectional-synchronization)

---

### Pitfall 21: Initial Sync Overwhelms Rate Limits

**What goes wrong:** User connects account with 10,000 contacts. Initial sync hammers API, gets rate limited, never completes.

**Why it happens:** Initial sync tries to process everything at once.

**Prevention:**
- Chunk initial sync (e.g., 100 contacts per cron run)
- Show progress to user ("Syncing... 500/10,000 contacts")
- Implement exponential backoff on rate limits
- Prioritize recently modified contacts first
- Allow partial sync state (resume from where we left off)

**Detection:**
- 429 errors during initial sync
- Sync progress stuck for large accounts
- User complaints about initial sync taking forever

**Phase to address:** Phase 1 (Initial Sync Implementation)

---

### Pitfall 22: Sync Loop - Changes Ping-Pong Between Systems

**What goes wrong:** Change in Stadion syncs to Google, detected as Google change, syncs back to Stadion, triggers sync to Google... infinite loop.

**Why it happens:** No mechanism to recognize your own changes coming back.

**Prevention:**
- Track origin of each change (local vs remote)
- Store sync timestamps to ignore changes we just made
- Use clientData field to mark Stadion-originated contacts
- Compare content, not just timestamps, to detect real changes
- Implement change deduplication window (ignore changes within N seconds of our write)

**Detection:**
- CPU/API usage spikes
- Same contact continuously being updated
- Sync queue never emptying

**Phase to address:** Phase 2 (Change Detection)

---

## Phase-Specific Warnings Summary

| Phase Topic | Likely Pitfall | Severity | Mitigation |
|-------------|---------------|----------|------------|
| OAuth Setup | Testing mode 7-day expiry | CRITICAL | Switch to production before users |
| OAuth Setup | Insecure token storage | HIGH | Encrypt at rest |
| Initial Sync | Rate limit exceeded | HIGH | Chunked processing, exponential backoff |
| Initial Sync | SyncToken not stored | MEDIUM | Persist with timestamp |
| Delta Sync | ETag conflicts | HIGH | Always fetch fresh before update |
| Delta Sync | Propagation delays | MEDIUM | Don't rely on read-after-write |
| Conflict Resolution | No clear winner | CRITICAL | Stadion is source of truth |
| Conflict Resolution | Sync loops | HIGH | Track change origin |
| Photo Sync | Separate API required | MEDIUM | Use updateContactPhoto |
| Deletion Sync | Markers not processed | MEDIUM | Check PersonMetadata.deleted |
| Background Jobs | WP-Cron timeout | HIGH | Chunked processing, Action Scheduler |

---

## Prevention Checklist by Category

### Before Writing Any Code
- [ ] OAuth consent screen in "In production" mode
- [ ] Token encryption strategy defined
- [ ] Rate limiting strategy documented
- [ ] Conflict resolution rules agreed upon
- [ ] Error handling patterns established

### API Client Setup
- [ ] personFields constant defined with all needed fields
- [ ] Error handling for 400, 401, 403, 429 responses
- [ ] Exponential backoff implemented
- [ ] Request logging for debugging

### Sync Infrastructure
- [ ] SyncToken storage with timestamps
- [ ] Full-sync fallback for expired tokens
- [ ] Chunked processing for large datasets
- [ ] Change origin tracking (local vs remote)
- [ ] Deletion handling for PersonMetadata.deleted

### OAuth Flow
- [ ] Refresh token encrypted storage
- [ ] Revocation detection and user notification
- [ ] Re-authorization flow implemented
- [ ] Token health monitoring

### Testing
- [ ] Test with expired sync tokens
- [ ] Test with revoked access
- [ ] Test concurrent modifications (etag conflicts)
- [ ] Test large contact lists (1000+)
- [ ] Test deletion sync both directions

---

## Sources

### Official Google Documentation
- [People API - people.connections.list](https://developers.google.com/people/api/rest/v1/people.connections/list)
- [People API - Read and Manage Contacts](https://developers.google.com/people/v1/contacts)
- [People API - updateContact](https://developers.google.com/people/api/rest/v1/people/updateContact)
- [People API - updateContactPhoto](https://developers.google.com/people/api/rest/v1/people/updateContactPhoto)
- [People API - Batching Requests](https://developers.google.com/people/v1/batch)
- [People API - Troubleshoot Authentication](https://developers.google.com/people/v1/troubleshoot-authentication-authorization)
- [People API - batchCreateContacts](https://developers.google.com/people/api/rest/v1/people/batchCreateContacts)
- [People API - batchUpdateContacts](https://developers.google.com/people/api/rest/v1/people/batchUpdateContacts)
- [Google OAuth Best Practices](https://developers.google.com/identity/protocols/oauth2/resources/best-practices)
- [Google OAuth 2.0](https://developers.google.com/identity/protocols/oauth2)

### Community Resources
- [Nango - Google OAuth Invalid Grant](https://nango.dev/blog/google-oauth-invalid-grant-token-has-been-expired-or-revoked)
- [WordPress WP-Cron Documentation](https://developer.wordpress.org/plugins/cron/)
- [Action Scheduler Performance](https://actionscheduler.org/perf/)
- [Merge.dev - Bidirectional Sync](https://www.merge.dev/blog/bidirectional-synchronization)

### Issue Trackers and Community Reports
- [GitHub - googleapis/google-api-php-client Issues](https://github.com/googleapis/google-api-php-client/issues)
- [Google Issue Tracker - People API](https://issuetracker.google.com/issues?q=componentid:190966)
