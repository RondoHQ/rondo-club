---
status: resolved
trigger: "resync-role-mapping-not-applied"
created: 2026-02-21T00:00:00Z
updated: 2026-02-21T01:30:00Z
---

## Current Focus

hypothesis: RESOLVED
test: Fixed and verified on production
expecting: N/A
next_action: Archive

## Symptoms

expected: After configuring functie-to-role mapping and hitting resync, person 285 should receive rondo_user and rondo_fairplay WordPress roles
actual: Roles don't seem to be applied after resync
errors: None visible — silent failure
reproduction: Configure functie mapping in FunctiesTab, hit resync on a person who has that functie
started: Just now, first time testing this flow with these specific mappings

## Eliminated

- hypothesis: FunctieCapabilityMap not saved correctly
  evidence: Production wp_options has 'FairPlay commissie' => ['rondo_user' => true, 'rondo_fairplay' => true] — correctly saved
  timestamp: 2026-02-21T00:30:00Z

- hypothesis: Person 285 doesn't have the correct job_title in work_history
  evidence: work_history on person 285 has job_title "FairPlay commissie" with is_current = true
  timestamp: 2026-02-21T00:30:00Z

- hypothesis: No WordPress user is linked to person 285
  evidence: User 8 (harrie.jansen@svawc.nl) has rondo_linked_person_id = 285. Person 285 has _rondo_wp_user_id = 8.
  timestamp: 2026-02-21T00:35:00Z

## Evidence

- timestamp: 2026-02-21T00:20:00Z
  checked: class-capability-sync.php sync_all() method
  found: sync_all() queries users WHERE _rondo_knvb_id != '' (META_KNVB_ID). User 8 has no _rondo_knvb_id meta → silently skipped.
  implication: All provisioned users without _rondo_knvb_id are invisible to sync_all()

- timestamp: 2026-02-21T00:25:00Z
  checked: Production option rondo_functie_capability_map
  found: {'FairPlay commissie' => {'rondo_user' => true, 'rondo_fairplay' => true}} — correctly configured
  implication: Mapping is correct; the sync step is the problem

- timestamp: 2026-02-21T00:30:00Z
  checked: Person 285 work_history ACF field on production
  found: 4 entries including job_title "FairPlay commissie" with is_current = true
  implication: The data is there; sync is just not processing user 8

- timestamp: 2026-02-21T00:35:00Z
  checked: User 8 meta on production
  found: User 8 has rondo_linked_person_id = 285 but NO _rondo_knvb_id meta. Current role: rondo_vog only.
  implication: User was provisioned before KNVB ID storage feature existed. sync_all() skips them.

- timestamp: 2026-02-21T00:40:00Z
  checked: rondo-sync pipelines/sync-individual.js and lib/web-server.js
  found: POST /api/sync/individual calls syncIndividual() which syncs person data + commissie work history but does NOT call /rondo/v1/capability-sync
  implication: "Ververs uit Sportlink" button also doesn't trigger capability sync

- timestamp: 2026-02-21T00:45:00Z
  checked: class-rest-api.php sync_individual_from_sportlink()
  found: Proxy endpoint forwards to rondo-sync and returns result. No capability sync step after success.
  implication: Both sync paths fail — sync_all (KNVB ID filter) AND individual sync (no cap sync step)

- timestamp: 2026-02-21T01:00:00Z
  checked: Fix deployed and tested on production
  found: sync_user_by_person_id(285) returns {granted: [rondo_fairplay], revoked: [rondo_vog]}
  implication: Fix works correctly. rondo_vog revoked (no mapping), rondo_fairplay granted (FairPlay commissie mapping).

- timestamp: 2026-02-21T01:10:00Z
  checked: sync_all() after fix
  found: Now processes 8 users (was 7), user 8 included, status synced
  implication: Fix is comprehensive — all provisioned users regardless of KNVB ID now synced

## Resolution

root_cause: |
  Two bugs caused the role mapping to silently fail:
  1. CapabilitySync::sync_all() queried by _rondo_knvb_id (UserProvisioning::META_KNVB_ID),
     excluding users provisioned before PROV-04 (KNVB ID storage). User 8 (linked to person 285)
     has no _rondo_knvb_id, so sync_all() never processed them.
  2. The "Ververs uit Sportlink" individual sync flow (via rondo-sync proxy) did NOT trigger
     capability sync at all — it only synced person data and commissie work history.

fix: |
  1. Fixed sync_all() in class-capability-sync.php to query by rondo_linked_person_id instead
     of _rondo_knvb_id — the true "provisioned" indicator, always set for provisioned users.
  2. Added new sync_user_by_person_id() method to CapabilitySync for person-based lookup.
  3. Added capability sync step in sync_individual_from_sportlink() (class-rest-api.php) after
     rondo-sync call succeeds — calls sync_user_by_person_id() via find_person_id_by_knvb_id().
  4. Added new REST endpoint POST /rondo/v1/people/{id}/capability-sync for per-person sync.
  5. Added "Sync rollen" button to AccountCard.jsx with inline feedback and query invalidation.
  6. Added syncPersonCapabilities() to api/client.js.

verification: |
  - sync_all() now finds 8 users (up from 7), user 8 included
  - sync_user_by_person_id(285) correctly granted rondo_fairplay, revoked rondo_vog
  - sync_all() is idempotent — second run shows granted: [], revoked: []
  - Build and lint pass clean

files_changed:
  - includes/class-capability-sync.php
  - includes/class-rest-api.php
  - src/api/client.js
  - src/components/AccountCard.jsx
