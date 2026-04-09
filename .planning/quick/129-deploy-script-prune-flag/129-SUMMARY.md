---
phase: quick-129
plan: "01"
subsystem: tooling/deploy
tags: [deploy, tooling, cleanup, rsync]
dependency_graph:
  requires: []
  provides: [--prune flag on bin/deploy.sh]
  affects:
    - bin/deploy.sh
tech_stack:
  added: [rsync --delete on targeted directories]
  patterns: [surgical prune over blanket delete]
key_files:
  created: []
  modified:
    - bin/deploy.sh
decisions:
  - Opt-in flag (`--prune`) rather than default-on. First version is safer explicit; can flip the default later if it proves reliable across all deletion scenarios.
  - Surgical prune over blanket `--delete` — the main Step 3 rsync still has no `--delete`, so prod-only files (acf-json/*.json, blog-post.md, README.md, php_errorlog, SECURITY.md, SUPPORT.md, CODE_OF_CONDUCT.md, logs/) stay untouched.
  - Prune targets configurable via `PRUNE_DIRS` bash array inside deploy.sh. Default: `(includes bin src)` — the three directories whose contents are fully git-tracked. Tests/ and scripts/ not included because they may not exist on prod (tests are dev-only, scripts/ exists locally but is not synced in current flow).
  - Separate Step 3b after the main sync — keeps the existing Step 3 behaviour byte-identical when `--prune` is not passed, so the default deploy path is unchanged for backwards compatibility.
  - Skip missing directories gracefully — if a PRUNE_DIRS entry doesn't exist locally, print a skip message instead of erroring. Lets the array hold optional dirs without brittleness.
metrics:
  duration: ~15min
  completed: 2026-04-09
  commit: (pending)
---

# Quick Task 129: bin/deploy.sh --prune flag

## Context

Phase 218 (retire MembershipFees) and quick task 128 (orphaned Google Sheets subsystem) both required a manual `ssh + rm + composer dump-autoload + wp cache flush` step because `bin/deploy.sh` uses rsync without `--delete`, so files deleted locally stay orphaned on prod. Retrospective flagged this as tooling debt to fix before the next class-deletion task.

This is that fix.

## What changed

Added `--prune` flag to `bin/deploy.sh`. When passed, adds a new **Step 3b: Pruning deleted files** after the main sync that runs `rsync -az --delete` on each directory in `PRUNE_DIRS` (default: `includes`, `bin`, `src`). Any file present on prod under those directories that doesn't exist locally is removed.

- Default behaviour (no flag) is **unchanged** — same rsync, same exclude list, same Step 3 output.
- With `--prune`, Step 3b runs targeted deletes on only the whitelisted directories.
- Prod-only files outside those directories (`acf-json/`, `blog-post.md`, `README.md`, `php_errorlog`, `logs/`, `SECURITY.md`, etc.) are **never touched**.
- Missing directories are skipped with a message rather than erroring.

### Code delta

```diff
+ PRUNE_DELETED=false
+ PRUNE_DIRS=(includes bin src)
+
  for arg in "$@"; do
      case $arg in
+         --prune)
+             PRUNE_DELETED=true
+             shift
+             ;;
          ...
      esac
  done
+
+ # Step 3b: Prune deleted files (opt-in via --prune)
+ if [ "$PRUNE_DELETED" = true ]; then
+     echo -e "${YELLOW}Step 3b: Pruning deleted files from ${PRUNE_DIRS[*]}...${NC}"
+     for dir in "${PRUNE_DIRS[@]}"; do
+         if [ ! -d "$PROJECT_ROOT/$dir" ]; then
+             echo "  (skipping ${dir}/ — not present locally)"
+             continue
+         fi
+         echo "  ${dir}/"
+         rsync -az --delete \
+             -e "ssh -p $DEPLOY_SSH_PORT" \
+             "$PROJECT_ROOT/$dir/" \
+             "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_THEME_PATH/$dir/"
+     done
+ fi
```

## Test method

1. **Plant a dummy file on prod** via SSH:
   ```bash
   ssh ... 'touch ~/www/rondo.svawc.nl/public_html/wp-content/themes/rondo-club/includes/_prune_test.php'
   ```
2. **Record prod-only files** that should NOT be affected: `blog-post.md`, `README.md`, `php_errorlog`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `SUPPORT.md`.
3. **Run** `bin/deploy.sh --prune`.
4. **Verify:**
   - `includes/_prune_test.php` is **gone** from prod ✓
   - All 6 prod-only files are **still present** with their original timestamps ✓
   - `includes/` file count on prod matches local (84) ✓
   - `bin/` file count on prod matches local (8) ✓

## Usage going forward

Use `--prune` whenever a deploy removes PHP classes, tooling scripts, or React source files:

```bash
bin/deploy.sh --prune
```

For deploys that only add or modify files, the default `bin/deploy.sh` is fine.

## Follow-up (not blocking)

- **Consider flipping the default to on** after 1-2 more successful `--prune` deployments. The current opt-in is conservative for the first version.
- **Extend `PRUNE_DIRS`** if new fully-tracked directories are added to the project in the future (e.g. `scripts/` if it ever gets synced to prod).
- **Integrate with direct-style phase completion** — when a phase deletes files, the phase NOTES.md should mention that `--prune` is needed. Could also be automated via a post-commit hook that inspects `git diff --diff-filter=D` and warns if a deploy is about to ship without `--prune`.
