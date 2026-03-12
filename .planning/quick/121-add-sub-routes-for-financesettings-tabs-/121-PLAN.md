---
phase: quick-121
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Finance/FinanceSettings.jsx
  - src/pages/Settings/Settings.jsx
  - src/pages/Clothing/ClothingPage.jsx
  - src/router.jsx
autonomous: true
requirements: [URL-TABS]
must_haves:
  truths:
    - "Navigating to /settings/financieel/betaling shows the Betaling tab directly"
    - "Navigating to /settings/financieel/email shows the E-mail tab directly"
    - "Navigating to /settings/financieel without a subtab defaults to the first available tab (factuur)"
    - "Navigating to /kleding/items shows the Items tab directly"
    - "Navigating to /kleding without a tab defaults to overview"
    - "Clicking a tab in FinanceSettings updates the URL to include the subtab"
    - "Clicking a tab in ClothingPage updates the URL to include the tab"
    - "Browser back/forward navigation works correctly with tab URLs"
    - "Email sub-tabs within the email tab do not need their own URL (pills, not navigation)"
  artifacts:
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "URL-driven tab switching via props from parent"
    - path: "src/pages/Settings/Settings.jsx"
      provides: "Passes subtab to FinanceSettings, handles financieel subtab navigation"
    - path: "src/pages/Clothing/ClothingPage.jsx"
      provides: "URL-driven tab switching via useParams"
    - path: "src/router.jsx"
      provides: "Route for /kleding/:tab"
  key_links:
    - from: "src/pages/Settings/Settings.jsx"
      to: "src/pages/Finance/FinanceSettings.jsx"
      via: "activeTab/onTabChange props"
      pattern: "activeTab.*subtab"
---

<objective>
Add URL-based sub-routes for all settings pages that use tabs, so each tab has its own bookmarkable URL.

Purpose: Users can share direct links to specific settings tabs and use browser back/forward to navigate between tabs. Currently FinanceSettings and ClothingPage use `useState` for tabs which loses state on refresh and is not shareable.

Output: URL-driven tab navigation for FinanceSettings (via Settings subtab param) and ClothingPage (via route param).
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/Finance/FinanceSettings.jsx
@src/pages/Settings/Settings.jsx
@src/pages/Clothing/ClothingPage.jsx
@src/router.jsx
@src/components/TabButton.jsx

<interfaces>
<!-- Settings already supports /:tab/:subtab URL params -->
From src/pages/Settings/Settings.jsx:
```javascript
const { tab: urlTab, subtab: urlSubtab } = useParams();
const activeTab = urlTab || 'appearance';
const activeSubtab = urlSubtab || (activeTab === 'admin' ? 'users' : activeTab === 'connections' ? 'carddav' : null);
```

From src/pages/Finance/FinanceSettings.jsx:
```javascript
// Current: internal state management (needs to become prop-driven)
export default function FinanceSettings({ initialTab = 'organization', allowedTabs = null })
const [activeTab, setActiveTab] = useState(...)

const TABS = [
  { id: 'organization', label: 'Factuur' },
  { id: 'payment', label: 'Betaling' },
  { id: 'discipline', label: 'Tuchtzaken' },
  { id: 'contributie', label: 'Contributie' },
  { id: 'email', label: 'E-mail' },
  { id: 'membership_passes', label: 'Wallets' },
  { id: 'mollie', label: 'Mollie' },
  { id: 'rabobank', label: 'Rabobank' },
];
```

From src/router.jsx:
```javascript
// Already exists:
{ path: 'settings/:tab', element: <Settings /> },
{ path: 'settings/:tab/:subtab', element: <Settings /> },
// ClothingPage currently has no :tab route:
{ path: 'kleding', element: <ClothingRoute><ClothingPage /></ClothingRoute> },
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Wire FinanceSettings tabs to URL subtab parameter</name>
  <files>src/pages/Finance/FinanceSettings.jsx, src/pages/Settings/Settings.jsx</files>
  <action>
**In Settings.jsx:**

1. Update the `activeSubtab` default logic to include financieel:
   ```javascript
   const activeSubtab = urlSubtab || (
     activeTab === 'admin' ? 'users' :
     activeTab === 'connections' ? 'carddav' :
     activeTab === 'financieel' ? 'organization' :
     null
   );
   ```

2. Update `setActiveTab` to handle financieel subtab navigation:
   - When switching to the financieel tab without a subtab, default to `organization`
   - Add: `} else if (tab === 'financieel') { navigate(\`/settings/${tab}/organization\`); }`

3. Where FinanceSettings is rendered (case 'financieel' around line 469-472), pass the subtab and a change handler:
   ```jsx
   <FinanceSettings
     allowedTabs={['organization', 'payment', 'discipline', 'contributie', 'email']}
     activeTab={activeSubtab}
     onTabChange={(tabId) => navigate(`/settings/financieel/${tabId}`)}
   />
   ```

4. For the `PaymentProvidersSubtab` (line ~1842) and `WalletsSubtab` (line ~1856) usages of FinanceSettings, these render within the connections tab context and only show 1-2 tabs, so keep using the existing `initialTab` prop (no URL routing needed for these — they are already at a specific URL subtab).

**In FinanceSettings.jsx:**

1. Update the component signature to accept optional `activeTab` and `onTabChange` props:
   ```javascript
   export default function FinanceSettings({ initialTab = 'organization', allowedTabs = null, activeTab: propActiveTab = null, onTabChange = null })
   ```

2. Change the internal `activeTab` state to be controlled when props are provided:
   - If `propActiveTab` is provided and valid (in `availableTabs`), use it as the active tab
   - If `propActiveTab` is not in `availableTabs`, fall back to first available tab
   - When `onTabChange` is provided, call it instead of `setActiveTab` for internal tab clicks
   - Keep the internal `useState` as fallback for cases where FinanceSettings is used without URL control (payment-providers, wallets subtabs)

   Specifically, replace the current state logic (lines 282-294):
   ```javascript
   // Controlled mode (URL-driven) vs uncontrolled mode (internal state)
   const [internalTab, setInternalTab] = useState(
     availableTabs.some((tab) => tab.id === initialTab) ? initialTab : availableTabs[0]?.id || 'organization'
   );

   const activeTab = propActiveTab && availableTabs.some((tab) => tab.id === propActiveTab)
     ? propActiveTab
     : propActiveTab ? (availableTabs[0]?.id || 'organization')  // propActiveTab invalid, fallback
     : internalTab;  // uncontrolled mode

   const handleTabChange = (tabId) => {
     if (onTabChange) {
       onTabChange(tabId);
     } else {
       setInternalTab(tabId);
     }
   };
   ```

3. Replace all `setActiveTab(tab.id)` calls in the tab button onClick with `handleTabChange(tab.id)` (line ~606).

4. Remove the `useEffect` that validated activeTab (lines 290-294) — this is now handled by the controlled/uncontrolled logic above.

5. Keep `emailSubTab` as internal state — email sub-tabs are secondary "pill" navigation within the email tab, not primary navigation tabs. They don't need URL routing.

6. Remove the `useSearchParams` import and `searchParams`/`setSearchParams` usage IF no longer needed. Check: the Rabobank OAuth callback uses `searchParams` (lines 373-387). This is still needed — it reads `?rabobank=success` from the OAuth redirect. Keep `useSearchParams` for this purpose only.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build 2>&1 | tail -5</automated>
  </verify>
  <done>
    - /settings/financieel loads with first tab (organization/factuur) active
    - /settings/financieel/betaling loads with Betaling tab active
    - /settings/financieel/email loads with E-mail tab active
    - Clicking between tabs updates the URL path
    - Payment-providers and wallets subtabs within connections still work (uncontrolled mode)
    - Rabobank OAuth callback still works (searchParams preserved)
  </done>
</task>

<task type="auto">
  <name>Task 2: Add URL-based tabs to ClothingPage</name>
  <files>src/pages/Clothing/ClothingPage.jsx, src/router.jsx</files>
  <action>
**In router.jsx:**

Add a `/kleding/:tab` route alongside the existing `/kleding` route (inside the ClothingRoute wrapper):
```jsx
{
  path: 'kleding/:tab',
  element: (
    <ClothingRoute>
      <ClothingPage />
    </ClothingRoute>
  ),
},
```
Place this BEFORE the existing `/kleding` route (more specific route first), or right after it — React Router v6 handles specificity automatically, but convention is specific-first.

**In ClothingPage.jsx:**

1. Add `useParams` and `useNavigate` imports from `react-router-dom` (check what's already imported).

2. Replace the `useState` tab management:
   ```javascript
   // Before:
   const [activeTab, setActiveTab] = useState('overview');

   // After:
   const { tab } = useParams();
   const navigate = useNavigate();
   const activeTab = tab || 'overview';
   ```

3. Validate the tab — if unknown tab, redirect:
   ```javascript
   const TABS = [
     { id: 'overview', label: 'Overzicht' },
     { id: 'items', label: 'Items' },
     { id: 'transactions', label: 'Transacties' },
   ];
   const isKnownTab = TABS.some(t => t.id === activeTab);
   if (!isKnownTab) {
     return <Navigate to="/kleding/overview" replace />;
   }
   ```
   Add `Navigate` to the react-router-dom imports if not already present.

4. Update the TabButton onClick handlers from `setActiveTab('overview')` to `navigate('/kleding/overview')`, etc. Following the pattern from VOG.jsx:
   ```jsx
   <TabButton label={t.label} isActive={activeTab === t.id} onClick={() => navigate(`/kleding/${t.id}`)} />
   ```

5. Extract the TABS constant to the module level if it's currently inside the component (check current structure — the tabs are likely inline in JSX around line 290).
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build 2>&1 | tail -5 && npm run lint 2>&1 | tail -5</automated>
  </verify>
  <done>
    - /kleding loads with overview tab active
    - /kleding/items loads with Items tab active
    - /kleding/transactions loads with Transacties tab active
    - /kleding/invalid redirects to /kleding/overview
    - Clicking between tabs updates the URL path
    - Build and lint pass with no errors
  </done>
</task>

</tasks>

<verification>
1. `npm run build` completes without errors
2. `npm run lint` passes with 0 warnings
3. Navigate to /settings/financieel — defaults to first tab, URL shows /settings/financieel/organization
4. Navigate to /settings/financieel/email — shows E-mail tab
5. Click between FinanceSettings tabs — URL updates accordingly
6. Navigate to /kleding — defaults to overview tab
7. Navigate to /kleding/items — shows Items tab
8. Browser back/forward works for tab navigation
9. /settings/connections/payment-providers still works (FinanceSettings uncontrolled mode)
</verification>

<success_criteria>
All settings pages with tabs use URL-based routing. Each tab has a unique, bookmarkable URL. Browser navigation (back/forward) works correctly with tabs. Build and lint pass clean.
</success_criteria>

<output>
After completion, create `.planning/quick/121-add-sub-routes-for-financesettings-tabs-/121-SUMMARY.md`
</output>
