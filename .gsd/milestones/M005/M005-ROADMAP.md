# M005: Spelactiviteit Field

**Vision:** Members with a spelactiviteit (game activity) from Sportlink are visible in person profiles and filterable in the people list — specifically those who should be playing but have no team assigned.

## Success Criteria

- Spelactiviteit value is displayed in the Sportlink card on person detail pages when populated
- Spelactiviteit field is hidden when empty (consistent with other Sportlink card fields)
- People list has a "Spelactiviteit zonder team" filter that shows people with a spelactiviteit value who are not in any team
- All changes deployed to production

## Key Risks / Unknowns

None — this follows the exact patterns of existing Sportlink fields (`leeftijdsgroep`, `type-lid`) for both display and filtering.

## Verification Classes

- Contract verification: manual meta set via WP-CLI, visual check on profile, filter returns correct results
- Integration verification: deployed to production
- Operational verification: none
- UAT / human verification: user verifies display and filter on production

## Milestone Definition of Done

This milestone is complete only when all are true:

- ACF field exists and is synced to production
- Sportlink card shows spelactiviteit when populated
- People list filter for "spelactiviteit without team" works correctly
- Deployed to production and verified

## Slices

- [x] **S01: Spelactiviteit field, display, and filter** `risk:low` `depends:[]`
  > After this: spelactiviteit shows in Sportlink card on person profiles, and "Spelactiviteit zonder team" filter works on /people — deployed to production

## Boundary Map

### S01

Produces:
- ACF text field `spelactiviteit` in the Sportlink tab of `group_person_fields.json`
- `spelactiviteit` row in `SportlinkCard.jsx` field list
- `spelactiviteit_no_team` filter parameter in `class-rest-people.php` (compound: has meta + no team relationship)
- Filter column in `PeopleList.jsx` for the new filter

Consumes:
- nothing (single slice)
