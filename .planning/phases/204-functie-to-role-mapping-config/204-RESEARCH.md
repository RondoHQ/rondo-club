# Phase 204: Functie-to-Role Mapping Config - Research

**Researched:** 2026-02-20
**Domain:** WordPress Options API, REST API, React Settings UI — admin configuration of a Functie-to-role mapping matrix
**Confidence:** HIGH

## Summary

Phase 204 adds an admin-configurable mapping from Sportlink Functies (job title strings that come from the `work_history` `job_title` field) to Rondo WordPress roles (`rondo_user`, `rondo_fairplay`, `rondo_vog`, `rondo_bestuur`). The mapping is stored in the WordPress Options API, exposed via two REST endpoints (GET + POST on `/rondo/v1/functie-capability-map`), and surfaced as a checkbox matrix in Settings > Beheer > a new subtab. Known Functies are auto-populated from the existing `work_history` data already stored in the database (the same source `get_available_werkfuncties()` already queries), so no new rondo-sync step is needed for this phase.

The system follows three established codebase patterns exactly: (1) `VolunteerStatus` — config class with `OPTION_*` constants, `get_option` with null-fallback, `update_option` for writes; (2) `get_available_werkfuncties()` REST handler — queries `work_history_%_job_title` post meta for distinct values; (3) `RollenTab` in Settings — checkbox matrix UI wired to GET + POST API calls with loading/saving/message state.

**Primary recommendation:** Model `FunctieCapabilityMap` exactly after `VolunteerStatus` + `ClubConfig` patterns. Embed the GET/POST endpoints inside the existing `Api` class (same file as `get_available_volunteer_roles`). Add a new "Functies" subtab inside the existing `AdminTabWithSubtabs` component alongside "Gebruikers" and "Rollen".

## Standard Stack

### Core
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| WordPress Options API | WordPress 6.0+ | Persist the mapping as a single serialized option | Project rule: Options API for site-wide settings |
| `class-rest-api.php` | existing | Host the GET/POST endpoints | All custom endpoints live here |
| React + TanStack Query pattern | 18 / v5 | Settings UI state management | Existing pattern in Settings.jsx |

### Supporting
| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| `UserRoles::ROLES` constant | existing | Enumerate Rondo roles as columns | Single source of truth for role slugs |
| `get_available_werkfuncties()` | existing | Source of known Functie strings | Already queries `work_history_%_job_title` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Single serialized option | Per-Functie options | Single option is simpler, atomic update, consistent with ClubConfig/FinanceConfig |
| Querying `work_history` job_title | Adding new rondo-sync endpoint | Query from existing data avoids cross-repo changes; rondo-sync step for phase 206 anyway |
| New `FunctieCapabilityMap` PHP class | Embedding logic in `Api` | Separate class keeps the data-model concern DRY and callable from future phases |

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── class-functie-capability-map.php    # New: storage + retrieval (Rondo\Config namespace)
└── class-rest-api.php                  # Modified: add 2 endpoints + reuse get_available_werkfuncties

src/pages/Settings/
└── Settings.jsx                        # Modified: add Functies subtab to AdminTabWithSubtabs
```

### Pattern 1: Config Class (VolunteerStatus / ClubConfig pattern)

**What:** Static utility class with `OPTION_*` constants, `get_option` with default fallback, `update_option` for writes. No constructor hooks needed — pure data access.

**When to use:** Site-wide settings with a clear key and a typed default value.

**Example (from `class-volunteer-status.php`):**
```php
// Source: /includes/class-volunteer-status.php
const OPTION_PLAYER_ROLES = 'rondo_player_roles';

public static function get_player_roles(): array {
    $roles = get_option( self::OPTION_PLAYER_ROLES, null );
    return is_array( $roles ) ? $roles : self::DEFAULT_PLAYER_ROLES;
}
```

**Applied to FunctieCapabilityMap:**
```php
namespace Rondo\Config;

class FunctieCapabilityMap {
    const OPTION_KEY = 'rondo_functie_capability_map';

    /**
     * Get current mapping.
     * Returns array of: functie_name => [ 'rondo_user' => bool, 'rondo_fairplay' => bool, ... ]
     */
    public static function get_map(): array {
        $map = get_option( self::OPTION_KEY, null );
        return is_array( $map ) ? $map : [];
    }

    /**
     * Update the full mapping.
     * @param array $map Same structure as get_map() returns.
     */
    public static function update_map( array $map ): bool {
        return update_option( self::OPTION_KEY, $map );
    }

    /**
     * Get roles a given Functie grants.
     * @param string $functie Functie name (job_title string from Sportlink).
     * @return string[] Array of WordPress role slugs granted by this Functie.
     */
    public static function get_roles_for_functie( string $functie ): array {
        $map = self::get_map();
        $entry = $map[ $functie ] ?? [];
        return array_keys( array_filter( $entry ) );
    }
}
```

### Pattern 2: Available-Functies REST Endpoint (reuse existing)

**What:** The existing `GET /rondo/v1/werkfuncties/available` already returns all distinct `job_title` values from `work_history` post meta. This endpoint should be reused as-is for populating the matrix rows.

**Confidence:** HIGH — code verified at `includes/class-rest-api.php` lines 949–958 and 4184–4217.

The existing endpoint:
- Queries `work_history_%_job_title` post meta via `get_field('work_history', $id)`
- Returns a sorted array of unique non-empty strings
- Requires admin permission (`check_admin_permission`)

No changes needed to this endpoint for Phase 204. The Settings UI simply calls it alongside the new map GET endpoint.

### Pattern 3: GET + POST Map Endpoints (volunteer-roles/settings pattern)

**What:** Two REST routes under the same path — one `READABLE` (returns current map), one `CREATABLE` (replaces the full map). Modeled exactly on the `volunteer-roles/settings` pair at lines 893–929.

**Example (from `class-rest-api.php` lines 893–929):**
```php
register_rest_route( 'rondo/v1', '/volunteer-roles/settings', [
    [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_volunteer_role_settings' ],
        'permission_callback' => [ $this, 'check_user_approved' ],
    ],
    [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'update_volunteer_role_settings' ],
        'permission_callback' => [ $this, 'check_admin_permission' ],
        'args'                => [ ... ],
    ],
] );
```

**Applied to FunctieCapabilityMap:**
- `GET /rondo/v1/functie-capability-map` — returns `{ map: {...}, roles: [...] }` (permission: admin)
- `POST /rondo/v1/functie-capability-map` — accepts `{ map: {...} }`, validates, stores (permission: admin)

Both endpoints are admin-only (no reason a non-admin user needs the Functie-to-role map).

### Pattern 4: Settings UI — Checkbox Matrix (RollenTab pattern)

**What:** The existing `RollenTab` component (`Settings.jsx` lines 1299–1445) is a table with Functies as rows and classifications as columns rendered as radio buttons. For Phase 204, the matrix has Functies as rows and Rondo roles as columns rendered as checkboxes (multiple roles per Functie are valid — a Functie could grant both `rondo_vog` and `rondo_fairplay`).

**Key implementation details from RollenTab (verified in Settings.jsx):**
- `availableRoles` fetched from `GET /rondo/v1/volunteer-roles/available` on mount (admin only)
- Settings fetched from `GET /rondo/v1/volunteer-roles/settings` on mount
- Save triggered by explicit "Opslaan" button, not on-blur
- Message shown after save (success or error)
- Loading spinner from `Loader2` (lucide-react)
- Table with `max-h-96 overflow-y-auto` for long lists
- `grid-cols-[1fr,auto]` layout pattern

**For Phase 204 checkbox matrix:**
- Rows: Functies from `GET /rondo/v1/werkfuncties/available`
- Columns: Rondo roles from `UserRoles::ROLES` (returned by the GET map endpoint as `roles` array)
- Cells: `<input type="checkbox">` — one per Functie × role intersection
- State: local `mapState` object `{ [functie]: { [roleSlug]: boolean } }`
- Save: POST the full `mapState`

### Pattern 5: Admin Subtab Integration

**What:** `AdminTabWithSubtabs` in Settings.jsx (lines 1169–1228) already has "Gebruikers" and "Rollen" subtabs. Add a "Functies" subtab.

**ADMIN_SUBTABS array (current):**
```js
const ADMIN_SUBTABS = [
  { id: 'users', label: 'Gebruikers', icon: Users },
  { id: 'rollen', label: 'Rollen' },
];
```

**After Phase 204:**
```js
const ADMIN_SUBTABS = [
  { id: 'users', label: 'Gebruikers', icon: Users },
  { id: 'rollen', label: 'Rollen' },
  { id: 'functies', label: 'Functies' },
];
```

The `AdminTabWithSubtabs` renders the corresponding `FunctiesTab` component when `activeSubtab === 'functies'`. State management (fetch, save, loading, message) follows the same pattern as `RollenTab`.

### Anti-Patterns to Avoid

- **Don't query Functies from rondo-sync's SQLite:** The known Functies live in WordPress post meta, already accessible via `get_available_werkfuncties()`. rondo-sync feeds the data; rondo-club reads it back.
- **Don't store mapping as per-Functie options:** A single serialized option is atomic, simpler to invalidate, and consistent with other config classes.
- **Don't use checkboxes that auto-save on change:** All existing Settings patterns use explicit save buttons to prevent accidental overwrites.
- **Don't add a new PHP class file for REST handlers:** The endpoints belong in `class-rest-api.php` alongside `get_volunteer_role_settings` and `get_available_werkfuncties`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| List of Rondo role slugs | Hard-coded array in new class | `UserRoles::ROLES` constant keys | Single source of truth; changes automatically reflected |
| List of known Functies | New storage layer | Existing `get_available_werkfuncties()` REST endpoint | Already queries `work_history` post meta correctly |
| Options storage | Custom DB table or JSON file | `update_option` / `get_option` | Project rule 0; already used by all other config classes |
| REST permission checks | Custom permission logic | `check_admin_permission()` (existing method in Api class) | Consistent, already tested pattern |

**Key insight:** The entire PHP data layer is four lines: get option, update option, one query for Functies (already exists), and return `UserRoles::ROLES` keys. The complexity is in the React UI, not the backend.

## Common Pitfalls

### Pitfall 1: Functies Matrix Is Empty Until rondo-sync Has Run
**What goes wrong:** On a fresh install (or if no people have `work_history`), `GET /rondo/v1/werkfuncties/available` returns `[]`. The UI shows "Geen functies gevonden" with no rows.
**Why it happens:** The Functies come from actual `work_history` post meta; if none exists, the list is empty.
**How to avoid:** Show the empty-state message (already used in `RollenTab`) and explain that Functies are populated by rondo-sync. Do not block save/load when empty.
**Warning signs:** Empty matrix on production immediately after deploy — expected if sync has not run yet.

### Pitfall 2: Saved Map Contains Stale Functie Names
**What goes wrong:** Admin saves a mapping for "Aanvaller". Later, rondo-sync renames a Functie. The saved map still references "Aanvaller" but the available list no longer includes it.
**Why it happens:** The map is keyed by string name, not a stable ID.
**How to avoid:** On GET, return the union of (available Functies) + (Functies present in the saved map). This ensures the matrix always shows all rows that have been configured, even if they no longer appear in the DB. Label stale rows visually (e.g., "(niet meer actief)").
**Warning signs:** Admin sees a configured row disappear silently after a sync.

### Pitfall 3: Checkbox State Out of Sync After Save
**What goes wrong:** POST succeeds, but UI still shows old state.
**Why it happens:** Not refreshing local state from server response.
**How to avoid:** After successful POST, update local state from the server's response body (same pattern as `update_volunteer_role_settings` which returns the saved values).

### Pitfall 4: Role Slug vs Display Name Confusion
**What goes wrong:** Column headers show slugs like `rondo_fairplay` instead of display names like "Rondo FairPlay".
**Why it happens:** `UserRoles::ROLES` contains `[ slug => [display_name, extra_caps] ]`.
**How to avoid:** The GET endpoint returns `roles` as `[ { slug, label } ]` derived from `UserRoles::ROLES`. The React component uses `label` for display.

## Code Examples

Verified patterns from existing codebase:

### PHP: How to Return Roles from UserRoles::ROLES
```php
// Source: includes/class-user-roles.php (lines 26-31)
const ROLES = [
    'rondo_user'     => [ 'Rondo User', [] ],
    'rondo_fairplay' => [ 'Rondo FairPlay', [ 'fairplay' ] ],
    'rondo_vog'      => [ 'Rondo VOG', [ 'vog' ] ],
    'rondo_bestuur'  => [ 'Rondo Bestuur', [ 'fairplay', 'vog', 'financieel' ] ],
];

// To produce [{ slug, label }] for the REST response:
$roles = [];
foreach ( \Rondo\Core\UserRoles::ROLES as $slug => [ $display_name, ] ) {
    $roles[] = [ 'slug' => $slug, 'label' => $display_name ];
}
```

### PHP: GET endpoint callback structure
```php
// Pattern from get_volunteer_role_settings (lines 4134-4143)
public function get_functie_capability_map( $request ) {
    return rest_ensure_response( [
        'map'   => \Rondo\Config\FunctieCapabilityMap::get_map(),
        'roles' => $this->get_rondo_roles_list(), // [{ slug, label }]
    ] );
}
```

### PHP: POST endpoint with sanitization
```php
// Pattern from update_volunteer_role_settings (lines 4151-4173)
public function update_functie_capability_map( $request ) {
    $map = $request->get_param( 'map' );
    // $map validated by 'args' validate_callback (must be array of arrays of booleans)
    \Rondo\Config\FunctieCapabilityMap::update_map( $map );
    return rest_ensure_response( [
        'map'   => \Rondo\Config\FunctieCapabilityMap::get_map(),
        'roles' => $this->get_rondo_roles_list(),
    ] );
}
```

### React: Checkbox matrix state pattern
```js
// Derived from RollenTab pattern (Settings.jsx lines 1316-1340)
const [mapState, setMapState] = useState({});
// mapState: { [functie]: { [roleSlug]: boolean } }

const handleCellChange = (functie, roleSlug, checked) => {
  setMapState(prev => ({
    ...prev,
    [functie]: { ...(prev[functie] || {}), [roleSlug]: checked }
  }));
};

// On save:
await prmApi.updateFunctieCapabilityMap({ map: mapState });
```

### React: Fetch both endpoints on mount (admin only)
```js
// Pattern from RollenTab useEffect (Settings.jsx lines 137-160)
useEffect(() => {
  if (!isAdmin) { setLoading(false); return; }
  Promise.all([
    prmApi.getAvailableWerkfuncties(),      // existing endpoint
    prmApi.getFunctieCapabilityMap(),        // new endpoint
  ]).then(([functiesRes, mapRes]) => {
    setAvailableFuncties(functiesRes.data || []);
    setMapState(mapRes.data.map || {});
    setRoles(mapRes.data.roles || []);
  }).finally(() => setLoading(false));
}, [isAdmin]);
```

### React: Combined Functies list (available + configured)
```js
// Include Functies present in saved map even if not in available list
// Pattern: same as RollenTab allRoles (Settings.jsx lines 1311-1315)
const allFuncties = [...new Set([
  ...availableFuncties,
  ...Object.keys(mapState),
])].sort((a, b) => a.localeCompare(b, 'nl'));
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-setting options | Single serialized option per config domain | Established in ClubConfig/FinanceConfig | Simpler atomic updates |
| Ad-hoc REST routes | Grouped route arrays with methods array | Established in volunteer-roles/settings | Cleaner registration |

**Deprecated/outdated:**
- Direct `get_option`/`update_option` calls in REST handlers without a config class: replaced by config class pattern (see `ClubConfig`, `VolunteerStatus`).

## Open Questions

1. **Should `GET /rondo/v1/functie-capability-map` be admin-only or readable by all users?**
   - What we know: `get_volunteer_role_settings` is readable by all approved users; the map itself is an admin concern and not user-facing in Phase 204.
   - What's unclear: Phase 206 (capability sync) may need to read the map server-side, which doesn't require a REST endpoint (it uses PHP directly).
   - Recommendation: Admin-only for both GET and POST — no non-admin use case exists in Phase 204 or 206.

2. **Where in `functions.php` should `FunctieCapabilityMap` be loaded?**
   - What we know: It's a pure static class with no hooks, so it does not need instantiation at all — it can be used directly via `FunctieCapabilityMap::get_map()` wherever needed.
   - Recommendation: No `new FunctieCapabilityMap()` call needed. Just add the `use` import at the top of `functions.php` (and `class-rest-api.php`). Autoloader handles the file loading.

3. **Does Phase 206 need a different storage format for capability sync?**
   - What we know: Phase 206 will call `FunctieCapabilityMap::get_roles_for_functie($functie)` to determine which roles to grant during sync. The proposed array structure supports this.
   - Recommendation: Design the data model with Phase 206's `get_roles_for_functie()` method in mind from the start — this avoids having to change storage format in Phase 206.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-volunteer-status.php` — Config class pattern with OPTION_* constants, get_option with null-fallback, typed defaults
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-club-config.php` — Static config utility class pattern
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` lines 882–958 — REST route registration for volunteer-roles and werkfuncties endpoints
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-api.php` lines 4113–4217 — `get_available_volunteer_roles`, `get_volunteer_role_settings`, `update_volunteer_role_settings`, `get_available_werkfuncties` implementations
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-user-roles.php` — `UserRoles::ROLES` constant with all Rondo role definitions
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/Settings.jsx` lines 1299–1445 — `RollenTab` component (checkbox-like matrix UI with save pattern)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/Settings.jsx` lines 28–32, 1169–1228 — `ADMIN_SUBTABS` config and `AdminTabWithSubtabs` structure
- `/Users/joostdevalk/Code/rondo/rondo-club/src/api/client.js` lines 249–258 — existing `getAvailableRoles`, `getVolunteerRoleSettings`, `updateVolunteerRoleSettings`, `getAvailableWerkfuncties` API methods
- `/Users/joostdevalk/Code/rondo/rondo-club/functions.php` lines 286–379 — class loading in `rondo_init()`
- `/Users/joostdevalk/Code/rondo/rondo-club/.planning/ROADMAP.md` — Phase 204 requirements, success criteria, relationship to 205/206

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/rondo/rondo-sync/steps/download-functions-from-sportlink.js` — confirms `FunctionDescription` from Sportlink maps to `job_title` in `work_history` (i.e., "Functie" = `job_title`)
- `/Users/joostdevalk/Code/rondo/rondo-sync/pipelines/sync-functions.js` — confirms sync flow: functions → commissies → work history → free fields

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components are existing codebase patterns, verified in source files
- Architecture: HIGH — directly modeled on verified existing patterns (VolunteerStatus, volunteer-roles endpoints, RollenTab)
- Pitfalls: HIGH for pitfalls 1 and 4 (spotted in code review); MEDIUM for pitfall 2 (stale names — reasoning from data model); HIGH for pitfall 3 (common React state bug, observed pattern in codebase)

**Research date:** 2026-02-20
**Valid until:** 90 days (stable codebase — only invalidated if RollenTab or VolunteerStatus are refactored)
