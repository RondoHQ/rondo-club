# M010: Role-Capability Matrix & Age-Group Access — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Replace the current hardcoded role→capability mapping with an admin-configurable matrix UI, fix inconsistent role checks in PHP, and add a new age-group-based member data viewing capability.

## Why This Milestone

Three problems with the current system:

1. **Hardcoded capability mapping** — The `UserRoles::ROLES` constant hardcodes which capabilities each role gets. Adding or changing capability assignments requires code changes and deployment. An admin UI matrix (rows=roles, columns=capabilities) would let the club configure this without developers.

2. **Inconsistent role vs capability checks** — Six PHP locations use `current_user_can('administrator')` (checking role name) instead of `current_user_can('manage_options')` (checking capability). This is fragile and inconsistent.

3. **No age-group access control** — Organisatorisch Coördinatoren (OCs) need to see member data for their specific age groups (e.g., "Onder 8", "Onder 9"). Currently there's no way to scope member visibility by leeftijdsgroep. This should work per-role: a role like "OC Pupillen" gets configured with specific leeftijdsgroep values (e.g., Onder 7 through Onder 12) and anyone with that role can view those members.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Open Settings → Beheer → a new "Capabilities" subtab and see a matrix of roles × capabilities
- Toggle capabilities on/off for each role and save
- See a new "Leeftijdsgroep" column in the matrix — when toggled on for a role, a multi-select lets the admin pick which leeftijdsgroep values that role can view
- Users with age-group-restricted roles see only members matching their permitted leeftijdsgroep values in the People list
- Age-group restrictions do NOT apply to the Kaderlijst (all volunteers remain visible there)
- All existing Functies→Roles and Commissie→Roles mappings continue to work with the new matrix

### Entry point / environment

- Entry point: Settings page at https://rondo.svawc.nl/settings/admin/capabilities
- Environment: production WordPress
- Live dependencies involved: none

## Completion Class

- Contract complete means: build passes, all capability checks use capabilities (not role names), matrix saves/loads correctly
- Integration complete means: capability sync still works, age-group filtering works on the People list, Kaderlijst unaffected
- Operational complete means: deployed to production, verified with real user accounts

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Admin can configure role→capability mappings in the UI, save, and the changes take effect
- Admin can configure age-group access per role, and users with that role see only the permitted members
- Kaderlijst shows all volunteers regardless of age-group restrictions
- Existing Functies→Roles and Commissie→Roles sync still assigns roles correctly, and those roles now get capabilities from the matrix
- The 6 role name checks are replaced with capability checks

## Risks and Unknowns

- **Matrix storage format** — Need to decide: store the matrix as a single `wp_option` (JSON), or modify the WP role definitions at runtime. Modifying WP roles is the WordPress-native approach and ensures `current_user_can()` works everywhere without extra lookups. Risk: role definitions are cached by WP and writing to them on every save could be slow for many roles. — **Mitigation:** Saving the matrix writes to WP roles via `add_cap()`/`remove_cap()`, which is the standard WP pattern. This is what UserRoles already does.
- **Age-group filtering scope** — Need to carefully filter only the People list and person detail access, NOT the Kaderlijst or team member lists. — **Mitigation:** Age-group filtering hooks into AccessControl at the person query level, with an explicit bypass for Kaderlijst endpoints.
- **"Rondo User" base role** — Currently `rondo_user` has no extra capabilities. Users with only this role and an age-group restriction should see only their permitted age groups. Users with `fairplay`/`vog`/`financieel` etc. should see everyone (these are management capabilities). — **Mitigation:** Age-group filtering only applies when the user lacks ALL of the management capabilities.

## Existing Codebase / Prior Art

- `includes/class-user-roles.php` — Defines 7 custom roles and 5 custom capabilities. Currently hardcoded in `ROLES` constant.
- `includes/class-access-control.php` — Query-level filtering. Currently handles VOG-only filtering and clothing gating. Will need age-group filtering.
- `includes/class-capability-sync.php` — Syncs roles from Sportlink functies/commissies. Assigns roles (not capabilities directly). Must continue to work.
- `includes/class-rest-base.php` — Shared permission check methods. Has 6 `current_user_can('administrator')` calls that should be `manage_options`.
- `includes/class-rest-people.php` — People list endpoint. Has leeftijdsgroep filter support already.
- `includes/class-rest-teams.php`, `includes/class-rest-commissies.php` — Have role name checks to fix.
- `src/pages/Settings/Settings.jsx` — Settings page with existing Functies/Commissie mapping tabs.
- `src/pages/People/PeopleList.jsx` — People list with leeftijdsgroep filter column.
- `src/pages/Teams/Kaderlijst.jsx` — Must NOT be affected by age-group restrictions.

## Scope

### In Scope

- Admin UI: role-capability matrix in Settings → Beheer
- PHP backend: REST endpoints to read/save the matrix, apply it to WP roles
- Fix 6 instances of `current_user_can('administrator')` → `current_user_can('manage_options')`
- New capability type: age-group access (leeftijdsgroep values per role)
- Age-group filtering on People list and person detail access
- Kaderlijst exemption from age-group filtering

### Out of Scope / Non-Goals

- Changing how Functies→Roles or Commissie→Roles mapping works (those stay as-is)
- Per-user capability overrides (beyond what manual grants/revokes already provide in CapabilitySync)
- Team-level access restrictions
- Refactoring the entire settings page

## Technical Constraints

- Must use WordPress native role/capability system (`add_cap()`, `remove_cap()`, `current_user_can()`)
- Age-group values come from Sportlink sync (`leeftijdsgroep` ACF meta field) — values like "Onder 7", "Onder 8", ..., "Senioren", "Senioren Vrouwen"
- The matrix UI must show all existing roles (including administrator) and all existing capabilities
- Capability sync must continue to assign roles; the matrix determines what capabilities those roles carry

## Integration Points

- **CapabilitySync** — Assigns roles to users based on Sportlink data. Roles' capabilities are now determined by the matrix, not hardcoded.
- **AccessControl** — Will enforce age-group restrictions at query level.
- **REST API `/rondo/v1/me`** — Must expose age-group access info to the frontend.
- **Frontend People list** — Must filter by permitted leeftijdsgroep when user has restrictions.
- **Frontend Kaderlijst** — Must explicitly bypass age-group restrictions.

## Open Questions

- **Age-group category grouping** — The user mentioned "Pupillen, Junioren, Senioren" as age categories. Should the matrix UI support selecting individual leeftijdsgroep values (Onder 7, Onder 8, etc.) AND/OR broader categories? — **Current thinking:** Show individual values from the DB (they're dynamic from Sportlink), but also allow grouping shortcuts. Start with individual values; grouping can be a polish item.
