---
status: resolved
trigger: "On https://rondo.svawc.nl/financien/contributie, clicking 'Instellingen' does nothing at all — the contribution settings page/panel never appears."
created: 2026-02-18T00:00:00Z
updated: 2026-02-18T00:10:00Z
---

## Current Focus

hypothesis: CONFIRMED — navigate() call used stale /contributie/:tab path that redirects back without tab
test: Read Contributie.jsx and router.jsx
expecting: Stale URL path in navigate call
next_action: DONE — fixed, deployed, committed

## Symptoms

expected: Clicking "Instellingen" on /financien/contributie should navigate to or show contribution settings
actual: Nothing happens — no navigation, no panel, no loading indicator, no error
errors: None visible
reproduction: Go to https://rondo.svawc.nl/financien/contributie, click "Instellingen" button/link
started: Unknown — user just reported it

## Eliminated

## Evidence

- timestamp: 2026-02-18T00:05:00Z
  checked: src/pages/Contributie/Contributie.jsx line 37
  found: onClick={() => navigate(`/contributie/${t.id}`) — uses OLD /contributie/:tab URL pattern
  implication: Router has redirect at path 'contributie/:tab' → Navigate to="/financien/contributie" (drops tab), so click loops back to same page

- timestamp: 2026-02-18T00:05:00Z
  checked: src/router.jsx lines 229-230
  found: { path: 'contributie/:tab', element: <Navigate to="/financien/contributie" replace /> } — redirect drops the tab param
  implication: Navigating to /contributie/instellingen silently redirects to /financien/contributie with no tab — appears as "nothing happened"

- timestamp: 2026-02-18T00:05:00Z
  checked: src/pages/Contributie/Contributie.jsx line 23
  found: <Navigate to="/contributie/overzicht" replace /> — also uses old path for non-admin redirect
  implication: Would also loop infinitely if a non-admin somehow navigated to /financien/contributie/instellingen

## Resolution

root_cause: The tab click handler in Contributie.jsx used the old /contributie/:tab URL format (pre-finance-reorganization). The router has a legacy redirect from /contributie/:tab → /financien/contributie that drops the :tab parameter, causing "Instellingen" to silently redirect back to the same page with no tab active.
fix: Updated navigate() call from /contributie/${t.id} to /financien/contributie/${t.id}. Also fixed the non-admin redirect from /contributie/overzicht to /financien/contributie/overzicht.
verification: Build succeeded, deployed to production.
files_changed:
  - src/pages/Contributie/Contributie.jsx
