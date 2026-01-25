---
phase: 52-settings-ui
plan: 52-FIX
type: fix
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-calendar.php
autonomous: true
---

<objective>
Fix 1 UAT issue from phase 52.

Source: 52-UAT.md
Diagnosed: yes
Priority: 0 blocker, 1 major, 0 minor, 0 cosmetic
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

**Issues being fixed:**
@.planning/phases/52-settings-ui/52-UAT.md

**Original implementation:**
@includes/class-rest-calendar.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix UAT-001 - Google OAuth callback redirect and connection creation</name>
  <files>includes/class-rest-calendar.php</files>
  <action>
**Root Cause:** wp_redirect() + exit pattern doesn't work in REST API callbacks. REST endpoints expect response objects, not redirects via header manipulation.

**Issue:** "When I click 'Connect Google Calendar', I'm correctly taken to a Google oAuth screen with the correct permissions, when I click Allow on that, I'm redirected to the Dashboard, not to the settings page. When I then go to the Settings page -> Calendar tab, it doesn't list an active connection."

**Expected:** After Google OAuth approval, user should be redirected back to Settings > Calendars tab with the new connection visible.

**Fix:**
The OAuth callback endpoint `google_auth_callback` at lines 538-600 uses `wp_redirect()` + `exit` which doesn't work in REST API context. Instead:

1. Change the OAuth callback from a REST API endpoint to a regular WordPress page/rewrite rule endpoint. This allows proper HTTP redirects.

   OR

2. Keep as REST endpoint but return an HTML page with JavaScript redirect:
   - Instead of `wp_redirect()`, return a simple HTML page with `<meta http-equiv="refresh">` or `<script>window.location.href='...'</script>`
   - This ensures the browser actually navigates to the settings page

**Recommended approach:** Option 2 - Return HTML redirect response from REST callback.

Replace all `wp_redirect($url); exit;` patterns in `google_auth_callback()` with:
```php
// Return HTML redirect response
header('Content-Type: text/html');
echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . esc_url($url) . '"></head><body>Redirecting...</body></html>';
exit;
```

Apply this to all redirect points in the callback:
- Line 543 (missing state)
- Line 550 (invalid state)
- Line 559 (nonce verification failed)
- Line 566 (missing code)
- Line 593 (success redirect)
- Line 597 (error redirect)

Also verify the connection is actually being created by checking `STADION_Calendar_Connections::add_connection()` is reached and succeeds.
  </action>
  <verify>
- Deploy to production
- Click "Connect Google Calendar" in Settings
- Complete OAuth flow
- Verify redirect goes to /settings (not Dashboard)
- Verify connection appears in list
  </verify>
  <done>UAT-001 resolved - OAuth callback properly redirects and creates connection</done>
</task>

</tasks>

<verification>
Before declaring plan complete:
- [ ] OAuth callback returns HTML redirect instead of wp_redirect
- [ ] User is redirected to /settings/calendars after OAuth
- [ ] Connection is created and visible in list
- [ ] Build succeeds without errors
</verification>

<success_criteria>
- UAT-001 from 52-UAT.md addressed
- Google OAuth flow works end-to-end
- Ready for re-verification with /gsd:verify-work 52
</success_criteria>

<output>
After completion, create `.planning/phases/52-settings-ui/52-FIX-SUMMARY.md`
</output>
