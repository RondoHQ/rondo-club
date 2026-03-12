# T02: 213-sitewide-rollout 02

**Slice:** S02 — **Milestone:** M001

## Description

Apply correct button tier hierarchy to all modal dialogs (22 files).

Purpose: Modal dialogs are the most consistent pattern — Save=primary, Cancel=secondary. Most modals already follow this but need cleanup of redundant Tailwind classes. DeleteFieldDialog has inline red styles that must become btn-danger.

Output: All 22 modal/dialog files using clean btn-* classes with no redundant inline styles.

## Must-Haves

- [ ] "In every modal dialog, Save/Submit uses btn-primary and Cancel/Close uses btn-secondary"
- [ ] "Delete/Remove actions in modals and dialogs use btn-danger"
- [ ] "No redundant inline-flex/items-center/px-4/py-2/rounded-lg classes remain alongside btn-* classes"

## Files

- `src/components/AddressEditModal.jsx`
- `src/components/ContactEditModal.jsx`
- `src/components/PersonEditModal.jsx`
- `src/components/RelationshipEditModal.jsx`
- `src/components/WorkHistoryEditModal.jsx`
- `src/components/CustomFieldsEditModal.jsx`
- `src/components/CommissieEditModal.jsx`
- `src/components/TeamEditModal.jsx`
- `src/components/FeedbackModal.jsx`
- `src/components/FeedbackEditModal.jsx`
- `src/components/DashboardCustomizeModal.jsx`
- `src/components/ShareModal.jsx`
- `src/components/DeleteFieldDialog.jsx`
- `src/components/MeetingDetailModal.jsx`
- `src/components/Timeline/GlobalTodoModal.jsx`
- `src/components/Timeline/TodoModal.jsx`
- `src/components/Timeline/CompleteTodoModal.jsx`
- `src/components/Timeline/NoteModal.jsx`
- `src/components/Timeline/QuickActivityModal.jsx`
- `src/components/FieldFormPanel.jsx`
- `src/components/AccountCard.jsx`
- `src/pages/People/ColumnSettingsModal.jsx`
