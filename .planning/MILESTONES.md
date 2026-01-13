# Project Milestones: Caelis

## v2.0 Multi-User (Shipped: 2026-01-13)

**Delivered:** Multi-user collaboration platform with workspaces, contact visibility (private/workspace/shared), @mentions, and team activity features while preserving single-user backward compatibility.

**Phases completed:** 7-11 (20 plans total)

**Key accomplishments:**

- Workspace CPT with role-based membership system (Admin/Member/Viewer roles) enabling multi-user collaboration
- Visibility framework for contacts with three levels (private/workspace/shared) and workspace_access taxonomy
- Complete workspace invitation system with email invitations, 7-day expiring tokens, and role assignment
- @Mentions infrastructure with MentionInput component, autocomplete, and notification preferences (immediate/digest)
- Workspace-scoped iCal calendar feeds with token-based authentication
- Workspace activity digest integrating @mentions and shared notes into daily reminders

**Stats:**

- 105 files created/modified
- +15,718 / -1,272 lines changed
- 5 phases, 20 plans
- 1 day from start to ship

**Git range:** `feat(07-01)` → `docs(11-01)`

**What's next:** To be determined

---

## v1.0 Tech Debt Cleanup (Shipped: 2026-01-13)

**Delivered:** Split monolithic 107KB REST API into domain-specific classes, hardened security with sodium encryption and XSS protection, cleaned up production code.

**Phases completed:** 1-6 (11 plans total)

**Key accomplishments:**

- Split class-rest-api.php into 5 domain-specific classes (Base, People, Companies, Slack, Import/Export)
- Implemented sodium encryption for Slack tokens with fallback
- Added server-side XSS protection using WordPress native wp_kses functions
- Removed 48 console.error() calls from 11 React files
- Created .env.example documenting 4 required environment variables
- Consolidated decodeHtml() to shared formatters.js utility

**Stats:**

- 60 files created/modified
- +4,779 / -2,083 lines changed
- 6 phases, 11 plans
- 1 day from start to ship

**Git range:** `91806f2` → `f4e307b`

**What's next:** v2.0 Multi-User

---
