# Requirements: Stadion v7.0 Dutch Localization

**Defined:** 2026-01-25
**Core Value:** Convert entire Stadion interface to Dutch with proper terminology (Leden, Teams, Commissies)

## v7.0 Requirements

Requirements for Dutch localization. Direct string replacement approach (no i18n library).

### Navigation & Core UI

- [x] **NAV-01**: Translate sidebar navigation labels (Dashboard->Dashboard, People->Leden, Teams->Teams, Commissies->Commissies, Dates->Datums, Todos->Taken, Workspaces->Werkruimtes, Feedback->Feedback, Settings->Instellingen)
- [x] **NAV-02**: Translate quick actions menu (Nieuw lid, Nieuw team, Nieuwe taak, Nieuwe datum)
- [x] **NAV-03**: Translate user menu (Profiel bewerken, WordPress admin, Uitloggen)
- [x] **NAV-04**: Translate search modal labels and placeholders

### Dashboard

- [x] **DASH-01**: Translate stat card labels (Totaal leden, Teams, Evenementen, Open taken, Wachtend)
- [x] **DASH-02**: Translate widget titles (Komende herinneringen, Open taken, Wachtend op reactie, Afspraken vandaag, etc.)
- [x] **DASH-03**: Translate empty states and welcome messages
- [x] **DASH-04**: Translate error messages and update banner

### People (Leden)

- [x] **LEDEN-01**: Translate page title and list headers (Voornaam, Achternaam, Team, Werkruimte, Labels)
- [x] **LEDEN-02**: Translate person form labels (Voornaam, Achternaam, Bijnaam, Geslacht, E-mail, Telefoon, etc.)
- [x] **LEDEN-03**: Translate gender options (Man, Vrouw, Non-binair, Anders, Zeg ik liever niet)
- [x] **LEDEN-04**: Translate vCard import messages

### Teams

- [x] **TEAM-01**: Translate page title and list headers
- [x] **TEAM-02**: Translate team form labels (Naam, Website, Sponsoren)
- [x] **TEAM-03**: Translate visibility and workspace options

### Commissies

- [x] **COMM-01**: Translate page title and list headers
- [x] **COMM-02**: Translate commissie form labels

### Dates (Datums)

- [ ] **DATE-01**: Translate page title and list headers
- [ ] **DATE-02**: Translate date form labels (Titel, Type, Jaarlijks terugkerend, Datum, Gerelateerde personen)
- [ ] **DATE-03**: Translate date type labels (Verjaardag, Huwelijk, Herdenking)

### Todos (Taken)

- [ ] **TODO-01**: Translate page title and filter tabs (Alle, Open, Wachtend, Voltooid)
- [ ] **TODO-02**: Translate action buttons and status labels
- [ ] **TODO-03**: Translate todo form labels

### Settings (Instellingen)

- [ ] **SET-01**: Translate settings tab labels (Uiterlijk, Verbindingen, Meldingen, Gegevens, Beheer, Over)
- [ ] **SET-02**: Translate appearance settings (Thema: Licht/Donker/Systeem)
- [ ] **SET-03**: Translate connections subtabs and settings
- [ ] **SET-04**: Translate notification preferences
- [ ] **SET-05**: Translate import/export labels
- [ ] **SET-06**: Translate admin settings

### Global UI Elements

- [ ] **UI-01**: Translate common buttons (Opslaan, Annuleren, Verwijderen, Bewerken, Toevoegen)
- [ ] **UI-02**: Translate loading/saving states (Laden..., Opslaan..., Verwijderen...)
- [ ] **UI-03**: Translate common placeholders (Zoeken..., Selecteer..., Geen resultaten)
- [ ] **UI-04**: Translate validation and error messages
- [ ] **UI-05**: Translate confirmation dialogs (Weet je het zeker?)

### Date Formatting

- [x] **FORMAT-01**: Configure date-fns with Dutch locale for all date displays
- [x] **FORMAT-02**: Update relative date formatting (vandaag, gisteren, over X dagen)

## Future Requirements

None for this milestone - focused solely on Dutch translation.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Multi-language support | Dutch-only per user request; no i18n library infrastructure |
| WordPress admin translation | Backend admin stays English; this milestone is frontend SPA only |
| PHP backend messages | Focus on React frontend; PHP-generated strings out of scope |
| RTL support | Dutch is LTR; no RTL requirements |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FORMAT-01 | Phase 99 | Complete |
| FORMAT-02 | Phase 99 | Complete |
| NAV-01 | Phase 100 | Complete |
| NAV-02 | Phase 100 | Complete |
| NAV-03 | Phase 100 | Complete |
| NAV-04 | Phase 100 | Complete |
| DASH-01 | Phase 101 | Complete |
| DASH-02 | Phase 101 | Complete |
| DASH-03 | Phase 101 | Complete |
| DASH-04 | Phase 101 | Complete |
| LEDEN-01 | Phase 102 | Complete |
| LEDEN-02 | Phase 102 | Complete |
| LEDEN-03 | Phase 102 | Complete |
| LEDEN-04 | Phase 102 | Complete |
| TEAM-01 | Phase 103 | Complete |
| TEAM-02 | Phase 103 | Complete |
| TEAM-03 | Phase 103 | Complete |
| COMM-01 | Phase 103 | Complete |
| COMM-02 | Phase 103 | Complete |
| DATE-01 | Phase 104 | Pending |
| DATE-02 | Phase 104 | Pending |
| DATE-03 | Phase 104 | Pending |
| TODO-01 | Phase 104 | Pending |
| TODO-02 | Phase 104 | Pending |
| TODO-03 | Phase 104 | Pending |
| SET-01 | Phase 105 | Pending |
| SET-02 | Phase 105 | Pending |
| SET-03 | Phase 105 | Pending |
| SET-04 | Phase 105 | Pending |
| SET-05 | Phase 105 | Pending |
| SET-06 | Phase 105 | Pending |
| UI-01 | Phase 106 | Pending |
| UI-02 | Phase 106 | Pending |
| UI-03 | Phase 106 | Pending |
| UI-04 | Phase 106 | Pending |
| UI-05 | Phase 106 | Pending |

**Coverage:**
- v7.0 requirements: 36 total
- Mapped to phases: 36
- Unmapped: 0

---
*Requirements defined: 2026-01-25*
*Last updated: 2026-01-25 (Phase 103 complete)*
