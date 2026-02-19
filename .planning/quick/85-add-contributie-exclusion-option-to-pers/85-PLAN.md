---
phase: quick-85
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-post-types.php
  - includes/class-rest-people.php
  - includes/class-membership-fees.php
  - includes/class-rest-api.php
  - src/components/FinancesCard.jsx
autonomous: true

must_haves:
  truths:
    - "A financieel user can see and toggle the 'Uitsluiten van contributie' option on a person's detail page"
    - "A non-financieel user sees no exclusion toggle on the person's detail page"
    - "When a person is excluded, calculate_fee returns null for that person"
    - "When a person is excluded, get_person_fee REST endpoint returns calculable: false with reason 'manually_excluded'"
    - "The exclusion flag persists in post meta and survives page refresh"
  artifacts:
    - path: "includes/class-post-types.php"
      provides: "register_post_meta for _exclude_from_contributie with financieel auth_callback"
    - path: "includes/class-rest-people.php"
      provides: "add_person_computed_fields exposes exclude_from_contributie only to financieel users"
    - path: "includes/class-membership-fees.php"
      provides: "calculate_fee returns null early when _exclude_from_contributie is true"
    - path: "includes/class-rest-api.php"
      provides: "get_person_fee returns calculable:false with reason manually_excluded when flag is set"
    - path: "src/components/FinancesCard.jsx"
      provides: "Toggle rendered only when canAccessFinancieel, updates via useUpdatePerson"
  key_links:
    - from: "src/components/FinancesCard.jsx"
      to: "/wp/v2/people/{id}"
      via: "useUpdatePerson mutation with meta._exclude_from_contributie"
      pattern: "useUpdatePerson|updatePerson"
    - from: "includes/class-membership-fees.php calculate_fee"
      to: "wp_post_meta _exclude_from_contributie"
      via: "get_post_meta check at top of calculate_fee"
      pattern: "get_post_meta.*_exclude_from_contributie"
---

<objective>
Add a per-person opt-out from contributie (membership fee) calculation, visible and editable only by users with the `financieel` capability.

Purpose: Allow finance admins to manually exclude specific people (e.g., honorary members, staff on special arrangements) from the automated fee calculation and invoice generation.
Output: `_exclude_from_contributie` post meta field, REST-exposed with capability gating, fee calculation skip, and a toggle in FinancesCard.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Register meta field and expose via REST with financieel gating</name>
  <files>
    includes/class-post-types.php
    includes/class-rest-people.php
    includes/class-membership-fees.php
    includes/class-rest-api.php
  </files>
  <action>
    **1. `includes/class-post-types.php` — register the meta field**

    In `register_person_post_type()`, after the existing VOG meta fields block (around line 82), add:

    ```php
    // Register contributie exclusion meta field for REST API access.
    // Write access is gated by the 'financieel' capability in the auth_callback.
    register_post_meta( 'person', '_exclude_from_contributie', [
        'type'              => 'boolean',
        'single'            => true,
        'show_in_rest'      => true,
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'auth_callback'     => function () {
            return current_user_can( 'financieel' );
        },
    ] );
    ```

    The `auth_callback` gates writes to financieel users. For reads, WordPress exposes registered meta to all authenticated users by default — the read-gating is handled in step 2 via the `rest_prepare_person` filter.

    **2. `includes/class-rest-people.php` — expose field conditionally in REST responses**

    In `add_person_computed_fields()`, before `$response->set_data( $data )`, add:

    ```php
    // Expose contributie exclusion flag only to users with financieel capability
    if ( current_user_can( 'financieel' ) ) {
        $data['exclude_from_contributie'] = (bool) get_post_meta( $post->ID, '_exclude_from_contributie', true );
    }
    ```

    This means the field is only present in the REST response for financieel users; non-financieel users never see it.

    **3. `includes/class-membership-fees.php` — skip excluded persons in calculate_fee**

    At the very top of `calculate_fee()` (line 605, after the opening brace but before `$leeftijdsgroep = get_field(...)`), add:

    ```php
    // Skip persons manually excluded from contributie
    if ( get_post_meta( $person_id, '_exclude_from_contributie', true ) ) {
        return null;
    }
    ```

    Returning `null` here causes the existing `get_fee_for_person`, `get_fee_for_person_cached`, bulk invoice creation, Google Sheets export, and all other callers to skip this person exactly as they skip persons with no valid fee category.

    **4. `includes/class-rest-api.php` — surface exclusion reason in get_person_fee**

    In `get_person_fee()` (around line 3681), add a check BEFORE the former-member check (before line 3698), so the exclusion takes priority in the response:

    ```php
    // Check if person is manually excluded from contributie
    if ( get_post_meta( $person_id, '_exclude_from_contributie', true ) ) {
        return rest_ensure_response(
            [
                'person_id'  => $person_id,
                'season'     => $season,
                'calculable' => false,
                'reason'     => 'manually_excluded',
                'message'    => 'Persoon is handmatig uitgesloten van contributie.',
            ]
        );
    }
    ```

    This gives the frontend a clear signal to show the "excluded" state in FinancesCard.
  </action>
  <verify>
    ```bash
    cd /Users/joostdevalk/Code/rondo/rondo-club && php -l includes/class-post-types.php && php -l includes/class-rest-people.php && php -l includes/class-membership-fees.php && php -l includes/class-rest-api.php
    ```
    All four files must report "No syntax errors detected".
  </verify>
  <done>
    - `_exclude_from_contributie` meta registered on `person` post type with `financieel` auth_callback
    - `add_person_computed_fields` includes `exclude_from_contributie` boolean only when current user has `financieel` cap
    - `calculate_fee()` returns `null` immediately when `_exclude_from_contributie` is truthy
    - `get_person_fee` REST endpoint returns `{ calculable: false, reason: 'manually_excluded' }` for excluded persons
    - PHP syntax clean on all four files
  </done>
</task>

<task type="auto">
  <name>Task 2: Add exclusion toggle in FinancesCard</name>
  <files>
    src/components/FinancesCard.jsx
  </files>
  <action>
    `FinancesCard` already checks `currentUser?.can_access_financieel` and hides the entire card for non-financieel users (line 82), so the toggle will only ever be seen by financieel users. No additional capability guard is needed in this component.

    **Imports:** Add `useUpdatePerson` to the import from `@/hooks/usePeople`:
    ```js
    import { useUpdatePerson } from '@/hooks/usePeople';
    ```
    Also add `Ban` from lucide-react (ban icon = "excluded") to the existing lucide import line.

    **Hook setup** (after the existing `createInvoice` mutation, around line 63):
    ```js
    const updatePerson = useUpdatePerson();
    const isExcluded = person?.exclude_from_contributie ?? false;

    const handleToggleExclusion = () => {
      updatePerson.mutate({
        id: personId,
        data: { meta: { _exclude_from_contributie: !isExcluded } },
      });
    };
    ```

    Note: `FinancesCard` receives `personId` as a prop, but does not receive the full `person` object. The `exclude_from_contributie` value comes back from the `usePersonFee` hook response (when `feeData?.reason === 'manually_excluded'` we know it's excluded). Handle it this way:

    ```js
    const isExcluded = feeData?.reason === 'manually_excluded';
    ```

    This works even before the meta value is loaded, because `usePersonFee` always fires for any financieel user.

    However, `FinancesCard` currently returns `null` when `!feeData?.calculable` (line 103). To show the exclusion toggle, the component must NOT early-return on non-calculable when the reason is `manually_excluded`. Update the early-return guard at line 102-105:

    ```jsx
    // Don't render if not calculable, UNLESS person is manually excluded (show toggle to re-include)
    if (!feeData?.calculable && feeData?.reason !== 'manually_excluded') {
      return null;
    }
    ```

    **Exclusion state UI** — show a distinct state when `isExcluded`:

    When `isExcluded` is true, render a simplified card body (instead of the full fee breakdown) that shows the exclusion notice and a "Opnemen" button:

    ```jsx
    {isExcluded ? (
      <div className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
        <div className="flex items-center gap-2">
          <Ban className="w-4 h-4 text-gray-400" />
          <span className="text-sm text-gray-500 dark:text-gray-400">Uitgesloten van contributie</span>
        </div>
        <button
          onClick={handleToggleExclusion}
          disabled={updatePerson.isPending}
          className="text-xs text-electric-cyan hover:underline disabled:opacity-50"
        >
          Opnemen
        </button>
      </div>
    ) : (
      /* existing fee breakdown JSX */
    )}
    ```

    **Toggle button when NOT excluded** — add an "Uitsluiten" button at the bottom of the card, below the existing invoice section. Place it after the last `</div>` of the invoice section, still inside the card's root div:

    ```jsx
    {/* Exclusion toggle — financieel users only (entire card is already hidden for others) */}
    <div className="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
      <button
        onClick={handleToggleExclusion}
        disabled={updatePerson.isPending}
        className="flex items-center gap-1 text-xs text-gray-400 hover:text-red-500 dark:hover:text-red-400 disabled:opacity-50 transition-colors"
      >
        <Ban className="w-3 h-3" />
        Uitsluiten van contributie
      </button>
    </div>
    ```

    After the `updatePerson.mutate()` call resolves, `usePersonFee` will be stale — to trigger a refresh, add `onSuccess` to the `updatePerson` mutation call. Since `useUpdatePerson` is a shared hook, instead use `useQueryClient` to invalidate `feeKeys.person(personId)` after the toggle. Add inside `handleToggleExclusion`:

    ```js
    const queryClient = useQueryClient(); // already imported via useMutation

    const handleToggleExclusion = () => {
      updatePerson.mutate(
        { id: personId, data: { meta: { _exclude_from_contributie: !isExcluded } } },
        {
          onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['fees', 'person', personId] });
          },
        }
      );
    };
    ```

    The fee query key is `feeKeys.person(personId)` — check `src/hooks/useFees.js` for the exact key shape and use it. If it is `['fees', 'person', String(personId)]` or similar, match that exactly.
  </action>
  <verify>
    ```bash
    cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build 2>&1 | tail -20
    ```
    Build must complete with no errors and no ESLint violations (pre-commit hook enforces max-warnings: 0).
  </verify>
  <done>
    - `Ban` icon imported from lucide-react
    - `useUpdatePerson` imported and used for toggling
    - Card does NOT early-return when `reason === 'manually_excluded'`
    - Excluded state renders "Uitgesloten van contributie" notice with "Opnemen" button
    - Non-excluded state has "Uitsluiten van contributie" button at card bottom
    - Toggle fires PATCH to `/wp/v2/people/{id}` with `meta._exclude_from_contributie`
    - After toggle, fee query is invalidated so UI refreshes
    - `npm run build` succeeds with zero ESLint errors
  </done>
</task>

</tasks>

<verification>
1. PHP syntax check passes on all four modified PHP files
2. `npm run build` succeeds (no ESLint errors, no compile errors)
3. Manual test (production): as a financieel user, open a person detail, observe "Uitsluiten van contributie" at bottom of FinancesCard → click → card switches to excluded state → refresh page → still excluded → click "Opnemen" → fee breakdown returns
4. Manual test: as a non-financieel user, open same person detail → FinancesCard is entirely hidden (existing behaviour unchanged)
5. Verify via REST: `GET /rondo/v1/fees/person/{id}` for excluded person returns `{ "calculable": false, "reason": "manually_excluded" }` as financieel user
6. Verify bulk invoice creation skips excluded person (no invoice created in bulk flow)
</verification>

<success_criteria>
- Financieel user can exclude a person from contributie via a toggle in FinancesCard; the change persists and the fee card reflects the excluded state
- Non-financieel users see no exclusion control and no change to existing behaviour
- `calculate_fee()` returns null for excluded persons, affecting all downstream callers (bulk invoicing, Google Sheets export, Contributie list)
- REST fee endpoint signals `reason: 'manually_excluded'` for clear frontend differentiation from "no valid category"
</success_criteria>

<output>
After completion, create `.planning/quick/85-add-contributie-exclusion-option-to-pers/85-SUMMARY.md` using the summary template.
</output>
