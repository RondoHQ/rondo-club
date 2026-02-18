---
status: resolved
trigger: "User reports getting an error when trying to save club configuration (club name, accent color, FreeScout URL) from the Settings page. The save fails entirely."
created: 2026-02-05T00:00:00Z
updated: 2026-02-05T00:02:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED - prmApi.post() does not exist; prmApi is a plain object without generic HTTP methods
test: Added named methods to prmApi, updated Settings.jsx to use them, build passes
expecting: Club config save now works correctly
next_action: None - resolved

## Symptoms

expected: After changing club name and color in Settings and saving, the values persist after a page refresh.
actual: The save fails entirely with an error (TypeError: prmApi.post is not a function).
errors: TypeError: prmApi.post is not a function (runtime error in browser)
reproduction: Go to Settings page, change club name/color, click save
started: After phase 145-frontend-color-refactor added Club Configuration section

## Eliminated

- hypothesis: Backend REST route not registered or misconfigured
  evidence: Route is properly registered at rondo/v1/config with GET and POST methods in class-rest-api.php lines 701-725. Permission callback uses check_admin_permission. Handler update_club_config correctly reads params and calls ClubConfig methods.
  timestamp: 2026-02-05T00:00:30Z

- hypothesis: ClubConfig service class has a bug
  evidence: class-club-config.php is straightforward - uses WordPress Options API with proper sanitization. All methods (get/update) are correct.
  timestamp: 2026-02-05T00:00:30Z

- hypothesis: Permission/nonce issue
  evidence: The error never reaches the server - it fails on the frontend before the request is sent. prmApi.post is not a function.
  timestamp: 2026-02-05T00:00:45Z

## Evidence

- timestamp: 2026-02-05T00:00:15Z
  checked: Settings.jsx line 943
  found: Frontend calls `prmApi.post('/config', { club_name, accent_color, freescout_url })`
  implication: This uses a generic .post() method on prmApi

- timestamp: 2026-02-05T00:00:20Z
  checked: src/api/client.js - prmApi object definition (lines 116-317)
  found: prmApi is a plain JavaScript object literal with only named methods (getDashboard, search, etc.). It does NOT have a generic .post() method. Only the raw `api` axios instance (default export) has .post().
  implication: prmApi.post('/config', ...) throws TypeError because .post is undefined on a plain object

- timestamp: 2026-02-05T00:00:25Z
  checked: Grep for prmApi.post across entire src/ directory
  found: Only ONE occurrence: Settings.jsx line 943. All other prmApi usage correctly calls named methods.
  implication: This is the only place in the codebase with this bug pattern

- timestamp: 2026-02-05T00:00:30Z
  checked: Backend REST API registration in class-rest-api.php lines 701-725
  found: Route properly registered: GET for read (check_user_approved), POST for write (check_admin_permission). Handler at lines 2657-2678 correctly processes partial updates.
  implication: Backend is correct - the request never reaches it

- timestamp: 2026-02-05T00:02:00Z
  checked: Build after fix applied
  found: `npm run build` completes successfully with no errors
  implication: Fix compiles and bundles correctly

## Resolution

root_cause: Settings.jsx line 943 calls `prmApi.post('/config', {...})` but prmApi is a plain object literal (defined in src/api/client.js) with only named methods. It does NOT have a generic `.post()` method -- only the raw axios instance `api` (the default export) has `.post()`. This causes a TypeError at runtime, preventing the save request from ever being sent to the backend.
fix: Added `getClubConfig` and `updateClubConfig` named methods to the prmApi object in client.js, and updated Settings.jsx to call `prmApi.updateClubConfig({...})` instead of the non-existent `prmApi.post('/config', {...})`.
verification: Build passes. Fix follows established patterns -- all other prmApi consumers use named methods.
files_changed:
  - src/api/client.js (added getClubConfig and updateClubConfig methods to prmApi)
  - src/pages/Settings/Settings.jsx (changed prmApi.post to prmApi.updateClubConfig)
