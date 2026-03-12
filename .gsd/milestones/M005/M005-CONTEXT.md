# M005: Spelactiviteit Field — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Add the Sportlink `spelactiviteit` (KernelGameActivities) field to Rondo Club: store it, display it in the Sportlink card on person profiles, and provide a filter on `/people` for people who have a spelactiviteit value but are not currently in a team.

## Why This Milestone

Rondo Sync is being updated to import the `KernelGameActivities` field from Sportlink as `spelactiviteit`. Rondo Club needs to be ready to receive, display, and filter on this data.

The "has spelactiviteit but no team" filter enables the club to quickly identify members who should be playing but aren't assigned to any team — a common administrative need.

## User-Visible Outcome

### When this milestone is complete, the user can:

- See the `Spelactiviteit` field in the Sportlink card on a person's profile page
- Filter the people list to show people who have a spelactiviteit value but are not in a team

### Entry point / environment

- Entry point: https://rondo.svawc.nl/people and person detail pages
- Environment: production WordPress site
- Live dependencies involved: none (Rondo Sync will write the data separately)

## Completion Class

- Contract complete means: field exists in ACF, REST API returns it, frontend renders it, filter works
- Integration complete means: deployed to production, field displays correctly (even if empty until Sync runs)
- Operational complete means: none

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Spelactiviteit field appears in the Sportlink card when populated (can test with manual WP-CLI meta set)
- Spelactiviteit field is hidden when empty (consistent with other Sportlink fields)
- The "Spelactiviteit zonder team" filter on `/people` returns the correct set of people
- Deployed to production and visually verified

## Risks and Unknowns

- None significant — this follows established patterns for all other Sportlink fields

## Existing Codebase / Prior Art

- `acf-json/group_person_fields.json` — Sportlink tab already has fields like `leeftijdsgroep`, `type-lid`; new field goes here
- `src/components/SportlinkCard.jsx` — renders Sportlink fields on person detail; add `spelactiviteit` to the fields array
- `includes/class-rest-people.php` — ACF fields already sent wholesale via `get_fields()`; filter logic for `leeftijdsgroep`/`type-lid` is the pattern to follow
- `src/pages/People/PeopleList.jsx` — filter columns array and filter state management; existing `leeftijdsgroep` filter is the pattern

## Scope

### In Scope

- ACF field `spelactiviteit` (text) on person post type, in the Sportlink tab
- Display in SportlinkCard component
- REST API filter parameter for "has spelactiviteit but no team"
- Filter option in PeopleList UI
- Deploy to production

### Out of Scope / Non-Goals

- Rondo Sync changes to import the field (separate repo/project)
- Sorting by spelactiviteit
- Spelactiviteit as a column in the people list table
- Filter options endpoint returning distinct spelactiviteit values (not needed for the boolean-style filter)

## Technical Constraints

- Field name must be `spelactiviteit` (Rondo Sync will write to this key)
- Field must be an ACF text field in the Sportlink tab of group_person_fields.json
- ACF fields are already returned in bulk by `get_fields()` in the REST API — no REST changes needed for display
- The filter is a compound condition (has meta value AND no team) requiring a custom SQL clause

## Integration Points

- **Rondo Sync** — will write `spelactiviteit` meta values to person posts via the REST API
- **ACF Pro** — field definition stored in `acf-json/`
