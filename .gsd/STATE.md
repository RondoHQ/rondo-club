# GSD State

**Active Milestone:** M012 — PHP Code Quality Refactor
**Phase:** planning → S01
**Requirements Status:** 0 active · 15 validated · 0 deferred · 0 out of scope

## Milestone Registry
- ✅ **M001:** Button Tier System & Sitewide Rollout
- ✅ **M002:** Mollie Payment Details
- ✅ **M003:** Credit Invoice Improvements
- ✅ **M004:** Contributie Exclusion Improvements
- ✅ **M005:** Spelactiviteit Field
- ✅ **M006:** Markeer als betaald
- ✅ **M007:** Remove iCal Feed
- ✅ **M008:** Credit Invoice Type Badge
- ✅ **M009:** Person Detail Page Improvements
- ✅ **M010:** Role-Capability Matrix & Age-Group Access
- ✅ **M011:** Roles & Capability Expansion (v32.4.0)
- 🔵 **M012:** PHP Code Quality Refactor

## Recent Decisions
- Split class-rest-api.php (7,854 lines) into ~10 focused controllers
- Extract duplicated sharing/logo code to Base class
- Extract login customization from functions.php to a class
- Remove ~33 dead class aliases
- Full GSD milestone approach chosen over incremental quick-wins

## Blockers
- None

## Current Version
32.4.0 deployed to production
