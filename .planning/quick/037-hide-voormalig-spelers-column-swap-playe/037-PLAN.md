---
phase: quick
plan: 037
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Teams/TeamDetail.jsx
  - includes/class-rest-teams.php
autonomous: true

must_haves:
  truths:
    - "Team detail page shows Staff column first, then Players column"
    - "Voormalig spelers column is hidden"
    - "Custom fields section appears in the third column slot"
    - "Players and staff load faster due to optimized database query"
  artifacts:
    - path: "src/pages/Teams/TeamDetail.jsx"
      provides: "Updated column layout with Staff first, Players second, Custom Fields third"
    - path: "includes/class-rest-teams.php"
      provides: "Optimized get_people_by_company using meta_query"
  key_links:
    - from: "TeamDetail.jsx"
      to: "/stadion/v1/teams/{id}/people"
      via: "prmApi.getTeamPeople"
---

<objective>
Improve team detail page UX by reorganizing columns and improving load performance.

Purpose: Better UX with Staff first (more important), hide former players (not needed), show custom fields inline, and faster loading.

Output: Updated TeamDetail.jsx with new column order and optimized REST endpoint.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/Teams/TeamDetail.jsx
@includes/class-rest-teams.php
@includes/class-rest-base.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Optimize get_people_by_company performance</name>
  <files>includes/class-rest-teams.php</files>
  <action>
Replace the inefficient get_people_by_company method that fetches ALL people and filters in PHP.

Current approach (slow):
- Fetches all people posts
- Loops through each to check work_history ACF repeater
- O(n) where n = total people count

New approach (fast):
- Use WP_Query with meta_query to find people whose work_history contains the team ID
- ACF stores repeater fields as serialized data with pattern `work_history_X_team` for the team ID
- Use meta_key LIKE 'work_history_%_team' AND meta_value = team_id
- This lets the database do the filtering instead of PHP

Implementation:
```php
// Replace the get_posts call with a meta_query approach
$people = get_posts([
    'post_type'      => 'person',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => 'work_history_%_team',
            'value'   => $team_id,
            'compare' => '=',
        ],
    ],
]);
```

Note: ACF repeater sub-fields are stored with pattern `{repeater}_{index}_{subfield}` so work_history_0_team, work_history_1_team, etc. The % wildcard matches any index.

The rest of the method (filtering current vs former based on dates) remains the same, but now only processes matching people instead of all people.
  </action>
  <verify>
Test endpoint `/stadion/v1/teams/{team_id}/people` returns same data but faster.
Check Network tab load time before and after.
  </verify>
  <done>
Endpoint returns current and former members with improved query performance.
  </done>
</task>

<task type="auto">
  <name>Task 2: Reorganize team detail columns</name>
  <files>src/pages/Teams/TeamDetail.jsx</files>
  <action>
Modify the Members section in TeamDetail.jsx (around lines 327-451):

1. **Swap column order**: Show Staf first, then Spelers
2. **Remove Voormalig spelers column entirely**: Delete the third column with former players
3. **Move CustomFieldsSection into the grid**: Replace the removed "Voormalig spelers" column with the CustomFieldsSection component

Current layout:
```
[Spelers] [Staf] [Voormalig spelers]
[CustomFieldsSection - full width below]
```

New layout:
```
[Staf] [Spelers] [CustomFieldsSection]
```

Changes needed:
- In the grid (line 346), keep `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`
- Move "Staf" column (lines 381-413) BEFORE "Spelers" column (lines 347-379)
- Remove "Voormalig spelers" column entirely (lines 415-448)
- Move the CustomFieldsSection component (lines 566-576) INTO the grid as the third column
- Wrap CustomFieldsSection in a `<div className="card p-6">` to match other columns
- Remove the variable `formerPlayers` since it's no longer needed
- Update `hasAnyMembers` check to only check `players.length > 0 || staff.length > 0`

Keep the condition that shows the section only if there are any members (players or staff).
  </action>
  <verify>
Run `npm run build` successfully.
Visit team detail page in browser.
Verify: Staff column appears first, Players second, Custom Fields third.
Verify: No "Voormalig spelers" column visible.
  </verify>
  <done>
Team detail page shows three-column layout: Staf, Spelers, Custom Fields.
Former players section is removed.
  </done>
</task>

</tasks>

<verification>
- [ ] `npm run build` completes without errors
- [ ] Team detail page loads faster (check Network tab for /teams/{id}/people endpoint)
- [ ] Staff column appears first (left)
- [ ] Players column appears second (middle)
- [ ] Custom fields appear in third column (right)
- [ ] No "Voormalig spelers" column visible
- [ ] Responsive layout works (1 col mobile, 2 col tablet, 3 col desktop)
</verification>

<success_criteria>
Team detail page shows Staff, Players, Custom Fields in three columns.
Former players section is removed.
API endpoint loads team members faster.
</success_criteria>

<output>
After completion, create `.planning/quick/037-hide-voormalig-spelers-column-swap-playe/037-SUMMARY.md`
</output>
