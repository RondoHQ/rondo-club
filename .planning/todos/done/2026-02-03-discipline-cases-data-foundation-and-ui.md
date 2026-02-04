---
created: 2026-02-03T12:42
title: Discipline Cases Data Foundation and UI
area: api
files:
  - includes/class-post-types.php
  - includes/class-taxonomies.php
  - acf-json/
  - src/pages/People/PersonDetail.jsx
---

## Problem

Need to add support for Discipline Cases (Tuchtzaken) in Stadion to enable sync from Sportlink. This requires:

1. **Data foundation** - Custom post type, ACF fields, and taxonomy
2. **List interface** - Discipline cases list page for administrators
3. **Person detail integration** - Card showing discipline cases connected to a person

This is a dependency for the Sportlink sync Phase 31 which assumes these structures exist.

## Solution

### Phase 1: Data Foundation

**Custom Post Type:** `discipline-cases`
- Labels: Tuchtzaak (singular), Tuchtzaken (plural)
- Supports: title, editor, custom-fields
- REST API enabled

**ACF Fields:**
| Field Name | Type | Description |
|------------|------|-------------|
| `dossier-id` | Text | Unique case ID (T-12345) |
| `person` | Relationship | Links to person |
| `match-date` | Date | Match date |
| `match-description` | Text | Match details |
| `team-name` | Text | Team name |
| `charge-codes` | Text | KNVB charge codes |
| `charge-description` | Textarea | Full charge description |
| `sanction-description` | Textarea | Penalty description |
| `processing-date` | Date | Processing date |
| `administrative-fee` | Number | Fee in euros |
| `is-charged` | True/False | Fee charged flag |

**Taxonomy:** `seizoen`
- Non-hierarchical (tags-style)
- REST API enabled
- Terms: "2024-2025", "2025-2026", etc.

### Phase 2: UI Components

**List Page:** `/discipline-cases`
- Table with columns: Person, Match, Date, Sanction, Season
- Filters: Season, Team
- Sortable by date

**PersonDetail Card:** DisciplineCasesCard component
- Shows cases linked to person
- Displayed in sidebar or profile tab
- Summary with expandable details

### REST API

Standard WordPress REST endpoints with ACF integration:
- `POST/PUT /wp-json/wp/v2/discipline-cases`
- `GET /wp-json/wp/v2/seizoen`
- Query by dossier-id for sync lookup
