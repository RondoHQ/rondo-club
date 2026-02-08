# Phase 156: Fee Category Backend Logic - Context

**Gathered:** 2026-02-08
**Status:** Ready for planning

<domain>
## Phase Boundary

Replace all hardcoded fee calculation logic with config-driven category lookups from the per-season category data model. This includes: rewriting `parse_age_group()` to match by Sportlink age class instead of hardcoded age ranges, deriving `VALID_TYPES` and `youth_categories` from config, unifying duplicated `category_order` arrays, and ensuring forecast mode works with per-season categories. Also update the Phase 155 data model to replace `age_min`/`age_max` with an `age_classes` array.

</domain>

<decisions>
## Implementation Decisions

### Data model update (from Phase 155)
- Replace `age_min` and `age_max` fields with an `age_classes` array per category
- Each category stores which Sportlink `AgeClassDescription` values it covers (e.g., `["Onder 9", "Onder 10", "Onder 11"]`)
- Fee assignment is by matching a member's Sportlink age class to the category that contains it — no age calculation needed
- A category with null/empty `age_classes` acts as a catch-all, matching any age class not covered by other categories

### Age class matching
- Matching is by exact string match of the member's Sportlink `AgeClassDescription` against category `age_classes` arrays
- Both-inclusive: if a class appears in multiple categories, the category with the lowest `sort_order` wins
- Members whose age class matches no category are flagged as "uncategorized" — no fee assigned
- The `is_youth` flag remains explicit on each category, not inferred

### Error handling
- If a season has no categories at all (empty config): all members get zero/no fee for that season (silent, no error)
- If any category has incomplete/invalid data (e.g., missing amount): fail the entire fee calculation for that season (loud failure)
- No validation on read — trust the stored data, but fail on calculation if data is unusable

### Fee result shape
- Fee calculation returns both the category slug AND the amount (not just the amount)
- This lets downstream consumers (REST API, Google Sheets) know which category was matched

### Hardcoded removal
- `parse_age_group()` is replaced entirely with a new function that looks up category by Sportlink age class
- `VALID_TYPES` constant is removed completely — derived from config category slugs
- `youth_categories` is derived from config by filtering categories with `is_youth: true` (centralized helper function)
- Duplicated `category_order` arrays removed from PHP files; whether to also clean ContributieList.jsx is Claude's discretion based on dependency risk

### Forecast mode
- Forecast uses next-season category config (not current season)
- If next-season categories don't exist yet, falls back to current-season categories
- Forecast uses the member's current Sportlink age class against the target season's categories (no age prediction)
- If a member's current age class doesn't exist in the forecast season's categories, they show as uncategorized/no fee

### Claude's Discretion
- Whether to clean up ContributieList.jsx `category_order` in this phase or defer to Phase 159
- New function names and signatures for the age-class lookup
- Internal organization of the config-reading helpers
- How to structure the data model migration (updating Phase 155's stored format)

</decisions>

<specifics>
## Specific Ideas

- Sportlink provides `AgeClassDescription` per member (values like "Onder 9", "Onder 10", "Senioren", etc.)
- Categories aggregate multiple Sportlink age classes — e.g., a "Pupil" fee category might cover "Onder 9", "Onder 10", "Onder 11"
- The age_min/age_max fields from Phase 155 are being replaced, not supplemented — clean break
- Age is never calculated from birthdate; Sportlink is the source of truth for age classification

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 156-fee-category-backend-logic*
*Context gathered: 2026-02-08*
