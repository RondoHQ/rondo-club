# Phase 147: Birthdate Field & Widget - Context

**Gathered:** 2026-02-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Add birthdate to person profiles (from Sportlink sync), display age and birthdate in person header, and update dashboard birthday widget to query person meta instead of Important Dates CPT. This phase adds the new field and display; removal of Important Dates happens in Phase 148.

</domain>

<decisions>
## Implementation Decisions

### Age display format
- Format: "43 jaar (16 feb 1982)" — includes full birth year
- No leading zeros on day numbers
- Expand the existing age display line in person header (don't create new location)
- Persons without birthdate: hide completely (no placeholder)
- Age calculated as of today (current age, not age they're turning)

### Date formatting
- Month abbreviations in Dutch: jan, feb, mrt, apr, mei, jun, jul, aug, sep, okt, nov, dec
- Use WordPress site timezone for birthday calculations
- Require complete dates only (ACF date picker enforces full date or empty)
- Widget "days until" shows "vandaag" for same-day birthdays (not "0 dagen")

### Documentation
- Update `docs/api-leden-crud.md` with birthdate field information
- Remove `docs/api-important-dates.md` in this phase (proactively, before infrastructure removal)

### Claude's Discretion
- Birthdate field placement within ACF field group
- Widget styling and layout details
- Number of days ahead to show in widget

</decisions>

<specifics>
## Specific Ideas

- Existing age display line should be expanded, not replaced with new UI element
- Dutch language conventions throughout (month names, "vandaag", "jaar")

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 147-birthdate-field-widget*
*Context gathered: 2026-02-06*
