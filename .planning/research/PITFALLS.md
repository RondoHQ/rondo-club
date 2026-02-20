# Pitfalls Research: User Accounts & Profiles

**Domain:** User provisioning from external member database + capability auto-sync + WP admin blocking + in-app profile added to existing WordPress + React SPA (Rondo Club)
**Researched:** 2026-02-20
**Confidence:** HIGH (WordPress admin blocking, capability storage behavior, WP nonce/CSRF) / MEDIUM (welcome email patterns, password reset endpoint security, Sportlink-to-role mapping edge cases)
**Context:** Adding to an existing system. The theme already has: custom roles (`rondo_user`, `rondo_fairplay`, `rondo_vog`, `rondo_bestuur`), custom capabilities (`fairplay`, `vog`, `financieel`), a React SPA that uses `admin-ajax.php` via plugins and the REST API exclusively via `/wp-json/`, user-to-person linking via `rondo_linked_person_id` user meta, and rondo-sync pushing data from Sportlink via WordPress Application Passwords over Basic Auth.

---

## Critical Pitfalls

### Pitfall 1: WP Admin Block Catches admin-ajax.php and Breaks SPA and Plugin Functionality

**What goes wrong:**
Blocking non-admin users from `/wp-admin/` using `admin_init` + `wp_redirect()` also redirects requests to `/wp-admin/admin-ajax.php`. That URL is technically inside `/wp-admin/`, but `admin-ajax.php` is the standard WordPress AJAX endpoint used by the frontend — plugins (caching, PWA, contact forms), WordPress core password reset hooks, and some legacy theme code all POST to it. When non-admin users are redirected away from `admin-ajax.php`, AJAX calls silently return an HTML redirect response instead of JSON, causing React to throw parse errors and features to fail with no visible error to the user.

WordPress's own `is_admin()` returns `true` inside `admin-ajax.php` — even for requests initiated from the frontend. This is a well-known WordPress quirk (confirmed by official docs: "Admin-Ajax is technically an admin page, so is_admin() returns TRUE") that trips up every developer who restricts wp-admin for the first time.

**Why it happens:**
A naïve `admin_init` block checks `is_admin()` and redirects. `is_admin()` is true for all requests to `/wp-admin/`, including `admin-ajax.php`. The redirect goes to the homepage, which returns HTML. Any JavaScript `fetch()` call waiting for JSON gets HTML, throws a parse error, and either silently fails or surfaces a generic error to the user.

**How to avoid:**
Always explicitly exempt `admin-ajax.php` from the admin block. The correct pattern:

```php
add_action( 'admin_init', function () {
    // Must exempt admin-ajax.php — it's called from the frontend
    if ( wp_doing_ajax() ) {
        return;
    }
    // Also exempt WP-CLI and cron
    if ( defined( 'WP_CLI' ) || defined( 'DOING_CRON' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_redirect( home_url( '/' ) );
        exit;
    }
} );
```

`wp_doing_ajax()` checks `$_SERVER['HTTP_X_REQUESTED_WITH']` and the `DOING_AJAX` constant — it is the canonical way to detect AJAX requests in WordPress, not checking the URL string directly.

Also verify that the REST API (`/wp-json/`) is NOT affected — REST requests set `REST_REQUEST` constant, not `DOING_AJAX`. The REST API does not go through `/wp-admin/` so it is not caught by the `admin_init` redirect. No exemption is needed for the REST API, but confirm this after implementation.

**Warning signs:**
- Browser console shows `SyntaxError: Unexpected token '<'` when React makes a request (HTML redirect returned instead of JSON)
- Any plugin that uses AJAX (caching plugins, contact forms, PWA) stops working for non-admin users
- `wp_doing_ajax()` is not called before the redirect in `admin_init`

**Phase to address:** WP Admin Blocking phase — test with a non-admin user in browser devtools immediately after implementing the block. Verify both that AJAX works (POST to `admin-ajax.php`) and that the REST API works (GET to `/wp-json/rondo/v1/dashboard`).

---

### Pitfall 2: Capability Sync Overwrites Admin's Manually-Granted or Manually-Revoked Capabilities

**What goes wrong:**
rondo-sync pushes a "functie" (role) mapping from Sportlink that triggers `wp_update_user()` or `$user->set_role()` to sync capabilities. If the admin has manually granted a user an additional capability (e.g., gave a regular member the `financieel` capability outside of their Sportlink role), the sync overwrites this. Next rondo-sync run: capability gone. Conversely, if admin manually demoted a user (e.g., removed `fairplay` from someone who resigned from the board before Sportlink was updated), the sync re-grants it.

The same risk applies in reverse: if rondo-sync calls `set_role()` on an existing `administrator` user because Sportlink shows them as a regular member, it strips `manage_options` and locks the admin out of wp-admin.

**Why it happens:**
`WP_User::set_role()` replaces all role-based capabilities. `WP_User::add_cap()` / `remove_cap()` work on user-level capability overrides in `wp_usermeta` (the `wp_capabilities` key), which persist independently of role. Sync code that calls `set_role()` wholesale loses any manually-set per-user capability overrides.

Developers confuse "user's role" (set_role) with "user's effective capabilities" (which include both role caps + per-user caps).

**How to avoid:**

1. **Never call `set_role()` on administrator users.** Before syncing, check `current_user_can( 'manage_options' )` or `in_array( 'administrator', $user->roles )` and skip the role update entirely for admins.

2. **Sync by adding capabilities, not by replacing role.** Instead of `$user->set_role('rondo_fairplay')`, keep the user's base Rondo role and add/remove the specific capability:
   ```php
   // Safe: grant or revoke specific capability only
   if ( $should_have_fairplay ) {
       $user->add_cap( 'fairplay' );
   } else {
       $user->remove_cap( 'fairplay' );
   }
   ```

3. **Alternatively, track a "sync-managed capabilities" set.** Store which capabilities rondo-sync controls in user meta (`_sync_managed_caps`). The sync only touches those capabilities; manually-set others are untouched.

4. **If you must change role:** Use a "guard" check — if the current role is NOT a Rondo role, don't change it:
   ```php
   if ( \Rondo\Core\UserRoles::has_rondo_role( $user ) ) {
       $user->set_role( $target_role );
   }
   // Administrators, editors, etc. are skipped
   ```

**Warning signs:**
- rondo-sync calls `wp_update_user( [ 'role' => ... ] )` without first checking if user is admin
- Post-sync: admin user cannot access wp-admin (lost `manage_options`)
- Post-sync: manually-granted `financieel` capability disappears from non-board member

**Phase to address:** Capability Sync phase. Write the "admin guard" and "Rondo-role guard" before any sync logic. Test by running sync against a user who has `administrator` role — verify their role is unchanged.

---

### Pitfall 3: Welcome Email Fires Again on Every Sync Run (Duplicate Emails to Members)

**What goes wrong:**
rondo-sync creates a new WordPress user if none exists for a Sportlink member (upsert pattern). The welcome email is sent on creation. On the next sync run, the code finds the user already exists, does an update (no email), and things are fine — UNLESS the matching logic fails to find the existing user. Matching by email address can fail if: the member's email changed in Sportlink (now no match), the email was never in Sportlink (empty email in Sportlink), or a user was created with a different email via `wp_new_user_notification()` than what Sportlink knows.

The failure mode: user already exists but is not found, a second user is created (duplicate WP user, different ID, same person), and another welcome email fires. The member gets two accounts and two welcome emails. The second account has no linked person record (or links to the wrong person).

A related failure: `wp_new_user_notification()` is the function that sends both the admin notification and the user welcome email. If the code calls it explicitly — instead of letting WordPress fire it automatically on `wp_insert_user` — it's easy to accidentally call it twice (once in the create function, once in a hook).

**Why it happens:**
- User lookup is done by email but Sportlink emails are not guaranteed unique or present
- `wp_insert_user()` triggers `user_register` hook which may fire `wp_new_user_notification()` depending on WordPress version and settings
- Sync code also calls `wp_new_user_notification()` explicitly → double email
- No idempotency guard ("was welcome email already sent for this user?")

**How to avoid:**

1. **Primary match key: Sportlink KNVB ID, not email.** Store KNVB ID in user meta (`rondo_sportlink_id` or similar) on user creation. Lookup first by KNVB ID user meta, fall back to email. This survives email changes.

2. **Suppress the default `wp_new_user_notification()`.** WordPress fires this automatically on `user_register` when called from certain contexts. Filter it out so you control when the welcome email sends:
   ```php
   // Suppress WordPress's default welcome email during programmatic creation
   add_filter( 'wp_send_new_user_notification_to_user', '__return_false' );
   $user_id = wp_insert_user( $user_data );
   remove_filter( 'wp_send_new_user_notification_to_user', '__return_false' );
   // Now send your own custom welcome email exactly once
   send_rondo_welcome_email( $user_id );
   ```

3. **Track "welcome email sent" in user meta.** After sending, set `update_user_meta( $user_id, '_welcome_email_sent', 1 )`. Before sending, check this flag. Idempotent by design.

4. **Never send welcome email for existing user updates.** The upsert function must distinguish create vs update and gate the welcome email on the create path only.

**Warning signs:**
- Members report receiving two "welcome to Rondo Club" emails
- WordPress user list shows two accounts with similar names (duplicate user records)
- `wp_new_user_notification()` called in both the create function and in a `user_register` hook
- No `_welcome_email_sent` user meta key on created users

**Phase to address:** User Provisioning phase — test the full sync cycle twice on the same member and verify: (a) no duplicate user created, (b) exactly one welcome email sent across both runs.

---

### Pitfall 4: Password Reset via Custom REST Endpoint Has No Rate Limiting or CSRF Protection

**What goes wrong:**
An in-app "change password" or "send password reset" endpoint at `POST /rondo/v1/user/password-reset` is accessible to anyone who can send an HTTP request. Without rate limiting, an attacker can call this endpoint for any user's email address in rapid succession. Each call triggers `retrieve_password()` or `get_password_reset_key()` + `wp_mail()`. This:
1. Floods the target member's inbox with password reset emails (email harassment)
2. Spams the Lettermint account's sending quota
3. Each call invalidates the previous key (only one valid reset key at a time in WordPress), so an attacker can continuously invalidate legitimate reset links

The REST API uses cookie authentication + nonce for logged-in users. A password reset endpoint for a logged-in user changing their own password needs the `X-WP-Nonce` header (protects against CSRF). An endpoint allowing unauthenticated password reset initiation (by email) needs server-side rate limiting because there is no WordPress-native nonce for unauthenticated requests.

**Why it happens:**
WordPress's own `wp_new_user_notification()` and `retrieve_password()` have no built-in rate limiting. Developers add a REST endpoint and forget the endpoint is public. The `X-WP-Nonce` mechanism only applies to logged-in (cookie-authenticated) requests.

**How to avoid:**

1. **Use WordPress's native password reset flow as much as possible.** For members resetting from the login page: standard `wp-login.php?action=lostpassword` has WordPress's built-in token expiry (24h) and single-use key. Reinventing this wheel creates more attack surface.

2. **For in-app "change password" (logged-in user):** This is safe — the request is cookie + nonce authenticated. Verify `X-WP-Nonce` header (WordPress REST API does this automatically for cookie-auth requests). Use `wp_set_password( $new_password, $user_id )` followed by `wp_clear_auth_cookie()` + new login to avoid session invalidation.

3. **For "send reset link to email" endpoint (unauthenticated):** If you must build this, add rate limiting per IP and per email address using WordPress transients:
   ```php
   $rate_key = 'rondo_pw_reset_' . md5( $email . $_SERVER['REMOTE_ADDR'] );
   if ( get_transient( $rate_key ) ) {
       return new WP_Error( 'too_many_requests', 'Try again in 5 minutes.', [ 'status' => 429 ] );
   }
   set_transient( $rate_key, 1, 5 * MINUTE_IN_SECONDS );
   ```

4. **Send reset emails through Lettermint's `from` address** (same as existing `EmailChannel::set_email_from_address()` pattern). Do not use WordPress's default `wordpress@domain.com` from address — it will fail Lettermint's sender validation.

**Warning signs:**
- Password reset endpoint has `permission_callback` set to `__return_true` (unauthenticated) with no rate limit guard
- No transient-based rate limiting on any endpoint that sends email
- Reset email uses WordPress default from address (`wordpress@site.nl`) instead of configured Lettermint address
- `wp_clear_auth_cookie()` not called after `wp_set_password()` — old sessions remain valid

**Phase to address:** In-App Profile phase. Rate limiting transient must be implemented before the endpoint goes to production.

---

### Pitfall 5: User-to-Person Link Becomes Stale When Person Is Deleted or Merged

**What goes wrong:**
The system stores `rondo_linked_person_id` in user meta pointing to a person post ID. If the person post is deleted (e.g., admin purges former member records), the user meta still points to the now-nonexistent post ID. The React SPA reads `currentUserPersonId` from `rondoConfig` (injected at page load from this meta) and passes it to attendee-list components to "exclude self." A stale link causes:
- The self-exclusion logic silently fails (the person ID doesn't match anything)
- `GET /rondo/v1/user/linked-person` returns the deleted person's ID; the React app tries to fetch that person and gets 404

The reverse also happens: if a person is duplicated (sync creates a second record), the user's link points to the old record while rondo-sync created a new one. The user's profile page shows stale data.

**Why it happens:**
User meta (`rondo_linked_person_id`) is a plain integer foreign key with no referential integrity enforced by WordPress. WordPress fires a `delete_post` action when a person is deleted, but nothing currently listens to clean up the user meta.

**How to avoid:**

1. **Add a `delete_post` hook that clears stale links.** When a `person` post is deleted, query for all users with `rondo_linked_person_id = $post_id` and set it to 0 or delete the meta:
   ```php
   add_action( 'delete_post', function ( $post_id ) {
       if ( get_post_type( $post_id ) !== 'person' ) return;
       $users = get_users( [
           'meta_key'   => 'rondo_linked_person_id',
           'meta_value' => $post_id,
       ] );
       foreach ( $users as $user ) {
           delete_user_meta( $user->ID, 'rondo_linked_person_id' );
       }
   } );
   ```

2. **The `get_linked_person` REST endpoint should validate the link.** Before returning the person ID, call `get_post( $person_id )` and verify it exists and has post type `person`. If not, return null and clear the stale meta.

3. **On user provisioning/sync, re-verify the link.** When syncing a user who already has a linked person, confirm the person still exists. If not, re-run the matching logic (by KNVB ID) to find the correct current record.

**Warning signs:**
- `GET /rondo/v1/user/linked-person` returns a person ID for a deleted person (subsequent `GET /wp/v2/people/{id}` returns 404)
- No `delete_post` hook anywhere in the codebase that touches `rondo_linked_person_id`
- `rondoConfig.currentUserPersonId` in browser console points to a person that doesn't appear in the people list

**Phase to address:** User Provisioning phase (add the delete hook) + In-App Profile phase (validate link in the REST endpoint).

---

### Pitfall 6: rondo-sync Provisioning Endpoint Lacks the Admin Permission Required by WordPress User Creation

**What goes wrong:**
`wp_insert_user()` and `wp_update_user()` with a `role` parameter require administrator privileges when called via the REST API (`/wp/v2/users`). rondo-sync authenticates via Application Password of a non-admin user → the user creation call returns HTTP 403. Alternatively, if the provisioning is done through a custom REST endpoint that bypasses this check, it becomes a privilege escalation vector: any authenticated user could call the endpoint to create admin accounts.

The existing rondo-sync pattern authenticates with a service account (Application Password). If that account is not an administrator, user management via the standard `/wp/v2/users` REST endpoint fails silently with 403.

**Why it happens:**
WordPress REST API `/wp/v2/users POST` requires `create_users` capability, which only administrators have. rondo-sync service accounts created with minimal permissions cannot create users.

**How to avoid:**

1. **The rondo-sync service account must be an Administrator.** The Application Password used by rondo-sync for user provisioning must belong to an administrator WordPress user. This is already the case for person/team sync (it requires `edit_others_posts`). Document this requirement explicitly.

2. **Custom provisioning endpoint must check `manage_options`** (not just `is_user_logged_in()`):
   ```php
   'permission_callback' => function() {
       return current_user_can( 'manage_options' );
   }
   ```
   This ensures only admins can call the user creation endpoint, whether via Application Password or nonce-authenticated session.

3. **Never return informative error messages from user creation endpoints.** A 403 response that says "user already exists with email X" leaks member data. Return generic errors to unauthenticated callers.

**Warning signs:**
- rondo-sync's user provisioning calls return 403 in sync logs
- Custom provisioning endpoint uses `permission_callback => '__return_true'` or `is_user_logged_in`
- Error messages include email addresses or user IDs in the response body

**Phase to address:** User Provisioning phase — test the full rondo-sync flow with the service account before building any UI.

---

## Moderate Pitfalls

### Pitfall 7: Lettermint From-Address Mismatch Causes Welcome Emails to Fail or Land in Spam

**What goes wrong:**
The existing `EmailChannel` class applies `wp_mail_from` and `wp_mail_from_name` filters for each notification email it sends, then removes them after. Welcome emails sent via `wp_new_user_notification()` or a custom `wp_mail()` call that does NOT apply these filters will use WordPress's default from address (`wordpress@rondo.svawc.nl`). This domain likely has no SPF/DKIM record configured for `rondo.svawc.nl` as a sending domain in Lettermint — emails fail delivery or land in spam.

The existing VOGEmail and InvoiceEmailSender classes each apply the from-address filter inline before calling `wp_mail()` and remove it after. The same pattern must be followed for all new email sends.

**Why it happens:**
Developers copy the `wp_mail()` call without copying the surrounding `add_filter` / `remove_filter` boilerplate. The welcome email works in test (local dev uses different SMTP) but fails in production (Lettermint rejects unlisted from address).

**How to avoid:**
Extract the from-address filter pattern into a reusable method or static utility in the email channel class:
```php
// Reusable email send helper
public static function send_with_rondo_from( string $to, string $subject, string $message, array $headers = [] ): bool {
    $channel = new EmailChannel();
    add_filter( 'wp_mail_from', [ $channel, 'set_email_from_address' ] );
    add_filter( 'wp_mail_from_name', [ $channel, 'set_email_from_name' ] );
    $result = wp_mail( $to, $subject, $message, $headers );
    remove_filter( 'wp_mail_from', [ $channel, 'set_email_from_address' ] );
    remove_filter( 'wp_mail_from_name', [ $channel, 'set_email_from_name' ] );
    return $result;
}
```
Use this for all new email sends including welcome emails. Never call `wp_mail()` bare.

Also: `wp_new_user_notification()` calls `wp_mail()` internally — it does NOT apply Rondo's from-address filters. To customize: use `wp_new_user_notification_email` filter to modify subject/body, then send manually via the helper instead of letting WordPress send it.

**Warning signs:**
- Welcome emails use `From: wordpress@rondo.svawc.nl` in email headers (visible in email source)
- Lettermint sending logs show "From address not verified" errors
- Welcome emails land in spam folders (SPF/DKIM failures)
- `add_filter('wp_mail_from', ...)` is not called before the welcome email `wp_mail()`

**Phase to address:** User Provisioning phase (welcome email) — verify in Lettermint dashboard that the test welcome email shows correct from address.

---

### Pitfall 8: Password Reset Links in Welcome Emails Expire Before Members Click Them

**What goes wrong:**
`wp_new_user_notification()` generates a password reset key that expires after 24 hours (WordPress default). For a club member who receives a welcome email when they're on holiday, travelling, or simply doesn't check email daily, the link is expired by the time they try to use it. They click "Set password" → "Invalid key. Please reset your password again." — a confusing dead end for a first-time user.

A batch provisioning run for 500 members generates 500 reset keys simultaneously. Members who don't act within 24 hours need to request a new reset — but they may not know how (they've never logged in before).

**Why it happens:**
WordPress's `user_activation_key` expiry is hardcoded at 24 hours via the `password_reset_expiration` filter (default 86400 seconds). Most developers don't think about expiry when building welcome emails.

**How to avoid:**

1. **Extend the expiry for welcome emails.** Use the `password_reset_expiration` filter with a longer TTL (7 days is reasonable for initial account activation):
   ```php
   // In the user creation context only — don't leave this on globally
   add_filter( 'password_reset_expiration', fn() => 7 * DAY_IN_SECONDS );
   $reset_key = get_password_reset_key( $user );
   remove_filter( 'password_reset_expiration', ... );
   ```

2. **Make the welcome email clearly explain what to do if the link expired.** Include: "If the link has expired, visit [login URL] and click 'Lost your password?'"

3. **Alternatively: send an initial temporary password** (WordPress's old pattern — `wp_new_user_notification` used to do this). Less secure but simpler for users. Use `wp_generate_password( 12 )` and force reset on first login via `$user->set_role()` to a "must-change-password" state.

4. **For batch provisioning:** stagger welcome emails over multiple days (send to 50 per day) so not all 500 members have to act on the same day.

**Warning signs:**
- Welcome email links use 24h expiry and batch provisioning sends 500 at once
- No "link expired" instructions in the welcome email body
- Test: send a welcome email, wait 25 hours, click the link → should get "Invalid key" — if that happens with no recovery path, it's a problem

**Phase to address:** User Provisioning phase (welcome email design) — test the full flow including expired link scenario before shipping.

---

### Pitfall 9: In-App Password Change Invalidates Active React SPA Sessions

**What goes wrong:**
`wp_set_password()` changes the password and also updates the `session_tokens` user meta, invalidating all existing WordPress auth cookies. After a successful password change via the in-app React form, the user's current session is immediately invalidated. The next REST API call from the React SPA returns 401. If the SPA doesn't handle this gracefully — detect 401, redirect to login — the user sees confusing broken state: the app appears to work (no redirect) but all data fetches fail silently.

**Why it happens:**
WordPress session management ties auth cookie validity to the password hash. `wp_set_password()` internally calls `update_user_meta( $user_id, 'session_tokens', [] )` which wipes all sessions. The SPA's `axios` client with a stale cookie/nonce receives 401 but the React Query error handling may not redirect to login.

**How to avoid:**

1. **After successful password change, explicitly redirect to login from the frontend.** The REST endpoint that changes the password should return a specific response code/flag (`"reauth_required": true`). The React component catches this and calls `window.location.href = rondoConfig.loginUrl`.

2. **Or: re-authenticate the user programmatically.** After `wp_set_password()`, call `wp_signon()` with the new credentials and set a new cookie. This is complex and fragile.

3. **Preferred approach: Use WordPress's own password change flow.** The existing login page (`wp-login.php?action=rp`) handles post-reset re-authentication correctly. For in-app feel, open it in a modal or redirect cleanly.

4. **The React SPA's `client.js` should have a global 401 interceptor** that redirects to login. This is defense-in-depth regardless of the password change approach:
   ```javascript
   client.interceptors.response.use(null, (error) => {
       if (error.response?.status === 401) {
           window.location.href = rondoConfig.loginUrl;
       }
       return Promise.reject(error);
   });
   ```

**Warning signs:**
- After changing password via the in-app form, subsequent REST calls return 401
- No 401 interceptor in `src/api/client.js`
- React components show stale data or empty state instead of redirecting to login
- `wp_set_password()` called without any subsequent session management

**Phase to address:** In-App Profile phase — test the full flow: change password, verify redirect to login, verify old session no longer works.

---

### Pitfall 10: Functie-to-Role Mapping Covers Only Current Board — Former Board Members Keep Elevated Caps

**What goes wrong:**
rondo-sync maps Sportlink "functie" (role/function) to Rondo capabilities. A board member (`rondo_bestuur`: `fairplay + vog + financieel`) leaves the board in Sportlink. On the next sync, if the sync only grants capabilities (adds caps for current functie holders) without revoking caps for former holders, the ex-board member retains `financieel` capability indefinitely. They can still access financial data in the React SPA.

This is a specific instance of Pitfall 2, but the scenario is common enough in club management that it deserves its own warning.

**Why it happens:**
Sync code is often written as "for each member with functie X, grant cap Y" — the positive pass. The negative pass ("for each member who no longer has functie X, revoke cap Y") is forgotten or considered out of scope.

**How to avoid:**

1. **Sync must be a full reconciliation, not append-only.** For each Rondo-managed capability (`fairplay`, `vog`, `financieel`): get the set of user IDs who should have it (based on Sportlink functie), get the set of user IDs who currently have it, compute the diff, and revoke from those who should not have it.

2. **Alternatively, replace the role entirely (with admin guard).** Set the correct `rondo_*` role for each synced user, which gives exactly the right caps. The admin guard from Pitfall 2 prevents touching administrator users. This is simpler than per-capability diffing.

3. **Sync logs must show both grants AND revocations.** "Granted financieel to user 42" and "Revoked fairplay from user 17" should both appear in sync output.

**Warning signs:**
- Sync code only calls `add_cap()` or `set_role()` for the positive case
- No code path exists that calls `remove_cap()` or changes role away from `rondo_bestuur`
- Former board members still appear in `get_users( [ 'capability' => 'financieel' ] )`

**Phase to address:** Capability Sync phase — test with a user who has `rondo_bestuur`, remove their functie in Sportlink data, run sync, verify they no longer have `financieel`.

---

### Pitfall 11: User Creation During Sync Run Creates Users Without Login Capability (No Role Set)

**What goes wrong:**
`wp_insert_user()` called without a `role` parameter creates a user with the default role (set in WordPress general settings — typically `subscriber`). `subscriber` does not have the custom Rondo capabilities. The user can log in but the React SPA's `check_user_approved()` passes (they are logged in), yet they have no meaningful capabilities and see an empty or broken app.

Even worse: if the general settings are changed to "No role for this site" (common on multisite), users are created with no role at all and cannot access anything.

**Why it happens:**
Developers call `wp_insert_user( [ 'user_login' => ..., 'user_email' => ..., 'user_pass' => ... ] )` without thinking about the role. WordPress silently defaults to the site's default role setting, not a Rondo role.

**How to avoid:**
Always specify `role` explicitly in the `wp_insert_user()` call:
```php
$user_id = wp_insert_user( [
    'user_login' => $username,
    'user_email' => $email,
    'user_pass'  => wp_generate_password(),
    'role'       => 'rondo_user', // ALWAYS explicit
    'first_name' => $first_name,
    'last_name'  => $last_name,
] );
```
The role should be mapped from the Sportlink functie: default to `rondo_user`, upgrade based on functie mapping.

**Warning signs:**
- `wp_insert_user()` call does not include a `role` key
- New users have `subscriber` role instead of `rondo_*` role in Users list
- React SPA shows no content for newly provisioned users despite successful login

**Phase to address:** User Provisioning phase — verify new users created by sync have `rondo_user` (or appropriate Rondo role) in the WordPress Users admin.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Match existing users by email only (not KNVB ID) | Simpler lookup | Email changes in Sportlink create duplicate user accounts | Never — always use KNVB ID as primary key |
| Call `set_role()` without checking for administrator | Simpler sync code | Next sync run locks admin out of wp-admin | Never |
| Use `wp_new_user_notification()` default | No custom email code | WordPress from-address fails Lettermint, users get spam/no email | Never in production |
| Skip "revoke capabilities" pass in sync | Simpler sync code | Former board members retain financieel access indefinitely | Never for security-sensitive caps |
| No `_welcome_email_sent` flag in user meta | Simpler code | Re-sync sends welcome emails to existing users | Never |
| Block all `/wp-admin/` without exempting AJAX | Simpler code | Breaks all AJAX-dependent functionality | Never |
| Store user KNVB link only in linked_person_id (no reverse) | Simpler model | Cannot find user from person without full user table scan | Acceptable for MVP if person count < 1000 |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| WordPress admin blocking | Redirect all `/wp-admin/` for non-admins | Always exempt `admin-ajax.php` using `wp_doing_ajax()` |
| `wp_new_user_notification()` | Called alongside custom email → double send | Suppress with `wp_send_new_user_notification_to_user` filter before calling; send custom email manually |
| Lettermint (wp_mail from-address) | Call `wp_mail()` bare without from-address filters | Always wrap with `add_filter('wp_mail_from', ...)` / `remove_filter` pair — see `EmailChannel` pattern |
| `wp_set_password()` | Change password without session management | Redirect to login after `wp_set_password()` — old sessions are invalid |
| rondo-sync Application Password | Use non-admin service account for user creation | rondo-sync service account must be Administrator for user management endpoints |
| Sportlink functie sync | Add capabilities only (positive pass) | Always diff: grant caps to new holders AND revoke from former holders |
| WordPress user creation | `wp_insert_user()` without explicit `role` | Always pass `role` explicitly — never rely on WordPress default role setting |
| Password reset key expiry | 24h default not suitable for welcome emails | Apply `password_reset_expiration` filter with 7-day TTL for provisioning context |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| `get_users()` scan to find user by KNVB ID (user meta query) | Slow sync runs as user count grows | Index user meta: ensure `rondo_sportlink_id` is stored and queried with `compare => '='` (uses meta_value index) | > 500 users |
| `get_users(['capability' => 'financieel'])` to find all board members | Slow query — capability queries are full table scans in WordPress | Use role-based queries instead: `get_users(['role' => 'rondo_bestuur'])` | > 200 users |
| Running full user sync on every rondo-sync cycle | Unnecessary user meta writes even when nothing changed | Add hash-based change detection (same pattern as rondo-sync person sync) | Every sync run |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Unauthenticated password reset endpoint with no rate limiting | Email harassment of members, Lettermint quota exhaustion, reset key invalidation loop | Transient-based rate limit per IP + per email: 1 request per 5 minutes |
| Custom provisioning endpoint with `__return_true` permission callback | Any authenticated user can create WordPress users or change roles | Require `manage_options` capability on all user management endpoints |
| Error responses that include email or user data | Leaks member PII to unauthenticated callers | Return generic errors: "Invalid request" not "No user found with email X" |
| `wp_set_password()` without redirect after | Old sessions remain (sort of) valid; auth state confusion | Always redirect to login after password change; the session IS invalidated but client doesn't know |
| Storing KNVB ID (member number) in a public-facing field | PII leakage — KNVB IDs are considered personal data | Store in user meta (not person post ACF visible to all users); control access at API layer |
| Admin block via `.htaccess` without AJAX exemption | Breaks frontend AJAX — some hosts apply `.htaccess` rules before WordPress loads | Never use `.htaccess` for role-based redirects; use `admin_init` hook with `wp_doing_ajax()` guard |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Welcome email sent before person record is linked | User clicks app link, sees empty profile (no linked person) | Link person to user BEFORE sending welcome email in provisioning flow |
| Welcome email from no-reply address that can't receive replies | Member replies with questions, email bounces | Use a monitored address or explicit "do not reply, contact X" instruction |
| In-app password change with no session feedback | App appears broken after password change (silent 401s) | Explicit "You have been logged out. Please log in again." redirect |
| "Set your password" link in welcome email expires in 24h with no recovery | New members locked out of first login | 7-day expiry for activation links + "link expired" instructions |
| Profile page shows user's own person record in full edit mode | Member can edit their own data in ways that conflict with Sportlink sync | Show Sportlink-managed fields as read-only; allow editing only non-synced fields |

---

## "Looks Done But Isn't" Checklist

- [ ] **Admin block:** Log in as a `rondo_user`, navigate to `/wp-admin/` — verify redirect to home. Then verify REST API (`/wp-json/rondo/v1/dashboard`) still works. Then verify no broken AJAX in browser console.
- [ ] **Duplicate user guard:** Run provisioning sync twice for the same Sportlink member — verify exactly one WordPress user exists, exactly one welcome email sent (check `_welcome_email_sent` user meta).
- [ ] **Admin protection:** Confirm an `administrator` user's role and capabilities are unchanged after running the sync (they should not be touched at all).
- [ ] **Capability revocation:** Grant a user `rondo_bestuur` role, remove their Sportlink functie, run sync — verify they now have `rondo_user` role (or appropriate), no longer have `financieel` capability.
- [ ] **Welcome email from-address:** Check the welcome email headers — `From:` must be the configured Lettermint address, not `wordpress@rondo.svawc.nl`.
- [ ] **Stale link cleanup:** Create a user, link to a person, delete the person, verify `rondo_linked_person_id` user meta is cleared (or `get_linked_person` REST endpoint returns null).
- [ ] **Password reset expiry:** If welcome email uses a set-password link, verify the link expiry is > 24 hours. Send email, wait 25 hours, click — should either work (extended TTL) or show clear recovery instructions.
- [ ] **Password change session:** Change password via in-app profile form, verify app redirects to login, verify old session cookie no longer authenticates.
- [ ] **Role default:** Check a newly provisioned user's role in wp-admin — must be `rondo_user` (or mapped Rondo role), never `subscriber`.
- [ ] **Rate limiting:** Call the password reset endpoint 5 times in 1 minute for the same email — verify the 5th call returns 429.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Admin block catches admin-ajax.php, breaks plugins | LOW | Remove or fix the `admin_init` block with `wp_doing_ajax()` exemption; no data loss |
| Sync wiped admin capabilities | HIGH | WP-CLI `wp user add-role <user-id> administrator`; audit all admin users; change application passwords |
| Duplicate users created (sync matched wrong user) | MEDIUM | Merge users: copy user meta from duplicate to canonical; delete duplicate; update rondo-sync KNVB ID tracking |
| Welcome emails sent to all 500 members on re-sync | MEDIUM | Manually apologize; add `_welcome_email_sent` guard; no data corruption but reputation damage |
| Former board member retains financieel access for months | MEDIUM | Run `wp user remove-cap <id> financieel` for affected users; add revocation pass to sync |
| Password reset endpoint abused (email flood) | MEDIUM | Add rate limiting transient; Lettermint may temporarily block account — contact support |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Admin block catches admin-ajax.php | WP Admin Blocking phase | Test: non-admin user, AJAX call to `admin-ajax.php` returns expected response (not HTML) |
| Capability sync overwrites admin caps | Capability Sync phase | Test: run sync against administrator user, verify role unchanged |
| Capability sync is append-only (no revocation) | Capability Sync phase | Test: remove Sportlink functie, run sync, verify cap revoked |
| Duplicate users from sync re-runs | User Provisioning phase | Test: run sync twice, verify single user in DB, single welcome email |
| Welcome email from wrong address | User Provisioning phase (email) | Verify: email headers show Lettermint-configured from address |
| Password reset key expires before member acts | User Provisioning phase | Test: send welcome email, wait 25h, click link — verify outcome |
| Password change invalidates session without feedback | In-App Profile phase | Test: change password, verify redirect to login page |
| Stale person link after person deleted | User Provisioning + Profile phases | Test: delete person, verify user meta cleaned up |
| rondo-sync service account lacks user create rights | User Provisioning phase | Test: run sync with service account, verify no 403 errors |
| Role not set on user creation | User Provisioning phase | Verify: all new users have Rondo role (not `subscriber`) |
| Lettermint from-address on welcome email | User Provisioning phase | Verify: email headers show correct from-address |

---

## Sources

### HIGH Confidence (Codebase Analysis + Official Documentation)

- `includes/class-user-roles.php` — confirmed: `set_role()` replaces all capabilities; `add_cap()` / `remove_cap()` are per-user overrides; admin protection requires explicit guard
- `includes/class-access-control.php` — confirmed: `is_user_logged_in()` is the only gate; no capability-specific access control on data endpoints
- `functions.php` lines 701-707 — confirmed: `admin_init` hook is where admin blocking should go; existing codebase does not yet implement the block
- `functions.php` lines 604-645 (`rondo_get_js_config`) — confirmed: `currentUserPersonId` comes from `rondo_linked_person_id` user meta with no existence validation
- `includes/class-email-channel.php` lines 69-78 — confirmed: `add_filter` / `remove_filter` pattern for from-address; welcome email must follow same pattern
- [WordPress REST API Authentication Handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — CSRF via nonce, cookie auth scope, Application Passwords for external auth
- [wp_new_user_notification() Reference](https://developer.wordpress.org/reference/functions/wp_new_user_notification/) — filter hooks `wp_send_new_user_notification_to_user` and `wp_new_user_notification_email` confirmed
- [Wordfence: Stop Password-Protecting wp-admin (2014)](https://www.wordfence.com/blog/2014/05/please-stop-password-protecting-your-wp-admin-folder-because-it-breaks-public-ajax-for-wordpress/) — admin-ajax.php AJAX break is a well-documented classic pitfall; HIGH confidence

### MEDIUM Confidence (Official Docs + Community Verification)

- WordPress Developer Docs: [Roles and Capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/) — `set_role()` vs `add_cap()` behavior confirmed; user-level caps persist independently of role
- [WordPress.org Support: Block wp-admin for non-admins](https://wordpress.org/support/topic/block-access-to-wp-admin-and-wp-login-php-from-non-admins/) — `admin_init` + `wp_doing_ajax()` exemption confirmed as correct approach
- [Make WordPress Core: Reset Password Links in 5.7](https://make.wordpress.org/core/2021/02/22/send-reset-password-links-in-wordpress-5-7/) — `password_reset_expiration` filter confirmed; 24h default confirmed
- WordPress `wp_set_password()` source — updates password AND clears session tokens (invalidates all cookies)

### LOW Confidence (WebSearch Only — Validate During Implementation)

- Lettermint-specific sending limits and from-address validation behavior — verify directly in Lettermint dashboard before implementing welcome email
- SiteGround: whether server-level `.htaccess` rules for `/wp-admin/` need additional AJAX exemptions beyond the PHP-level hook
- Rate limiting transient approach for password reset endpoint — standard pattern but no authoritative WordPress documentation; validate with a real load test

---

*Pitfalls research for: User provisioning, capability auto-sync, WP admin blocking, in-app profile added to Rondo Club*
*Researched: 2026-02-20*
