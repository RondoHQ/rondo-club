---
phase: 219-finance-settings-snapshot-harness
plan: "01"
subsystem: finance-settings
status: complete
completed: "2026-04-09"
requirements_satisfied: [FIN-01]
standing_requirements_activated: [FIN-11, FIN-12]
tags: [snapshot, regression-harness, finance, v34.0]
dependency_graph:
  requires: []
  provides: [v34.0-baseline-snapshot, finance-settings-snapshot-harness]
  affects: [phases 220-224 regression gates]
tech_stack:
  added: []
  patterns: [wp-eval-file-snapshot, ssh-pipe-php, jq-S-diff]
key_files:
  created:
    - bin/finance-settings-snapshot.php
    - bin/finance-settings-snapshot.sh
    - .planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json
  modified: []
decisions:
  - Namespace is Rondo\Config\FinanceConfig (not Rondo\Finance\FinanceConfig as plan assumed)
metrics:
  duration_minutes: 30
  tasks_completed: 3
  files_created: 3
  files_modified: 0
---

# Phase 219 Plan 01 — Finance Settings Snapshot Harness

One-liner: WP-CLI-backed finance settings snapshot harness capturing all 48 finance-surface options + full `FinanceConfig::get_all_settings()` REST response as a deterministic JSON envelope, with v34.0 golden-state baseline committed.

## What shipped

- `bin/finance-settings-snapshot.php` — WP eval-file payload; 48-key allowlist (40 `rondo_finance_*` + 8 `rondo_membership_pass_*`), hard count assertion (exits 1 if drift), calls `Rondo\Config\FinanceConfig::get_all_settings()`, recursive key-sort for deterministic output.
- `bin/finance-settings-snapshot.sh` — bash wrapper mirroring `bin/fee-snapshot.sh`; `.env`-driven SSH invocation, `--output` / `--site` flags, schema sanity check (`option_count == 48`, `.options` and `.rest_response` both objects).
- `.planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json` — golden-state reference captured from production before any extraction work, proven byte-for-byte stable (triple-clean diff).

## FIN-01 evidence

**Harness exists and is runnable:**
```
$ bin/finance-settings-snapshot.sh --help
Rondo Club Finance Settings Snapshot Tool

Runs bin/finance-settings-snapshot.php on the target WordPress install via
SSH + wp eval-file and saves the resulting JSON locally. Read-only: no
writes, no emails. Used as a regression harness for the v34.0 Finance
Service Decomposition milestone.

Options:
  --output PATH   Write snapshot to PATH (default: finance-settings-snapshot.json)
  --site NAME     Target site: prod (default) or demo
  --help, -h      Show this help
```

**48-key count assertion:**
```
$ jq -r '.option_count' .planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json
48
```

**Determinism proof (run-twice-diff-empty):**
```
$ bin/finance-settings-snapshot.sh --output /tmp/run1.json
Running finance settings snapshot against prod (u27-qkfuzqfj63zn@c1130624.sgvps.net:18765)...
Snapshot saved: /tmp/run1.json
  Site URL:     https://rondo.svawc.nl
  Option count: 48 / 48 expected
  Target:       prod

$ bin/finance-settings-snapshot.sh --output /tmp/run2.json
Running finance settings snapshot against prod ...
Snapshot saved: /tmp/run2.json

$ diff <(jq -S 'del(.generated_at)' /tmp/run1.json) <(jq -S 'del(.generated_at)' /tmp/run2.json)
(zero output — diff empty)
$ echo $?
0
```

**Baseline matches re-capture (triple-clean proof):**
```
$ bin/finance-settings-snapshot.sh --output /tmp/verify.json
$ diff \
    <(jq -S 'del(.generated_at)' .planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json) \
    <(jq -S 'del(.generated_at)' /tmp/verify.json)
(zero output)
```

## Standing requirements activated

From Phase 220 onward:

- **FIN-11:** `/rondo/v1/finance-settings` REST response shape preserved — verified by comparing `.rest_response` field of each phase's post-diff against the baseline.
- **FIN-12:** Every phase SUMMARY.md MUST record a clean `bin/finance-settings-snapshot.sh` diff against `v34.0-baseline.json`. The exact command template is:
  ```bash
  bin/finance-settings-snapshot.sh --output .planning/phases/{NNN-name}/post-phase-{NNN}.json
  diff \
    <(jq -S 'del(.generated_at)' .planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json) \
    <(jq -S 'del(.generated_at)' .planning/phases/{NNN-name}/post-phase-{NNN}.json)
  ```
  Must return zero output. If not: extraction has a bug — fix before merging.

## Baseline pointer

All subsequent v34.0 phases diff against:
`.planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json`

Captured: 2026-04-09 against production (https://rondo.svawc.nl).
Option count: 48 (40 `rondo_finance_*` + 8 `rondo_membership_pass_*`).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed wrong class namespace in PHP payload**
- **Found during:** Task 2 (first production run returned "not loaded" error)
- **Issue:** Plan specified `Rondo\Finance\FinanceConfig` but the actual class namespace is `Rondo\Config\FinanceConfig` (verified with `wp eval 'class_exists()'` on production)
- **Fix:** Updated all three occurrences in `bin/finance-settings-snapshot.php` (class_exists check, instantiation, docblock/package tag)
- **Files modified:** `bin/finance-settings-snapshot.php`
- **Commit:** 91ee7419 (included in same commit)

## Tech debt / notes

None.

## Self-Check: PASSED

- `bin/finance-settings-snapshot.php` exists — FOUND
- `bin/finance-settings-snapshot.sh` exists and is executable — FOUND
- `.planning/phases/219-finance-settings-snapshot-harness/v34.0-baseline.json` exists — FOUND
- Commit 91ee7419 — FOUND
