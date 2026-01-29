---
phase: 117
plan: 01
subsystem: frontend-ui
tags:
  - react
  - ui-restrictions
  - sportlink-integration
  - organization-management
requires:
  - phase-116
provides:
  - ui-creation-disabled-teams
  - ui-creation-disabled-commissies
  - api-creation-preserved
affects:
  - phase-118
tech-stack:
  added: []
  patterns:
    - ui-restriction-pattern
key-files:
  created: []
  modified:
    - src/pages/Teams/TeamsList.jsx
    - src/pages/Commissies/CommissiesList.jsx
decisions:
  - id: ui-restriction-complete-removal
    decision: Remove creation controls entirely rather than conditionally hiding
    rationale: Follows Phase 116 pattern - cleaner code, no conditional logic
    impact: Creation impossible via UI, preserved in REST API
  - id: empty-state-messaging
    decision: Update empty state messages to inform about API/import data flow
    rationale: Users need to understand why creation button is missing
    impact: Clear communication that organizations come from external source
metrics:
  duration: 127s
  completed: 2026-01-29
---

# Phase 117 Plan 01: Organization Creation Restrictions Summary

**One-liner:** Remove UI creation controls for teams and commissies, preserving REST API endpoints for Sportlink automation

## Objective

Block manual creation of teams and commissies in the UI while maintaining REST API functionality for the Sportlink sync automation system (constraint API-01).

## What Was Delivered

### Teams List Page (TeamsList.jsx)
- Removed "Nieuw team" button from page header
- Removed "Nieuw team" button from empty state
- Updated empty state message to "Teams worden via de API of data import toegevoegd."
- Removed all creation-related imports (Plus icon, useCreateTeam hook, TeamEditModal)
- Removed all creation-related state (showTeamModal, isCreatingTeam)
- Removed all creation-related handlers (createTeamMutation, handleCreateTeam)
- Removed TeamEditModal component JSX from list page

### Commissies List Page (CommissiesList.jsx)
- Removed "Nieuwe commissie" button from page header
- Removed "Nieuwe commissie" button from empty state
- Updated empty state message to "Commissies worden via de API of data import toegevoegd."
- Removed all creation-related imports (Plus icon, useCreateCommissie hook, CommissieEditModal)
- Removed all creation-related state (showCommissieModal, isCreatingCommissie)
- Removed all creation-related handlers (createCommissieMutation, handleCreateCommissie)
- Removed CommissieEditModal component JSX from list page

### Preserved Functionality
- REST API POST /wp/v2/teams endpoint remains functional
- REST API POST /wp/v2/commissies endpoint remains functional
- TeamEditModal component still used on TeamDetail.jsx for editing existing teams
- CommissieEditModal component still used on CommissieDetail.jsx for editing existing commissies

## Technical Implementation

### Pattern Applied
Followed Phase 116 person edit restrictions pattern:
1. Complete removal of UI elements (no conditional hiding)
2. Clean removal of unused imports and state
3. No backend modifications (REST API untouched)
4. Clear user messaging about data source

### Code Changes
**TeamsList.jsx:**
- Removed 42 lines of creation-related code
- 3 insertions (simplified component structure)

**CommissiesList.jsx:**
- Removed 42 lines of creation-related code
- 3 insertions (simplified component structure)

### Build Impact
- Bundle size slightly reduced (creation modal code no longer included in list page chunks)
- No new dependencies added
- No TypeScript or ESLint errors introduced

## Decisions Made

### UI Restriction Pattern
**Decision:** Remove creation controls entirely rather than conditionally hiding them

**Context:** Could have used feature flags or permission checks to conditionally show/hide buttons

**Rationale:**
- Cleaner code without conditional logic
- Follows established Phase 116 pattern
- Sportlink is single source of truth for organizations
- No use case for manual creation in production

**Impact:**
- Simpler codebase to maintain
- Users cannot create teams/commissies via UI under any circumstances
- REST API remains available for automation

### Empty State Messaging
**Decision:** Change empty state from "Voeg je eerste [type] toe" to "[Type] worden via de API of data import toegevoegd"

**Context:** Users seeing empty list without creation button might be confused

**Rationale:**
- Communicates data source (API/import) clearly
- Sets expectation that this is read-only for manual operations
- Aligns with Sportlink integration architecture

**Impact:**
- Users understand why creation button is missing
- Reduces support questions about "how do I add organizations?"
- Clear system behavior communication

## Deviations from Plan

None - plan executed exactly as written.

## Integration Points

### Upstream Dependencies
- Phase 116 person edit restrictions (pattern established)
- Sportlink sync system (provides organization data via API)

### Downstream Effects
- Phase 118 must handle organization-person relationships with UI restrictions in mind
- Any future organization management features must go through REST API

### REST API Preservation
**POST /wp/v2/teams:**
- Accepts title, ACF fields (industry, website, logo, etc.)
- Returns created team object with ID
- Used by Sportlink sync automation

**POST /wp/v2/commissies:**
- Accepts title, ACF fields (parent team, description, etc.)
- Returns created commissie object with ID
- Used by Sportlink sync automation

## Next Phase Readiness

### Phase 118 Blockers
None. This phase is complete and Phase 118 can proceed.

### Known Issues
None.

### Recommendations for Phase 118
1. Consider whether relationship management (adding persons to organizations) should also be UI-restricted
2. Verify Sportlink sync handles person-organization relationships
3. May need to add read-only indicators on organization detail pages

## Testing Notes

### Manual Verification Completed
- ✅ TeamsList page loads without "Nieuw team" button
- ✅ CommissiesList page loads without "Nieuwe commissie" button
- ✅ Empty state shows API/import messaging
- ✅ TeamDetail page still has edit functionality via modal
- ✅ CommissieDetail page still has edit functionality via modal
- ✅ npm run build completes successfully
- ✅ No new ESLint errors introduced

### Verification Commands
```bash
# No creation text in list pages
grep -r "Nieuw team" src/pages/Teams/TeamsList.jsx  # No matches
grep -r "Nieuwe commissie" src/pages/Commissies/CommissiesList.jsx  # No matches

# No modal state in list pages
grep -r "showTeamModal" src/pages/Teams/TeamsList.jsx  # No matches
grep -r "showCommissieModal" src/pages/Commissies/CommissiesList.jsx  # No matches

# Edit modals preserved on detail pages
grep -r "TeamEditModal" src/pages/Teams/TeamDetail.jsx  # Still present
grep -r "CommissieEditModal" src/pages/Commissies/CommissieDetail.jsx  # Still present
```

## Success Criteria Met

All Phase 117 requirements satisfied:
- ✅ **ORG-01:** Team creation disabled in UI (no "Nieuw team" button in header or empty state)
- ✅ **ORG-02:** Commissie creation disabled in UI (no "Nieuwe commissie" button in header or empty state)
- ✅ **API-01:** REST API functionality unchanged (no backend modifications)

No other UI path allows creating teams or commissies:
- ✅ List page creation buttons removed
- ✅ Empty state creation buttons removed
- ✅ Modal components removed from list pages
- ✅ Creation handlers and state removed

## Files Changed

### Modified (2 files)
- `src/pages/Teams/TeamsList.jsx` - Removed team creation UI controls
- `src/pages/Commissies/CommissiesList.jsx` - Removed commissie creation UI controls

### Preserved
- `src/components/TeamEditModal.jsx` - Still used on TeamDetail.jsx
- `src/components/CommissieEditModal.jsx` - Still used on CommissieDetail.jsx
- `src/hooks/useTeams.js` - useCreateTeam hook unused but preserved for potential future needs
- `src/hooks/useCommissies.js` - useCreateCommissie hook unused but preserved for potential future needs

## Commits

| Commit | Message | Files |
|--------|---------|-------|
| f0c8e85 | feat(117-01): remove team creation controls from TeamsList | TeamsList.jsx |
| 0a90c3b | feat(117-01): remove commissie creation controls from CommissiesList | CommissiesList.jsx |

## Lessons Learned

### What Went Well
- Pattern from Phase 116 applied cleanly to organization pages
- No complications with removing creation controls
- Build and lint passed immediately after changes
- Clear separation between list (no creation) and detail (allow editing) pages

### What Could Be Improved
- Could add data-testid attributes for automated testing
- Consider adding a banner/notice on list pages explaining read-only nature

### Technical Insights
- React component structure made it easy to remove creation functionality without affecting other features
- TanStack Query invalidation still works correctly for data refreshing
- Empty state messaging is important for user communication when removing functionality

## Documentation Updates Needed

None. This is internal restructuring; external documentation (if any) doesn't reference manual organization creation.

## Related Links

- Phase 116 Person Edit Restrictions: `.planning/phases/116-person-edit-restrictions/116-01-SUMMARY.md`
- Phase 117 Research: `.planning/phases/117-organization-creation-restrictions/117-RESEARCH.md`
- Sportlink Integration: (external system, no internal docs)
