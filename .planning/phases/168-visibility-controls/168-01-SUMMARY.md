---
phase: 168-visibility-controls
plan: 01
subsystem: people-management
tags:
  - api
  - ui
  - filters
  - former-members
dependency_graph:
  requires:
    - 167-01-core-filtering
  provides:
    - former-member-visibility-controls
    - include-former-parameter
    - oud-leden-toggle
  affects:
    - people-list-filtering
    - global-search
    - export-functionality
tech_stack:
  added: []
  patterns:
    - Toggle switch UI component
    - URL-persisted filter state
    - Conditional SQL WHERE clause
key_files:
  created:
    - ../developer/src/content/docs/features/former-members.md
  modified:
    - includes/class-rest-people.php
    - includes/class-rest-base.php
    - src/hooks/usePeople.js
    - src/pages/People/PeopleList.jsx
    - src/components/layout/Layout.jsx
    - CHANGELOG.md
    - package.json
    - style.css
decisions:
  - decision: Place "Toon oud-leden" toggle at top of filter dropdown for prominence
    rationale: Former members are a critical use case (rejoining members, inquiries) that needs to be easily discoverable
  - decision: Use reduced opacity (60%) instead of greying out former member rows
    rationale: Maintains readability while providing visual distinction without suggesting disabled state
  - decision: Show "Oud-lid" badge in both People list and global search
    rationale: Consistent visual language across all contexts where former members appear
  - decision: Use loose comparison (== true) for ACF former_member field
    rationale: ACF true_false fields return '1' as string when true, not boolean
metrics:
  duration_seconds: 380
  duration_human: 6m 20s
  tasks_completed: 2
  files_modified: 8
  commits: 3
  repositories: 2
  completed_date: 2026-02-09
---

# Phase 168 Plan 01: Visibility Controls Summary

**One-liner:** Toggle-controlled former member visibility with "Toon oud-leden" filter and "Oud-lid" visual indicators in People list and global search.

## Objective

Add visibility controls for former members: a "Toon oud-leden" toggle on the Leden list filter dropdown and "oud-lid" indicator in global search results, enabling users to find former members when needed (e.g., when a former member contacts the club) even though they are hidden from default views.

## Execution Report

### Task 1: Add include_former parameter and former_member flag to backend

**Status:** ✅ Complete
**Commit:** 17d5fa9d
**Files Modified:** 2 (includes/class-rest-people.php, includes/class-rest-base.php)

**Changes:**
- Added `include_former` parameter to `/rondo/v1/people/filtered` endpoint registration
  - Type: string, values: '' or '1', default: ''
  - Description: "Include former members in results (1=include, empty=exclude)"
- Modified `get_filtered_people()` to extract `include_former` parameter
- Converted former member exclusion from always-on to conditional based on `include_former`
  - Always includes LEFT JOIN for `fm` (former_member) meta field
  - Only adds WHERE clause exclusion when `include_former !== '1'`
- Added `fm.meta_value AS is_former_member` to SELECT fields
- Added `former_member` boolean to person data array in response formatting
  - Converts string '1' to boolean true: `( $row->is_former_member === '1' )`
- Updated `format_person_summary()` in includes/class-rest-base.php to include `former_member` field
  - Uses loose comparison: `( get_field( 'former_member', $post->ID ) == true )`
  - Affects global search, dashboard recent people/contacts, and all person summary responses

**Verification:**
- npm run build succeeded without errors
- Deployed to production successfully
- Backend changes enable frontend controls

### Task 2: Add Toon oud-leden toggle and search indicator in frontend

**Status:** ✅ Complete
**Commit:** 9a8f4502 (frontend), c108b12 (docs)
**Files Modified:** 6 (rondo-club) + 1 (developer docs)

**Changes:**

**1. usePeople.js:**
- Added `include_former: filters.includeFormer || null` to params object in useFilteredPeople hook

**2. PeopleList.jsx:**
- Added URL param parsing: `const includeFormer = searchParams.get('oudLeden') || ''`
- Added setter: `setIncludeFormer` callback that updates URL via `updateSearchParams({ oudLeden: value })`
- Added `includeFormer` to useFilteredPeople call
- Added `includeFormer` to hasActiveFilters calculation
- Updated filter count badge to include `(includeFormer ? 1 : 0)`
- Added "Toon oud-leden" toggle UI at top of filter dropdown:
  - Toggle switch with peer-checked state for electric-cyan background
  - Positioned prominently as first filter for easy discovery
- Updated PersonListRow component:
  - Added `${person.former_member ? 'opacity-60' : ''}` to row className
  - Added "Oud-lid" badge after person name with conditional rendering
  - Badge styling: gray background (bg-gray-200/dark:bg-gray-600)
- Added `includeFormer` to selection reset useEffect dependency array
- Added `include_former: includeFormer || undefined` to export filter params
- clearFilters already clears all URL params except sort/order, so includeFormer handled automatically

**3. Layout.jsx (SearchModal):**
- Added "Oud-lid" badge in search results after person name
- Badge includes `flex-shrink-0` to prevent wrapping
- Consistent styling with People list badge

**4. Version and Changelog:**
- Bumped version to 23.1.0 in style.css and package.json (minor version for new features)
- Added changelog entry documenting all visibility control features

**5. Developer Documentation:**
- Created `../developer/src/content/docs/features/former-members.md` with Starlight frontmatter
- Documented ACF field, default filtering behavior, visibility controls, API response format
- Explained NULL-safe filtering pattern and fail-safe behavior
- Documented frontend UI components and visual indicators

**Verification:**
- npm run build succeeded without errors
- Deployed to production successfully
- All UI changes visible in production environment

## Deviations from Plan

None - plan executed exactly as written.

## Key Technical Details

### SQL Query Pattern

The filtered people endpoint uses a conditional WHERE clause for former member exclusion:

```php
// Always include LEFT JOIN (needed for SELECT and response)
$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'";
$select_fields .= ', fm.meta_value AS is_former_member';

// Conditionally exclude former members
if ( $include_former !== '1' ) {
    $where_clauses[] = "(fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')";
}
```

This pattern:
- Always JOINs the former_member field (for response data)
- Only adds exclusion WHERE clause when toggle is OFF
- Uses NULL-safe pattern to treat missing field as "not former member"

### Toggle Switch Component

The "Toon oud-leden" toggle uses Tailwind's peer-checked pattern for state-based styling:

```jsx
<input type="checkbox" checked={includeFormer === '1'} className="sr-only peer" />
<div className="... peer-checked:bg-electric-cyan">...</div>
<div className="... peer-checked:translate-x-4">...</div>
```

Benefits:
- Screen reader accessible (sr-only checkbox)
- Pure CSS state management via peer selector
- Electric-cyan brand color on checked state

### ACF Field Type Handling

ACF true_false fields return string '1' when true, requiring different comparison patterns:

```php
// Strict comparison with string '1' in SQL result
'former_member' => ( $row->is_former_member === '1' )

// Loose comparison with boolean for get_field() result
'former_member' => ( get_field( 'former_member', $post->ID ) == true )
```

## Impact Assessment

**Users:**
- Can now easily find former members through filter toggle
- Former members discoverable via global search with visual indicator
- Clear visual distinction prevents confusion between current and former members

**Codebase:**
- Backend filtering remains performant (single query with conditional WHERE)
- Frontend state properly persisted in URL for bookmarkability
- Consistent former_member flag across all person summary responses

**Future Work:**
- Toggle pattern can be reused for other binary filters
- Former member visual treatment established as pattern

## Artifacts

**Commits:**
- 17d5fa9d: feat(168-01): add include_former parameter and former_member flag to backend
- 9a8f4502: feat(168-01): add Toon oud-leden toggle and search indicator in frontend
- c108b12: docs(168-01): add former members feature documentation

**Files Modified:** 8 across 2 repositories (rondo-club, developer)

**Documentation:** features/former-members.md created with comprehensive system documentation

## Self-Check: PASSED

**Created files verified:**
```bash
✓ ../developer/src/content/docs/features/former-members.md exists
```

**Commits verified:**
```bash
✓ 17d5fa9d exists in git log
✓ 9a8f4502 exists in git log
✓ c108b12 exists in developer repo git log
```

**Modified files verified:**
```bash
✓ includes/class-rest-people.php contains "include_former"
✓ includes/class-rest-base.php contains "former_member"
✓ src/hooks/usePeople.js contains "includeFormer"
✓ src/pages/People/PeopleList.jsx contains "Toon oud-leden"
✓ src/components/layout/Layout.jsx contains "Oud-lid"
✓ CHANGELOG.md contains "[23.1.0]"
✓ package.json version is "23.1.0"
✓ style.css Version is "23.1.0"
```

All files created, all commits present, all changes verified.

---

**Execution completed:** 2026-02-09
**Phase 168 Plan 01 complete.** Former member visibility controls enable users to find former members when needed while maintaining clean default views.
