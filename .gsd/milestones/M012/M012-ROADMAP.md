# M012: PHP Code Quality Refactor

**Vision:** Transform the PHP codebase from a monolithic god-class architecture to focused, single-responsibility controllers — improving navigability, testability, and maintainability without any functional changes.

## Success Criteria

- All REST API endpoints return identical responses before/after refactor
- No PHP errors on production after final deploy
- `class-rest-api.php` reduced from 7,854 lines to <1,500 lines (core dashboard/search/version)
- Zero duplicated sharing/logo code across controllers
- `functions.php` reduced by removing dead aliases, legacy migration, and extracting login customization
- Every new controller follows the existing `Base` extension pattern

## Key Risks / Unknowns

- **Route registration order** — WordPress REST API is order-insensitive for routes, but if any code depends on class instantiation order, splitting could break it
- **Shared private helpers** — Some private methods in the god-class are used by multiple route handlers that will land in different new classes. These need to be identified and moved to Base or a trait.

## Proof Strategy

- Route registration order → retire in S01 by deploying the first extraction and verifying all endpoints
- Shared private helpers → retire in S01 by mapping internal call graph before extraction

## Verification Classes

- Contract verification: `npm run build` succeeds, PHP syntax check (`php -l`) on all new files
- Integration verification: Deploy to production, verify endpoints respond correctly
- Operational verification: Cron jobs, webhooks, and public pages continue working
- UAT / human verification: Browse production site, confirm all pages load, search works, dashboard loads

## Milestone Definition of Done

This milestone is complete only when all are true:

- All slices deployed to production and verified
- `class-rest-api.php` contains only dashboard, search, version, and core utility routes
- Duplicated code extracted to shared locations
- `functions.php` cleaned of dead code and extracted login customization
- No PHP errors in production debug.log

## Slices

- [x] **S01: Extract User Settings & User Management controllers** `risk:high` `depends:[]`
  > After this: User preferences, notification channels, dashboard settings, user provisioning, and password changes are served from dedicated `class-rest-user-settings.php` and `class-rest-users.php` — verified by deploying to production and confirming the settings page and user management work

- [x] **S02: Extract Reminders, VOG & Fees controllers** `risk:medium` `depends:[S01]`
  > After this: Reminders/anniversaries, VOG bulk operations, and membership fee settings/list are served from `class-rest-reminders.php`, `class-rest-vog.php`, and `class-rest-fees.php` — verified on production

- [x] **S03: Extract Lettermint, Finance Settings & Capability Matrix controllers** `risk:medium` `depends:[S01]`
  > After this: Email integration, finance settings/branding, billing settings, and the capability matrix/role management are served from `class-rest-lettermint.php`, `class-rest-finance-settings.php`, and `class-rest-capabilities.php` — verified on production

- [ ] **S04: DRY extraction — sharing code, logo uploads, and Base class improvements** `risk:medium` `depends:[S01]`
  > After this: Sharing and logo upload code lives in Base, eliminating ~470 duplicated lines across People/Teams/Commissies controllers

- [ ] **S05: functions.php cleanup — login class, dead aliases, legacy code removal** `risk:low` `depends:[S01]`
  > After this: Login customization is a proper class, dead class aliases removed, legacy migration code removed, orphaned cron cleanup removed — functions.php reduced by ~500 lines

## Boundary Map

### S01 → S02, S03

Produces:
- Proven pattern for extracting controllers from the god-class (file structure, namespace, imports, `functions.php` wiring)
- Any shared helpers identified and moved to Base during S01

Consumes:
- nothing (first slice)

### S01 → S04

Produces:
- Stable Base class that S04 will extend with sharing/logo methods

### S01 → S05

Produces:
- Confidence that class loading changes in `functions.php` work correctly after deploy
