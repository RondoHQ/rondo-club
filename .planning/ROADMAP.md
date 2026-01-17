# Roadmap: Caelis v5.0.1

**Milestone:** v5.0.1 Meeting Card Polish
**Created:** 2026-01-18
**Phases:** 1 (Phase 86)

## Phase Overview

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 86 | Meeting Card Polish | MEET-01, MEET-02, MEET-03, MEET-04 | Pending |

## Phase 86: Meeting Card Polish

**Goal:** Improve dashboard meeting card visual clarity with time-based styling and data cleanup

**Plans:** 1 plan

Plans:
- [ ] 86-01-PLAN.md - Meeting card styling and data cleanup

**Requirements covered:**
- MEET-01: Past events display with dimmed/muted styling
- MEET-02: Currently active event is visually highlighted
- MEET-03: Event times display in 24h format
- MEET-04: Existing synced events have `&amp;` decoded to `&`

**Success criteria:**
1. Past events (end time < now) appear dimmed compared to upcoming events
2. Current event (start < now < end) has distinct highlight styling
3. All meeting times show 24h format (e.g., "14:30" not "2:30 PM")
4. Running data cleanup updates existing event titles with proper `&` character
5. All changes work correctly in both light and dark mode

**Implementation notes:**
- Frontend: React component styling changes in MeetingCard (Dashboard.jsx)
- Backend: WP-CLI command `wp prm event cleanup-titles` for data cleanup
- No API changes needed

---
*Roadmap created: 2026-01-18*
*Plans created: 2026-01-18*
