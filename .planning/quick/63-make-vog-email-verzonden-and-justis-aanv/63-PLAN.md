---
phase: 63
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/VOGCard.jsx
  - src/pages/People/PersonDetail.jsx
autonomous: true

must_haves:
  truths:
    - User can click VOG email date and edit it inline
    - User can click Justis date and edit it inline
    - Changes save immediately to database
  artifacts:
    - path: src/components/VOGCard.jsx
      provides: Editable date fields with inline input
      min_lines: 150
  key_links:
    - from: src/components/VOGCard.jsx
      to: updatePerson mutation
      via: onUpdateField callback
      pattern: onUpdateField\\(.*vog_email_sent_date
---

<objective>
Make the two VOG tracking date fields (email verzonden and Justis aanvraag) editable on the PersonDetail page.

Purpose: Allow users to manually update VOG process tracking dates without needing ACF admin access
Output: Clickable date fields in VOGCard that save changes via REST API
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/components/VOGCard.jsx
@src/pages/People/PersonDetail.jsx
@src/utils/formatters.js
</context>

<tasks>

<task type="auto">
  <name>Make VOG tracking dates editable with inline date inputs</name>
  <files>
src/components/VOGCard.jsx
src/pages/People/PersonDetail.jsx
  </files>
  <action>
**1. Update PersonDetail to pass update capability to VOGCard:**

At line 1498 where VOGCard is rendered, change from:
```jsx
<VOGCard acfData={person?.acf} />
```

To:
```jsx
<VOGCard
  acfData={person?.acf}
  personId={parseInt(id)}
  onUpdateField={(fieldName, value) => {
    const acfData = sanitizePersonAcf(person.acf, {
      [fieldName]: value,
    });
    updatePerson.mutateAsync({
      id,
      data: { acf: acfData },
    });
  }}
  isUpdating={updatePerson.isPending}
/>
```

**2. Update VOGCard component signature and add editing state:**

Change the component signature from:
```jsx
export default function VOGCard({ acfData }) {
```

To:
```jsx
export default function VOGCard({ acfData, personId, onUpdateField, isUpdating }) {
```

Add state for tracking which field is being edited (after the currentUser hook around line 31):
```jsx
const [editingField, setEditingField] = useState(null);
```

Add the useState import to the imports at the top:
```jsx
import { useState } from 'react';
```

**3. Make the email sent date field editable:**

Replace the email sent display (lines 105-113) with:
```jsx
<div className="flex items-center gap-2">
  <Mail className={`w-4 h-4 ${emailSentDate ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
  <span className="text-gray-600 dark:text-gray-400">
    E-mail verzonden:
  </span>
  {editingField === 'vog_email_sent_date' ? (
    <input
      type="date"
      defaultValue={emailSentDate || ''}
      className="px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600"
      autoFocus
      disabled={isUpdating}
      onChange={(e) => {
        if (e.target.value) {
          onUpdateField('vog_email_sent_date', e.target.value);
        }
        setEditingField(null);
      }}
      onBlur={() => setEditingField(null)}
    />
  ) : (
    <button
      onClick={() => setEditingField('vog_email_sent_date')}
      disabled={isUpdating || !personId}
      className="text-gray-900 dark:text-gray-100 hover:text-brand-primary dark:hover:text-brand-secondary underline decoration-dotted disabled:opacity-50"
    >
      {emailSentDate ? format(new Date(emailSentDate), 'd MMM yyyy') : 'Nog niet'}
    </button>
  )}
</div>
```

**4. Make the Justis date field editable:**

Replace the Justis submitted display (lines 116-124) with:
```jsx
<div className="flex items-center gap-2">
  <FileCheck className={`w-4 h-4 ${justisSubmittedDate ? 'text-green-500' : 'text-gray-300 dark:text-gray-600'}`} />
  <span className="text-gray-600 dark:text-gray-400">
    Justis aanvraag:
  </span>
  {editingField === 'vog_justis_submitted_date' ? (
    <input
      type="date"
      defaultValue={justisSubmittedDate || ''}
      className="px-2 py-1 text-sm border rounded dark:bg-gray-700 dark:border-gray-600"
      autoFocus
      disabled={isUpdating}
      onChange={(e) => {
        if (e.target.value) {
          onUpdateField('vog_justis_submitted_date', e.target.value);
        }
        setEditingField(null);
      }}
      onBlur={() => setEditingField(null)}
    />
  ) : (
    <button
      onClick={() => setEditingField('vog_justis_submitted_date')}
      disabled={isUpdating || !personId}
      className="text-gray-900 dark:text-gray-100 hover:text-brand-primary dark:hover:text-brand-secondary underline decoration-dotted disabled:opacity-50"
    >
      {justisSubmittedDate ? format(new Date(justisSubmittedDate), 'd MMM yyyy') : 'Nog niet'}
    </button>
  )}
</div>
```

The approach: clicking the date text opens an inline date input, selecting a date auto-saves and closes the input, clicking away cancels editing.
  </action>
  <verify>
1. Visit PersonDetail for a volunteer with missing/expired VOG
2. Click on "Nog niet" or existing date for email verzonden → date input appears
3. Select a date → it saves and displays formatted
4. Click on Justis aanvraag date → date input appears
5. Select a date → it saves and displays formatted
6. Refresh page → dates persist
7. Check dark mode → input styling works
  </verify>
  <done>
Both VOG tracking date fields are clickable, show native date input on click, save changes immediately via REST API, and display formatted dates when not editing.
  </done>
</task>

</tasks>

<verification>
- [ ] Email verzonden date is editable by clicking
- [ ] Justis aanvraag date is editable by clicking
- [ ] Date input appears inline when clicked
- [ ] Changes save to database immediately
- [ ] Dates persist after page refresh
- [ ] Dark mode styling works correctly
- [ ] Disabled state shows when updating
</verification>

<success_criteria>
Users can click on VOG tracking dates in the VOGCard and edit them inline using a native date picker, with changes saving immediately to the WordPress database via the REST API.
</success_criteria>

<output>
After completion, create `.planning/quick/63-make-vog-email-verzonden-and-justis-aanv/63-SUMMARY.md`
</output>
