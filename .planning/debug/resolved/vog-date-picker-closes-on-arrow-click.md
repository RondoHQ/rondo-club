---
status: resolved
trigger: "vog-date-picker-closes-on-arrow-click"
created: 2026-02-12T10:00:00Z
updated: 2026-02-12T10:10:00Z
---

## Current Focus

hypothesis: CONFIRMED - onBlur handler at lines 125 and 157 fires when clicking date picker arrows
test: remove onBlur handlers, use onChange to close editing
expecting: date picker navigation will work without closing
next_action: implement fix - remove onBlur, close editing after onChange

## Symptoms

expected: Clicking month/year arrows in the native browser date picker should navigate between months while keeping the date input open
actual: Clicking the arrows closes the editing mode, reverting back to the read-only display text
errors: No console errors
reproduction: Go to any volunteer's PersonDetail page where VOG is missing/expired. Click "Nog niet" or the date next to "E-mail verzonden" or "Justis aanvraag". The date input appears, but clicking the month navigation arrows in the date picker popup closes the input.
started: Just introduced in quick task 63 (commit 2add0f3a)

## Eliminated

## Evidence

- timestamp: 2026-02-12T10:01:00Z
  checked: VOGCard.jsx lines 112-127 and 144-159
  found: Both date inputs have `onBlur={() => setEditingField(null)}` handlers
  implication: When clicking date picker arrows, focus moves away from input, blur fires, editing mode closes

- timestamp: 2026-02-12T10:02:00Z
  checked: onChange handlers at lines 119-124 and 151-156
  found: Both already call `setEditingField(null)` after updating the field
  implication: onBlur is redundant - onChange already closes editing mode after saving

## Resolution

root_cause: The date inputs have onBlur handlers (lines 125, 157) that call setEditingField(null) when focus leaves the input. When users click the month/year navigation arrows in the native browser date picker, the browser's date picker controls receive focus (they are technically separate DOM elements), triggering blur on the input. This immediately closes editing mode before the user can select a date.

fix: Remove the onBlur handlers. The onChange handlers already close editing mode after saving the value, so onBlur is redundant. Users can navigate the date picker freely, and editing mode closes automatically once they select a date.

verification: Deployed to production. Fix tested - date picker navigation arrows now work correctly. Users can navigate months/years without editing mode closing. Editing mode only closes after selecting a date (via onChange).
files_changed: ['src/components/VOGCard.jsx']
