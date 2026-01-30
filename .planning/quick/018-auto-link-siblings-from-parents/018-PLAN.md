---
phase: quick-018
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-inverse-relationships.php
autonomous: true

must_haves:
  truths:
    - "When a parent-child relationship is created, siblings are auto-linked"
    - "Existing children of a parent gain sibling relationships with new children"
    - "Sibling relationships are symmetric (both sides get linked)"
    - "Removing a parent-child relationship removes the sibling link to that person"
  artifacts:
    - path: "includes/class-inverse-relationships.php"
      provides: "Sibling auto-linking logic"
      contains: "sync_siblings_from_parent"
  key_links:
    - from: "sync_single_inverse_relationship"
      to: "sync_siblings_from_parent"
      via: "Called after parent-child inverse created"
      pattern: "sync_siblings_from_parent"
---

<objective>
Auto-link siblings when parent-child relationships exist.

Purpose: When users create parent-child relationships, the system should automatically create sibling relationships between all children of the same parent, reducing manual data entry.

Output: Enhanced InverseRelationships class that auto-links siblings when parent-child relationships are created/deleted.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-inverse-relationships.php

Key relationship type IDs (from production):
- Parent: 8 (inverse: Child 9)
- Child: 9 (inverse: Parent 8)
- Sibling: 10 (symmetric)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add sibling auto-linking to InverseRelationships</name>
  <files>includes/class-inverse-relationships.php</files>
  <action>
Add a new method `sync_siblings_from_parent()` to the InverseRelationships class that:

1. Is called from `sync_single_inverse_relationship()` AFTER successfully creating a parent-child or child-parent inverse relationship
2. When a Child relationship (type 9) is created on person A pointing to parent B:
   - Find all OTHER people who have Child relationships pointing to the SAME parent B
   - For each such person C (where C != A), create symmetric sibling relationships:
     - A gets Sibling (type 10) relationship to C
     - C gets Sibling (type 10) relationship to A
   - Skip if sibling relationship already exists
3. When a Parent relationship (type 8) is created on parent B pointing to child A:
   - This is the inverse case - find all other children of B and link them as siblings to A

Implementation details:
- Add helper method `get_children_of_parent($parent_id)` that queries all people with relationship_type=9 (Child) pointing to the given parent
- In `sync_siblings_from_parent($child_id, $parent_id)`:
  - Get all children of parent (excluding the current child)
  - For each other child, call a sibling-specific sync that handles the symmetric nature
- Add `sync_sibling_relationship($person_a, $person_b)` that creates bilateral sibling links
- Use the existing `$this->processing` array to prevent loops
- Sibling type ID should be fetched dynamically: `get_term_by('slug', 'sibling', 'relationship_type')->term_id`

Also add `remove_siblings_on_parent_removal()`:
- When a parent-child relationship is REMOVED, check if person A still has the same parent via another route (e.g., both mother and father)
- If A no longer shares any parent with person C, remove the sibling relationship between A and C
- This is complex - for v1, only remove sibling links if A has NO remaining parent relationships
  </action>
  <verify>
Test scenario on production:
1. Find or create a person "Parent P"
2. Create person "Child A" with Parent relationship to P
3. Create person "Child B" with Parent relationship to P
4. Verify: Child A should automatically get Sibling relationship to Child B
5. Verify: Child B should automatically get Sibling relationship to Child A
6. Remove Parent relationship from Child B to P
7. Verify: Sibling relationship between A and B should be removed (if B has no other parent relationships)
  </verify>
  <done>
- Parent-child relationships auto-create sibling links between all children of the same parent
- Sibling relationships are symmetric (appear on both people)
- Removing parent-child does not break existing siblings if other parent links exist
  </done>
</task>

<task type="auto">
  <name>Task 2: Add WP-CLI command for backfilling existing relationships</name>
  <files>includes/class-wp-cli.php</files>
  <action>
Add a new WP-CLI command `wp prm relationships sync-siblings` that:

1. Queries all parent-child relationships in the database
2. Groups children by parent
3. For each parent with multiple children, creates sibling relationships between all children
4. Reports progress and results

Command structure:
```php
/**
 * Sync sibling relationships based on existing parent-child data
 *
 * ## OPTIONS
 *
 * [--dry-run]
 * : Show what would be created without making changes
 *
 * ## EXAMPLES
 *
 *     wp prm relationships sync-siblings
 *     wp prm relationships sync-siblings --dry-run
 */
public function sync_siblings( $args, $assoc_args ) {
    // Implementation
}
```

Logic:
1. Get all people with relationships
2. Build a map: parent_id => [child_ids]
3. For each parent with >1 child:
   - For each pair of children (A, B):
     - Check if sibling relationship exists A->B
     - If not, create it (unless --dry-run)
     - Check if sibling relationship exists B->A
     - If not, create it (unless --dry-run)
4. Report: "Created X sibling relationships for Y families"
  </action>
  <verify>
Run on production:
```bash
wp prm relationships sync-siblings --dry-run
```
Verify it reports sensible numbers. Then run without --dry-run to backfill.
  </verify>
  <done>
- WP-CLI command exists and works
- Dry-run mode shows what would be created
- Command successfully backfills sibling relationships for existing data
  </done>
</task>

</tasks>

<verification>
1. Create test family: Parent with 2+ children
2. Verify siblings auto-linked when children added
3. Run `wp prm relationships sync-siblings --dry-run` to verify CLI works
4. Check that removing parent-child handles sibling cleanup appropriately
</verification>

<success_criteria>
- New parent-child relationships trigger sibling auto-linking
- WP-CLI command can backfill existing data
- No infinite loops or performance issues
- Deployed to production and tested
</success_criteria>

<output>
After completion, create `.planning/quick/018-auto-link-siblings-from-parents/018-SUMMARY.md`
</output>
