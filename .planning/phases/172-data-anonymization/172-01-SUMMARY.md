---
phase: 172-data-anonymization
plan: 01
subsystem: demo-export
tags: [demo-data, anonymization, fixtures, data-generation]
dependency_graph:
  requires:
    - None (first plan in phase)
  provides:
    - DemoAnonymizer class for generating Dutch fake data
  affects:
    - Will be consumed by DemoExport in plan 172-02
tech_stack:
  added:
    - Pure PHP data generator (no WordPress dependencies)
  patterns:
    - Seeded randomization for reproducibility
    - Per-reference identity caching
    - Weighted random selection for infixes
key_files:
  created:
    - includes/class-demo-anonymizer.php
  modified:
    - functions.php
decisions:
  - choice: Use seeded mt_rand() for reproducibility
    rationale: Same seed always produces same fake data, critical for consistent demo fixtures
  - choice: Cache identities per ref string
    rationale: Same person ref must always get same fake identity within export run
  - choice: Weighted infix distribution (40% have infix, with nested weights)
    rationale: Mirrors realistic Dutch name patterns
  - choice: Pure PHP class with no WordPress dependencies
    rationale: Enables easier testing and reuse, maintains clean separation of concerns
metrics:
  duration_seconds: 132
  completed_date: 2026-02-11
  tasks_completed: 2
  files_created: 1
  files_modified: 1
  commits: 2
---

# Phase 172 Plan 01: Dutch Fake Data Generator Summary

**One-liner:** Pure PHP class generating consistent, realistic Dutch fake identities using seeded randomization and per-ref caching.

## What Was Built

Created the `DemoAnonymizer` class in the `Rondo\Demo` namespace, a standalone Dutch fake data generator that produces realistic anonymized PII for demo fixture exports. The class generates consistent fake identities using seeded randomization and maintains a per-reference cache to ensure the same person ref always receives the same fake identity within an export run.

### Core Capabilities

**Data Generation Methods:**
- `generate_identity()` - Complete fake identity with caching
- `generate_first_name()` - Gender-aware Dutch first names
- `generate_infix()` - Weighted Dutch tussenvoegsel (van, de, van der, etc.)
- `generate_last_name()` - Common Dutch surnames
- `generate_phone()` - Valid Dutch phone numbers (70% mobile, 30% landline)
- `generate_email()` - Fake emails derived from fake names
- `generate_address()` - Complete Dutch addresses with valid postal codes
- `generate_relatiecode()` - 7-digit fake Sportlink member numbers

**Data Sets:**
- 80+ Dutch male first names
- 80+ Dutch female first names
- 100+ Dutch last names
- 50+ Dutch street names
- 60+ Dutch cities
- Weighted infix distribution (40% have infix, realistic distribution among options)

**Technical Features:**
- Seeded randomization using `mt_srand()` and `mt_rand()` for reproducibility
- Identity cache keyed by person ref for consistency
- Pure PHP with zero WordPress dependencies
- Email normalization with diacritics removal
- Valid Dutch postal code format (4 digits + 2 letters)
- Realistic mobile/landline phone distribution

## Tasks Completed

### Task 1: Create DemoAnonymizer class
**Files:** `includes/class-demo-anonymizer.php`
**Commit:** 30d252e1

Created the complete `Rondo\Demo\DemoAnonymizer` class with:
- Constructor accepting seed (default: 42)
- All 8 required generation methods
- Hardcoded Dutch data arrays (names, streets, cities, infixes)
- Weighted random selection for infixes
- Per-ref identity caching mechanism
- Email normalization helper method

**Technical Implementation:**
- Uses `mt_srand($seed)` in constructor for reproducibility
- Cache check at start of `generate_identity()` before generation
- Weighted infix selection using cumulative weight algorithm
- Phone number format: mobile `06-XXXXXXXX` or landline `0XX-XXXXXXX`
- Postal code format: `[1000-9999] [AA-ZZ]`
- Email formats: multiple patterns (first.last, f.last, firstlast, flast) with fake domains

### Task 2: Register class alias in functions.php
**Files:** `functions.php`
**Commit:** b8b527ad

Registered the DemoAnonymizer class following the existing pattern:
- Added `use Rondo\Demo\DemoAnonymizer;` import after DemoExport
- Added `class_alias(DemoAnonymizer::class, 'RONDO_Demo_Anonymizer')` for backward compatibility
- Matches the pattern used for other demo classes

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All verification checks passed:

1. ✅ `php -l includes/class-demo-anonymizer.php` - No syntax errors
2. ✅ `grep "class DemoAnonymizer"` - Class declaration found
3. ✅ `grep "DemoAnonymizer" functions.php` - Both use statement and class_alias present
4. ✅ All 8 required methods exist: generate_identity, generate_first_name, generate_infix, generate_last_name, generate_phone, generate_email, generate_address, generate_relatiecode
5. ✅ Hardcoded Dutch data arrays present for first names (male/female), last names, streets, cities

## Success Criteria

✅ **Met:** A standalone Dutch fake data generator class exists that can produce realistic Dutch names, addresses, phone numbers, and email addresses with seeded reproducibility and per-ref caching. The class is registered in functions.php following existing patterns.

## Integration Points

**Consumed By (Plan 172-02):**
The `DemoExport` class will instantiate `DemoAnonymizer` with a seed and call `generate_identity()` for each person ref to obtain fake PII before export. The per-ref caching ensures consistency if the same person is referenced multiple times.

**Example Usage:**
```php
$anonymizer = new DemoAnonymizer(42); // Reproducible seed
$identity = $anonymizer->generate_identity('person:1', 'male');
// Returns: ['first_name' => 'Jan', 'infix' => 'van', 'last_name' => 'Berg', ...]
```

## Files Changed

**Created:**
- `includes/class-demo-anonymizer.php` (340 lines)

**Modified:**
- `functions.php` (added 2 lines: import + class alias)

## Self-Check: PASSED

**Created files verification:**
```bash
[ -f "/Users/joostdevalk/Code/rondo/rondo-club/includes/class-demo-anonymizer.php" ] && echo "FOUND"
```
✅ FOUND: includes/class-demo-anonymizer.php

**Commits verification:**
```bash
git log --oneline --all | grep -E "(30d252e1|b8b527ad)"
```
✅ FOUND: 30d252e1 feat(172-01): create DemoAnonymizer class with Dutch fake data generators
✅ FOUND: b8b527ad feat(172-01): register DemoAnonymizer class alias in functions.php

**Method verification:**
✅ All 8 required public methods present in class
✅ PHP syntax valid (no parse errors)
✅ Class alias registered in functions.php
✅ Use statement added to functions.php

All claims verified. Plan execution successful.
