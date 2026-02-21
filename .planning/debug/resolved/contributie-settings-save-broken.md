---
status: resolved
trigger: "When adding a Contributie categorie on /financien/instellingen, filling out the form and clicking 'Opslaan' does nothing — user gets redirected to the first tab 'Organisatie' with nothing saved. No PUT or POST requests are even visible in the network tab."
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:00:00Z
---

## Current Focus

hypothesis: RESOLVED
next_action: done

## Symptoms

expected: Filling out a Contributie categorie form and clicking "Opslaan" should save the data via a REST API call
actual: Clicking "Opslaan" redirects to the first tab "Organisatie", nothing is saved, and no PUT or POST requests are made
errors: No visible errors — the form just silently fails
reproduction: Go to /financien/instellingen, switch to the Contributie tab, fill out a new category form, click "Opslaan"
started: Unknown — user reporting now

## Eliminated

(none needed — root cause found directly)

## Evidence

- timestamp: 2026-02-22
  checked: FinanceSettings.jsx line 354
  found: Entire page wrapped in <form onSubmit={handleSubmit}>
  implication: Any submit button anywhere in the page will trigger this outer form

- timestamp: 2026-02-22
  checked: FinanceSettings.jsx line 1104-1105
  found: FeeCategorySettings is rendered inside the outer <form> when activeTab === 'contributie'
  implication: All buttons inside FeeCategorySettings are inside the outer form

- timestamp: 2026-02-22
  checked: FeeCategorySettings.jsx line 178 (EditCategoryForm)
  found: EditCategoryForm renders as <form onSubmit={handleSubmit} ...> — a nested form inside FinanceSettings' outer form
  implication: Nested <form> elements are invalid HTML; browser ignores the inner form tag; inner onSubmit never fires; type="submit" button submits the outer form instead

- timestamp: 2026-02-22
  checked: FinanceSettings.jsx line 169
  found: const [activeTab, setActiveTab] = useState('organization')
  implication: When FinanceSettings component re-renders or remounts (e.g., after outer form submit triggers state change), activeTab resets to 'organization' — explaining the redirect behavior

## Resolution

root_cause: EditCategoryForm (in FeeCategorySettings.jsx) rendered a <form> element nested inside the parent <form> in FinanceSettings.jsx. HTML does not allow nested forms — browsers strip the inner <form> element, so the inner onSubmit handler never fired. Clicking the "Opslaan" type="submit" button submitted the outer FinanceSettings form instead, which reset activeTab state to 'organization' (the useState default).
fix: Converted EditCategoryForm from <form> to <div> and changed its submit button from type="submit" to type="button" with onClick={handleSubmit}. Also added explicit type="button" to all other buttons in FeeCategorySettings that were missing it, preventing accidental outer form submission.
verification: Build succeeded. Deployed to production. Commit 5dcf2d09.
files_changed:
  - src/pages/Settings/FeeCategorySettings.jsx
