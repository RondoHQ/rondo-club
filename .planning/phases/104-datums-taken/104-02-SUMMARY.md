---
phase: 104-datums-taken
plan: 02
subsystem: ui-translation
status: complete
completed: 2026-01-25

requires:
  - phase: 103-teams-commissies
    reason: "Follows established Dutch terminology patterns"
  - plan: 104-01
    reason: "Builds on dates translation work"

provides:
  - "Fully translated Todos list page (Taken)"
  - "Dutch filter tabs using CONTEXT.md status terms (Te doen, Openstaand, Afgerond)"
  - "Translated complete todo modal with Dutch status options"

affects:
  - phase: 105-dashboard-tijdlijn
    reason: "Todos terminology carries over to dashboard todo widgets"

tech-stack:
  added: []
  patterns:
    - "Dutch status terminology: Te doen (Open), Openstaand (Awaiting), Afgerond (Completed)"

key-files:
  created: []
  modified:
    - path: "src/pages/Todos/TodosList.jsx"
      type: "ui-component"
      changes: "Translated page title, filter tabs, headers, empty states, tooltips, due dates"
    - path: "src/components/Timeline/CompleteTodoModal.jsx"
      type: "ui-component"
      changes: "Already translated in 104-01 (no changes needed)"

decisions: []

tags: [translation, todos, ui, dutch, localization]
---

# Phase 104 Plan 02: Todos List & Modal Translation Summary

**One-liner:** Translated Todos list page to Dutch with status terms Te doen, Openstaand, Afgerond per CONTEXT.md

## What Was Built

### Translated TodosList.jsx

Completed full Dutch localization of the Todos list page:

**Page Structure:**
- Document title: `Todos` → `Taken`
- Page header: `Todos` → `Taken`
- Add button: `Add todo` → `Taak toevoegen`

**Filter Tabs (per CONTEXT.md terminology):**
- `Open` → `Te doen`
- `Awaiting` → `Openstaand`
- `Completed` → `Afgerond`
- `All` → `Alle`

**Section Headers:**
- `All todos` → `Alle taken`
- `Open todos` → `Te doen`
- `Awaiting response` → `Openstaand`
- `Completed todos` → `Afgeronde taken`

**Empty States:**
- `No todos found` → `Geen taken gevonden`
- `No open todos` → `Geen open taken`
- `No todos awaiting response` → `Geen openstaande taken`
- `No completed todos` → `Geen afgeronde taken`
- Empty hint: `Create todos from a person's detail page...` → `Maak taken aan vanaf een ledenpagina...`

**Action Tooltips:**
- `Reopen todo` → `Taak heropenen`
- `Mark as complete` → `Markeren als afgerond`
- `Complete todo` → `Taak afronden`
- `Edit todo` → `Taak bewerken`
- `Delete todo` → `Taak verwijderen`

**Due Date Display:**
- `Due:` → `Deadline:`
- `(overdue)` → `(te laat)`

**Awaiting Indicator:**
- `Waiting since today` → `Wacht sinds vandaag`
- `Waiting ${n}d` → `Wacht ${n}d`

**Confirmations & Errors:**
- Delete confirmation: `Are you sure...` → `Weet je zeker dat je deze taak wilt verwijderen?`
- Error message: `Failed to create activity...` → `Activiteit kon niet worden aangemaakt...`

### CompleteTodoModal Already Translated

During execution, discovered that `CompleteTodoModal.jsx` was already translated in plan 104-01 with the exact same Dutch text specified in this plan. No additional changes were needed.

**Modal content (already present):**
- Header: `Taak afronden`
- Question: `Wat is de status van deze taak?`
- Option 1: `Openstaand` (per CONTEXT.md - uses "Openstaand" instead of "Awaiting response")
- Option 2: `Afronden`
- Option 3: `Afronden & activiteit loggen`
- Cancel button: `Annuleren`

## Technical Implementation

### Status Terminology Consistency

All status terms follow CONTEXT.md mappings:
- **Te doen** for open/active todos
- **Openstaand** for awaiting response (not "Wachtend" or other variants)
- **Afgerond** for completed todos
- **Alle** for all/no filter

This matches the terminology established in the Important Dates phase.

### User Experience

The translated interface provides:
1. **Clear filter tabs** - Dutch status names make filtering intuitive
2. **Consistent empty states** - Each filter shows appropriate Dutch message
3. **Localized tooltips** - Hover states guide users in Dutch
4. **Natural date terminology** - "Deadline" instead of "Due date"
5. **Contextual indicators** - Overdue and waiting states in Dutch

## Deviations from Plan

### CompleteTodoModal Pre-Translated

**Deviation:** Task 2 required no work - the file was already translated.

**Discovery:** During execution, found that `CompleteTodoModal.jsx` was translated in plan 104-01 (commit fd5ac13).

**Impact:** No changes were needed. The file already contained all the Dutch translations specified in the plan with identical wording.

**Classification:** [Not a deviation - task prerequisite satisfied by prior work]

**Commits affected:** Only Task 1 generated a commit (b38925e).

## Verification Results

✅ **Build:** `npm run build` passed (2.59s)
✅ **Lint:** No new errors (pre-existing issues unrelated to this work)
✅ **Terminology:** All status terms follow CONTEXT.md (Te doen, Openstaand, Afgerond)
✅ **String search:** No English UI strings remain in TodosList.jsx
✅ **Modal:** CompleteTodoModal already fully translated

### Manual Verification Checklist

User should verify:
- [ ] Navigate to `/todos` - page title shows "Taken"
- [ ] Filter tabs show "Te doen", "Openstaand", "Afgerond", "Alle"
- [ ] Empty state shows "Geen open taken" (or appropriate message per filter)
- [ ] Hover over todo buttons shows Dutch tooltips
- [ ] Click complete on an open todo - modal shows "Taak afronden" header
- [ ] Modal options show "Openstaand" (not "Awaiting response")
- [ ] Delete confirmation shows Dutch text

## Next Phase Readiness

### For Phase 105 (Dashboard & Tijdlijn)

**Terminology established:**
- Todos are "Taken" throughout UI
- Status terms ready for dashboard widgets: Te doen, Openstaand, Afgerond
- Action terminology consistent (heropenen, bewerken, verwijderen)

**Patterns to follow:**
- Filter tab structure (rounded pill buttons with accent colors)
- Empty state messaging (Geen X gevonden)
- Tooltip wording (Taak + action verb)

### Blockers/Concerns

None - translation complete and verified.

## Files Changed

| File | Lines Changed | Type | Purpose |
|------|---------------|------|---------|
| src/pages/Todos/TodosList.jsx | 29 | modified | Translated todos list page to Dutch |
| src/components/Timeline/CompleteTodoModal.jsx | 0 | already-translated | No changes needed (translated in 104-01) |

## Commits

| Hash | Message | Files |
|------|---------|-------|
| b38925e | feat(104-02): translate TodosList to Dutch | TodosList.jsx |

**Note:** Only one commit generated because CompleteTodoModal was already translated.

## Performance Metrics

- **Planning time:** Included in plan creation
- **Execution time:** ~4 minutes
- **Files modified:** 1
- **Commits:** 1
- **Build time:** 2.59s

---

*Phase: 104-datums-taken*
*Plan: 02*
*Completed: 2026-01-25*
*Execution: Autonomous*
