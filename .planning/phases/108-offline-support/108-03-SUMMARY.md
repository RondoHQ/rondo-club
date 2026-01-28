---
phase: 108-offline-support
plan: 03
subsystem: frontend-offline
tags: [react, hooks, offline, forms, modals, ux]

requires:
  - phase: 108-offline-support
    plan: 01
    provides: useOnlineStatus hook

provides:
  - modals: All edit modals respect online status
  - protection: Forms disabled when offline
  - ux: Visual feedback via opacity and cursor styling

affects:
  - phase: 108-offline-support
    plan: 04
    note: Edit modals now properly disabled offline

tech-stack:
  added: []
  patterns:
    - "Offline state integration in form components"
    - "Consistent button disable pattern across modals"

key-files:
  created: []
  modified:
    - src/components/PersonEditModal.jsx
    - src/components/TeamEditModal.jsx
    - src/components/CommissieEditModal.jsx
    - src/components/ContactEditModal.jsx
    - src/components/WorkHistoryEditModal.jsx
    - src/components/AddressEditModal.jsx
    - src/components/RelationshipEditModal.jsx
    - src/components/ImportantDateModal.jsx
    - src/components/FeedbackModal.jsx
    - src/components/CustomFieldsEditModal.jsx

decisions:
  - title: "Disable buttons only, not all form inputs"
    rationale: "Prevents visual noise. Primary protection is submit/delete button disabling. Users can still view/edit data, but cannot save."
    alternatives: ["Disable all inputs", "Hide forms entirely"]
  - title: "Use opacity-50 cursor-not-allowed styling"
    rationale: "Provides clear visual feedback that action is unavailable without explicit text"
    alternatives: ["Show inline message", "Toast notification"]
  - title: "No inline messages in modals"
    rationale: "Per CONTEXT.md: 'banner is enough context' - OfflineBanner provides global context"
    alternatives: ["Add per-modal offline message"]

metrics:
  duration: "3m 19s"
  completed: "2026-01-28"
---

# Phase 108 Plan 03: Edit Modal Offline Protection Summary

**One-liner:** Integrated useOnlineStatus hook into all 10 edit modals to disable mutations when offline

## What Was Done

### Task 1: Major Entity Edit Modals
Added offline protection to the three primary entity edit modals:

**PersonEditModal:**
- Import useOnlineStatus hook
- Call hook at component top
- Disable submit button with `!isOnline || isLoading`
- Add opacity-50 cursor-not-allowed styling when offline

**TeamEditModal:**
- Same pattern as PersonEditModal
- Submit button disabled when offline

**CommissieEditModal:**
- Same pattern as PersonEditModal and TeamEditModal
- Submit button disabled when offline

**Pattern applied:**
```jsx
const isOnline = useOnlineStatus();
// ...
<button
  type="submit"
  className={`btn-primary ${!isOnline ? 'opacity-50 cursor-not-allowed' : ''}`}
  disabled={!isOnline || isLoading}
>
```

### Task 2: Secondary Edit Modals
Applied same offline protection pattern to 7 additional modals:

1. **ContactEditModal** - Email, phone, social contact editing
2. **WorkHistoryEditModal** - Work history entry editing
3. **AddressEditModal** - Address editing with country selector
4. **RelationshipEditModal** - Person relationship editing
5. **ImportantDateModal** - Important date creation/editing
6. **FeedbackModal** - Feedback submission (also disabled when uploading)
7. **CustomFieldsEditModal** - Custom field value editing

All modals now:
- Import and call useOnlineStatus hook
- Disable submit buttons when offline
- Apply visual feedback styling
- Respect existing isLoading states

## Technical Implementation

**Hook integration pattern:**
```javascript
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

export default function Modal({ isOpen, onClose, onSubmit, isLoading }) {
  const isOnline = useOnlineStatus();
  // ... rest of component
}
```

**Button disable pattern:**
```jsx
disabled={!isOnline || isLoading}
className={`btn-primary ${!isOnline ? 'opacity-50 cursor-not-allowed' : ''}`}
```

**Visual feedback:**
- `opacity-50` reduces button opacity by 50%
- `cursor-not-allowed` shows not-allowed cursor on hover
- No inline text messages (banner provides context)

## Verification

All verification criteria met:
- ✅ npm run build succeeds
- ✅ All 10 modals import useOnlineStatus
- ✅ Submit buttons include !isOnline in disabled condition
- ✅ Visual feedback via opacity-50 cursor-not-allowed
- ✅ No lint errors introduced

**Modified files:**
- 3 major entity modals (Person, Team, Commissie)
- 7 secondary modals (Contact, WorkHistory, Address, Relationship, ImportantDate, Feedback, CustomFields)

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Blockers:** None

**Concerns:** None

**Dependencies satisfied:**
- ✅ useOnlineStatus hook available (108-01)
- ✅ OfflineBanner provides user context (108-02)

**Ready for:**
- Plan 108-04: Action button offline protection
- Plan 108-05: Network error handling for mutations

## Key Learnings

1. **Consistent pattern application:** Using the same hook integration and styling pattern across all 10 modals ensures predictable UX
2. **Separation of concerns:** Banner handles messaging, modals handle state - clean division of responsibility
3. **Build verification:** Running npm run build after changes caught no issues - changes are production-ready

## Search Keywords

offline modals, edit forms offline, useOnlineStatus integration, form disable pattern, offline mutation protection, React hooks modal, PWA form handling, offline UX patterns
