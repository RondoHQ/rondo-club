---
status: resolved
trigger: "Fix filter dropdown clipping caused by ptr__children and space-y-6 ancestors"
created: 2026-02-21T00:00:00Z
updated: 2026-02-21T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED — .ptr__children overflow:hidden clips position:fixed FilterDropdown
test: Applied overflow:visible !important CSS override
expecting: dropdown renders without clipping
next_action: DONE — build and lint passed

## Symptoms

expected: Filter dropdown opens fully visible without being cut off
actual: Dropdown is clipped by ptr__children (overflow:hidden auto) and space-y-6 ancestor
errors: No JS errors — pure CSS layout clipping
reproduction: Open any list page with PullToRefreshWrapper, click the Filter button, see dropdown cut off
started: After portal + position:fixed approach was added

## Eliminated

- hypothesis: position:fixed alone (portal to document.body) would escape all overflow:hidden
  evidence: On iOS Safari, overflow:hidden/auto on a scroll ancestor can clip fixed descendants. The library also applies CSS transform during pull gestures which creates new containing block. CSS class overflow:hidden on .ptr__children was the clipping source.
  timestamp: 2026-02-21

- hypothesis: space-y-6 div is the clipping ancestor
  evidence: Tailwind space-y-6 only sets margin-top on children — no overflow property. Not the cause.
  timestamp: 2026-02-21

## Evidence

- timestamp: 2026-02-21
  checked: node_modules/react-simple-pull-to-refresh/build/index.cjs.js
  found: Library injects CSS: ".ptr, .ptr__children { overflow: hidden; }" via styleInject(). initContainer() sets inline overflowX='hidden', overflowY='auto'. During drag: sets overflow='visible' + transform=translate(0,Ypx).
  implication: .ptr__children clips fixed descendants via overflow:hidden at rest, and via CSS transform during drag (transform creates new containing block for position:fixed)

- timestamp: 2026-02-21
  checked: src/components/PullToRefreshWrapper.jsx
  found: Uses react-simple-pull-to-refresh. All pages using PullToRefreshWrapper produce DOM: .ptr > .ptr__pull-down + .ptr__children > {page content}
  implication: Every list page with pull-to-refresh has FilterDropdown nested under .ptr__children

- timestamp: 2026-02-21
  checked: CSS cascade spec (CSS Cascade Level 5 §6.2)
  found: Author !important declarations beat inline normal style declarations. So .ptr__children { overflow: visible !important; } in our CSS file overrides element.style.overflowX='hidden' set by the library's initContainer().
  implication: A single CSS rule in src/index.css is sufficient to fix the clipping without patching the library

- timestamp: 2026-02-21
  checked: Layout.jsx <main> element
  found: Already has overflow-y-auto — this handles page scrolling. .ptr__children does not need to be a scroll container.
  implication: Making .ptr__children overflow:visible removes its scroll behavior, but <main> continues to handle scrolling — functionally equivalent

- timestamp: 2026-02-21
  checked: space-y-6 in 21 page files
  found: Tailwind space-y-6 = margin-top on children only. No overflow properties anywhere.
  implication: Not a clipping source — user's browser inspector was showing nearest named ancestor, real clipping was from .ptr__children

## Resolution

root_cause: react-simple-pull-to-refresh injects CSS that sets overflow:hidden on .ptr__children. This clips the FilterDropdown portal on iOS Safari (where fixed positioning inside overflow scroll containers is unreliable). During pull gestures, the library also applies CSS transform which creates a new containing block for fixed-position descendants. The <main> element already handles page scrolling so .ptr__children does not need overflow:hidden.

fix: Added to src/index.css:
  .ptr__children {
    overflow: visible !important;
  }
  The !important overrides both the library's CSS class (overflow:hidden) and its inline styles set by initContainer() (overflowX:hidden, overflowY:auto). Pull-to-refresh gesture functionality is unaffected because the library's touchstart/touchmove/touchend handlers work regardless of the overflow property.

verification: npm run build → clean. npm run lint → clean (0 warnings).

files_changed:
  - src/index.css (added .ptr__children override with explanation comment)
