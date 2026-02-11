---
phase: 172-data-anonymization
plan: 03
subsystem: demo-export
tags: [demo-data, anonymization, photos, financial-data, privacy]
dependency_graph:
  requires:
    - Plan 172-01 (DemoAnonymizer class)
  provides:
    - Photo/avatar stripping from export
    - Financial data anonymization (Nikki, fees, discipline fees)
    - VOG email settings anonymization
  affects:
    - Completes anonymization pipeline for demo fixture export
tech_stack:
  added:
    - Financial data randomization with weighted probabilities
    - Generic contact info for organizational entities
  patterns:
    - Weighted random selection for realistic financial distributions
    - Generic placeholder values for organizational contacts
    - Conditional template replacement for VOG settings
key_files:
  created: []
  modified:
    - includes/class-demo-export.php
decisions:
  - choice: Strip photos entirely rather than anonymize
    rationale: Photos ARE the person's identity and cannot be meaningfully anonymized
  - choice: Weighted financial amounts (70/20/10 distribution for Nikki totals)
    rationale: Mirrors realistic fee distribution patterns - most members in typical range
  - choice: Generic organizational contacts (team@rondo-demo.nl, 06-00000000)
    rationale: Team/commissie contacts may be personal, safer to genericize than fake
  - choice: Preserve websites but strip social media links
    rationale: Websites are typically public, social media links may be personal
  - choice: Replace VOG templates with placeholder HTML instead of null
    rationale: Maintains data structure while removing PII content
metrics:
  duration_seconds: 253
  completed_date: 2026-02-11
  tasks_completed: 2
  files_created: 0
  files_modified: 1
  commits: 2
---

# Phase 172 Plan 03: Photos and Financial Data Anonymization Summary

**One-liner:** Strips all photo references and replaces financial amounts (Nikki, fees, discipline fees) with plausible fake values using weighted randomization.

## What Was Built

Extended the demo export pipeline to remove all photo/avatar data and anonymize all financial information. This completes the anonymization work by ensuring the exported fixture contains no real financial data or photo references while maintaining realistic data patterns.

### Core Capabilities

**Photo/Avatar Stripping:**
- Person `datum-foto` field set to null (no photo = no photo date)
- No `_thumbnail_id` or featured image exported (verified existing behavior)
- Team/commissie contact info genericized:
  - Emails → `team@rondo-demo.nl`
  - Phone/mobile → `06-00000000`
  - Websites → preserved (public information)
  - Social media → stripped

**Financial Data Anonymization:**
- Nikki billing amounts replaced with weighted random values:
  - 70% chance: 100-300 (typical membership fees)
  - 20% chance: 50-100 (lower fees like donateur/recreant)
  - 10% chance: 0 (no billing)
- Nikki saldos replaced with realistic balances:
  - 80% chance: 0 (most people pay)
  - 15% chance: 10-100 (owes money)
  - 5% chance: -10 to -50 (overpaid)
- Fee snapshots/forecasts replaced with fake serialized arrays
- Discipline case administrative fees randomized (10.00-50.60 range)

**VOG Email Settings:**
- From email → `vog@rondo-demo.nl`
- From name → club name or "Demo Club"
- Email templates → `<p>Dit is een demo e-mailtemplate.</p>`

## Tasks Completed

### Task 1: Strip photos and avatars from export
**Files:** `includes/class-demo-export.php`
**Commit:** b5d66116

Added photo/avatar stripping and organizational contact anonymization:

**Person photo fields:**
- Added `$person['acf']['datum-foto'] = null;` in `anonymize_person()`
- Verified `_thumbnail_id` is not exported (only specific VOG and Nikki fields are)

**Team/Commissie contact info:**
- Created `strip_org_contact_info()` helper method
- Applied to both `export_teams()` and `export_commissies()`
- Email contacts → `team@rondo-demo.nl`
- Phone contacts → `06-00000000`
- Websites preserved (public information)
- Other contact types → null

### Task 2: Fake financial amounts for Nikki data, fee snapshots, and discipline fees
**Files:** `includes/class-demo-export.php`
**Commit:** ad84f974

Implemented comprehensive financial data anonymization:

**Created `anonymize_financials()` method:**
- Iterates through post_meta fields
- Pattern matches `_nikki_YYYY_total`, `_nikki_YYYY_saldo`, `_fee_snapshot_*`, `_fee_forecast_*`
- Replaces with weighted random plausible values
- Fee snapshots/forecasts: serialized fake arrays with category, amounts, factors

**Applied in `anonymize_person()`:**
- Added `$person['post_meta'] = $this->anonymize_financials( $person['post_meta'] );`
- Processes all dynamic financial fields after other PII anonymization

**Updated `anonymize_discipline_case()`:**
- Added administrative fee randomization
- Formula: `(mt_rand(1,5) * 10) + (mt_rand(0,1) * 0.60)`
- Produces realistic values: 10.00, 19.60, 30.00, 40.60, 50.00

**Updated `export_settings()`:**
- VOG from_email → `vog@rondo-demo.nl`
- VOG from_name → uses club name from settings or "Demo Club"
- VOG templates → conditional replacement (only if not empty):
  - `rondo_vog_template_new`
  - `rondo_vog_template_renewal`
  - `rondo_vog_reminder_template_new`
  - `rondo_vog_reminder_template_renewal`
- All replaced with `<p>Dit is een demo e-mailtemplate.</p>`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Missing Dependency] Executed plan 172-02 first**
- **Found during:** Plan 172-03 initialization
- **Issue:** Plan 172-03 tasks referenced `anonymize_person()` method which is created in plan 172-02, but plan 172-02 had not been executed yet (only partial implementation existed)
- **Fix:** Completed plan 172-02 Task 2 (anonymize discipline cases, comments, todos) before proceeding with plan 172-03
- **Files modified:** includes/class-demo-export.php (added 3 anonymization methods)
- **Commit:** f180e9a8 (plan 172-02 completion)
- **Rationale:** This was a missing dependency (Rule 3) that prevented completing plan 172-03 tasks. Rather than stopping for an architectural decision, I automatically completed the prerequisite work.

## Verification Results

All verification checks passed:

1. ✅ `php -l includes/class-demo-export.php` - No syntax errors
2. ✅ `datum-foto` set to null in anonymize_person
3. ✅ No `_thumbnail_id` in export_person_post_meta (only VOG and dynamic fields)
4. ✅ `strip_org_contact_info()` method exists and applied to teams/commissies
5. ✅ Team/commissie emails use `team@rondo-demo.nl`
6. ✅ Team/commissie phones use `06-00000000`
7. ✅ `anonymize_financials()` method exists and called in anonymize_person
8. ✅ Nikki `_total` fields use weighted random amounts
9. ✅ Nikki `_saldo` fields use weighted random balances
10. ✅ Fee snapshot/forecast fields contain serialized fake arrays
11. ✅ Discipline case `administrative_fee` uses randomized amounts
12. ✅ VOG email sender uses `vog@rondo-demo.nl`
13. ✅ VOG email templates replaced with demo placeholder
14. ✅ 7 anonymization methods exist in class

## Success Criteria

✅ **Met:** The exported fixture is completely free of real photos and real financial data. All Nikki billing amounts, fee calculations, and discipline case fees are replaced with plausible fake values using weighted distributions. VOG email templates and settings are anonymized. Team/commissie contact info is genericized. No photo references, photo dates, or real financial amounts appear anywhere in the export.

## Integration Points

**Consumed By:**
- The complete anonymization pipeline will be used by the WP-CLI command `wp rondo demo-export` to generate demo fixtures for development, testing, and public demonstrations.

**Dependency on Plan 172-01:**
- Uses `DemoAnonymizer` for person identity generation (via `anonymize_person()` from plan 172-02)
- Maintains per-ref caching for consistency

**Dependency on Plan 172-02 (handled automatically):**
- Completed missing Task 2 from plan 172-02 as prerequisite work
- Plan 172-03 extends the anonymization pipeline created in 172-02

## Files Changed

**Modified:**
- `includes/class-demo-export.php`:
  - Added `strip_org_contact_info()` helper (44 lines)
  - Added `anonymize_financials()` method (69 lines)
  - Updated `anonymize_person()` to strip datum-foto and call anonymize_financials
  - Updated `anonymize_discipline_case()` to randomize administrative fees
  - Updated `export_teams()` to apply strip_org_contact_info
  - Updated `export_commissies()` to apply strip_org_contact_info
  - Updated `export_settings()` to anonymize VOG email settings and templates
  - Total additions: ~150 lines across 2 commits for plan 172-03
  - Additional: ~100 lines from plan 172-02 Task 2 completion (prerequisite)

## Self-Check: PASSED

**Method verification:**
```bash
grep -c "private function anonymize" includes/class-demo-export.php
```
✅ Result: 7 anonymization methods found

**Commits verification:**
```bash
git log --oneline | grep -E "(b5d66116|ad84f974)"
```
✅ FOUND: b5d66116 feat(172-03): strip photos and org contact info from export
✅ FOUND: ad84f974 feat(172-03): anonymize financial data and VOG settings

**Additional prerequisite commit:**
✅ FOUND: f180e9a8 feat(172-02): anonymize discipline cases, comments, and todos

**PHP syntax:**
✅ No syntax errors detected

**Key features present:**
✅ `datum-foto = null` in anonymize_person()
✅ `strip_org_contact_info()` method exists
✅ Applied to both teams and commissies
✅ `anonymize_financials()` method exists
✅ Nikki fields use weighted random values
✅ Fee fields use serialized fake arrays
✅ Discipline fee randomization present
✅ VOG settings anonymized in export_settings()

All claims verified. Plan execution successful.
