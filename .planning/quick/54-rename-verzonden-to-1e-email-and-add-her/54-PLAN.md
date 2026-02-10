---
phase: quick-54
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/VOG/VOGList.jsx
  - includes/class-rest-people.php
autonomous: true

must_haves:
  truths:
    - "Column header displays '1e email' instead of 'Verzonden'"
    - "Email filter chip displays '1e email:' prefix instead of 'Email:'"
    - "Herinnering filter exists with Wel verzonden/Niet verzonden options"
    - "Herinnering filter affects displayed results based on vog_reminder_sent_date"
  artifacts:
    - path: "src/pages/VOG/VOGList.jsx"
      provides: "Renamed column header and new Herinnering filter UI"
      min_lines: 1100
    - path: "includes/class-rest-people.php"
      provides: "Backend vog_reminder_status parameter and filter logic"
      min_lines: 1300
  key_links:
    - from: "src/pages/VOG/VOGList.jsx"
      to: "/wp-json/rondo/v1/people"
      via: "vog_reminder_status parameter"
      pattern: "vog_reminder_status.*reminderStatusFilter"
    - from: "includes/class-rest-people.php"
      to: "vog_reminder_sent_date meta"
      via: "SQL JOIN and WHERE clauses"
      pattern: "vog_reminder_sent_date"
---

<objective>
Rename the "Verzonden" column to "1e email" and add a new "Herinnering" filter to the VOG page, following the existing pattern for email and Justis filters.

Purpose: Clarify that the "Verzonden" column refers specifically to the first email sent, and provide filtering capability for reminder emails.

Output: Updated VOG list with renamed column and functional Herinnering filter
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@src/pages/VOG/VOGList.jsx
@includes/class-rest-people.php
</context>

<tasks>

<task type="auto">
  <name>Rename Verzonden column to 1e email</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
Update three references to "Verzonden" in VOGList.jsx:

1. Line 212: Update comment from `{/* Verzonden date */}` to `{/* 1e email date */}`
2. Line 1015: Change label from `label="Verzonden"` to `label="1e email"`
3. Line 893: Update filter chip text from `Email: {emailStatusFilter === 'sent' ? 'Wel verzonden' : 'Niet verzonden'}` to `1e email: {emailStatusFilter === 'sent' ? 'Wel verzonden' : 'Niet verzonden'}`

These are simple string replacements for UI clarity. The underlying data field `vog_email_sent_date` remains unchanged.
  </action>
  <verify>Grep for "Verzonden" in VOGList.jsx — should return no results except in Dutch text strings</verify>
  <done>Column header and filter chip display "1e email" instead of "Verzonden"</done>
</task>

<task type="auto">
  <name>Add backend support for Herinnering filter</name>
  <files>includes/class-rest-people.php</files>
  <action>
Add vog_reminder_status parameter and filter logic following the exact pattern of vog_justis_status:

1. **Register parameter** (after line 365, after vog_justis_status):
```php
'vog_reminder_status' => [
    'description'       => 'Filter by VOG reminder status (sent, not_sent, empty=all)',
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => function ( $value ) {
        return in_array( $value, [ '', 'sent', 'not_sent' ], true );
    },
],
```

2. **Extract parameter** (after line 1010):
```php
$vog_reminder_status = $request->get_param( 'vog_reminder_status' );
```

3. **Add filter logic** (after line 1194, after the vog_justis_status filter block):
```php
// VOG Reminder status filter (sent/not_sent based on vog_reminder_sent_date meta field)
if ( $vog_reminder_status !== null && $vog_reminder_status !== '' ) {
    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} vrs ON p.ID = vrs.post_id AND vrs.meta_key = 'vog_reminder_sent_date'";

    if ( $vog_reminder_status === 'sent' ) {
        $where_clauses[] = "(vrs.meta_value IS NOT NULL AND vrs.meta_value != '')";
    } elseif ( $vog_reminder_status === 'not_sent' ) {
        $where_clauses[] = "(vrs.meta_value IS NULL OR vrs.meta_value = '')";
    }
}
```

Pattern matches vog_justis_status exactly: alias `vrs` for reminder, checks `vog_reminder_sent_date` meta field.
  </action>
  <verify>Test API endpoint: curl to /wp-json/rondo/v1/people?vog_reminder_status=sent should return only people with vog_reminder_sent_date set</verify>
  <done>Backend accepts vog_reminder_status parameter and filters results correctly</done>
</task>

<task type="auto">
  <name>Add Herinnering filter UI to VOG page</name>
  <files>src/pages/VOG/VOGList.jsx</files>
  <action>
Add Herinnering filter following the exact pattern of justisStatusFilter:

1. **Add state** (after line 256):
```js
const [reminderStatusFilter, setReminderStatusFilter] = useState('');
```

2. **Pass to useFilteredPeople** (line 287, add after vogJustisStatus):
```js
vogReminderStatus: reminderStatusFilter,
```

3. **Add to API params** (line 490, after vog_justis_status):
```js
vog_reminder_status: reminderStatusFilter || undefined,
```

4. **Add radio group in filter dropdown** (after line 870, after Justis section, before "Alles wissen"):
```jsx
{/* Herinnering filter */}
<div className="space-y-2 pb-4 border-b border-gray-200 dark:border-gray-700">
  <h4 className="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
    Herinnering
  </h4>
  <div className="space-y-2">
    <label className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded">
      <input
        type="radio"
        name="reminderStatus"
        checked={reminderStatusFilter === ''}
        onChange={() => {
          setReminderStatusFilter('');
          setShowFilterDropdown(false);
        }}
        className={`${
          reminderStatusFilter === ''
            ? 'text-bright-cobalt dark:text-electric-cyan'
            : ''
        }`}
      />
      {reminderStatusFilter === '' && (
        <Check className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan" />
      )}
      <span className="text-sm text-gray-700 dark:text-gray-200">
        Alle
      </span>
    </label>
    <label className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded">
      <input
        type="radio"
        name="reminderStatus"
        checked={reminderStatusFilter === 'not_sent'}
        onChange={() => {
          setReminderStatusFilter('not_sent');
          setShowFilterDropdown(false);
        }}
        className={`${
          reminderStatusFilter === 'not_sent'
            ? 'text-bright-cobalt dark:text-electric-cyan'
            : ''
        }`}
      />
      {reminderStatusFilter === 'not_sent' && (
        <Check className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan" />
      )}
      <span className="text-sm text-gray-700 dark:text-gray-200">
        Niet verzonden
      </span>
    </label>
    <label className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 p-2 rounded">
      <input
        type="radio"
        name="reminderStatus"
        checked={reminderStatusFilter === 'sent'}
        onChange={() => {
          setReminderStatusFilter('sent');
          setShowFilterDropdown(false);
        }}
        className={`${
          reminderStatusFilter === 'sent'
            ? 'text-bright-cobalt dark:text-electric-cyan'
            : ''
        }`}
      />
      {reminderStatusFilter === 'sent' && (
        <Check className="w-4 h-4 text-bright-cobalt dark:text-electric-cyan" />
      )}
      <span className="text-sm text-gray-700 dark:text-gray-200">
        Wel verzonden
      </span>
    </label>
  </div>
</div>
```

5. **Add active filter chip** (after line 913, after justisStatusFilter chip):
```jsx
{reminderStatusFilter && (
  <span className="inline-flex items-center gap-1 px-2 py-1 text-xs bg-cyan-50 dark:bg-obsidian/30 text-bright-cobalt dark:text-electric-cyan-light border border-cyan-200 dark:border-bright-cobalt rounded">
    Herinnering: {reminderStatusFilter === 'sent' ? 'Wel verzonden' : 'Niet verzonden'}
    <button
      onClick={() => setReminderStatusFilter('')}
      className="hover:text-obsidian dark:hover:text-cyan-100"
    >
      <X className="w-3 h-3" />
    </button>
  </span>
)}
```

6. **Update filter count** (line 651, add reminderStatusFilter):
```js
{(emailStatusFilter ? 1 : 0) + (vogTypeFilter ? 1 : 0) + (justisStatusFilter ? 1 : 0) + (reminderStatusFilter ? 1 : 0)}
```

7. **Update filter button highlight** (line 645, add reminderStatusFilter):
```js
className={`btn-secondary ${(emailStatusFilter || vogTypeFilter || justisStatusFilter || reminderStatusFilter) ? 'bg-cyan-50 text-bright-cobalt border-cyan-200 dark:bg-obsidian/30 dark:text-electric-cyan-light dark:border-bright-cobalt' : ''}`}
```

8. **Update "Alles wissen" button** (line 871, add reminderStatusFilter):
```js
{(emailStatusFilter || vogTypeFilter || justisStatusFilter || reminderStatusFilter) && (
```
And update the clear action to include:
```js
setReminderStatusFilter('');
```

9. **Update active filters section** (line 889, add reminderStatusFilter):
```js
{(emailStatusFilter || vogTypeFilter || justisStatusFilter || reminderStatusFilter) && (
```

Use exact same pattern as justisStatusFilter throughout — radio buttons, filter chips, count logic, clear logic.
  </action>
  <verify>
1. Run `npm run build` to compile frontend
2. Test filter UI: open VOG page, click Filters button, verify Herinnering section appears with radio buttons
3. Test filtering: select "Wel verzonden" — only people with reminder dates should appear
4. Test filter chip: verify active filter displays "Herinnering: Wel verzonden"
5. Test clear: click X on filter chip or "Alles wissen" — filter resets
  </verify>
  <done>Herinnering filter works identically to Email and Justis filters with radio buttons, active chips, and proper filtering behavior</done>
</task>

</tasks>

<verification>
1. Column header displays "1e email" instead of "Verzonden"
2. Email filter chip shows "1e email:" prefix
3. Herinnering filter appears in filter dropdown with 3 radio options
4. Selecting "Wel verzonden" shows only people with vog_reminder_sent_date
5. Selecting "Niet verzonden" shows only people without vog_reminder_sent_date
6. Active Herinnering filter displays as chip with X button
7. Filter count includes Herinnering when active
8. "Alles wissen" clears Herinnering filter
9. Filter button highlights when Herinnering is active
</verification>

<success_criteria>
- [ ] "Verzonden" renamed to "1e email" in column header, comment, and filter chip
- [ ] Backend vog_reminder_status parameter registered and working
- [ ] Herinnering filter UI matches Email/Justis pattern exactly
- [ ] Frontend compiled with `npm run build`
- [ ] Changes deployed to production
- [ ] Manual verification on production VOG page confirms all functionality
</success_criteria>

<output>
After completion, create `.planning/quick/54-rename-verzonden-to-1e-email-and-add-her/54-SUMMARY.md`
</output>
