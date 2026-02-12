---
phase: 61-add-vrijwilliger-badge-to-volunteers-sim
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-base.php
  - src/pages/People/PersonDetail.jsx
  - dist/
autonomous: true

must_haves:
  truths:
    - "Volunteers see 'Vrijwilliger' badge on PersonDetail page"
    - "Vrijwilliger badge uses distinct color (electric-cyan) from gray Oud-lid badge"
    - "REST API includes huidig_vrijwilliger field in person summary data"
  artifacts:
    - path: "includes/class-rest-base.php"
      provides: "huidig_vrijwilliger in format_person_summary()"
      contains: "huidig_vrijwilliger"
      min_lines: 1
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Vrijwilliger badge rendering"
      contains: "Vrijwilliger"
      min_lines: 5
    - path: "dist/"
      provides: "Production build assets"
  key_links:
    - from: "src/pages/People/PersonDetail.jsx"
      to: "acf.huidig_vrijwilliger"
      via: "Badge conditional rendering"
      pattern: "acf\\.huidig_vrijwilliger"
    - from: "includes/class-rest-base.php"
      to: "get_field('huidig-vrijwilliger')"
      via: "REST API person summary"
      pattern: "huidig[_-]vrijwilliger"
---

<objective>
Add a "Vrijwilliger" badge to volunteer profiles on the PersonDetail page, matching the existing "Oud-lid" badge pattern.

Purpose: Visual indication that a person is currently a volunteer (huidig-vrijwilliger field already calculated by includes/class-volunteer-status.php)
Output: Vrijwilliger badge rendered in electric-cyan brand color next to person name on PersonDetail
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md

# Existing implementation to replicate
@includes/class-rest-base.php      # Add huidig_vrijwilliger to format_person_summary()
@includes/class-volunteer-status.php  # Already calculates huidig-vrijwilliger field
@src/pages/People/PersonDetail.jsx  # Add badge next to Oud-lid badge
</context>

<tasks>

<task type="auto">
  <name>Add huidig_vrijwilliger to REST API person summary</name>
  <files>includes/class-rest-base.php</files>
  <action>
In `format_person_summary()` method (~line 207), add `huidig_vrijwilliger` field after `former_member`:

```php
'former_member'       => ( get_field( 'former_member', $post->ID ) == true ),
'huidig_vrijwilliger' => ( get_field( 'huidig-vrijwilliger', $post->ID ) == true ),
```

Note: The ACF field key uses hyphens (`huidig-vrijwilliger`) but the REST API key uses underscores (`huidig_vrijwilliger`) to match JavaScript naming conventions.

This makes the volunteer status available in list views and search results, consistent with the former_member pattern.
  </action>
  <verify>
```bash
grep -A1 "former_member" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-base.php | grep "huidig_vrijwilliger"
```
  </verify>
  <done>REST API returns huidig_vrijwilliger field in person summary responses</done>
</task>

<task type="auto">
  <name>Add Vrijwilliger badge to PersonDetail</name>
  <files>src/pages/People/PersonDetail.jsx</files>
  <action>
In PersonDetail.jsx, add the Vrijwilliger badge immediately after the Oud-lid badge (after line 1006):

```jsx
{acf.former_member && (
  <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">
    Oud-lid
  </span>
)}
{acf.huidig_vrijwilliger && (
  <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-electric-cyan/10 text-electric-cyan dark:bg-electric-cyan/20 dark:text-electric-cyan-light">
    Vrijwilliger
  </span>
)}
```

Use electric-cyan brand color (bg-electric-cyan/10 with text-electric-cyan) to distinguish from the gray Oud-lid badge. This creates visual hierarchy: gray for former status, brand color for active volunteer status.

The `acf.huidig_vrijwilliger` field is already populated by the REST API (previous task) and calculated server-side by class-volunteer-status.php.
  </action>
  <verify>
```bash
grep -A3 "huidig_vrijwilliger" /Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PersonDetail.jsx
```
  </verify>
  <done>Vrijwilliger badge renders next to person name when huidig_vrijwilliger is true</done>
</task>

<task type="auto">
  <name>Build production assets</name>
  <files>dist/</files>
  <action>
Run production build to compile updated React components into dist/ folder:

```bash
cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build
```

This updates the production JavaScript bundle with the new Vrijwilliger badge rendering logic.
  </action>
  <verify>
```bash
ls -lh /Users/joostdevalk/Code/rondo/rondo-club/dist/assets/*.js | head -n 2
```
Build timestamp should be current.
  </verify>
  <done>Production assets rebuilt with Vrijwilliger badge code</done>
</task>

</tasks>

<verification>
Manual verification on production after deployment:

1. Visit a volunteer's PersonDetail page (person with active commissie role)
2. Confirm "Vrijwilliger" badge appears next to name in electric-cyan color
3. Confirm badge does NOT appear for non-volunteers
4. Confirm "Oud-lid" badge (if applicable) appears in gray next to Vrijwilliger badge
5. Check dark mode - both badges should have appropriate dark mode colors
</verification>

<success_criteria>
- [ ] REST API includes huidig_vrijwilliger field in format_person_summary()
- [ ] PersonDetail renders Vrijwilliger badge using electric-cyan brand color
- [ ] Badge only shows for people with huidig_vrijwilliger = true
- [ ] Production build completed successfully
- [ ] Code committed with semantic versioning message
- [ ] Changes deployed to production
</success_criteria>

<output>
After completion, create `.planning/quick/61-add-vrijwilliger-badge-to-volunteers-sim/61-SUMMARY.md`
</output>
