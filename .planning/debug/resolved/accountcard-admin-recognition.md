---
status: resolved
trigger: "accountcard-not-recognizing-admin"
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - update_linked_person only wrote user meta, not post meta
test: Read PHP source for update_linked_person and prepare_item_for_response
expecting: Missing update_post_meta call in update_linked_person
next_action: DONE - fixed and deployed

## Symptoms

expected: AccountCard on /people/757 should show the current logged-in user (Joost, WordPress admin) as connected to this person
actual: AccountCard didn't recognize the connection — showed "no account linked"
errors: No errors, just incorrect state display
reproduction: Log in as WordPress administrator who has a linked person record, go to that person's detail page, observe AccountCard
started: Since AccountCard was built in v30.0 Phase 205

## Eliminated

- hypothesis: Role-based filtering excluded administrators from being returned
  evidence: PHP code doesn't filter by role for linked_user_id lookup
  timestamp: 2026-02-22

## Evidence

- timestamp: 2026-02-22
  checked: AccountCard.jsx
  found: Card reads personData.linked_user_id — if null, shows "not provisioned" branch
  implication: Bug is in PHP data source, not React

- timestamp: 2026-02-22
  checked: class-rest-people.php prepare_item_for_response
  found: linked_user_id = (int) get_post_meta($post->ID, '_rondo_wp_user_id', true) ?: null
  implication: Only reads post meta side of the link

- timestamp: 2026-02-22
  checked: class-rest-api.php update_linked_person
  found: Only calls update_user_meta($user_id, 'rondo_linked_person_id', $person_id)
  implication: Never writes _rondo_wp_user_id on the person post — asymmetric link

- timestamp: 2026-02-22
  checked: class-user-provisioning.php provision()
  found: Writes BOTH update_user_meta + update_post_meta (PROV-03)
  implication: Provisioned users work fine; manually-linked users (admins) don't

## Resolution

root_cause: update_linked_person in class-rest-api.php only wrote the user meta side
  (rondo_linked_person_id on the WP user) but not the post meta side
  (_rondo_wp_user_id on the person post). AccountCard reads only the post meta side,
  so administrators who linked themselves via Settings appeared unlinked.

fix: Two-part fix:
  1. update_linked_person now writes both sides bidirectionally (mirrors PROV-03).
     Unlinking also clears both sides.
  2. prepare_item_for_response falls back to a reverse lookup via
     rondo_linked_person_id user meta when _rondo_wp_user_id is empty.
     Backfills the post meta on first hit for backward compatibility.

verification: Build passes, lint clean, deployed to production. On first page load
  of person 757, the fallback query will find Joost's user via rondo_linked_person_id,
  backfill the post meta, and AccountCard will show "Account aangemaakt" with
  administrator role badge.

files_changed:
  - includes/class-rest-api.php
  - includes/class-rest-people.php
