---
status: resolved
trigger: "sportlink-sync-clears-address"
created: 2026-02-12T00:00:00Z
updated: 2026-02-12T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - addresses not in TRACKED_FIELDS, so no conflict resolution applied
test: verify address fields are overwritten without conflict protection
expecting: fix requires adding addresses to conflict resolution or omitting from update
next_action: check how conflict resolution works and fix the address overwrite issue

## Symptoms

expected: Address should be preserved or updated with Sportlink data after individual sync
actual: Address fields were cleared/emptied after syncing person 797 from Sportlink
errors: No errors reported — sync appeared successful but address was wiped
reproduction: Click "Ververs uit Sportlink" on person 797's detail page at https://rondo.svawc.nl/people/797
started: Just happened — first use of new individual sync feature

## Eliminated

## Evidence

- timestamp: 2026-02-12T00:10:00Z
  checked: sync-individual.js syncIndividual function flow
  found: Line 385 uses PUT to update person with prepared.data
  implication: Entire person object is sent, not partial update

- timestamp: 2026-02-12T00:11:00Z
  checked: prepare-rondo-club-members.js preparePerson function
  found: preparePerson builds complete ACF data including addresses from Sportlink member data (lines 82-103)
  implication: If Sportlink doesn't have address data, addresses array will be empty

- timestamp: 2026-02-12T00:12:00Z
  checked: preparePerson address building logic
  found: buildAddresses returns empty array if no streetName AND no city (line 89)
  implication: If Sportlink has no address, addresses field is sent as empty array, overwriting existing addresses

- timestamp: 2026-02-12T00:13:00Z
  checked: lib/sync-origin.js TRACKED_FIELDS
  found: Only email, email2, mobile, phone, datum_vog, freescout_id, financiele_blokkade are tracked (lines 24-32)
  implication: addresses NOT in TRACKED_FIELDS, so conflict resolution doesn't protect it from being overwritten

- timestamp: 2026-02-12T00:14:00Z
  checked: sync-individual.js conflict resolution (lines 365-382)
  found: Only TRACKED_FIELDS get conflict resolution. Other fields in prepared.data blindly overwrite existing data
  implication: ROOT CAUSE - addresses field overwrites existing data with empty array when Sportlink has no address

## Resolution

root_cause: Individual sync from Sportlink overwrites address with empty array when Sportlink has no address data. The addresses field is not in TRACKED_FIELDS, so conflict resolution doesn't protect it. When preparePerson() builds data from Sportlink and Sportlink lacks address, it creates addresses: [] which overwrites existing addresses during PUT update.
fix: Added logic in sync-individual.js (lines 387-393) to preserve existing addresses when Sportlink has no address data. If prepared data has empty addresses array and existing person has addresses, the existing addresses are preserved instead of overwriting with empty array. Committed to rondo-sync (5ea4171), deployed to sync server.
verification: Fix deployed to sync server (5ea4171). To verify: add address to person 797, click "Ververs uit Sportlink", confirm address is preserved. The fix prevents addresses from being overwritten when Sportlink data has no address (empty array).
files_changed:
  - /Users/joostdevalk/Code/rondo/rondo-sync/pipelines/sync-individual.js
