---
status: resolved
trigger: "tuchtzaken-no-scroll — User can't scroll down on the /tuchtzaken page. The page content is cut off and scrolling doesn't work."
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED — react-simple-pull-to-refresh sets height:100% on .ptr, capping it at <main>'s height, so content overflow is clipped and <main> never scrolls
test: Read library source, trace CSS cascade, check .ptr / .ptr__children styles
expecting: Fix verified by build + lint passing
next_action: DONE — archived

## Symptoms

expected: Page should scroll normally to show all discipline cases
actual: Cannot scroll down on the tuchtzaken page — content is cut off
errors: No error messages
reproduction: Go to /tuchtzaken, try to scroll down
started: Likely after recent changes — commits 893e258e (column visibility), eba2e54b/f5c53c68 (team column/filter) added content that now exceeds viewport height

## Eliminated

- hypothesis: DisciplineCasesList JSX structure has broken overflow/height classes
  evidence: No overflow-hidden, h-screen, or max-h-screen classes found in DisciplineCasesList.jsx or DisciplineCaseTable.jsx. Structure identical to working pages.
  timestamp: 2026-02-22

- hypothesis: ColumnSettingsPanel breaks scroll by rendering outside the inner div
  evidence: ColumnSettingsPanel renders via position:fixed (fixed overlay), has no layout effect.
  timestamp: 2026-02-22

- hypothesis: FairplayRoute capability wrapper adds a constraining div
  evidence: FairplayRoute returns children directly with no wrapper div.
  timestamp: 2026-02-22

## Evidence

- timestamp: 2026-02-22
  checked: node_modules/react-simple-pull-to-refresh/build/index.esm.js
  found: Library injects CSS: ".ptr, .ptr__children { height: 100%; overflow: hidden; }". initContainer() sets inline overflowX='hidden', overflowY='auto' on .ptr__children.
  implication: .ptr is always capped at height:100% of its containing block (<main>). With overflow:hidden, content that overflows .ptr is clipped and does NOT contribute to <main>'s scrollHeight. <main> never scrolls.

- timestamp: 2026-02-22
  checked: src/index.css (commit b6e176cc)
  found: ".ptr__children { overflow: visible !important; }" was added to fix FilterDropdown clipping on iOS Safari. But .ptr { overflow: hidden } was NOT overridden.
  implication: .ptr__children no longer clips children, but .ptr itself still has height:100% + overflow:hidden. Content that exceeds viewport is clipped at .ptr's boundary. <main>.scrollHeight == <main>.clientHeight → no scroll.

- timestamp: 2026-02-22
  checked: Why only tuchtzaken breaks (not Teams/VOG/People)
  found: Other pages have fewer rows that fit in the viewport. Recent tuchtzaken commits (893e258e, eba2e54b, f5c53c68) added a Team column and column visibility, making the table taller/wider. Now tuchtzaken content exceeds the viewport for the first time → scroll was always broken but never noticed.
  implication: Root cause is the missing height:auto override on .ptr, exposed by the new content volume.

## Resolution

root_cause: react-simple-pull-to-refresh injects CSS that sets height:100% and overflow:hidden on .ptr. This caps .ptr at the height of its containing block (<main>) and clips any content that overflows. Since .ptr's rendered height == <main>'s height, <main>.scrollHeight == <main>.clientHeight and <main> never scrolls. Recent commits added more table content (Team column + column visibility) that caused the page to exceed the viewport height for the first time, revealing the always-broken scroll.

fix: Added ".ptr { height: auto !important; }" to src/index.css alongside the existing .ptr__children override. With height:auto, .ptr grows with its content, <main>.scrollHeight exceeds <main>.clientHeight, and <main>'s overflow-y-auto provides scrollbars. The min-h-full class on PullToRefreshWrapper ensures .ptr is at least viewport-tall (min-height:100%).

verification: npm run build → clean. npm run lint → 0 warnings.

files_changed:
  - src/index.css
