---
id: M009
provides:
  - Cleaner person detail page with conditional card visibility and tab item counts
key_decisions:
  - "TabButton count prop renders in parentheses only when count > 0 — tabs without items show no number"
  - "Account card gated on linked_user_id (not volunteer status) — only show when WP account exists"
  - "Relaties card uses sortedRelationships?.length > 0 guard — completely hidden when empty"
  - "VOG status pill removed from header entirely — VOG info still accessible via VOGCard in profile tab"
patterns_established:
  - "Optional count prop pattern on TabButton for showing item counts across any tabbed interface"
observability_surfaces:
  - none
requirement_outcomes: []
duration: 1 hour
verification_result: passed
completed_at: 2026-03-12
---

# M009: Person Detail Page Improvements

**Cleaner person detail page: empty Relaties/Account cards hidden, tab counts added, VOG header pill removed.**

## What Happened

All four improvements were delivered in a single slice (S01) with one commit. The changes are purely presentational — no backend modifications, no new API endpoints, no data model changes.

The TabButton component gained an optional `count` prop that displays a parenthesized count when greater than zero. This was applied to Tijdlijn, Rollen, Kleding, and Tuchtzaken tabs, each reading from their respective data arrays.

The Relaties card now wraps in a `sortedRelationships?.length > 0` conditional, completely hiding it when no relationships exist instead of showing an empty card. The Account card condition was tightened from "admin + volunteer" to "admin + linked_user_id exists", so it only appears when the person actually has a WordPress account. The VOG status pill was removed from the person header; VOG information remains accessible in the VOGCard component on the profile tab.

## Cross-Slice Verification

Single slice, so no cross-slice concerns. Each success criterion verified:

1. **Relaties card hidden when no relationships** — Verified: line 1476 of PersonDetail.jsx wraps card in `{sortedRelationships?.length > 0 && (` conditional
2. **Account card only shown with linked account** — Verified: line 1855 checks `person?.linked_user_id` in addition to `config.isAdmin`
3. **Tab labels show item counts** — Verified: lines 1288-1293 pass `count` prop to TabButton for Kleding, Tijdlijn, Rollen, Tuchtzaken tabs; TabButton renders `({count})` when > 0
4. **VOG status pill removed from header** — Verified: grep for VOG in header area returns no pill markup; only VOGCard import and usage in profile tab remain
5. **Build passes** — `npm run build` succeeds (109 precache entries)
6. **Lint passes** — `npm run lint` returns 0 errors, 0 warnings

## Requirement Changes

No requirements changed status during this milestone. All changes were UI tweaks with no associated tracked requirements.

## Forward Intelligence

### What the next milestone should know
- TabButton now accepts an optional `count` prop — any future tabbed interface can reuse this pattern for item counts
- The person detail page is a large component (~1900 lines) — consider component extraction if future milestones add more complexity

### What's fragile
- Tab counts rely on client-side array lengths (timeline, disciplineCases, sortedWorkHistory, clothingProfile.current_items) — if data loading patterns change, counts could show stale or zero values during loading

### Authoritative diagnostics
- `src/components/TabButton.jsx` — simple component, single source of truth for tab count rendering
- `src/pages/People/PersonDetail.jsx` lines 1286-1293 — all tab count wiring in one place

### What assumptions changed
- No assumptions changed — all four changes were straightforward as anticipated (risk:low confirmed)
