---
phase: quick-024
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/layout/Layout.jsx
autonomous: true

must_haves:
  truths:
    - "Arrow keys scroll the page immediately after navigation without clicking"
    - "Page Up/Down and Spacebar work for scrolling without clicking first"
    - "Focus behavior does not interfere with interactive elements (inputs, buttons)"
  artifacts:
    - path: "src/components/layout/Layout.jsx"
      provides: "Auto-focus main element on route change"
      contains: "mainRef"
  key_links:
    - from: "src/components/layout/Layout.jsx"
      to: "useLocation"
      via: "useEffect dependency"
      pattern: "useEffect.*location"
---

<objective>
Add auto-focus to the main content area so keyboard scrolling (arrow keys, Page Up/Down, Spacebar) works immediately after page load and navigation, without requiring the user to click first.

Purpose: Improve keyboard navigation UX - currently users must click on the page before keyboard scrolling works.
Output: Modified Layout.jsx with focus management on the main element.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@src/components/layout/Layout.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add auto-focus to main element on route changes</name>
  <files>src/components/layout/Layout.jsx</files>
  <action>
Modify the Layout component to auto-focus the main element:

1. Import `useRef` (already imported on line 1) and `useLocation` from react-router-dom (add to existing import on line 2)

2. In the Layout function component (starts at line 530):
   - Add a ref: `const mainRef = useRef(null);`
   - Get current location: `const location = useLocation();`
   - Add useEffect to focus main on route changes:
     ```javascript
     // Focus main element on route change for keyboard scrolling
     useEffect(() => {
       // Small delay to ensure content is rendered
       const timer = setTimeout(() => {
         mainRef.current?.focus();
       }, 0);
       return () => clearTimeout(timer);
     }, [location.pathname]);
     ```

3. Update the main element (line 585) to add ref and tabIndex:
   - Change from: `<main className="flex-1 overflow-y-auto p-4 lg:p-6 [overscroll-behavior-y:none]">`
   - Change to: `<main ref={mainRef} tabIndex={-1} className="flex-1 overflow-y-auto p-4 lg:p-6 [overscroll-behavior-y:none] focus:outline-none">`

   Note: tabIndex={-1} makes element focusable programmatically but not via Tab key.
   The focus:outline-none prevents a visible focus ring on the main content area.
  </action>
  <verify>
1. Run `npm run build` to verify no build errors
2. Run `npm run lint` to verify no lint errors
3. Manual test: Navigate to different pages and verify arrow keys scroll immediately
  </verify>
  <done>
- Layout.jsx has mainRef attached to main element
- main element has tabIndex={-1} and focus:outline-none
- useEffect focuses main on location.pathname changes
- Build and lint pass
  </done>
</task>

</tasks>

<verification>
1. `npm run build` completes without errors
2. `npm run lint` passes with 0 warnings
3. Deploy to production and test keyboard scrolling works immediately on page load and navigation
</verification>

<success_criteria>
- Arrow keys, Page Up/Down, and Spacebar scroll the content area immediately after:
  - Initial page load
  - Navigating to a different page via sidebar or links
- Focus does not interfere with form inputs or other interactive elements (they should still be focusable via Tab or click)
- No visible focus outline on the main content area
</success_criteria>

<output>
After completion, create `.planning/quick/024-auto-focus-main-for-instant-scroll/024-SUMMARY.md`
</output>
