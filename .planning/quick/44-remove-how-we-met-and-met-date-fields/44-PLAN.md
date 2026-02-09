---
phase: quick-44
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - acf-json/group_person_fields.json
  - src/pages/People/PersonDetail.jsx
  - src/components/PersonEditModal.jsx
  - src/hooks/usePeople.js
autonomous: true

must_haves:
  truths:
    - "Story tab displays no how_we_met or met_date fields"
    - "Edit modal has no how_we_met field"
    - "Person API responses exclude how_we_met and met_date"
  artifacts:
    - path: "acf-json/group_person_fields.json"
      provides: "Person ACF field definitions without story fields"
      contains: "field_person_basic_info"
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "Person detail display without story section"
      min_lines: 1300
    - path: "src/components/PersonEditModal.jsx"
      provides: "Person edit form without how_we_met field"
      min_lines: 400
  key_links:
    - from: "src/components/PersonEditModal.jsx"
      to: "acf-json/group_person_fields.json"
      via: "form submission to REST API"
      pattern: "how_we_met.*register"
---

<objective>
Remove deprecated how_we_met and met_date fields from person records.

Purpose: Clean up unused "Story" fields that were never adopted in production use
Output: Person posts without story tab, edit modal simplified, ACF field group cleaned
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md

# ACF field group for person posts
@acf-json/group_person_fields.json

# Frontend components using these fields
@src/pages/People/PersonDetail.jsx
@src/components/PersonEditModal.jsx
@src/hooks/usePeople.js
</context>

<tasks>

<task type="auto">
  <name>Remove how_we_met and met_date fields from ACF and frontend</name>
  <files>
    acf-json/group_person_fields.json
    src/pages/People/PersonDetail.jsx
    src/components/PersonEditModal.jsx
    src/hooks/usePeople.js
  </files>
  <action>
Remove the "Story" tab and its two fields from person records:

**1. ACF field group (acf-json/group_person_fields.json):**
- Remove the "Story" tab field definition (key: field_person_story, lines 108-113)
- Remove the "How We Met" field definition (key: field_how_we_met, lines 115-121)
- Remove the "When We Met" field definition (key: field_met_date, lines 122-129)

**2. PersonDetail display (src/pages/People/PersonDetail.jsx):**
- Find and remove the conditional Story section rendering (around line 1342-1354)
- Section is wrapped in `{(acf.how_we_met || acf.met_date) && (...)}`
- Includes a Card component with "Our Story" title and formatted date/text display

**3. PersonEditModal form (src/components/PersonEditModal.jsx):**
- Remove how_we_met field from initial form state (line 61)
- Remove how_we_met from state initialization when loading person data (line 91)
- Remove how_we_met from reset logic (lines 106, 121)
- Remove how_we_met from note import logic (line 171)
- Remove the how_we_met textarea field from the form (line 424)
- The field appears after relationships section, uses register('how_we_met')

**4. usePeople hook (src/hooks/usePeople.js):**
- Remove how_we_met from createPersonMutation payload (line 255)
- Search for any other references and remove them

**What NOT to do:**
- Do not touch met_date references in code dealing with other date types (important dates, work history)
- Do not remove the Contact Info, Team History, or Relationships tabs
- Leave all other ACF field definitions intact
  </action>
  <verify>
```bash
# Confirm fields removed from ACF
! grep -q "field_how_we_met\|field_met_date\|field_person_story" acf-json/group_person_fields.json

# Confirm removed from frontend
! grep -q "how_we_met\|met_date" src/pages/People/PersonDetail.jsx
! grep -q "how_we_met" src/components/PersonEditModal.jsx
! grep -q "how_we_met" src/hooks/usePeople.js

# Build succeeds
npm run build
```
  </verify>
  <done>
- ACF field group has no Story tab or how_we_met/met_date field definitions
- PersonDetail.jsx displays no Story section
- PersonEditModal.jsx has no how_we_met form field
- usePeople.js excludes how_we_met from API payloads
- Build completes without errors
  </done>
</task>

</tasks>

<verification>
**Manual checks:**
1. Open person edit in WordPress admin — Story tab should be gone
2. Open person in React app — no "Our Story" card visible
3. Create new person via React — form has no how_we_met field
4. Edit existing person via React — form has no how_we_met field
</verification>

<success_criteria>
- [ ] Story tab removed from ACF field group JSON
- [ ] how_we_met and met_date fields removed from ACF JSON
- [ ] PersonDetail displays no Story section
- [ ] PersonEditModal form has no how_we_met field
- [ ] usePeople hook excludes how_we_met from mutations
- [ ] npm run build passes
- [ ] No grep matches for how_we_met or met_date in src/ files
</success_criteria>

<output>
After completion, create `.planning/quick/44-remove-how-we-met-and-met-date-fields/44-01-SUMMARY.md`
</output>
