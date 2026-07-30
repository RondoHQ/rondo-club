# AGENTS.md

This file provides guidance to Agents when working with code in this repository.

## Claude specific
Prefer Read over `cat`, Grep over `grep/rg` in Bash, and Glob over `find` in Bash. Use Bash only for: running tests, executing build commands, git operations, and multi-step shell scripts. 

## Project Overview

**Rondo Club** is a React-powered WordPress theme for sports team management. This theme provides a modern, single-page application interface for managing people, teams, and club operations.

**Tech Stack:**
- Backend: WordPress 6.0+, PHP 8.0+, ACF Pro (required)
- Frontend: React 18, React Router 6, TanStack Query, Tailwind CSS v4
- Build: Vite 5.0

## Development Commands

All commands run from `rondo-club/`:

```bash
npm run dev      # Start Vite dev server (port 5173, HMR enabled)
npm run build    # Production build to dist/
npm run lint     # ESLint check (max-warnings: 0)
npm run preview  # Preview production build
composer lint    # phpcs — run before every deploy
composer test    # Codeception wpunit suite
```

**Important:** The deploy script (`bin/deploy.sh`) runs `npm run build` automatically before syncing. You do not need to build separately before deploying. However, when creating PRs, run `npm run build` to verify the frontend compiles before committing.

### Running the PHP test suite

**The suite is green: 389 tests, 0 failures. Keep it that way — a red suite is a regression, not
the status quo.** It also runs in CI on every push and pull request.

`composer test` needs a WordPress install with the theme symlinked in, ACF Pro, and MySQL (not
SQLite — it cannot evaluate the `DATETIME` meta comparisons this codebase uses). Full setup, and
the conventions for writing tests, are in **[docs/testing.md](docs/testing.md)**.

```bash
docker start rondo-test-db && composer test
vendor/bin/codecept run Wpunit AgeGroupAccessTest   # one file
```

Two things that will otherwise waste an afternoon:

- **REST routes must be booted in the test.** The theme only instantiates its controllers on real
  REST requests, so routes do not exist and every dispatch answers 404 — indistinguishable from a
  permission check working. Use `$this->bootRestControllers( [ Controller::class ] )`.
- **ACF Pro trips a `_doing_it_wrong()` notice** on person and shift select fields under WP 7.0.
  Silence it per class with `$this->ignoreIncorrectUsage( 'rest_handle_multi_type_schema' )`, never
  with `setExpectedIncorrectUsage()`.

## Fetching feedback items

Users file feature requests and bug reports as `rondo_feedback` posts, referenced by URLs like
`https://rondo.svawc.nl/feedback/8658`. In remote/cloud sessions there is no SSH access, but the
environment provides `RONDO_API_URL`, `RONDO_API_USER`, and `RONDO_API_PASSWORD` (a WordPress
application password). Fetch a feedback item with:

```bash
curl -sS -u "$RONDO_API_USER:$RONDO_API_PASSWORD" "$RONDO_API_URL/wp-json/rondo/v1/feedback/<id>"
```

The response contains `title`, `content`, `author`, and `meta` (feedback_type, status, priority,
`url_context` — the app page the user was on, `use_case`, plus steps/expected/actual for bugs).
Comments live at `/wp-json/rondo/v1/feedback/<id>/comments`. When you start work on an item, record
your branch, and when your work is ready for review, update the status (allowed: `new`, `approved`,
`in_progress`, `in_review`, `resolved`, `declined`, `needs_info`; resolving requires
`resolution_summary`):

```bash
curl -sS -X POST -u "$RONDO_API_USER:$RONDO_API_PASSWORD" \
  -H 'Content-Type: application/json' \
  -d '{"status":"in_review","agent_branch":"claude/feedback-<id>-xxxxx","pr_url":"<pr-url>"}' \
  "$RONDO_API_URL/wp-json/rondo/v1/feedback/<id>"
```

## Development Setup

1. Set `WP_DEBUG = true` in `wp-config.php` for development mode
2. Theme auto-detects Vite dev server at `http://localhost:5173` when debug is enabled
3. Production mode loads assets from `dist/` via manifest.json

## Architecture

### Backend (Theme Functions)

Entry point: `functions.php`

**Initialization flow (`rondo_init()`):**
- Checks for ACF Pro dependency
- Loads classes from `includes/` conditionally on `after_setup_theme` and `plugins_loaded`
- Core classes (PostTypes, Taxonomies, AccessControl, UserRoles, DemoProtection) load on every request
- REST API classes load only for REST requests; Reminders only for admin/cron
- iCal requests get early returns after loading only their specific class
- ~50 class files in `includes/`, organized by function

**Key class groups:**
- **Core:** PostTypes, Taxonomies, AccessControl, UserRoles, AutoTitle, VolunteerStatus
- **REST controllers:** Api (dashboard/search/timeline), People, Teams, Commissies, Todos, Feedback, Calendar, GoogleContacts, GoogleSheets, CustomFields, ImportExport
- **Collaboration:** CommentTypes (notes/activities), Mentions, MentionNotifications
- **Integrations:** GoogleOAuth, ICalFeed
- **Other:** Reminders, MembershipFees, FeeCacheInvalidator, VogEmail, DemoProtection, ClubConfig

**ACF field groups** are stored as JSON in `acf-json/` for version control.

### Frontend (React SPA)

Entry point: `src/main.jsx`

**React app structure:**
- `router.jsx` - Route definitions with lazy-loaded pages and ProtectedRoute
- `App.jsx` - Root layout wrapper (version check, theme, offline banner)
- `api/client.js` - Axios client with WordPress nonce injection
- `hooks/` - Custom hooks (useAuth, usePeople, useDashboard, etc.)
- `pages/` - Route components (People, Teams, Commissies, Feedback, VOG, Contributie, Settings, etc.)

**State management:**
- TanStack Query for server state/caching
- WordPress config via window globals (`wpApiSettings`)

**API client uses two namespaces:**
- `/wp/v2/` - Standard WordPress REST (people, teams, commissies)
- `/rondo/v1/` - Custom endpoints (dashboard, search, timeline)

## Data Model

**Custom Post Types:**
- `person` - Contact records with relationships, work history, photo gallery
- `team` - Teams with logo and contact info (post type slug remains `team` for backward compatibility)
- `commissie` - Committees with staff members and team structure
- `rondo_todo` - Task/todo items linked to people
- `rondo_feedback` - User feedback items with agent processing workflow
- `calendar_event` - Calendar events
- `discipline_case` - Discipline/incident tracking

**Taxonomies:**
- `relationship_type` - Relationship classifications
- `seizoen` - Season classification for discipline cases

## Access Control

- All approved users can see and edit all data (shared access model)
- Unapproved users see nothing until an administrator approves them
- Filtering applied at WP_Query level and REST API response level
- Authentication via WordPress session with REST nonce (`X-WP-Nonce` header)

## User Roles

- **Rondo User** - Custom role created automatically on theme activation
  - Minimal permissions: can create/edit/delete their own people and teams, upload files
  - Cannot access WordPress admin settings, manage other users, or install plugins/themes
  - Role is automatically removed on theme deactivation (users reassigned to Subscriber)

## Key Files

**Backend (PHP):**
- `functions.php` - Theme initialization, asset loading, SPA routing, class loading
- `includes/class-post-types.php` - All CPT registrations
- `includes/class-rest-api.php` - Core custom endpoints (dashboard, search, timeline)
- `includes/class-rest-people.php` - Person CRUD endpoints
- `includes/class-rest-feedback.php` - Feedback endpoints with comment support
- `includes/class-access-control.php` - Permission logic
- `includes/class-membership-fees.php` - Per-season fee category system

**Frontend (React):**
- `src/main.jsx` - React app entry point
- `src/router.jsx` - Route definitions with lazy loading
- `src/App.jsx` - Root layout (version check, theme, offline/install prompts)
- `src/api/client.js` - Axios client with nonce injection
- `src/hooks/` - Custom React hooks
- `src/pages/` - Route components
- `src/components/layout/Layout.jsx` - Sidebar navigation, capability-based menu filtering
- `vite.config.js` - Build configuration

## Mollie Payment Integration

### Critical Rule: Always Use Payment Links API

**NEVER use `$mollie->payments->create()` for any payment that could be opened later.** Regular Mollie payments expire in ~15 minutes. Use `$mollie->paymentLinks->create()` instead — payment links remain valid until paid or archived.

This applies to all payments, including installments. The only exception would be a payment where the user is immediately redirected to Mollie checkout in the same request (and even then, payment links are preferred for consistency).

### Two Mollie APIs

| API | Method | Expiry | Webhook ID | Use case |
|-----|--------|--------|------------|----------|
| Payment Links | `$mollie->paymentLinks->create()` | Never | `pl_xxx` | All invoices and installments |
| Payments | `$mollie->payments->create()` | ~15 min | `tr_xxx` | **Legacy only** — do not use for new code |

### Payment Flows

**Membership fee invoices** (created by cron via `MembershipFees`):
1. Invoice created → `PublicPaymentPage::generate_token()` stores `/betaling/{token}` as `payment_link` ACF field
2. Email sent with `{betaallink}` → links to `/betaling/{token}` (public page, always valid)
3. Member visits public page → selects plan (full / 3 termijnen / 8 termijnen)
4. `InstallmentPaymentService::create_payment()` creates a Mollie **payment link** (`pl_xxx`)
5. Member is redirected to Mollie checkout
6. Webhook fires → routes through Path 0a (installment payment link)
7. For multi-installment plans: webhook marks installment paid, creates next payment link, cron sends email when due

**Discipline case invoices** (created manually via admin):
1. `MolliePayment::create_payment_link()` creates a Mollie **payment link** (`pl_xxx`)
2. Checkout URL stored in `payment_link` ACF field and emailed directly
3. Webhook fires → routes through Path 0b (full payment link)

### Webhook Routing (4 paths)

File: `includes/class-mollie-webhook.php` — single endpoint at `POST /rondo/v1/mollie/webhook`

| Path | ID prefix | Lookup | Handler |
|------|-----------|--------|---------|
| **0a** | `pl_xxx` | `_mollie_pid_{pl_xxx}` (EXISTS) | `handle_installment_paid()` — marks installment, checks all-paid, creates next |
| **0b** | `pl_xxx` | `_mollie_payment_link_id` = `pl_xxx` | Direct transition to `rondo_paid` |
| **1** | `tr_xxx` | `_mollie_pid_{tr_xxx}` (EXISTS) | `handle_installment_paid()` — legacy installments |
| **2** | `tr_xxx` | `_mollie_payment_id` = `tr_xxx` | Direct transition to `rondo_paid` — legacy full payments |

### Key Files

| File | Purpose |
|------|---------|
| `includes/class-mollie-payment.php` | `MolliePayment` — creates payment links for discipline/full invoices |
| `includes/class-installment-payment-service.php` | `InstallmentPaymentService` — creates payment links for installments |
| `includes/class-mollie-webhook.php` | `MollieWebhook` — handles all incoming Mollie webhook notifications |
| `includes/class-mollie-client.php` | `MollieClient` — wraps Mollie SDK initialization with API key from config |
| `includes/class-public-payment-page.php` | `PublicPaymentPage` — standalone HTML page at `/betaling/{token}` for plan selection |
| `includes/class-installment-email-sender.php` | `InstallmentEmailSender` — sends installment emails and reminders with payment links |
| `includes/class-invoice-email-sender.php` | `InvoiceEmailSender` — sends discipline case invoice emails with PDF attachments |

### Meta Storage Pattern

Invoices use flat numbered post meta for installment tracking:

```
_installment_plan              → full | quarterly_3 | monthly_8
_installment_count             → number of installments
_installment_{N}_amount        → base amount for installment N
_installment_{N}_admin_fee     → admin fee for installment N
_installment_{N}_status        → pending | sent | betaald
_installment_{N}_due_date      → Y-m-d due date
_installment_{N}_mollie_payment_id → pl_xxx (payment link ID)
_installment_{N}_payment_link  → Mollie checkout URL
_mollie_pid_{pl_xxx}           → N (reverse-lookup for O(1) webhook matching)
_payment_token                 → 64-char hex token for public payment page
_mollie_payment_link_id        → pl_xxx (for discipline case invoices via MolliePayment)
```

## Git Workflow

This is a single repository containing both backend (PHP) and frontend (React) code. All changes should be committed together to keep the system in sync.

## Extending the System

**Adding ACF fields:** Edit in WordPress admin when `WP_DEBUG` is true; changes auto-save to `acf-json/`

**Adding REST endpoints:** Extend `Rondo\REST\Api` class in `includes/class-rest-api.php`

**Adding React pages:** Create component in `src/pages/`, add route in `src/router.jsx`

**Adding PHP classes:** Create new class file in `includes/`, load it in `functions.php` via `rondo_init()`

## Common Pitfalls

### ACF select fields reject empty strings via REST — coerce to `null` on the client

**Symptom — this exact error has bitten us multiple times:**
```
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): acf",
  "data": {
    "params": { "acf": "acf[<field_name>] is not one of <choice1>, <choice2>, ..." }
  }
}
```

**Why it happens:**
ACF auto-generates a JSON Schema for every field exposed in REST. For `type: "select"` fields it emits `enum: [<choice1>, <choice2>, ...]`. **That enum does NOT include `""` — even when the field has `allow_null: 1` and `default_value: ""` in its ACF config.** So any REST update whose `acf` payload contains `<field>: ""` is rejected by WP REST schema validation *before* any of our PHP code runs. The whole request fails, including the unrelated field the user was actually trying to change (e.g. a relationship update).

**The frontend trigger:**
Most person/team writes round-trip the full `acf` object (read it, mutate one field, send it all back). So a person editing a *relationship* still POSTs `vergoeding_reden: ""` if the person isn't a paid volunteer. Same for any other select field that's empty on this record.

**The fix — always done on the client:**
Add the field name to the `enumFields` list inside the relevant sanitizer in `src/utils/formatters.js` (`sanitizePersonAcf`, `sanitizeTeamAcf`, etc.). That sanitizer converts `""` → `null` before submit, which IS accepted by the schema.

**When you add a new ACF select to any CPT:**
1. Add the field name to the `enumFields` array of the corresponding sanitizer in `src/utils/formatters.js`.
2. Make sure every code path that builds an ACF payload routes through that sanitizer (`sanitizePersonAcf(person.acf, { ... })`), not raw object spread.
3. Same applies to `radio` and `button_group` ACF types — they generate the same enum schema.

**If you see this error in production:** grep the field name out of the error message, check it's a select-type field in `acf-json/`, then add it to the sanitizer. Don't try to fix it server-side by loosening the ACF schema — that fights the framework and breaks the next ACF Pro upgrade.

### `former_member=true` persons are read-only end-to-end

Sportlink rejects every contact / profile write for the lidsoorten that map to `former_member=true` ("Oud bondslid", "Oud verenigingslid"). Accepting an edit here just generates reverse-sync work in the `rondo-sync` repo that can never land. The policy is enforced in two places that **MUST stay in sync** if you touch either side:

- **Backend:** `class-rest-people.php` — `block_former_member_edits()` on the `rest_pre_insert_person` filter rejects non-admin ACF writes with `HTTP 403 rondo_former_member_readonly`. Admins (incl. the `RONDO_USERNAME`-authenticated sync service user with `manage_options`) are exempt so the forward sync keeps working on former-member records.
- **Frontend:** `src/pages/People/PersonDetail.jsx` — `canEditPeople` flips to `false` when `acf.former_member === true`, hiding every existing edit affordance. A "Oud-lid — alleen-lezen" banner explains why and tells the user to ask a beheerder.

The only allowed non-admin write is the `former_member` field itself, so an admin can flip a person back to active to make them editable. When adding new edit UI: route through the same `canEditPeople` gate (don't introduce a parallel "can edit" boolean), and don't try to relax the REST filter for "just one field" — the next reverse-sync loop will find you.

### ACF `date_picker` fields store `YYYYMMDD`, not `YYYY-MM-DD` — use `parseAcfDate()`

ACF persists `date_picker` values in compact `Ymd` format (e.g. `"20140708"`) regardless of `return_format`. `new Date("20140708")` returns `Invalid Date`. Affects any date field returned by `wp/v2/people` via ACF — `birthdate`, `lid-sinds`, `lid-tot`, `datum-vog`, every `work_history.start_date`/`end_date`, etc.

Use `parseAcfDate()` from `src/utils/formatters.js` — it handles both `YYYYMMDD` and `YYYY-MM-DD`. `isValidDate()` already delegates to it. Anywhere you'd write `new Date(acf.foo_date)` to format an ACF date, write `parseAcfDate(acf.foo_date)` instead. This bit us once when work_history dates rendered as empty `" - "` for every sync-written entry — only the legacy hand-entered records survived because they were in the other format.

## Required rules for every change

### Rule 0: Use WordPress & ACF native data models

**NEVER create custom database tables.** Always use WordPress native data storage:

| Data Type | Storage Method |
|-----------|---------------|
| Entities (contacts, events, etc.) | Custom Post Types (`register_post_type`) |
| Entity metadata | Post meta (`update_post_meta`, `get_post_meta`) |
| Complex/repeatable fields | ACF field groups (stored in `acf-json/`) |
| Categories/tags | Custom Taxonomies (`register_taxonomy`) |
| User settings | User meta (`update_user_meta`, `get_user_meta`) |
| Site-wide settings | Options API (`update_option`, `get_option`) |
| Temporary/cached data | Transients API (`set_transient`, `get_transient`) |

**Use WordPress native functions:**
- Queries: `WP_Query`, `get_posts()`, `get_users()` - never raw SQL
- CRUD: `wp_insert_post`, `wp_update_post`, `wp_delete_post`
- Cron: `wp_schedule_event`, `wp_cron` hooks
- REST: Extend `WP_REST_Controller` or register via `register_rest_route`
- Caching: Use WordPress object cache, transients, or WP_Query caching

**ACF for complex fields:**
- Use ACF for repeaters, flexible content, relationships
- Store field groups in `acf-json/` for version control
- Access via `get_field()`, `update_field()`, or native `get_post_meta()` with ACF field keys

### Rule 1: Semantic Versioning

### What is it?

*Semantic Versioning* follows the format: ⁠ MAJOR.MINOR.PATCH ⁠ (bijv. ⁠ 1.10.7 ⁠)

* ⁠*MAJOR* (x.0.0): Breaking changes which break existing functionality
* ⁠*MINOR* (0.x.0): New features that are backward compatible
* ⁠*PATCH* (0.0.x): Bug fixes and small improvements

Update the version of the theme in style.css and package.json after every milestone, following the semantic versioning system.

## Rule 2: Update the Changelog

After each milestone, add a changelog entry in in [Keep a Changelog](https://keepachangelog.com/) format:

* ⁠*Added*: New features
* ⁠*Changed*: Changes in existing functionality
* ⁠*Fixed*: Bug fixes
* ⁠*Removed*: Removed features

You will find the changelog in /CHANGELOG.md

### Rule 3: Don't Repeat Yourself (DRY)

Apply DRY principles to all coding. If you see multiple changes you're doing are the same code, make sure you properly apply DRY principles and clean up the code where possible.

### Rule 4: 95% sure rule

If you're less than 95% sure about the changes you're going to make: *ASK QUESTIONS!*

*When you should ask:*
* Before making big changes
* For architectural decisions
* When you have unclear requirements
* When there are trade-offs between options
* Before making big architectural changes

*How you should ask:*
* ⁠Present up to 3 options with pros and cons
* Select your recommended option and tell me why
* Wait for approval before implementing
* When making big architectural changes, explain what you're going to do and ask for confirmation.

### Rule 5: Self testing

Test your changes as much as you can before claiming something works.

### Rule 6: Update documentation

Developer documentation lives in the **developer docs site** at `../developer/src/content/docs/`. This site is deployed to `developer.rondo.club`.

When making changes, update the relevant docs there. Each doc file requires Starlight frontmatter (`title:` in YAML front matter). The docs are organized as:
- `api/` — Rondo Club REST API reference
- `features/` — Feature documentation (access control, relationships, etc.)
- `integrations/` — iCal, import
- `architecture/` — Frontend, PHP autoloading, relationship system

PRDs and product specs still live in `docs/prd/` within this repo.

If the (sub-)system you made changes to is not documented yet, document that system too.

### Rule 7: Git Commit & Push

#### What is it?

*ALWAYS* commit & push after every milestone phase, with clear commit messages:

*Format*: ⁠ 

type: Description ⁠
* ⁠⁠ feat: ⁠ - New feature
* ⁠⁠ fix: ⁠ - Bug fix
* ⁠⁠ chore: ⁠ - Maintenance (version updates, config changes)
* ⁠⁠ docs: ⁠ - Documentation
* ⁠⁠ refactor: ⁠ - Code refactoring
* ⁠⁠ perf: ⁠ - Performance improvement
* ⁠⁠ style: ⁠ - Code formatting

*Workflow:*
```⁠ bash
cd "<project directory>"
git add -A
git commit -m "<git commit summary>"
git push
```

### Rule 8: Deploy to Production

**WHEN you're in a worktree, do not EVER push to production**

**ALWAYS deploy to production BEFORE asking for verification or UAT.** The user tests on production, not locally.

Production deployment is automatic:

1. Commit and push the milestone.
2. A commit on `main` runs `.github/workflows/ci.yml`.
3. JavaScript lint/build and PHP coding standards must pass.
4. GitHub Actions builds a production release, deploys it over SSH, clears
   WordPress and SiteGround caches, and verifies the live version and URL.
5. Wait for the **CI and deploy** workflow to complete successfully before
   presenting verification or UAT steps.

Feature and worktree branches do not deploy. When a pull request is used, merge
it into `main` and wait for the resulting `main` workflow.

`bin/deploy.sh` is a break-glass fallback only. It reads exported deployment
variables first and uses `.env` only when required values are absent:

```bash
bin/deploy.sh --prune
```

Use the **Roll back production** GitHub workflow with a full earlier commit SHA
from `main` to restore a previous release.

**Production URL:** See the GitHub `production` environment or local `.env`.

### Rondo Club Sites

All three sites are on the same server. SSH always requires **port 18765** and uses key-based auth.

| Site | URL | SSH User | Purpose |
|------|-----|----------|---------|
| **Production** | https://rondo.svawc.nl | `u27-qkfuzqfj63zn` | Live site — deploy target, user tests here |
| **Demo** | https://demo.rondo.club | `u26-b0fnaayuzqqg` | Demo environment |
| **Old site** | https://stadion.svawc.nl | `u19-uuugmprbtnev` | Legacy — only access when explicitly asked |

**SSH host:** `c1130624.sgvps.net`
**SSH port:** `18765`

Each user's WordPress install lives at `~/www/<domain>/public_html/` and the theme at `.../wp-content/themes/rondo-club/`.

### SSH Access

When connecting via SSH, **always include the port**. Use `.env` for production credentials:

```bash
source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "command here"
```

For WP-CLI commands on production:

```bash
source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "cd $DEPLOY_REMOTE_WP_PATH && wp <command>"
```

**Important:** Never deploy to or run destructive commands on the old site (stadion.svawc.nl) unless the user specifically asks.
