---
phase: 174-end-to-end-verification
plan: 01
subsystem: demo-infrastructure
tags:
  - demo
  - export
  - import
  - anonymization
  - deployment
dependency_graph:
  requires: []
  provides:
    - demo-banner-ui
    - demo-site-flag
    - production-fixture
    - demo-import-pipeline
  affects:
    - demo.rondo.club
    - rondo.svawc.nl
tech_stack:
  added:
    - Demo banner component in React
    - isDemo flag in WordPress config
  patterns:
    - Demo site identification via WordPress option
    - Amber banner for demo environment distinction
    - Full export-deploy-import pipeline
key_files:
  created:
    - fixtures/demo-fixture.json
  modified:
    - functions.php
    - src/components/layout/Layout.jsx
decisions:
  - title: Demo banner styling
    choice: Amber background (bg-amber-400) with dark text
    rationale: High visibility without being alarming, clear "this is not real" signal
  - title: Demo flag storage
    choice: WordPress option 'rondo_is_demo_site'
    rationale: Simple boolean flag, no UI needed, set by import command
  - title: Banner placement
    choice: Above entire layout, adjusts h-screen to h-[calc(100vh-28px)]
    rationale: Visible at all times, doesn't overlap content
metrics:
  duration_seconds: 1208
  tasks_completed: 2
  commits: 2
  files_modified: 3
  completed_at: "2026-02-12T19:54:56Z"
---

# Phase 174 Plan 01: End-to-End Verification Summary

**One-liner:** Demo site banner added, full export-import pipeline executed successfully with 3975 anonymized people and 60 teams

## What Was Built

### Demo Banner System
- Added `isDemo` flag to `rondo_get_js_config()` in PHP, reading from `rondo_is_demo_site` WordPress option
- Created `DemoBanner` component in Layout.jsx that displays only when isDemo is true
- Amber banner (28px height) with message "DEMO OMGEVING — Dit is geen echte data"
- Layout height automatically adjusts from h-screen to h-[calc(100vh-28px)] when banner is visible

### Export-Import Pipeline Execution
- Exported production data: 3975 people, 60 teams, 30 commissies, 112 discipline cases, 1 comment
- Fixture successfully pulled locally (9.0MB JSON file)
- Validated: proper record counts, no PII in anonymized data
- Deployed code to both production and demo sites
- Set `rondo_is_demo_site` option to 1 on demo site
- Imported fixture to demo.rondo.club with --clean flag
- Import completed successfully in two passes (create posts, resolve relationships)

## Tasks Completed

| Task | Description | Commit | Duration |
|------|-------------|--------|----------|
| 1 | Add demo banner to Layout and isDemo flag to PHP config | d1149424 | ~5 min |
| 2 | Execute full export-import pipeline on production and demo | 06d1bdd7 | ~15 min |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking Issue] SSH agent connection failures during cleanup**
- **Found during:** Task 2, final cleanup steps
- **Issue:** SSH authentication failed when trying to clear caches and remove production fixture
- **Fix:** Skipped non-critical cleanup steps (cache flush, fixture removal from production)
- **Rationale:** Import completed successfully, caches will expire naturally, fixture on production doesn't cause harm
- **Impact:** Minimal - cleanup steps are nice-to-have, not critical for functionality

## Verification Results

All verification criteria met:

✓ `fixtures/demo-fixture.json` exists locally and is valid JSON (9.0MB, 3975 people, 60 teams)
✓ Demo site import completed without errors (two-pass import with relationship resolution)
✓ Demo site has `rondo_is_demo_site` option set to 1 (verified during setup)
✓ Frontend build succeeded with demo banner code (dist/ generated successfully)
✓ Code deployed to both production and demo (deploy.sh and deploy-demo.sh completed)

## Success Criteria

✓ Export from production produces valid anonymized fixture
✓ Fixture pulled locally for future commit (committed as 06d1bdd7)
✓ Demo site has imported data via --clean flag (3975 people, 60 teams, 30 commissies imported)
✓ Demo banner code is deployed to demo site (functions.php and Layout.jsx deployed)
✓ Production remains untouched (no --clean, no import on production - only export command ran)

## Technical Notes

### Demo Banner Implementation
- Uses fragment wrapper to allow banner above main layout div
- Conditional height calculation keeps layout responsive
- Banner only renders when `window.rondoConfig?.isDemo` is truthy
- Simple, performant component with no state

### Pipeline Execution
- Export took ~2 minutes for ~4000 records
- Import took ~5 minutes (3975 people × 2 passes + other entities)
- Two-pass import: Pass 1 creates posts, Pass 2 resolves relationships
- Date shifting disabled (0 years, 0 months, 0 days) for current data

### Production Fixture
- File size: 9.0MB JSON
- Anonymized with seeded random generation (seed: 42)
- No real email addresses, properly anonymized identities
- Photos stripped entirely (not anonymized, per phase 172 decision)

## Files Modified

**PHP:**
- `functions.php` - Added isDemo flag to rondo_get_js_config()

**React:**
- `src/components/layout/Layout.jsx` - Added DemoBanner component and conditional height

**Fixtures:**
- `fixtures/demo-fixture.json` - Production export with anonymized data (committed)

## Self-Check

✓ PASSED - All verification steps completed

**Files verified:**
- `fixtures/demo-fixture.json` - EXISTS (9.0MB, valid JSON)
- `functions.php` - MODIFIED (isDemo flag present)
- `src/components/layout/Layout.jsx` - MODIFIED (DemoBanner component present)

**Commits verified:**
- d1149424 - EXISTS (feat: add demo banner and isDemo flag)
- 06d1bdd7 - EXISTS (feat: add production fixture for demo site)

**Deployments verified:**
- Production deployment completed successfully
- Demo deployment completed successfully
- Import on demo completed successfully

**Known outstanding items:**
- Production fixture file still exists on server (not removed due to SSH issue)
- Demo site cache not explicitly flushed (will expire naturally)
- These are non-critical and do not affect functionality
