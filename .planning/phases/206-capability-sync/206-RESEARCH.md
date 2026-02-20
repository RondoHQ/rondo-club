# Phase 206: Capability Sync - Research

**Researched:** 2026-02-20
**Domain:** WordPress user role management + cross-repo rondo-sync step
**Confidence:** HIGH

## Summary

Phase 206 is a cross-repository phase. Plan 01 lives in `rondo-club` (WordPress PHP): a new REST endpoint `POST /rondo/v1/capability-sync` that accepts a KNVB ID and its active Functies, reconciles the user's WordPress roles against the FunctieCapabilityMap, and applies grant-and-revoke with manual override protection. Plan 02 lives in `rondo-sync` (Node.js): a new step `submit-capability-sync.js` that iterates over all tracked members who have a provisioned WP user, reads their active functions from the local SQLite database, and calls the new endpoint. The pipeline is added to `pipelines/sync-functions.js` as a final step after the existing free-fields sync.

The architecture follows the well-established pattern in this codebase: pure service class (like `UserProvisioning`) called from REST callbacks in `class-rest-api.php`, plus a standalone rondo-sync step that calls `rondoClubRequestWithRetry`. The on-demand "sync all" action from Settings follows the same pattern as the existing `triggerReminders` and `rescheduleCronJobs` admin buttons.

**Primary recommendation:** Model the PHP service class on `UserProvisioning` (pure, no hooks), store manual override tracking in user meta (`_rondo_cap_manual_grant` and `_rondo_cap_manual_revoke` sets), guard administrators with `user_can('manage_options')`, and integrate the rondo-sync step into `sync-functions.js` pipeline after free-fields sync.

## Standard Stack

### Core (no new dependencies needed)

| Component | Location | Purpose | Notes |
|-----------|----------|---------|-------|
| WordPress `WP_User` roles | PHP built-in | `add_role()`, `remove_role()` on user object | Role slugs defined in `UserRoles::ROLES` |
| `FunctieCapabilityMap::get_map()` | `includes/class-functie-capability-map.php` | Read Functie → roles mapping | Phase 204 deliverable |
| `UserProvisioning::META_KNVB_ID` = `_rondo_knvb_id` | `includes/class-user-provisioning.php` | User meta: KNVB ID on WP user | Set during provisioning |
| `rondoClubRequestWithRetry` | `rondo-sync/lib/rondo-club-client.js` | HTTP calls to WP REST with retry | Retry on 5xx only |
| `getAllActiveMemberFunctions(db)` | `rondo-sync/lib/rondo-club-db.js` | Get all active Functies per KNVB ID | Returns `{knvb_id, function_description}[]` |
| `getActiveTrackedMembers(db)` | `rondo-sync/lib/rondo-club-db.js` | All members with `rondo_club_id` | Excludes former members |

### Roles in the system

From `class-user-roles.php`:

```php
const ROLES = [
    'rondo_user'     => [ 'Rondo User', [] ],
    'rondo_fairplay' => [ 'Rondo FairPlay', [ 'fairplay' ] ],
    'rondo_vog'      => [ 'Rondo VOG', [ 'vog' ] ],
    'rondo_bestuur'  => [ 'Rondo Bestuur', [ 'fairplay', 'vog', 'financieel' ] ],
];
```

Only roles in `UserRoles::ROLES` (keyed by slug) should be touched by capability sync. The `administrator` role is identified by `user_can('manage_options')` or `in_array('administrator', $user->roles)`.

## Architecture Patterns

### Recommended File Structure

```
rondo-club/
├── includes/
│   └── class-capability-sync.php          # New: pure service class
rondo-sync/
├── steps/
│   └── submit-capability-sync.js          # New: rondo-sync step
├── pipelines/
│   └── sync-functions.js                  # Modified: add step 5
```

### Pattern 1: Pure PHP Service Class (Plan 01)

**What:** `CapabilitySync` class in `Rondo\Users` namespace. No constructor, no hooks. Pure service methods called from REST callbacks.

**When to use:** All Phase 206 business logic.

**Key method signatures:**

```php
namespace Rondo\Users;

class CapabilitySync {
    // User meta key for manually-granted roles (set by admin override)
    const META_MANUAL_GRANTS = '_rondo_cap_manual_grants';
    // User meta key for manually-revoked roles (set by admin override)
    const META_MANUAL_REVOKES = '_rondo_cap_manual_revokes';

    /**
     * Sync capabilities for a single user given their current Functies.
     *
     * @param int    $user_id  WP user ID.
     * @param array  $functies Array of Functie strings (active ones from Sportlink).
     * @return array { granted: string[], revoked: string[], skipped: string[], unchanged: string[] }
     *              or WP_Error on failure.
     */
    public function sync_user( int $user_id, array $functies ): array|\WP_Error;

    /**
     * Sync capabilities for all provisioned users.
     * Iterates get_users() filtered by _rondo_knvb_id meta existence.
     * Looks up each user's KNVB ID, finds their Functies from the incoming
     * payload (or from person ACF work_history as fallback).
     *
     * Used by the on-demand "sync all" admin action.
     *
     * @param array $knvb_functies_map  Map of knvb_id => string[] functies.
     * @return array { total: int, synced: int, skipped: int, errors: [] }
     */
    public function sync_all( array $knvb_functies_map ): array;
}
```

### Pattern 2: Manual Override Tracking

**What:** Two user meta keys track administrator overrides so they survive automatic sync.

**Schema:**

```
_rondo_cap_manual_grants  → JSON-encoded array of role slugs admin has manually granted
_rondo_cap_manual_revokes → JSON-encoded array of role slugs admin has manually revoked
```

**Sync algorithm:**

```
For a user with functies [F1, F2]:
  mapped_roles = union of FunctieCapabilityMap::get_roles_for_functie(F) for each F
  manual_grants = get_user_meta(_rondo_cap_manual_grants)   // protected — always kept
  manual_revokes = get_user_meta(_rondo_cap_manual_revokes) // protected — always absent

  target_roles = (mapped_roles ∪ manual_grants) - manual_revokes - {'administrator'}

  current_rondo_roles = user->roles filtered to UserRoles::ROLES keys

  to_grant = target_roles - current_rondo_roles
  to_revoke = (current_rondo_roles - target_roles) filtered to NOT in manual_grants

  for role in to_grant: $user->add_role($role)
  for role in to_revoke: $user->remove_role($role)   // never remove rondo_user base role?
```

**Note on base role:** `rondo_user` is the base role set during provisioning. The capability sync should never revoke `rondo_user` — it can only manage the additional roles (`rondo_fairplay`, `rondo_vog`, `rondo_bestuur`). This preserves account access. Only the admin user-delete action removes `rondo_user`.

**Revised target roles (cleaner):**

```
syncable_roles = UserRoles::ROLES keys MINUS 'rondo_user'   // [rondo_fairplay, rondo_vog, rondo_bestuur]
mapped_roles = union(FunctieCapabilityMap::get_roles_for_functie(F) for F in functies)
               filtered to syncable_roles
```

### Pattern 3: Administrator Guard

**What:** Never modify users who have `administrator` role.

**Implementation:**

```php
if ( in_array( 'administrator', $user->roles, true ) ) {
    return [ 'skipped' => true, 'reason' => 'administrator' ];
}
```

Check at the START of `sync_user()` before any mutations.

### Pattern 4: REST Endpoint (Plan 01)

**Two endpoints to register in `register_routes()` of `class-rest-api.php`:**

1. **Per-user sync** (called by rondo-sync per member):
   ```
   POST /rondo/v1/capability-sync
   Body: { knvb_id: "12345", functies: ["Trainer", "Penningmeester"] }
   Auth: admin (check_admin_permission)
   Response: { user_id, granted, revoked, skipped, reason? }
   ```

2. **Sync all** (called by Settings on-demand button):
   ```
   POST /rondo/v1/capability-sync/all
   Body: { users: [ { knvb_id: "12345", functies: [...] } ] }
   Auth: admin
   Response: { total, synced, skipped, errors: [] }
   ```

   **Alternative for sync-all (simpler):** Re-derive functies from each person's ACF `work_history` field using `FunctieCapabilityMap`. This avoids sending a large payload from the Settings button. The Settings button triggers a server-side re-application of the current map against existing work_history. This is the cleaner approach for the on-demand case.

   **Recommended:** Use body-less `POST /rondo/v1/capability-sync/all` — the server derives functies from person work_history ACF for all provisioned users. The rondo-sync step uses the per-user endpoint with functies from its SQLite database.

### Pattern 5: rondo-sync Step (Plan 02)

**File:** `rondo-sync/steps/submit-capability-sync.js`

**Algorithm:**

```javascript
// 1. Get all tracked members with both knvb_id and rondo_club_id
const members = getActiveTrackedMembers(db);

// 2. Get all active functions grouped by knvb_id
const allFunctions = getAllActiveMemberFunctions(db);
// Build map: knvb_id => [function_description, ...]
const functiesByKnvb = groupBy(allFunctions, 'knvb_id');

// 3. For each member that has a known WP user (identified by KNVB ID),
//    call POST /rondo/v1/capability-sync with their functies
for (const member of members) {
  const functies = functiesByKnvb[member.knvb_id] || [];
  await rondoClubRequestWithRetry(
    'rondo/v1/capability-sync',
    'POST',
    { knvb_id: member.knvb_id, functies: functies.map(f => f.function_description) }
  );
}
```

**Important:** Members without a provisioned WP user will get a 404 or a `no_user` response from the endpoint — that's fine, log and skip (follows the established skip-and-warn pattern from Phase 154).

**Integration into `pipelines/sync-functions.js`:** Add as Step 5 after `freeFields` sync. Non-critical (failure does not stop pipeline). Include in summary output and error aggregation.

### Pattern 6: On-Demand UI (Plan 02)

**Location:** `src/pages/Settings/Settings.jsx` — `FunctiesTab` component.

The "Functies" subtab already shows the mapping. Add a "Sync nu uitvoeren" (Sync now) button below the save button that calls `POST /rondo/v1/capability-sync/all`. Show a loading state and result message (same pattern as `handleTriggerReminders`).

**State additions to Settings.jsx:**
```javascript
const [syncingCapabilities, setSyncingCapabilities] = useState(false);
const [capabilitySyncMessage, setCapabilitySyncMessage] = useState('');
```

**API client addition:**
```javascript
syncAllCapabilities: () => api.post('/rondo/v1/capability-sync/all'),
```

### Anti-Patterns to Avoid

- **Touching `administrator` users:** The administrator guard must be the first check. Never use `remove_role` or `add_role` on any user with `administrator` in their roles array.
- **Revoking `rondo_user`:** This is the base role that grants account access. Capability sync only manages the extra roles (`rondo_fairplay`, `rondo_vog`, `rondo_bestuur`).
- **Using capabilities directly instead of roles:** WordPress roles are sets of capabilities. We manage roles, not individual capability grants, to stay consistent with UserRoles class design.
- **Clearing manual grants on each sync:** If the admin explicitly grants a role and the Functie mapping doesn't cover it, the next sync must not revoke it. The `_rondo_cap_manual_grants` set is the safeguard.
- **sync-all endpoint accepting large JSON body from Settings:** The body-less server-side re-derive approach is cleaner. Settings doesn't have access to Sportlink functies — it should just trigger re-application of the current map against existing ACF work_history.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Role mutation | Custom meta-flag-based role system | `WP_User::add_role()` / `remove_role()` | WordPress handles capability inheritance correctly |
| User lookup by KNVB ID | Custom SQL | `get_users(['meta_key' => '_rondo_knvb_id', 'meta_value' => $knvb_id])` | WordPress meta API is indexed |
| Batching rondo-sync calls | Complex queue | Simple for-loop with retry | Volume is bounded (~300 members max) |

## Common Pitfalls

### Pitfall 1: WP_User::set_role() Wipes All Roles
**What goes wrong:** Calling `$user->set_role('rondo_fairplay')` removes ALL other roles and sets only fairplay. A user with both `rondo_user` and `rondo_fairplay` would lose `rondo_user`.
**Why it happens:** `set_role()` replaces the entire role set. `add_role()` appends.
**How to avoid:** Always use `add_role()` to grant and `remove_role()` to revoke. Never call `set_role()` in capability sync.
**Warning signs:** Users losing account access (can't log in) after sync.

### Pitfall 2: Multiple WP_User Instances from get_users()
**What goes wrong:** `get_users()` with `meta_key` filter returns all matching users. If KNVB ID is not unique on user meta (e.g., stale meta from deleted/recreated user), multiple results return.
**Why it happens:** Old user meta not cleaned up.
**How to avoid:** Use `get_users(['meta_key' => '_rondo_knvb_id', 'meta_value' => $knvb_id, 'number' => 1])` and take the first result. Log a warning if more than one user has the same KNVB ID.

### Pitfall 3: sync-all Works on Person Work History, not Sportlink Functions DB
**What goes wrong:** The on-demand "sync all" endpoint runs server-side on WordPress — it has no access to the rondo-sync SQLite database. It must derive functies from each person's ACF `work_history` field (the `job_title` values stored there are Sportlink function descriptions), not from the sync DB.
**Why it happens:** Confusion between what data lives where.
**How to avoid:** For the on-demand endpoint, iterate provisioned users, follow `rondo_linked_person_id` user meta to the person post, read `work_history` ACF field, extract `job_title` strings where `is_current === true`.
**Warning signs:** If the endpoint tries to read the SQLite database — that's wrong.

### Pitfall 4: Administrator Detection
**What goes wrong:** Checking `$user->has_cap('manage_options')` instead of checking roles. An admin could theoretically have `manage_options` granted to a non-administrator role.
**Why it happens:** Confusion between capabilities and roles.
**How to avoid:** Check `in_array('administrator', $user->roles, true)` OR check `user_can($user, 'manage_options')` — both work for the standard WP setup. The PRD requirement "administrator role" maps directly to `in_array('administrator', $user->roles)`.
**Confidence:** HIGH — standard WordPress pattern.

### Pitfall 5: rondo-sync Members Without Provisioned WP Users
**What goes wrong:** Most tracked members will NOT have a provisioned WP account. The endpoint returns a `no_user` response. If not handled, this floods error logs.
**Why it happens:** Provisioning is optional and admin-initiated (Phase 205).
**How to avoid:** The endpoint should return `{ status: 'no_user' }` with HTTP 200 (not 404) for members without a WP account. The rondo-sync step treats this as a skip, not an error.

### Pitfall 6: Race Between Manual Override and Sync
**What goes wrong:** Admin grants a role manually; within seconds rondo-sync runs and revokes it because the Functie map doesn't include it.
**Why it happens:** No tracking of manual grants.
**How to avoid:** Admin-granted roles are written to `_rondo_cap_manual_grants` user meta. This set is always preserved during automatic sync (CAPS-06 requirement).

## Code Examples

### Fetching provisioned users on the WordPress side

```php
// Source: WordPress get_users() docs + established pattern from class-user-provisioning.php
$provisioned_users = get_users( [
    'meta_key'    => \Rondo\Users\UserProvisioning::META_KNVB_ID,
    'meta_value'  => '',
    'meta_compare' => '!=',  // Has any value (i.e., has KNVB ID set)
    'fields'      => 'all',
    'number'      => -1,
] );
```

### Looking up user by KNVB ID

```php
// Source: WordPress get_users() + META_KNVB_ID constant
$users = get_users( [
    'meta_key'   => \Rondo\Users\UserProvisioning::META_KNVB_ID,
    'meta_value' => $knvb_id,
    'number'     => 1,
] );
if ( empty( $users ) ) {
    return [ 'status' => 'no_user' ];
}
$user = $users[0];
```

### Role reconciliation (WordPress)

```php
// Source: established in codebase (class-user-provisioning.php)
$user = new \WP_User( $user_id );

if ( in_array( 'administrator', $user->roles, true ) ) {
    return [ 'status' => 'skipped', 'reason' => 'administrator' ];
}

$syncable_roles = array_diff( \Rondo\Core\UserRoles::get_role_slugs(), [ 'rondo_user' ] );
$mapped_roles   = []; // derived from FunctieCapabilityMap
$manual_grants  = (array) json_decode( get_user_meta( $user_id, CapabilitySync::META_MANUAL_GRANTS, true ), true );
$manual_revokes = (array) json_decode( get_user_meta( $user_id, CapabilitySync::META_MANUAL_REVOKES, true ), true );

foreach ( $functies as $functie ) {
    foreach ( \Rondo\Config\FunctieCapabilityMap::get_roles_for_functie( $functie ) as $role ) {
        if ( in_array( $role, $syncable_roles, true ) ) {
            $mapped_roles[] = $role;
        }
    }
}
$mapped_roles = array_unique( $mapped_roles );

$target_roles   = array_diff( array_unique( array_merge( $mapped_roles, $manual_grants ) ), $manual_revokes );
$current_roles  = array_intersect( $user->roles, $syncable_roles );

$to_grant  = array_diff( $target_roles, $current_roles );
$to_revoke = array_diff( $current_roles, $target_roles );

foreach ( $to_grant as $role ) {
    $user->add_role( $role );
}
foreach ( $to_revoke as $role ) {
    if ( ! in_array( $role, $manual_grants, true ) ) {
        $user->remove_role( $role );
    }
}
```

### rondo-sync step skeleton (Node.js)

```javascript
// Follows the established step module+CLI hybrid pattern (CLAUDE.md)
const { rondoClubRequestWithRetry } = require('../lib/rondo-club-client');
const { openDb, getActiveTrackedMembers, getAllActiveMemberFunctions } = require('../lib/rondo-club-db');

async function runCapabilitySync(options = {}) {
  const { logger, verbose = false } = options;
  const result = { success: true, total: 0, synced: 0, skipped: 0, errors: [] };

  const db = openDb();
  try {
    const members = getActiveTrackedMembers(db);
    const allFunctions = getAllActiveMemberFunctions(db);

    // Build map: knvb_id => [function_description, ...]
    const functiesByKnvb = {};
    for (const f of allFunctions) {
      if (!functiesByKnvb[f.knvb_id]) functiesByKnvb[f.knvb_id] = [];
      functiesByKnvb[f.knvb_id].push(f.function_description);
    }

    result.total = members.length;

    for (const member of members) {
      const functies = functiesByKnvb[member.knvb_id] || [];
      try {
        const response = await rondoClubRequestWithRetry(
          'rondo/v1/capability-sync',
          'POST',
          { knvb_id: member.knvb_id, functies }
        );
        if (response.body.status === 'no_user') {
          result.skipped++;
        } else {
          result.synced++;
        }
      } catch (error) {
        result.errors.push({ knvb_id: member.knvb_id, message: error.message });
      }
    }

    result.success = result.errors.length === 0;
  } finally {
    db.close();
  }
  return result;
}

module.exports = { runCapabilitySync };
if (require.main === module) {
  const verbose = process.argv.includes('--verbose');
  runCapabilitySync({ verbose })
    .then(r => { if (!r.success) process.exitCode = 1; })
    .catch(err => { console.error(err.message); process.exitCode = 1; });
}
```

### Settings on-demand sync (PHP endpoint body-less approach)

```php
public function sync_all_capabilities( $request ) {
    $sync_service = new \Rondo\Users\CapabilitySync();

    // Get all provisioned users (have KNVB ID in user meta)
    $users = get_users( [
        'meta_key'     => \Rondo\Users\UserProvisioning::META_KNVB_ID,
        'meta_compare' => '!=',
        'meta_value'   => '',
        'number'       => -1,
    ] );

    $knvb_functies_map = [];
    foreach ( $users as $user ) {
        $knvb_id = get_user_meta( $user->ID, \Rondo\Users\UserProvisioning::META_KNVB_ID, true );
        if ( ! $knvb_id ) continue;

        // Derive functies from linked person's work_history ACF field
        $person_id = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
        $functies  = [];
        if ( $person_id ) {
            $work_history = get_field( 'work_history', $person_id );
            if ( is_array( $work_history ) ) {
                foreach ( $work_history as $job ) {
                    if ( ! empty( $job['job_title'] ) && ! empty( $job['is_current'] ) ) {
                        $functies[] = $job['job_title'];
                    }
                }
            }
        }
        $knvb_functies_map[ $knvb_id ] = $functies;
    }

    return rest_ensure_response( $sync_service->sync_all( $knvb_functies_map ) );
}
```

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| N/A — this is new | `WP_User::add_role()` / `remove_role()` for per-role management | Correct WordPress pattern |
| Append-only role assignment in UserProvisioning (Phase 205) | Full reconciliation: grant AND revoke in Phase 206 | Phase 206 adds revocation |

**What Phase 205 already does:** Assigns roles based on work_history Functies during initial provisioning — but append-only, no revocation. Phase 206 adds the reconciliation layer and the rondo-sync integration.

## Open Questions

1. **Should the per-user endpoint accept KNVB ID or WP user ID?**
   - What we know: rondo-sync knows KNVB IDs. The endpoint must accept KNVB ID and perform the user lookup server-side (single responsibility: rondo-sync doesn't know WP user IDs).
   - Recommendation: Accept `knvb_id` and do the user lookup in PHP. This is consistent with how rondo-sync identifies members everywhere.
   - Confidence: HIGH

2. **Should `rondo_user` be in the syncable roles set?**
   - What we know: `rondo_user` is the base role granting account access. Provisioning sets it. There's no Functie mapping to `rondo_user` — all Functies map to the extra roles.
   - Recommendation: Exclude `rondo_user` from the syncable set (never grant or revoke via capability sync). Base role is managed by provisioning/deletion only.
   - Confidence: HIGH

3. **Where do manual capability overrides get set?**
   - Phase 205's AccountCard UI exists. Phase 206 could add per-role toggle controls to the AccountCard that write to `_rondo_cap_manual_grants`/`_rondo_cap_manual_revokes`. But the requirements only say "Admin can manually override" (CAPS-05) and that overrides survive sync (CAPS-06). It doesn't specify the UI for setting overrides.
   - Recommendation: For Plan 01, implement the data model and sync logic. Plan 02 can expose a simple override UI in AccountCard (a set of role checkboxes with a "Pin (override)" toggle). If the planner wants to keep 206-02 scoped to rondo-sync only, defer the override UI to Phase 207.
   - What's unclear: Whether the planner wants override UI in this phase or a follow-up.

4. **Does the sync-functions pipeline integration create a performance concern?**
   - What we know: `getActiveTrackedMembers` returns ~300 members. Each sync call is lightweight (no Playwright, no file I/O). At 300 sequential HTTP calls with retry: ~5-10 seconds worst case.
   - Recommendation: No batching needed. Sequential is fine for this volume.
   - Confidence: HIGH

## Sources

### Primary (HIGH confidence)

- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-user-roles.php` — role slug definitions, `ROLES` constant, `add_role`/`remove_role` usage
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-functie-capability-map.php` — Phase 204 deliverable, `get_roles_for_functie()` API
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-user-provisioning.php` — Phase 205 deliverable, META_KNVB_ID, provisioning pattern
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` — Route registration patterns, `check_admin_permission`, existing Phase 205 endpoints
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-base.php` — `check_admin_permission` = `current_user_can('manage_options')`
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/Settings.jsx` — Admin subtab patterns, `handleTriggerReminders` pattern for on-demand actions
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` — API client method patterns
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-sync/lib/rondo-club-db.js` — `getActiveTrackedMembers`, `getAllActiveMemberFunctions` signatures
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-sync/lib/rondo-club-client.js` — `rondoClubRequestWithRetry` pattern
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-sync/pipelines/sync-functions.js` — Pipeline step integration pattern, RunTracker usage
- Codebase: `/Users/joostdevalk/Code/rondo/rondo-sync/CLAUDE.md` — Module+CLI hybrid pattern, logging conventions

### Secondary (MEDIUM confidence)

- WordPress documentation (training data + verification via codebase usage): `WP_User::add_role()`, `remove_role()`, `get_users()` with meta query

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components verified in existing codebase
- Architecture: HIGH — follows established patterns from Phase 205 and sync-functions pipeline
- Pitfalls: HIGH — derived from reading actual code, not hypothetical

**Research date:** 2026-02-20
**Valid until:** 2026-03-20 (stable codebase, no fast-moving dependencies)

## Key Integration Points Summary

For the planner — this phase touches exactly these files:

**rondo-club (new files):**
- `includes/class-capability-sync.php` — new pure service class (`Rondo\Users\CapabilitySync`)

**rondo-club (modified files):**
- `includes/class-rest-api.php` — register 2 routes + 2 callback methods
- `src/api/client.js` — add `syncAllCapabilities` method
- `src/pages/Settings/Settings.jsx` — add sync-all button to `FunctiesTab` (+ optional manual override UI in AccountCard)
- `style.css` + `package.json` — version bump (MINOR: new feature)
- `/CHANGELOG.md` — changelog entry

**rondo-sync (new files):**
- `steps/submit-capability-sync.js` — new step

**rondo-sync (modified files):**
- `pipelines/sync-functions.js` — add Step 5 capability sync
- `pipelines/sync-all.js` — add capability sync stats tracking (matches functions pipeline pattern)
