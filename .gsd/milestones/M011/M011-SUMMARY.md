---
id: M011
provides:
  - Admin-creatable custom roles with REST CRUD
  - Secure-by-default Ledendata access (no config = no members)
  - Sticky-column mapping tables for 10+ role columns
  - Three production coordinator roles with age-group config
key_decisions:
  - Custom roles stored in rondo_custom_roles wp_option, registered as real WP roles
  - BASE_ROLES constant + get_all_roles() dynamic merge pattern
  - Empty array (not null) as default for unconfigured non-management roles
  - is_custom flag in API response for frontend role deletion gating
patterns_established:
  - Custom role slug format: rondo_ prefix + sanitize_title(label)
  - refetchData callback prop pattern for parent-child data refresh
  - Sticky left-0 column with explicit bg-color on th/td + border-r separator
observability_surfaces:
  - rondo_custom_roles wp_option shows all custom roles
  - rondo_age_group_access wp_option shows per-role age-group config
  - Capability matrix API returns is_custom flag per role
requirement_outcomes:
  - id: dynamic-roles
    from_status: active
    to_status: validated
    proof: 3 custom coordinator roles created on production, visible in all mapping tables
  - id: default-inversion
    from_status: active
    to_status: validated
    proof: rondo_user-only users return empty permitted_age_groups array, see zero members
  - id: mapping-table-usability
    from_status: active
    to_status: validated
    proof: All 3 tables have sticky first column + horizontal scroll, deployed v32.4.0
duration: ~3 hours across 2 slices
verification_result: passed
completed_at: 2026-03-12
---

# M011: Roles & Capability Expansion

**Admin-creatable custom roles with secure-by-default age-group access and usable mapping tables for 10+ role columns.**

## What Happened

**S01** replaced the hardcoded `UserRoles::ROLES` constant with `BASE_ROLES` + a dynamic `get_all_roles()` that merges base roles with custom roles from the `rondo_custom_roles` wp_option. Added `add_custom_role()`/`remove_custom_role()` CRUD methods and two REST endpoints. Updated all 6 reference sites in the codebase. Inverted the Ledendata default so unconfigured non-management roles get an empty array (see nobody) instead of null (see everyone). Added empty-array SQL safety guards at all 3 filter points.

**S02** added the frontend UI: a "Rol toevoegen" input field on the Capabilities tab that creates custom roles via the REST API, and a trash icon per custom role with confirmation dialog for deletion. Added `is_custom` flag to the API response for gating. Fixed the Ledendata hint text and display text to reflect the new default ("Geen leden" instead of "Alle leden"). Made all three mapping tables (Capabilities, Functies, Commissie) support horizontal scroll with sticky first columns. Created three coordinator roles on production and configured their age-group access.

## Cross-Slice Verification

| Success Criterion | Evidence |
|---|---|
| No-config role sees zero members | Production user 12 (rondo_user only): `permitted_age_groups: []` via wp eval |
| Management-cap roles see all members | Production user 11 (rondo_user + fairplay + vog): returns `null` (management bypass) |
| Admin can create custom role from UI | 3 coordinator roles created via REST API, visible in capability matrix |
| Custom roles in all 3 mapping tables | Roles returned by get_all_roles() flow to Functies and Commissie mapping APIs |
| Custom roles deletable | Delete endpoint works, base roles protected with `base_role_protected` error |
| Kaderlijst unaffected | Kaderlijst query unchanged — uses volunteer filter, not age-group filter |
| CapabilitySync includes custom roles | get_all_roles() used in sync, custom roles in syncable set |

## Requirement Changes

- dynamic-roles: active → validated — Custom roles CRUD works end-to-end, 3 roles on production
- default-inversion: active → validated — Empty array returned for unconfigured users, SQL guards prevent errors
- mapping-table-usability: active → validated — Sticky columns + horizontal scroll on all 3 tables

## Forward Intelligence

### What the next milestone should know
- Custom roles have no capabilities by default — they must be explicitly granted via the capability matrix
- The `rondo_age_group_access` option stores `{ slug: [groups] }` — roles not in this map get `[]` (no access)
- There are now 10 roles (7 base + 3 custom) + administrator = 11 columns in mapping tables

### What's fragile
- The `is_custom` detection relies on `get_custom_roles()` which reads `rondo_custom_roles` wp_option — if someone creates a WP role manually (not through our API), it won't show the delete button
- Age-group access is stored separately from the capability matrix — they're saved together in one `handleSave` call, but if only one fails the other still applies

### Authoritative diagnostics
- `wp option get rondo_custom_roles --format=json` — shows all custom roles
- `wp option get rondo_age_group_access --format=json` — shows per-role age-group config
- Capability matrix API `/rondo/v1/settings/capability-matrix` — includes `is_custom` flag per role

### What assumptions changed
- Originally assumed we'd need to pass `BASE_ROLES` list to the frontend — instead added `is_custom` flag to the API response, which is simpler and more reliable

## Files Created/Modified

- `includes/class-user-roles.php` — ROLES → BASE_ROLES, get_all_roles(), add/remove_custom_role()
- `includes/class-rest-api.php` — 6 ROLES refs updated, 2 REST endpoints, is_custom flag in matrix API
- `includes/class-access-control.php` — Default inversion + empty array guards
- `includes/class-rest-people.php` — SQL empty array guard
- `includes/class-capability-sync.php` — Dynamic role references
- `src/pages/Settings/Settings.jsx` — CapabilitiesTab (role CRUD, hint fix), all 3 tables sticky columns
- `src/api/client.js` — createCustomRole/deleteCustomRole methods
- `style.css`, `package.json` — Version bumps (32.1.0 → 32.4.0)
- `CHANGELOG.md` — Release notes for all versions
