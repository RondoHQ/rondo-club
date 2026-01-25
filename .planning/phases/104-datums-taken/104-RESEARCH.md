# Phase 104: Datums & Taken - Research

**Researched:** 2026-01-25
**Domain:** React UI translation (Dutch localization)
**Confidence:** HIGH

## Summary

This phase involves translating Important Dates and Todos pages from English to Dutch, following the established translation patterns from Phases 101-103 (Navigation, Dashboard, Leden, Teams, Commissies). The research confirms this is a straightforward string replacement task with no library dependencies or architectural changes required.

The navigation sidebar is already translated ("Datums" and "Taken" visible in Layout.jsx), so this phase focuses on the page content, forms, and modals. There are 5 component files to translate plus 1 utility hook file for document titles.

**Primary recommendation:** Apply direct string replacement in DatesList.jsx, TodosList.jsx, ImportantDateModal.jsx, TodoModal.jsx, GlobalTodoModal.jsx, CompleteTodoModal.jsx, and useDocumentTitle.js, following Phase 103 patterns and CONTEXT.md terminology.

## Standard Stack

This phase requires no new libraries - it uses the existing React application stack.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.x | UI framework | Already in use |
| React Router | 6.x | Navigation | Already in use |
| TanStack Query | 5.x | Data fetching | Already in use |

### Supporting
No additional libraries needed for translation work.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Hardcoded strings | i18n library (react-intl, i18next) | Overkill for single-language app; adds complexity |

**Installation:**
No installation required - pure string replacement.

## Architecture Patterns

### Translation Pattern (from Phases 101-103)

The established pattern is direct string replacement in JSX files:

```jsx
// Before (English)
<h1 className="text-2xl font-bold">Todos</h1>

// After (Dutch)
<h1 className="text-2xl font-bold">Taken</h1>
```

### Files to Modify

```
src/
├── pages/
│   ├── Dates/
│   │   └── DatesList.jsx        # List page (~187 lines)
│   └── Todos/
│       └── TodosList.jsx        # List page with filters (~497 lines)
├── components/
│   ├── ImportantDateModal.jsx   # Create/edit date form (~411 lines)
│   └── Timeline/
│       ├── TodoModal.jsx        # View/edit todo form (~461 lines)
│       ├── GlobalTodoModal.jsx  # Create todo form (~325 lines)
│       └── CompleteTodoModal.jsx # Complete todo options (~78 lines)
└── hooks/
    └── useDocumentTitle.js      # Document title utility (~92 lines)
```

### Date Type Translation Note

The date types (Birthday, Wedding, Memorial, Other) come from WordPress taxonomy terms defined in PHP (`includes/class-taxonomies.php`). These are stored in the database and displayed via the REST API.

**IMPORTANT:** The date type labels shown in the frontend come from the database terms, NOT from hardcoded frontend strings. To translate date type labels:
1. The taxonomy terms need to be updated in the WordPress database (via WP Admin > Date Types)
2. OR the frontend needs to map slugs to Dutch labels

Per CONTEXT.md decisions:
- Birthday -> Verjaardag (slug: birthday)
- Anniversary -> Trouwdag (slug: wedding)
- Memorial -> Herdenking (slug: memorial)
- Other -> Overig (slug: other)

**Recommendation:** Create a frontend mapping object that translates date type slugs/names to Dutch labels. This avoids database changes and keeps translation in the frontend.

### Document Title Translation

The `useDocumentTitle.js` hook sets browser tab titles. Currently contains English strings:
- "Events" for /dates route
- "New date", "Edit date" for date forms
- "Todos" for /todos route

These should be translated to Dutch.

### Anti-Patterns to Avoid
- **Partial translation:** Don't leave some strings in English - complete each component
- **Inconsistent terminology:** Always use terminology from CONTEXT.md
- **Breaking existing functionality:** Only change string literals, not logic
- **Database date type modification:** Use frontend mapping instead

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Translation system | i18n framework | Direct string replacement | Single-language app, simple scope |
| Date type translation | Database modification | Frontend slug-to-label mapping | Keeps translation in React code |

**Key insight:** The date type labels should be translated via a frontend mapping object, not by changing database taxonomy terms. This keeps all translation work in the React codebase and follows the established pattern.

## Common Pitfalls

### Pitfall 1: Forgetting Document Titles
**What goes wrong:** Browser tab still shows English ("Events", "Todos")
**Why it happens:** Document titles are set in useDocumentTitle.js, not in page components
**How to avoid:** Translate strings in useDocumentTitle.js AND update useDocumentTitle() calls in page components
**Warning signs:** Browser tab shows English when on Dates/Todos pages

### Pitfall 2: Missing Modal Titles
**What goes wrong:** Modal headers still show "Add todo", "Edit date" in English
**Why it happens:** Modals are separate components easy to overlook
**How to avoid:** Systematically translate all 4 modal files
**Warning signs:** Opening a modal shows English header

### Pitfall 3: Inconsistent Todo Status Labels
**What goes wrong:** Using different terms than CONTEXT.md decisions
**Why it happens:** Not referencing CONTEXT.md during translation
**How to avoid:** Cross-reference every translation with CONTEXT.md:
  - Open -> Te doen
  - Awaiting -> Openstaand
  - Completed -> Afgerond
  - All -> Alle
**Warning signs:** Terms like "Open" instead of "Te doen"

### Pitfall 4: Date Type Labels Still in English
**What goes wrong:** "Birthday" appears instead of "Verjaardag"
**Why it happens:** Date types come from database taxonomy, not hardcoded
**How to avoid:** Create frontend mapping object for date type slugs -> Dutch labels
**Warning signs:** Date cards show English date type names

### Pitfall 5: Missing Empty State Messages
**What goes wrong:** "No todos found" still appears in English
**Why it happens:** Empty states are only visible when data is empty
**How to avoid:** Grep for common empty state patterns in each file
**Warning signs:** Users see English when they have no data

### Pitfall 6: Missing Confirmation Dialogs
**What goes wrong:** "Are you sure you want to delete this todo?" in English
**Why it happens:** window.confirm() calls easy to miss
**How to avoid:** Search for window.confirm in each file
**Warning signs:** Confirmation popups show English

### Pitfall 7: Tooltip/Title Attributes
**What goes wrong:** Hover tooltips show English ("Reopen todo", "Edit todo")
**Why it happens:** title="" attributes easy to overlook
**How to avoid:** Search for title= in each file
**Warning signs:** Hovering over buttons shows English tooltips

## Code Examples

### Translation Mapping - DatesList.jsx

```jsx
// Source: CONTEXT.md decisions + codebase review

// Header
"{dates?.length || 0} upcoming dates" -> "{dates?.length || 0} aankomende datums"
"Add date" -> "Datum toevoegen"

// Error state
"Failed to load dates." -> "Datums konden niet worden geladen."

// Empty state
"No important dates" -> "Geen belangrijke datums"
"Add birthdays, anniversaries, and more." -> "Voeg verjaardagen, trouwdagen en meer toe."

// Date type labels (displayed in PersonDateEntry component)
// These come from the dateType prop which contains taxonomy term names
// Need frontend mapping: birthday -> Verjaardag, wedding -> Trouwdag, etc.
```

### Translation Mapping - TodosList.jsx

```jsx
// Source: CONTEXT.md decisions

// Document title
useDocumentTitle('Todos') -> useDocumentTitle('Taken')

// Page header
<h1>Todos</h1> -> <h1>Taken</h1>
"Add todo" -> "Taak toevoegen"

// Filter tabs (per CONTEXT.md)
"Open" -> "Te doen"
"Awaiting" -> "Openstaand"
"Completed" -> "Afgerond"
"All" -> "Alle"

// Header text (getHeaderText function)
'All todos' -> 'Alle taken'
'Open todos' -> 'Te doen'
'Awaiting response' -> 'Openstaand'
'Completed todos' -> 'Afgeronde taken'
'Todos' -> 'Taken'

// Empty state (getEmptyMessage function)
'No todos found' -> 'Geen taken gevonden'
'No open todos' -> 'Geen open taken'
'No todos awaiting response' -> 'Geen openstaande taken'
'No completed todos' -> 'Geen afgeronde taken'

// Empty state hint
"Create todos from a person's detail page or click \"Add todo\""
-> "Maak taken aan vanaf een ledenpagina of klik op \"Taak toevoegen\""

// Confirmation dialog
'Are you sure you want to delete this todo?'
-> 'Weet je zeker dat je deze taak wilt verwijderen?'

// TodoItem tooltips
'Reopen todo' -> 'Taak heropenen'
'Mark as complete' -> 'Markeren als afgerond'
'Complete todo' -> 'Taak afronden'
'Edit todo' -> 'Taak bewerken'
'Delete todo' -> 'Taak verwijderen'

// Due date display
'Due:' -> 'Deadline:'
'(overdue)' -> '(te laat)'

// Awaiting indicator
'Waiting since today' -> 'Wacht sinds vandaag'
'Waiting {n}d' -> 'Wacht {n}d'
```

### Translation Mapping - ImportantDateModal.jsx

```jsx
// Source: CONTEXT.md decisions

// Modal headers
"Edit date" -> "Datum bewerken"
"Add date" -> "Datum toevoegen"

// Form labels
"Related people" -> "Gerelateerde personen"
"Date type *" -> "Datumtype *"
"Select a type..." -> "Selecteer een type..."
"Label" -> "Label"
"e.g., Mom's Birthday" -> "bijv. Moeders verjaardag"
"Auto-generated from person and date type. You can customize it."
-> "Automatisch gegenereerd op basis van persoon en type. Je kunt dit aanpassen."
"Date *" -> "Datum *"
"Year unknown" -> "Jaar onbekend"
"Repeats every year" -> "Jaarlijks terugkerend" (per CONTEXT.md)

// Validation messages
"Please select a date type" -> "Selecteer een datumtype"
"Date is required" -> "Datum is verplicht"

// People selector
"Search for people to add..." -> "Zoek leden om toe te voegen..."
"No people found" -> "Geen leden gevonden"

// Buttons
"Cancel" -> "Annuleren"
"Saving..." -> "Opslaan..."
"Save changes" -> "Wijzigingen opslaan"
"Add date" -> "Datum toevoegen"
```

### Translation Mapping - TodoModal.jsx

```jsx
// Source: CONTEXT.md decisions

// Modal headers (getModalTitle function)
"Add todo" -> "Taak toevoegen"
"View todo" -> "Taak bekijken"
"Edit todo" -> "Taak bewerken"

// View mode labels
"Description" -> "Beschrijving"
"Due date" -> "Deadline" (per CONTEXT.md)
"No due date" -> "Geen deadline"
"Notes" -> "Notities"
"Related people" -> "Gerelateerde personen"

// View mode buttons
"Close" -> "Sluiten"
"Edit" -> "Bewerken"

// Edit mode labels
"Description" -> "Beschrijving"
"What needs to be done?" -> "Wat moet er gedaan worden?"
"Due date (optional)" -> "Deadline (optioneel)"
"Notes (optional)" -> "Notities (optioneel)"
"Add detailed notes..." -> "Voeg gedetailleerde notities toe..."
"Related people" -> "Gerelateerde personen"
"Add person" -> "Lid toevoegen"
"Search people..." -> "Leden zoeken..."
"Loading..." -> "Laden..."
"No people found" -> "Geen leden gevonden"

// Status hint
"Tip: Use the status buttons on the todo list to change between Open, Awaiting, and Completed."
-> "Tip: Gebruik de statusknoppen in de takenlijst om te wisselen tussen Te doen, Openstaand en Afgerond."

// Buttons
"Cancel" -> "Annuleren"
"Saving..." -> "Opslaan..."
"Adding..." -> "Toevoegen..."
"Save" -> "Opslaan"
"Add todo" -> "Taak toevoegen"
```

### Translation Mapping - GlobalTodoModal.jsx

```jsx
// Source: CONTEXT.md decisions

// Modal header
"Add todo" -> "Taak toevoegen"

// Form labels
"People *" -> "Leden *"
"Add person" -> "Lid toevoegen"
"Search people..." -> "Leden zoeken..."
"Loading..." -> "Laden..."
"No people found" -> "Geen leden gevonden"
"Description *" -> "Beschrijving *"
"What needs to be done?" -> "Wat moet er gedaan worden?"
"Due date (optional)" -> "Deadline (optioneel)"
"Notes (optional)" -> "Notities (optioneel)"
"Add detailed notes..." -> "Voeg gedetailleerde notities toe..."

// Buttons
"Cancel" -> "Annuleren"
"Adding..." -> "Toevoegen..."
"Add todo" -> "Taak toevoegen"
```

### Translation Mapping - CompleteTodoModal.jsx

```jsx
// Source: CONTEXT.md decisions

// Modal header
"Complete todo" -> "Taak afronden"

// Question
"What's the status of this todo?" -> "Wat is de status van deze taak?"

// Option 1: Awaiting
"Awaiting response" -> "Openstaand"
"You did your part, waiting for their reply" -> "Je hebt je deel gedaan, wachten op hun reactie"

// Option 2: Complete
"Complete" -> "Afronden"
"Mark the todo as fully done" -> "Markeer de taak als volledig afgerond"

// Option 3: Complete & log activity
"Complete & log activity" -> "Afronden & activiteit loggen"
"Record this as an activity on the timeline" -> "Leg dit vast als activiteit op de tijdlijn"

// Button
"Cancel" -> "Annuleren"
```

### Translation Mapping - useDocumentTitle.js

```jsx
// Route titles (useRouteTitle function)
// /dates routes
"Events" -> "Datums"
"New date" -> "Nieuwe datum"
"Edit date" -> "Datum bewerken"

// Note: Todos are handled via useDocumentTitle() calls in TodosList.jsx
// No explicit /todos handling needed in useRouteTitle
```

### Date Type Mapping Object

```jsx
// Create a mapping object for date type translations
// Place in a utils file or at top of DatesList.jsx

const DATE_TYPE_LABELS = {
  // Core types (per CONTEXT.md)
  'birthday': 'Verjaardag',
  'wedding': 'Trouwdag',
  'marriage': 'Huwelijk',
  'memorial': 'Herdenking',
  'other': 'Overig',

  // Additional types from taxonomy
  'first-met': 'Eerste ontmoeting',
  'new-relationship': 'Nieuwe relatie',
  'engagement': 'Verloving',
  'expecting-a-baby': 'Verwacht een baby',
  'new-child': 'Nieuw kind',
  'new-family-member': 'Nieuw familielid',
  'new-pet': 'Nieuw huisdier',
  'end-of-relationship': 'Einde relatie',
  'loss-of-a-loved-one': 'Verlies van een geliefde',
  'new-job': 'Nieuwe baan',
  'retirement': 'Pensioen',
  'new-school': 'Nieuwe school',
  'study-abroad': 'Studie in buitenland',
  'volunteer-work': 'Vrijwilligerswerk',
  'published-book-or-paper': 'Boek of paper gepubliceerd',
  'military-service': 'Militaire dienst',
  'moved': 'Verhuisd',
  'bought-a-home': 'Huis gekocht',
  'home-improvement': 'Verbouwing',
  'holidays': 'Vakantie',
  'new-vehicle': 'Nieuw voertuig',
  'new-roommate': 'Nieuwe huisgenoot',
  'overcame-an-illness': 'Ziekte overwonnen',
  'quit-a-habit': 'Gewoonte gestopt',
  'new-eating-habits': 'Nieuwe eetgewoontes',
  'weight-loss': 'Afgevallen',
  'surgery': 'Operatie',
  'new-sport': 'Nieuwe sport',
  'new-hobby': 'Nieuwe hobby',
  'new-instrument': 'Nieuw instrument',
  'new-language': 'Nieuwe taal',
  'travel': 'Reis',
  'achievement-or-award': 'Prestatie of prijs',
  'first-word': 'Eerste woord',
  'first-kiss': 'Eerste kus',
  'died': 'Overleden',
};

// Helper function to translate date type
const getDateTypeLabel = (dateType) => {
  if (!dateType) return '';
  // dateType could be a name or slug
  const normalized = dateType.toLowerCase().replace(/\s+/g, '-');
  return DATE_TYPE_LABELS[normalized] || DATE_TYPE_LABELS[dateType.toLowerCase()] || dateType;
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| English UI | Dutch UI | Phase 101-103 | Full Dutch experience |
| "Awaiting" status | "Openstaand" | CONTEXT.md decision | Consistent with Dutch UX |
| "Due date" | "Deadline" | CONTEXT.md decision | More natural Dutch term |

**Deprecated/outdated:**
- None for this phase

## Open Questions

1. **Date Type Taxonomy Terms**
   - What we know: Date types come from WordPress taxonomy, displayed via REST API
   - What's unclear: Whether we should also update the database terms or only use frontend mapping
   - Recommendation: Use frontend mapping only - keeps all translation in React code, avoids database changes

2. **Auto-Generated Date Titles**
   - What we know: ImportantDateModal auto-generates titles like "Wedding of John & Jane" or "John's Birthday"
   - What's unclear: Should these auto-generated titles be in Dutch?
   - Recommendation: Keep auto-generation logic but translate the template strings ("Wedding of" -> "Trouwdag van", "'s Birthday" -> "'s verjaardag")

## Sources

### Primary (HIGH confidence)
- `src/pages/Dates/DatesList.jsx` - Current implementation (verified English strings)
- `src/pages/Todos/TodosList.jsx` - Current implementation (verified English strings)
- `src/components/ImportantDateModal.jsx` - Current implementation (verified English strings)
- `src/components/Timeline/TodoModal.jsx` - Current implementation (verified English strings)
- `src/components/Timeline/GlobalTodoModal.jsx` - Current implementation (verified English strings)
- `src/components/Timeline/CompleteTodoModal.jsx` - Current implementation (verified English strings)
- `src/hooks/useDocumentTitle.js` - Document title utility (verified English strings)
- `includes/class-taxonomies.php` - Date type taxonomy definition
- `.planning/phases/104-datums-taken/104-CONTEXT.md` - User decisions and terminology
- `.planning/phases/103-teams-commissies/103-RESEARCH.md` - Reference for translation patterns

### Secondary (MEDIUM confidence)
- `src/components/layout/Layout.jsx` - Navigation already translated ("Datums", "Taken")
- `src/pages/Dashboard.jsx` - Reference for some already-translated todo strings

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No changes needed, existing stack
- Architecture: HIGH - Direct string replacement, established pattern
- Pitfalls: HIGH - Based on Phase 103 experience and code review
- Code examples: HIGH - Derived from actual codebase + CONTEXT.md

**Files to translate (7 total):**
1. `src/pages/Dates/DatesList.jsx` (~187 lines)
2. `src/pages/Todos/TodosList.jsx` (~497 lines)
3. `src/components/ImportantDateModal.jsx` (~411 lines)
4. `src/components/Timeline/TodoModal.jsx` (~461 lines)
5. `src/components/Timeline/GlobalTodoModal.jsx` (~325 lines)
6. `src/components/Timeline/CompleteTodoModal.jsx` (~78 lines)
7. `src/hooks/useDocumentTitle.js` (~92 lines)

**Research date:** 2026-01-25
**Valid until:** 2026-02-25 (stable - translation patterns don't change)
