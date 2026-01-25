# Phase 105: Instellingen (Settings) - Research

**Researched:** 2026-01-25
**Domain:** React UI translation (Dutch localization)
**Confidence:** HIGH

## Summary

This phase involves translating all Settings pages from English to Dutch, following the established translation patterns from Phases 101-104. The research confirms this is a straightforward string replacement task with no library dependencies or architectural changes required.

The Settings module is the largest translation scope so far, consisting of:
- Main `Settings.jsx` file (~3300 lines) with 6 main tabs and multiple subtabs
- 5 subpage components: Labels.jsx, UserApproval.jsx, RelationshipTypes.jsx, CustomFields.jsx, FeedbackManagement.jsx
- Each tab contains forms, modals, status indicators, and help text

The navigation sidebar already shows "Instellingen" (translated in Phase 100), so this phase focuses on all content within the Settings pages.

**Primary recommendation:** Apply direct string replacement across Settings.jsx and all subpage files, following Phase 104 patterns and CONTEXT.md terminology. The main Settings.jsx should be split into multiple translation tasks due to its size.

## Standard Stack

This phase requires no new libraries - it uses the existing React application stack.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.x | UI framework | Already in use |
| React Router | 6.x | Navigation | Already in use |
| TanStack Query | 5.x | Data fetching | Already in use |
| Lucide React | 0.x | Icons | Already in use |

### Supporting
No additional libraries needed for translation work.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Hardcoded strings | i18n library (react-intl, i18next) | Overkill for single-language app; adds complexity |

**Installation:**
No installation required - pure string replacement.

## Architecture Patterns

### Translation Pattern (from Phases 101-104)

The established pattern is direct string replacement in JSX files:

```jsx
// Before (English)
const TABS = [
  { id: 'appearance', label: 'Appearance', icon: Palette },
  { id: 'connections', label: 'Connections', icon: Share2 },
];

// After (Dutch)
const TABS = [
  { id: 'appearance', label: 'Weergave', icon: Palette },
  { id: 'connections', label: 'Koppelingen', icon: Share2 },
];
```

### Files to Modify

```
src/
└── pages/
    └── Settings/
        ├── Settings.jsx            # Main settings (~3300 lines)
        │   ├── TABS config         # Main navigation tabs
        │   ├── CONNECTION_SUBTABS  # Connections subtabs
        │   ├── AppearanceTab       # Color scheme, accent, profile link
        │   ├── CalendarsTab        # Calendar connections
        │   ├── ConnectionsTab      # Container for subtabs
        │   ├── ConnectionsContactsSubtab    # Google Contacts
        │   ├── ConnectionsCardDAVSubtab     # CardDAV
        │   ├── ConnectionsSlackSubtab       # Slack
        │   ├── NotificationsTab    # Email, Slack toggles
        │   ├── DataTab             # Import/Export
        │   ├── APIAccessTab        # Application passwords
        │   ├── AdminTab            # User/config management
        │   ├── AboutTab            # App info
        │   ├── CalDAVModal         # CalDAV connection form
        │   └── EditConnectionModal # Edit calendar connection
        ├── Labels.jsx              # Label management (~333 lines)
        ├── UserApproval.jsx        # User approval (~201 lines)
        ├── RelationshipTypes.jsx   # Relationship types (~524 lines)
        ├── CustomFields.jsx        # Custom field management (~435 lines)
        └── FeedbackManagement.jsx  # Feedback management (~350 lines)
```

### Tab/Subtab Structure

Per CONTEXT.md decisions:

**Main Tabs:**
| English | Dutch | Icon |
|---------|-------|------|
| Appearance | Weergave | Palette |
| Connections | Koppelingen | Share2 |
| Notifications | Meldingen | Bell |
| Data | Gegevens | Database |
| Admin | Beheer | Shield (with visual indicator) |
| About | Info | Info |

**Connections Subtabs:**
| English | Dutch | Notes |
|---------|-------|-------|
| Calendars | Google Agenda | Service name preserved |
| Contacts | Google Contacten | Service name preserved |
| CardDAV | CardDAV | Technical name preserved |
| Slack | Slack | Service name preserved |
| API Access | API-toegang | |

### Document Title Translation

The `useDocumentTitle.js` hook sets browser tab titles. Settings-related titles need translation:
- "Settings" -> "Instellingen"
- "Labels - Settings" -> "Labels - Instellingen"
- "User Approval - Settings" -> "Gebruikers - Instellingen"
- "Relationship Types - Settings" -> "Relatietypes - Instellingen"
- "Custom Fields - Settings" -> "Aangepaste velden - Instellingen"
- "Feedback Management - Settings" -> "Feedback - Instellingen"

### Admin Tab Visual Indicator

Per CONTEXT.md decision, the Admin tab should have a visual indicator showing it's admin-only. Current implementation uses `adminOnly: true` flag but no visual indicator beyond hiding it for non-admins.

**Recommendation:** Add a Lock icon or "Admin" badge next to "Beheer" label for admins.

### Anti-Patterns to Avoid
- **Partial translation:** Don't leave some strings in English - complete each component
- **Inconsistent terminology:** Always use terminology from CONTEXT.md
- **Breaking existing functionality:** Only change string literals, not logic
- **Translating service names:** Keep Google, Slack, CardDAV as-is

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Translation system | i18n framework | Direct string replacement | Single-language app, simple scope |
| Icon badges | Custom implementation | Existing Lucide Lock icon | Already in use |

**Key insight:** The Settings page is large but follows predictable patterns. Each tab/component has similar structures: headers, form labels, buttons, empty states, and status messages.

## Common Pitfalls

### Pitfall 1: Missing Modal Translations
**What goes wrong:** CalDAVModal, EditConnectionModal still show English
**Why it happens:** Modals defined at end of Settings.jsx, easy to miss
**How to avoid:** Explicitly translate all modal components
**Warning signs:** Opening edit modal shows English headers

### Pitfall 2: Inconsistent Toggle Labels
**What goes wrong:** Some toggles say "Enable" others say "Inschakelen"
**Why it happens:** Not following CONTEXT.md verb form pattern
**How to avoid:** Use verb form for toggles per CONTEXT.md: "Meldingen inschakelen", "Agenda synchroniseren"
**Warning signs:** Mixed English/Dutch in toggle descriptions

### Pitfall 3: Missing Confirmation Dialogs
**What goes wrong:** "Are you sure you want to delete..." in English
**Why it happens:** window.confirm() calls scattered throughout
**How to avoid:** Search for window.confirm and confirm( in each file
**Warning signs:** Confirmation popups show English

### Pitfall 4: Status Indicators Not Translated
**What goes wrong:** "Connected", "Active", "Error" still in English
**Why it happens:** Status strings easy to overlook
**How to avoid:** Per CONTEXT.md: Actief / Inactief / Fout
**Warning signs:** Connection status badges show English

### Pitfall 5: Form Validation Messages
**What goes wrong:** "Please fill in..." validation shows English
**Why it happens:** Error messages in catch blocks easy to miss
**How to avoid:** Search for alert( and setError( calls
**Warning signs:** Form errors show English

### Pitfall 6: Tooltip/Title Attributes
**What goes wrong:** Hover tooltips show English ("Delete", "Edit", "Copy")
**Why it happens:** title="" attributes easy to overlook
**How to avoid:** Search for title= in each file
**Warning signs:** Hovering over buttons shows English tooltips

### Pitfall 7: Loading/Saving States
**What goes wrong:** "Saving...", "Loading...", "Connecting..." in English
**Why it happens:** State-dependent text easy to miss
**How to avoid:** Search for "ing..." patterns
**Warning signs:** Buttons show English during operations

### Pitfall 8: Help Text Paragraphs
**What goes wrong:** Long help text paragraphs still in English
**Why it happens:** Multi-line text easy to skip
**How to avoid:** Translate all <p> content in forms
**Warning signs:** Explanation text under forms in English

## Code Examples

### Translation Mapping - Main TABS Config

```jsx
// Source: CONTEXT.md decisions

// Before
const TABS = [
  { id: 'appearance', label: 'Appearance', icon: Palette },
  { id: 'connections', label: 'Connections', icon: Share2 },
  { id: 'notifications', label: 'Notifications', icon: Bell },
  { id: 'data', label: 'Data', icon: Database },
  { id: 'admin', label: 'Admin', icon: Shield, adminOnly: true },
  { id: 'about', label: 'About', icon: Info },
];

// After
const TABS = [
  { id: 'appearance', label: 'Weergave', icon: Palette },
  { id: 'connections', label: 'Koppelingen', icon: Share2 },
  { id: 'notifications', label: 'Meldingen', icon: Bell },
  { id: 'data', label: 'Gegevens', icon: Database },
  { id: 'admin', label: 'Beheer', icon: Shield, adminOnly: true },
  { id: 'about', label: 'Info', icon: Info },
];
```

### Translation Mapping - CONNECTION_SUBTABS Config

```jsx
// Source: CONTEXT.md decisions

// Before
const CONNECTION_SUBTABS = [
  { id: 'calendars', label: 'Calendars', icon: Calendar },
  { id: 'contacts', label: 'Contacts', icon: Users },
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'slack', label: 'Slack', icon: MessageSquare },
  { id: 'api-access', label: 'API Access', icon: Key },
];

// After
const CONNECTION_SUBTABS = [
  { id: 'calendars', label: 'Google Agenda', icon: Calendar },
  { id: 'contacts', label: 'Google Contacten', icon: Users },
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'slack', label: 'Slack', icon: MessageSquare },
  { id: 'api-access', label: 'API-toegang', icon: Key },
];
```

### Translation Mapping - AppearanceTab

```jsx
// Source: CONTEXT.md decisions

// Color scheme section
"Color scheme" -> "Kleurenschema"
"Choose how {APP_NAME} looks to you. Select a single theme or sync with your system settings."
-> "Kies hoe {APP_NAME} eruitziet. Selecteer een thema of synchroniseer met je systeeminstellingen."

// Theme options (per CONTEXT.md)
"Light" -> "Licht"
"Dark" -> "Donker"
"System" -> "Systeem"

// Current mode indicator
"Currently using" -> "Momenteel"
"mode" -> "modus"
"(based on your system preference)" -> "(op basis van je systeeminstelling)"

// Accent color section
"Accent color" -> "Accentkleur"
"Choose the accent color used for buttons, links, and other interactive elements."
-> "Kies de accentkleur voor knoppen, links en andere interactieve elementen."
"Selected:" -> "Geselecteerd:"

// Profile link section
"Profile link" -> "Profielkoppeling"
"Link your account to your person record. When linked, you will be hidden from meeting attendee lists since you already know you are attending."
-> "Koppel je account aan je persoonsrecord. Wanneer gekoppeld word je verborgen uit de deelnemerslijst van afspraken, omdat je al weet dat je aanwezig bent."
"Loading..." -> "Laden..."
"Linked to your account" -> "Gekoppeld aan je account"
"Unlink" -> "Ontkoppelen"
"Unlinking..." -> "Ontkoppelen..."
"Link to your person record" -> "Koppel aan je persoonsrecord"
"Search for your person record..." -> "Zoek je persoonsrecord..."
"Searching..." -> "Zoeken..."
"No people found" -> "Geen personen gevonden"
"Cancel" -> "Annuleren"
"Not linked yet. Search for your person record to link it to your account."
-> "Nog niet gekoppeld. Zoek je persoonsrecord om het aan je account te koppelen."
```

### Translation Mapping - CalendarsTab

```jsx
// Source: CONTEXT.md decisions

// Section headers
"Calendar Connections" -> "Agendakoppelingen"
"Connect your calendars to automatically sync meetings and find contacts you meet with."
-> "Koppel je agenda's om automatisch afspraken te synchroniseren en contacten te vinden."

// Connection status
"Never synced" -> "Nog nooit gesynchroniseerd"
"Error" -> "Fout"
"Paused" -> "Gepauzeerd"

// Buttons
"Sync" -> "Synchroniseren"
"Syncing..." -> "Synchroniseren..."
"Edit" -> "Bewerken"
"Delete" -> "Verwijderen"

// Empty state
"No calendar connections yet. Add one below to start syncing your meetings."
-> "Nog geen agendakoppelingen. Voeg er een toe om je afspraken te synchroniseren."

// Add connection section
"Add Connection" -> "Koppeling toevoegen"
"Connect Google Calendar" -> "Google Agenda koppelen"
"Sign in with your Google account" -> "Inloggen met je Google-account"
"Add CalDAV Calendar" -> "CalDAV-agenda toevoegen"
"iCloud, Fastmail, Nextcloud, etc." -> "iCloud, Fastmail, Nextcloud, etc."

// iCal subscription
"Subscribe to important dates in your calendar" -> "Abonneer je op belangrijke datums in je agenda"
"Subscribe to your important dates in any calendar app (Apple Calendar, Google Calendar, Outlook, etc.)"
-> "Abonneer je op je belangrijke datums in elke agenda-app (Apple Agenda, Google Agenda, Outlook, etc.)"
"Your calendar feed URL" -> "Je agenda-feed URL"
"Copy" -> "Kopiëren"
"Copied" -> "Gekopieerd"
"Subscribe in calendar app" -> "Abonneren in agenda-app"
"Regenerate URL" -> "URL opnieuw genereren"
"Regenerating..." -> "Opnieuw genereren..."
"Keep this URL private. Anyone with access to it can see your important dates. If you think it has been compromised, click \"Regenerate URL\" to get a new one."
-> "Houd deze URL privé. Iedereen met toegang kan je belangrijke datums zien. Als je denkt dat deze gecompromitteerd is, klik op \"URL opnieuw genereren\"."
```

### Translation Mapping - NotificationsTab

```jsx
// Source: CONTEXT.md decisions

// Section header
"Notifications" -> "Meldingen"
"Choose how you want to receive daily reminders about your important dates."
-> "Kies hoe je dagelijkse herinneringen over je belangrijke datums wilt ontvangen."

// Email channel
"Email" -> "E-mail"
"Receive daily digest emails" -> "Ontvang dagelijkse samenvattingen per e-mail"

// Slack channel
"Slack" -> "Slack"
"Receive notifications in Slack" -> "Ontvang meldingen in Slack"
"Connect Slack in Connections to enable" -> "Koppel Slack via Koppelingen om in te schakelen"
"Connect Slack" -> "Slack koppelen"

// Notification time
"Notification time (UTC)" -> "Meldingstijd (UTC)"
"UTC:" -> "UTC:"
"Your time" -> "Jouw tijd"
"Choose the UTC time when you want to receive your daily reminder digest. Reminders are sent within a 1-hour window of your selected time."
-> "Kies de UTC-tijd waarop je je dagelijkse herinneringen wilt ontvangen. Meldingen worden verzonden binnen een uur na de geselecteerde tijd."

// Mention notifications
"Mention notifications" -> "Vermeldingsmeldingen"
"Include in daily digest (default)" -> "Opnemen in dagelijkse samenvatting (standaard)"
"Send immediately" -> "Direct verzenden"
"Don't notify me" -> "Niet melden"
"Choose when to receive notifications when someone @mentions you in a note."
-> "Kies wanneer je meldingen wilt ontvangen als iemand je @vermeldt in een notitie."
```

### Translation Mapping - DataTab

```jsx
// Source: CONTEXT.md decisions (Importeren, Exporteren, Gegevens downloaden)

// Import section
"Import data" -> "Gegevens importeren"
"Import your contacts from various sources. Existing contacts with matching names will be updated instead of duplicated."
-> "Importeer je contacten vanuit verschillende bronnen. Bestaande contacten met dezelfde naam worden bijgewerkt in plaats van gedupliceerd."

// Import types
"vCard" -> "vCard"
"Apple Contacts, Outlook, Android" -> "Apple Contacten, Outlook, Android"
"Google Contacts" -> "Google Contacten"
"CSV export from Google" -> "CSV-export van Google"
"Monica CRM" -> "Monica CRM"
"SQL export from Monica" -> "SQL-export van Monica"

// Export section
"Export data" -> "Gegevens exporteren"
"Export all your contacts in a format compatible with other contact management systems."
-> "Exporteer al je contacten in een formaat dat compatibel is met andere contactbeheersystemen."

"Export as vCard (.vcf)" -> "Exporteren als vCard (.vcf)"
"Compatible with Apple Contacts, Outlook, Android, and most contact apps"
-> "Compatibel met Apple Contacten, Outlook, Android en de meeste contact-apps"

"Export as Google Contacts CSV" -> "Exporteren als Google Contacten CSV"
"Import directly into Google Contacts or other CSV-compatible systems"
-> "Importeer direct in Google Contacten of andere CSV-compatibele systemen"
```

### Translation Mapping - AdminTab

```jsx
// Source: CONTEXT.md decisions

// Section headers
"User management" -> "Gebruikersbeheer"
"Configuration" -> "Configuratie"
"System actions" -> "Systeemacties"

// User management links
"User approval" -> "Gebruikersgoedkeuring"
"Approve or deny access for new users" -> "Keur toegang goed of weiger voor nieuwe gebruikers"

// Configuration links
"Relationship types" -> "Relatietypes"
"Manage relationship types and their inverse mappings" -> "Beheer relatietypes en hun omgekeerde koppelingen"
"Labels" -> "Labels"
"Manage labels for people and organizations" -> "Beheer labels voor personen en organisaties"
"Custom fields" -> "Aangepaste velden"
"Define custom data fields for people and organizations" -> "Definieer aangepaste gegevensvelden voor personen en organisaties"
"Feedback management" -> "Feedbackbeheer"
"View and manage all user feedback" -> "Bekijk en beheer alle gebruikersfeedback"

// System actions
"Trigger reminders" -> "Herinneringen versturen"
"Manually send reminders for today" -> "Handmatig herinneringen voor vandaag versturen"
"Sending..." -> "Verzenden..."

"Reschedule cron jobs" -> "Cron-taken herplannen"
"Reschedule all user reminder cron jobs" -> "Alle gebruikers-herinneringstaken herplannen"
"Rescheduling..." -> "Herplannen..."
```

### Translation Mapping - AboutTab

```jsx
// Source: codebase

"About {APP_NAME}" -> "Over {APP_NAME}"
"Version" -> "Versie"
"{APP_NAME} is a personal CRM system that helps you manage your contacts, track important dates, and maintain meaningful relationships."
-> "{APP_NAME} is een persoonlijk CRM-systeem dat je helpt bij het beheren van je contacten, het bijhouden van belangrijke datums en het onderhouden van betekenisvolle relaties."
"Built with WordPress, React, and Tailwind CSS."
-> "Gebouwd met WordPress, React en Tailwind CSS."
```

### Translation Mapping - Subpages

```jsx
// Labels.jsx
"Labels" -> "Labels"
"People Labels" -> "Ledenlabels"
"Organization Labels" -> "Organisatielabels"
"Add Label" -> "Label toevoegen"
"Add New Label" -> "Nieuw label toevoegen"
"Name *" -> "Naam *"
"e.g., Friend, Family, VIP, Client" -> "bijv. Vriend, Familie, VIP, Klant"
"Save" -> "Opslaan"
"Cancel" -> "Annuleren"
"No labels found. Create one to get started." -> "Geen labels gevonden. Maak er een aan om te beginnen."
"people" -> "leden"
"organizations" -> "organisaties"
"Name is required" -> "Naam is verplicht"
"Are you sure you want to delete \"{name}\"? This cannot be undone."
-> "Weet je zeker dat je \"{name}\" wilt verwijderen? Dit kan niet ongedaan worden gemaakt."
"Edit" -> "Bewerken"
"Delete" -> "Verwijderen"

// UserApproval.jsx
"User Approval" -> "Gebruikersgoedkeuring"
"Back to Settings" -> "Terug naar Instellingen"
"Pending Approval" -> "Wacht op goedkeuring"
"Approved Users" -> "Goedgekeurde gebruikers"
"Registered:" -> "Geregistreerd:"
"Approve" -> "Goedkeuren"
"Deny" -> "Weigeren"
"Delete" -> "Verwijderen"
"Revoke Access" -> "Toegang intrekken"
"No Stadion users found." -> "Geen Stadion-gebruikers gevonden."
"Are you sure you want to approve this user?" -> "Weet je zeker dat je deze gebruiker wilt goedkeuren?"
"Are you sure you want to deny this user? They will not be able to access the system."
-> "Weet je zeker dat je deze gebruiker wilt weigeren? Ze krijgen geen toegang tot het systeem."
"Are you sure you want to delete {name}? This will permanently delete their account and all their related data (people, organizations, dates). This action cannot be undone."
-> "Weet je zeker dat je {name} wilt verwijderen? Dit verwijdert permanent hun account en alle gerelateerde gegevens (personen, organisaties, datums). Dit kan niet ongedaan worden gemaakt."

// RelationshipTypes.jsx
"Relationship Types" -> "Relatietypes"
"Add Relationship Type" -> "Relatietype toevoegen"
"Restore Defaults" -> "Standaardwaarden herstellen"
"Add New Relationship Type" -> "Nieuw relatietype toevoegen"
"Name *" -> "Naam *"
"e.g., Parent, Spouse, Colleague" -> "bijv. Ouder, Partner, Collega"
"Inverse Relationship Type" -> "Omgekeerd relatietype"
"Select the inverse relationship type. For example, if this is \"Parent\", select \"Child\". If this is \"Spouse\" or \"Acquaintance\", select the same type."
-> "Selecteer het omgekeerde relatietype. Bijvoorbeeld, als dit \"Ouder\" is, selecteer \"Kind\". Als dit \"Partner\" of \"Kennis\" is, selecteer hetzelfde type."
"Search for a relationship type..." -> "Zoek een relatietype..."
"None (no inverse)" -> "Geen (geen omgekeerd)"
"No relationship types found" -> "Geen relatietypes gevonden"
"Inverse:" -> "Omgekeerd:"
"No inverse relationship" -> "Geen omgekeerd relatietype"
"This will restore all default inverse mappings and gender-dependent settings. Continue?"
-> "Dit herstelt alle standaard omgekeerde koppelingen en geslachtsafhankelijke instellingen. Doorgaan?"
"Default relationship type configurations have been restored."
-> "De standaardconfiguratie voor relatietypes is hersteld."

// CustomFields.jsx
"Custom Fields" -> "Aangepaste velden"
"People Fields" -> "Ledenvelden"
"Organization Fields" -> "Organisatievelden"
"Add Field" -> "Veld toevoegen"
"No custom fields defined. Click 'Add Field' to create one."
-> "Geen aangepaste velden gedefinieerd. Klik op 'Veld toevoegen' om er een aan te maken."
"Label" -> "Label"
"Type" -> "Type"
"Reorder" -> "Herordenen"
"Drag to reorder" -> "Sleep om te herordenen"

// FeedbackManagement.jsx
"Feedback Management" -> "Feedbackbeheer"
"Back to Settings" -> "Terug naar Instellingen"
"Type:" -> "Type:"
"Status:" -> "Status:"
"Priority:" -> "Prioriteit:"
"All Types" -> "Alle types"
"Bugs" -> "Bugs"
"Features" -> "Functies"
"All Statuses" -> "Alle statussen"
"New" -> "Nieuw"
"In Progress" -> "In behandeling"
"Resolved" -> "Opgelost"
"Declined" -> "Afgewezen"
"All Priorities" -> "Alle prioriteiten"
"Low" -> "Laag"
"Medium" -> "Gemiddeld"
"High" -> "Hoog"
"Critical" -> "Kritiek"
"Title" -> "Titel"
"Author" -> "Auteur"
"Date" -> "Datum"
"Actions" -> "Acties"
"Unknown" -> "Onbekend"
"View" -> "Bekijken"
"Bug" -> "Bug"
"Feature" -> "Functie"
"No feedback found matching your filters." -> "Geen feedback gevonden met deze filters."
"Showing {n} feedback item(s)" -> "{n} feedback-item(s) weergegeven"
```

### Access Denied Component Translation

```jsx
// Used in multiple subpages
"Access Denied" -> "Toegang geweigerd"
"You don't have permission to manage [X]. This feature is only available to administrators."
-> "Je hebt geen toestemming om [X] te beheren. Deze functie is alleen beschikbaar voor beheerders."
"Back to Settings" -> "Terug naar Instellingen"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| English UI | Dutch UI | Phase 100-104 | Full Dutch experience |
| "Appearance" | "Weergave" | CONTEXT.md decision | User-friendly Dutch |
| "Due date" format | "Deadline" | CONTEXT.md decision | More natural Dutch term |

**Deprecated/outdated:**
- None for this phase

## Open Questions

1. **Admin Tab Visual Indicator**
   - What we know: CONTEXT.md mentions visual indicator for admin-only tab
   - What's unclear: Exact implementation (Lock icon, badge, both?)
   - Recommendation: Add Lock icon next to "Beheer" label for visual distinction

2. **Import Components**
   - What we know: DataTab imports MonicaImport, VCardImport, GoogleContactsImport components
   - What's unclear: Whether these component files need translation
   - Recommendation: Check and translate if they contain English strings (they likely do)

## Sources

### Primary (HIGH confidence)
- `src/pages/Settings/Settings.jsx` - Main settings page (verified English strings)
- `src/pages/Settings/Labels.jsx` - Label management (verified English strings)
- `src/pages/Settings/UserApproval.jsx` - User approval (verified English strings)
- `src/pages/Settings/RelationshipTypes.jsx` - Relationship types (verified English strings)
- `src/pages/Settings/CustomFields.jsx` - Custom fields (verified English strings)
- `src/pages/Settings/FeedbackManagement.jsx` - Feedback management (verified English strings)
- `.planning/phases/105-instellingen-settings/105-CONTEXT.md` - User decisions and terminology
- `.planning/phases/104-datums-taken/104-RESEARCH.md` - Reference for translation patterns

### Secondary (MEDIUM confidence)
- `src/hooks/useDocumentTitle.js` - Document title utility (needs Settings section translation)

### Tertiary (LOW confidence)
- `src/components/import/MonicaImport.jsx` - May contain translatable strings
- `src/components/import/VCardImport.jsx` - May contain translatable strings
- `src/components/import/GoogleContactsImport.jsx` - May contain translatable strings

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No changes needed, existing stack
- Architecture: HIGH - Direct string replacement, established pattern
- Pitfalls: HIGH - Based on Phase 104 experience and code review
- Code examples: HIGH - Derived from actual codebase + CONTEXT.md

**Files to translate (6 primary, 3 secondary, 1 utility):**

Primary files:
1. `src/pages/Settings/Settings.jsx` (~3300 lines) - Main settings, all tabs
2. `src/pages/Settings/Labels.jsx` (~333 lines) - Label management
3. `src/pages/Settings/UserApproval.jsx` (~201 lines) - User approval
4. `src/pages/Settings/RelationshipTypes.jsx` (~524 lines) - Relationship types
5. `src/pages/Settings/CustomFields.jsx` (~435 lines) - Custom fields
6. `src/pages/Settings/FeedbackManagement.jsx` (~350 lines) - Feedback management

Secondary files (check for translations):
7. `src/components/import/MonicaImport.jsx` - Monica import component
8. `src/components/import/VCardImport.jsx` - vCard import component
9. `src/components/import/GoogleContactsImport.jsx` - Google Contacts import component

Utility file:
10. `src/hooks/useDocumentTitle.js` - Settings document titles

**Suggested task breakdown:**
Due to the size of Settings.jsx, recommend splitting into 6 tasks:
1. Settings tabs config + AppearanceTab
2. CalendarsTab + CalDAVModal + EditConnectionModal
3. ConnectionsContactsSubtab + ConnectionsCardDAVSubtab + ConnectionsSlackSubtab + APIAccessTab
4. NotificationsTab + DataTab + AdminTab + AboutTab
5. Subpages (Labels, UserApproval, RelationshipTypes, CustomFields, FeedbackManagement)
6. Import components + useDocumentTitle.js

**Research date:** 2026-01-25
**Valid until:** 2026-02-25 (stable - translation patterns don't change)
