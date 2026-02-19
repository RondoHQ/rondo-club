---
phase: quick-103
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Finance/FinanceSettings.jsx
  - src/pages/Contributie/Contributie.jsx
autonomous: true
must_haves:
  truths:
    - "Financien -> Instellingen has a Contributie tab showing FeeCategorySettings"
    - "E-mail tab shows one template at a time via sub-tabs (Boetes, Contributie, Termijnen, Herinneringen)"
    - "Contributie page no longer has an Instellingen tab"
    - "All existing email template editors and save functionality still work"
  artifacts:
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "Updated with Contributie tab and E-mail sub-tabs"
    - path: "src/pages/Contributie/Contributie.jsx"
      provides: "Instellingen tab removed"
  key_links:
    - from: "FinanceSettings.jsx contributie tab"
      to: "src/pages/Settings/FeeCategorySettings.jsx"
      via: "import and conditional render"
      pattern: "FeeCategorySettings"
    - from: "FinanceSettings.jsx email sub-tabs"
      to: "formData email template fields"
      via: "emailSubTab state controlling which card is shown"
      pattern: "emailSubTab"
---

<objective>
Move FeeCategorySettings from Contributie -> Instellingen into a new Contributie tab in FinanceSettings, and replace the stacked E-mail template cards with sub-tabs.

Purpose: Consolidates all financial settings into one location (Financien -> Instellingen) and makes the E-mail tab less overwhelming by showing one template at a time.
Output: Updated FinanceSettings.jsx and Contributie.jsx.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add Contributie tab to FinanceSettings</name>
  <files>src/pages/Finance/FinanceSettings.jsx</files>
  <action>
    In src/pages/Finance/FinanceSettings.jsx:

    1. Add import at the top: `import FeeCategorySettings from '@/pages/Settings/FeeCategorySettings';`

    2. Add to the TABS array after 'mollie': `{ id: 'contributie', label: 'Contributie' }`
       Final order: organization, payment, email, rabobank, mollie, contributie

    3. After the mollie tab content block (after line `{activeTab === 'mollie' && <div ...>...</div>}`), add:
    ```jsx
    {/* Section 6: Fee Category Settings */}
    {activeTab === 'contributie' && <FeeCategorySettings />}
    ```

    Note: FeeCategorySettings is self-contained — it has its own data fetching and save logic. Do NOT wrap it in the form's save button or include it in handleSubmit. The tab simply renders the component standalone, exactly as Contributie.jsx currently does.
  </action>
  <verify>npm run lint passes. Visually: navigate to Financien -> Instellingen, confirm Contributie tab appears and shows the fee category settings UI.</verify>
  <done>Contributie tab is visible in FinanceSettings and renders FeeCategorySettings correctly without breaking the form save for other tabs.</done>
</task>

<task type="auto">
  <name>Task 2: Add E-mail sub-tabs in FinanceSettings</name>
  <files>src/pages/Finance/FinanceSettings.jsx</files>
  <action>
    In src/pages/Finance/FinanceSettings.jsx, within the email tab section (`{activeTab === 'email' && ...}`):

    1. Add local state at the top of the FinanceSettings component (near other useState declarations):
    ```jsx
    const [emailSubTab, setEmailSubTab] = useState('boetes');
    ```

    2. Define sub-tabs constant outside the component (near TABS):
    ```jsx
    const EMAIL_SUB_TABS = [
      { id: 'boetes', label: 'Boetes' },
      { id: 'contributie', label: 'Contributie' },
      { id: 'termijnen', label: 'Termijnen' },
      { id: 'herinneringen', label: 'Herinneringen' },
    ];
    ```

    3. Replace the email tab content (currently `{activeTab === 'email' && <div className="space-y-6">...all 4 cards...</div>}`) with:
    ```jsx
    {activeTab === 'email' && (
      <div className="space-y-4">
        {/* Sub-tab navigation */}
        <div className="flex gap-2 flex-wrap">
          {EMAIL_SUB_TABS.map(sub => (
            <button
              key={sub.id}
              type="button"
              onClick={() => setEmailSubTab(sub.id)}
              className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                emailSubTab === sub.id
                  ? 'bg-electric-cyan text-white'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
              }`}
            >
              {sub.label}
            </button>
          ))}
        </div>

        {/* Boetes template */}
        {emailSubTab === 'boetes' && <div className="card p-6">...existing boetes card content...</div>}

        {/* Contributie template */}
        {emailSubTab === 'contributie' && <div className="card p-6">...existing contributie card content...</div>}

        {/* Termijnen template */}
        {emailSubTab === 'termijnen' && <div className="card p-6">...existing termijnbetaling card content...</div>}

        {/* Herinneringen template */}
        {emailSubTab === 'herinneringen' && <div className="card p-6">...existing herinneringen card content with both reminder 1 and reminder 2...</div>}
      </div>
    )}
    ```

    Move each existing card's full JSX content (including the variable docs box) into the matching sub-tab. The herinneringen card already contains both reminder 1 and reminder 2 editors — keep them together in the herinneringen sub-tab.

    The formData bindings (email_template, membership_email_template, installment_email_template, reminder_1_email_template, reminder_2_email_template) remain unchanged — only the display is gated by emailSubTab.
  </action>
  <verify>npm run lint passes. Visually: E-mail tab shows 4 pill buttons. Clicking each shows only that template card. Editing and saving still works (all fields included in handleSubmit payload).</verify>
  <done>E-mail tab shows one template at a time controlled by pill sub-tabs. All 5 template fields still save correctly.</done>
</task>

<task type="auto">
  <name>Task 3: Remove Instellingen tab from Contributie</name>
  <files>src/pages/Contributie/Contributie.jsx</files>
  <action>
    In src/pages/Contributie/Contributie.jsx:

    1. Remove the import: `import FeeCategorySettings from '../Settings/FeeCategorySettings';`

    2. Remove from TABS array: `{ id: 'instellingen', label: 'Instellingen', adminOnly: true }`

    3. Remove the redirect guard block:
    ```jsx
    // Non-admin navigating to instellingen → redirect to overzicht
    if (activeTab === 'instellingen' && !isAdmin) {
      return <Navigate to="/financien/contributie/overzicht" replace />;
    }
    ```

    4. Remove the tab content render: `{activeTab === 'instellingen' && isAdmin && <FeeCategorySettings />}`

    5. Keep the `useFeeSummary` import and `billingMethod` — still used for nog-te-factureren guard.

    6. If the `Navigate` import from react-router-dom is only used for the instellingen redirect (check: is it still used for the nog-te-factureren redirect?), keep it — the nog-te-factureren redirect also uses Navigate.
  </action>
  <verify>npm run lint passes. Visually: Contributie page shows only Overzicht, Per lid (and Nog te factureren for admin with nikki billing). No Instellingen tab.</verify>
  <done>Instellingen tab is gone from Contributie. No unused imports remain. nog-te-factureren redirect still works.</done>
</task>

</tasks>

<verification>
Run `npm run lint` — must pass with 0 warnings.
Run `npm run build` — must complete without errors.
Navigate to Financien -> Instellingen: confirm Contributie tab exists and renders fee categories.
Navigate to Financien -> Instellingen -> E-mail: confirm 4 pill sub-tabs, each showing one template card.
Navigate to Contributie: confirm no Instellingen tab is visible (even as admin).
</verification>

<success_criteria>
- FinanceSettings has 6 tabs: Organisatie, Betaling, E-mail, Rabobank, Mollie, Contributie
- Contributie tab in FinanceSettings renders FeeCategorySettings identically to how Contributie page used to
- E-mail tab shows pill sub-tabs (Boetes, Contributie, Termijnen, Herinneringen) instead of 4 stacked cards
- Contributie page has 2-3 tabs: Overzicht, Per lid (and Nog te factureren if applicable)
- All saves work correctly, lint and build pass
</success_criteria>

<output>
After completion, create `.planning/quick/103-move-contributie-instellingen-to-financi/103-SUMMARY.md`
</output>
