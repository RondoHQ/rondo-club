# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-08)

**Core value:** Club administrators can manage their members, teams, and important dates through a single integrated system
**Current focus:** v21.0 Per-Season Fee Categories (next milestone)

## Current Position

Phase: 160 of 161 (Configurable Family Discount) — fifth phase of v21.0
Plan: 02 of 02 complete
Status: Complete
Last activity: 2026-02-09 — Phase 160 complete (configurable family discount UI and backend)

Progress: [█████████░] 95% (10/10 v21.0 plans complete, 1 phase remaining: 161-configurable-matching-rules)

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

- Season key format YYYY-YYYY already established (v12.0)
- Per-season fee amounts already stored in WordPress options (`rondo_membership_fees_{season}`)
- Season detection (July 1 boundary) and next-season support already implemented
- Forecast mode already works for next season
- User chose: WordPress options storage, copy-previous for new seasons, fully configurable age ranges
- **Phase 155-01:** Category data structure is slug-keyed objects with label, amount, age ranges, youth flag, sort order
- **Phase 155-01:** Copy-forward clones entire category configuration from previous season
- **Phase 155-01:** No backward compatibility layer - clean break from flat amount format
- **Phase 156-01:** Age class matching uses exact string comparison, not regex/range calculation
- **Phase 156-01:** Automatic migration from age_min/age_max to age_classes (empty array as catch-all)
- **Phase 156-01:** Category with lowest sort_order wins when age class appears in multiple categories
- **Phase 156-01:** Season parameter flows through entire calculation chain for forecast mode
- **Phase 156-02:** Eliminated all hardcoded category_order arrays in favor of get_category_sort_order()
- **Phase 156-02:** REST API and Google Sheets export respect per-season category configuration
- **Phase 157-01:** GET /membership-fees/settings returns full category objects (not flat amounts)
- **Phase 157-01:** POST /membership-fees/settings uses full replacement pattern with structured validation
- **Phase 157-01:** Validation distinguishes errors (block save) from warnings (informational)
- **Phase 157-01:** Empty categories array is valid for reset functionality
- **Phase 157-02:** GET /fees includes categories metadata (label, sort_order, is_youth) for dynamic frontend rendering
- **Phase 157-02:** Category metadata in fee list returns only display-relevant fields, not full config
- **Phase 158-01:** FeeCategorySettings component is self-contained (fetches own data via TanStack Query)
- **Phase 158-01:** Validation display distinguishes blocking errors (red) from informational warnings (amber)
- **Phase 158-01:** Auto-slug generation from label for new categories reduces user error
- **Phase 158-01:** Age class coverage summary always visible (not just after save) for better UX
- **Phase 158-02:** Age classes fetched from database (filter-options endpoint), not free text
- **Phase 158-02:** Slug field removed from UI, auto-derived from label
- **Phase 158-02:** is_youth field relabeled as "Familiekorting mogelijk?" — reflects actual purpose
- **Phase 158-02:** Donateur is a werkfunctie, not a type-lid — Phase 161 should use werkfuncties for matching
- **Phase 160-01:** Family discount stored in separate option (rondo_family_discount_{season}) to avoid conflicts with category saves
- **Phase 160-01:** Copy-forward pattern: new seasons inherit previous season's discount config automatically
- **Phase 160-01:** Validation warns (not errors) if second child discount >= third child discount (allows flexibility)
- **Phase 160-02:** Separate discountMutation avoids sending categories when only discount changes
- **Phase 160-02:** FamilyDiscountSection placed prominently above category list for visibility

### Pending Todos

7 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **remove-contact-import-feature**: Remove contact import feature (area: ui)
- **remove-how-we-met-and-met-date-fields**: Remove how_we_met and met_date fields (area: data-model)
- **remove-user-approval-system**: Remove user approval system (area: auth)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)
- **switch-to-new-website-design-style**: Switch to new website design style (area: ui)

### Blockers/Concerns

- Phases 155-158 deployed together on 2026-02-09 — deployment blocker resolved.

## Session Continuity

Last session: 2026-02-09
Stopped at: Phase 160 complete. Next: Phase 161 (Configurable Matching Rules) - final v21.0 phase.
Resume file: None

---
*State updated: 2026-02-09*
