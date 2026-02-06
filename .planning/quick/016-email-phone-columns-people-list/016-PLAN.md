---
quick_task: 016
type: execute
description: Add email and phone number to available columns in People list
autonomous: true
files_modified:
  - includes/class-rest-api.php
  - src/pages/People/PeopleList.jsx
---

<objective>
Add email and phone number as available columns in the People list view, displaying the first email address and first phone number from the person's contact_info repeater field.

Purpose: Allow users to see contact information at a glance in the People list without opening each person's detail page.

Output: Email and phone columns available in column settings, displaying first matching contact value or '-' if none exists.
</objective>

<context>
@CLAUDE.md
@includes/class-rest-api.php (CORE_LIST_COLUMNS, get_valid_column_ids, get_available_columns_metadata)
@src/pages/People/PeopleList.jsx (PersonListRow component, column rendering)
@acf-json/group_person_fields.json (contact_info repeater structure)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add email and phone to available columns in backend</name>
  <files>includes/class-rest-api.php</files>
  <action>
    1. Add email and phone to CORE_LIST_COLUMNS constant (around line 965):
       ```php
       private const CORE_LIST_COLUMNS = [
           [ 'id' => 'email', 'label' => 'E-mail', 'type' => 'core' ],
           [ 'id' => 'phone', 'label' => 'Telefoon', 'type' => 'core' ],
           [ 'id' => 'team', 'label' => 'Team', 'type' => 'core' ],
           [ 'id' => 'labels', 'label' => 'Labels', 'type' => 'core' ],
           [ 'id' => 'modified', 'label' => 'Laatst gewijzigd', 'type' => 'core' ],
       ];
       ```
       Note: Using Dutch labels consistent with other column labels (Team, Labels). Also update 'Last Modified' to Dutch 'Laatst gewijzigd' for consistency.

    2. Add email and phone to get_valid_column_ids() method (around line 1286):
       ```php
       private function get_valid_column_ids(): array {
           // Core columns
           $core = [ 'email', 'phone', 'team', 'labels', 'modified' ];
           // ... rest unchanged
       }
       ```
  </action>
  <verify>
    Check /wp-json/rondo/v1/user/list-preferences endpoint returns email and phone in available_columns array.
  </verify>
  <done>
    Email and phone columns appear in available_columns with correct labels and type.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add email and phone column rendering in frontend</name>
  <files>src/pages/People/PeopleList.jsx</files>
  <action>
    In the PersonListRow component (around line 47-180), add rendering logic for email and phone columns in the dynamic columns section (after the existing team, labels, modified column handlers):

    1. Add helper function at top of file to extract first contact by type:
       ```jsx
       // Helper function to get first contact value by type
       function getFirstContactByType(person, type) {
         const contactInfo = person.acf?.contact_info || [];
         const contact = contactInfo.find(c => c.contact_type === type);
         return contact?.contact_value || null;
       }

       // Helper function to get first phone (includes mobile)
       function getFirstPhone(person) {
         const contactInfo = person.acf?.contact_info || [];
         const contact = contactInfo.find(c => c.contact_type === 'phone' || c.contact_type === 'mobile');
         return contact?.contact_value || null;
       }
       ```

    2. Add column rendering in PersonListRow (in the visibleColumns.map section, after the modified column handler around line 175):
       ```jsx
       if (colId === 'email') {
         const email = getFirstContactByType(person, 'email');
         return (
           <td
             key={colId}
             className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
             style={style}
           >
             {email ? (
               <a href={`mailto:${email}`} className="hover:text-accent-600 dark:hover:text-accent-400">
                 {email}
               </a>
             ) : '-'}
           </td>
         );
       }

       if (colId === 'phone') {
         const phone = getFirstPhone(person);
         return (
           <td
             key={colId}
             className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
             style={style}
           >
             {phone ? (
               <a href={`tel:${phone}`} className="hover:text-accent-600 dark:hover:text-accent-400">
                 {phone}
               </a>
             ) : '-'}
           </td>
         );
       }
       ```
  </action>
  <verify>
    1. Run `npm run build` - should complete without errors
    2. Open People list in browser
    3. Click gear icon to open column settings
    4. Verify "E-mail" and "Telefoon" appear as available columns
    5. Enable both columns and verify they display correct values (first email/phone from contact info)
  </verify>
  <done>
    Email and phone columns can be enabled via column settings and display the first email/phone value from contact_info, with clickable mailto:/tel: links.
  </done>
</task>

<task type="auto">
  <name>Task 3: Build, deploy, and commit</name>
  <files>dist/</files>
  <action>
    1. Run `npm run build` to compile frontend changes
    2. Deploy to production using `bin/deploy.sh`
    3. Git add and commit with message: "feat: add email and phone columns to People list"
    4. Git push
  </action>
  <verify>
    1. Production site shows email and phone as available columns
    2. Git log shows commit
  </verify>
  <done>
    Changes deployed to production and committed to git.
  </done>
</task>

</tasks>

<verification>
1. Backend: GET /wp-json/rondo/v1/user/list-preferences returns email and phone in available_columns
2. Frontend: Column settings modal shows E-mail and Telefoon options
3. Frontend: Enabling columns displays correct contact values with clickable links
4. Frontend: Missing contact info shows '-' placeholder
5. Production: Feature works after deployment
</verification>

<success_criteria>
- Email and phone columns available in People list column settings
- Columns display first email/phone from contact_info repeater
- Values are clickable (mailto:/tel: links)
- Missing values display as '-'
- Changes deployed and committed
</success_criteria>

<output>
After completion, update STATE.md quick tasks table and commit.
</output>
