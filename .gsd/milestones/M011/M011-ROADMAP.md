# M011: Roles & Capability Expansion

**Vision:** Admin-creatable roles with secure-by-default age-group access, enabling purpose-specific coordinator roles that replace the generic "Rondo User" for member data visibility.

## Success Criteria

- A role with no Ledendata config and no management capabilities sees ZERO members in the People list
- Roles with management capabilities (fairplay, vog, financieel, toegangscontrole, manage_clothing, manage_options) still see all members
- Admin can create a new custom role from the Capabilities tab with a display name
- Custom roles appear in the Capabilities matrix, the Functies mapping table, and the Commissie mapping table
- Custom roles can be deleted, removing the WP role and cleaning up affected users
- The Kaderlijst remains unaffected by all changes (all volunteers visible)
- CapabilitySync assigns and revokes custom roles correctly during sync

## Key Risks / Unknowns

- **ROLES constant replacement** — 10+ PHP locations reference `UserRoles::ROLES`. Must be replaced with a dynamic method without breaking validation, label lookup, or role registration.
- **Ledendata default inversion** — Changes effective access for all users with `rondo_user` only. Must be deployed alongside age-group config for the new coordinator roles.

## Proof Strategy

- ROLES constant replacement → retire in S01 by proving custom roles register in WP, appear in all 3 mapping UIs, and CapabilitySync includes them
- Ledendata default inversion → retire in S01 by proving `rondo_user`-only users see zero members and management-cap users still see all

## Verification Classes

- Contract verification: `npm run build` + `npm run lint` pass, PHP produces no errors
- Integration verification: custom roles in Functies/Commissie mapping, CapabilitySync with custom roles, age-group filtering with new default
- Operational verification: deployed to production, coordinator roles created, verified with real user accounts
- UAT / human verification: admin creates coordinator roles, configures age groups, verifies member visibility

## Milestone Definition of Done

This milestone is complete only when all are true:

- Both slices are complete and deployed
- Ledendata default is "see nobody" for unconfigured roles
- Custom roles can be created, configured, mapped, and deleted
- Three coordinator roles created on production with correct age-group access
- Success criteria re-checked on production

## Slices

- [x] **S01: Dynamic roles backend & default inversion** `risk:high` `depends:[]`
  > After this: `UserRoles::ROLES` replaced with `get_all_roles()` merging base + custom roles from wp_option. `get_permitted_age_groups()` returns empty array (not null) for unconfigured non-management roles, meaning they see zero members. REST endpoints for creating/deleting custom roles work. Custom roles registered as WP roles. CapabilitySync includes custom roles. Build/lint pass.

- [ ] **S02: UI for custom role management & mapping table usability** `risk:medium` `depends:[S01]`
  > After this: Capabilities tab has an "Add Role" button that creates custom roles and a delete action per custom role. Functies and Commissie mapping tables have sticky first columns and horizontal scroll to handle 10+ role columns. Custom roles appear in all three mapping surfaces. Three coordinator roles created on production. Deployed and verified end-to-end.

## Boundary Map

### S01 → S02

Produces:
- `UserRoles::get_all_roles()` static method returning merged base + custom roles as `[ slug => [label, caps] ]`
- `UserRoles::get_custom_roles()` static method returning custom roles from wp_option
- `UserRoles::add_custom_role( $label )` returning slug, registering WP role
- `UserRoles::remove_custom_role( $slug )` removing WP role, cleaning up users
- REST `POST /rondo/v1/settings/roles` for creating custom roles (returns slug + label)
- REST `DELETE /rondo/v1/settings/roles/{slug}` for removing custom roles
- `get_permitted_age_groups()` returning `[]` (empty array, not null) for non-management users with no age-group config
- `CapabilitySync::sync_user()` including custom roles in syncable set
- All existing `UserRoles::ROLES` references updated to use `get_all_roles()`

Consumes:
- nothing (first slice)

### S02 detail

Produces:
- "Add Role" UI on Capabilities tab (name input → POST /rondo/v1/settings/roles → role appears in matrix)
- Delete action per custom role (confirmation → DELETE /rondo/v1/settings/roles/{slug} → role removed from matrix)
- Sticky first column + horizontal scroll on Functies mapping table (both Functie and Commissie sections)
- Sticky first column + horizontal scroll on Capabilities matrix
- Custom roles flowing into all three mapping tables automatically (they share the same `roles` API response)
- Three coordinator roles created on production with appropriate leeftijdsgroep config

Consumes:
- S01 REST endpoints for role CRUD
- S01 `get_all_roles()` dynamic role listing
- S01 default inversion (deployed before coordinator role setup)
