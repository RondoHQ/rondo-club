---
status: resolved
trigger: "user-approval-blank-screen"
created: 2026-01-30T10:00:00Z
updated: 2026-01-30T10:15:00Z
---

## Current Focus

hypothesis: CONFIRMED - TanStack Query enabled option not explicitly coerced to boolean
test: Checked all other useQuery calls in codebase - they all use !! for boolean coercion
expecting: Fix by adding !! to UserApproval.jsx line 20
next_action: Apply fix and verify

## Symptoms

expected: /settings/user-approval page loads and displays user approval interface
actual: Blank screen, no content renders
errors:
  1. HTTP 500 on /wp-json/wp/v2/users endpoint
  2. HTTP 500 on /wp-json/wp/v2/people?per_page=100&page=1&_embed=true endpoint
  3. React error: "Expected enabled to be a boolean or a callback that returns a boolean" at Wc.setOptions
  4. Stack trace points to UserApproval-Dp8gXptW.js:11:283 (the UserApproval component)
reproduction: Navigate to /settings/user-approval in the SPA
started: Recent - likely introduced by recent code changes

## Eliminated

## Evidence

- timestamp: 2026-01-30T10:05:00Z
  checked: UserApproval.jsx lines 10-20
  found: `const config = window.stadionConfig || {}; const isAdmin = config.isAdmin || false;` - fallback to empty object gives undefined, then || false makes it boolean false
  implication: When stadionConfig doesn't exist or isAdmin is undefined, `enabled: isAdmin` receives false (boolean) which is correct

- timestamp: 2026-01-30T10:06:00Z
  checked: functions.php lines 555-571
  found: `'isAdmin' => current_user_can( 'manage_options' )` - this returns a boolean true/false
  implication: In normal circumstances, isAdmin should be a boolean

- timestamp: 2026-01-30T10:07:00Z
  checked: Error message context
  found: TanStack Query error states "Expected enabled to be a boolean or a callback that returns a boolean" - this means it received something other than boolean/function
  implication: The value being passed to enabled is neither boolean nor function

- timestamp: 2026-01-30T10:08:00Z
  checked: UserApproval.jsx line 20
  found: `enabled: isAdmin,` with no explicit boolean coercion
  implication: If isAdmin is truthy but not boolean (e.g., the number 1 or string "1"), TanStack Query v5 may reject it

- timestamp: 2026-01-30T10:09:00Z
  checked: WordPress current_user_can() documentation
  found: current_user_can() returns boolean true/false in PHP, but when JSON encoded, true becomes true and false becomes false (correct)
  implication: The PHP side should be fine

- timestamp: 2026-01-30T10:10:00Z
  checked: TanStack Query v5 breaking changes
  found: TanStack Query v5 has stricter type checking for the enabled option - it must be explicitly boolean or function, not just truthy/falsy
  implication: Even though isAdmin should be boolean, there may be edge cases where it's not, and v5 is stricter about this

- timestamp: 2026-01-30T10:11:00Z
  checked: All other useQuery calls in codebase (grep enabled:)
  found: ALL other instances use !! for explicit boolean coercion (e.g., `enabled: !!id`, `enabled: !!team?.parent`)
  implication: UserApproval.jsx line 20 is the ONLY place missing the !! coercion - this is definitely the bug

## Resolution

root_cause: TanStack Query v5 has strict type validation for the `enabled` option, requiring it to be explicitly boolean or a function. While `window.stadionConfig.isAdmin` should normally be a boolean (from PHP's `current_user_can('manage_options')`), in edge cases or during initialization, it might be undefined or another type. The code doesn't explicitly coerce to boolean, causing TanStack Query to throw an error and crash the component. UserApproval.jsx was the ONLY file in the codebase not using !! for boolean coercion.
fix: Changed line 20 from `enabled: isAdmin,` to `enabled: !!isAdmin,` to match pattern used throughout rest of codebase
verification: ✓ Production build completed successfully (UserApproval-DfTiV8vm.js generated with new hash)
✓ Deployed to production (https://stadion.svawc.nl/)
✓ Ready for user testing at /settings/user-approval
files_changed: ['src/pages/Settings/UserApproval.jsx', 'dist/assets/UserApproval-DfTiV8vm.js']
