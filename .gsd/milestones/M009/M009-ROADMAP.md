# M009: Person Detail Page Improvements

**Vision:** A cleaner, more informative person detail page that hides empty sections and shows item counts on tabs.

## Success Criteria

- Relaties card is hidden when a person has no relationships
- Account card is only shown when the person has a linked WordPress account
- Tab labels show item counts in parentheses when items exist
- VOG status pill is removed from the person header

## Key Risks / Unknowns

None — all changes are small, isolated UI tweaks to well-understood components.

## Verification Classes

- Contract verification: `npm run build` passes
- Integration verification: none
- Operational verification: deployed to production, visually verified
- UAT / human verification: user verifies on production

## Milestone Definition of Done

This milestone is complete only when all are true:

- All four UI changes are implemented and build passes
- Deployed to production
- Verified on a person with relationships (card shows) and without (card hidden)
- Verified Account card visibility logic
- Tab counts visible on tabs with items

## Slices

- [x] **S01: Person detail page cleanup** `risk:low` `depends:[]`
  > After this: person detail page hides empty cards, shows tab counts, and has no VOG header pill
