# Requirements: Stadion v7.0 Dutch Localization

**Defined:** 2026-01-25
**Core Value:** Convert entire Stadion interface to Dutch with proper terminology (Leden, Teams, Commissies)

## v7.0 Requirements

Requirements for Dutch localization. Direct string replacement approach (no i18n library).

### Navigation & Core UI

- [ ] **NAV-01**: Translate sidebar navigation labels (Dashboard→Dashboard, People→Leden, Teams→Teams, Commissies→Commissies, Dates→Datums, Todos→Taken, Workspaces→Werkruimtes, Feedback→Feedback, Settings→Instellingen)
- [ ] **NAV-02**: Translate quick actions menu (Nieuw lid, Nieuw team, Nieuwe taak, Nieuwe datum)
- [ ] **NAV-03**: Translate user menu (Profiel bewerken, WordPress admin, Uitloggen)
- [ ] **NAV-04**: Translate search modal labels and placeholders

### Dashboard

- [ ] **DASH-01**: Translate stat card labels (Totaal leden, Teams, Evenementen, Open taken, Wachtend)
- [ ] **DASH-02**: Translate widget titles (Komende herinneringen, Open taken, Wachtend op reactie, Afspraken vandaag, etc.)
- [ ] **DASH-03**: Translate empty states and welcome messages
- [ ] **DASH-04**: Translate error messages and update banner

### People (Leden)

- [ ] **LEDEN-01**: Translate page title and list headers (Voornaam, Achternaam, Team, Werkruimte, Labels)
- [ ] **LEDEN-02**: Translate person form labels (Voornaam, Achternaam, Bijnaam, Geslacht, E-mail, Telefoon, etc.)
- [ ] **LEDEN-03**: Translate gender options (Man, Vrouw, Non-binair, Anders, Zeg ik liever niet)
- [ ] **LEDEN-04**: Translate vCard import messages

### Teams

- [ ] **TEAM-01**: Translate page title and list headers
- [ ] **TEAM-02**: Translate team form labels (Naam, Website, Moederorganisatie, Branche, Investeerders)
- [ ] **TEAM-03**: Translate visibility and workspace options

### Commissies

- [ ] **COMM-01**: Translate page title and list headers
- [ ] **COMM-02**: Translate commissie form labels

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

- [ ] **FORMAT-01**: Configure date-fns with Dutch locale for all date displays
- [ ] **FORMAT-02**: Update relative date formatting (vandaag, gisteren, over X dagen)

## Future Requirements

None for this milestone — focused solely on Dutch translation.

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
| NAV-01 | TBD | Pending |
| NAV-02 | TBD | Pending |
| NAV-03 | TBD | Pending |
| NAV-04 | TBD | Pending |
| DASH-01 | TBD | Pending |
| DASH-02 | TBD | Pending |
| DASH-03 | TBD | Pending |
| DASH-04 | TBD | Pending |
| LEDEN-01 | TBD | Pending |
| LEDEN-02 | TBD | Pending |
| LEDEN-03 | TBD | Pending |
| LEDEN-04 | TBD | Pending |
| TEAM-01 | TBD | Pending |
| TEAM-02 | TBD | Pending |
| TEAM-03 | TBD | Pending |
| COMM-01 | TBD | Pending |
| COMM-02 | TBD | Pending |
| DATE-01 | TBD | Pending |
| DATE-02 | TBD | Pending |
| DATE-03 | TBD | Pending |
| TODO-01 | TBD | Pending |
| TODO-02 | TBD | Pending |
| TODO-03 | TBD | Pending |
| SET-01 | TBD | Pending |
| SET-02 | TBD | Pending |
| SET-03 | TBD | Pending |
| SET-04 | TBD | Pending |
| SET-05 | TBD | Pending |
| SET-06 | TBD | Pending |
| UI-01 | TBD | Pending |
| UI-02 | TBD | Pending |
| UI-03 | TBD | Pending |
| UI-04 | TBD | Pending |
| UI-05 | TBD | Pending |
| FORMAT-01 | TBD | Pending |
| FORMAT-02 | TBD | Pending |

**Coverage:**
- v7.0 requirements: 36 total
- Mapped to phases: 0
- Unmapped: 36 (pending roadmap)

---
*Requirements defined: 2026-01-25*
*Last updated: 2026-01-25 after initial definition*
