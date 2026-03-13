# M011: Roles & Capability Expansion — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Expand the role system so admins can create custom roles from the UI, add age-group-scoped coordinator roles, change the default Ledendata behavior to "see nobody" instead of "see everyone", and move toward deprecating the generic "Rondo User" role in favor of purpose-specific roles.

## Why This Milestone

Three problems with the current system:

1. **Wrong default for Ledendata** — When a role has no age-group config, the current behavior is "see all members" (null = unrestricted). This should be inverted: a role with no config should see NO members. Only roles explicitly configured with age groups (or roles with management capabilities) should see member data. This is a security-by-default principle.

2. **Hardcoded roles** — The 7 Rondo roles are defined as a PHP `const ROLES` in `UserRoles`. Adding roles requires code changes and deployment. The admin needs to create roles like "Coördinator Pupillen", "Coördinator Junioren", "Coördinator Senioren" directly from the UI.

3. **Generic "Rondo User" is too broad** — Currently every provisioned user gets `rondo_user` as their base role, which (after the default fix) would see no members. The goal is purpose-specific roles with appropriate age-group access configured per role, so each coordinator sees exactly the members they need.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Go to Settings → Beheer → Capabilities and see that roles without Ledendata config cannot see any members (not all)
- Go to Settings → Beheer → Functies and see the role column headers now include custom roles
- Add a new custom role from the Capabilities tab (e.g., "Coördinator Pupillen") with a name
- The new role appears in the Capabilities matrix, the Functies mapping, and the Commissie mapping
- Assign the role specific leeftijdsgroep values via the Ledendata column
- Map Sportlink functies to the new role so it gets auto-assigned during sync
- Users with the new role see only their permitted age groups in the People list

### Entry point / environment

- Entry point: Settings page at https://rondo.svawc.nl/settings/admin/capabilities
- Environment: production WordPress
- Live dependencies involved: none

## Completion Class

- Contract complete means: build passes, default Ledendata behavior is "none", custom roles can be created/deleted via UI, roles persist in WP role system
- Integration complete means: custom roles appear in Functies/Commissie mapping, capability sync works with custom roles, age-group filtering respects new default
- Operational complete means: deployed to production, coordinator roles created and tested with real data

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- A new role "Coördinator Pupillen" can be created from the UI, assigned leeftijdsgroep values, and mapped to a Sportlink functie
- A user with only that role sees only the configured age groups in the People list
- A user with the base `rondo_user` role (and no management caps, no age-group config) sees NO members
- Existing management-capability roles (fairplay, vog, financieel, etc.) still see all members
- Kaderlijst still shows all volunteers regardless of role

## Risks and Unknowns

- **ROLES constant replacement** — The `UserRoles::ROLES` PHP constant is referenced in 10+ locations for role slug validation, display labels, registration, and cleanup. Replacing it with a dynamic method that merges hardcoded + stored roles must not break any existing code path. — **Mitigation:** Replace `ROLES` with a static `get_all_roles()` method that merges the hardcoded base roles with custom roles from a wp_option. Keep the hardcoded roles as a `BASE_ROLES` constant for fallback/registration.
- **Ledendata default inversion** — Changing from "no config = see all" to "no config = see nobody" is a breaking behavior change. Any user who currently relies on `rondo_user` seeing all members will lose access. — **Mitigation:** This is the intended behavior (security by default). The milestone should ensure management-cap users remain unaffected, and the admin configures age-group access for the new coordinator roles before rolling out.
- **CapabilitySync with custom roles** — `CapabilitySync::sync_user()` uses `UserRoles::get_role_slugs()` to determine syncable roles. Custom roles must be included in this list. — **Mitigation:** `get_role_slugs()` will pull from `get_all_roles()` which includes custom roles.

## Existing Codebase / Prior Art

- `includes/class-user-roles.php` — `ROLES` constant defines 7 roles. `get_role_slugs()` returns their keys. `register_role()` creates them on activation. `remove_role()` cleans up on deactivation.
- `includes/class-access-control.php` — `get_permitted_age_groups()` returns `null` (unrestricted) when no config exists for user's roles. This is the method where the default inversion happens.
- `includes/class-capability-sync.php` — `sync_user()` computes syncable roles from `UserRoles::get_role_slugs()` minus `rondo_user`. Must include custom roles.
- `includes/class-rest-api.php` — Capability matrix endpoints use `UserRoles::ROLES` for validation and label lookup (~4 locations). Age-group access endpoints use `UserRoles::ROLES` for validation (~1 location).
- `includes/class-functie-capability-map.php` — Static config class for functie→role mapping. Not role-aware (stores arbitrary role slugs), so custom roles work automatically.
- `includes/class-commissie-capability-map.php` — Same pattern as FunctieCapabilityMap. Custom roles work automatically.
- `src/pages/Settings/Settings.jsx` — FunctiesTab gets its role columns from a `getRoles()` API call that reads `UserRoles::ROLES`. CapabilitiesTab same. Both need to show custom roles.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions.

## Scope

### In Scope

- Invert Ledendata default: "no config = see nobody" for non-management roles
- Custom role CRUD: create and delete roles from the Capabilities tab UI
- Custom roles stored in wp_option, registered as WP roles on save
- Custom roles appear in Capabilities matrix, Functies mapping, and Commissie mapping
- `UserRoles::ROLES` replaced with dynamic `get_all_roles()` merging base + custom roles
- `CapabilitySync` updated to include custom roles in syncable set
- Three initial coordinator roles created via the UI after deployment: Coördinator Pupillen, Coördinator Junioren, Coördinator Senioren

### Out of Scope / Non-Goals

- Removing `rondo_user` role entirely (it remains as a base role for provisioning)
- Per-user role overrides UI (manual grants/revokes already exist in backend)
- Role hierarchy or inheritance
- Renaming existing hardcoded roles

## Technical Constraints

- Custom roles must be registered as real WP roles via `add_role()` so `current_user_can()` and `user_can()` work natively
- Custom roles get the same base capabilities as `rondo_user` (read, edit_posts, publish_posts, etc.)
- Deleting a custom role must reassign affected users (at minimum remove the role, keeping other roles intact)
- The `ROLES` constant must remain available as `BASE_ROLES` for backward compatibility in any code that references specific hardcoded role slugs

## Integration Points

- **CapabilitySync** — Must include custom role slugs in the syncable set
- **FunctieCapabilityMap / CommissieCapabilityMap** — Store arbitrary role slugs, so custom roles work without changes to these classes themselves. The REST endpoints and UI that list available roles must include custom roles.
- **AccessControl age-group filtering** — Default behavior change in `get_permitted_age_groups()`
- **REST API role listing** — The `get_available_roles()` helper used by Functies/Commissie tabs must return custom roles
- **User provisioning** — `rondo_user` remains the base role assigned at provisioning. Custom roles are assigned via sync, not provisioning.

## Open Questions

- **Custom role slug format** — Auto-generate from display name (e.g., "Coördinator Pupillen" → `rondo_coordinator_pupillen`)? Or let admin choose? — **Current thinking:** Auto-generate with `rondo_` prefix and sanitized label. Slug is immutable after creation.
- **Delete vs deactivate** — Should deleting a custom role remove it from WP entirely, or soft-delete? — **Current thinking:** Hard delete (remove_role) since the role is no longer needed. Users with only that role keep their other roles. If it was their only role, they fall back to their base `rondo_user`.
