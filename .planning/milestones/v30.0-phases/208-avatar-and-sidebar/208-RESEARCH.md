# Phase 208: Avatar & Sidebar - Research

**Researched:** 2026-02-20
**Domain:** React sidebar component, WordPress REST API extension, person photo fetch for current user
**Confidence:** HIGH

---

## Summary

Phase 208 shows the linked Sportlink person's profile photo as the user avatar inside the app sidebar. The `GET /rondo/v1/user/me` endpoint already fetches the linked person (via `rondo_linked_person_id` user meta) and returns `linked_person_name` and `active_functies`, but it does **not** currently return the person's featured image (photo). Adding `linked_person_photo` to the `/user/me` response is a one-line PHP change using the already-present `$person_id` variable and `get_the_post_thumbnail_url()`.

The frontend currently has two avatar locations: the `UserMenu` in the top-right header (already shows `user.avatar_url` — a Gravatar), and the `Sidebar` bottom section which shows only a logout link with no user identity at all. Phase 208 specifically calls for avatar in the **sidebar**. The plan description says "208-01: Sidebar avatar component reading linked person photo from /user/me, fallback default icon" — meaning a new sidebar user identity area at the bottom replaces or augments the logout link area.

The work is small and self-contained: one PHP field added to `/user/me`, and a sidebar bottom section redesign in `Layout.jsx`. No new files are strictly required — everything can be done by modifying two existing files.

**Primary recommendation:** Add `linked_person_photo` to `get_current_user()` in `class-rest-api.php`, then redesign the `Sidebar` footer area in `Layout.jsx` to show a mini user-identity block (avatar + name + logout). Use `linked_person_photo ?? null` as the avatar source (no Gravatar fallback in sidebar — use a default icon component instead per AVTR-02).

---

## Standard Stack

### Core (no new dependencies)

| Component | Version/Function | Purpose | Why Standard |
|-----------|-----------------|---------|--------------|
| `get_the_post_thumbnail_url($person_id, 'thumbnail')` | WP core | Fetch person's featured image URL | Already used in `class-rest-people.php` and search results for person thumbnails |
| `useCurrentUser()` hook | Project hook | Fetch user data including new field | Already used by `Sidebar`, `UserMenu`, and `Profile.jsx` — no new hook needed |
| `User` icon from lucide-react | Already imported | Default avatar fallback | Already imported in `Layout.jsx` line 18 |
| `Link` from react-router-dom | Already imported | Profile page link | Already imported in `Layout.jsx` line 2 |

### Supporting

| Component | Purpose | When to Use |
|-----------|---------|-------------|
| `get_the_post_thumbnail_url($id, 'thumbnail')` | Returns WP thumbnail-size URL | Person photo at ~150×150px, appropriate for sidebar avatar |
| `get_the_post_thumbnail_url($id, 'medium')` | Returns larger URL | Overly large for sidebar — stick with `thumbnail` |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Add to `/user/me` | Separate `/user/linked-person-photo` endpoint | More endpoints for a simple field. Not DRY. `/user/me` already fetches person data. |
| `linked_person_photo` field name | `person_thumbnail` or `avatar_photo` | `linked_person_photo` is most descriptive given existing field names (`linked_person_name`, `active_functies`) |
| Default `User` icon | Gravatar (`avatar_url`) as sidebar fallback | Requirements (AVTR-02) say "default avatar icon" not Gravatar. Icon is simpler and avoids an extra HTTP request. |

**Installation:** No new packages needed.

---

## Architecture Patterns

### Current State

```
GET /rondo/v1/user/me  →  {
  id, name, email,
  avatar_url,              ← Gravatar (already exists)
  is_admin, can_access_*,
  profile_url, admin_url,
  linked_person_name,      ← Already added in Phase 207
  active_functies          ← Already added in Phase 207
  // MISSING: linked_person_photo
}
```

```
Layout.jsx Sidebar (bottom)  →  logout link only (no user identity)
Layout.jsx UserMenu (header) →  shows user.avatar_url (Gravatar) with initials fallback
```

### Target State

```
GET /rondo/v1/user/me  →  {
  ...existing fields...,
  linked_person_photo: "https://..." | null   ← NEW
}
```

```
Layout.jsx Sidebar (bottom)  →  [avatar img OR User icon] + name + logout link
Layout.jsx UserMenu (header) →  unchanged (still uses avatar_url/Gravatar)
```

### Pattern 1: Adding a Field to `/user/me`

**What:** Extend the `get_current_user()` method in `class-rest-api.php` to include the person photo.

**Where:** Lines 2721-2739 (the `if ($person_id)` block that already loads person data). The `$person_id` variable is already resolved. Just call `get_the_post_thumbnail_url()` inside the same block.

**Example:**
```php
// Source: class-rest-api.php — existing get_current_user() method, line ~2725-2738
// Pattern from class-rest-people.php line 491: get_the_post_thumbnail_url($person_id, 'thumbnail')

$linked_person_photo = null;  // default
if ( $person_id ) {
    $person = get_post( $person_id );
    if ( $person && 'person' === $person->post_type ) {
        $first              = get_field( 'first_name', $person_id ) ?: '';
        $last               = get_field( 'last_name', $person_id ) ?: '';
        $linked_person_name = trim( $first . ' ' . $last ) ?: null;
        $linked_person_photo = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;

        $work_history = get_field( 'work_history', $person_id ) ?: [];
        foreach ( $work_history as $job ) {
            if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
                $active_functies[] = $job['job_title'];
            }
        }
    }
}

// In response array, add:
'linked_person_photo' => $linked_person_photo,
```

**Confidence:** HIGH — `get_the_post_thumbnail_url($person_id, 'thumbnail')` is already called in `class-rest-people.php` at line 491 and in `upload_person_photo()` at line 423 with identical purpose.

### Pattern 2: Sidebar User Identity Block

**What:** Replace (or augment) the existing logout-only footer area in `Sidebar` with a mini user identity section.

**Current sidebar footer (lines 170-179):**
```jsx
<div className="p-4 border-t border-gray-200 dark:border-gray-700">
  <a href={logoutUrl} ...>
    <LogOut className="w-5 h-5 mr-3" />
    Uitloggen
  </a>
</div>
```

**Target sidebar footer pattern:**
```jsx
// Source: Existing UserMenu pattern in Layout.jsx (lines 206-270)
// The Sidebar already calls useCurrentUser() at line 63 — no extra fetch
const { data: currentUser } = useCurrentUser(); // already present

// New footer:
<div className="p-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
  {/* User identity row */}
  <div className="flex items-center gap-3 px-1">
    {currentUser?.linked_person_photo ? (
      <img
        src={currentUser.linked_person_photo}
        alt={currentUser.name}
        className="w-8 h-8 rounded-full object-cover flex-shrink-0"
      />
    ) : (
      <div className="w-8 h-8 rounded-full bg-cyan-100 dark:bg-obsidian flex items-center justify-center flex-shrink-0">
        <User className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan-light" />
      </div>
    )}
    <span className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
      {currentUser?.name}
    </span>
  </div>
  {/* Logout */}
  <a href={logoutUrl} ...>
    <LogOut className="w-5 h-5 mr-3" />
    Uitloggen
  </a>
</div>
```

**Note on `useCurrentUser()` in Sidebar:** The `Sidebar` component already calls `const { data: currentUser } = useCurrentUser()` at line 63 for capability checks (`canAccessFairplay`, etc.). No additional hook call is needed — `currentUser` is already available in scope, and `useCurrentUser` uses a shared TanStack Query cache (queryKey `['current-user']`) so it's a zero-cost read.

### Pattern 3: Null/Loading Safety

**What:** Guard against `currentUser` being undefined while loading.

**When to use:** `Sidebar` is rendered before `currentUser` loads. The avatar section must handle the loading state without layout shift.

**Example:**
```jsx
// During loading, show placeholder avatar (same size as real avatar to prevent layout shift)
{currentUser?.linked_person_photo ? (
  <img src={currentUser.linked_person_photo} ... />
) : (
  <div className="w-8 h-8 rounded-full bg-cyan-100 dark:bg-obsidian flex items-center justify-center">
    <User className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan-light" />
  </div>
)}
// Show name only when loaded (avoid showing "undefined" or "null")
{currentUser?.name && (
  <span ...>{currentUser.name}</span>
)}
```

This means the default icon doubles as both the "no photo" state (AVTR-02) and the loading state. No separate skeleton needed.

### Anti-Patterns to Avoid

- **Fetching person data separately:** The person photo should come from `/user/me`, not from a separate `/wp/v2/people/{id}` call. Avoids an extra network request and keeps the sidebar light.
- **Adding `avatar_url` (Gravatar) as sidebar fallback:** AVTR-02 requires a "default avatar icon", not Gravatar. Keep Gravatar only in the existing `UserMenu` header component where it already works.
- **Duplicating `useCurrentUser()` call:** `Sidebar` already calls it — just use `currentUser` directly.
- **Changing `UserMenu` behavior:** The header `UserMenu` is not within scope. Leave its `avatar_url`/Gravatar logic intact.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Image size/format | Custom image resizing | `get_the_post_thumbnail_url($id, 'thumbnail')` | WP registers thumbnail size (150×150) at media upload time, already cropped |
| User data fetch | New hook or API call | Existing `useCurrentUser()` (shared cache) | Cache hit — zero additional HTTP requests |
| Avatar fallback | Custom SVG or base64 | `User` icon from lucide-react (already imported) | Consistent with rest of UI, already available |

**Key insight:** This phase is almost entirely wiring existing pieces together. The only new code is: (1) one PHP line in `get_current_user()`, and (2) a sidebar footer redesign in `Layout.jsx`.

---

## Common Pitfalls

### Pitfall 1: `get_the_post_thumbnail_url()` Returns `false`, Not `null`

**What goes wrong:** PHP's `get_the_post_thumbnail_url()` returns `false` (boolean) when no thumbnail is set, not `null`. Passing `false` to the REST API response means the JSON encodes it as `false`, which is truthy-ish in JavaScript (`if (user.linked_person_photo)` would be falsy, but JSON.stringify gives `false` not `null`).

**Why it happens:** WP function returns `false` on no attachment.

**How to avoid:** Use the `?: null` idiom already used in this codebase:
```php
$linked_person_photo = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;
```
This collapses `false` to `null`, which serializes cleanly as `null` in JSON.

**Warning signs:** `user.linked_person_photo === false` in JS instead of `null`.

### Pitfall 2: Sidebar Already Has `currentUser` — Don't Double-Fetch

**What goes wrong:** Adding another `const { data: currentUser } = useCurrentUser()` inside the `Sidebar` function body, or passing user data as a prop unnecessarily.

**Why it happens:** Not noticing the existing `useCurrentUser()` call at line 63.

**How to avoid:** The `Sidebar` function already calls `useCurrentUser()` at line 63 and stores the result in `currentUser`. Just extend the existing destructure or use `currentUser` directly.

### Pitfall 3: Layout Shift from Loading State

**What goes wrong:** If the avatar area renders as "nothing" while loading, then expands when data arrives, it causes a visible layout shift.

**Why it happens:** Conditional render of `null` before data loads.

**How to avoid:** Always render a fixed-size element. Use the `User` icon placeholder (same 32×32 dimensions as the real avatar) during loading and as fallback.

### Pitfall 4: `staleTime` Already 5 Minutes — No Cache Concern

**What goes wrong:** Worrying that `linked_person_photo` won't update when a person photo changes.

**Why it happens:** `useCurrentUser` has `staleTime: 5 * 60 * 1000` (5 minutes).

**How to handle:** The 5-minute stale time is acceptable for an avatar. A photo change is rare. If the photo changes, the user will see the new photo after the next page refresh or after 5 minutes. This is fine per requirements.

---

## Code Examples

Verified patterns from the codebase:

### PHP: Adding Photo to /user/me Response

```php
// Source: includes/class-rest-api.php get_current_user() — lines 2721-2756
// EXISTING code (lines 2722-2739), with addition of linked_person_photo:

$person_id           = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
$linked_person_name  = null;
$linked_person_photo = null;  // NEW
$active_functies     = [];

if ( $person_id ) {
    $person = get_post( $person_id );
    if ( $person && 'person' === $person->post_type ) {
        $first               = get_field( 'first_name', $person_id ) ?: '';
        $last                = get_field( 'last_name', $person_id ) ?: '';
        $linked_person_name  = trim( $first . ' ' . $last ) ?: null;
        $linked_person_photo = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;  // NEW

        $work_history = get_field( 'work_history', $person_id ) ?: [];
        foreach ( $work_history as $job ) {
            if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
                $active_functies[] = $job['job_title'];
            }
        }
    }
}

// In the return array, add:
'linked_person_photo' => $linked_person_photo,  // NEW
```

### React: Sidebar Footer with Avatar

```jsx
// Source: src/components/layout/Layout.jsx — Sidebar function
// currentUser is already available from existing line 63: const { data: currentUser } = useCurrentUser();

{/* Sidebar footer — user identity + logout */}
<div className="p-4 border-t border-gray-200 dark:border-gray-700">
  {/* User identity row */}
  <div className="flex items-center gap-3 px-1 mb-3">
    {currentUser?.linked_person_photo ? (
      <img
        src={currentUser.linked_person_photo}
        alt={currentUser?.name || ''}
        className="w-8 h-8 rounded-full object-cover flex-shrink-0"
      />
    ) : (
      <div className="w-8 h-8 rounded-full bg-cyan-100 dark:bg-obsidian flex items-center justify-center flex-shrink-0">
        <User className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan-light" />
      </div>
    )}
    {currentUser?.name && (
      <span className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
        {currentUser.name}
      </span>
    )}
  </div>
  {/* Logout */}
  <a
    href={logoutUrl}
    className="flex items-center px-3 py-2 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition-colors dark:text-gray-200 dark:hover:bg-gray-700"
  >
    <LogOut className="w-5 h-5 mr-3" />
    Uitloggen
  </a>
</div>
```

### PHP: Verified thumbnail pattern (already in codebase)

```php
// Source: includes/class-rest-people.php line 491 — identical usage for person search results
$person_thumbnail = get_the_post_thumbnail_url( $person_id, 'thumbnail' );

// Source: includes/class-rest-people.php line 423 — identical in upload_person_photo response
'thumbnail_url' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ),
'full_url'      => get_the_post_thumbnail_url( $person_id, 'full' ),
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| Sidebar has no user identity (just logout) | Add avatar + name to sidebar footer | User sees themselves in the nav; standard SaaS pattern |
| `/user/me` returns Gravatar only | Add `linked_person_photo` (Sportlink photo) | More relevant photo vs generic Gravatar |
| `UserMenu` (header) has Gravatar avatar | Unchanged — Gravatar remains in header | Header UserMenu not in scope for this phase |

**Note on avatar_url vs linked_person_photo:** The `avatar_url` field in `/user/me` is Gravatar (from `get_avatar_url()`). This is an email-hash-based external service. The `linked_person_photo` is the actual Sportlink profile photo stored in WordPress media. For the sidebar avatar, use `linked_person_photo` per the requirements. The existing `UserMenu` in the header will continue using `avatar_url` (Gravatar) unchanged — that is out of scope.

---

## Open Questions

1. **Should `UserMenu` (header) also be updated to prefer `linked_person_photo` over Gravatar?**
   - What we know: Phase description says "sidebar avatar." The plan `208-01` says "Sidebar avatar component." The `UserMenu` in the header is separate.
   - What's unclear: Whether updating the header `UserMenu` to also use `linked_person_photo` is in scope.
   - Recommendation: Stick to sidebar only per the plan description. The header `UserMenu` already shows Gravatar and is not mentioned in AVTR-01/AVTR-02.

2. **Should the sidebar avatar/name be a link to `/profile`?**
   - What we know: Phase 207 added the `/profile` route. The prior decisions note says "Phase 207 Plan 01: Non-admin UserMenu links to /profile."
   - What's unclear: Whether the sidebar identity block should link to `/profile` in addition to the `UserMenu` dropdown already doing so.
   - Recommendation: Yes — clicking the name or avatar in the sidebar should navigate to `/profile`. This is a common SaaS pattern and the `Link` component is already imported in `Layout.jsx`. Keep it simple: wrap the identity row in a `Link to="/profile"` (hide for demo users consistent with how profile link is hidden in `UserMenu`).

3. **What happens in demo mode?**
   - What we know: `window.rondoConfig?.isDemoUser` gates profile access in `UserMenu` (line 245). Demo users don't show the profile link.
   - What's unclear: Should demo users still see the avatar/name in the sidebar footer?
   - Recommendation: Show the avatar/name display even for demo users (informational only), but don't make it a clickable link to `/profile`. This matches `UserMenu` behavior where the profile link is hidden for demo but the avatar is still shown.

---

## Scope Summary for Plan 208-01

This is a single-plan phase. All changes fit in one task:

**PHP change (1 file, ~3 lines):**
- `includes/class-rest-api.php` — add `linked_person_photo` field in `get_current_user()` method

**Frontend change (1 file, ~20 lines):**
- `src/components/layout/Layout.jsx` — redesign `Sidebar` footer from logout-only to user identity block (avatar + name) + logout

**No new files needed.**

**No new hooks needed** (`useCurrentUser` already used in `Sidebar`).

**No new API client methods needed** (field is additive to existing `/user/me` response).

---

## Sources

### Primary (HIGH confidence)

- Codebase: `src/components/layout/Layout.jsx` — full file read; `Sidebar` function, `UserMenu` function, avatar rendering patterns, `useCurrentUser()` usage
- Codebase: `includes/class-rest-api.php` lines 2694-2757 — `get_current_user()` implementation; confirmed `linked_person_name` and `active_functies` already present, `linked_person_photo` absent
- Codebase: `src/hooks/useCurrentUser.js` — `useCurrentUser` hook structure, queryKey `['current-user']`, staleTime 5 minutes
- Codebase: `includes/class-rest-people.php` lines 423, 491 — `get_the_post_thumbnail_url()` usage pattern confirmed
- Codebase: `src/api/client.js` line 114 — `getCurrentUser: () => api.get('/rondo/v1/user/me')` confirmed
- Codebase: Phase 205 verification — bidirectional `rondo_linked_person_id` user meta confirmed as set for provisioned users

### Secondary (MEDIUM confidence)

- Phase 207 research/plans — confirmed `linked_person_name` and `active_functies` added to `/user/me` in Phase 207
- Phase 208 additional context — "prior decisions" confirm avatar can ship before profile page; sidebar avatar is the explicit target

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new libraries, all existing WP and React patterns
- Architecture: HIGH — directly verified in codebase; patterns already used multiple times
- Pitfalls: HIGH — `false` vs `null` from WP is a known WP API behavior, loading state pattern directly observed in existing `UserMenu`

**Research date:** 2026-02-20
**Valid until:** 2026-03-22 (30 days — stable WP and React APIs)
