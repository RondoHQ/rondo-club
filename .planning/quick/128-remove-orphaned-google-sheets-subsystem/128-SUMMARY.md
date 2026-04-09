---
phase: quick-128
plan: "01"
subsystem: fees/google-sheets
tags: [cleanup, dead-code, deletion, tech-debt]
dependency_graph:
  requires: []
  provides: []
  affects:
    - includes/class-rest-google-sheets.php
    - includes/class-google-oauth.php
    - includes/class-google-sheets-connection.php
    - src/api/client.js
    - functions.php
tech_stack:
  removed:
    - Google OAuth 2.0 flow for Google Sheets API (subsystem-specific)
    - rondo/v1/google-sheets/* REST namespace
    - _rondo_google_sheets_connection user_meta key (no rows on prod)
key_files:
  deleted:
    - includes/class-rest-google-sheets.php
    - includes/class-google-oauth.php
    - includes/class-google-sheets-connection.php
  modified:
    - src/api/client.js
    - functions.php
decisions:
  - Verified zero user_meta rows on prod with `_rondo_google_sheets_connection` meta key before deletion — no stale data to clean up
  - Left historical references intact in CHANGELOG.md, AGENTS.md, docs/prd/, .gsd/DECISIONS.md, .claude/codebase-map.md (history, not live code)
  - Manual `ssh + rm + composer dump-autoload + wp cache flush` on prod after deploy because bin/deploy.sh doesn't --delete theme files (second occurrence after phase 218; warrants a --prune flag in a future tooling pass)
  - No regression net captured beyond composer lint + npm build + HTTP 200 smoke check — the code was already unreachable, so there was nothing to regress
metrics:
  duration: ~15min
  completed: 2026-04-09
  lines_removed: 1710
  commit: 68c63652
---

# Quick Task 128: Remove orphaned Google Sheets subsystem

## Context

v29.0 "Made in Europe" partially removed the Google sync subsystem (Google Contacts, Gmail, Calendar) but left the Google Sheets OAuth + export pieces in place. The audit of v29.0 and subsequent milestones flagged this as tech debt: 3 PHP classes + 5 client.js methods + 5 REST routes that nothing reachable in the frontend called.

This task cleans up that debt.

## What was deleted

### PHP (1,693 lines across 3 files)
- `includes/class-rest-google-sheets.php` (1,331 lines) — REST controller with 6 routes: `/google-sheets/status`, `/google-sheets/auth`, `/google-sheets/callback`, `/google-sheets/disconnect`, `/google-sheets/export-people`, `/google-sheets/export-fees`
- `includes/class-google-oauth.php` (213 lines) — OAuth flow helpers (`is_configured`, `get_sheets_auth_url`, `handle_sheets_callback`, `has_sheets_scope`, `get_access_token`)
- `includes/class-google-sheets-connection.php` (149 lines) — user_meta storage for connection state (`_rondo_google_sheets_connection` key)

### JavaScript (5 orphaned client.js methods)
- `getSheetsStatus`
- `getSheetsAuthUrl`
- `disconnectSheets`
- `exportPeopleToSheets`
- `exportFeesToSheets`

### functions.php wiring
- `use Rondo\REST\GoogleSheets as RESTGoogleSheets;`
- `use Rondo\Sheets\GoogleOAuth;`
- `use Rondo\Sheets\GoogleSheetsConnection;`
- `new RESTGoogleSheets();` registration

## Verification

1. **Zero orphaned user_meta rows** — `wp db query "SELECT COUNT(*) FROM wp_usermeta WHERE meta_key = '_rondo_google_sheets_connection'"` returned 0 on prod. No cleanup needed for stored OAuth tokens.
2. **Zero live code references** — `grep -rn 'GoogleSheets\|GoogleOAuth\|google-sheets' includes/ src/ functions.php bin/` returned empty after the delete.
3. **composer lint clean** — all 8 modified PHP files lint cleanly.
4. **npm run build succeeds** — frontend bundles cleanly; client.js edits did not break anything.
5. **Prod smoke check:**
   - `GET https://rondo.svawc.nl/` → HTTP 200, 16.9KB HTML (theme loads normally)
   - `GET https://rondo.svawc.nl/wp-json/rondo/v1/` → HTTP 200 (REST namespace still registered)
   - `GET https://rondo.svawc.nl/wp-json/rondo/v1/google-sheets/status` → HTTP 404 `rest_no_route` (deletion confirmed)

## Deploy wrinkle

Same issue as Phase 218: `bin/deploy.sh` uses rsync without `--delete` on theme files, so the 3 deleted PHP files stayed on prod after the normal deploy and would have been re-indexed by `composer dump-autoload` as orphaned classes. Fixed with:

```bash
source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
  'cd ~/www/rondo.svawc.nl/public_html/wp-content/themes/rondo-club && \
   rm -f includes/class-rest-google-sheets.php includes/class-google-oauth.php includes/class-google-sheets-connection.php && \
   composer dump-autoload -o --quiet && \
   cd ~/www/rondo.svawc.nl/public_html && wp cache flush'
```

Second time this manual cleanup was needed. Worth a `bin/deploy.sh --prune` flag as a future quick task.
