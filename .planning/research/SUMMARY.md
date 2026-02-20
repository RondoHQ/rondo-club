# Research Summary

**Project:** Rondo Club — v30.0 User Accounts & Profiles
**Domain:** User provisioning, Functie-based capability mapping, in-app profile management, WP admin blocking
**Researched:** 2026-02-20
**Confidence:** HIGH

## Executive Summary

The User Accounts & Profiles milestone adds the missing layer between the Sportlink member data (already synced to Rondo Club via rondo-sync) and WordPress user accounts. Today, Rondo Club stores all member data in the `person` CPT and team/committee relationships in related post types — but no automated path exists to turn a Sportlink member into a logged-in Rondo user with the right capabilities. This milestone closes that gap by building a provisioning flow (admin-triggered or rondo-sync-triggered), a Functie-to-role mapping config, and an in-app profile page for password management.

The recommended approach reuses every existing mechanism in the stack. No new npm packages or Composer dependencies are needed. User creation uses WordPress core `wp_create_user()`. Email uses the existing `wp_mail()` + Lettermint pattern established by `VogEmail`, `InvoiceEmailSender`, and `InstallmentEmailSender`. Capability mapping is stored in the Options API (same pattern as `ClubConfig` and `FinanceConfig`). The rondo-sync pipeline already downloads Sportlink Functies to SQLite — a new step at the end of that pipeline submits them to Rondo Club's REST API. The React profile page uses `react-hook-form` (already installed) and TanStack Query. The entire milestone is pure logic work inside existing patterns.

The key risks are all operational, not architectural. The most critical: the `admin_init` admin-blocking hook must exempt `admin-ajax.php` (via `wp_doing_ajax()`) or it silently breaks all AJAX-dependent plugins and features for non-admin users. The capability sync must use `add_cap()`/`remove_cap()` (not `set_role()`) to avoid overwriting manually-granted capabilities and must include a full revocation pass to strip capabilities from former board members — not just a grant pass. The welcome email must use the `EmailChannel` Lettermint from-address pattern and implement a `_welcome_email_sent` flag to prevent duplicate sends on re-sync runs.

---

## Key Findings

### Recommended Stack

No new dependencies are needed. Every required capability exists in the current stack: WordPress core for user management (`wp_create_user`, `wp_set_password`, `wp_generate_password`, `add_cap`, `remove_cap`), ACF Pro for person data access, `wp_mail()` + Lettermint for email delivery, `react-hook-form` + TanStack Query for the profile form, and the existing `rondoClubRequest()` REST client in rondo-sync for the new capability-sync step. This is the lowest-risk stack profile possible: zero new surface area outside existing patterns.

**Core technologies (all existing):**
- WordPress core user API: user creation, role and capability management — stable since WP 2.0, used throughout the codebase
- `wp_mail()` + Lettermint: welcome email delivery — same pattern as `VogEmail` and `InvoiceEmailSender`
- WordPress Options API: Functie→role mapping config — same pattern as `ClubConfig` and `FinanceConfig`
- `react-hook-form` (already installed): profile page password change form — no new install needed
- rondo-sync SQLite + `rondoClubRequest()`: capability sync step — new step appended to existing `sync-functions.js` pipeline
- TanStack Query 5: profile data fetching — same pattern as existing notification-channels and dashboard-settings hooks

### Expected Features

**Must have (table stakes):**
- Create WP user from person record — admin needs a one-click path; `wp-admin > Users` is unfamiliar to non-technical admins and bypasses the Rondo workflow
- Welcome email with password-set link (not plaintext password) — uses `get_password_reset_key()`, 7-day expiry for initial activation links
- Functie-to-role mapping config — admin configures "Penningmeester = rondo_bestuur" once; system enforces automatically from then on
- Auto-assign role from Sportlink Functies on sync — rondo-sync step reads SQLite Functies, posts to Rondo Club REST; must be a full reconciliation (grant + revoke)
- Manual capability override per user — `_cap_source_{cap}` user meta flag distinguishes functies-driven from manual; auto-sync must respect manual overrides
- Block wp-admin for non-admin users — `admin_init` hook with `wp_doing_ajax()` + `WP_CLI` + `DOING_CRON` exemptions; redirects to `home_url()`
- In-app password change — new Profile tab in Settings; `POST /rondo/v1/user/password`; requires current password verification; redirects to login on success

**Should have (differentiators):**
- Functie-to-role matrix UI in Settings — checkbox matrix showing Functies as rows, roles as columns; saves to Options API
- Bidirectional user-person link on provisioning — person stores `_wp_user_id`, WP user stores `rondo_linked_person_id`; enables avatar and attendee self-exclusion features with zero additional code
- Known-functies list written by rondo-sync to `rondo_known_functies` WP option — populates the mapping UI without admin typing Functie names manually
- Admin-triggered "sync all capabilities" endpoint — `POST /rondo/v1/users/sync-capabilities`; re-applies roles from stored Functies + current map config

**Defer to post-launch:**
- Paginated user list in Settings > Gebruikers — useful for admin oversight but not required for provisioning to work
- rondo-sync auto-provisioning flag (`--provision`) — gate on explicit opt-in to avoid accidental mass user creation; implement after core provisioning is validated

**Anti-features (do not build):**
- Self-registration / public signup — Sportlink is the source of truth for membership; no approval flow or GDPR consent collection in scope
- Email username-based login — members use a set-password link, not `wp-login.php` directly; WordPress username is internal
- Role hierarchy (`rondo_bestuur` inheriting from `rondo_fairplay`) — the 4 existing roles plus per-user `add_cap()` covers all cases; role hierarchies add complexity with no benefit
- Bulk user creation from CSV — bypasses Sportlink-to-person sync chain; breaks the knvb-id link needed for auto-sync to work

### Architecture Approach

The architecture adds two new PHP classes (`UserProvisioning`, `FunctieCapabilityMap`), modifies three existing ones (`class-rest-api.php`, `class-user-roles.php`, `functions.php`), adds one new React page (`Profile/index.jsx`) with its companion hook, and appends one new step to the rondo-sync `sync-functions.js` pipeline. All data is stored in WordPress native tables: user accounts in `wp_users`, capability config in `wp_options`, and user-to-person/knvb-id links in `wp_usermeta`. No schema changes required.

**Major components:**
1. `class-user-provisioning.php` — creates WP user from person record, links to person, resolves role via FunctieCapabilityMap, sends welcome email; loaded on REST requests only
2. `class-functie-capability-map.php` — stores and resolves the admin-configured Functie→role mapping (`rondo_functie_capability_map` WP option); highest-privilege-wins resolution when multiple Functies map to different roles
3. `POST /rondo/v1/users/provision` (in `class-rest-api.php`) — thin wrapper calling `UserProvisioning::provision()`; requires `manage_options`; idempotent (skips user creation if user already exists, still syncs capabilities)
4. `submit-capabilities-to-rondo-club.js` (rondo-sync) — new step appended to `sync-functions.js`; reads SQLite member_functions, fetches capability map from Rondo Club REST, POSTs provision for each member with email
5. `Profile/index.jsx` — in-app profile page at `/profile` route; reads from expanded `GET /rondo/v1/user/me`; password change form calls `POST /rondo/v1/user/password`
6. `admin_init` hook in `functions.php` — non-admin redirect with `wp_doing_ajax()`, `WP_CLI`, and `DOING_CRON` exemptions

### Critical Pitfalls

1. **admin_init block catches admin-ajax.php and breaks SPA/plugin AJAX** — always call `wp_doing_ajax()` before the redirect; also exempt `WP_CLI` and `DOING_CRON`; test immediately after implementing with a non-admin user: verify AJAX and REST both still work
2. **Capability sync overwrites manually-granted capabilities** — never call `set_role()` on administrator users (check `in_array('administrator', $user->roles)` first); use `add_cap()`/`remove_cap()` for functies-driven capabilities; track capability source in `_cap_source_{cap}` user meta so manual overrides survive re-sync
3. **Capability sync is append-only — former board members retain elevated access** — sync must be a full reconciliation: grant caps to current functie holders AND revoke from former holders; write tests proving revocation before shipping
4. **Duplicate welcome emails on re-sync** — set `_welcome_email_sent` user meta flag after sending; check before sending; suppress WordPress default `wp_new_user_notification_to_user`; use KNVB ID (not email) as primary user lookup key so email changes do not create duplicate accounts
5. **Lettermint from-address mismatch causes welcome emails to fail or land in spam** — all `wp_mail()` calls must use the `add_filter('wp_mail_from', ...) / remove_filter` pattern from `EmailChannel`; verify correct from-address in Lettermint dashboard during Phase 3 testing before sending to real members

---

## Implications for Roadmap

The features have clear dependency chains that dictate build order. Each phase is independently deployable and testable before the next phase depends on it.

### Phase 1: WP Admin Blocking

**Rationale:** Zero dependencies on other phases. A simple single-hook change in `functions.php`. Must ship first so that once user accounts are created at scale, non-admins can never accidentally reach wp-admin. Delivers real security value immediately and prevents the most embarrassing possible outcome.
**Delivers:** `admin_init` hook with `wp_doing_ajax()`, `WP_CLI`, `DOING_CRON` exemptions; redirect non-admins to `home_url()`
**Addresses:** Block wp-admin access (table stakes)
**Avoids:** The admin-ajax.php break pitfall — test immediately with a non-admin user in browser devtools; verify AJAX and REST both work after implementing

### Phase 2: Functie-to-Role Mapping Config

**Rationale:** Provisioning (Phase 3) reads the mapping config to assign the correct role on user creation. The mapping must exist before provisioning runs, or all provisioned users get `rondo_user` regardless of their Sportlink position. Admin must be able to configure this before the first sync runs.
**Delivers:** `FunctieCapabilityMap` class; `rondo_functie_capability_map` WordPress option; `GET/POST /rondo/v1/functie-capability-map` REST endpoints (admin-only); Settings admin UI — Functie-to-role matrix with checkboxes
**Uses:** WordPress Options API (same pattern as ClubConfig), existing Settings > Admin tab structure
**Implements:** Architecture component: `class-functie-capability-map.php`

### Phase 3: User Provisioning

**Rationale:** Core of the milestone. Creates WP user accounts. Depends on Phase 2 (mapping config) so the right role is assigned at creation time. Welcome email must be tested end-to-end including Lettermint from-address and 7-day password-set link.
**Delivers:** `UserProvisioning` class; `POST /rondo/v1/users/provision` REST endpoint; welcome email with `get_password_reset_key()` and 7-day expiry; bidirectional user-person link (`rondo_linked_person_id`, `_wp_user_id`); KNVB ID stored in user meta (`rondo_knvb_id`); `_welcome_email_sent` idempotency flag
**Avoids:** Duplicate user creation (KNVB ID as primary lookup key), duplicate welcome emails (`_welcome_email_sent` flag), role-not-set pitfall (always pass explicit `role` to `wp_insert_user()`), Lettermint from-address mismatch (wrap all `wp_mail()` calls with `EmailChannel` filter pattern)

### Phase 4: Capability Sync (rondo-sync step)

**Rationale:** Depends on Phase 3 — users must exist and have `rondo_knvb_id` user meta before the sync can find them. This is where the full reconciliation logic lives: grant and revoke. The administrator guard and `_cap_source_{cap}` manual-override tracking are correctness requirements with high recovery cost if missed.
**Delivers:** `submit-capabilities-to-rondo-club.js` rondo-sync step appended to `sync-functions.js`; `POST /rondo/v1/users/sync-capabilities` admin-triggered endpoint; full grant + revoke reconciliation; administrator role protection; manual-override (`_cap_source_*`) respect; sync logs showing both grants and revocations
**Avoids:** Append-only sync leaving former board members with elevated access, capability sync overwriting administrator role, capability revocation not implemented

### Phase 5: In-App Profile Page

**Rationale:** Can be built in parallel with or after Phase 3. Needs WP user accounts to exist and the expanded `/user/me` data (set in Phase 3). Password change endpoint is independent of provisioning but the user experience only makes sense once accounts exist. Profile link in sidebar completes the end-to-end user experience.
**Delivers:** `Profile/index.jsx` page at `/profile` route; `useProfile.js` TanStack Query hook; `POST /rondo/v1/user/password` endpoint with current-password verification; session-expiry warning and redirect to login after password change; global 401 interceptor in `src/api/client.js`; sidebar profile link in `Layout.jsx`; expanded `GET /rondo/v1/user/me` (adds `linked_person_thumbnail`, `functies[]`)
**Avoids:** Silent 401s after password change (explicit redirect to login), no session management after `wp_set_password()`, missing 401 interceptor causing broken app state

### Phase Ordering Rationale

- Phase 1 first — zero dependencies, immediate security value, prevents gap once accounts are created
- Phase 2 before Phase 3 — provisioning reads the mapping config; without it all provisioned users get `rondo_user` regardless of Sportlink position
- Phase 3 before Phase 4 — the sync step needs WP users with `rondo_knvb_id` user meta to exist
- Phase 5 last (or parallel with Phase 3) — depends on expanded `/user/me` data from Phase 3; users can change passwords via `wp-login.php` as a fallback until this phase ships

### Research Flags

Phases with standard patterns (no additional research needed):
- **Phase 1 (WP Admin Blocking):** Single `admin_init` hook — thoroughly documented WordPress pattern, verified in codebase; zero ambiguity
- **Phase 2 (Functie Mapping Config):** Options API + REST endpoints — established pattern in codebase (`ClubConfig`, `FinanceConfig`); no novel decisions
- **Phase 3 (User Provisioning):** WordPress user API stable since WP 2.0; email follows `VogEmail` pattern exactly; all patterns read from codebase
- **Phase 4 (Capability Sync):** Logic is clear; rondo-sync pipeline structure is established; patterns read from codebase
- **Phase 5 (Profile Page):** React + TanStack Query + react-hook-form — all established patterns; password change endpoint mirrors existing REST patterns

No phases require `/gsd:research-phase` — all required patterns are verified with HIGH confidence from direct codebase reads and WordPress official documentation.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies verified by reading existing class files and package.json; zero new dependencies confirmed |
| Features | HIGH | Sourced from deep codebase analysis + WordPress official docs; anti-features explicitly reasoned; no speculation |
| Architecture | HIGH | All patterns read directly from existing class files; component boundaries match established codebase conventions; build order derived from feature dependency graph |
| Pitfalls | HIGH (critical pitfalls) / MEDIUM (moderate pitfalls) | admin-ajax.php break, capability overwrite, duplicate emails, append-only sync all confirmed from codebase + official docs; Lettermint-specific sending behavior needs live verification |

**Overall confidence:** HIGH

### Gaps to Address

- **Lettermint from-address validation behavior:** The pattern is clear (follow `EmailChannel`), but Lettermint's specific rejection behavior for unlisted from-addresses has not been verified against the live account. Verify in Lettermint dashboard during Phase 3 execution before sending to real members.
- **rondo-sync service account permissions:** The Application Password used by rondo-sync must belong to an administrator WordPress user for the provisioning endpoint (`manage_options` requirement) to succeed. Verify the service account's role in wp-admin before Phase 3 execution — check the owner of the Application Password.
- **Password reset expiry filter scope:** The `password_reset_expiration` filter extending expiry to 7 days must only be applied in the provisioning context, not globally. Ensure `remove_filter` is called immediately after `get_password_reset_key()` to avoid extending expiry for standard password resets elsewhere in the app.
- **Manual re-send of welcome email:** If a member loses their set-password link and it expires, they need a recovery path. The `_welcome_email_sent` flag prevents re-send. The MVP recovery is the standard `wp-login.php?action=lostpassword` page — document this in admin notes. A dedicated "resend welcome email" admin action is a post-launch addition.

---

## Sources

### Primary (HIGH confidence — direct codebase reads)

- `includes/class-user-roles.php` — role registration, ROLES constant, `add_cap()` / `remove_cap()` pattern
- `includes/class-rest-api.php` lines 2614–2660 — existing users GET/DELETE endpoints; REST pattern
- `includes/class-vog-email.php`, `class-invoice-email-sender.php`, `class-installment-email-sender.php` — email template and `wp_mail()` wrapper pattern
- `includes/class-finance-config.php`, `class-club-config.php` — Options API pattern for site-wide config
- `includes/class-email-channel.php` — from-address filter pattern (`add_filter` / `remove_filter`) that all new email sends must follow
- `includes/class-access-control.php` — permission model and `check_admin_permission()`
- `functions.php` lines 620, 703 — `rondo_linked_person_id` confirmed; `show_admin_bar(false)` pattern
- `rondo-sync/steps/download-functions-from-sportlink.js` — Functies data structure (`function_description`, `is_active`)
- `rondo-sync/lib/rondo-club-db.js` — `sportlink_member_functions` SQLite schema confirmed
- `rondo-sync/lib/rondo-club-client.js` — `rondoClubRequest()` REST client pattern for new step
- `package.json` — `react-hook-form`, `@tanstack/react-query`, `lucide-react`, `axios` all confirmed present

### Secondary (MEDIUM confidence — official documentation)

- WordPress Developer Reference: `WP_User::add_cap()`, `remove_cap()` — capability storage in `wp_usermeta`, persistence across role changes
- WordPress Developer Reference: `get_password_reset_key()` — 24h default expiry, single-use, `password_reset_expiration` filter
- WordPress Developer Reference: `wp_set_password()` — clears `session_tokens`, invalidates all active sessions
- WordPress Developer Reference: Roles and Capabilities — `set_role()` replaces role caps; `add_cap()` adds per-user overrides that persist independently
- WordPress: `wp_send_new_user_notification_to_user` filter — suppress default WP welcome notification
- WordPress: `admin_init` + `wp_doing_ajax()` for non-admin redirect — standard idiomatic WordPress pattern
- WordPress Core: `password_reset_expiration` filter — 24h default confirmed; 7-day extended TTL for provisioning context

### Tertiary (LOW confidence — validate during implementation)

- Lettermint specific sending limits and from-address validation behavior — verify directly in Lettermint dashboard before sending welcome emails to members
- SiteGround server-level `.htaccess` interaction with PHP-level `admin_init` hook — verify no double-blocking after implementing Phase 1
- Rate limiting transient approach for password reset endpoint — standard WordPress community pattern; no authoritative official documentation

---

*Research completed: 2026-02-20*
*Ready for roadmap: yes*
