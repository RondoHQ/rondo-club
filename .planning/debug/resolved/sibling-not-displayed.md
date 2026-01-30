---
status: resolved
trigger: "Siblings are not displayed in the Relationships area even when two people share the same parents."
created: 2026-01-30T10:00:00Z
updated: 2026-01-30T10:25:00Z
---

## Current Focus

hypothesis: CONFIRMED - Bug in get_children_of_parent() using wrong relationship type ID
test: Verified data model shows children have type 8 (Parent) pointing to parent, not type 9 (Child)
expecting: Fix by changing type check from 9 to 8 in get_children_of_parent()
next_action: Apply fix to class-inverse-relationships.php

## Symptoms

expected: Person 4635 should show person 4428 as a sibling in the Relationships area, since they share the same parents
actual: No sibling is displayed on person 4635's page
errors: None reported
reproduction: View https://stadion.svawc.nl/people/4635 - sibling (person 4428) does not appear despite sharing parents
started: Never worked - feature may be missing or broken

## Eliminated

## Evidence

- timestamp: 2026-01-30T10:05:00Z
  checked: Person 4635 relationships in database
  found: Has type 8 (Parent) relationships to persons 5098 and 3948
  implication: Person 4635 is a child with parents 5098 and 3948

- timestamp: 2026-01-30T10:06:00Z
  checked: Person 4428 relationships in database
  found: Has identical type 8 (Parent) relationships to persons 5098 and 3948
  implication: Person 4428 is also a child of same parents - should be a sibling of 4635

- timestamp: 2026-01-30T10:07:00Z
  checked: Person 5098 (parent) relationships in database
  found: Has type 9 (Child) relationships to 4428 and 4635
  implication: Data model confirmed - type 8 means "related person is my parent", type 9 means "related person is my child"

- timestamp: 2026-01-30T10:08:00Z
  checked: get_children_of_parent() in class-inverse-relationships.php line 759
  found: Code checks for relationship_type_id == 9, but should check for 8
  implication: ROOT CAUSE FOUND - method looks for wrong relationship type

## Resolution

root_cause: Multiple functions had inverted type 8/9 semantics. Type 8 (Parent) means "related person IS my parent" (so I am the child). Type 9 (Child) means "related person IS my child" (so I am the parent). The code was using these backwards in 4 places.
fix: Fixed type checks in:
1. get_children_of_parent() - changed from type 9 to type 8 (line 759)
2. sync_single_inverse_relationship() - swapped type 8/9 sibling sync logic (lines 369-380)
3. sync_inverse_relationships() sibling cleanup - swapped type 8/9 logic (lines 166-172)
4. remove_siblings_on_parent_removal() - changed from type 9 to type 8 (line 889-890)
5. WP-CLI sync-siblings command - changed from type 9 to type 8 (line 2651-2652)
verification: |
  1. Deployed fix to production
  2. Ran `wp prm relationships sync_siblings` - created 156 sibling relationships
  3. Verified person 4635 now has sibling relationship to 4428 (type 10)
  4. Verified person 4428 now has sibling relationship to 4635 (type 10)
  5. Cleared all caches
files_changed:
- includes/class-inverse-relationships.php
- includes/class-wp-cli.php
