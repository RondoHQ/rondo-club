# Feature Research

**Domain:** User Accounts & Profiles — WordPress Club Management System
**Researched:** 2026-02-20
**Confidence:** HIGH (based on deep codebase analysis + verified WordPress core API behavior)

## Context

This research covers only NEW features needed for user provisioning, capability mapping, profile management, and admin access blocking. The following are already built and out of scope:

- WordPress authentication with session + REST nonce (X-WP-Nonce header)
- User roles: Administrator and four Rondo roles (rondo_user, rondo_fairplay, rondo_vog, rondo_bestuur)
- Custom capabilities: financieel, fairplay, vog (declared in `UserRoles::ROLES`)
- Sportlink member data synced to `person` CPT via rondo-sync (people, teams, functions, committees)
- Commissie work history synced (MemberFunctions, MemberCommittees from Sportlink API)
- Email delivery via Lettermint (wp_mail() routing, EU-compliant)
- Admin settings pages (VOG, contributie, finance, roles classification)
- Settings > Admin > Gebruikers tab (exists in UI, current functionality unknown — must verify before building)

The Sportlink "Functies" (job functions like Penningmeester, Voorzitter, Secretaris) are already downloaded into the rondo-sync SQLite database and synced to commissie work history records. The **gap** is: none of this Sportlink data currently triggers WordPress user account creation or capability assignment.

---

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Dependency on Existing |
|---------|--------------|------------|------------------------|
| Create WP user from person record | Admin needs a button to turn a Sportlink member into a Rondo user — no manual WP admin visit. Creating users via wp-admin is unfamiliar to non-technical admins and bypasses the Rondo workflow. | MEDIUM | Requires: `person` CPT (exists), email in ACF `contact_info` repeater (exists), `rondo_user` role (exists). New: REST endpoint `POST /rondo/v1/users`, Settings > Gebruikers tab enhancement. |
| Welcome email with password-set link | New users must receive a branded email with a link to set their password. WordPress's default `wp_new_user_notification()` is plain-text and admin-branded — not appropriate for members receiving their first club communication. | LOW | Uses WordPress core: `get_password_reset_key()` + `wp_login_url()` pattern. Email sent via wp_mail() → routed through Lettermint. Must suppress default WP notification via `wp_send_new_user_notification_to_user` filter. |
| Functies → capability mapping configuration | Admin must be able to define: "Penningmeester = financieel capability", "Voorzitter = fairplay + financieel". Without this, capabilities must be set manually per user — not scalable for 500 members. | MEDIUM | Requires: Sportlink functies already in rondo-sync SQLite (exists). New: WordPress options to store mapping (JSON), Settings admin subtab for mapping UI, REST endpoint to save/read mapping. |
| Auto-assign capabilities from Sportlink functies on sync | When rondo-sync runs and a member's functies change, that member's WP user capabilities update automatically. Without auto-sync, the mapping configuration is useless — admin would still need to manually update users. | HIGH | Requires: Functies mapping config (new), member's WP user linked to person record (new — user_id stored on person post meta), rondo-sync step to call Rondo Club REST API after commissie sync. |
| Manual capability override per user | Some members need a capability that their Sportlink functies don't warrant (e.g., an informal treasurer helper). The mapping is the rule; the override is the exception. | LOW | Uses WordPress core `WP_User::add_cap()` / `remove_cap()` — persists to wp_usermeta `wp_capabilities` key. UI in Settings > Gebruikers user detail view. |
| Block wp-admin access for non-admin users | rondo_user, rondo_fairplay, rondo_vog, rondo_bestuur must never see the WordPress admin panel. They should be redirected to the Rondo SPA home when accessing /wp-admin/. | LOW | WordPress `admin_init` hook + `DOING_AJAX` exception. Already partially implied by the custom roles having minimal capabilities, but the redirect is not currently implemented. |
| In-app password change | Users must be able to change their password from within the Rondo SPA. They have no reason to visit wp-login.php and should not need to. | MEDIUM | New: React Profile page (Settings > Profiel tab, not a separate route). New: REST endpoint `POST /rondo/v1/users/me/password`. Uses `wp_set_password()`. Requires current-password verification to prevent CSRF. After password change: WP destroys session → must re-authenticate (warn user). |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Dependency on Existing |
|---------|-------------------|------------|------------------------|
| Functies-to-capability mapping UI in Settings | Admin configures "Penningmeester = financieel" once, not per-user. This is a governance feature — the system enforces club structure automatically rather than requiring individual admin attention for each board member change. | MEDIUM | Requires: Functies list from rondo-sync DB readable by Rondo Club (via existing WP options or new REST endpoint to expose known functies). UI is a checkbox-matrix: functies as rows, capabilities (financieel, fairplay, vog) as columns. |
| Link WP user to person record (bidirectional) | When a WP user is created from a person record, both sides know about each other: person stores `_wp_user_id`, WP user stores `_linked_person_id`. This enables auto-syncing capabilities and eventually showing "your person record" in-app. | LOW | `person` CPT (exists), user meta API (exists). Pattern already exists for the profile link feature (Settings > Weergave > Profielkoppeling links user to person record — but is user-initiated, not admin-provisioned). The new admin-provisioned flow must set the same meta keys to be compatible. |
| Capability sync reuses existing rondo-sync pipeline | rondo-sync already runs daily (`sync-functions.js` pipeline). Adding a capability-sync step at the end of that pipeline means capabilities update automatically without any new scheduler. The infrastructure cost is near-zero. | LOW | rondo-sync `pipelines/sync-functions.js` (exists). New: step `submit-rondo-club-user-capabilities.js` that reads member functies from SQLite, reads mapping config from Rondo Club REST API, and calls `PUT /rondo/v1/users/{id}/capabilities`. |
| Respects manual overrides on auto-sync | When auto-sync runs, it must not overwrite capabilities that were manually set. An admin who manually granted a user the `financieel` capability should not have it removed on the next sync. Track: which capabilities are "functies-driven" vs "manual-override". | MEDIUM | Requires: per-user meta flag per capability: `_cap_source_{cap}` = `functies` or `manual`. On sync: only update capabilities where source is `functies`. Leave `manual` overrides untouched. |

### Anti-Features (Explicitly Do Not Build)

| Anti-Feature | Why Requested | Why Problematic | What to Do Instead |
|--------------|---------------|-----------------|-------------------|
| Self-registration (public signup) | "Let members sign up themselves" | Rondo Club is a private club management tool — self-registration would require email verification, approval flow, spam protection, GDPR consent collection, and invite-only gating. None of this complexity is in scope. Sportlink is the source of truth for who is a member. | Admin provisions users from existing person records. No public registration page. |
| Password visible in welcome email | "Just send them a generated password" | Sending plaintext passwords via email is a security anti-pattern. Any email security breach (member forwards email) exposes the password permanently. | Send a password-set link (get_password_reset_key() URL). Link is single-use and expires per WordPress core behavior (24 hours by default). |
| "Remember me" for WP admin blocked users | "Some users occasionally need wp-admin for plugin X" | If a rondo_user or rondo_bestuur user needs wp-admin access, they need an administrator account — not a work-around. The redirect is intentional. | User gets an administrator account if they genuinely need wp-admin. The rondo_bestuur role is for club board members managing club data, not site administration. |
| Email username-based login (change login to email) | "Our members don't know their WordPress username" | WordPress username is internal. Members never log in via wp-login.php directly — they use the SPA login form or go via the set-password link. Changing login field to email adds complexity with no benefit for this use case. | Keep WordPress username login. Generate usernames from member names (firstname.lastname pattern). Welcome email shows username. |
| Role hierarchy (rondo_bestuur inherits from rondo_fairplay, etc.) | "Board members should automatically get all sub-capabilities" | WordPress role capabilities are flat — a role has the capabilities explicitly listed. The ROLES constant already handles this correctly (rondo_bestuur gets `['fairplay', 'vog', 'financieel']`). The Functies mapping should map to capabilities, not to roles, to avoid combinatorial role complexity. | Map functies to individual capabilities (fairplay, vog, financieel). User's role stays rondo_user. Extra capabilities are added/removed per-user. This matches the existing pattern where roles have base capability sets and users can have overrides. |
| Bulk user creation (import CSV of users) | "We have 50 board members to provision" | Sportlink is the source of truth. Bulk-creating users from CSV bypasses the Sportlink-to-person sync chain and creates users without the knvb-id link needed for auto-sync to work. | Provision from person records (which have knvb-id already). If 50 users need creating, the admin creates them one by one from the Gebruikers tab — each takes ~10 seconds. |
| User profile photo (avatar) | "Show the member's photo from Sportlink on their profile" | Sportlink photos are synced to the person CPT (via `upload-photos-to-rondo-club.js`). WP user accounts don't have photo fields. Syncing person photos to WP user avatars requires custom avatar code, Gravatar conflicts, and storage management. | The person record already has the photo. The profile page shows only account-management fields (password change). The photo is visible when admin navigates to the person record. |

---

## Feature Dependencies

```
Person Record (exists, has email via ACF contact_info, has knvb-id)
    └──required by──> Create WP User (must have email to create user account)
    └──required by──> Link user ↔ person (person stores _wp_user_id, user stores _linked_person_id)

Create WP User (new REST endpoint POST /rondo/v1/users)
    └──triggers──> Welcome Email with password-set link
    └──enables──> In-App Password Change (user now has WP account to change)
    └──enables──> Auto-Capability Sync (only works if user exists and is linked to person)

Functies → Capability Mapping Config (new WordPress options + Settings UI)
    └──required by──> Auto-Assign Capabilities on Sync (rondo-sync reads config from Rondo Club API)
    └──enables──> Manual Override (override modifies same capability keys the mapping manages)

Link user ↔ person
    └──required by──> Auto-Capability Sync (rondo-sync needs to know which WP user to update for each knvb-id)
    └──compatible with──> Profile link (Settings > Weergave > Profielkoppeling uses same meta keys)

Auto-Assign Capabilities from Functies (rondo-sync step)
    └──respects──> Manual Override Flag (_cap_source_{cap} meta)
    └──requires──> Functies → Capability Mapping Config
    └──requires──> Link user ↔ person

Block wp-admin Access
    └──independent──> All other features (pure admin_init hook, no dependencies)

In-App Password Change
    └──requires──> Create WP User (need a WP account to change password)
    └──adds to──> Settings page (new Profile/Account tab or sub-section)
```

### Dependency Notes

- **Person record must have an email:** If a person has no email in their `contact_info` ACF repeater, they cannot get a WP account. The REST endpoint must return a clear error rather than creating an accountless user.
- **Username generation must be deterministic:** If admin runs "create user" twice for the same person, the second call must detect the existing user (by email or linked person meta) and return an error, not create a duplicate.
- **Profile link (existing) and admin provisioning (new) must use the same meta keys:** `_linked_person_id` on WP user and `_wp_user_id` on person post. This prevents two parallel systems for the same relationship.
- **Auto-sync only runs if knvb-id is on the person record:** Without `knvb-id`, rondo-sync cannot look up functies in its SQLite database. Person records created manually (not via Sportlink sync) won't get auto-capability updates — this is acceptable and expected.
- **Password change logs out the user in the same session:** WordPress cookies are based on password hash. `wp_set_password()` invalidates all sessions. The React SPA must show a warning before the submit, and after submitting must redirect to the login page.

---

## MVP Definition

### Launch With (this milestone)

- [ ] **Create WP user from person record** — REST endpoint `POST /rondo/v1/users`. Takes `person_id`. Creates WP user (username: firstname.lastname, email from ACF). Assigns `rondo_user` role. Links person ↔ user via post/user meta. Returns error if person has no email, user already exists, or email already taken.
- [ ] **Welcome email with password-set link** — Sends on user creation. Branded template (club name from ClubConfig), includes username and set-password URL. Uses `get_password_reset_key()`. Suppress WP default notification via filter. Delivered via Lettermint.
- [ ] **Functies → capability mapping config** — WordPress option `rondo_functies_capability_map` stores JSON: `{"Penningmeester": ["financieel"], "Voorzitter": ["fairplay", "financieel"]}`. REST endpoints to read/write. Settings admin UI: fetch known functies from rondo-sync data (via WordPress option `rondo_known_functies` set by rondo-sync), display checkbox matrix.
- [ ] **Auto-assign capabilities on functies sync** — New rondo-sync step runs after `submit-rondo-club-work-history.js`. For each member with a linked WP user: read their active functies from SQLite, look up capability map from Rondo Club API, compute required capabilities, call `PUT /rondo/v1/users/{id}/capabilities` to update. Respects manual override flags.
- [ ] **Manual capability override per user** — In Settings > Admin > Gebruikers user detail: show current capabilities with source (functies-driven vs manual), allow admin to grant/revoke capabilities. Mark as manual override (`_cap_source_{cap}` = `manual`) so auto-sync skips those capabilities.
- [ ] **Block wp-admin for non-admin users** — Hook `admin_init`: check `manage_options`, if not admin and not DOING_AJAX → redirect to `home_url()`. Simple and well-established WordPress pattern.
- [ ] **In-app password change** — New tab or section in Settings. Form: current password, new password, confirm. REST endpoint `POST /rondo/v1/users/me/password`. Verifies current password via `wp_check_password()`. Sets new password via `wp_set_password()`. Shows warning about session expiration. After successful change, SPA shows "Please log in again" and clears session.

### Defer to Post-Launch

- [ ] **rondo-sync: expose known functies to Rondo Club** — rondo-sync could write the list of all known Sportlink functies to a WordPress option after each sync. This enables the Settings UI to show a complete list without the admin typing functies manually. Implement after core mapping config is working.
- [ ] **User list in Settings > Gebruikers** — Paginated list of all Rondo users showing name, email, role, linked person, capabilities, last login. Useful for admin oversight but not required for provisioning to work.

---

## Implementation Notes for Roadmap

### REST API Design

**POST /rondo/v1/users**
```json
Request:  { "person_id": 123 }
Response: { "user_id": 456, "username": "jan.jansen", "email": "jan@example.com" }
Errors:   404 person not found | 409 user already exists | 422 no email on person | 422 email taken
```

**PUT /rondo/v1/users/{user_id}/capabilities**
```json
Request:  { "capabilities": { "financieel": true, "fairplay": false }, "source": "functies" }
Response: { "capabilities": { "financieel": {"granted": true, "source": "functies"}, ... } }
```

**POST /rondo/v1/users/me/password**
```json
Request:  { "current_password": "...", "new_password": "..." }
Response: { "success": true }
Errors:   403 current password wrong | 422 new password too weak
```

**GET /rondo/v1/users/capability-map**
**PUT /rondo/v1/users/capability-map**
```json
Body: { "Penningmeester": ["financieel"], "Voorzitter": ["fairplay", "financieel"], "Secretaris": [] }
```

### Username Generation Pattern

```php
function generate_username(string $first_name, string $last_name): string {
    $base = strtolower(
        remove_accents($first_name) . '.' . remove_accents($last_name)
    );
    $base = preg_replace('/[^a-z0-9.]/', '', $base);

    // Ensure uniqueness
    $username = $base;
    $counter  = 1;
    while (username_exists($username)) {
        $username = $base . $counter;
        $counter++;
    }
    return $username;
}
```

### Password Set Link Construction

```php
$key = get_password_reset_key($user);
if (is_wp_error($key)) {
    // handle error
}
$set_password_url = network_site_url(
    "wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login),
    'login'
);
```

The link expires in 24 hours by default (WordPress core). This is sufficient — admin provisions the user, member receives email and sets password on the same day.

### Capability Source Tracking

Store per-capability source in WP user meta:

```php
// Set by auto-sync:
update_user_meta($user_id, '_cap_source_financieel', 'functies');
update_user_meta($user_id, '_cap_source_fairplay', 'functies');

// Set by manual override:
update_user_meta($user_id, '_cap_source_financieel', 'manual');
```

On auto-sync: skip any capability where `_cap_source_{cap}` = `manual`.
On manual override: set `_cap_source_{cap}` = `manual` so future syncs skip it.
If admin removes a manual override: delete the `_cap_source_{cap}` meta, capability reverts to functies-driven on next sync.

### Block wp-admin Pattern

```php
add_action('admin_init', function () {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return; // Allow AJAX from frontend
    }
    if (is_user_logged_in() && !current_user_can('manage_options')) {
        wp_redirect(home_url('/'));
        exit;
    }
});
```

This hook fires after WordPress has authenticated the user but before any admin page renders. The DOING_AJAX exception is critical — the REST API does not use /wp-admin/ but some WP internals do use admin-ajax.php.

### Welcome Email Template Variables

The email template follows the same pattern as VOGEmail (`class-vog-email.php`) using `{variable}` placeholders:

- `{first_name}` — member's first name
- `{username}` — generated WordPress username
- `{set_password_url}` — one-time password-set link
- `{club_name}` — from `ClubConfig::get_club_name()`
- `{site_url}` — WordPress home URL

Default template (Dutch):

```
Beste {first_name},

Je account voor {club_name} is aangemaakt.

Gebruikersnaam: {username}

Stel je wachtwoord in via onderstaande link:
{set_password_url}

Deze link is 24 uur geldig.

Met sportieve groet,
{club_name}
```

### rondo-sync Capability Sync Step

The new step `submit-rondo-club-user-capabilities.js` runs at the end of `sync-functions.js` pipeline (after commissie work history sync). It:

1. Reads all tracked members with `rondo_club_id` from SQLite
2. For each member: fetches their active functies from `member_functions` table
3. Fetches capability map from `GET /rondo/v1/users/capability-map`
4. Computes which capabilities the member should have based on their functies
5. If member has a linked WP user (person post meta `_wp_user_id`): calls `PUT /rondo/v1/users/{id}/capabilities` with `source: "functies"`
6. If member has no linked WP user: skips (no error — member may not have an account yet)
7. Reports: synced, skipped, errors

The skip-and-warn pattern (established in phase 154) applies here: no WP user is a normal case, not an error.

### Settings UI: Functies → Capability Matrix

The mapping UI in Settings > Admin > Gebruikers (new subtab or within existing) renders a table:

| Functie (Sportlink) | Financieel | FairPlay | VOG |
|---------------------|:----------:|:--------:|:---:|
| Penningmeester       | ☑          | ☐        | ☐   |
| Voorzitter          | ☑          | ☑        | ☐   |
| Secretaris          | ☐          | ☐        | ☐   |

Functies list is read from `rondo_known_functies` WordPress option (populated by rondo-sync). Save button calls `PUT /rondo/v1/users/capability-map`.

---

## Ecosystem Patterns: WordPress Capability Management

### How WP_User::add_cap() Works (HIGH confidence — verified via official docs)

- Persists to `wp_usermeta` table under `wp_capabilities` key as a serialized array
- Per-user capabilities are in addition to role capabilities (additive, not replacing)
- `$user->add_cap('financieel', true)` grants; `$user->add_cap('financieel', false)` explicitly denies
- `$user->remove_cap('financieel')` removes from per-user array (reverts to role-level capability)
- Changes persist across requests — no need to recalculate on each page load
- This is the correct pattern for functies-to-capability mapping (role stays `rondo_user`, capabilities added per-user)

### How get_password_reset_key() Works (HIGH confidence — verified via official docs)

- Generates a random 20-character key, hashes it with timestamp, stores in `user_activation_key` DB field
- Returns the unhashed key string for inclusion in the URL
- URL format: `wp-login.php?action=rp&key={key}&login={rawurlencode(username)}`
- Default expiration: 24 hours (configurable via `password_reset_expiration` filter)
- Single-use: WordPress invalidates key after successful password set

### How admin_init Hook Works for Access Control (HIGH confidence — verified via community patterns)

- Fires after `init`, after user is authenticated, before admin page renders
- `DOING_AJAX` check is required — admin-ajax.php is in `/wp-admin/` path but must remain accessible for frontend WordPress features
- The SPA uses REST API (`/wp-json/`), not admin-ajax.php, so this does not affect Rondo's AJAX calls
- Pattern is idiomatic WordPress: used by thousands of plugins and themes

---

## Sources

- WordPress Developer Reference: `WP_User::add_cap()` — https://developer.wordpress.org/reference/classes/wp_user/add_cap/
- WordPress Developer Reference: `WP_User::remove_cap()` — https://developer.wordpress.org/reference/classes/wp_user/remove_cap/
- WordPress Developer Reference: `get_password_reset_key()` — https://developer.wordpress.org/reference/functions/get_password_reset_key/
- WordPress Developer Reference: Roles and Capabilities — https://developer.wordpress.org/plugins/users/roles-and-capabilities/
- Block Dashboard Access for Non-Admins (admin_init pattern): https://jeroensormani.com/block-dashboard-access-non-admins/
- WordPress Core: Send reset password links (WP 5.7+): https://make.wordpress.org/core/2021/02/22/send-reset-password-links-in-wordpress-5-7/
- Disable `wp_new_user_notification` to user via filter `wp_send_new_user_notification_to_user`: https://developer.wordpress.org/reference/hooks/wp_send_new_user_notification_to_user/
- Existing codebase: `includes/class-user-roles.php` — UserRoles::ROLES with capabilities
- Existing codebase: `includes/class-access-control.php` — access control patterns
- Existing codebase: `includes/class-vog-email.php` — email template pattern with {variable} substitution
- Existing codebase: `src/pages/Settings/Settings.jsx` — existing Settings tabs including Admin > Gebruikers subtab
- Existing codebase: `rondo-sync/pipelines/sync-functions.js` — functions sync pipeline structure
- Existing codebase: `rondo-sync/steps/download-functions-from-sportlink.js` — Sportlink functies data model
- Existing codebase: `rondo-sync/steps/prepare-rondo-club-members.js` — member data transformation patterns

---

*Feature research for: User Accounts & Profiles — Rondo Club WordPress sports club management*
*Researched: 2026-02-20*
