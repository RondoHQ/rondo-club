# Roadmap: Caelis

## Milestones

- ✅ [v1.0 Tech Debt Cleanup](milestones/v1.0-tech-debt-cleanup.md) (Phases 1-6) — SHIPPED 2026-01-13
- ✅ [v2.0 Multi-User](milestones/v2.0-multi-user.md) (Phases 7-11) — SHIPPED 2026-01-13
- ✅ [v2.1 Bulk Operations](milestones/v2.1-bulk-operations.md) (Phases 12-13) — SHIPPED 2026-01-13
- ✅ [v2.2 List View Polish](milestones/v2.2-list-view-polish.md) (Phases 14-15) — SHIPPED 2026-01-13

## Current Milestone

No active milestone. Use `/gsd:new-milestone` to create the next milestone.

<details>
<summary>✅ v2.2 List View Polish (Phases 14-15) — SHIPPED 2026-01-13</summary>

**Overview:** Complete the list view experience with full sorting capabilities, labels display, and extended bulk actions.

**Phases:**
- [x] Phase 14: List View Columns & Sorting (2/2 plans) ✓
- [x] Phase 15: Extended Bulk Actions (2/2 plans) ✓

**Total:** 2 phases, 4 plans

**Key Accomplishments:**
- Split Name into First Name / Last Name columns
- Labels column with styled pills
- Clickable column headers with sort indicators
- Sticky table header and selection toolbar
- Organization, Workspace, Labels sorting
- BulkOrganizationModal with search and clear option
- BulkLabelsModal with add/remove mode toggle

**Issues closed:** ISS-001, ISS-002, ISS-003, ISS-004, ISS-005

See [milestone archive](milestones/v2.2-list-view-polish.md) for full details.

</details>

<details>
<summary>✅ v2.1 Bulk Operations (Phases 12-13) — SHIPPED 2026-01-13</summary>

**Overview:** Enable efficient bulk management of contacts through a new list view with multi-select and batch actions.

**Phases:**
- [x] Phase 12: List View & Selection Infrastructure (1/1 plans) ✓
- [x] Phase 13: Bulk Actions (2/2 plans) ✓

**Total:** 2 phases, 3 plans

**Key Accomplishments:**
- Card/list view toggle for people screen
- Tabular list view with Name, Organization, Workspace columns
- Checkbox multi-selection infrastructure
- Bulk update REST endpoint with ownership validation
- Bulk visibility and workspace assignment modals

See [milestone archive](milestones/v2.1-bulk-operations.md) for full details.

</details>

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
| v2.2 List View Polish | 14-15 | 4/4 | Complete ✅ | 2026-01-13 |

**Total: 15 phases, 38 plans shipped**
