---
phase: quick
plan: 008
type: execute
wave: 1
depends_on: []
files_modified:
  - src/components/CustomFieldsSection.jsx
  - src/components/CustomFieldColumn.jsx
autonomous: true

must_haves:
  truths:
    - "WYSIWYG custom fields render as formatted HTML on detail pages"
    - "WYSIWYG custom fields show plain text preview in list views"
  artifacts:
    - path: "src/components/CustomFieldsSection.jsx"
      provides: "HTML rendering for wysiwyg fields"
      contains: "case 'wysiwyg'"
    - path: "src/components/CustomFieldColumn.jsx"
      provides: "Plain text preview for wysiwyg fields in lists"
      contains: "case 'wysiwyg'"
  key_links:
    - from: "CustomFieldsSection"
      to: "wysiwyg field value"
      via: "dangerouslySetInnerHTML"
      pattern: "dangerouslySetInnerHTML.*__html"
---

<objective>
Fix WYSIWYG custom fields to render as HTML instead of escaped text.

Purpose: Currently, custom fields of type `wysiwyg` (rich text from ACF) are displayed with escaped HTML tags visible as text. Users see raw `<p>`, `<strong>` tags instead of formatted content.

Output: WYSIWYG fields render properly formatted HTML on detail pages and show plain text previews in list views.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/components/CustomFieldsSection.jsx
@src/components/CustomFieldColumn.jsx
@src/components/Timeline/TimelineView.jsx (reference: existing dangerouslySetInnerHTML pattern at line 147)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add wysiwyg rendering to CustomFieldsSection</name>
  <files>src/components/CustomFieldsSection.jsx</files>
  <action>
Add a new case in the `renderFieldValue` switch statement for `wysiwyg` type fields.

Insert after the `textarea` case (around line 182) a new case:

```javascript
case 'wysiwyg':
  return (
    <div
      className="prose prose-sm dark:prose-invert max-w-none"
      dangerouslySetInnerHTML={{ __html: value }}
    />
  );
```

This follows the exact pattern used in:
- `TimelineView.jsx` line 147
- `TodoModal.jsx` line 197
- `MeetingDetailModal.jsx` line 295

The `prose` classes from Tailwind Typography handle proper styling of HTML content (paragraphs, lists, links, etc.).
  </action>
  <verify>
1. Run `npm run lint` - should pass
2. Run `npm run build` - should complete without errors
  </verify>
  <done>CustomFieldsSection renders wysiwyg fields as formatted HTML using dangerouslySetInnerHTML with prose classes</done>
</task>

<task type="auto">
  <name>Task 2: Add wysiwyg preview to CustomFieldColumn</name>
  <files>src/components/CustomFieldColumn.jsx</files>
  <action>
Add a new case in the switch statement for `wysiwyg` type fields. For list views, we want a plain text preview (stripped HTML) since full formatting would break the table layout.

Insert after the `textarea` case (around line 51) a new case:

```javascript
case 'wysiwyg': {
  // Strip HTML tags for list view preview
  const plainText = String(value).replace(/<[^>]*>/g, '').trim();
  return <span className="truncate block max-w-32" title={plainText}>{plainText || '-'}</span>;
}
```

This strips HTML tags and shows truncated plain text, consistent with how `text` and `textarea` fields are displayed in the list view.
  </action>
  <verify>
1. Run `npm run lint` - should pass
2. Run `npm run build` - should complete without errors
  </verify>
  <done>CustomFieldColumn shows plain text preview (HTML stripped) for wysiwyg fields in list views</done>
</task>

<task type="auto">
  <name>Task 3: Deploy and verify</name>
  <files>None (deployment only)</files>
  <action>
1. Run `npm run build` to generate production assets
2. Deploy to production using `bin/deploy.sh`
3. Clear caches if needed
  </action>
  <verify>
1. Visit production site
2. Navigate to a person/team detail page that has a wysiwyg custom field
3. Confirm the field displays formatted HTML (bold, lists, links work) instead of escaped tags
4. Check list view to confirm wysiwyg fields show plain text preview
  </verify>
  <done>WYSIWYG fields render correctly on production - formatted HTML on detail pages, plain text in lists</done>
</task>

</tasks>

<verification>
- `npm run lint` passes without new errors
- `npm run build` succeeds
- Detail page wysiwyg fields show formatted content (not escaped HTML tags)
- List view wysiwyg fields show plain text preview (HTML stripped)
</verification>

<success_criteria>
- WYSIWYG custom fields on detail pages render as formatted HTML with proper styling
- WYSIWYG custom fields in list views show truncated plain text preview
- No new ESLint errors introduced
- Production deployment verified working
</success_criteria>

<output>
After completion, create `.planning/quick/008-wysiwyg-html-render/008-SUMMARY.md`
</output>
