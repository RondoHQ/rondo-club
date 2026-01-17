# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-17)

**Core value:** Personal CRM with multi-user collaboration capabilities
**Current focus:** None (between milestones)

## Current Position

Milestone: None (v4.9 shipped)
Phase: N/A
Plan: N/A
Status: Between milestones
Last activity: 2026-01-17 — Shipped v4.9 Dashboard & Calendar Polish

Progress: Ready for next milestone

## Completed Milestones

| Milestone | Phases | Plans | Shipped |
|-----------|--------|-------|---------|
| v1.0 Tech Debt Cleanup | 1-6 | 11 | 2026-01-13 |
| v2.0 Multi-User | 7-11 | 20 | 2026-01-13 |
| v2.1 Bulk Operations | 12-13 | 3 | 2026-01-13 |
| v2.2 List View Polish | 14-15 | 4 | 2026-01-13 |
| v2.3 List View Unification | 16-18 | 3 | 2026-01-13 |
| v2.4 Bug Fixes | 19 | 2 | 2026-01-13 |
| v2.5 Performance | 20 | 3 | 2026-01-13 |
| v3.0 Testing Infrastructure | 21-23 | 7 | 2026-01-13 |
| v3.1 Pending Response Tracking | 24-28 | 9 | 2026-01-14 |
| v3.2 Person Profile Polish | 29-31 | 3 | 2026-01-14 |
| v3.3 Todo Enhancement | 32-34 | 3 | 2026-01-14 |
| v3.4 UI Polish | 35-37 | 3 | 2026-01-14 |
| v3.5 Bug Fixes & Polish | 38-39 | 2 | 2026-01-14 |
| v3.6 Quick Wins & Performance | 40-41 | 2 | 2026-01-14 |
| v3.7 Todo UX Polish | 42 | 1 | 2026-01-15 |
| v3.8 Theme Customization | 43-46 | 10 | 2026-01-15 |
| v4.0 Calendar Integration | 47-55 | 11 | 2026-01-15 |
| v4.1 Bug Fixes & Polish | 56-57 | 3 | 2026-01-15 |
| v4.2 Settings & Stability | 58-60 | 3 | 2026-01-15 |
| v4.3 Performance & Documentation | 61-63 | 5 | 2026-01-16 |
| v4.4 Code Organization | 64-66 | 6 | 2026-01-16 |
| v4.5 Calendar Sync Control | 67-68 | 3 | 2026-01-16 |
| v4.6 Dashboard & Polish | 69-70 | 2 | 2026-01-16 |
| v4.7 Dark Mode & Activity Polish | 71-72 | 4 | 2026-01-17 |
| v4.8 Meeting Enhancements | 73-76 | 6 | 2026-01-17 |
| v4.9 Dashboard & Calendar Polish | 77-78 | 4 | 2026-01-17 |
**Total:** 29 milestones, 78 phases, 140 plans completed

## Deferred Issues

See `.planning/ISSUES.md`:
- ~~ISS-006: Remove card view from People~~ - RESOLVED in Phase 16
- ~~ISS-007: Move person image to its own column~~ - RESOLVED in Phase 16
- ~~ISS-008: Organizations list interface~~ - RESOLVED in Phase 17-18

**0 issues remaining**

## Decisions Made

See `.planning/PROJECT.md` Key Decisions table for full decision history.

### Phase 65-01 Decisions

- **WP-CLI multi-class exception:** Discovered `class-wp-cli.php` contains 9 CLI command classes (not in audit). Added PHPCS exclusion rather than splitting. Rationale: conditionally loaded, logically grouped CLI commands, audit stated "pattern should be preserved".

### Phase 66-02 Decisions

- **Namespace plural convention:** Used `Caelis\Notifications` (plural) to match `Caelis\Collaboration` naming pattern as specified in audit.
- **Reminders placement:** Placed Reminders class in `Caelis\Collaboration` namespace per audit mapping, not in Notifications namespace.

### Phase 66-03 Decisions

- **Cross-references updated immediately:** Updated 6 files that reference namespaced classes (VCard export, CredentialEncryption) to use fully qualified namespaces rather than waiting for Plan 04 class aliases.
- **PRM_Workspace_Members unchanged:** Left reference in ICalFeed as-is since that class will be namespaced in a future batch.

### Phase 66-04 Decisions

- **Composer classmap added:** Added classmap alongside PSR-4 to support current `class-*.php` file naming convention during transition.
- **WP-CLI kept using PRM_* CLI classes:** WP-CLI command classes (e.g., `PRM_Reminders_CLI_Command`) remain with original names since they're not part of the PSR-4 namespace structure.

### Phase 67-01 Decisions

- **sync_frequency values in minutes:** Used minutes (15, 30, 60, 240, 1440) for consistency with WP cron interval patterns.
- **Server-side validation:** Added validate_callback for sync_frequency to ensure only allowed values (15, 30, 60, 240, 1440) are accepted.
- **Backward compatibility:** Both providers default to 30 days for sync_to_days when field is not present, so existing connections continue to work.

### Phase 71-02 Decisions

- **Dark mode contrast pattern:** Consistently use gray-300/gray-400 for better contrast in dark mode (not gray-400/gray-500).

### Phase 71-FIX Decisions

- **Semi-transparent backgrounds don't work:** accent-900/30 creates unreliable contrast in dark mode.
- **Solid background pattern:** Use dark:bg-accent-800 with dark:text-accent-100 for all selected/active states.

### Phase 74-01 Decisions

- **Lazy loading for PersonEditModal:** Used lazy() and Suspense in MeetingDetailModal to avoid chunk size increase.
- **prefillData prop pattern:** Pass { first_name, last_name, email } for pre-filling PersonEditModal from external context.

### Phase 75-01 Decisions

- **Date parameter format:** YYYY-MM-DD with regex validation for REST API
- **useTodayMeetings refactored:** Now calls useDateMeetings(new Date()) internally for backward compatibility

### Phase 76-01 Decisions

- **Inline popup over modal:** Used inline positioned popup below attendee row rather than opening another modal
- **Case-insensitive duplicate check:** Email addresses compared case-insensitively and stored lowercase

### Phase 77-01 Decisions

- **280px content height:** Chosen to comfortably display ~5 items while keeping widget size manageable
- **6 skeleton widgets:** Shows typical dashboard layout during loading for visual consistency

### Phase 77-FIX Decisions

- **placeholderData for layout stability:** Used `placeholderData: (prev) => prev` in useDateMeetings hook to prevent layout jump during date navigation. This is the TanStack Query v5 pattern replacing deprecated `keepPreviousData: true`.

## Roadmap Evolution

- Milestone v4.3 complete: React performance review, installation documentation, WPCS compliance
- Milestone v4.3 archived: Git tag v4.3 created
- Milestone v4.4 created: Code organization, 3 phases (Phase 64-66)
- Milestone v4.4 complete: PSR-4 namespaces, Composer autoloading, 38 classes namespaced
- Milestone v4.5 created: Calendar sync control, 2 phases (Phase 67-68)
- Milestone v4.5 complete: Per-connection sync settings, calendar selection UI
- Milestone v4.6 created: Dashboard customization & UI polish, 2 phases (Phase 69-70)
- Milestone v4.7 created: Dark Mode & Activity Polish, 2 phases (Phase 71-72)
- Phase 71 complete: Dark Mode Fixes (2 plans + FIX plan completed)
- Phase 71 FIX UAT passed: All 3 issues verified fixed
- Phase 72 complete: Activity Bug Fixes (1 plan)
- Milestone v4.7.0 released and deployed
- Phase 73 complete: Meeting Detail Modal (2 plans)
- Phase 74 complete: Add Person from Meeting (1 plan)
- Phase 75 complete: Date Navigation (1 plan)
- Phase 76 complete: Add Email to Existing Person (1 plan)
- Phase 76-FIX complete: Fixed ACF validation error, increased popup height
- Milestone v4.8 complete: Meeting Enhancements (Phases 73-76)
- Milestone v4.8 archived: Git tag v4.8 created, ROADMAP.md and REQUIREMENTS.md archived
- Milestone v4.9 created: Dashboard & Calendar Polish (Phase 77+)
- Phase 77 complete: Fixed Height Dashboard Widgets (1 plan + FIX plan)
- Phase 77-FIX UAT passed: Events widget no longer jumps during date navigation
- Phase 78 complete: Multi-Calendar Selection (1 plan)
- Phase 78-FIX complete: Two-column layout for EditConnectionModal
- Milestone v4.9 complete: Dashboard & Calendar Polish (Phases 77-78)

### Phase 78-FIX Decisions

- **Wider modal:** Changed from max-w-md to max-w-2xl to accommodate two columns
- **Responsive breakpoint:** Used md:grid-cols-2 so layout stacks on small screens
- **Non-Google connections:** Keep single-column layout since they have fewer options

## Session Continuity

Last session: 2026-01-17T14:08:00Z
Stopped at: Completed 78-FIX-PLAN.md
Resume file: None

## Accumulated Context

### Pending Todos

38 todos in `.planning/todos/pending/`:
1. ~~Add label management interface (ui)~~ - DONE in v3.4 Phase 37
2. ~~Todo detail modal with notes and multi-person support (ui)~~ - DONE in v3.3
3. Add import from Twenty CRM (api)
4. ~~Prioritize first name in search (api)~~ - DONE in v3.5 Phase 39
5. ~~Todo changes should invalidate dashboard cache (api)~~ - DONE in v3.5 Phase 39
6. ~~Add Awaiting block to dashboard (ui)~~ - DONE in v3.4 Phase 36
7. ~~Make Timeline panel 2 columns wide on desktop (ui)~~ - DONE in v3.4 Phase 36
8. ~~Simplify Slack contact details display (ui)~~ - DONE in v3.4
9. ~~Make company website link clickable in list (ui)~~ - DONE in v3.4
10. ~~Remove labels from company list (ui)~~ - DONE in v3.4
11. ~~Use build numbers for refresh indicator (ui)~~ - DONE in v3.4
12. ~~Important date name overwritten by auto-title (api)~~ - DONE in v3.5 Phase 39
13. ~~Update X logo color to black (ui)~~ - DONE in v3.5 Phase 38
14. ~~Dashboard card styling consistency (ui)~~ - DONE in v3.5 Phase 38
15. ~~Add checkbox to awaiting response items (ui)~~ - DONE in v3.6 Phase 40
16. ~~Lowercase email addresses on save (api)~~ - DONE in v3.6 Phase 40
17. ~~Fix CardDAV connection details dark mode contrast (ui)~~ - DONE in v4.1 Phase 56
18. ~~Fix React/DOM Node synchronization errors (ui)~~ - DONE in v4.1 Phase 56 (documented as benign)
19. ~~Fix recurring module MIME type errors (ui)~~ - DONE in v4.1 Phase 56 (deploy procedure fix)
20. ~~Add wp-config.php constants installation documentation (docs)~~ - DONE in v4.3 Phase 62
21. ~~Restructure Settings with Connections tab and subtabs (ui)~~ - DONE in v4.2 Phase 59
22. ~~Fix search modal active result dark mode contrast (ui)~~ - DONE in v4.1 Phase 56
23. ~~Re-run meetings matching when email address added (api)~~ - DONE in v4.2 Phase 60
24. ~~Match events against all person email addresses (api)~~ - DONE in v4.0 (already implemented in get_email_lookup)
25. ~~Update favicon to match current color scheme (ui)~~ - DONE in v4.1 Phase 57
26. ~~Fix Today's meetings layout and timezone display (ui)~~ - DONE in v4.1 Phase 57
27. ~~Soften PersonDetail delete button style (ui)~~ - DONE in v4.6 Phase 70-01
28. ~~Review performance using react-best-practices skill (ui)~~ - DONE in v4.3 Phase 61 (no changes needed)
29. ~~Allow selecting Google Calendars to sync (ui)~~ - DONE in v4.5 Phase 68-01
30. ~~Check and configure Google Calendar sync date range (api)~~ - DONE in v4.5 Phase 67-01
31. ~~Split multi-class files and reorganize includes folder (refactoring)~~ - DONE in v4.4 Phase 65-66
32. ~~Fix Settings subtab headings dark mode contrast (ui)~~ - DONE in v4.7 Phase 71-02
33. ~~Fix Timeline activity type label dark mode contrast (ui)~~ - DONE in v4.7 Phase 71-02
34. ~~Fix ImportantDateModal people selector dark mode contrast (ui)~~ - DONE in v4.7 Phase 71-02
35. ~~Add date navigation to meetings widget (ui)~~ - DONE in v4.8 Phase 75
36. ~~Meeting detail modal with add person (ui)~~ - DONE in v4.8 Phase 73-74
37. ~~Add email to existing person from meeting (ui)~~ - DONE in v4.8 Phase 76
38. ~~Fixed height for homepage widgets (ui)~~ - DONE in v4.9 Phase 77

Completed todos in `.planning/todos/done/`:
1. Testing framework - PHPUnit done in v3.0 (Playwright deferred)
2. React bundle chunking - Done in v2.5
3. Console MIME type errors - Resolved via production deploy
4. Add pending response tracking - Done in v3.1
5. Convert todos to custom post type - Done in v3.1
6. Fix todo migration and open todos display - Fixed: migration bypasses access control
7. Show role + job in person header - Done in Phase 29
8. Add persistent todos sidebar on person profile - Done in Phase 30
9. Add mobile todos access - Done in Phase 31

## Next Steps

- Identify next milestone from pending todos
- Or start new work based on user needs
- Run `/gsd:new-milestone` to begin next milestone
