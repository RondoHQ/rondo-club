# M012: PHP Code Quality Refactor — Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

## Project Description

Systematic refactoring of the Rondo Club PHP codebase to improve maintainability, reduce duplication, and split oversized classes. The codebase currently has ~48.7K lines of PHP across ~70 files, with a single god-class (`class-rest-api.php`) containing 7,854 lines, 68 routes, and 85+ public methods spanning 10+ unrelated domains.

## Why This Milestone

The REST API god-class has grown organically over 32 versions. Every new feature adds methods to it, making it harder to navigate, review, and test. There are also ~390 lines of duplicated sharing/logo code across controllers, ~340 lines of login customization inline in functions.php, 33 mostly-dead class aliases, and legacy migration code that still runs on every request. Addressing this now prevents further compounding — every new feature added to the god-class makes the eventual split harder.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Experience the same exact application behavior — zero functional changes
- (Developer) Navigate and understand the PHP codebase more easily with focused, single-responsibility classes

### Entry point / environment

- Entry point: https://rondo.svawc.nl (production WordPress site)
- Environment: Production server via deploy script
- Live dependencies involved: WordPress REST API, ACF Pro, Mollie, Lettermint

## Completion Class

- Contract complete means: `npm run build` succeeds, deploy completes, all REST API endpoints respond identically
- Integration complete means: Production site works without errors after deploy
- Operational complete means: All cron jobs, webhooks, and background processes continue functioning

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Production site loads and all navigation works (SPA, login, search, dashboard)
- REST API endpoints return identical responses before/after refactor
- No PHP errors in debug.log after deploy

## Risks and Unknowns

- **Breaking REST API routes** — Incorrect class extraction could break endpoint registration order or namespace conflicts. Mitigated by deploying after each slice and testing.
- **Class alias removal breaking external consumers** — rondo-sync or other code might reference old class names. Must verify before removing.
- **PSR-4 autoloading** — New files must follow the namespace-to-directory mapping in composer.json. Currently all files are flat in `includes/`.

## Existing Codebase / Prior Art

- `includes/class-rest-api.php` — 7,854-line god-class, primary refactoring target
- `includes/class-rest-base.php` — 245-line abstract base class for REST controllers
- `includes/class-rest-people.php` — Contains duplicated sharing code
- `includes/class-rest-teams.php` — Contains duplicated sharing + logo upload code
- `includes/class-rest-commissies.php` — Contains duplicated sharing + logo upload code
- `functions.php` — 1,457 lines with login customization, dead aliases, legacy migrations

## Scope

### In Scope

- Split `class-rest-api.php` into ~10 focused controllers
- Extract duplicated sharing code to base class
- Extract duplicated logo upload code to base class
- Extract login customization from functions.php to a class
- Remove dead class aliases
- Remove completed migration code and orphaned cron cleanup
- Update functions.php imports and initialization

### Out of Scope / Non-Goals

- Functional changes — no new features, no behavior changes
- Frontend changes — React code is untouched
- Performance optimization of search queries (noted but separate effort)
- Splitting class-wp-cli.php (lower priority, can be a future milestone)
- PSR-4 directory restructuring (e.g., moving files into subdirectories matching namespaces)

## Technical Constraints

- Must maintain exact REST API endpoint compatibility (routes, parameters, responses)
- Must follow existing PSR-4 autoloading: `Rondo\` namespace maps to `includes/`
- Must deploy to production after each slice for verification
- WordPress class loading order matters — classes used by others must load first
