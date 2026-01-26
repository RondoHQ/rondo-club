---
quick: 002-remove-favorites
type: execute
autonomous: true
files_modified:
  - src/pages/People/PeopleList.jsx
  - src/pages/People/PersonDetail.jsx
  - src/pages/Dashboard.jsx
  - src/components/DashboardCustomizeModal.jsx
  - src/components/PersonEditModal.jsx
  - src/hooks/usePeople.js
  - src/hooks/useDashboard.js
  - includes/class-rest-api.php
  - includes/class-rest-base.php
  - includes/class-monica-import.php
  - acf-json/group_person_fields.json
---

<objective>
Remove the Favorites feature entirely from Stadion.

Purpose: Simplify the data model by removing an unused feature - the is_favorite field and all related UI elements.
Output: Clean codebase with no references to favorites functionality.
</objective>

<context>
@AGENTS.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Remove favorites from frontend components</name>
  <files>
    - src/pages/People/PeopleList.jsx
    - src/pages/People/PersonDetail.jsx
    - src/pages/Dashboard.jsx
    - src/components/DashboardCustomizeModal.jsx
    - src/components/PersonEditModal.jsx
  </files>
  <action>
    1. **PeopleList.jsx:**
       - Remove `Star` from lucide-react imports
       - Remove `showFavoritesOnly` state (line 641)
       - Remove favorites filter from `filteredAndSortedPeople` useMemo (lines 772-775)
       - Remove `showFavoritesOnly` from `hasActiveFilters` calculation (line 840)
       - Remove `showFavoritesOnly` from `clearFilters` function (line 851)
       - Remove favorites checkbox filter UI in filter dropdown (lines 1118-1141)
       - Remove favorites active filter chip (lines 1288-1298)
       - Remove `showFavoritesOnly` from useEffect dependencies (line 890)
       - Remove star icon display in PersonListRow (lines 82-84)

    2. **PersonDetail.jsx:**
       - Remove `Star` from lucide-react imports (if not used elsewhere)
       - Remove is_favorite from the updatePerson payload (line 641)
       - Remove star icon display next to person name (lines 1528-1529)

    3. **Dashboard.jsx:**
       - Remove `Star` from lucide-react imports
       - Remove `favorites` from destructured dashboard data (line 605)
       - Remove the entire `favorites` card renderer (lines 821-837)
       - Remove `hideStar` prop handling in PersonCard - always hide star (simplify component)

    4. **DashboardCustomizeModal.jsx:**
       - Remove `'favorites'` entry from CARD_DEFINITIONS object (line 31)

    5. **PersonEditModal.jsx:**
       - Remove `is_favorite: false` from useForm defaultValues (line 64)
       - Remove `is_favorite` from form reset in all places (lines 94, 112, 129, 183)
       - Remove the "Markeren als favoriet" checkbox UI (lines 446-457)
       - Remove `is_favorite` from register calls
  </action>
  <verify>
    - `grep -r "is_favorite" src/` returns no results
    - `grep -r "showFavoritesOnly" src/` returns no results
    - `grep -r "'favorites'" src/components/DashboardCustomizeModal.jsx` returns no results
    - `npm run lint` passes without errors
  </verify>
  <done>Frontend has no references to favorites functionality.</done>
</task>

<task type="auto">
  <name>Task 2: Remove favorites from hooks and backend</name>
  <files>
    - src/hooks/usePeople.js
    - src/hooks/useDashboard.js
    - includes/class-rest-api.php
    - includes/class-rest-base.php
    - includes/class-monica-import.php
  </files>
  <action>
    1. **src/hooks/usePeople.js:**
       - Remove `is_favorite: person.acf?.is_favorite || false` from transformPerson function (line 39)

    2. **src/hooks/useDashboard.js:**
       - Remove `'favorites'` from DEFAULT_DASHBOARD_CARDS array (line 13)

    3. **includes/class-rest-api.php:**
       - Remove `'favorites'` from VALID_DASHBOARD_CARDS array (line 878)
       - Remove `'favorites'` from DEFAULT_DASHBOARD_ORDER array (line 893)
       - Remove favorites query from get_dashboard_summary method (lines 1363-1376)
       - Remove `'favorites'` from the dashboard response (line 1398)

    4. **includes/class-rest-base.php:**
       - Remove `'is_favorite' => (bool) get_field( 'is_favorite', $post->ID ),` from format_person_summary method (line 214)

    5. **includes/class-monica-import.php:**
       - Remove the line that sets is_favorite field during import (around line 474)
  </action>
  <verify>
    - `grep -r "is_favorite" includes/` returns no results (except any unrelated matches)
    - `grep "'favorites'" includes/class-rest-api.php` returns no results
    - `grep "is_favorite" src/hooks/` returns no results
  </verify>
  <done>Backend and hooks have no references to favorites functionality.</done>
</task>

<task type="auto">
  <name>Task 3: Remove favorites ACF field and build</name>
  <files>
    - acf-json/group_person_fields.json
  </files>
  <action>
    1. **acf-json/group_person_fields.json:**
       - Remove the entire is_favorite field definition (lines 86-93):
         ```json
         {
             "key": "field_is_favorite",
             "label": "Favorite",
             "name": "is_favorite",
             "type": "true_false",
             "message": "Mark as favorite",
             "default_value": 0,
             "ui": 1
         },
         ```
       - Ensure valid JSON after removal (no trailing commas)

    2. Build frontend:
       - Run `npm run build` to create production assets

    3. Deploy to production:
       - Run `bin/deploy.sh` to deploy changes
  </action>
  <verify>
    - `grep "is_favorite" acf-json/group_person_fields.json` returns no results
    - `npm run build` completes successfully
    - Production site loads without errors
    - PeopleList page renders without favorites filter
    - Dashboard renders without favorites widget
    - Person edit modal has no favorite checkbox
  </verify>
  <done>ACF field removed, frontend built, and deployed to production.</done>
</task>

</tasks>

<verification>
1. Frontend verification:
   - `npm run lint` passes
   - `npm run build` completes without errors
   - No references to is_favorite, showFavoritesOnly, or favorites widget in src/

2. Backend verification:
   - No references to is_favorite in REST API responses
   - Dashboard endpoint no longer queries or returns favorites

3. ACF verification:
   - Field definition removed from JSON
   - Field will no longer appear in WordPress admin
</verification>

<success_criteria>
- All frontend references to favorites removed (filter, star icons, checkbox, dashboard widget)
- All backend references to favorites removed (API responses, dashboard data)
- ACF field definition removed
- Build passes without errors
- Deployed to production and verified working
</success_criteria>

<output>
After completion, create `.planning/quick/002-remove-favorites/002-SUMMARY.md`
</output>
