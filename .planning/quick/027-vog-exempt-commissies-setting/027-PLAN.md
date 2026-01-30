---
phase: quick-027
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-vog-email.php
  - includes/class-rest-api.php
  - includes/class-volunteer-status.php
  - src/pages/Settings/Settings.jsx
  - src/api/client.js
autonomous: true

must_haves:
  truths:
    - "Admin can select commissies that are exempt from VOG requirements in settings"
    - "Exempt commissies are excluded from volunteer status calculation (for VOG purposes)"
    - "Saving exempt commissies triggers VOG recalculation for all volunteers"
  artifacts:
    - path: "includes/class-vog-email.php"
      provides: "exempt_commissies option getter/setter methods"
    - path: "includes/class-rest-api.php"
      provides: "API endpoints for exempt_commissies setting"
    - path: "includes/class-volunteer-status.php"
      provides: "Logic to check exempt commissies during volunteer status calculation"
    - path: "src/pages/Settings/Settings.jsx"
      provides: "Multi-select UI for exempt commissies in VOG tab"
  key_links:
    - from: "src/pages/Settings/Settings.jsx"
      to: "/stadion/v1/vog/settings"
      via: "API call on save"
    - from: "includes/class-rest-api.php"
      to: "includes/class-volunteer-status.php"
      via: "trigger_vog_recalculation function call"
---

<objective>
Add a setting to exclude commissies from VOG requirements. Some commissies have no interaction with data or kids and should not require a VOG.

Purpose: Allow administrators to exempt specific commissies from VOG tracking, reducing unnecessary VOG requests.
Output: Working exempt commissies setting in VOG tab with recalculation on save.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@AGENTS.md
@includes/class-vog-email.php - VOG settings storage (options pattern)
@includes/class-rest-api.php - VOG settings REST endpoints
@includes/class-volunteer-status.php - Volunteer status calculation logic
@src/pages/Settings/Settings.jsx - VOGTab component
@src/api/client.js - API client methods
</context>

<tasks>

<task type="auto">
  <name>Task 1: Backend - Add exempt commissies setting and recalculation</name>
  <files>
    includes/class-vog-email.php
    includes/class-rest-api.php
    includes/class-volunteer-status.php
  </files>
  <action>
1. In `class-vog-email.php`, add:
   - New constant `OPTION_EXEMPT_COMMISSIES = 'stadion_vog_exempt_commissies'`
   - Method `get_exempt_commissies(): array` - returns array of commissie IDs (empty array default)
   - Method `update_exempt_commissies(array $ids): bool` - saves array of commissie IDs

2. In `class-rest-api.php`:
   - Update `get_vog_settings()` to include `exempt_commissies` in response
   - Update `update_vog_settings()` args to accept `exempt_commissies` array
   - In `update_vog_settings()`, when `exempt_commissies` changes:
     - Call new method `trigger_vog_recalculation()` that queries all people with `huidig-vrijwilliger = 1` and re-saves them to trigger the ACF save hook
   - Add new private method `trigger_vog_recalculation()`:
     ```php
     private function trigger_vog_recalculation() {
         $volunteer_status = new \Stadion\Core\VolunteerStatus();
         $people = get_posts([
             'post_type' => 'person',
             'posts_per_page' => -1,
             'post_status' => 'publish',
             'fields' => 'ids',
         ]);
         foreach ($people as $person_id) {
             $volunteer_status->calculate_and_update_status($person_id);
         }
         return count($people);
     }
     ```

3. In `class-volunteer-status.php`:
   - Make `calculate_and_update_status()` public (currently private) so it can be called from REST API
   - Update `is_volunteer_position()` method to check if the commissie is exempt:
     - If `entity_type === 'commissie'` or post type is commissie, check if the commissie ID is in the exempt list
     - If exempt, return false (not a volunteer position for VOG purposes)
     - Get exempt list via: `get_option('stadion_vog_exempt_commissies', [])`

Note: The exemption only affects VOG tracking. People in exempt commissies may still be volunteers for other purposes, but they won't trigger VOG requirements through that position.
  </action>
  <verify>
    - Test API: `curl -X GET /wp-json/stadion/v1/vog/settings` includes `exempt_commissies: []`
    - Test API: `curl -X POST /wp-json/stadion/v1/vog/settings -d '{"exempt_commissies": [123, 456]}'` saves and triggers recalculation
    - Verify option saved: `wp option get stadion_vog_exempt_commissies`
  </verify>
  <done>
    - VOG settings API returns and accepts `exempt_commissies` array
    - Saving exempt commissies triggers volunteer status recalculation
    - Exempt commissie positions no longer count toward `huidig-vrijwilliger` status for VOG
  </done>
</task>

<task type="auto">
  <name>Task 2: Frontend - Add exempt commissies multi-select to VOG settings</name>
  <files>
    src/pages/Settings/Settings.jsx
    src/api/client.js
  </files>
  <action>
1. In `Settings.jsx`:
   - Add state for commissies list: `const [commissies, setCommissies] = useState([]);`
   - Add state for recalculation status: `const [isRecalculating, setIsRecalculating] = useState(false);`
   - In the VOG settings fetch useEffect, also fetch commissies:
     ```javascript
     const commissiesResponse = await prmApi.getCommissies({ per_page: 100, _fields: 'id,title' });
     setCommissies(commissiesResponse.data || []);
     ```
   - Update `vogSettings` state to include `exempt_commissies: []`
   - In `VOGTab` component, add a multi-select section after the templates:
     ```jsx
     {/* Vrijgestelde commissies */}
     <div>
       <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
         Vrijgestelde commissies
       </label>
       <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
         Selecteer commissies die vrijgesteld zijn van de VOG-verplichting. Leden van deze commissies verschijnen niet in de VOG-lijst.
       </p>
       <div className="mt-2 border rounded-md border-gray-300 dark:border-gray-600 max-h-48 overflow-y-auto">
         {commissies.map(commissie => (
           <label key={commissie.id} className="flex items-center px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
             <input
               type="checkbox"
               checked={vogSettings.exempt_commissies?.includes(commissie.id)}
               onChange={(e) => {
                 const id = commissie.id;
                 setVogSettings(prev => ({
                   ...prev,
                   exempt_commissies: e.target.checked
                     ? [...(prev.exempt_commissies || []), id]
                     : (prev.exempt_commissies || []).filter(i => i !== id)
                 }));
               }}
               className="h-4 w-4 text-accent-600 focus:ring-accent-500 border-gray-300 rounded"
             />
             <span className="ml-3 text-sm text-gray-700 dark:text-gray-300">
               {commissie.title?.rendered || commissie.title}
             </span>
           </label>
         ))}
         {commissies.length === 0 && (
           <p className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
             Geen commissies gevonden
           </p>
         )}
       </div>
     </div>
     ```
   - Update save message to indicate recalculation when exempt_commissies changed:
     ```javascript
     setVogMessage(response.data.people_recalculated
       ? `VOG-instellingen opgeslagen. ${response.data.people_recalculated} personen herberekend.`
       : 'VOG-instellingen opgeslagen');
     ```

2. In `src/api/client.js`:
   - No changes needed - `updateVOGSettings` already accepts full settings object

3. Pass commissies to VOGTab component and update its props.
  </action>
  <verify>
    - Navigate to Settings > VOG tab
    - Verify commissies list shows with checkboxes
    - Select/deselect commissies and save
    - Verify save message shows recalculation count
    - Refresh page, verify selections persisted
  </verify>
  <done>
    - VOG settings tab shows multi-select list of all commissies
    - Selecting exempt commissies and saving persists the selection
    - Save message indicates number of people recalculated
  </done>
</task>

<task type="auto">
  <name>Task 3: Build, deploy, and version bump</name>
  <files>
    style.css
    package.json
    CHANGELOG.md
  </files>
  <action>
1. Run `npm run build` to build production assets
2. Update version in style.css and package.json (patch bump)
3. Add changelog entry under "Added":
   - VOG exempt commissies setting - exclude commissies without child contact from VOG requirements
4. Deploy to production with `bin/deploy.sh`
5. Git commit all changes
  </action>
  <verify>
    - `npm run build` succeeds without errors
    - Production site shows exempt commissies setting in VOG tab
    - Version number updated in both files
    - Changelog entry added
  </verify>
  <done>
    - Feature deployed to production
    - Version bumped and changelog updated
    - All changes committed
  </done>
</task>

</tasks>

<verification>
- API: GET /stadion/v1/vog/settings returns exempt_commissies array
- API: POST /stadion/v1/vog/settings with exempt_commissies saves and returns people_recalculated count
- UI: VOG settings tab shows commissies checkbox list
- Functional: Person in exempt commissie only (no other volunteer roles) does NOT appear in VOG list
- Functional: Person in exempt commissie + non-exempt team role DOES appear in VOG list
</verification>

<success_criteria>
- Admin can select commissies to exempt from VOG requirements
- Saving triggers automatic recalculation of all volunteer statuses
- People whose only volunteer role is in an exempt commissie are removed from VOG list
- Changes persist across page refreshes
- Feature deployed to production
</success_criteria>

<output>
After completion, create `.planning/quick/027-vog-exempt-commissies-setting/027-SUMMARY.md`
</output>
