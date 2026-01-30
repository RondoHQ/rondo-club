---
task: 022
type: quick
title: VOG filter dropdown and Google Sheets export
autonomous: true
files_modified:
  - src/pages/VOG/VOGList.jsx
---

<objective>
Update the VOG list page to:
1. Replace the simple select element for email status filtering with a proper dropdown panel that matches the PeopleList filter dropdown styling
2. Add the Google Sheets export button that mirrors the PeopleList export functionality

Purpose: Consistency across list views and ability to export VOG data to Google Sheets for reporting.
Output: VOG list with improved filter UI and export capability.
</objective>

<context>
@src/pages/VOG/VOGList.jsx - Current VOG list (has simple select for filter on line 508-520)
@src/pages/People/PeopleList.jsx - Reference implementation with filter dropdown (lines 1104-1301) and Google Sheets export (lines 1036-1096, 1417-1440)
@src/api/client.js - API client with Google Sheets methods (getSheetsStatus, getSheetsAuthUrl, exportPeopleToSheets)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Refactor VOG filter to dropdown panel</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
Replace the simple select filter (lines 508-520) with a dropdown panel that matches PeopleList styling:

1. Add state for filter dropdown:
   - `const [isFilterOpen, setIsFilterOpen] = useState(false);`
   - Add `filterRef` and `dropdownRef` refs (same pattern as PeopleList)

2. Add click-outside handler to close dropdown (copy pattern from PeopleList lines 885-908)

3. Replace the filter section with a proper Filter button + dropdown panel:
   - Filter button with icon, matching PeopleList style (line 1105-1118)
   - Show active filter count badge when filter is active
   - Dropdown panel with:
     - Section header "Email status"
     - Checkable options for "Alle", "Niet verzonden", "Wel verzonden" with counts
     - Clear filters link at bottom when filter is active

4. Add filter chip display below the filter button showing active filter (same pattern as PeopleList active filter chips)

Use these Tailwind classes from PeopleList:
- Dropdown: `absolute top-full left-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50`
- Section header: `text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2`
- Checkbox items: same pattern as label checkboxes in PeopleList

Add Filter and Check icons to imports at top of file.
  </action>
  <verify>
Build passes: `npm run build`
Visually verify the dropdown matches PeopleList style by comparing screenshots.
  </verify>
  <done>
VOG filter uses dropdown panel with proper styling, matching PeopleList filter dropdown appearance.
Filter chips show when filter is active.
Click outside closes dropdown.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add Google Sheets export functionality</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
Add Google Sheets export button and functionality, following PeopleList pattern:

1. Add imports at top:
   - `FileSpreadsheet` from lucide-react
   - `useQuery` (already imported)
   - Ensure `prmApi` is imported (already imported)

2. Add state:
   - `const [isExporting, setIsExporting] = useState(false);`

3. Add Google Sheets status query (copy from PeopleList lines 748-755):
   ```javascript
   const { data: sheetsStatus } = useQuery({
     queryKey: ['google-sheets-status'],
     queryFn: async () => {
       const response = await prmApi.getSheetsStatus();
       return response.data;
     },
   });
   ```

4. Add export handler function adapted for VOG context:
   ```javascript
   const handleExportToSheets = async () => {
     if (isExporting) return;
     setIsExporting(true);
     const newWindow = window.open('about:blank', '_blank');

     try {
       // VOG-specific columns
       const columns = ['name', 'knvb-id', 'email', 'phone', 'datum-vog', 'vog_email_sent_date', 'vog_justis_submitted_date'];

       // VOG-specific filters (matching the useFilteredPeople params in VOGList)
       const filters = {
         huidig_vrijwilliger: '1',
         vog_missing: '1',
         vog_older_than_years: 3,
         vog_email_status: emailStatusFilter || undefined,
         orderby,
         order,
       };

       const response = await prmApi.exportPeopleToSheets({ columns, filters });

       if (response.data.spreadsheet_url && newWindow) {
         newWindow.location.href = response.data.spreadsheet_url;
       }
     } catch (error) {
       console.error('Export error:', error);
       if (newWindow) newWindow.close();
       const message = error.response?.data?.message || 'Export mislukt. Probeer het opnieuw.';
       alert(message);
     } finally {
       setIsExporting(false);
     }
   };
   ```

5. Add connect handler (copy from PeopleList lines 1086-1096):
   ```javascript
   const handleConnectSheets = async () => {
     try {
       const response = await prmApi.getSheetsAuthUrl();
       if (response.data.auth_url) {
         window.location.href = response.data.auth_url;
       }
     } catch (error) {
       console.error('Auth error:', error);
       alert('Kon geen verbinding maken met Google Sheets. Probeer het opnieuw.');
     }
   };
   ```

6. Add the export button in the header area (next to the filter button), matching PeopleList pattern:
   - If connected: show export button with FileSpreadsheet icon
   - If not connected but google configured: show connect button
   - During export: show spinner

Place the button in a flex container alongside the filter button.
  </action>
  <verify>
Build passes: `npm run build`
Button appears when Google Sheets is configured.
Clicking export opens new tab with spreadsheet (when connected).
  </verify>
  <done>
Google Sheets export button appears on VOG list page.
Export uses VOG-specific columns and filters.
Connect flow works when not yet connected.
  </done>
</task>

</tasks>

<verification>
1. `npm run build` completes without errors
2. VOG page filter dropdown visually matches PeopleList filter dropdown
3. Filter badge shows count when filter is active
4. Filter chip displays below filter button when active
5. Click outside dropdown closes it
6. Google Sheets export button appears (when configured)
7. Export creates spreadsheet with VOG columns and filtered data
</verification>

<success_criteria>
- VOG filter uses dropdown panel matching PeopleList styling
- Google Sheets export works with VOG-specific columns
- Build passes with no lint errors
- Consistent UX across both list pages
</success_criteria>

<output>
After completion, create `.planning/quick/022-vog-filter-dropdown-and-export/022-SUMMARY.md`
</output>
