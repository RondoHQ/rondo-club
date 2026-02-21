---
phase: 205-user-provisioning
verified: 2026-02-20T19:09:07Z
status: passed
score: 16/16 must-haves verified
re_verification: false
---

# Phase 205: User Provisioning Verification Report

**Phase Goal:** Admin can create a WordPress user account from a Sportlink person record with a branded welcome email and bidirectional link
**Verified:** 2026-02-20T19:09:07Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths — Plan 01 (Backend)

| #  | Truth | Status | Evidence |
|----|-------|--------|----------|
| 1  | POST /rondo/v1/people/{id}/provision creates a WordPress user and returns user_id + status | VERIFIED | Route registered at line 989 of class-rest-api.php; provision_user() callback at line 2711 delegates to UserProvisioning::provision() which returns `['status' => 'created', 'user_id' => $user_id, 'message' => ...]` |
| 2  | Provisioning a person with no email returns a clear error, not a broken user | VERIFIED | get_person_email() returns null when no email found; provision() returns WP_Error('no_email', 'Deze persoon heeft geen e-mailadres.', ['status' => 422]) before any user creation |
| 3  | Re-provisioning an already-provisioned person returns already_exists without creating a second user or sending email | VERIFIED | Idempotency check at lines 96-108: reads _rondo_wp_user_id, calls get_userdata(), returns ['status' => 'already_exists', ...] early — no user creation, no email |
| 4  | The new user has rondo_linked_person_id set to the person ID and the person has _rondo_wp_user_id set to the user ID | VERIFIED | Lines 168-169: update_user_meta($user_id, 'rondo_linked_person_id', $person_id) AND update_post_meta($person_id, self::META_USER_ID, $user_id) |
| 5  | The new user has _rondo_knvb_id stored in user meta if the person has a knvb-id | VERIFIED | Lines 172-175: get_field('knvb-id', $person_id) then update_user_meta($user_id, self::META_KNVB_ID, $knvb_id) |
| 6  | The new user receives a branded welcome email with a password-set link | VERIFIED | send_welcome_email() builds set_password_url with get_password_reset_key(), performs {variable} substitution, sends via wp_mail() with custom from via filter pattern |
| 7  | GET /rondo/v1/provisioning/settings returns configurable email template fields | VERIFIED | Route at line 1007 (READABLE), get_provisioning_settings() returns get_settings() array with subject, body, from_email, from_name |
| 8  | POST /rondo/v1/provisioning/settings persists updated template values | VERIFIED | Route at line 1015 (CREATABLE), update_provisioning_settings() calls update_settings() which saves to WP options |
| 9  | Person REST response includes linked_user_id and welcome_email_sent_at fields | VERIFIED | class-rest-people.php lines 547-548: $data['linked_user_id'] and $data['welcome_email_sent_at'] added in add_person_computed_fields() |

### Observable Truths — Plan 02 (Frontend)

| #  | Truth | Status | Evidence |
|----|-------|--------|----------|
| 10 | Admin sees an Account card on a person's detail page showing provisioning status | VERIFIED | AccountCard.jsx exists (119 lines, substantive); PersonDetail.jsx imports and renders it in second column |
| 11 | Admin can click 'Maak account aan' on a person without an account and the button triggers provisioning | VERIFIED | handleProvision() in AccountCard.jsx calls prmApi.provisionUser(personId) on button click |
| 12 | The Account card disables the button and shows a message when the person has no email | VERIFIED | hasEmail check at line 32-34; button disabled={provisioning \|\| !hasEmail} at line 90; help text shown when !hasEmail at line 108 |
| 13 | After provisioning, the card updates to show account-created status without a full page reload | VERIFIED | queryClient.invalidateQueries({ queryKey: ['people', 'detail', String(personId)] }) at line 44 triggers refetch; linked_user_id change switches card to "Account aangemaakt" state |
| 14 | Admin can configure the welcome email subject and body in Settings > Beheer > Welkomstmail | VERIFIED | 'welkomstmail' in ADMIN_SUBTABS at Settings.jsx line 33; WelkomstmailTab component at line 1699 with from_email, from_name, subject, body fields and save handler |
| 15 | Variable placeholders are explained in the Welkomstmail tab so the admin knows what to use | VERIFIED | Settings.jsx line 1774-1776: "Beschikbare variabelen: {first_name}, {login}, {email}, {set_password_url}, {club_naam}" with code formatting |
| 16 | Non-admin users do not see the Account card on person detail | VERIFIED | AccountCard.jsx lines 24-26: `if (!config.isAdmin) { return null; }` early return; PersonDetail also wraps in `{config.isAdmin && ...}` at line 1345 — double guard |

**Score:** 16/16 truths verified

### Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `includes/class-user-provisioning.php` | VERIFIED | 503 lines, Rondo\Users\UserProvisioning class with provision(), send_welcome_email(), generate_username(), get_settings(), update_settings(), filter callbacks, all constants defined |
| `includes/class-rest-api.php` | VERIFIED | Three new routes registered (lines 986-1020), three callback methods (lines 2711-2756), get_users() enriched with linked_person_id/linked_person_name |
| `includes/class-rest-people.php` | VERIFIED | linked_user_id and welcome_email_sent_at added at lines 547-548 in add_person_computed_fields() |
| `src/components/AccountCard.jsx` | VERIFIED | 119 lines, full provisioning state machine, admin guard, query invalidation on success |
| `src/pages/People/PersonDetail.jsx` | VERIFIED | Imports AccountCard at line 25, renders in second column at lines 1344-1347 with isAdmin guard |
| `src/pages/Settings/Settings.jsx` | VERIFIED | WelkomstmailTab component at line 1699, subtab entry at line 33, state/effects/handlers wired |
| `src/api/client.js` | VERIFIED | provisionUser, getProvisioningSettings, updateProvisioningSettings at lines 265-267 |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `class-rest-api.php` | `class-user-provisioning.php` | REST callback instantiates `new \Rondo\Users\UserProvisioning()` and calls provision() | WIRED | Line 2713-2714 |
| `class-user-provisioning.php` | `FunctieCapabilityMap::get_roles_for_functie()` | Role assignment from work_history Functies at provisioning time | WIRED | Line 157: `\Rondo\Config\FunctieCapabilityMap::get_roles_for_functie($job_title)` |
| `class-user-provisioning.php` | `wp_mail()` | Welcome email via wp_mail with filter-based from address | WIRED | Lines 286-295: add_filter wp_mail_from/wp_mail_from_name, call wp_mail(), remove_filter |
| `AccountCard.jsx` | `src/api/client.js` | prmApi.provisionUser() called on button click | WIRED | Line 41: `await prmApi.provisionUser(personId)` in handleProvision() |
| `Settings.jsx` | `src/api/client.js` | prmApi.getProvisioningSettings() and updateProvisioningSettings() | WIRED | Lines 182 and 218 |
| `PersonDetail.jsx` | `AccountCard.jsx` | AccountCard imported and rendered in second column | WIRED | Import at line 25, render at lines 1344-1347 |

### Anti-Patterns Found

None detected. No TODOs, FIXMEs, placeholder comments, or stub implementations found in any new files.

### Commit Verification

All four task commits verified in git log:
- `afde0eff` — feat(205-01): create UserProvisioning service class (503 lines added)
- `3fc82a8a` — feat(205-01): register provisioning REST endpoints and enrich person/user responses
- `4923dba2` — feat(205-02): add AccountCard component and API client provisioning methods
- `a966db42` — feat(205-02): add WelkomstmailTab to Settings and bump version to 29.2.0

Version bump confirmed: style.css Version 29.2.0, package.json "version": "29.2.0", CHANGELOG.md [29.2.0] - 2026-02-20 entry present.

### Human Verification Required

Two items require human testing on production (rondo.svawc.nl):

**1. End-to-end provisioning flow**

Test: Navigate to a person detail page with a known email address. Click "Maak account aan". Observe spinner, then success message, then card updates to "Account aangemaakt" state.

Expected: WordPress user is created, welcome email arrives in mailbox with a working 7-day password-set link, person detail card shows green check with email sent date.

Why human: Email delivery (Lettermint), password reset link validity, and visual state transitions cannot be verified programmatically. Note: Lettermint from-address must be verified before real use (per STATE.md).

**2. Settings > Beheer > Welkomstmail tab**

Test: Navigate to Settings > Beheer > Welkomstmail. Edit the subject and body. Click Opslaan. Reload the page and reopen the tab.

Expected: Changes persist across page reloads. "Opgeslagen" flash appears for ~2 seconds after save.

Why human: Persistence and UI feedback timing cannot be verified without running the app.

### Gaps Summary

No gaps found. All 16 must-haves verified across both plans (backend and frontend). All key links are wired. Artifacts are substantive (no stubs). The phase goal is fully achieved.

---

_Verified: 2026-02-20T19:09:07Z_
_Verifier: Claude (gsd-verifier)_
