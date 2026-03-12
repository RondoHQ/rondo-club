# M010: Role-Capability Matrix & Age-Group Access

**Vision:** An admin-configurable role-capability system with age-group-based member visibility, replacing hardcoded role mappings and inconsistent capability checks.

## Success Criteria

- Admin can view and edit a role×capability matrix in Settings → Beheer → Capabilities
- Saving the matrix updates the WordPress role definitions (add_cap/remove_cap)
- All 6 instances of `current_user_can('administrator')` are replaced with `current_user_can('manage_options')`
- Each role can be configured with specific leeftijdsgroep values for member data access
- Users with age-group-restricted roles see only matching members in the People list and person detail
- The Kaderlijst is NOT affected by age-group restrictions (all volunteers visible)
- Existing Functies→Roles and Commissie→Roles sync continues to work unchanged

## Key Risks / Unknowns

- **Age-group filtering scope** — Must filter People list queries without affecting Kaderlijst, team member lists, or other endpoints. Requires careful hook placement.
- **Matrix vs hardcoded roles** — Moving from hardcoded `ROLES` constant to a stored matrix must handle role registration on theme activation (fresh installs) and matrix updates (existing installs).

## Proof Strategy

- Age-group filtering scope → retire in S02 by proving People list filters correctly AND Kaderlijst remains unaffected
- Matrix vs hardcoded roles → retire in S01 by proving matrix save/load works and capabilities apply correctly

## Verification Classes

- Contract verification: `npm run build` + `npm run lint` pass, PHP produces no errors
- Integration verification: capability sync still works, matrix changes reflect in `current_user_can()` checks
- Operational verification: deployed to production, verified with real user accounts
- UAT / human verification: admin configures matrix and age-group access, verifies member visibility

## Milestone Definition of Done

This milestone is complete only when all are true:

- All three slices are complete and deployed
- Role-capability matrix UI works end-to-end (load, edit, save, apply)
- Age-group filtering works on People list, not on Kaderlijst
- All role name checks replaced with capability checks
- Success criteria re-checked on production

## Slices

- [ ] **S01: Role-capability matrix backend & UI** `risk:high` `depends:[]`
  > After this: admin can open Settings → Beheer → Capabilities, see the matrix of all roles × capabilities, toggle them, save, and the WordPress role definitions update accordingly. Also fixes the 6 role-name checks. The matrix replaces the hardcoded ROLES constant for capability assignments.

- [ ] **S02: Age-group access filtering** `risk:high` `depends:[S01]`
  > After this: the matrix UI has a "Ledendata" column with a leeftijdsgroep multi-select per role. Users with age-group-restricted roles (and no management capabilities) see only members matching their permitted leeftijdsgroep values. The Kaderlijst is unaffected — all volunteers remain visible there. The `/rondo/v1/me` endpoint exposes the user's permitted age groups to the frontend.

- [ ] **S03: Frontend age-group filtering** `risk:medium` `depends:[S02]`
  > After this: the People list automatically filters to show only permitted members when the user has age-group restrictions. Person detail pages for non-permitted members show an access denied message. The Kaderlijst page works normally for all users. The system is fully end-to-end functional.

## Boundary Map

### S01 → S02

Produces:
- REST endpoint `GET /rondo/v1/settings/capability-matrix` returning `{ roles: { slug: { label, capabilities: { cap: bool } } } }`
- REST endpoint `POST /rondo/v1/settings/capability-matrix` accepting the same shape and writing to WP roles
- PHP function to read the matrix from WP role definitions (source of truth is the WP roles themselves, not a separate option)
- All 6 role-name checks fixed to use `manage_options`

Consumes:
- nothing (first slice)

### S02 → S03

Produces:
- `age_group_access` field in the capability matrix (per-role array of permitted leeftijdsgroep values, stored as a wp_option since WP capabilities are boolean only)
- PHP `AccessControl` method `get_permitted_age_groups( $user_id )` returning array of permitted values or `null` (no restriction)
- PHP query filtering on person queries when user has age-group restrictions
- `/rondo/v1/me` endpoint includes `permitted_age_groups: string[] | null` (null = no restriction)
- Kaderlijst endpoint explicitly bypasses age-group filtering

Consumes:
- Capability matrix backend from S01
