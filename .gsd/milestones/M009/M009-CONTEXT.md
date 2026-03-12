# M009: Person Detail Page Improvements — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Four targeted UI improvements to the person detail page in Rondo Club to reduce visual clutter and add informational counts to tabs.

## Why This Milestone

The person detail page shows cards and badges that aren't always relevant:
- The Relaties card displays an empty state when there are no relationships
- The Account card shows for all volunteers even when there's no account
- Tabs don't indicate how many items they contain
- The VOG status pill in the header adds clutter

## User-Visible Outcome

### When this milestone is complete, the user can:

- See a cleaner person detail page without empty Relaties cards or unnecessary Account cards
- See item counts on tabs (Tijdlijn, Tuchtzaken, etc.) to quickly gauge content
- See a person header without the VOG status badge

### Entry point / environment

- Entry point: https://rondo.svawc.nl/people/{id}
- Environment: production WordPress
- Live dependencies involved: none

## Completion Class

- Contract complete means: build passes, changes verified visually on production
- Integration complete means: all four changes work together on the person detail page
- Operational complete means: deployed to production

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Person with no relationships: Relaties card is hidden
- Person with no account: Account card is hidden, person with account still shows it
- Tab counts visible on Tijdlijn and other tabs with items
- VOG status pill removed from person header

## Risks and Unknowns

- None — all changes are straightforward UI tweaks to a single component

## Existing Codebase / Prior Art

- `src/pages/People/PersonDetail.jsx` — main component with all four areas to change
- `src/components/TabButton.jsx` — tab button component that needs count support
- `src/components/AccountCard.jsx` — already has internal admin check, needs visibility condition change

## Scope

### In Scope

- Hide Relaties card when no relationships exist
- Show Account card only when a linked account exists (not just for all volunteers)
- Add item counts to tab labels (Tijdlijn, Tuchtzaken, Kleding, Rollen)
- Remove VOG status pill from person header

### Out of Scope / Non-Goals

- Redesigning the person detail page layout
- Changing the VOG card in the profile tab (stays as-is)
- Changing the Relaties or Account card internals

## Technical Constraints

- Must not break existing functionality for any person type
