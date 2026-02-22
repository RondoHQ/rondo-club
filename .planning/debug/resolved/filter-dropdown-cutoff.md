---
status: resolved
trigger: "filter-dropdown-cutoff"
created: 2026-02-21T00:00:00Z
updated: 2026-02-21T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED — overflow-y:auto on <main> clips the absolutely-positioned dropdown
test: Read Layout.jsx to confirm overflow-y:auto on main; trace DOM ancestry
expecting: Fix verified by build + lint passing
next_action: DONE — archived

## Symptoms

expected: The filter dropdown panel should be fully visible and scrollable regardless of how many rows the table has.
actual: When the table has few rows, the dropdown gets clipped/cut off at the bottom — the bottom portion of the dropdown is hidden.
errors: No JS errors, purely visual/CSS issue.
reproduction: Open a page with the DataTable filter (e.g. Facturen, NogTeFactureren, or any page using DataTableToolbar). Click the filter button to open the dropdown. If the table only has a few rows, the dropdown gets cut off.
timeline: Present since the DataTable component was introduced (Wave 1, 2026-02-21).

## Eliminated

- hypothesis: The card div (overflow-hidden) clips the dropdown
  evidence: DataTableToolbar renders ABOVE the card div in the DOM tree in DataTable.jsx — dropdown is never a child of the card
  timestamp: 2026-02-21

## Evidence

- timestamp: 2026-02-21
  checked: src/index.css
  found: .card { @apply ... overflow-hidden ... } — card has overflow:hidden
  implication: Card clips its children but toolbar/dropdown is outside the card

- timestamp: 2026-02-21
  checked: src/components/layout/Layout.jsx line 631
  found: <main className="flex-1 overflow-y-auto p-4 lg:p-6 [overscroll-behavior-y:none]">
  implication: overflow-y:auto on main element creates a scroll container that clips absolutely-positioned descendants that overflow its bounds — when content is short, dropdown extends past the scroll container height

- timestamp: 2026-02-21
  checked: src/components/DataTable/FilterDropdown.jsx
  found: dropdown used position:absolute top-full — anchored inside the main scroll container
  implication: When main has little content (few table rows), the dropdown can overflow the bottom of main's visible/scroll area and be clipped

## Resolution

root_cause: The <main> element in Layout.jsx has overflow-y:auto, which creates a scroll container. An overflow-y:auto (or any non-visible overflow) element clips absolutely-positioned descendants that extend beyond its bounds. When the table has few rows, the filter dropdown extends below the bottom of the scrollable area and is clipped.

fix: Converted FilterDropdown to use React createPortal (renders into document.body) with position:fixed computed from the filter button's getBoundingClientRect(). This places the dropdown outside all overflow-constrained ancestors entirely. Updated DataTableToolbar to pass a buttonRef to FilterDropdown for position anchoring, and updated outside-click detection to handle the portal-rendered dropdown via a data-filter-dropdown attribute.

verification: npm run build — 0 errors. npm run lint — 0 warnings.

files_changed:
  - src/components/DataTable/FilterDropdown.jsx
  - src/components/DataTable/DataTableToolbar.jsx
