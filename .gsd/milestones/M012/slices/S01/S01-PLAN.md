# S01: Extract User Settings & User Management controllers

**Slice goal:** Extract all `/user/*` routes into `class-rest-user-settings.php` and all `/users/*` + provisioning routes into `class-rest-users.php`, proving the god-class extraction pattern.

## Tasks

- [x] **T01: Extract UserSettings controller** `est:45min`
  Extract routes: `/user/notification-channels` (GET/POST), `/user/notification-time`, `/user/mention-notifications`, `/user/dashboard-settings` (GET/PATCH), `/user/list-preferences` (GET/PATCH), `/user/linked-person` (GET/POST), `/user/me`, `/user/password` — plus `validate_dashboard_cards()` and `get_available_columns_metadata()` private helpers.

- [x] **T02: Extract Users controller** `est:30min`
  Extract routes: `/users` (GET), `/users/{id}` (DELETE), `/users/provisionable`, `/users/search`, `/provisioning/settings` (GET/POST), `/user/provision` (POST) — plus `delete_user_posts()` and `get_provisionable_users()` private helpers.

- [x] **T03: Wire up in functions.php and remove from Api** `est:20min`
  Add `use` imports and instantiation in `rondo_init()`. Remove extracted methods and routes from `class-rest-api.php`. Add class aliases if needed for backward compat.

- [x] **T04: Build, deploy, and verify** `est:15min`
  Run `npm run build`, deploy to production, verify settings page works, user management works, search users works, no PHP errors.

## Acceptance Criteria

- All `/user/*` routes served from `Rondo\REST\UserSettings`
- All `/users/*` routes served from `Rondo\REST\Users`
- `class-rest-api.php` reduced by ~1,200 lines
- Production site works without errors
