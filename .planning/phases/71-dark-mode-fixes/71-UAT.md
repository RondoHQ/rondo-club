---
status: diagnosed
phase: 71-dark-mode-fixes
source: 71-01-SUMMARY.md, 71-02-SUMMARY.md
started: 2026-01-16T18:50:00Z
updated: 2026-01-16T19:00:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Work History Modal Dark Mode
expected: Open a person's Work History edit modal in dark mode. Modal should have dark background, proper border contrast, readable white/light text for headings and labels, and dark footer.
result: pass

### 2. Address Modal Dark Mode
expected: Open a person's Address edit modal in dark mode. Modal should have dark background, proper border contrast, readable white/light text, and dark footer.
result: pass

### 3. Settings Connections Subtab Buttons
expected: Go to Settings > Connections tab. In dark mode, the subtab buttons (Calendars, CardDAV, Slack) should be readable with proper contrast. Inactive tabs should have visible gray text (not too dark).
result: issue
reported: "No, the active button is still hard to read"
severity: major

### 4. Timeline Activity Type Labels
expected: View a person's Timeline tab in dark mode. Activity icons, type labels (e.g., "Meeting", "Note"), dates, and any buttons should be readable with proper contrast.
result: issue
reported: "No, the active activity type is still hard to read."
severity: major

### 5. Important Date Modal People Badges
expected: Open an Important Date modal that has related people selected. In dark mode, the people badges should be readable with proper text contrast on the accent-colored background.
result: issue
reported: "No, the contrast is way too low as the background is too light"
severity: major

## Summary

total: 5
passed: 2
issues: 3
pending: 0
skipped: 0

## Gaps

- truth: "Settings Connections subtab active button is readable in dark mode"
  status: failed
  reason: "User reported: No, the active button is still hard to read"
  severity: major
  test: 3
  root_cause: "Active subtab button uses dark:text-accent-400 on dark:bg-accent-900/30 background - accent-400 is too dark for this semi-transparent background"
  artifacts:
    - path: "src/pages/Settings/Settings.jsx"
      line: 1566
      issue: "dark:text-accent-400 has insufficient contrast on dark:bg-accent-900/30"
  missing:
    - "Change dark:text-accent-400 to dark:text-accent-200 for better contrast"

- truth: "Timeline activity type labels are readable in dark mode"
  status: failed
  reason: "User reported: No, the active activity type is still hard to read."
  severity: major
  test: 4
  root_cause: "QuickActivityModal selected type button uses dark:text-accent-300 on dark:bg-accent-900/30 background - accent-300 is too dark for this semi-transparent background"
  artifacts:
    - path: "src/components/Timeline/QuickActivityModal.jsx"
      line: 166
      issue: "dark:text-accent-300 has insufficient contrast on dark:bg-accent-900/30"
  missing:
    - "Change dark:text-accent-300 to dark:text-accent-200 for better contrast"

- truth: "Important Date Modal people badges have proper contrast in dark mode"
  status: failed
  reason: "User reported: No, the contrast is way too low as the background is too light"
  severity: major
  test: 5
  root_cause: "People badges use dark:bg-accent-900/30 which is too transparent (light) in dark mode - the 30% opacity doesn't create enough background contrast for text readability"
  artifacts:
    - path: "src/components/ImportantDateModal.jsx"
      line: 43
      issue: "dark:bg-accent-900/30 is too transparent, making text (dark:text-accent-200) hard to read"
  missing:
    - "Change dark:bg-accent-900/30 to dark:bg-accent-900/50 or dark:bg-accent-800 for darker background"
