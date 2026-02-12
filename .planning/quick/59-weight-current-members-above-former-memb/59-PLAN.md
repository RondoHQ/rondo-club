---
phase: quick-59
plan: 01
type: execute
wave: 1
depends_on: []
files_modified: [includes/class-rest-api.php]
autonomous: true

must_haves:
  truths:
    - "Current members appear above former members with same match quality"
    - "Search ranking is: match quality first, membership status second"
    - "Former members still appear in results (not filtered out)"
  artifacts:
    - path: "includes/class-rest-api.php"
      provides: "global_search() method with former member penalty"
      min_lines: 150
      contains: "former_member"
  key_links:
    - from: "includes/class-rest-api.php"
      to: "former_member ACF field"
      via: "get_field() check after scoring"
      pattern: "get_field.*former_member"
---

<objective>
Weight current members above former members in global search results.

Purpose: Prevent former members from crowding out active members in search results
Output: Modified search scoring that prioritizes current members over former members
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@includes/class-rest-api.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Apply former member penalty to search scores</name>
  <files>includes/class-rest-api.php</files>
  <action>
In the `global_search()` method around line 1806 (after all person queries are complete, before the uasort call):

1. Loop through `$people_results` array
2. For each person, check `get_field('former_member', $person_id)`
3. If former_member === '1' (string comparison, ACF stores as string), subtract 50 points from their score
4. This ensures current members with score 60 (first name contains) rank above former members with score 100 (first name exact)

Example logic:
```php
// Apply former member penalty (after line 1804, before line 1806)
foreach ( $people_results as $person_id => &$item ) {
    $is_former = get_field( 'former_member', $person_id );
    if ( $is_former === '1' ) {
        $item['score'] -= 50;
    }
}
unset( $item ); // Break reference
```

This preserves the existing match quality scoring while ensuring membership status is the tiebreaker.
  </action>
  <verify>
1. Search for a former member's first name (exact match = score 100 - 50 = 50)
2. Verify they appear BELOW current members with partial matches (score 60+)
3. Search for a unique former member name and verify they still appear (not filtered out)
4. Test that current members' ranking is unchanged
  </verify>
  <done>
- Former members receive -50 score penalty
- Current members with partial matches rank above former members with exact matches
- Former members still appear in results when relevant
- No PHP errors or warnings
  </done>
</task>

</tasks>

<verification>
**Manual testing:**
1. Identify a former member in the system (former_member = '1')
2. Search for their exact first name
3. Verify they appear below current members with similar names
4. Search for a unique former member name
5. Verify they still appear in results (penalty doesn't eliminate them)
6. Search for various current members
7. Verify their ranking is unchanged

**Code review:**
- Former member check uses correct ACF field key
- String comparison ('1' not 1) matches ACF storage format
- Penalty is applied before sorting
- Reference is properly unset after foreach loop
</verification>

<success_criteria>
- [ ] Current members consistently rank above former members with same match type
- [ ] Former members still appear when their names match uniquely
- [ ] No PHP errors in error log after search requests
- [ ] Search performance remains fast (no N+1 query issues)
- [ ] Code deployed to production
</success_criteria>

<output>
After completion, create `.planning/quick/59-weight-current-members-above-former-memb/59-SUMMARY.md`
</output>
