# Roadmap: Caelis

## Milestones

- ✅ [v1.0 Tech Debt Cleanup](milestones/v1.0-tech-debt-cleanup.md) (Phases 1-6) — SHIPPED 2026-01-13
- ✅ [v2.0 Multi-User](milestones/v2.0-multi-user.md) (Phases 7-11) — SHIPPED 2026-01-13
- ✅ **v2.1 Bulk Operations** — Phases 12-13 — SHIPPED 2026-01-13

## Current Milestone

None active. Run `/gsd:complete-milestone` to archive v2.1 or `/gsd:new-milestone` to start next version.

### ✅ v2.1 Bulk Operations (Complete)

**Milestone Goal:** Enable efficient bulk management of contacts through a new list view with multi-select and batch actions

#### Phase 12: List View & Selection Infrastructure ✓

**Goal**: Add list view to people screen with columns (name, workspace, org) and checkbox selection
**Depends on**: v2.0 Multi-User complete
**Status**: Complete (2026-01-13)

Plans:
- [x] 12-01: List view with card/list toggle, table columns, checkbox selection infrastructure

#### Phase 13: Bulk Actions ✓

**Goal**: Implement bulk workspace assignment and bulk visibility change actions
**Depends on**: Phase 12
**Status**: Complete (2026-01-13)

Plans:
- [x] 13-01: Bulk update REST endpoint and React hook
- [x] 13-02: Bulk actions UI with dropdown and modals

---

<details>
<summary>✅ v2.0 Multi-User (Phases 7-11) — SHIPPED 2026-01-13</summary>

**Overview:** Transform Caelis from a single-user personal CRM into a multi-user platform combining Clay.earth's intimate relationship focus with Twenty CRM's collaborative features. Adds workspaces, sharing, and team collaboration while preserving privacy.

**Phases:**
- [x] Phase 7: Data Model & Visibility System (4/4 plans) ✓
- [x] Phase 8: Workspace & Team Infrastructure (3/3 plans) ✓
- [x] Phase 9: Sharing UI & Permissions Interface (6/6 plans) ✓
- [x] Phase 10: Collaborative Features (5/5 plans) ✓
- [x] Phase 11: Migration, Testing & Polish (2/2 plans) ✓

**Total:** 5 phases, 20 plans

**Key Accomplishments:**
- Workspace CPT with role-based membership (Admin/Member/Viewer)
- Contact visibility system (private/workspace/shared)
- ShareModal and VisibilitySelector React components
- @mentions in notes with notification preferences
- Workspace iCal calendar feeds
- WP-CLI migration command for existing data

See [milestone archive](milestones/v2.0-multi-user.md) for full details.

</details>

<details>
<summary>✅ v1.0 Tech Debt Cleanup (Phases 1-6) — SHIPPED 2026-01-13</summary>

**Overview:** Cleaned up technical debt in the Caelis personal CRM. Split the monolithic REST API class into domain-specific files, hardened security, and cleaned up code quality issues.

**Phases:**
- [x] Phase 1: REST API Infrastructure (2/2 plans) ✓
- [x] Phase 2: REST API People & Companies (2/2 plans) ✓
- [x] Phase 3: REST API Integrations (2/2 plans) ✓
- [x] Phase 4: Security Hardening (2/2 plans) ✓
- [x] Phase 5: XSS Protection (1/1 plan) ✓
- [x] Phase 6: Code Cleanup (2/2 plans) ✓

**Total:** 6 phases, 11 plans

**Key Accomplishments:**
- Split 107KB class-rest-api.php into 5 domain-specific classes
- Implemented sodium encryption for Slack tokens
- Added server-side XSS protection with wp_kses
- Removed 48 console.error() calls
- Created .env.example with environment documentation

See [milestone archive](milestones/v1.0-tech-debt-cleanup.md) for full details.

</details>

## Progress

| Milestone | Phases | Plans | Status | Completed |
|-----------|--------|-------|--------|-----------|
| v1.0 Tech Debt Cleanup | 1-6 | 11/11 | Complete ✅ | 2026-01-13 |
| v2.0 Multi-User | 7-11 | 20/20 | Complete ✅ | 2026-01-13 |
| v2.1 Bulk Operations | 12-13 | 3/3 | Complete ✅ | 2026-01-13 |

| Phase | Milestone | Plans | Status | Completed |
|-------|-----------|-------|--------|-----------|
| 12. List View & Selection | v2.1 | 1/1 | Complete ✓ | 2026-01-13 |
| 13. Bulk Actions | v2.1 | 2/2 | Complete ✓ | 2026-01-13 |
