# State: v6.1 Feedback System

## Project Reference

**Core Value:** Enable users to submit bug reports and feature requests from within Caelis, with API access for external tool integration and admin management capabilities.

**Current Focus:** Roadmap created, awaiting phase 1 planning.

## Current Position

**Phase:** Not started
**Plan:** N/A
**Status:** Roadmap complete
**Progress:** [--------------------] 0%

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases Total | 4 |
| Phases Complete | 0 |
| Requirements Total | 17 |
| Requirements Complete | 0 |
| Plans Executed | 0 |

## Accumulated Context

### Key Decisions

| Decision | Rationale | Phase |
|----------|-----------|-------|
| Global scope (not workspace) | User confirmed - simpler implementation, feedback is installation-wide | Roadmap |
| Admin management UI needed | User confirmed - not API-only, need full management interface | Roadmap |
| WordPress attachment defaults | User confirmed - no custom limits needed | Roadmap |
| Notifications deferred | User confirmed - focus on core feedback functionality first | Roadmap |
| 4-status workflow | new -> in_progress -> resolved/declined covers typical feedback lifecycle | Roadmap |

### Patterns to Follow

| Pattern | Reference | Notes |
|---------|-----------|-------|
| CPT registration | `includes/class-post-types.php` | Follow existing CPT pattern |
| REST endpoints | `includes/class-rest-api.php`, `includes/class-rest-base.php` | Extend Base class |
| ACF fields | `acf-json/` directory | JSON sync for version control |
| Frontend pages | `src/pages/` directory | React component per feature |
| Settings subtabs | Existing Settings page | Tab-based organization |

### Technical Notes

- Application passwords: Use WordPress native implementation (no custom auth)
- Feedback access: Users see own feedback, admins see all
- System info capture: Browser via navigator, version from wpApiSettings, URL from location
- File uploads: WordPress media library with gallery field type

### Blockers

None currently.

### Todos

- [ ] Plan Phase 1: Backend Foundation
- [ ] Create REQUIREMENTS.md with detailed specs

## Session Continuity

### Last Session

**Date:** 2026-01-21
**Completed:** Roadmap creation
**Next Action:** Plan Phase 1

### Handoff Notes

Roadmap defines 4 phases with 17 requirements total. All scope decisions documented. Ready for phase planning. Key files to reference:
- PRD: `docs/prd-feedback-system.md`
- CPT pattern: `includes/class-post-types.php`
- REST pattern: `includes/class-rest-base.php`, `includes/class-rest-api.php`
