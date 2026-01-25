# Roadmap: Stadion v7.0 Dutch Localization

## Overview

This milestone transforms the entire Stadion frontend from English to Dutch. Starting with date formatting foundation (Dutch locale for date-fns), then systematically translating each UI area: navigation, dashboard, entity pages (Leden, Teams, Commissies), important dates, todos, settings, and global UI elements. Direct string replacement approach - no i18n library complexity.

## Milestones

- 📋 **v7.0 Dutch Localization** - Phases 99-106 (in progress)

## Phases

- [x] **Phase 99: Date Formatting Foundation** - Configure Dutch locale for date-fns
- [x] **Phase 100: Navigation & Layout** - Translate sidebar, quick actions, user menu, search
- [x] **Phase 101: Dashboard** - Translate stats, widgets, empty states, messages
- [ ] **Phase 102: Leden (People)** - Translate people pages and forms
- [ ] **Phase 103: Teams & Commissies** - Translate teams and commissies pages
- [ ] **Phase 104: Datums & Taken** - Translate important dates and todos
- [ ] **Phase 105: Instellingen (Settings)** - Translate all settings pages
- [ ] **Phase 106: Global UI** - Translate buttons, states, placeholders, dialogs

## Phase Details

### Phase 99: Date Formatting Foundation
**Goal**: All dates display in Dutch format with proper relative date labels
**Depends on**: Nothing (foundation phase)
**Requirements**: FORMAT-01, FORMAT-02
**Success Criteria** (what must be TRUE):
  1. All date displays use Dutch formatting (e.g., "25 januari 2026")
  2. Relative dates show Dutch labels ("vandaag", "gisteren", "over 3 dagen")
  3. Month and day names appear in Dutch throughout the application
**Plans**: 2 plans

Plans:
- [x] 99-01-PLAN.md — Create Dutch date formatting utility and update timeline.js
- [x] 99-02-PLAN.md — Update all remaining files to use Dutch formatting utility

### Phase 100: Navigation & Layout
**Goal**: Users see Dutch labels in all navigation elements
**Depends on**: Phase 99
**Requirements**: NAV-01, NAV-02, NAV-03, NAV-04
**Success Criteria** (what must be TRUE):
  1. Sidebar shows Dutch labels (Dashboard, Leden, Teams, Commissies, Datums, Taken, Workspaces, Feedback, Instellingen)
  2. Quick actions menu shows Dutch labels (Nieuw lid, Nieuw team, Nieuwe taak, Nieuwe datum)
  3. User menu shows Dutch labels (Profiel bewerken, WordPress beheer, Uitloggen)
  4. Search modal placeholder and labels are in Dutch
**Plans**: 1 plan

Plans:
- [x] 100-01-PLAN.md — Translate all navigation and layout strings in Layout.jsx

### Phase 101: Dashboard
**Goal**: Dashboard displays entirely in Dutch
**Depends on**: Phase 100
**Requirements**: DASH-01, DASH-02, DASH-03, DASH-04
**Success Criteria** (what must be TRUE):
  1. Stat cards show Dutch labels (Totaal leden, Teams, Evenementen, Open taken, Openstaand)
  2. Widget titles are in Dutch (Komende herinneringen, Open taken, Openstaand, Afspraken vandaag)
  3. Empty states and welcome messages display in Dutch
  4. Error messages and update banners are in Dutch
**Plans**: 1 plan

Plans:
- [x] 101-01-PLAN.md — Translate Dashboard.jsx and DashboardCustomizeModal.jsx to Dutch

### Phase 102: Leden (People)
**Goal**: People pages display entirely in Dutch
**Depends on**: Phase 101
**Requirements**: LEDEN-01, LEDEN-02, LEDEN-03, LEDEN-04
**Success Criteria** (what must be TRUE):
  1. Page title shows "Leden" and list headers are Dutch (Voornaam, Achternaam, Team, Werkruimte, Labels)
  2. Person form labels are in Dutch (Voornaam, Achternaam, Bijnaam, Geslacht, E-mail, Telefoon)
  3. Gender options display in Dutch (Man, Vrouw, Non-binair, Anders, Zeg ik liever niet)
  4. vCard import messages are in Dutch
**Plans**: TBD

Plans:
- [ ] 102-01: Translate people list and headers
- [ ] 102-02: Translate person form and modals

### Phase 103: Teams & Commissies
**Goal**: Teams and Commissies pages display entirely in Dutch
**Depends on**: Phase 102
**Requirements**: TEAM-01, TEAM-02, TEAM-03, COMM-01, COMM-02
**Success Criteria** (what must be TRUE):
  1. Teams page title and list headers are in Dutch
  2. Team form labels are in Dutch (Naam, Website, Moederorganisatie, Branche, Investeerders)
  3. Visibility and workspace options are in Dutch
  4. Commissies page title and list headers are in Dutch
  5. Commissie form labels are in Dutch
**Plans**: TBD

Plans:
- [ ] 103-01: Translate teams pages
- [ ] 103-02: Translate commissies pages

### Phase 104: Datums & Taken
**Goal**: Important Dates and Todos pages display entirely in Dutch
**Depends on**: Phase 103
**Requirements**: DATE-01, DATE-02, DATE-03, TODO-01, TODO-02, TODO-03
**Success Criteria** (what must be TRUE):
  1. Dates page title and list headers are in Dutch
  2. Date form labels are in Dutch (Titel, Type, Jaarlijks terugkerend, Datum, Gerelateerde personen)
  3. Date type labels are in Dutch (Verjaardag, Huwelijk, Herdenking)
  4. Todos page title and filter tabs are in Dutch (Alle, Open, Wachtend, Voltooid)
  5. Todo action buttons and status labels are in Dutch
  6. Todo form labels are in Dutch
**Plans**: TBD

Plans:
- [ ] 104-01: Translate important dates pages
- [ ] 104-02: Translate todos pages

### Phase 105: Instellingen (Settings)
**Goal**: All settings pages display entirely in Dutch
**Depends on**: Phase 104
**Requirements**: SET-01, SET-02, SET-03, SET-04, SET-05, SET-06
**Success Criteria** (what must be TRUE):
  1. Settings tab labels are in Dutch (Uiterlijk, Verbindingen, Meldingen, Gegevens, Beheer, Over)
  2. Appearance settings are in Dutch (Thema: Licht/Donker/Systeem)
  3. Connections subtabs and settings are in Dutch
  4. Notification preferences are in Dutch
  5. Import/export labels are in Dutch
  6. Admin settings are in Dutch
**Plans**: TBD

Plans:
- [ ] 105-01: Translate settings navigation
- [ ] 105-02: Translate settings content panels

### Phase 106: Global UI Elements
**Goal**: All common UI elements display in Dutch
**Depends on**: Phase 105
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05
**Success Criteria** (what must be TRUE):
  1. Common buttons are in Dutch (Opslaan, Annuleren, Verwijderen, Bewerken, Toevoegen)
  2. Loading/saving states are in Dutch (Laden..., Opslaan..., Verwijderen...)
  3. Common placeholders are in Dutch (Zoeken..., Selecteer..., Geen resultaten)
  4. Validation and error messages are in Dutch
  5. Confirmation dialogs are in Dutch (Weet je het zeker?)
**Plans**: TBD

Plans:
- [ ] 106-01: Translate common components and utilities

## Progress

**Execution Order:**
Phases execute in numeric order: 99 -> 100 -> 101 -> 102 -> 103 -> 104 -> 105 -> 106

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 99. Date Formatting Foundation | 2/2 | ✓ Complete | 2026-01-25 |
| 100. Navigation & Layout | 1/1 | ✓ Complete | 2026-01-25 |
| 101. Dashboard | 1/1 | ✓ Complete | 2026-01-25 |
| 102. Leden (People) | 0/2 | Not started | - |
| 103. Teams & Commissies | 0/2 | Not started | - |
| 104. Datums & Taken | 0/2 | Not started | - |
| 105. Instellingen (Settings) | 0/2 | Not started | - |
| 106. Global UI Elements | 0/1 | Not started | - |

---
*Roadmap created: 2026-01-25*
*Last updated: 2026-01-25*
