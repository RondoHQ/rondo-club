---
phase: quick
plan: 040
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-membership-fees.php
  - includes/class-rest-api.php
  - src/api/client.js
  - src/pages/Settings/Settings.jsx
autonomous: true
user_setup: []

must_haves:
  truths:
    - "Admin can view fee settings for current season (2025-2026)"
    - "Admin can view fee settings for next season (2026-2027)"
    - "Admin can save fee changes for each season independently"
    - "Existing global fees are migrated to current season on first read"
    - "Fee calculations use current season fees by default"
  artifacts:
    - path: "includes/class-membership-fees.php"
      provides: "Per-season fee storage with migration"
      contains: "get_settings_for_season"
    - path: "includes/class-rest-api.php"
      provides: "Updated API returning both seasons"
      contains: "current_season"
    - path: "src/pages/Settings/Settings.jsx"
      provides: "Two-section fee settings UI"
      contains: "currentSeasonFees"
  key_links:
    - from: "src/pages/Settings/Settings.jsx"
      to: "/rondo/v1/membership-fees/settings"
      via: "prmApi.getMembershipFeeSettings()"
      pattern: "getMembershipFeeSettings"
    - from: "includes/class-rest-api.php"
      to: "class-membership-fees.php"
      via: "MembershipFees service"
      pattern: "get_settings_for_season"
---

<objective>
Make membership fee (contributie) settings per-season instead of global.

Purpose: Fees change annually. Club needs to configure current season fees and pre-configure next season fees before July 1 transition. Migration preserves existing values.

Output: Per-season fee storage with UI showing both current (2025-2026) and next (2026-2027) seasons.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@includes/class-membership-fees.php (fee service with get_season_key(), get_next_season_key())
@includes/class-rest-api.php (GET/POST /membership-fees/settings endpoints)
@src/pages/Settings/Settings.jsx (FeesSubtab component)
@src/api/client.js (getMembershipFeeSettings, updateMembershipFeeSettings)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Backend per-season fee storage with migration</name>
  <files>
    includes/class-membership-fees.php
    includes/class-rest-api.php
  </files>
  <action>
    **In class-membership-fees.php:**

    1. Keep `OPTION_KEY = 'stadion_membership_fees'` as fallback for migration

    2. Add new method `get_option_key_for_season(string $season): string`:
       - Returns `'stadion_membership_fees_' . $season` (e.g., 'stadion_membership_fees_2025-2026')

    3. Add new method `get_settings_for_season(string $season): array`:
       - Check for season-specific option using `get_option_key_for_season($season)`
       - If season option exists, return merged with DEFAULTS
       - If season option doesn't exist AND season equals current season AND old global option exists:
         - Migrate: copy old global option to season-specific option
         - Delete old global option (one-time migration)
         - Return the migrated values
       - Otherwise return DEFAULTS

    4. Add new method `update_settings_for_season(array $fees, string $season): bool`:
       - Validate fee types against VALID_TYPES
       - Validate amounts are numeric and non-negative
       - Save to season-specific option key

    5. Modify existing `get_all_settings()`:
       - Call `get_settings_for_season($this->get_season_key())` for current season
       - This ensures backward compatibility with existing code

    6. Modify existing `update_settings(array $fees)`:
       - Call `update_settings_for_season($fees, $this->get_season_key())`
       - Backward compatible with existing callers

    **In class-rest-api.php:**

    1. Modify `get_membership_fee_settings()` to return structure:
       ```php
       [
           'current_season' => [
               'key' => '2025-2026',
               'fees' => [ 'mini' => 130, ... ]
           ],
           'next_season' => [
               'key' => '2026-2027',
               'fees' => [ 'mini' => 130, ... ]
           ]
       ]
       ```
       - Use `get_settings_for_season()` for each season

    2. Modify `update_membership_fee_settings()` to accept:
       - New parameter `season` (string, required) - which season to update
       - Validate season is either current or next season key
       - Call `update_settings_for_season($fees, $season)`
       - Return updated structure for both seasons (same as GET)

    3. Update route registration args to include `season` parameter:
       ```php
       'season' => [
           'required' => true,
           'type' => 'string',
           'validate_callback' => function($param, $request, $key) {
               $membership_fees = new \Stadion\Fees\MembershipFees();
               $valid = [$membership_fees->get_season_key(), $membership_fees->get_next_season_key()];
               return in_array($param, $valid, true);
           }
       ]
       ```
  </action>
  <verify>
    Test via WP-CLI or curl:
    - GET returns both seasons with current season having migrated values
    - POST with season parameter updates only that season
    - Existing get_fee() calls still work (uses current season)
  </verify>
  <done>
    - API returns `{current_season: {key, fees}, next_season: {key, fees}}`
    - POST requires `season` param and updates only specified season
    - Old global option migrated to current season on first read
    - Backward compatible: existing calculate_fee/get_fee use current season
  </done>
</task>

<task type="auto">
  <name>Task 2: Frontend two-section fee settings UI</name>
  <files>
    src/api/client.js
    src/pages/Settings/Settings.jsx
  </files>
  <action>
    **In src/api/client.js:**

    1. Keep `getMembershipFeeSettings()` unchanged (still GET)

    2. Modify `updateMembershipFeeSettings(settings)` to `updateMembershipFeeSettings(settings, season)`:
       - POST body should include season: `api.post('/rondo/v1/membership-fees/settings', { ...settings, season })`

    **In src/pages/Settings/Settings.jsx:**

    1. Change state from single `feeSettings` to two separate states:
       ```javascript
       const [currentSeasonFees, setCurrentSeasonFees] = useState({ key: '', fees: { mini: 130, ... } });
       const [nextSeasonFees, setNextSeasonFees] = useState({ key: '', fees: { mini: 130, ... } });
       ```

    2. Update `fetchFeeSettings` to parse new API response:
       ```javascript
       const response = await prmApi.getMembershipFeeSettings();
       setCurrentSeasonFees(response.data.current_season);
       setNextSeasonFees(response.data.next_season);
       ```

    3. Change `handleFeeSave` to `handleSeasonFeeSave(season, fees)`:
       ```javascript
       const handleSeasonFeeSave = async (season, fees) => {
         setFeeSaving(true);
         setFeeMessage('');
         try {
           const response = await prmApi.updateMembershipFeeSettings(fees, season);
           setCurrentSeasonFees(response.data.current_season);
           setNextSeasonFees(response.data.next_season);
           setFeeMessage('Contributie-instellingen opgeslagen');
         } catch (error) {
           setFeeMessage('Fout bij opslaan: ' + (error.response?.data?.message || 'Onbekende fout'));
         } finally {
           setFeeSaving(false);
         }
       };
       ```

    4. Update `AdminTabWithSubtabs` props to pass both season states and the new save handler

    5. Rewrite `FeesSubtab` component to show two sections:
       - Section 1: "Huidig seizoen: 2025-2026" with fee inputs + save button
       - Section 2: "Volgend seizoen: 2026-2027" with fee inputs + save button
       - Each section has its own save button that calls `handleSeasonFeeSave(seasonKey, seasonFees)`
       - Use same FEE_TYPES array for both sections
       - Visual separation between sections (divider or card styling)

    **UI Layout:**
    ```
    Contributie-instellingen

    [Card] Huidig seizoen: 2025-2026
      Mini: [___] EUR
      Pupil: [___] EUR
      ...
      [Opslaan]

    [Card] Volgend seizoen: 2026-2027
      Mini: [___] EUR
      Pupil: [___] EUR
      ...
      [Opslaan]
    ```
  </action>
  <verify>
    - `npm run lint` passes
    - `npm run build` succeeds
    - UI shows two sections with correct season labels
    - Each section saves independently
    - Values persist after refresh
  </verify>
  <done>
    - FeesSubtab shows two season sections
    - Each section displays season key as header (e.g., "2025-2026")
    - Each section has independent save button
    - Saving one season doesn't affect the other
    - Message shows save success/failure
  </done>
</task>

<task type="auto">
  <name>Task 3: Deploy, test, and document</name>
  <files>
    docs/membership-fees.md (if exists, update; otherwise create)
    style.css
    package.json
    CHANGELOG.md
  </files>
  <action>
    1. Run `npm run build` to create production assets

    2. Deploy to production: `bin/deploy.sh`

    3. Test on production:
       - Navigate to Settings > Admin > Contributie
       - Verify both season sections appear
       - Change a value in current season, save, verify it persists
       - Change a value in next season, save, verify it persists
       - Verify seasons are independent (changing one doesn't affect other)

    4. Update version to 18.0.0 (new feature: per-season fees):
       - style.css: Version: 18.0.0
       - package.json: "version": "18.0.0"

    5. Update CHANGELOG.md with:
       ```markdown
       ## [18.0.0] - 2026-02-05

       ### Added
       - Per-season membership fee settings (current and next season)
       - Automatic migration of existing global fees to current season

       ### Changed
       - Settings UI shows two fee sections: current season and next season
       - API returns both seasons, accepts season parameter for updates
       ```

    6. Update or create docs/membership-fees.md to document:
       - Per-season storage (option keys: stadion_membership_fees_YYYY-YYYY)
       - Migration behavior (one-time from global to current season)
       - API endpoint changes (GET returns both, POST requires season)
       - Season transition behavior (July 1 = new season starts)

    7. Commit all changes with message:
       `feat: per-season membership fee settings`

    8. Push to remote
  </action>
  <verify>
    - Production shows two-section fee UI
    - Both seasons save correctly
    - Version is 18.0.0
    - CHANGELOG updated
    - Git commit pushed
  </verify>
  <done>
    - Feature deployed and working on production
    - Documentation updated
    - Version bumped to 18.0.0
    - Changes committed and pushed
  </done>
</task>

</tasks>

<verification>
After all tasks complete:
1. API: `curl /rondo/v1/membership-fees/settings` returns `{current_season: {...}, next_season: {...}}`
2. UI: Admin > Settings > Contributie shows two season sections
3. Each season saves independently via POST with season parameter
4. Existing fee calculations use current season (backward compatible)
5. Migration: old global option moved to current season option on first access
</verification>

<success_criteria>
- Fees stored per-season in separate WordPress options
- Current + next season visible and editable in admin UI
- Existing `get_fee()` calls work unchanged (use current season)
- Old global option migrated automatically
- Version 18.0.0, changelog updated, deployed to production
</success_criteria>

<output>
After completion, create `.planning/quick/040-per-season-contributie-settings/040-SUMMARY.md`
</output>
