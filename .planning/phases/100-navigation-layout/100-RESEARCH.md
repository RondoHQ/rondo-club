# Phase 100: Navigation & Layout - Research

**Researched:** 2026-01-25
**Domain:** UI string localization (Dutch translation)
**Confidence:** HIGH

## Summary

This phase involves translating navigation and layout UI elements from English to Dutch in the Stadion CRM application. All navigation elements are consolidated in a single file: `src/components/layout/Layout.jsx`. The changes are simple string replacements with no structural modifications required.

The file contains four distinct areas requiring translation:
1. **Navigation array** (lines 39-49) - Sidebar menu labels
2. **Sidebar logout button** (line 114) - "Log Out" text
3. **UserMenu component** (lines 196-207) - Profile and admin links
4. **QuickAddMenu component** (lines 492-519) - Quick action buttons
5. **SearchModal component** (lines 307, 322, 335, 375) - Placeholder and labels
6. **Header getPageTitle function** (lines 534-541) - Page titles in header

The approach is direct string replacement following the decisions made in CONTEXT.md. No internationalization (i18n) library is needed since this is a single-locale application (Dutch only).

**Primary recommendation:** Replace all English strings in Layout.jsx with Dutch equivalents as specified in CONTEXT.md. This is a straightforward find-and-replace operation with no architectural changes needed.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | UI framework | Already in use |
| Lucide React | - | Icons | Already in use, icons don't need translation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| N/A | - | - | No additional libraries needed for this simple translation task |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Direct string replacement | react-i18next | Overkill for single-locale app, adds complexity |
| Direct string replacement | FormatJS/react-intl | Same as above, unnecessary for Dutch-only |
| Hardcoded strings | Constants file | Could centralize strings but adds indirection for no benefit in single-locale app |

**Installation:**
```bash
# No installation needed - this is a pure string replacement task
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── components/
│   └── layout/
│       └── Layout.jsx    # Contains all navigation elements to translate
└── ...
```

### Pattern 1: Direct String Replacement
**What:** Replace English strings with Dutch equivalents directly in JSX
**When to use:** Single-locale applications where no language switching is needed
**Example:**
```javascript
// BEFORE
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'People', href: '/people', icon: Users },
  // ...
];

// AFTER
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },  // Keep English (loan word)
  { name: 'Leden', href: '/people', icon: Users },
  // ...
];
```

### Pattern 2: Consistent Tone and Style
**What:** Use informal Dutch (je/jij) and imperative verbs for actions
**When to use:** All user-facing text
**Example:**
```javascript
// Navigation: Use nouns only (no verbs)
{ name: 'Leden', href: '/people' }  // Not "Bekijk leden"
{ name: 'Instellingen', href: '/settings' }  // Not "Wijzig instellingen"

// Buttons: Use imperative (command form)
<button>Nieuw lid</button>  // Not "Lid toevoegen" or infinitive "Toevoegen"
```

### Anti-Patterns to Avoid
- **Mixing English and Dutch randomly:** Keep consistent - only use English for approved loan words (Dashboard, Feedback, Workspaces)
- **Using formal Dutch:** Stick with informal "je" form, not "u" form
- **Using infinitives for buttons:** Use imperative "Sla op" not infinitive "Opslaan"
- **Abbreviating for space:** Allow UI to adapt to longer Dutch text, don't truncate

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| N/A | - | - | This phase is simple string replacement, no complex solutions needed |

**Key insight:** This phase intentionally avoids complexity. No i18n library, no constants file, no string extraction. Direct replacement is the correct approach for a single-locale application.

## Common Pitfalls

### Pitfall 1: Inconsistent Loan Words
**What goes wrong:** Translating words that should remain English (Dashboard, Feedback, Workspaces)
**Why it happens:** Over-translation, trying to make everything Dutch
**How to avoid:** Follow CONTEXT.md exactly - only Dashboard, Feedback, and Workspaces stay English
**Warning signs:** "Controlepaneel" instead of "Dashboard"

### Pitfall 2: Wrong Verb Form
**What goes wrong:** Using infinitive ("Opslaan") or noun-based commands ("Lid toevoegen") instead of imperative ("Sla op", "Nieuw lid")
**Why it happens:** Direct translation from English patterns
**How to avoid:** Use imperative form for actions, simple nouns for navigation
**Warning signs:** "Toevoegen" appearing as button text

### Pitfall 3: Missing Translation Points
**What goes wrong:** Forgetting to translate a string, leaving English mixed with Dutch
**Why it happens:** Some strings are in less obvious places (aria-labels, titles, placeholders)
**How to avoid:** Systematic search for all user-visible strings in Layout.jsx
**Warning signs:** "Search..." appearing alongside Dutch navigation

### Pitfall 4: Incorrect Gender Agreement
**What goes wrong:** Using "Nieuw" with feminine nouns that need "Nieuwe"
**Why it happens:** Dutch grammatical gender is complex
**How to avoid:** Per CONTEXT.md decisions: "Nieuw lid", "Nieuw team", "Nieuwe taak", "Nieuwe datum"
**Warning signs:** "Nieuw datum" (incorrect) instead of "Nieuwe datum" (correct)

### Pitfall 5: Translating Internal Labels
**What goes wrong:** Translating switch case values or internal identifiers
**Why it happens:** Confusion between user-facing strings and code
**How to avoid:** Only translate strings that appear in JSX/HTML output, not logic
**Warning signs:** Breaking navigation because internal values were changed

## Code Examples

Verified patterns from CONTEXT.md decisions:

### Navigation Array Translation
```javascript
// Source: Layout.jsx lines 39-49
// BEFORE:
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'People', href: '/people', icon: Users },
  { name: 'Teams', href: '/teams', icon: Building2 },
  { name: 'Commissies', href: '/commissies', icon: UsersRound },
  { name: 'Dates', href: '/dates', icon: Calendar },
  { name: 'Todos', href: '/todos', icon: CheckSquare },
  { name: 'Workspaces', href: '/workspaces', icon: UsersRound },
  { name: 'Feedback', href: '/feedback', icon: MessageSquare },
  { name: 'Settings', href: '/settings', icon: Settings },
];

// AFTER:
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },      // Keep English
  { name: 'Leden', href: '/people', icon: Users },
  { name: 'Teams', href: '/teams', icon: Building2 },
  { name: 'Commissies', href: '/commissies', icon: UsersRound },
  { name: 'Datums', href: '/dates', icon: Calendar },
  { name: 'Taken', href: '/todos', icon: CheckSquare },
  { name: 'Workspaces', href: '/workspaces', icon: UsersRound },  // Keep English
  { name: 'Feedback', href: '/feedback', icon: MessageSquare },    // Keep English
  { name: 'Instellingen', href: '/settings', icon: Settings },
];
```

### Sidebar Logout Translation
```javascript
// Source: Layout.jsx line 114
// BEFORE:
<LogOut className="w-5 h-5 mr-3" />
Log Out

// AFTER:
<LogOut className="w-5 h-5 mr-3" />
Uitloggen
```

### UserMenu Translation
```javascript
// Source: Layout.jsx lines 196-207
// BEFORE:
<span className="hidden md:inline">Edit profile</span>
<span className="hidden md:inline">WordPress admin</span>

// AFTER:
<span className="hidden md:inline">Profiel bewerken</span>
<span className="hidden md:inline">WordPress beheer</span>
```

### QuickAddMenu Translation
```javascript
// Source: Layout.jsx lines 492-519
// BEFORE:
New Person
New Organization
New Todo
New Date

// AFTER:
Nieuw lid
Nieuw team
Nieuwe taak
Nieuwe datum
```

### SearchModal Translation
```javascript
// Source: Layout.jsx line 307
// BEFORE:
placeholder="Search people & organizations..."

// AFTER:
placeholder="Zoek leden en teams..."

// Source: Layout.jsx line 322
// BEFORE:
<p className="text-sm">Type at least 2 characters to search</p>

// AFTER:
<p className="text-sm">Typ minimaal 2 tekens om te zoeken</p>

// Source: Layout.jsx line 327
// BEFORE:
<p className="mt-3 text-sm text-gray-500">Searching...</p>

// AFTER:
<p className="mt-3 text-sm text-gray-500">Zoeken...</p>

// Source: Layout.jsx line 335 (section header)
// BEFORE:
People

// AFTER:
Leden

// Source: Layout.jsx line 375 (section header)
// BEFORE:
Organizations

// AFTER:
Teams

// Source: Layout.jsx line 413
// BEFORE:
<p className="text-sm">No results found for "{searchQuery}"</p>

// AFTER:
<p className="text-sm">Geen resultaten gevonden voor "{searchQuery}"</p>
```

### Header Page Title Translation
```javascript
// Source: Layout.jsx lines 532-543
// BEFORE:
const getPageTitle = () => {
  const path = location.pathname;
  if (path === '/') return 'Dashboard';
  if (path.startsWith('/people')) return 'People';
  if (path.startsWith('/teams')) return 'Teams';
  if (path.startsWith('/commissies')) return 'Commissies';
  if (path.startsWith('/dates')) return 'Important Dates';
  if (path.startsWith('/todos')) return 'Todos';
  if (path.startsWith('/workspaces')) return 'Workspaces';
  if (path.startsWith('/settings')) return 'Settings';
  return '';
};

// AFTER:
const getPageTitle = () => {
  const path = location.pathname;
  if (path === '/') return 'Dashboard';           // Keep English
  if (path.startsWith('/people')) return 'Leden';
  if (path.startsWith('/teams')) return 'Teams';
  if (path.startsWith('/commissies')) return 'Commissies';
  if (path.startsWith('/dates')) return 'Datums';
  if (path.startsWith('/todos')) return 'Taken';
  if (path.startsWith('/workspaces')) return 'Workspaces';  // Keep English
  if (path.startsWith('/settings')) return 'Instellingen';
  return '';
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| i18n libraries for all localization | Direct strings for single-locale apps | Always valid | Simpler code, no runtime overhead |
| Extract all strings to constants | Inline strings for single-locale apps | Always valid | Better DX, easier to read code |

**Deprecated/outdated:**
- N/A - Direct string replacement is the appropriate approach for this use case

## Open Questions

Things that couldn't be fully resolved:

1. **Sidebar getCounts function internal switch values**
   - What we know: The switch cases use 'People', 'Organizations', 'Dates' to match navigation item names
   - What's unclear: Whether these need to change when navigation names change to Dutch
   - Recommendation: Update switch case values to match the new Dutch navigation names ('Leden', 'Teams', 'Datums')

2. **aria-label attributes**
   - What we know: There are aria-label="User menu" and aria-label="Quick add" attributes
   - What's unclear: Whether these should be translated for Dutch screen reader users
   - Recommendation: Translate for consistency (aria-label="Gebruikersmenu", aria-label="Snelle actie")

3. **title attributes**
   - What we know: There's title="Search (Cmd+K)" and title="Quick add"
   - What's unclear: Exact Dutch phrasing
   - Recommendation: "Zoeken (Cmd+K)" and "Snelle actie"

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx` - Source code analysis
- `/Users/joostdevalk/Code/rondo/rondo-club/.planning/phases/100-navigation-layout/100-CONTEXT.md` - User decisions

### Secondary (MEDIUM confidence)
- Phase 99 research - Established pattern for Dutch localization approach

### Tertiary (LOW confidence)
- N/A

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No new libraries needed, pure string replacement
- Architecture: HIGH - Single file change, no structural modifications
- Pitfalls: HIGH - Based on CONTEXT.md decisions and Dutch language rules

**Research date:** 2026-01-25
**Valid until:** ~90 days (stable UI, unlikely to change significantly)
