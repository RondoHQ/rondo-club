# Phase 101: Dashboard - Research

**Researched:** 2026-01-25
**Domain:** UI string localization (Dutch translation - Dashboard page)
**Confidence:** HIGH

## Summary

This phase translates all dashboard UI elements from English to Dutch in the Stadion CRM application. Following the pattern established in Phase 100 (Navigation & Layout), this is a direct string replacement task focused on a single file: `src/pages/Dashboard.jsx` and its dependency `src/components/DashboardCustomizeModal.jsx`.

The dashboard contains five distinct areas requiring translation:
1. **Stat cards** (5 cards) - Summary statistics labels
2. **Widget titles** (8 widgets) - Section headers for dashboard cards
3. **Empty states** (multiple) - Messages when data is empty
4. **Error messages** (2 states) - Network/API error messages
5. **Button/interaction text** - Action buttons and labels

The approach follows Phase 100's pattern: direct string replacement with no i18n library needed, as this is a single-locale (Dutch only) application. Date formatting is already Dutch via the existing `src/utils/dateFormat.js` wrapper around date-fns with nl locale.

**Primary recommendation:** Replace all English strings in Dashboard.jsx and DashboardCustomizeModal.jsx with Dutch equivalents as specified in CONTEXT.md. This is a straightforward find-and-replace operation following established patterns from Phase 100.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | UI framework | Already in use |
| date-fns | ^3.2.0 | Date formatting | Already configured with Dutch locale in dateFormat.js |
| Lucide React | ^0.309.0 | Icons | Already in use, icons don't need translation |

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
├── pages/
│   └── Dashboard.jsx           # Main dashboard with stat cards, widgets, empty states
├── components/
│   └── DashboardCustomizeModal.jsx  # Dashboard customization modal
└── utils/
    └── dateFormat.js           # Already configured with Dutch locale (Phase 99)
```

### Pattern 1: Direct String Replacement
**What:** Replace English strings with Dutch equivalents directly in JSX
**When to use:** Single-locale applications where no language switching is needed
**Example:**
```javascript
// BEFORE
<StatCard title="Total people" value={stats?.total_people || 0} icon={Users} href="/people" />

// AFTER
<StatCard title="Totaal leden" value={stats?.total_people || 0} icon={Users} href="/people" />
```

### Pattern 2: Consistent Terminology with Phase 100
**What:** Use exact same Dutch terms as established in Phase 100 for consistency
**When to use:** All user-facing text that references navigation items
**Example:**
```javascript
// Phase 100 established: "Leden", "Teams", "Datums", "Taken"
// Dashboard must use the exact same terms:
{ name: 'Leden', href: '/people' }  // Navigation (Phase 100)
title="Totaal leden"                // Dashboard stat (Phase 101)
```

### Pattern 3: Informal Dutch with "je" Pronoun
**What:** Use informal Dutch (je/jij) in all user-facing messages
**When to use:** Empty states, welcome messages, error messages
**Example:**
```javascript
// Empty state messages use "je" form:
"Je hebt nog geen taken"           // Not "U heeft geen taken"
"Voeg je eerste lid toe"           // Not "Voeg uw eerste lid toe"
```

### Pattern 4: Warm & Helpful Empty States
**What:** Empty states provide guidance and include action suggestions
**When to use:** When displaying empty data sections
**Example:**
```javascript
// BEFORE
<p>No open todos</p>

// AFTER
<p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
  Nog geen taken. <Link to="/todos" className="text-accent-600 dark:text-accent-400">Maak je eerste taak</Link>
</p>
```

### Anti-Patterns to Avoid
- **Mixing English and Dutch randomly:** Keep consistent - only use English for approved loan words (Dashboard, Feedback, Workspaces)
- **Using formal Dutch:** Stick with informal "je" form, not "u" form
- **Inconsistent terminology:** Must match Phase 100 navigation terms exactly (Leden, Teams, Datums, Taken)
- **Translating "Dashboard":** This is an approved loan word, keep it as "Dashboard"

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dutch date formatting | Custom formatters | date-fns with nl locale (already in dateFormat.js) | Already configured in Phase 99 |
| N/A | - | - | This phase is simple string replacement, no complex solutions needed |

**Key insight:** Date formatting is already Dutch via the existing `src/utils/dateFormat.js` wrapper. All date-related strings like "Vandaag" (Today) are already correctly translated through date-fns locale support.

## Common Pitfalls

### Pitfall 1: Terminology Inconsistency with Phase 100
**What goes wrong:** Using different Dutch terms than Phase 100 (e.g., "Personen" instead of "Leden")
**Why it happens:** Not checking Phase 100's established terminology
**How to avoid:** Cross-reference all terms with Phase 100 navigation labels and CONTEXT.md
**Warning signs:** "Personen", "Organisaties", "Todo's", "Belangrijke datums" appearing instead of "Leden", "Teams", "Taken", "Datums"

### Pitfall 2: Forgetting "Openstaand" vs "Wachtend"
**What goes wrong:** Using "Wachtend" for the awaiting stat instead of "Openstaand"
**Why it happens:** Direct English translation doesn't match the context decision
**How to avoid:** Per CONTEXT.md: use "Openstaand" for pending/waiting items, not "Wachtend"
**Warning signs:** "Wachtend" appearing in stat cards instead of "Openstaand"

### Pitfall 3: Translating "Dashboard"
**What goes wrong:** Changing "Dashboard" to "Controlepaneel" or similar Dutch word
**Why it happens:** Over-translation, trying to make everything Dutch
**How to avoid:** Follow CONTEXT.md - Dashboard is an approved loan word and stays English
**Warning signs:** Any Dutch translation of "Dashboard" appearing

### Pitfall 4: Missing Embedded Strings
**What goes wrong:** Forgetting to translate strings inside template literals, error messages, or conditional rendering
**Why it happens:** Some strings are less visible in the code (e.g., ternary operators, template strings)
**How to avoid:** Systematic search through all JSX output, including conditionals
**Warning signs:** "All day", "overdue", "Searching...", "Saving..." appearing untranslated

### Pitfall 5: Gender Agreement Errors
**What goes wrong:** Using "Nieuw" with feminine nouns that need "Nieuwe"
**Why it happens:** Dutch grammatical gender is complex
**How to avoid:** Per CONTEXT.md decisions: "Nieuw lid", "Nieuw team", "Nieuwe taak", "Nieuwe datum"
**Warning signs:** "Nieuw datum" or "Nieuwe team" (incorrect gender agreement)

### Pitfall 6: Formal vs Informal Tone
**What goes wrong:** Using formal "u" form or formal phrasing in messages
**Why it happens:** Business context might suggest formality
**How to avoid:** All messages use informal "je/jij" per CONTEXT.md
**Warning signs:** "U heeft", "Uw dashboard", "voegt u toe" appearing instead of "Je hebt", "Jouw dashboard", "voeg je toe"

## Code Examples

Verified patterns from CONTEXT.md decisions:

### Stat Card Labels Translation
```javascript
// Source: Dashboard.jsx lines 614-644, 659-665
// BEFORE:
<StatCard title="Total people" value={stats?.total_people || 0} icon={Users} href="/people" />
<StatCard title="Organizations" value={stats?.total_teams || 0} icon={Building2} href="/teams" />
<StatCard title="Events" value={stats?.total_dates || 0} icon={Calendar} href="/dates" />
<StatCard title="Open todos" value={stats?.open_todos_count || 0} icon={CheckSquare} href="/todos" />
<StatCard title="Awaiting" value={stats?.awaiting_todos_count || 0} icon={Clock} href="/todos?status=awaiting" />

// AFTER:
<StatCard title="Totaal leden" value={stats?.total_people || 0} icon={Users} href="/people" />
<StatCard title="Teams" value={stats?.total_teams || 0} icon={Building2} href="/teams" />
<StatCard title="Evenementen" value={stats?.total_dates || 0} icon={Calendar} href="/dates" />
<StatCard title="Open taken" value={stats?.open_todos_count || 0} icon={CheckSquare} href="/todos" />
<StatCard title="Openstaand" value={stats?.awaiting_todos_count || 0} icon={Clock} href="/todos?status=awaiting" />
```

### Widget Title Translation
```javascript
// Source: Dashboard.jsx lines 667-837
// BEFORE:
Upcoming reminders
Open todos
Awaiting response
Today's meetings
Recently contacted
Recently edited
Favorites

// AFTER:
Komende herinneringen
Open taken
Openstaand                    // Note: was "Awaiting response", now "Openstaand" per CONTEXT.md
Afspraken vandaag
Recent gecontacteerd
Recent bewerkt
Favorieten
```

### Empty State Messages Translation
```javascript
// Source: Dashboard.jsx lines 308-337
// BEFORE:
<h2 className="text-2xl font-semibold text-gray-900 dark:text-gray-50 mb-2">Welcome to {APP_NAME}!</h2>
<p className="text-gray-600 dark:text-gray-300 mb-8 max-w-md mx-auto">
  Get started by adding your first contact, organization, or important date. Your dashboard will populate as you add more information.
</p>
<Plus className="w-5 h-5 mr-2" />
Add Your First Person
<Plus className="w-5 h-5 mr-2" />
Add Your First Organization

// AFTER:
<h2 className="text-2xl font-semibold text-gray-900 dark:text-gray-50 mb-2">Welkom bij {APP_NAME}!</h2>
<p className="text-gray-600 dark:text-gray-300 mb-8 max-w-md mx-auto">
  Begin met het toevoegen van je eerste lid, team of datum. Je dashboard vult zich naarmate je meer informatie toevoegt.
</p>
<Plus className="w-5 h-5 mr-2" />
Voeg je eerste lid toe
<Plus className="w-5 h-5 mr-2" />
Voeg je eerste team toe
```

### Widget Empty States Translation
```javascript
// Source: Dashboard.jsx lines 682, 702, 722, 794, 814, 833
// BEFORE:
No upcoming reminders
No open todos
No awaiting responses
No recent activities yet
No people yet. <Link>Add someone</Link>
No favorites yet

// AFTER:
Geen komende herinneringen
Geen open taken
Geen openstaande reacties
Nog geen recente activiteiten
Nog geen leden. <Link>Voeg iemand toe</Link>
Nog geen favorieten
```

### Error Messages Translation
```javascript
// Source: Dashboard.jsx lines 578-603
// BEFORE (Network error):
<p className="font-medium mb-1">Failed to load dashboard data</p>
<p className="text-sm text-gray-600 dark:text-gray-300">
  Please check your connection and try refreshing the page.
</p>

// AFTER:
<p className="font-medium mb-1">Dashboard data kon niet worden geladen</p>
<p className="text-sm text-gray-600 dark:text-gray-300">
  Controleer je verbinding en ververs de pagina.
</p>

// BEFORE (Generic error):
<p className="font-medium mb-1">Unable to load dashboard</p>
<p className="text-sm text-gray-600 dark:text-gray-300">
  {error?.response?.data?.message || 'An error occurred while loading your data.'}
</p>

// AFTER:
<p className="font-medium mb-1">Dashboard kon niet worden geladen</p>
<p className="text-sm text-gray-600 dark:text-gray-300">
  {error?.response?.data?.message || 'Er is een fout opgetreden bij het laden van je gegevens.'}
</p>
```

### Meeting Card "All day" Translation
```javascript
// Source: Dashboard.jsx line 260
// BEFORE:
{meeting.all_day ? 'All day' : <>{formattedStartTime} - <br />{formattedEndTime}</>}

// AFTER:
{meeting.all_day ? 'Hele dag' : <>{formattedStartTime} - <br />{formattedEndTime}</>}
```

### Todo "overdue" Translation
```javascript
// Source: Dashboard.jsx line 183
// BEFORE:
{isOverdue && <div className="text-red-600 dark:text-red-300">overdue</div>}

// AFTER:
{isOverdue && <div className="text-red-600 dark:text-red-300">achterstallig</div>}
```

### Alert Message Translation
```javascript
// Source: Dashboard.jsx line 514
// BEFORE:
alert('Failed to create activity. Please try again.');

// AFTER:
alert('Activiteit aanmaken mislukt. Probeer het opnieuw.');
```

### DashboardCustomizeModal Translation
```javascript
// Source: DashboardCustomizeModal.jsx lines 24-32, 177-221, 237-238
// BEFORE:
CARD_DEFINITIONS = {
  'stats': { label: 'Statistics', description: 'People, organizations, events counts' },
  'reminders': { label: 'Upcoming Reminders', description: 'Important dates coming up' },
  'todos': { label: 'Open Todos', description: 'Tasks to complete' },
  'awaiting': { label: 'Awaiting Response', description: 'Waiting for replies' },
  'meetings': { label: "Today's Meetings", description: 'Calendar events for today' },
  'recent-contacted': { label: 'Recently Contacted', description: 'Recent activity contacts' },
  'recent-edited': { label: 'Recently Edited', description: 'Recently modified people' },
  'favorites': { label: 'Favorites', description: 'Starred contacts' },
}

<h2>Customize Dashboard</h2>
<p>Show, hide, and reorder cards</p>
<button aria-label="Drag to reorder">...</button>
<button>Reset to defaults</button>
<button>Cancel</button>
<button>{isSaving ? 'Saving...' : 'Save'}</button>

// AFTER:
CARD_DEFINITIONS = {
  'stats': { label: 'Statistieken', description: 'Aantallen van leden, teams en evenementen' },
  'reminders': { label: 'Komende herinneringen', description: 'Aankomende belangrijke datums' },
  'todos': { label: 'Open taken', description: 'Taken om af te ronden' },
  'awaiting': { label: 'Openstaand', description: 'Wachten op reacties' },
  'meetings': { label: 'Afspraken vandaag', description: 'Agenda-items voor vandaag' },
  'recent-contacted': { label: 'Recent gecontacteerd', description: 'Contacten met recente activiteit' },
  'recent-edited': { label: 'Recent bewerkt', description: 'Recent gewijzigde leden' },
  'favorites': { label: 'Favorieten', description: 'Favoriete contacten' },
}

<h2>Dashboard aanpassen</h2>
<p>Toon, verberg en sorteer kaarten</p>
<button aria-label="Slepen om te sorteren">...</button>
<button>Herstel standaard</button>
<button>Annuleer</button>
<button>{isSaving ? 'Bezig met opslaan...' : 'Opslaan'}</button>
```

### Meeting Empty States Translation
```javascript
// Source: Dashboard.jsx lines 773-777
// BEFORE:
<p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
  {isToday(selectedDate)
    ? 'No meetings scheduled for today'
    : `No meetings on ${format(selectedDate, 'd MMMM')}`}
</p>

// AFTER:
<p className="p-4 text-sm text-gray-500 dark:text-gray-400 text-center">
  {isToday(selectedDate)
    ? 'Geen afspraken gepland voor vandaag'
    : `Geen afspraken op ${format(selectedDate, 'd MMMM')}`}
</p>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| i18n libraries for all localization | Direct strings for single-locale apps | Always valid | Simpler code, no runtime overhead |
| Extract all strings to constants | Inline strings for single-locale apps | Always valid | Better DX, easier to read code |
| English-first with translation layer | Dutch-native for Dutch-only app | Phase 100 | Eliminates translation layer complexity |

**Deprecated/outdated:**
- N/A - Direct string replacement is the appropriate approach for this use case

## Open Questions

Things that couldn't be fully resolved:

1. **"Today's meetings" vs "Afspraken vandaag"**
   - What we know: CONTEXT.md specifies "Afspraken vandaag" in requirements
   - What's unclear: Whether to use possessive form or not
   - Recommendation: Use "Afspraken vandaag" (no possessive) per CONTEXT.md, matches Dutch conventions better

2. **Empty state action button style**
   - What we know: Phase 100 established imperative style for buttons
   - What's unclear: Whether empty state CTAs should use imperative "Voeg toe" or full phrase "Voeg je eerste lid toe"
   - Recommendation: Use full friendly phrase "Voeg je eerste lid toe" for warmth, matches CONTEXT.md's warm & helpful style

3. **"Organizations" in DashboardCustomizeModal description**
   - What we know: Navigation uses "Teams", stat card says "Teams"
   - What's unclear: Whether descriptions should also say "teams" or can be more descriptive
   - Recommendation: Use "teams" for consistency with rest of UI

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/Dashboard.jsx` - Source code analysis (947 lines)
- `/Users/joostdevalk/Code/stadion/src/components/DashboardCustomizeModal.jsx` - Source code analysis (245 lines)
- `/Users/joostdevalk/Code/stadion/.planning/phases/101-dashboard/101-CONTEXT.md` - User decisions and terminology
- `/Users/joostdevalk/Code/stadion/.planning/phases/100-navigation-layout/100-CONTEXT.md` - Phase 100 terminology for consistency
- `/Users/joostdevalk/Code/stadion/.planning/phases/100-navigation-layout/100-RESEARCH.md` - Phase 100 patterns

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/src/utils/dateFormat.js` - Verified Dutch date formatting already configured (Phase 99)

### Tertiary (LOW confidence)
- N/A

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No new libraries needed, pure string replacement following Phase 100 pattern
- Architecture: HIGH - Two file changes, no structural modifications, following established pattern
- Pitfalls: HIGH - Based on CONTEXT.md decisions, Phase 100 learnings, and Dutch language rules

**Research date:** 2026-01-25
**Valid until:** ~90 days (stable UI, unlikely to change significantly)
