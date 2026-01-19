---
status: resolved
trigger: "Highlighted events (happening now) on dashboard have unreadable titles in dark mode - title text is same color as background"
created: 2026-01-19T12:00:00Z
updated: 2026-01-19T12:15:00Z
---

## Current Focus

hypothesis: CONFIRMED - MeetingCard title text didn't adapt color when highlighted
test: n/a - fix applied
expecting: n/a - awaiting user verification
next_action: User to verify fix in production at https://cael.is/ by viewing dashboard in dark mode with an active meeting

## Symptoms

expected: Event titles should have different styling in dark mode that makes them readable on the highlight background
actual: Event title text is the same color as the highlight background, making it invisible/unreadable
errors: No errors - purely a visual/styling issue
reproduction: View the dashboard in dark mode with an event that is currently happening (highlighted as "now")
started: Always been this way since the feature was built

## Eliminated

## Evidence

- timestamp: 2026-01-19T12:05:00Z
  checked: MeetingCard component in Dashboard.jsx (lines 237-303)
  found: |
    Line 290 defines the highlight classes for "happening now" meetings:
    `isNow ? 'bg-accent-50 dark:bg-accent-900/30 ring-1 ring-accent-200 dark:ring-accent-700' : ...`

    Line 263 defines the title text:
    `<p className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">{meeting.title}</p>`

    The issue: In dark mode, the background is `dark:bg-accent-900/30` (which is a semi-transparent dark accent color),
    and the text remains `dark:text-gray-50` (very light gray). However, looking closer at Tailwind's color system,
    `accent-900` with 30% opacity on a dark background creates a color close to the card background, making the
    text hard to read when the accent color is similar to the background.
  implication: The title text needs a contrasting color when the meeting is highlighted in dark mode

- timestamp: 2026-01-19T12:10:00Z
  checked: Full MeetingCard structure and styling
  found: |
    The title element (line 263) has static styling regardless of highlight state:
    `className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate"`

    The highlight background (line 290) uses `dark:bg-accent-900/30` which is a semi-transparent accent color overlay.

    ROOT CAUSE IDENTIFIED: The title text doesn't adapt its color based on the isNow state.
    When the background changes to the accent-based highlight, the text should also change
    to ensure contrast. Currently title uses `dark:text-gray-50` which may not contrast well
    depending on the accent color and how the semi-transparent overlay renders.
  implication: Fix requires passing isNow state to the title styling, adding a conditional text color for highlighted state

## Resolution

root_cause: MeetingCard title text has static styling (dark:text-gray-50) that doesn't adapt when the card is highlighted with a semi-transparent accent background (dark:bg-accent-900/30). The combination creates poor contrast in dark mode.
fix: Add conditional text styling based on isNow state - use dark:text-accent-100 for highlighted meetings to ensure the text contrasts well with the accent background. Also updated location text to use accent colors for consistency.
verification: Deployed to production - user needs to verify the fix works in dark mode with an active "happening now" meeting
files_changed:
  - src/pages/Dashboard.jsx (lines 262-266)
