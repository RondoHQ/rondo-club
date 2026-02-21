# Phase 205: User Provisioning - Research

**Researched:** 2026-02-20
**Domain:** WordPress user management, password-reset flow, bidirectional person-user linking, email templating
**Confidence:** HIGH

---

## Summary

Phase 205 adds admin-triggered user provisioning: from a person record an admin clicks "Maak account aan", a WordPress account is created, a branded welcome email with a 7-day password-set link is sent, and a bidirectional link between the user and person record is established. Re-triggering on an already-provisioned person is a no-op with no second email. Welcome email subject and body are configurable in Settings.

The phase is purely additive: new PHP class (`UserProvisioning`), a new REST endpoint, new person-record UI (button + status card), and a new "Welkomstmail" subtab in Settings > Beheer. No existing classes need structural changes other than the `get_users` endpoint to include linked person data.

**Primary recommendation:** Use WordPress's `get_password_reset_key()` (WP 6.0+, stable) to generate a cryptographically secure reset token for the password-set link. Store sent state on person post meta (`_welcome_email_sent_at`) and the bidirectional link via both `rondo_linked_person_id` user meta (already exists) and a new `_rondo_wp_user_id` post meta on the person. Follow the established `VOGEmail` pattern for email template storage and delivery. Follow the `FunctieCapabilityMap` pattern (static option-key config class) for welcome email settings.

---

## Standard Stack

### Core (no new dependencies needed)

| Component | Version/Function | Purpose | Why Standard |
|-----------|-----------------|---------|--------------|
| `wp_create_user()` | WP core | Create WordPress user | Standard WP user creation API |
| `get_password_reset_key()` | WP 6.0+ | Generate 7-day cryptographic reset token | Native, secure, ties to WP session storage |
| `network_site_url()` + `add_query_arg()` | WP core | Build the password-set URL | Standard WP login URL construction |
| `wp_mail()` | WP core via Lettermint | Send welcome email | Established email delivery channel |
| `wp_mail_from` / `wp_mail_from_name` filters | WP core | Override from address | VOGEmail pattern, already used throughout |
| `update_post_meta()` | WP core | Store `_rondo_wp_user_id` on person | Native WP post meta |
| `update_user_meta()` | WP core | Store `rondo_linked_person_id` on user | Already used, extend existing |
| `update_user_meta()` | WP core | Store `_rondo_knvb_id` on user | PROV-04: KNVB ID dedup key |

### Supporting

| Component | Purpose | When to Use |
|-----------|---------|-------------|
| `username_exists()` | Idempotency check by login name | Before creating user |
| `email_exists()` | Guard against duplicate email | Before creating user |
| `get_password_reset_key()` | Returns WP_Error if fails | Wrap in error check |
| `FunctieCapabilityMap::get_roles_for_functie()` | Phase 204 integration | Look up roles at provisioning time based on functie |
| `CommentTypes::create_email_log()` | Timeline audit trail | After sending welcome email |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `get_password_reset_key()` | Custom token in transient | WP native is preferred; handles expiry automatically (7 days by default in WP 6.0+, configurable via `password_reset_expiration` filter) |
| `wp_mail()` | Direct Lettermint API | wp_mail goes through Lettermint already (current setup); no value in bypassing it |
| Post meta for person→user link | ACF relationship field | Post meta is simpler, faster, and already the pattern for `rondo_linked_person_id` |

---

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── class-user-provisioning.php     # NEW: UserProvisioning service class
├── class-rest-api.php              # MODIFIED: add provisioning + provisioning settings endpoints
src/
├── components/
│   └── AccountCard.jsx             # NEW: person detail account status card (admin only)
├── pages/Settings/
│   └── Settings.jsx                # MODIFIED: add WelkomstmailTab subtab
├── api/client.js                   # MODIFIED: add provisionUser, getProvisioningSettings, updateProvisioningSettings
```

### Pattern 1: Meta Key Naming Convention

The phase establishes two new meta keys for the bidirectional link:

- **User meta:** `rondo_linked_person_id` (already exists — stores person post ID on user)
- **Person post meta:** `_rondo_wp_user_id` (NEW — stores WP user ID on person post; underscore prefix = hidden from ACF)
- **User meta for KNVB ID:** `_rondo_knvb_id` (NEW — PROV-04: enables sync dedup on email change)
- **Person post meta for welcome email:** `_welcome_email_sent_at` (NEW — date string, PROV-05 idempotency guard)

**Key insight on the link:** The `rondo_linked_person_id` user meta already exists and is used by the "Linked Person" feature (user self-service linking in Settings > Appearance). Provisioning MUST also set this meta so the provisioned user's person link is immediately active. There is currently NO back-link stored on the person side — this phase adds `_rondo_wp_user_id` as a new post meta.

### Pattern 2: WordPress Password Reset Key Flow

```php
// Source: WordPress core wp-login.php and pluggable.php
// Verified: WP 6.0+ introduces get_password_reset_key() returning WP_Error on failure

// Step 1: Create user
$user_id = wp_create_user( $login, wp_generate_password(), $email );

// Step 2: Generate reset key (valid ~7 days via WP_User_Request expiration)
$reset_key = get_password_reset_key( get_user_by( 'ID', $user_id ) );
if ( is_wp_error( $reset_key ) ) { /* handle */ }

// Step 3: Build login URL (same URL WordPress uses for password reset emails)
$login_url = add_query_arg(
    [
        'action' => 'rp',
        'key'    => $reset_key,
        'login'  => rawurlencode( $user->user_login ),
    ],
    network_site_url( 'wp-login.php', 'login' )
);
```

**Confidence:** HIGH — this is the exact pattern used by WordPress core `wp_new_user_notification()` in `pluggable.php`.

**7-day expiry:** By default `get_password_reset_key()` generates a key stored in `user_activation_key` user meta, with expiry controlled by `password_reset_expiration` filter (default 86400 seconds = 1 day in WP, but documented as "expires in 24 hours" in WP core). Research confirms the default is actually 24 hours in WP core. **The requirement says 7 days** — this means the welcome email template must state "geldig voor 7 dagen" but we need to override the expiry with the `password_reset_expiration` filter OR document the real expiry.

**IMPORTANT OPEN QUESTION:** WP's `get_password_reset_key()` default expiration is 24 hours, not 7 days. To set 7 days, add:
```php
add_filter( 'password_reset_expiration', function( $expiration ) {
    return 7 * DAY_IN_SECONDS; // 604800 seconds
});
```
This filter is global. A targeted approach scopes it only during provisioning. See Open Questions.

### Pattern 3: UserProvisioning Class Structure

Follow the `VOGEmail` pattern (stateless, option-key constants, service methods):

```php
namespace Rondo\Users;

class UserProvisioning {

    const OPTION_EMAIL_SUBJECT = 'rondo_welcome_email_subject';
    const OPTION_EMAIL_BODY    = 'rondo_welcome_email_body';
    const OPTION_FROM_EMAIL    = 'rondo_welcome_from_email';
    const OPTION_FROM_NAME     = 'rondo_welcome_from_name';

    const META_USER_ID      = '_rondo_wp_user_id';       // on person post
    const META_EMAIL_SENT   = '_welcome_email_sent_at';  // on person post (ISO datetime)
    const META_KNVB_ID      = '_rondo_knvb_id';          // on user (PROV-04)

    /**
     * Provision a WordPress user account for a person post.
     * Returns array with user_id and status ('created'|'already_exists')
     */
    public function provision( int $person_id ): array|\WP_Error { ... }

    /**
     * Send welcome email to newly provisioned user.
     * ONLY called if $status === 'created'.
     */
    private function send_welcome_email( int $person_id, int $user_id, string $reset_key ): true|\WP_Error { ... }

    /**
     * Get/update settings (subject, body, from_email, from_name)
     */
    public function get_settings(): array { ... }
    public function update_settings( array $settings ): bool { ... }
    public function get_subject(): string { ... }
    public function get_body(): string { ... }
    public function get_default_subject(): string { ... }
    public function get_default_body(): string { ... }
}
```

### Pattern 4: Username Generation

The person's first+last name is used to generate the login. Since WordPress logins must be unique:

```php
private function generate_username( string $first, string $last ): string {
    $base = sanitize_user( strtolower( $first . '.' . $last ), true );
    // strip diacritics: sanitize_user handles this
    $login = $base;
    $i = 1;
    while ( username_exists( $login ) ) {
        $login = $base . $i;
        $i++;
    }
    return $login;
}
```

If both first and last name are empty, fall back to person post ID: `gebruiker-{id}`.

### Pattern 5: Email Template with Substitution

Follow the `{variable}` substitution pattern established in `VOGEmail`:

```
{first_name}     → person first name
{email}          → generated WordPress login email
{login}          → generated WordPress username
{set_password_url} → the wp-login.php?action=rp&key=...&login=... URL
{club_naam}      → ClubConfig::get_club_name() fallback to get_bloginfo('name')
```

Default body template (Dutch, plain-text as HTML via nl2br):
```
Beste {first_name},

Je hebt een account gekregen voor {club_naam}.

Gebruik de volgende link om je wachtwoord in te stellen en in te loggen:
{set_password_url}

Je gebruikersnaam is: {login}

Deze link is 7 dagen geldig.

Met sportieve groet,
{club_naam}
```

### Pattern 6: Person Detail — Account Card

The `AccountCard` component (admin-only) is added to the second column of PersonDetail alongside the existing `SportlinkCard`. It:
- Shows current provisioning status: "Geen account" or "Account aangemaakt op {date}"
- If no account: shows "Maak account aan" button (only if person has email)
- If account: shows linked user email + "Welkomstmail opnieuw sturen is niet mogelijk" note
- Uses `config.isAdmin` for admin guard (same pattern as sync button)

### Pattern 7: REST Endpoint Design

New endpoints, added to the existing `class-rest-api.php` (or a new `class-rest-provisioning.php` if scope warrants):

```
POST /rondo/v1/people/{person_id}/provision
  Permission: check_admin_permission
  Body: (none required)
  Response: { success, user_id, status, message }
  Status codes: 200 (created), 200 (already_exists idempotent), 4xx on error

GET  /rondo/v1/provisioning/settings
  Permission: check_admin_permission
  Response: { subject, body, from_email, from_name }

POST /rondo/v1/provisioning/settings
  Permission: check_admin_permission
  Body: { subject?, body?, from_email?, from_name? }
  Response: updated settings
```

The person endpoint also returns `_rondo_wp_user_id` and `_welcome_email_sent_at` so the frontend knows provisioning state without a separate call.

### Pattern 8: Settings — New Admin Subtab

Add `{ id: 'welkomstmail', label: 'Welkomstmail' }` to `ADMIN_SUBTABS` in `Settings.jsx`. The `WelkomstmailTab` component follows the `FunctiesTab` pattern (inline inside Settings.jsx or extracted to a separate file if large).

The tab shows:
- From email and from name fields
- Subject field
- Body textarea (multi-line, `{variable}` substitution explained inline)
- Save button

### Anti-Patterns to Avoid

- **Sending `wp_new_user_notification()`:** WP built-in sends its own admin notification email. Do NOT call it — it sends a separate "new user registered" email to the admin, which is unwanted. Create user silently, then send our own branded email.
- **Using `wp_set_password()`:** This immediately sets a known password, which is insecure. Use `get_password_reset_key()` so the user sets their own password.
- **Not deduplicating by KNVB ID:** PROV-04 requires storing `_rondo_knvb_id` on the user so that if a member later changes their email address in Sportlink, rondo-sync can still find the correct WP user by KNVB ID rather than email.
- **Sending welcome email on re-trigger:** PROV-05 requires checking `_welcome_email_sent_at` post meta. If set, skip email send entirely (return `already_exists` status). Do NOT offer a "resend" button.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password reset token | Custom random string in transient | `get_password_reset_key()` | Handles hashing, storage in `user_activation_key`, WP password reset screen validates it natively |
| Login URL construction | Custom URL builder | `network_site_url('wp-login.php')` + `add_query_arg()` | WP core handles multisite correctly |
| Email HTML wrapping | Custom HTML email builder | `nl2br(esc_html($text))` | Existing pattern (VOGEmail), consistent with rest of email system |
| Username deduplication | Complex slug logic | Simple `username_exists()` loop with incrementing suffix | Sufficient for low-frequency provisioning |

**Key insight:** WordPress already has all primitives for this. The value of this phase is wiring them together with branded templates and a clean UI, not building infrastructure.

---

## Common Pitfalls

### Pitfall 1: wp_new_user_notification() Side Effects
**What goes wrong:** Calling `wp_new_user_notification()` sends an admin notification email AND a user notification email with a password-set link — but they use WordPress's own template, not the branded one. Also sends double email.
**Why it happens:** WP sends two emails on registration by default.
**How to avoid:** Do NOT call `wp_new_user_notification()`. Create user with `wp_create_user()`, generate key manually with `get_password_reset_key()`, send our own branded email via `wp_mail()`.
**Warning signs:** Two emails received by admin when provisioning.

### Pitfall 2: Password Reset Key Expiry Mismatch
**What goes wrong:** Template says "7 dagen geldig" but WP default is 24 hours.
**Why it happens:** WP core `password_reset_expiration` filter defaults to `DAY_IN_SECONDS` (86400).
**How to avoid:** Apply the `password_reset_expiration` filter ONLY during provisioning (add/remove pattern used in VOGEmail for from_email filter), OR accept 24-hour expiry and update copy to "24 uur geldig." The requirement says 7 days — we should either override or discuss.
**Warning signs:** User gets "invalid key" error more than 24 hours after provisioning.

### Pitfall 3: Person Has No Email Address
**What goes wrong:** `wp_create_user()` is called with empty email, fails silently or creates invalid user.
**Why it happens:** Not all person records have a contact_info email set (especially older records synced before email was populated).
**How to avoid:** Check `get_person_email()` (same logic as VOGEmail) BEFORE attempting provisioning. Return `WP_Error('no_email', ...)` if no email found. Show clear message in UI.
**Warning signs:** WP_Error from `email_exists()` or failed user creation.

### Pitfall 4: Duplicate Email — User Already Exists with Different Login
**What goes wrong:** A WP user with the same email already exists but is NOT linked to this person (e.g., admin created them manually earlier).
**Why it happens:** `email_exists()` returns user ID of existing user, not false.
**How to avoid:** Check `email_exists()` BEFORE creating. If email is taken, two options: (a) link the existing user to the person and return `already_exists`, or (b) return an error asking admin to resolve the conflict. Given PROV-04, the safest approach is (b) — return a clear error so admin can manually link.
**Warning signs:** `email_exists()` returns truthy but user is not already linked to this person.

### Pitfall 5: Bidirectional Link Out of Sync
**What goes wrong:** `_rondo_wp_user_id` is set on person but `rondo_linked_person_id` is NOT set on user (or vice versa), causing inconsistent state.
**Why it happens:** Setting one meta succeeds but the second call fails (e.g., database error).
**How to avoid:** Set BOTH metas atomically (PHP can't do true transactions in WP, but both calls should be in the same request with error checking).

### Pitfall 6: Rondo-sync Service Account Runs Provisioning Check
**What goes wrong:** The rondo-sync service account (which is an administrator) can trigger provisioning via the REST API.
**Why it happens:** The endpoint uses `check_admin_permission` which checks `manage_options`.
**How to avoid:** Documented in STATE.md: verify rondo-sync service account exists as administrator before the provisioning endpoint runs. No special code needed beyond what `check_admin_permission` already provides.

---

## Code Examples

Verified patterns from WordPress core and existing codebase:

### Creating WP User + Generating Password Reset Link
```php
// Source: WP core pluggable.php (wp_new_user_notification) + existing VOGEmail pattern

$user_id = wp_create_user( $login, wp_generate_password(), $email );
if ( is_wp_error( $user_id ) ) {
    return $user_id;
}

// Set role to rondo_user (default for new provisioned users)
$user = new \WP_User( $user_id );
$user->set_role( \Rondo\Core\UserRoles::ROLE_NAME ); // 'rondo_user'

// Generate password reset key
$reset_key = get_password_reset_key( $user );
if ( is_wp_error( $reset_key ) ) {
    wp_delete_user( $user_id ); // rollback
    return $reset_key;
}

// Build password-set URL (same as WP core new user notification)
$set_password_url = add_query_arg(
    [
        'action' => 'rp',
        'key'    => $reset_key,
        'login'  => rawurlencode( $user->user_login ),
    ],
    network_site_url( 'wp-login.php', 'login' )
);
```

### Bidirectional Link Storage
```php
// User → Person (already used by linked-person feature)
update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

// Person → User (new in Phase 205)
update_post_meta( $person_id, '_rondo_wp_user_id', $user_id );

// KNVB ID for sync dedup (PROV-04)
$knvb_id = get_field( 'knvb-id', $person_id );
if ( $knvb_id ) {
    update_user_meta( $user_id, '_rondo_knvb_id', $knvb_id );
}
```

### Idempotency Guard (PROV-05)
```php
$existing_user_id = (int) get_post_meta( $person_id, '_rondo_wp_user_id', true );
if ( $existing_user_id && get_userdata( $existing_user_id ) ) {
    return [
        'status'  => 'already_exists',
        'user_id' => $existing_user_id,
        'message' => 'Gebruiker bestaat al.',
    ];
}
```

### Email Sending Pattern (from VOGEmail)
```php
// Set custom from address during send only
$this->current_from_email = $this->get_from_email();
add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
$result  = wp_mail( $email, $subject, nl2br( esc_html( $body ) ), $headers );

remove_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
remove_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

if ( ! $result ) {
    return new \WP_Error( 'send_failed', 'Welkomstmail kon niet verstuurd worden.' );
}

// Log to timeline
update_post_meta( $person_id, '_welcome_email_sent_at', current_time( 'Y-m-d H:i:s' ) );
$comment_types = new \Rondo\Collaboration\CommentTypes();
$comment_types->create_email_log( $person_id, [
    'template_type' => 'welcome',
    'recipient'     => $email,
    'subject'       => $subject,
    'content'       => nl2br( esc_html( $body ) ),
]);
```

### Role Assignment from Phase 204 (FunctieCapabilityMap)
```php
// At provisioning time, assign roles based on Functies
$functies = get_field( 'werkfunctie', $person_id ); // array of Sportlink functies
$user = get_userdata( $user_id );
$user->set_role( \Rondo\Core\UserRoles::ROLE_NAME ); // base role first

foreach ( (array) $functies as $functie ) {
    $roles = \Rondo\Config\FunctieCapabilityMap::get_roles_for_functie( $functie );
    foreach ( $roles as $role_slug ) {
        $user->add_role( $role_slug );
    }
}
```

**Important:** "Werkfuncties" are NOT a separate ACF field on person. They are derived from `work_history[].job_title` (job titles in the work history repeater). The `get_available_werkfuncties()` method in class-rest-api.php already shows this pattern: iterate `get_field('work_history', $person_id)`, collect `$position['job_title']`.

```php
// Correct werkfuncties access pattern (from class-rest-api.php get_available_werkfuncties)
$work_history = get_field( 'work_history', $person_id ) ?: [];
$functies = [];
foreach ( $work_history as $position ) {
    if ( ! empty( $position['job_title'] ) ) {
        $functies[] = trim( $position['job_title'] );
    }
}
// Then look up roles for each:
foreach ( array_unique( $functies ) as $functie ) {
    $roles = \Rondo\Config\FunctieCapabilityMap::get_roles_for_functie( $functie );
    foreach ( $roles as $role_slug ) {
        $user->add_role( $role_slug );
    }
}
```

### Frontend: Provision Action in AccountCard
```jsx
// Admin-only component on PersonDetail, second column, above or below SportlinkCard
const handleProvision = async () => {
  setProvisioning(true);
  try {
    const response = await prmApi.provisionUser(id);
    setProvisionStatus(response.data);
    queryClient.invalidateQueries(['people', 'detail', id]);
  } catch (err) {
    setError(err.response?.data?.message || 'Kon account niet aanmaken');
  } finally {
    setProvisioning(false);
  }
};
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `wp_new_user_notification()` | Manual `get_password_reset_key()` + `wp_mail()` | This phase | Branded email, no double-send |
| No person→user link | `_rondo_wp_user_id` post meta + `rondo_linked_person_id` user meta | This phase | Bidirectional navigation |
| No idempotency | `_welcome_email_sent_at` guard | This phase | Safe re-trigger |

**Note on `password_reset_expiration` filter:** Added in WP 4.4 (2015). DEFAULT is `DAY_IN_SECONDS` (24 hours). Changing it globally affects all password resets on the site. Recommendation: scope the override using add/remove filter pattern during provisioning.

---

## Key Existing Patterns to Follow

### Email Infrastructure (already in place)
- Email delivery via Lettermint (configured in WordPress plugin), flows through `wp_mail()`
- `wp_mail_from` and `wp_mail_from_name` filter pattern for custom sender (used in VOGEmail, EmailChannel)
- Timeline logging via `CommentTypes::create_email_log()` (used in VOGEmail)
- Template variables using `{variable}` substitution (used in VOGEmail)

### Settings Storage (already in place)
- WordPress Options API with `const OPTION_*` constants (VOGEmail, ClubConfig, FinanceConfig)
- Static utility class with `get_*()` and `update_*()` methods (ClubConfig, FunctieCapabilityMap)
- REST endpoints GET+POST same path with permission `check_admin_permission` (VOG settings)

### Admin-only Person Detail Elements (already in place)
- `config.isAdmin` check for conditional rendering (sync button uses this)
- `useCurrentUser()` hook for capability checks (FinancesCard, VOGCard use `can_access_*`)
- Second column of PersonDetail already has SportlinkCard; AccountCard goes in same column

### User Management (already in place)
- `UserRoles::ROLE_NAME = 'rondo_user'` for base role
- `UserRoles::ROLES` for all role slugs
- `check_admin_permission()` for admin-only REST endpoints
- `get_users()` endpoint returns basic user list — needs enhancement to include `linked_person_id`

---

## Open Questions

1. **Password reset key expiry — 7 days vs 24 hours**
   - What we know: WP default is 24 hours via `password_reset_expiration` filter
   - What's unclear: Should we override globally (affects all WP password resets) or accept 24 hours and update copy?
   - Recommendation: Override with add/remove filter pattern scoped to provisioning only, or change copy to "24 uur geldig." Best option is to simply use the filter scoped to the provisioning call using add_filter / remove_filter.

2. **werkfunctie ACF field structure — RESOLVED**
   - Werkfuncties are NOT a separate ACF field. They come from `work_history[].job_title` in the work_history repeater on person posts. This is confirmed by `get_available_werkfuncties()` in class-rest-api.php.
   - Access pattern: `get_field('work_history', $person_id)` returns array of `['team', 'job_title', 'start_date', 'end_date', 'is_current']` entries. Extract all `job_title` values (not just `is_current=true`) to get the full list of functies.
   - Recommendation: At provisioning time, iterate all work_history entries, collect unique `job_title` strings, run each through `FunctieCapabilityMap::get_roles_for_functie()`, add resulting role slugs to the user.

3. **AccountCard placement on PersonDetail**
   - What we know: Second column currently has SportlinkCard, then Relationships, then VOGCard
   - What's unclear: Should AccountCard appear before or after SportlinkCard?
   - Recommendation: After SportlinkCard (only visible to admins anyway, so doesn't affect regular users' view).

4. **get_users endpoint enhancement**
   - What we know: Current `GET /rondo/v1/users` returns `id, name, email, registered` — no linked person info
   - What's unclear: Should the users list in Settings > Beheer > Gebruikers also show which person record is linked?
   - Recommendation: Add `linked_person_id` and `linked_person_name` to the users list response. This does NOT change the response format destructively (just adds fields), and helps admins see provisioning status in the user list too. If this scope creep feels too big, defer to a follow-up phase.

5. **What happens to person post author on provisioning?**
   - What we know: Person posts have a `post_author` (the WP user who created the person record)
   - What's unclear: Should `post_author` be changed to the provisioned user (so they "own" their own record)?
   - Recommendation: Do NOT change `post_author`. Rondo's access model is shared — all logged-in users see all persons. Changing authorship would affect ownership-based filtering if that's ever used. Keep `post_author` as the admin who created the record; use the bidirectional meta for the link.

---

## Implementation Plan Summary

### Phase 205-01: PHP Backend + REST API

**File: `includes/class-user-provisioning.php`** (NEW, ~200 lines)
- Namespace: `Rondo\Users`
- Static option constants for settings
- Static meta constants for person/user link
- `provision($person_id)`: orchestrates creation, linking, email, returns status array or WP_Error
- `send_welcome_email($person_id, $user_id, $reset_key)`: builds and sends branded email
- `get_settings()`, `update_settings()`: options API CRUD
- `get_default_subject()`, `get_default_body()`: Dutch default templates

**File: `includes/class-rest-api.php`** (MODIFIED)
- Register `POST /rondo/v1/people/{person_id}/provision` (admin only)
- Register `GET /rondo/v1/provisioning/settings` (admin only)
- Register `POST /rondo/v1/provisioning/settings` (admin only)
- Implement callback methods (or delegate to UserProvisioning)
- Enhance `get_users()` to include `linked_person_id`, `linked_person_name`

**File: `functions.php`** (MODIFIED — add UserProvisioning to REST init block)

### Phase 205-02: PersonDetail AccountCard UI

**File: `src/components/AccountCard.jsx`** (NEW)
- Admin-only (guard with `config.isAdmin`)
- Shows: provisioning status, linked user email if provisioned, "Maak account aan" button
- Calls `prmApi.provisionUser(personId)` on click
- Disables button if person has no email (with tooltip explanation)

**File: `src/pages/People/PersonDetail.jsx`** (MODIFIED)
- Import and render `<AccountCard />` in second column (admin only, after SportlinkCard)
- Add provisioning state reading from person ACF/meta response

**Note:** The `_rondo_wp_user_id` and `_welcome_email_sent_at` meta values need to be exposed in the person REST response. Best approach: add to `rest_prepare_person` filter in a REST filter similar to `add_person_computed_fields`, OR return them in the provisioning endpoint response and cache locally. Recommend: expose in person REST response as top-level fields `linked_user_id` and `welcome_email_sent_at`.

### Phase 205-03: Settings Welkomstmail Subtab

**File: `src/pages/Settings/Settings.jsx`** (MODIFIED)
- Add `{ id: 'welkomstmail', label: 'Welkomstmail' }` to `ADMIN_SUBTABS`
- Add WelkomstmailTab component (inline or extracted)
- Fetch settings on mount, save on button click
- Fields: from_email, from_name, subject, body (textarea with variable hints)

**File: `src/api/client.js`** (MODIFIED)
- Add `provisionUser(personId)` → `POST /rondo/v1/people/{personId}/provision`
- Add `getProvisioningSettings()` → `GET /rondo/v1/provisioning/settings`
- Add `updateProvisioningSettings(data)` → `POST /rondo/v1/provisioning/settings`

---

## Sources

### Primary (HIGH confidence)
- Codebase: `includes/class-vog-email.php` — email template pattern, filter-based from address
- Codebase: `includes/class-user-roles.php` — role slugs, `ROLE_NAME` constant
- Codebase: `includes/class-rest-api.php` — `get_linked_person()`, `update_linked_person()`, `rondo_linked_person_id` user meta
- Codebase: `includes/class-rest-api.php` — `get_users()`, `delete_user()`, existing user management
- Codebase: `includes/class-rest-api.php` — `get_current_user()` response shape, `is_admin` field
- Codebase: `includes/class-functie-capability-map.php` — static option class pattern
- Codebase: `includes/class-rest-base.php` — `check_admin_permission()` method
- Codebase: `src/pages/Settings/Settings.jsx` — `ADMIN_SUBTABS` pattern, FunctiesTab integration
- Codebase: `src/pages/People/PersonDetail.jsx` — `config.isAdmin` guard, column layout
- Codebase: `src/api/client.js` — `prmApi` method naming conventions
- Codebase: `includes/class-comment-types.php` — `create_email_log()` for timeline audit

### Secondary (MEDIUM confidence)
- WordPress Core knowledge: `get_password_reset_key()`, `network_site_url()`, `wp_create_user()`, `username_exists()`, `email_exists()` — verified to exist in WP 6.0+ via function signatures and training knowledge
- WordPress Core knowledge: `password_reset_expiration` filter — documented filter that controls reset key TTL

### Tertiary (LOW confidence)
- `werkfunctie` ACF field access pattern — NOT verified against acf-json/; needs checking before role-assignment code is written
- 7-day vs 24-hour expiry specifics — needs testing on production WP version

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all WordPress native, no new libraries
- Architecture: HIGH — directly extends existing patterns (VOGEmail, FunctieCapabilityMap, linked-person)
- Pitfalls: HIGH — grounded in actual WP behavior and codebase analysis
- Email expiry question: MEDIUM — needs validation against actual WP version on server

**Research date:** 2026-02-20
**Valid until:** 2026-03-22 (30 days — stable WP APIs)
