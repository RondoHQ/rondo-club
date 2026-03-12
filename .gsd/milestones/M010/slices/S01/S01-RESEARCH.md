# S01: Role-Capability Matrix Backend & UI — Research

**Date:** 2026-03-12

## Summary

This slice delivers two pieces: (1) a new "Capabilities" admin subtab in Settings → Beheer showing a matrix of roles × capabilities with save functionality, and (2) fixing 6 instances of `current_user_can('administrator')` → `current_user_can('manage_options')`.

The codebase already has strong patterns for exactly this kind of work. The existing Functies tab (`FunctiesTab` in Settings.jsx) is a near-identical checkbox-matrix UI mapping functies→roles. We can follow this exact same pattern but flip the axes: roles (rows) × capabilities (columns). The backend follows the same `FunctieCapabilityMap` static config class pattern but reads/writes directly to WordPress role definitions via `get_role()→add_cap()/remove_cap()`. This is the WordPress-native approach already used in `UserRoles::register_role()`.

The 6 role-name checks are straightforward find-and-replace in 3 files. The key insight is that `current_user_can('administrator')` checks if the user has the 'administrator' role (WP treats it as a meta-capability that maps to checking the role), while `current_user_can('manage_options')` checks for the actual capability — which is semantically correct and matches the existing `check_admin_permission()` method in `REST\Base`.

## Recommendation

**Approach: Read capabilities from WP roles directly (source of truth), write via add_cap/remove_cap.**

The matrix should NOT store data in a separate `wp_option`. Instead:
- **Read**: Loop `wp_roles()->roles` to get each role's capabilities → build the matrix
- **Write**: Compare desired state to current state → call `$role->add_cap()` / `$role->remove_cap()` for changes
- This ensures `current_user_can()` always reflects the matrix without any extra lookups
- The `ROLES` constant in `UserRoles` stays for role registration (display names, initial setup on `after_switch_theme`), but after activation, the WP role definitions are the source of truth

The UI should be a new subtab "Capabilities" in the existing `ADMIN_SUBTABS` array, placed after "Rollen". It mirrors the `FunctiesTab` table layout: role labels as rows, capability labels as columns, checkboxes at intersections.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Admin subtab navigation | `ADMIN_SUBTABS` + `TabButton` in Settings.jsx | Pattern already handles routing, active state, permissions |
| Matrix table UI | `FunctiesTab` checkbox table in Settings.jsx | Near-identical pattern — roles×capabilities instead of functies×roles |
| REST endpoint registration | `register_rest_route` in `class-rest-api.php` | Follow exact same pattern as `/functie-capability-map` |
| Admin permission check | `check_admin_permission()` in `REST\Base` | Already checks `manage_options` correctly |
| API client methods | `prmApi` in `src/api/client.js` | Follow `getFunctieCapabilityMap` / `updateFunctieCapabilityMap` pattern |
| Role/capability manipulation | WordPress `get_role()→add_cap()/remove_cap()` | Native WP pattern already used in `UserRoles::register_role()` |

## Existing Code and Patterns

- `includes/class-user-roles.php` — Defines 7 custom roles + 5 custom capabilities in `ROLES` constant. `register_role()` uses `add_role()` + `add_cap()`. The constant stays for initial setup; the matrix becomes the ongoing management tool.
- `includes/class-rest-api.php:1317-1380` — `functie-capability-map` REST routes (GET/POST). Follow this exact pattern for the new capability-matrix endpoints.
- `includes/class-functie-capability-map.php` — Static config class with `get_map()`/`update_map()` using `wp_option`. Our endpoint won't need a separate config class since it reads/writes WP roles directly.
- `src/pages/Settings/Settings.jsx:2645-2775` — `FunctiesTab` component. Checkbox table with roles as columns, functies as rows. Near-identical UI pattern to reuse, but axes flipped.
- `src/pages/Settings/Settings.jsx:41-49` — `ADMIN_SUBTABS` array. Add `{ id: 'capabilities', label: 'Capabilities' }` here.
- `includes/class-rest-base.php:44-48` — `check_admin_permission()` method. Already correctly uses `manage_options`. All 6 role-name check fixes should use this same capability.
- `includes/class-rest-people.php:594,630,763` — Three `current_user_can('administrator')` usages. Lines 630 and 763 are permission checks; line 594 gates showing linked_user_roles.
- `includes/class-rest-teams.php:486` — One `current_user_can('administrator')` for team delete permission.
- `includes/class-rest-commissies.php:254,378` — Two `current_user_can('administrator')` for commissie edit/delete permissions.

## Constraints

- **WordPress role definitions are stored in `wp_options`** as serialized data under key `wp_user_roles`. Calling `add_cap()`/`remove_cap()` on a `WP_Role` object automatically updates this option. This is the standard WP pattern and is safe.
- **The `administrator` role must NOT be editable in the matrix** for its core WP capabilities (manage_options, etc.). Only the 5 custom Rondo capabilities should be toggleable on administrator. Otherwise admins could lock themselves out.
- **Capability sync (`CapabilitySync`) assigns ROLES, not capabilities directly.** Roles carry capabilities. The matrix changes what capabilities each role carries. This is complementary, not conflicting.
- **The `ROLES` constant** in `UserRoles` is still needed for `ensure_role_exists()` (fresh installs) and `get_role_slugs()` (used by CapabilitySync). The matrix doesn't replace role registration — it replaces capability assignment after registration.
- **Only Rondo custom capabilities should be in the matrix columns.** WordPress base capabilities (read, edit_posts, publish_posts, etc.) are structural and should not be exposed in the matrix — they're part of the base `rondo_user` role setup.

## Common Pitfalls

- **`current_user_can('administrator')` is NOT the same as checking `manage_options`.** In WordPress, passing a role name to `current_user_can()` checks if the user has that role — but it's officially deprecated behavior. Some plugins rely on it. All 6 instances should use `manage_options` which is the capability-based equivalent.
- **`ensure_role_exists()` could overwrite matrix changes.** Currently, `ensure_role_exists()` calls `register_role()` which runs `add_cap()` for all capabilities from `ROLES` constant. This runs on every `init` (priority 20). If the admin removes a capability via the matrix, `ensure_role_exists()` would add it back on next page load. **Mitigation:** Only add caps in `ensure_role_exists()` when the role doesn't exist yet (it already has this guard: `if ( ! get_role( $slug ) )`). The existing code already handles this correctly — it only calls `register_role()` if any role is missing, and `add_role()` is a no-op if the role exists. However, the inner `add_cap()` loop at line 80 DOES run on existing roles. This needs to be removed or gated to only run on fresh role creation.
- **Matrix save must be idempotent.** Sending the same matrix state twice should not cause errors or unintended changes. Using `add_cap()`/`remove_cap()` is naturally idempotent.
- **Administrator role capabilities** — The administrator role already has `manage_options` and all WP core capabilities. The matrix should show but not allow removing `manage_options` from administrator (would lock out). The 5 Rondo capabilities on administrator should be toggleable.
- **Race condition with CapabilitySync** — If a sync runs while an admin saves the matrix, roles could get capabilities re-applied. This is acceptable: CapabilitySync assigns roles, the matrix determines what capabilities those roles carry. They operate on different levels.

## Open Risks

- **`ensure_role_exists()` re-adding capabilities on every init** — The inner `add_cap()` loop at UserRoles line 78-82 runs on existing roles and could overwrite matrix changes. Must be fixed: only run `add_cap()` when creating a new role (move inside the `if ( ! get_role( $slug ) )` block, or only run on `after_switch_theme`). This is a critical fix to make the matrix actually work.
- **Removing a capability from a role affects all users with that role immediately.** The admin should understand this. A confirmation dialog or warning text in the UI would help.
- **The `rondo_bestuur` role currently grants ALL 5 custom capabilities.** If the admin removes one via the matrix, it diverges from the `ROLES` constant. This is by design (matrix overrides the constant), but needs to be clearly communicated.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress | `jeffallan/claude-skills@wordpress-pro` (1.8K installs) | available — not needed, project has strong established patterns |
| WordPress REST API | `wordpress/agent-skills@wp-rest-api` (381 installs) | available — not needed, existing patterns are sufficient |
| React / Tailwind | frontend-design skill | installed — not needed for this matrix UI (follows existing table patterns) |

## Six `current_user_can('administrator')` Locations

| # | File | Line | Context | Fix |
|---|------|------|---------|-----|
| 1 | `class-rest-people.php` | 594 | Gates showing `linked_user_roles` in person detail | → `manage_options` |
| 2 | `class-rest-people.php` | 630 | Person delete permission (author OR admin) | → `manage_options` |
| 3 | `class-rest-people.php` | 763 | Bulk delete permission check | → `manage_options` |
| 4 | `class-rest-teams.php` | 486 | Team delete permission (author OR admin) | → `manage_options` |
| 5 | `class-rest-commissies.php` | 254 | Commissie edit permission (author OR admin) | → `manage_options` |
| 6 | `class-rest-commissies.php` | 378 | Commissie delete permission (author OR admin) | → `manage_options` |

## Sources

- WordPress Role API: `wp_roles()`, `get_role()`, `WP_Role::add_cap()`, `WP_Role::remove_cap()` — standard WordPress core API
- Existing codebase patterns (FunctieCapabilityMap, FunctiesTab, UserRoles) — primary reference for implementation
