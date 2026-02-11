---
phase: 171-export-command-foundation
plan: 02
subsystem: demo-data-export
tags: [export, people, teams, commissies, taxonomies, acf-fields]
requires: [171-01]
provides:
  - people-export
  - teams-export
  - commissies-export
  - taxonomies-export
affects: [demo-data-export]
tech_stack:
  added: []
  patterns: [acf-repeater-export, relationship-ref-mapping, dynamic-meta-scanning]
key_files:
  created: []
  modified:
    - includes/class-demo-export.php
decisions:
  - Dynamic post_meta scanning for nikki and fee fields using regex pattern matching
  - Skip relationships where related_person not in ref map to handle deleted persons
  - Progress logging every 100 people for user feedback on large exports
metrics:
  duration_minutes: 4
  tasks_completed: 2
  files_modified: 1
  completed_at: "2026-02-11T10:25:23Z"
---

# Phase 171 Plan 02: People, Teams, Commissies, and Taxonomies Export Summary

**One-liner:** Complete entity export for people (with all ACF fields, relationships, work history), teams, commissies, and taxonomies with full reference mapping

## What Was Built

Implemented the core export methods for the largest and most complex entity types in the fixture:

### 1. People Export (`export_people()`)

The most comprehensive export method with 3948 records containing:

**Basic Information Fields:**
- first_name, infix, last_name, nickname (all with null normalization)
- gender, pronouns, birthdate
- former_member (boolean), lid-tot, datum-overlijden

**Contact & Address Data:**
- `contact_info` repeater: contact_type, label, value
- `addresses` repeater: label, street, postal_code, city, state, country

**Work History & Team Affiliations:**
- `work_history` repeater: team refs, entity_type, job_title, description, dates, is_current
- `werkfuncties` repeater (Sportlink): same structure as work_history
- All team/commissie references converted to fixture refs (e.g., "team:27", "commissie:28")

**Relationships:**
- `relationships` repeater: related_person refs, relationship_type refs, custom labels
- Skips relationships where related person not in ref map (handles deleted persons gracefully)
- Uses slug-based taxonomy refs (e.g., "relationship_type:parent")

**Sportlink-Synced Fields:**
- lid-sinds, leeftijdsgroep, datum-vog, datum-foto
- type-lid, huidig-vrijwilliger, financiele-blokkade
- relatiecode, freescout-id
- factuur-adres, factuur-email, factuur-referentie

**Post Meta (non-ACF):**
- VOG tracking dates: vog_email_sent_date, vog_justis_submitted_date, vog_reminder_sent_date
- Dynamic meta scanning for patterns:
  - `_nikki_{year}_total` and `_nikki_{year}_saldo`
  - `_fee_snapshot_{season}` and `_fee_forecast_{season}`

### 2. Teams Export (`export_teams()`)

Exported 61 team records with:
- Team names preserved unchanged per EXPORT-06
- Post content (nullable)
- Parent hierarchy using fixture refs
- ACF fields: website, contact_info repeater

### 3. Commissies Export (`export_commissies()`)

Exported 30 commissie records with:
- Commissie names preserved unchanged
- Same structure as teams
- Parent hierarchy using fixture refs
- ACF fields: website, contact_info repeater

### 4. Taxonomies Export (`export_taxonomies()`)

**Relationship Types (3 terms):**
- Parent/Child/Sibling relationships
- Inverse relationship mapping using slug-based refs
- Example: Parent's inverse → "relationship_type:child"

**Seizoenen (1 term):**
- Season 2025-2026 with is_current flag
- Based on term meta `is_current_season`

### 5. Helper Methods

**`normalize_value()`:**
- Converts empty strings, false, and empty arrays to null
- Ensures clean JSON output without empty string pollution

**`export_contact_info()`:**
- Shared helper for team/commissie/person contact_info repeaters
- Returns empty array if no data

**`export_addresses()`:**
- Person address repeater export
- Handles all 6 address fields

**`export_work_history()` & `export_werkfuncties()`:**
- Convert team/commissie post IDs to fixture refs
- Determine entity_type from post_type
- Handle nullable dates and descriptions

**`export_relationships()`:**
- Convert related_person IDs to fixture refs
- Convert relationship_type term IDs to slug-based refs
- Skip invalid relationships gracefully

**`export_person_post_meta()`:**
- Static meta fields (VOG tracking)
- Dynamic meta scanning with regex patterns
- Handles serialized PHP in fee snapshots/forecasts

## How It Works

### Export Flow for People

```
1. Query all person posts (any status, ordered by ID)
2. For each person (with progress logging every 100):
   a. Build top-level fields (_ref, title, status)
   b. Export all ACF fields (basic, contact, addresses, work, relationships, Sportlink)
   c. Export post_meta (static + dynamic scan)
   d. Normalize all nullable values
3. Log total exported count
```

### Reference Conversion Examples

**Work History:**
```php
WordPress ID 123 (team post) → "team:5" (fixture ref)
WordPress ID 456 (commissie post) → "commissie:12" (fixture ref)
```

**Relationships:**
```php
related_person: WordPress ID 789 → "person:42"
relationship_type: Term ID 5 → "relationship_type:parent" (slug-based)
```

### Dynamic Meta Scanning

```php
// Scans all post_meta for patterns:
preg_match('/^_nikki_(\d+)_(total|saldo)$/', $meta_key)
preg_match('/^_fee_(snapshot|forecast)_/', $meta_key)

// Captures:
_nikki_2025_total, _nikki_2025_saldo
_nikki_2024_total, _nikki_2024_saldo
_fee_snapshot_2025-2026, _fee_forecast_2025-2026
```

## Production Testing

Tested on production database with 3948 people, 61 teams, 30 commissies:

### Export Performance

```
Total time: ~1 minute for 3948 people
Progress logging every 100 people
Final counts match meta section exactly
```

### Verification Results

1. **All people exported:** 3948 records ✓
2. **Team names unchanged:** "AWC", "AWC JO13-1JM" preserved ✓
3. **Commissie names unchanged:** "Commissie Sportiviteit" preserved ✓
4. **Relationship refs:** Used "relationship_type:parent" format ✓
5. **Work history refs:** Used "team:27", "commissie:28" format ✓
6. **Nullable fields:** Converted to null, not empty strings ✓
7. **Record counts:** Meta section matches actual array lengths ✓
8. **Inverse relationship mapping:** Parent ↔ Child correctly mapped ✓
9. **Seizoen is_current flag:** Set correctly from term meta ✓

### Sample Data Validation

**Person with relationships:**
```json
{
  "_ref": "person:5",
  "title": "Gijsbert van Malenstein",
  "acf": {
    "first_name": "Gijsbert",
    "infix": "van",
    "relationships": [
      {
        "related_person": "person:512",
        "relationship_type": "relationship_type:child",
        "relationship_label": null
      }
    ],
    "work_history": [
      {
        "team": "commissie:28",
        "entity_type": "commissie",
        "job_title": "Jeugdbegeleid(st)er",
        "is_current": true
      },
      {
        "team": "team:27",
        "entity_type": "team",
        "job_title": "Staflid",
        "is_current": true
      }
    ]
  },
  "post_meta": {
    "_nikki_2025_saldo": "0",
    "_nikki_2025_total": "55"
  }
}
```

**Taxonomies structure:**
```json
{
  "relationship_types": [
    {
      "_ref": "relationship_type:parent",
      "name": "Parent",
      "slug": "parent",
      "acf": {
        "inverse_relationship_type": "relationship_type:child"
      }
    }
  ],
  "seizoenen": [
    {
      "name": "2025-2026",
      "slug": "2025-2026",
      "is_current": true
    }
  ]
}
```

## Deviations from Plan

None - plan executed exactly as written.

## Technical Decisions

### 1. Dynamic Meta Scanning Pattern

**Decision:** Use regex pattern matching to scan all post_meta for nikki and fee fields rather than hardcoding year/season values.

**Rationale:**
- Years and seasons change over time
- Hardcoding would miss future data
- Pattern matching is future-proof and handles any year/season
- Small performance cost (scan all meta) acceptable for export operation

**Implementation:**
```php
foreach ( $all_meta as $meta_key => $meta_values ) {
    if ( preg_match( '/^_nikki_(\d+)_(total|saldo)$/', $meta_key ) ||
         preg_match( '/^_fee_(snapshot|forecast)_/', $meta_key ) ) {
        $post_meta[ $meta_key ] = $this->normalize_value( $value );
    }
}
```

### 2. Skip Invalid Relationships

**Decision:** Skip relationship rows where related_person is not in the ref map, rather than failing or warning.

**Rationale:**
- Handles edge case of deleted persons gracefully
- Prevents broken refs in fixture
- Silent skip is acceptable for demo data export
- Production data may have orphaned references

### 3. Progress Logging Frequency

**Decision:** Log progress every 100 people during export.

**Rationale:**
- Provides user feedback for long-running export (3948 records)
- Every 100 is granular enough (39 total log lines)
- Not too verbose to spam the console
- Helps identify if export is hanging

### 4. Null Normalization Strategy

**Decision:** Create `normalize_value()` helper to convert empty values to null consistently.

**Rationale:**
- Clean JSON output without empty string pollution
- Matches schema requirement for nullable fields
- Centralized logic prevents inconsistencies
- Easier to read exported fixtures

## Commits

| Commit   | Message                                                                 | Files                         |
|----------|-------------------------------------------------------------------------|-------------------------------|
| 11646547 | feat(171-02): implement export_teams, export_commissies, and export_taxonomies | includes/class-demo-export.php |
| e7bb02b0 | feat(171-02): implement export_people with all ACF fields and relationships | includes/class-demo-export.php |

## Next Steps

Plan 03 will implement the remaining entity export methods:
- `export_discipline_cases()` - KNVB discipline records
- `export_todos()` - Task management records
- `export_comments()` - Notes, activities, email logs
- `export_settings()` - WordPress options (VOG config, fee categories, etc.)

These methods will complete the fixture export functionality, allowing full production data exports for demo/testing purposes.

## Self-Check: PASSED

**Files modified:**
- ✓ `includes/class-demo-export.php` has all export methods implemented
- ✓ Contains `export_people()` with all ACF fields
- ✓ Contains `export_teams()`, `export_commissies()`, `export_taxonomies()`
- ✓ Contains helper methods for repeaters and normalization

**Commits exist:**
- ✓ 11646547: Teams, commissies, taxonomies export
- ✓ e7bb02b0: People export with relationships

**Production validation:**
- ✓ Export runs without errors
- ✓ 3948 people exported
- ✓ 61 teams exported
- ✓ 30 commissies exported
- ✓ 3 relationship types, 1 seizoen exported
- ✓ Work history uses fixture refs (team:N, commissie:N)
- ✓ Relationships use fixture refs (person:N, relationship_type:slug)
- ✓ Nullable fields are null (not empty strings)
- ✓ Team/commissie names unchanged
- ✓ Record counts accurate in meta section
