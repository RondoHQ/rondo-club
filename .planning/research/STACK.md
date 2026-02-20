# Stack Research

**Domain:** User provisioning, Functie-based capability mapping, in-app profile management, welcome emails, WP admin blocking — additions to existing WordPress theme + React SPA
**Researched:** 2026-02-20
**Confidence:** HIGH

---

## Summary Verdict

No new npm packages or Composer dependencies are needed. Every required capability is already in the existing stack. This milestone is pure logic work inside existing patterns.

---

## Recommended Stack

### Core Technologies (unchanged — already present)

| Technology | Version | Purpose | Why It Covers This Milestone |
|------------|---------|---------|------------------------------|
| WordPress user system | 6.0+ | User storage, role/capability management | `wp_create_user`, `wp_set_password`, `wp_generate_password`, `user_can()`, `add_role`, `add_cap` are all native — no library needed |
| ACF Pro | current | Complex field storage on person posts | `get_field()` / `update_field()` already used for Sportlink fields including `knvb-id`; user preferences stored via native `update_user_meta` |
| wp_mail + Lettermint | current | Welcome email delivery | Already used for VOG emails (`VogEmail`), installment emails (`InstallmentEmailSender`), invoice emails (`InvoiceEmailSender`) — exact same pattern applies |
| React 18 + React Hook Form 7 | as in package.json | In-app profile page | `react-hook-form` already in dependencies; `prmApi` client already handles user endpoints |
| rondo-sync Node.js CLI | current | Sportlink Functie data delivery to Rondo Club | `download-functions-from-sportlink.js` already downloads `MemberFunctions` (function_description, is_active) and stores to SQLite; pipeline already exists |

### Supporting Libraries (unchanged — already present)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| TanStack Query 5 | ^5.17.0 | Server state for profile page | Use for `GET /rondo/v1/user/profile` — same as existing notification-channels, dashboard-settings hooks |
| Lucide React | ^0.309.0 | Profile page icons | Already used everywhere; user/key/shield icons available |
| Tailwind CSS v4 | ^4.1.18 | Profile page styling | Same CSS-first `@theme` tokens already in use |
| Axios (via prmApi) | ^1.6.0 | API calls from React | `prmApi` client already injects WP nonce; add profile methods there |

---

## What Is Already Built (Do Not Rebuild)

| Capability Needed | Existing Mechanism | Location |
|-------------------|--------------------|----------|
| Download Sportlink Functies | `runFunctionsDownload()` | rondo-sync: `steps/download-functions-from-sportlink.js` |
| Store Functies in SQLite | `upsertMemberFunctions()`, `sportlink_member_functions` table (knvb_id, function_description, is_active) | rondo-sync: `lib/rondo-club-db.js` |
| User role registration | `UserRoles::register_role()`, `UserRoles::ROLES` constant with per-role extra caps | `includes/class-user-roles.php` |
| User capability checks | `user_can($id, 'fairplay')`, `user_can($id, 'financieel')`, `user_can($id, 'vog')` | Already used in `AccessControl` + REST permission callbacks |
| User meta storage | `update_user_meta` / `get_user_meta` with `rondo_` prefix | Used for notification channels, dashboard settings, linked person ID |
| Person → User link | `rondo_linked_person_id` user meta; exposed as `currentUserPersonId` in `window.rondoConfig` | `functions.php` line 620 |
| Email template with variable substitution | Pattern in `VogEmail`, `InvoiceEmailSender`, `InstallmentEmailSender` using `str_replace()` on stored HTML template | `includes/class-vog-email.php`, etc. |
| REST endpoint pattern | `register_rest_route` in `Api::register_routes()` | `includes/class-rest-api.php` |
| Admin-only REST endpoints | `check_admin_permission()` in `Base` | `includes/class-rest-base.php` |
| Password generation | `wp_generate_password()` | WordPress core |
| User creation | `wp_create_user()` / `wp_insert_user()` | WordPress core |
| Admin bar hiding for non-admins | `show_admin_bar(false)` for frontend | `functions.php` line 703 |
| Existing user list + delete | `GET /rondo/v1/users` and `DELETE /rondo/v1/users/{id}` | `includes/class-rest-api.php` lines 2614–2660 |

---

## What Needs to Be Added (No New Libraries)

### PHP — Rondo Club

| New Component | What It Does | Implementation Pattern |
|---------------|-------------|------------------------|
| `class-user-provisioner.php` | Creates WP user from Sportlink member data, assigns role, sends welcome email, links to `person` post via `rondo_linked_person_id` | New class in `includes/`, loaded in `functions.php`. Uses `wp_create_user()` + `wp_set_role()` + `update_user_meta()`. Call from new REST endpoint. |
| `class-functie-capability-mapper.php` | Maps Sportlink `function_description` strings to Rondo capabilities (`club-admin`, `fairplay`, `financieel`, `vog`) | New class in `includes/`. Reads mapping config from Options API (`rondo_functie_capability_map` as JSON). Applied when rondo-sync pushes Functie data via REST. |
| New `club-admin` capability | Third Rondo custom capability alongside `fairplay` and `financieel` | Add to `UserRoles::ROLES` for `rondo_bestuur` (or new `rondo_club_admin` role), and `add_cap()` on administrator role — same pattern as lines 73–79 of `class-user-roles.php`. |
| REST: `POST /rondo/v1/users/provision` | Admin-triggered or rondo-sync-triggered user account creation for a given `knvb_id` | Add to `Api::register_routes()`. Permission: `check_admin_permission`. Body: `{knvb_id, email, send_welcome_email}`. |
| REST: `GET /rondo/v1/user/profile` | Current user's own profile data (display_name, email, linked person name) | Add to `Api::register_routes()`. Permission: `is_user_logged_in`. Pattern mirrors `/user/notification-channels`. |
| REST: `PATCH /rondo/v1/user/profile` | Current user updates display name, email, and/or password | Permission: `is_user_logged_in`. Uses `wp_update_user()`. Password change requires current password verification. |
| REST: `PATCH /rondo/v1/users/{id}/capabilities` | Admin sets custom capabilities for a specific user | Permission: `check_admin_permission`. Uses `$user->add_cap()` / `$user->remove_cap()` per capability. |
| REST: `POST /rondo/v1/users/update-functie-capabilities` | rondo-sync pushes active Functies for a `knvb_id`; server computes capability delta and applies | Called by new rondo-sync step. Maps Functie strings via `FunctieCapabilityMapper`. Finds WP user by `rondo_linked_person_id` → `knvb-id` ACF field. |
| `class-welcome-email.php` | Sends welcome email with temporary password + login URL | New class in `includes/`. Same pattern as `VogEmail`: Options API for template HTML, `wp_mail()` for delivery, `str_replace()` for `{voornaam}`, `{loginurl}`, `{tijdelijk_wachtwoord}` placeholders. |
| WP admin block for `rondo_user` roles | Prevent non-admin Rondo users from accessing `/wp-admin/` | One `admin_init` hook in `functions.php`: if `is_admin()` and not `manage_options` and not `wp_doing_ajax()`, redirect to `home_url('/')`. |

### rondo-sync — Node.js

| What | How |
|------|-----|
| Submit active Functies per user to Rondo Club | New step `submit-rondo-club-user-capabilities.js`. After functions download, for each member with `rondo_club_id` and active functions, call `POST /rondo/v1/users/update-functie-capabilities` with `{knvb_id, active_functions: [function_description, ...]}`. |
| Integrate into `sync-functions.js` pipeline | Add step after existing free-fields-sync step. Same RunTracker pattern. |

### React — Frontend

| New Component | What It Does | Pattern |
|---------------|-------------|---------|
| Profile tab in Settings (`ProfileTab.jsx`) | Display name, email, change password form for current user | Use `react-hook-form` (already installed). `prmApi` calls to `GET/PATCH /rondo/v1/user/profile`. New TABS entry in `Settings.jsx`. |
| Capabilities column in admin users list | Show active capabilities per user; allow admin to toggle them | Extend existing admin/users subtab. Update `prmApi.getUsers()` — server adds `capabilities` array to response. Toggle calls `PATCH /rondo/v1/users/{id}/capabilities`. |
| Functie-to-capability mapping config UI | Admin configures which Functie strings map to which capability | New subtab in admin section. Simple key-value list stored via Options API. Fetched from new `GET /rondo/v1/admin/functie-map` endpoint. |

---

## Installation

No new packages needed. All capabilities come from existing dependencies.

```bash
# Nothing to install — all required libraries already in package.json and WordPress core
```

---

## Alternatives Considered

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| WordPress native `wp_create_user` + per-user `add_cap()` | External identity provider (Auth0, Keycloak) | Overkill for a club of hundreds; would break the existing WP session + REST nonce auth model |
| Options API for Functie→capability mapping | ACF field group for mapping | Options API is correct for site-wide config without a post entity; ACF is for entity data |
| Config-driven string matching for Functie→capability | Hard-coded function descriptions | Config-driven lets admin adjust mappings when Sportlink function names change without a code deploy |
| `add_cap()` / `remove_cap()` on individual users | Reassigning roles wholesale | Per-user capability grants allow mixing: user can have `fairplay` from their Functie without being `rondo_bestuur`; roles stay semantically clean |
| `admin_init` redirect hook for WP admin blocking | Must-use plugin | Theme hook is sufficient; consistent with existing `is_admin()` checks already in `functions.php` |
| `wp/v2/users` for provisioning | Custom `/rondo/v1/users/provision` | WP REST users endpoint requires `edit_users` capability; returns WP default schema; hard to extend with Sportlink context |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `wp/v2/users` endpoint for provisioning or capability management | Requires `edit_users` capability; returns WordPress default user schema; not extensible with Sportlink-domain fields | Custom `/rondo/v1/users/` endpoints — already the established pattern in this codebase |
| New Composer packages for email templating | WordPress wraps PHPMailer via `wp_mail()`; Lettermint handles SMTP | `wp_mail()` with HTML content-type and `str_replace()` — same as `VogEmail` and `InvoiceEmailSender` |
| JWT or separate OAuth for profile/provisioning endpoints | All existing Rondo REST endpoints use WP session + `X-WP-Nonce`; changing auth model for new endpoints creates inconsistency | WordPress nonce via existing `prmApi` Axios client |
| Custom DB table for provisioning state | Violates Rule 0 | WordPress user meta with `rondo_` prefix (e.g., `rondo_provisioned_at`, `rondo_welcome_email_sent`) |
| Redux or Zustand for profile page state | Profile is a simple form; no shared state needed | `react-hook-form` (already installed) + TanStack Query for server sync |
| Generating a "set your own password" email flow | Adds a round-trip before user can log in; complexity for marginal UX gain | Generate temporary password via `wp_generate_password()`, send in welcome email, user changes it on first login via profile tab |

---

## Stack Patterns by Variant

**If the Functie→capability mapping needs to be club-configurable:**
- Store as JSON in `rondo_functie_capability_map` Options API key
- Expose via `GET /rondo/v1/admin/functie-map` (admin only) and `PUT` for updates
- React renders a simple editable list in the admin subtab
- Default mapping hardcoded in `FunctieCapabilityMapper` as fallback

**If welcome email must match club branding:**
- Follow `VogEmail` pattern exactly: `OPTION_TEMPLATE` in Options API, admin edits template HTML
- Supported placeholders: `{voornaam}`, `{loginurl}`, `{tijdelijk_wachtwoord}`
- No new library — `str_replace()` on the template string

**If rondo-sync should auto-provision (not just sync capabilities):**
- Add `--provision` CLI flag to `sync-functions.js` pipeline
- Gate on explicit opt-in per run to avoid accidental mass user creation
- `UserProvisioner::provision_from_knvb_id()` checks `get_user_by('email', $email)` before creating

---

## Version Compatibility

| Component | Compatible With | Notes |
|-----------|-----------------|-------|
| `wp_create_user` / `wp_insert_user` | WordPress 6.0+ | Stable API since WP 2.0; no compatibility concerns |
| `$user->add_cap()` / `$user->remove_cap()` | WordPress 6.0+ | Stored in `wp_usermeta` as `wp_capabilities` serialized array; persists across role changes |
| `react-hook-form` ^7.49.0 | React 18.2.0 | Already installed and used; no version conflict |
| `sportlink_member_functions` SQLite table | rondo-sync current | Already created by `rondo-club-db.js` migration; contains `function_description` and `is_active` columns |

---

## Integration Points

### rondo-sync → Rondo Club (new data flow for capabilities)

```
sync-functions.js pipeline (existing)
  step 1: download-functions-from-sportlink.js (existing — downloads MemberFunctions)
  step 2: submit-rondo-club-commissies.js (existing)
  step 3: submit-rondo-club-commissie-work-history.js (existing)
  step 4: sync-free-fields-to-rondo-club.js (existing)
  step 5: submit-rondo-club-user-capabilities.js (NEW)
    → for each member with rondo_club_id + active functions:
      POST /rondo/v1/users/update-functie-capabilities
        { knvb_id, active_functions: ["Wedstrijdsecretaris", ...] }
```

### Rondo Club REST → WordPress user system

```
POST /rondo/v1/users/provision
  → UserProvisioner::provision()
    → get_user_by('email', $email)      — skip if already exists
    → wp_create_user($login, $temp_pass, $email)
    → wp_set_role($user_id, 'rondo_user')
    → update_user_meta($user_id, 'rondo_linked_person_id', $person_id)
    → update_user_meta($user_id, 'rondo_provisioned_at', current_time('mysql'))
    → WelcomeEmail::send($user_id, $temp_pass)

POST /rondo/v1/users/update-functie-capabilities
  → look up WP user by knvb_id (query persons by ACF 'knvb-id', get rondo_linked_person_id)
  → FunctieCapabilityMapper::resolve($active_functions[]) → $desired_capabilities[]
  → diff current $user->allcaps against desired
  → $user->add_cap($cap) for each new capability
  → $user->remove_cap($cap) for each removed capability
  → return {user_id, added: [], removed: []}
```

### WP admin blocking (one hook, no library)

```php
// In functions.php alongside existing is_admin() guards:
add_action( 'admin_init', function() {
    if ( ! current_user_can( 'manage_options' ) && ! wp_doing_ajax() ) {
        wp_redirect( home_url( '/' ) );
        exit;
    }
} );
```

---

## Sources

- Existing codebase: `includes/class-user-roles.php` — capability registration pattern, `ROLES` constant, `add_cap()` on admin role (HIGH confidence — read directly)
- Existing codebase: `includes/class-vog-email.php`, `includes/class-invoice-email-sender.php` — email template pattern (HIGH confidence — read directly)
- Existing codebase: `includes/class-rest-api.php` lines 2614–2628 — existing `get_users` / `delete_user` REST endpoints (HIGH confidence — read directly)
- Existing codebase: `functions.php` line 620 — `rondo_linked_person_id` user meta confirmed; line 703 — `show_admin_bar(false)` pattern (HIGH confidence — read directly)
- Existing codebase: `rondo-sync/steps/download-functions-from-sportlink.js` — Functie data structure confirmed (`function_description`, `is_active` fields in `sportlink_member_functions` SQLite table) (HIGH confidence — read directly)
- Existing codebase: `rondo-sync/lib/rondo-club-db.js` — SQLite schema for `sportlink_member_functions` and tracked members confirmed (HIGH confidence — read directly)
- Existing codebase: `package.json` — `react-hook-form`, `@tanstack/react-query`, `lucide-react`, `axios` all confirmed present (HIGH confidence — read directly)

---

*Stack research for: User Accounts & Profiles milestone — Rondo Club WordPress theme*
*Researched: 2026-02-20*
