---
id: S01
parent: M001
milestone: M001
provides:
  - Four-tier button CSS system (btn-primary, btn-secondary, btn-tertiary, btn-danger)
  - DRY button base class with @apply btn extension pattern
  - Outlined btn-secondary with brand colors
  - Ghost btn-tertiary with no border and subtle hover
  - Corrected btn-danger dark mode
requires: []
affects: []
key_files: []
key_decisions:
  - "btn-secondary restyled to outlined (brand border + text, transparent bg) — signals lower prominence than primary"
  - "btn-tertiary created as ghost (no border, text-only, subtle hover bg) — lowest non-destructive tier"
  - "btn-danger-outline and btn-glass removed — confirmed unused across all JSX files"
  - "Destructive and ghost buttons do not lift on hover (hover:translate-y-0 override) — lift is reserved for primary/secondary"
patterns_established:
  - "Button hierarchy: primary (gradient fill) > secondary (outlined) > tertiary (ghost) > danger (red fill)"
  - "All button variants extend .btn base class via @apply btn — never duplicate base styles"
observability_surfaces: []
drill_down_paths: []
duration: ~15min
verification_result: passed
completed_at: 2026-03-11
blocker_discovered: false
---
# S01: Button Css System

**# Phase 212 Plan 01: Button CSS System Summary**

## What Happened

# Phase 212 Plan 01: Button CSS System Summary

**Four-tier CSS button hierarchy (primary/secondary/tertiary/danger) defined in src/index.css with DRY @apply extension pattern and production-verified visual correctness**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-11
- **Completed:** 2026-03-11
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 1

## Accomplishments
- Restyled btn-secondary from filled to outlined (brand border + text, no fill, subtle hover tint)
- Created new btn-tertiary ghost class (no border, text-only, gray hover bg)
- Corrected btn-danger dark mode (red-600/red-700 consistently, no red-500/red-400 inconsistency)
- Removed unused btn-danger-outline and btn-glass classes
- DRY refactor: all four variants extend shared .btn base via @apply instead of duplicating base styles
- Visual verification on production confirmed all tiers correct in light and dark mode

## Task Commits

Each task was committed atomically:

1. **Task 1: Restyle btn-secondary to outlined, create btn-tertiary ghost, clean up unused classes** - `2d830580` (feat)
2. **Task 2: Visual verification of button tiers on production** - checkpoint approved (no commit)

**Plan metadata:** (docs commit below)

## Files Created/Modified
- `src/index.css` - Four-tier button system: btn-primary (unchanged), btn-secondary (outlined), btn-tertiary (ghost), btn-danger (corrected dark mode); removed btn-danger-outline and btn-glass; DRY @apply base extension

## Decisions Made
- btn-secondary restyled to outlined — signals lower action prominence than primary gradient fill
- btn-tertiary ghost with no lift on hover — ghost/tertiary actions should not attract attention via animation
- btn-danger also suppresses hover lift — destructive actions should be deliberate, not inviting
- Removed btn-danger-outline and btn-glass after confirming zero JSX usages

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All four button CSS tiers are defined and production-verified
- Phase 213 (button rollout) can now replace ad-hoc inline styles and mixed button usages sitewide with the four canonical classes
- No blockers

---
*Phase: 212-button-css-system*
*Completed: 2026-03-11*
