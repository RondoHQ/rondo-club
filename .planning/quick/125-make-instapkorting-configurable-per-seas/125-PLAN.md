---
phase: quick-125
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-membership-fees.php
  - includes/class-rest-api.php
  - src/pages/Settings/FeeCategorySettings.jsx
autonomous: true
requirements: [QUICK-125]
must_haves:
  truths:
    - "Admin can configure instapkorting periods and percentages per season"
    - "Each period has a start month, end month, and discount percentage"
    - "Instapkorting config is independent per season (current and next)"
    - "Pro-rata calculation uses configured periods instead of hardcoded quarters"
    - "Copy-season copies instapkorting config along with categories and family discount"
    - "Default config matches current hardcoded behavior (quarterly 0/25/50/75%)"
  artifacts:
    - path: "includes/class-membership-fees.php"
      provides: "Configurable instapkorting storage and lookup"
      contains: "get_entry_discount_config"
    - path: "includes/class-rest-api.php"
      provides: "Entry discount in settings GET/POST and copy-season endpoints"
      contains: "entry_discount"
    - path: "src/pages/Settings/FeeCategorySettings.jsx"
      provides: "EntryDiscountSection UI component"
      contains: "EntryDiscountSection"
  key_links:
    - from: "includes/class-membership-fees.php"
      to: "wp_options"
      via: "get_option/update_option with rondo_entry_discount_{season}"
      pattern: "rondo_entry_discount_"
    - from: "includes/class-rest-api.php"
      to: "includes/class-membership-fees.php"
      via: "get/save_entry_discount_config calls"
      pattern: "entry_discount_config"
    - from: "src/pages/Settings/FeeCategorySettings.jsx"
      to: "/rondo/v1/membership-fees/settings"
      via: "prmApi.updateMembershipFeeSettings with entry_discount param"
      pattern: "entry_discount"
---

<objective>
Make instapkorting (entry discount / pro-rata) configurable per season instead of using hardcoded quarterly periods.

Currently `MembershipFees::get_prorata_percentage()` has fixed quarters (Jul-Sep=100%, Oct-Dec=75%, Jan-Mar=50%, Apr-Jun=25%). This should be configurable per season via the existing Contributie settings tab, following the same pattern as family discount config (stored per season in wp_options, included in GET/POST settings endpoints, copied via copy-season).

Purpose: Allow clubs to customize when and how much instapkorting applies, since not all clubs follow the same quarterly structure.
Output: Configurable instapkorting periods per season with admin UI, stored in wp_options and used by fee calculation.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-membership-fees.php (lines 1074-1104 for family_discount pattern, lines 1802-1856 for get_prorata_percentage)
@includes/class-rest-api.php (lines 4451-4540 for settings GET/POST endpoints, lines 4555-4614 for copy-season, lines 4757-4805 for validate_family_discount_config)
@src/pages/Settings/FeeCategorySettings.jsx (lines 402-500 for FamilyDiscountSection pattern, lines 636-665 for discount mutation, lines 1081-1086 for where to add)
@includes/class-bulk-invoice-creator.php (lines 282-293 for instapkorting invoice line creation)
@src/api/client.js (line 288 for updateMembershipFeeSettings)

<interfaces>
<!-- Existing patterns to follow exactly -->

From includes/class-membership-fees.php — Family discount config pattern:
```php
public function get_family_discount_config( ?string $season = null ): array {
    $season   = $season ?: $this->get_season_key();
    $defaults = [ 'second_child_percent' => 25, 'third_child_percent' => 50 ];
    $config = get_option( 'rondo_family_discount_' . $season, false );
    // ... returns config or defaults
}

public function save_family_discount_config( array $config, string $season ): bool {
    return update_option( 'rondo_family_discount_' . $season, $config );
}
```

From includes/class-rest-api.php — Settings response shape:
```php
'current_season' => [
    'key'             => $current_season,
    'categories'      => ...,
    'family_discount' => ...,
    // entry_discount will be added here
],
```

From src/pages/Settings/FeeCategorySettings.jsx — Discount section pattern:
```jsx
function FamilyDiscountSection({ discountConfig, onSave, isSaving }) {
  // Local state synced from props, isDirty tracking, handleSave/handleReset
  // Renders card with inputs and save/reset buttons
}
// Used in main component:
<FamilyDiscountSection discountConfig={activeDiscount} onSave={handleDiscountSave} isSaving={...} />
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Backend — configurable instapkorting storage, lookup, and API</name>
  <files>includes/class-membership-fees.php, includes/class-rest-api.php</files>
  <action>
**In `includes/class-membership-fees.php`:**

1. Add `get_entry_discount_config(?string $season = null): array` method (follow `get_family_discount_config` pattern exactly). Store in `rondo_entry_discount_{season}` option. Default config should match current hardcoded behavior:
```php
$defaults = [
    'periods' => [
        [ 'start_month' => 7, 'end_month' => 9, 'discount_percent' => 0 ],
        [ 'start_month' => 10, 'end_month' => 12, 'discount_percent' => 25 ],
        [ 'start_month' => 1, 'end_month' => 3, 'discount_percent' => 50 ],
        [ 'start_month' => 4, 'end_month' => 6, 'discount_percent' => 75 ],
    ],
];
```
Each period: `start_month` (1-12), `end_month` (1-12), `discount_percent` (0-100). The discount_percent represents how much discount they GET (so 75% discount = they pay 25% = 0.25 prorata).

2. Add `save_entry_discount_config(array $config, string $season): bool` method (follow `save_family_discount_config` pattern).

3. Modify `get_prorata_percentage()` to use configured periods instead of hardcoded quarters:
   - Load config via `$this->get_entry_discount_config($season)`
   - Keep the early returns for null date, invalid date, and joined-before-season (return 1.0)
   - For members who joined during the season, find the matching period by month and return `(100 - discount_percent) / 100`
   - If no period matches the month, return 1.0 (no discount — safe fallback)

**In `includes/class-rest-api.php`:**

4. In `get_membership_fee_settings()`: Add `'entry_discount' => $membership_fees->get_entry_discount_config($current_season)` to both current_season and next_season response objects.

5. In `update_membership_fee_settings()`:
   - Read `$entry_discount = $request->get_param('entry_discount');`
   - Add `$entry_validation = $this->validate_entry_discount_config($entry_discount);` and merge into errors/warnings
   - Save if not null: `$membership_fees->save_entry_discount_config($entry_discount, $season);`
   - Add `entry_discount` to both season objects in the response

6. Add `validate_entry_discount_config($config)` private method (follow `validate_family_discount_config` pattern):
   - null/missing is valid (defaults used)
   - Must be array with 'periods' key
   - Each period must have start_month (1-12), end_month (1-12), discount_percent (0-100)
   - Warn if periods don't cover all 12 months
   - Error if months overlap between periods

7. In `copy_season_categories()`:
   - Add: `$source_entry_discount = $membership_fees->get_entry_discount_config($from_season);`
   - Add: `$membership_fees->save_entry_discount_config($source_entry_discount, $to_season);`
   - Add `entry_discount` to both season objects in the response

8. Update ALL places in the settings response that return season data to include `entry_discount` (there are 4: GET handler, POST handler, copy-season handler — each returns both seasons).
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run lint && npm run build</automated>
  </verify>
  <done>
    - get_prorata_percentage() reads from configurable periods instead of hardcoded quarters
    - Default config produces identical results to old hardcoded behavior
    - Settings GET endpoint returns entry_discount for both seasons
    - Settings POST endpoint accepts and saves entry_discount
    - Copy-season copies entry_discount config
    - Validation rejects invalid period configs
  </done>
</task>

<task type="auto">
  <name>Task 2: Frontend — EntryDiscountSection in FeeCategorySettings</name>
  <files>src/pages/Settings/FeeCategorySettings.jsx</files>
  <action>
1. Create `EntryDiscountSection` component in FeeCategorySettings.jsx (follow `FamilyDiscountSection` pattern). Props: `{ discountConfig, onSave, isSaving }`.

2. The component renders a card (use amber-50 bg to differentiate from blue-50 family discount card) with:
   - Title: "Instapkorting" with description explaining that members who join during the season get a discount based on when they join
   - A list of configurable periods, each with:
     - Start month dropdown (Januari through December, value 1-12)
     - End month dropdown (same)
     - Discount percentage input (0-100, number input with % suffix)
   - "Periode toevoegen" button to add a new period row
   - Delete button (Trash2 icon) on each period row to remove it
   - "Opslaan" button (only enabled when dirty) and "Herstel standaard" reset button
   - Dutch month names for the dropdowns: ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December']

3. Local state: `periods` array synced from `discountConfig.periods` via useEffect (like FamilyDiscountSection syncs from props). Track `isDirty`.

4. Reset handler sets periods back to the 4 default quarterly periods (matching backend defaults).

5. Save handler calls `onSave({ periods })` with the current periods array.

6. Add a separate `entryDiscountMutation` in the main FeeCategorySettings component (follow `discountMutation` pattern exactly):
   ```jsx
   const entryDiscountMutation = useMutation({
     mutationFn: async ({ entry_discount, season }) => {
       const response = await prmApi.updateMembershipFeeSettings({ entry_discount }, season);
       return response.data;
     },
     // same onSuccess/onError/onSettled as discountMutation but with message 'Instapkorting opgeslagen'
   });
   ```

7. Add `handleEntryDiscountSave` handler (follow `handleDiscountSave` pattern):
   ```jsx
   const handleEntryDiscountSave = (entryDiscountConfig) => {
     clearMessages();
     entryDiscountMutation.mutate({ entry_discount: entryDiscountConfig, season: activeSeasonKey });
   };
   ```

8. Extract `activeEntryDiscount` from season data:
   ```jsx
   const activeEntryDiscount = activeSeason?.entry_discount || { periods: [...defaults] };
   ```

9. Render `<EntryDiscountSection>` right after `<FamilyDiscountSection>` (before the recalculate button), passing `discountConfig={activeEntryDiscount}`, `onSave={handleEntryDiscountSave}`, `isSaving={entryDiscountMutation.isPending}`.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run lint && npm run build</automated>
  </verify>
  <done>
    - EntryDiscountSection renders in Contributie settings tab below family discount
    - Each period shows start month, end month, and discount percentage
    - Periods can be added and removed
    - Saving persists config via REST API
    - Reset button restores default quarterly periods
    - Season switching loads correct config for each season
  </done>
</task>

</tasks>

<verification>
1. Build passes: `npm run build` succeeds
2. Lint passes: `npm run lint` succeeds
3. Deploy to production and verify:
   - Navigate to Finance > Instellingen > Contributie tab
   - See Instapkorting section with 4 default periods matching current hardcoded values
   - Edit a period's discount percentage, save, verify it persists on page reload
   - Add/remove periods, verify save works
   - Switch seasons, verify independent config per season
   - Use "Herstel standaard" to reset to defaults
4. Verify fee calculation still works correctly by checking a member with pro-rata in the Contributie overview
</verification>

<success_criteria>
- Instapkorting periods are configurable per season via admin UI
- Default configuration produces identical results to previous hardcoded behavior
- Copy-season includes instapkorting config
- Pro-rata fee calculation uses configured periods
- All existing invoices and fee calculations remain correct (backward compatible defaults)
</success_criteria>

<output>
After completion, create `.planning/quick/125-make-instapkorting-configurable-per-seas/125-SUMMARY.md`
</output>
