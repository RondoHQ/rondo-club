---
status: verifying
trigger: "All dropdowns across the site (feedback, tuchtzaken, possibly others) immediately close after being opened. User suspects this is caused by a recent change."
created: 2026-02-16T10:00:00Z
updated: 2026-02-16T10:45:00Z
---

## Current Focus

hypothesis: TabButton component (introduced in quick-72) is missing type="button", defaulting to type="submit", which triggers form submission and disrupts page interactions
test: Check TabButton.jsx for missing type attribute and verify it's used inside form elements
expecting: TabButton is missing type="button" and is used inside <form> on FinanceSettings.jsx
next_action: Verify theory, then fix TabButton and test on production

## Symptoms

expected: Dropdowns stay open when clicked, allowing the user to select an option
actual: Dropdowns snap shut immediately after being clicked/opened
errors: None reported
reproduction: Click any dropdown on /feedback or /tuchtzaken pages — it immediately closes
started: After recent changes (Quick tasks 72-74)

## Eliminated

## Evidence

- timestamp: 2026-02-16T10:05:00Z
  checked: Recent commits (git log, git diff)
  found: Quick task 72 (d31e152b) introduced TabButton component and tabs to FinanceSettings.jsx
  implication: New component may have introduced a bug

- timestamp: 2026-02-16T10:10:00Z
  checked: TabButton.jsx component code
  found: TabButton renders a <button> without type attribute (line 21)
  implication: In HTML forms, buttons default to type="submit" when type is not specified

- timestamp: 2026-02-16T10:12:00Z
  checked: FinanceSettings.jsx structure
  found: TabButton is used inside <form onSubmit={handleSubmit}> (line 308-318)
  implication: Clicking a tab triggers form submission, which could disrupt page state/focus

- timestamp: 2026-02-16T10:15:00Z
  checked: User symptoms (dropdown issue on /feedback and /tuchtzaken)
  found: These pages don't use TabButton, but issue is site-wide
  implication: Form submission or state disruption may have global side effects (page re-render, focus loss, event propagation)

- timestamp: 2026-02-16T10:25:00Z
  checked: Commit c669a724 (auto-focus feature)
  found: Added mainRef.current?.focus() on every route change (useEffect with location.pathname dependency)
  implication: Focus is programmatically moved to main element, potentially stealing focus from dropdowns

## Resolution

root_cause: Auto-focus feature (commit c669a724) programmatically focuses the main element on route changes using mainRef.current?.focus(). While the useEffect dependency is [location.pathname], something is triggering this effect or causing focus loss that closes dropdowns. Secondary issue: TabButton component missing type="button" causes form submission in Finance Settings.
fix:
1. Removed auto-focus feature from Layout.jsx (mainRef, useEffect, tabIndex, focus classes)
2. Added type="button" to TabButton.jsx
verification: Deploy to production and test dropdowns on /feedback, /tuchtzaken, and /financien/instellingen pages
files_changed: [src/components/layout/Layout.jsx, src/components/TabButton.jsx]
