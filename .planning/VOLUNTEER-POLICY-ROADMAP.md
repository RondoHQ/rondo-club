# Roadmap: AWC Volunteer Policy support in Rondo

**Source:** [Toekomstbestendig Vrijwilligersbeleid](https://docs.google.com/document/d/1UBunFqCy9jS5hHHmPpJmZLO4IXtCyXdsROxZ7iewv5Y/edit)
**Status:** Bestuursbesluit verwerkt — alle blokkerende vragen beantwoord (2026-05-26 's avonds). Klaar voor Fase A implementatie.
**Owner:** Joost
**Last updated:** 2026-05-26 (bestuursvergadering)

## Context

AWC is moving to a future-proof volunteer policy. Two-shift-per-year obligation for parents (t/m JO15) and players (vanaf O17), with fines for non-compliance. Core operational categories: terreinmeester, kantinedienst, schoonmaak, terreinonderhoud, activiteiten. Rondo Club is named in the policy as the working alternative to Sportlink for executing this.

Rondo today has the people, teams, roles, VOG dates, invoicing and Mollie payments to support volunteer administration. It has **no scheduling, sign-up, shift or task-pool features** — that's the largest gap.

## Decision summary

| # | Feature | Decision | Effort | Phase |
|---|---|---|---|---|
| 1 | Target group filtering | Pure derived filter, gezin-based, O17+ vervangt ouderplicht | S | TBD |
| 2 | Task catalog | CPT `dienst_type` met VOG/IVA/capaciteit-velden; activiteiten buiten Rondo (poule via #5) | M | TBD |
| 3 | Shift / time-slot scheduling | `shift_template` + `dienst_shift` CPTs; eligibility-filtered zichtbaarheid; afmelden altijd, boete bij no-show | XL | TBD |
| 4 | Member self sign-up (inschrijving) | Full WP-login via Magic Login plugin; alleen individuele signup; overlap = waarschuwing | L | TBD |
| 5 | Pool management (vaste poules) | Hergebruik commissies; in Rondo beheerd (Sportlink-sync whitelist); geen rotatie | S | TBD |
| 6 | 2-diensten-per-jaar counter | Hybrid completion, KNVB-seizoen, pro-rato half, multi-kind met contributie-korting (25%/50%) | M | C |
| 7 | Boete for missed duty | No-show direct, naar primaire ouder, €30/gemiste dienst, geen vrijkoop | M | E |
| 8 | VOG-plicht voor iedereen | Hard block in signup; scope = eligible pool; bulk-rollout handmatig per persoon | S | B |
| 9 | Online IVA / alcoholtraining | `datum-iva` + upload; bestuurslid kantine approveert; alleen Kantine-bar; 5 jaar geldig | S | B |
| 10 | Talentregistratie | v2-feature; hybride (tags + vrije tekst); half-geautomatiseerd matchen | M | v2 |
| 11 | Sleutel-uitgifte | Niet bouwen — paper/whiteboard volstaat | — | n.v.t. |
| 12 | Trainer/leider extra taken | Trainer/leider = vrijgesteld (analoog aan commissie); extra taken alleen in communicatie | XS | n.v.t. |
| 13 | Team-communicatie met beeldmateriaal | Beeldmateriaal op website (Astro); kickoff_done_at + notes op team-CPT; uitnodigingen buiten Rondo | XS | TBD |
| 14 | Onboarding nieuwe vrijwilliger | Bestaande welkomstmail uitbreiden via bestaande editor; handmatige trigger via bestaande schermen | XS | TBD |
| 15 | Vrijwilligersvergoeding | `betaalde_vrijwilliger` boolean + `vergoeding_reden` enum op person; betaald = vrijgesteld | S | TBD |
| 16 | Huidig-vrijwilliger status / vrijstellingen | 4 auto-vrijstellingen (commissie/staff/betaald/handmatig); Sportlink-veld blijft puur informatief | S | TBD |
| 17 | Communicatieplan / website + Vrijwilligers-dashboard | Top-level Vrijwilligers-pagina in Rondo (VOG/IVA eronder); Joost schrijft website-content | M | TBD |

## Effort legend

- **XS** — config or content change, no code
- **S** — single ACF field / single endpoint
- **M** — new ACF group + REST + small UI surface
- **L** — new CPT + REST + full UI
- **XL** — new CPT + member-facing UI + workflow + notifications

## Features

### 1. Target group filtering

**From the doc:** "Alle ouders t/m JO15 en spelers vanaf O17 worden geacht een aantal uren vrijwilligerswerk voor AWC te doen."

**Status in Rondo:** Bouwstenen aanwezig — `leeftijdsgroep`, `type-lid`, `spelactiviteit` uit Sportlink + `relationships` repeater met `relationship_type`. Geen gezin-concept.

**Decisions (2026-05-26):**
- Eligibility is een **pure derived filter** — geen opgeslagen boolean per persoon.
- De plicht is **per gezin gedeeld** voor een speler t/m JO15: één huishouden = één 2-diensten-plicht, in te vullen door één of meerdere ouders samen.
- Zodra een speler O17+ is, **vervangt** de spelersplicht de ouderplicht — alleen de speler zit in de doelgroep.
- **Gezin-definitie:** primair via parent-of relaties in `relationships`; fallback via gedeelde `addresses[0]` (straat + postcode + huisnummer) wanneer relatiedata ontbreekt.

**Plan:**
1. Voeg een server-side derived view `eligible_units` toe die per huishouden óf per O17+ speler één entry teruggeeft, met de bijbehorende verantwoordelijke personen.
2. Eén REST endpoint `/rondo/v1/volunteer-eligibility` met query params (`season`, `with_persons`).
3. UI: een nieuwe filter "Vrijwilligersplicht-doelgroep" op de Leden-lijst die het endpoint aanroept.
4. Geen migraties; alles is on-the-fly afgeleid uit bestaande Sportlink-velden + relationships.

**Open punten voor latere features:**
- Eligibility-set is input voor #6 (counter) en #7 (boete) — die moeten dezelfde unit-definitie gebruiken.
- Edge cases (bestuur/ereleden/langdurig zieken/dubbeltellingen kaderlid) worden in #16 afgehandeld via een aparte "vrijgesteld"-route, niet hier.

---

### 2. Task catalog

**From the doc:** Terreinmeester · Kantinedienst (bar/keuken voorbereiding/keuken verkoop) · Schoonmaak · Terreinonderhoud · Activiteiten/evenementen · Communicatie · Talent-based.

**Status in Rondo:** Geen taakconcept. `job_title` in `work_history` is vrije tekst, voor historische rollen — niet voor diensten.

**Decisions (2026-05-26):**
- Nieuwe CPT `dienst_type` met eigen velden — geen platte taxonomy. Per taaktype zijn er aparte regels die later (#3 shifts, #4 signup, #8 VOG, #9 IVA) ingezet worden.
- **Activiteiten/evenementen blijven buiten Rondo.** We tracken alleen de poule van ~75 personen die activiteiten regelen (zie #5), niet de individuele evenementen of shifts.
- **Alle dienst_types tellen mee voor de 2-diensten-plicht.** Geen "telt-mee" flag per type — die is overbodig omdat alles telt.
- Vrijstelling regelen we op persoonsniveau, niet op type-niveau: een persoon die actief commissielid is, is automatisch vrijgesteld van de 2-diensten-plicht. Detail in #16.

**Voorgestelde initiële dienst_types:**
| Naam | VOG vereist | IVA vereist | Default capaciteit | Sleutel-betrokken |
|---|---|---|---|---|
| Terreinmeester | ja | nee | 1 | ja |
| Kantine — bar | ja | ja | 1 | nee |
| Kantine — keuken-prep | ja | nee | 1 | nee |
| Kantine — keuken-verkoop | ja | nee | 1 | nee |
| Schoonmaak | ja | nee | 4 | nee |
| Terreinonderhoud | ja | nee | open | nee |

**ACF velden op CPT `dienst_type`:**
- `description` (textarea — wat houdt deze dienst in)
- `vog_required` (boolean)
- `iva_required` (boolean)
- `default_capacity` (number, 0 = onbeperkt)
- `sleutel_involved` (boolean — flag voor sleutel-uitgifte logica in #11)
- `color` (color picker — voor calendar UI in #3)

**Plan:**
1. Registreer CPT `dienst_type` (admin-only, geen public).
2. ACF field group met bovenstaande velden.
3. Seed 6 initiële types via migration on activation.
4. REST endpoint `/rondo/v1/dienst-types` (read-only voor frontend; admin CRUD via standaard CPT UI).
5. Geen UI buiten admin in deze fase — wordt benut door #3 (shifts) en #4 (signup).

**Open punten:**
- Communicatietaken: in beleidstekst genoemd maar nog niet uitgewerkt. Latere uitbreiding.
- Talent-based taken (#10) krijgen mogelijk eigen taakroute, niet dezelfde shifts/signup.

---

### 3. Shift / time-slot scheduling

**From the doc:** Concrete openingstijden kantine (Di 20–23, Wo/Do 20–00, Za 7:30–12 / 11:30–15:30 / 15–19 / 18:30–sluit, Zo 7:30–12 / 11:30–15:30 / 15–sluit). Schoonmaak ma 9–12. Terreinonderhoud ma + vr 9–12. Avondwedstrijden incidenteel.

**Status in Rondo:** Niets aanwezig. Geen schedule/calendar/shift CPT.

**Decisions (2026-05-26):**
- **Twee CPTs.** `shift_template` beschrijft de seizoensregels ("elke za 7:30–12:00, kantine-bar, capaciteit 2"). `dienst_shift` is een concrete, uitgerolde shift in de tijd. Een seizoensplanner expandert templates naar concrete shifts.
- **Afmelden mag altijd, geen automatisch sanctie-venster.** Sanctie (#7) komt alleen bij no-show, niet bij tijdige afmelding. Houdt de regels simpel; sociale druk doet de rest.
- **Eligibility-gefilterde zichtbaarheid.** Leden zien alleen shifts waarvoor ze in aanmerking komen — VOG-vereiste shifts pas zichtbaar zodra VOG aanwezig is, IVA idem. Minder keuze-overload, geen foute aanmeldingen.
- **Ad-hoc shifts via dezelfde flow.** Avondwedstrijd-incidenten zijn gewoon admin-aangemaakte `dienst_shift` records zonder bovenliggend template. Signup en telling zijn identiek.

**Datamodel:**

`shift_template` (CPT, admin-only):
- `dienst_type` (relatie naar #2)
- `day_of_week` (1–7)
- `start_time`, `end_time` (HH:MM)
- `capacity` (overschrijft default van dienst_type indien nodig)
- `active_from`, `active_until` (seizoen-window)
- `notes` (bv. "alleen bij thuiswedstrijden")

`dienst_shift` (CPT):
- `dienst_type` (relatie naar #2)
- `template` (optionele relatie naar `shift_template` — null voor ad-hoc)
- `start_datetime`, `end_datetime`
- `capacity` (snapshot van template op moment van expansie)
- `assigned_persons` (multi-relatie naar Person)
- `status` (`open` / `vol` / `voltooid` / `geannuleerd`)
- `notes`

**Plan (gefaseerd):**
1. **Fase 3a — Datamodel + admin UI.** CPTs + ACF + REST CRUD. Geen member-facing surface. Admin kan handmatig shifts aanmaken.
2. **Fase 3b — Template expander.** WP-Cron job (of WP-CLI command) die templates expandert naar shifts voor een opgegeven seizoens-window. Idempotent (genereert geen duplicaten).
3. **Fase 3c — Member-facing rooster.** Frontend kalender/lijst view die alleen elig shifts toont. Aanmelden/afmelden via REST. Vereist #1 (eligibility) en #2 (taaktypes) klaar.
4. **Fase 3d — Notificaties.** Reminder e-mail 48u voor shift; bevestiging bij signup; cancel-bevestiging. Hergebruikt Lettermint stack.

**Open punten:**
- Hoe expanderen we templates dat een jaar vooruit? Eén keer per seizoen via een handmatige run, of altijd "12 weken vooruit" rolling?
- Sub-roles binnen een shift (bv. Kantine-shift wil 1× bar + 1× keuken-prep): of dat we per sub-role een eigen shift maken? Voorlopig: aparte shifts per sub-role, simpeler. Wel veel records in de UI.
- iCal feed terugbrengen (was v4.0–v28.0, in v29.0 verwijderd) zodat vrijwilligers shifts in eigen agenda krijgen?

---

### 4. Member self sign-up (inschrijving)

**From the doc:** "Op inschrijving." Het beleid noemt ook "in het begin per team" als fallback voor schoonmaak — bij her-lezing een tentatieve optie ("?" in bron), geen kernfunctie. Geschrapt uit v1.

**Status in Rondo:** Geen self-service. User-provisioning bestaat (v30.0) via KNVB-ID + welcome e-mail; Magic Login plugin draait al voor passwordless authenticatie.

**Decisions (2026-05-26):**
- **Full WP-login.** Iedere eligible member krijgt een WP-account, login verloopt via [Magic Login](https://wordpress.org/plugins/magic-login/) — magic-link e-mails, geen wachtwoord-management. Hergebruikt v30.0 provisioning.
- **Alleen individuele signup in v1.** Geen team-signup als first-class flow.
- **Overlap = waarschuwing, geen blokkade.** Bij aanmelden voor een tweede shift in dezelfde tijd: toon waarschuwing met de overlappende shift; gebruiker mag doorgaan als hij echt twee rollen wil vervullen.

**Surface:**
- Member-facing route `/vrijwillig` (logged-in)
- Toont elig shifts (gefilterd via #1 + VOG/IVA-vereisten van #2)
- Aanmelden / afmelden via REST
- Persoonlijke "mijn shifts" pagina met komende + voltooide diensten (input voor #6 counter)

**Plan:**
1. Magic Login configureren voor productie (als nog niet actief in v30.0 provisioning flow).
2. Bulk-provisioning script: alle eligible members (uit #1) krijgen een WP-account met `subscriber` rol + `rondo_volunteer` capability.
3. Frontend route `/vrijwillig` + `/vrijwillig/mijn-shifts`.
4. REST endpoints `/rondo/v1/shifts/available`, `/rondo/v1/shifts/{id}/signup`, `/rondo/v1/shifts/{id}/cancel`, `/rondo/v1/my-shifts`.
5. Overlap-validator: voor signup, query bestaande assignments tegen tijdvenster van de nieuwe shift; toon banner in UI.
6. E-mail bevestigingen via Lettermint (signup confirmation, cancel confirmation, 48u reminder).

**Open punten:**
- Hoe ver de magic-link-mails opschalen? Welcome-mailbatch + maandelijkse roosteropening kan ~500 mails per piek genereren — Lettermint capaciteit checken.
- "Mijn shifts" — toont counter (#6) live? Of pas na verwerking?

---

### 5. Pool management (vaste poules)

**From the doc:** Schoonmaakpoule 20 personen die rouleren/onderling ruilen · Activiteitenpoule 75 personen · Werkploeg terreinonderhoud.

**Status in Rondo:** Commissies + `CapabilitySync` (v30.0) bestaan al. Geen rotatie-logica.

**Decisions (2026-05-26):**
- **Hergebruik commissies.** Drie nieuwe commissies: `Schoonmaakpoule`, `Activiteitenpoule`, `Werkploeg terreinonderhoud`. Capability-sync mapt deze naar nieuwe rollen `rondo_pool_schoonmaak`, `rondo_pool_activiteiten`, `rondo_pool_werkploeg`.
- **Beheer in Rondo, niet in Sportlink.** Rondo is bron van waarheid voor poule-membership. De Sportlink-sync mag deze commissies niet overschrijven — whitelist toevoegen in `rondo-sync`.
- **Geen automatische rotatie.** Poule-leden zien hun beschikbare shifts en melden zich aan. Ruilen onderling. Simpel; het probleem "wie is aan de beurt" is sociaal, niet code.
- **Activiteitenpoule = vrijgesteld van 2-diensten-plicht.** Honoreert de "commissielid = vrijgesteld" regel uit #2. Idem schoonmaakpoule en werkploeg (zij doen al hun bijdrage in de poule zelf).

**Plan:**
1. `rondo-sync` aanpassen: lijst van Rondo-managed commissies die niet overschreven worden door Sportlink-sync. Genaamd `RONDO_MANAGED_COMMISSIES` constant.
2. Drie commissies aanmaken in Rondo via admin UI (handmatig, eenmalig).
3. `CapabilitySync` uitbreiden met de drie nieuwe rol-mappings.
4. Eligibility-derivatie in #1 honoreert "actief in een Rondo-managed pool commissie" = vrijgesteld van 2-diensten-plicht. Hoort technisch bij #16, maar logica hier afgesproken.
5. Shifts (#3) gebruiken `dienst_type.required_pool` (optioneel veld toevoegen aan #2 schema) om te bepalen of een shift alleen open staat voor poule-leden, of voor de hele eligible doelgroep.

**Open punten:**
- Hoe importeren we de bestaande activiteitenpoule? Excel of Sportlink-export? Wie weet wie er nu in zit?
- Werkploeg "evt aanvullen met (ouders van) leden op inschrijving" — die ad-hoc helpers zijn géén poule-leden, doen wel mee aan een werkploeg-shift. Dat werkt automatisch als `dienst_type.required_pool` optioneel is.

---

### 6. 2-diensten-per-jaar counter

**From the doc:** "Het uiteindelijke doel is verplichting (2 diensten per jaar) en anders krijg je een boete."

**Status in Rondo:** Geen telling, geen completion-mechanisme. Bouwstenen volgen uit #1–#5.

**Decisions (2026-05-26):**
- **Hybrid completion.** Cron-job (1u na `end_datetime`) zet alle ingevulde shifts op `voltooid`. Coördinator/admin heeft een **72-uurs venster** om individuele assignments als no-show te markeren (input voor #7 boete).
- **Seizoen = KNVB-seizoen** (1 juli – 30 juni). Counter reset op 1 juli; evaluatie en eventuele boetes in juni.
- **Pro-rato bij mid-seizoen instromers**: lid sinds vóór 1 januari → volledige plicht; lid sinds 1 januari of later → halve plicht (afgerond naar boven: 1 dienst).
- **Plichten optellen** voor gemengde huishoudens: ouderplicht (voor JO15- spelers) en spelersplicht (voor O17+ spelers) tellen apart. Een gezin met JO13 + O17+ heeft dus 2 ouderplicht-diensten + 2 spelersplicht-diensten = 4 totaal.
- **Vrijgesteld** is wie actief commissielid is (zie #2/#16) — die personen staan niet in de counter-tabel.

**Multi-child scaling (besluit bestuursvergadering 2026-05-26):**

Per kind tellend, met **contributie-korting** voor opvolgende kinderen:
- Kind 1: 2 diensten (100%)
- Kind 2: 1,5 diensten (75% — 25% korting)
- Kind 3 en verder: 1 dienst (50% — 50% korting)

**Berekening en afronding:**

| Aantal JO15- kinderen | Berekening | Halve diensten | Afgerond (floor) |
|---|---|---|---|
| 1 | 2 | 2 | **2** |
| 2 | 2 + 1.5 | 3,5 | **3** |
| 3 | 2 + 1.5 + 1 | 4,5 | **4** |
| 4 | 2 + 1.5 + 1 + 1 | 5,5 | **5** |
| n (≥3) | 1.5 + n | n + 1.5 | **n + 1** |

**Afrondingsregel:** naar beneden (floor) — in voordeel van het lid, consistent met "korting"-intentie. Configureerbaar via constante als bestuur later anders besluit.

**Datamodel:**

`volunteer_obligation` (geen CPT, maar virtuele entity afgeleid per `(eligible_unit, season)` met cache):
- `unit_id` (huishouden-id óf person-id voor O17+)
- `season` (string, bv. "2026-2027")
- `required_count` (int, afgeleid uit eligibility + pro-rato + multi-kind regel)
- `completed_count` (count van `dienst_shift` met `status=voltooid` en `assigned_persons` ⊇ unit-members)
- `no_show_count` (count van markeringen door coördinator binnen 72u-venster)
- `pending_count` (count van toekomstige aangemelde shifts)

Caching via WP transient (`rondo_vobligation_{unit}_{season}`), invalided bij shift-status-changes.

**Plan:**
1. WP-Cron: hourly `rondo_complete_shifts` job — zet shifts met `end_datetime < now() - 1h` op voltooid.
2. REST endpoint `/rondo/v1/shifts/{id}/no-show` (admin only, alleen geldig binnen 72u na voltooi).
3. Service `VolunteerObligationCalculator` die `required_count` en `completed_count` levert voor een unit+seizoen.
4. UI surfaces:
   - Member: counter zichtbaar op `/vrijwillig/mijn-shifts` ("Je hebt X van Y diensten gedaan")
   - Admin: dashboard met aggregatie ("420 huishoudens, 312 voldaan, 108 risico")
5. Hooks voor #7 (boete) en #17 (communicatie/herinnering).

**Open punten:**
- Speler O17+ in gezin met JO13 — telt zijn dienst alleen voor zichzelf, of mag hij ook bijdragen aan de ouderplicht voor het broertje? Voorstel: nee, plichten zijn gescheiden (consistent met "plichten optellen" beslissing). Bestuur heeft dit punt niet expliciet geadresseerd; standaard houden op gescheiden.

---

### 7. Boete for missed duty

**From the doc:** Boete bij minder dan 2 diensten per jaar. Geen bedrag, geen mechanisme genoemd.

**Status in Rondo:** Volledig werkende boete-pipeline aanwezig — `rondo_invoice` CPT met `invoice_type=discipline`, Mollie + Rabobank betaalverzoek, Lettermint reminders (2 + 3 weken). Direct herbruikbaar voor vrijwilligers-boetes.

**Decisions (bestuursvergadering 2026-05-26):**
- **Trigger: alleen no-show direct.** Geen eindseizoen-batch. Wie zich nooit aanmeldt en dus nooit no-show is, krijgt geen boete via dit mechanisme. Dat is een bewuste keuze — sociale druk en gesprek (Guido per team) moet zorgen voor signups; sanctie is alleen voor mensen die zich aanmelden en dan verzaken.
- **Ontvanger: primaire ouder.** Boete naar contributie-account van de primaire ouder uit `relationships`-repeater. Sluit aan op bestaande contributie-flow. Bij O17+ speler: naar speler zelf.
- **Vrijkoop: nee.** Plicht blijft staan. Boete is pure sanctie, geen alternatief. Volgend seizoen begint iedereen weer met 2 diensten.
- **Bedrag: €30 per gemiste dienst.** Eén invoice per no-show event.

**Datamodel:**
- Nieuw `invoice_type=volunteer_fine` in bestaande invoice CPT
- Eigen `heading_type=volunteer_fine` voor email template
- Line item: "Gemiste vrijwilligersdienst op [datum] — [dienst_type naam]"

**Plan:**
1. `invoice_type=volunteer_fine` toevoegen aan `group_invoice_fields.json` enum.
2. Service `VolunteerFineGenerator::on_no_show_marked($shift, $person)` die meteen een invoice creëert:
   - €30 bedrag
   - Line item met dienst-info
   - `person` = primaire ouder van het huishouden (of speler zelf voor O17+)
   - Mollie payment link gegenereerd
3. Hook in no-show endpoint (#6): bij `mark_no_show()` → trigger `VolunteerFineGenerator`.
4. Email template: nieuwe `heading_type` toevoegen in `EmailTemplates` (post-v34.0 refactor) of in `FinanceConfig`-equivalent.
5. Hardship/coulance: admin-knop "kwijtschelden met reden" op invoice (kan bestaande discipline-kwijtschelding patroon hergebruiken).

**Primaire ouder bepalen:**
Eerste persoon in `relationships`-repeater van het JO15- kind met `relationship_type=ouder`. Bij meerdere ouders: oudste relatie-entry (eerste in array). Admin kan handmatig wijzigen via "factuur-ontvanger" veld als nodig.

**Open punten:**
- Bevestigingsmail aan speler/ouder als no-show wordt gemarkeerd, met uitleg en betaallink. Hergebruikt Lettermint.
- Reminder-cadans hergebruikt discipline-flow (2 + 3 weken).

---

### 8. VOG-plicht voor iedereen

**From the doc:** "Iedereen moet een VOG aanleveren."

**Status in Rondo:** Sterke basis aanwezig — `datum-vog` ACF, `rondo_vog` capability, `/rondo/v1/vog` endpoint, `VogEmail` met aanvraag/verlenging templates, VOG-lijst + CSV export, en (volgens Joost) reeds ingebouwde geldigheidsregel.

**Decisions (2026-05-26):**
- **Geldigheidsregel: hergebruik bestaande logica.** Niet opnieuw bepalen; bij implementatie verifiëren welke termijn momenteel actief is en daar bij aansluiten.
- **Hard block in signup.** Een persoon zonder geldige VOG ziet geen shifts in `/vrijwillig`. De eligibility-filter uit #4 wordt uitgebreid met VOG-validatie. Geen "soft warning"-route.
- **Scope: alleen wie onder de doelgroep valt** (eligible pool uit #1). Niet alle leden ≥18 — alleen wie ouderplicht of spelersplicht heeft, plus bestaande commissieleden.
- **Bulk-rollout (bestuursbesluit 2026-05-26): handmatig per persoon.** Geen Sportlink OAS-bulk-script. VOG-coördinator stuurt aanvragen via de bestaande VogEmail-templates één voor één naar mensen die zich aanmelden voor diensten. De hard-block in signup zorgt voor de natuurlijke trigger ("ik wil aanmelden → o, ik heb geen geldige VOG → coördinator stuurt aanvraag").

**Plan:**
1. Bestaande VOG-geldigheidsregel localiseren en documenteren (waarschijnlijk in `VogEmail` of een gerelateerde class).
2. Signup-validator uitbreiden: `is_eligible_for_shift(person, shift)` returnt false als `vog_required` op `dienst_type` én VOG ongeldig.
3. Member-facing UI: persoonlijke `/vrijwillig` pagina toont banner "VOG aanvragen" als status incompleet, met deeplink naar bestaande aanvraagflow.
4. Cron: 3 maanden voor expiratie verstuurt `VogEmail` verlengingsbericht (waarschijnlijk al gedeeltelijk geregeld).
5. Bulk-rollout-script staat klaar als sub-taak na bestuurlijke beslissing.

**Open punten:**
- Vergeet niet: in beleidsactiveringsbericht (#17 communicatie) duidelijk maken dat geen VOG = geen diensten kan doen.
- Hard-block UX: wanneer iemand in `/vrijwillig` geen shifts kan zien wegens ontbrekende VOG, moet de pagina expliciet uitleggen waarom en hoe de VOG aan te vragen — anders krijgen we support-vragen.

---

### 9. Online IVA / alcoholtraining

**From the doc:** "Verplichting tot online training alcohol schenken." Geldt voor wie achter de bar staat. IVA = Instructie Verantwoord Alcoholschenken, gratis online via NOC*NSF.

**Status in Rondo:** Geen veld, geen tracking. Analoog aan VOG-systeem opzetten.

**Decisions (2026-05-26):**
- **Registratie via upload + admin-approval.** Persoon uploadt PDF-certificaat via `/vrijwillig/profiel`; admin keurt goed → status `geldig`. Veiliger dan zelf-rapportage, beter dan handmatige admin-only invoer.
- **Mapping op `dienst_type`**: alleen `Kantine — bar` vereist IVA. Keuken-prep en keuken-verkoop niet (default off; admin kan later aanvinken voor specifieke shifts indien gewenst).
- **Geldigheidstermijn (bestuursbesluit 2026-05-26): 5 jaar.** `datum-iva` + 5 jaar = expiratiedatum. Verlengingsreminder 3 maanden voor expiratie.
- **Approval-rol (bestuursbesluit 2026-05-26): bestuurslid kantine.** Nieuwe capability `rondo_iva_approve` toegekend aan de WP-account van het bestuurslid kantine.

**Datamodel (ACF velden op Person):**
- `datum-iva` (date — datum certificaat behaald)
- `iva-certificaat` (file upload, PDF) — bewijsstuk
- `iva-approved` (boolean, admin-only) — goedgekeurd door bestuur

**Plan:**
1. ACF veldgroep `group_iva_fields` met bovenstaande drie velden op Person CPT.
2. REST: hergebruik bestaande person endpoints; nieuwe sub-endpoint `/rondo/v1/iva/upload` met file-upload permission gate.
3. Admin-UI: filter "IVA pending approval" op personen-lijst; één-klik approve/reject.
4. Signup-validator (#4): bij shift met `iva_required=true` → checken op `iva-approved=true` en `datum-iva` binnen geldigheidsvenster.
5. Member-UI: persoonlijke profiel-sectie met upload-knop + status (`niet ingeleverd` / `wacht op goedkeuring` / `geldig` / `verlopen`).
6. Lettermint-mail: bevestiging na approval; verlengingsherinnering 3 maanden voor expiratie (als termijn-besluit dat oplevert).

**Open punten:**
- Bulk-onboarding bestaande bar-vrijwilligers: bekend bar-personeel uitvragen op bestaande IVA-certificaten en handmatig invoeren (analoog aan VOG-aanpak uit #8).
- IVA-certificaat uploaden — bewaartermijn? GDPR-overweging: na 5 jaar (na expiratie) automatisch verwijderen? Detail voor implementatie.

---

### 10. Talentregistratie

**From the doc:** "Geef je talent aan en we kijken samen welke taken je kan doen. Via Rondo, een meer werkbaar alternatief voor Sportlink, komen we hiervoor met een voorstel."

**Status in Rondo:** Geen skills/talent-veld; bestaat nog niet.

**Decisions (2026-05-26):**
- **Uitgesteld naar v2.** Eerst hoofdsysteem (1–9 + 14+) draaiend krijgen; talent-laag pas erna. Beleidstekst zegt "komen we hiervoor met een voorstel" — past bij gefaseerde uitrol.
- **Hybride structuur**: vaste tags voor zoeken/filteren + vrije toelichting voor nuance. Beste van twee werelden.
- **Half-geautomatiseerd matchen**: talenten linken aan relevante `dienst_type` records; admin ziet kandidaten, neemt contact op. Niet self-service signup voor talent-taken — talent-werk is intrinsiek mensenwerk.

**Datamodel (v2 — niet bouwen in v1):**
- ACF op person: `talent_tags` (multi-select taxonomy), `talent_omschrijving` (textarea)
- Nieuwe taxonomy `talent_categorie` met ~25 vooraf gedefinieerde termen (ICT, Fotografie, Boekhouden, Onderhoud, Coaching, Evenementen, Communicatie/PR, Klusjes, Tuinieren, Sportmasseur, Jurybank, Vertalen, etc.)
- Admin-UI: filter "personen met talent X" op personen-lijst.

**Plan (v2):**
1. Taxonomy + ACF velden registreren.
2. Optionele linking veld op `dienst_type` (#2): `gerelateerde_talenten` (multi-relatie naar `talent_categorie`).
3. Admin dashboard widget: "Beschikbare talenten" met per-categorie aantal personen.
4. Member-UI: profiel-sectie "Mijn talenten" in `/vrijwillig/profiel`.
5. Geen impact op #6 counter — talent-werk telt mee als reguliere dienst alleen als admin er een `dienst_shift` voor aanmaakt en de persoon toewijst.

**Open punten:**
- Welke initiële tag-lijst? Bestuur/Hans/Marieke kunnen aanlevering.
- Hoe motiveer je mensen om hun talent te registreren? Misschien gekoppeld aan onboarding-flow (#14).

---

### 11. Sleutel-uitgifte

**From the doc:** Terreinmeester reikt sleutel + ranja uit; trainer/leider levert sleutel weer in. Vanaf januari 2026 verhuist deze functie naar kantine.

**Status in Rondo:** N.v.t. — niet bouwen.

**Decision (2026-05-26):** Sleutelbeheer blijft fysiek: paper-logboek of whiteboard bij terreinmeesterruimte/kantine. Fysieke realiteit leent zich niet voor digitalisering; sociale controle volstaat.

**Plan:** Geen Rondo-werk.

**Restant — wel relevant**: `sleutel_involved` flag op `dienst_type` (#2) blijft staan zodat we later, mocht het toch nodig zijn, een logging-feature kunnen toevoegen zonder schemamigratie. Voor nu: alleen informatie-veld in admin-overzicht.

---

### 12. Trainer/leider extra taken

**From the doc:** Kleedkamers controleren (sleutel inleveren), verlichting aan/uit bij trainingen.

**Status in Rondo:** Trainer/leider tracking bestaat via `work_history` met `job_title`. Geen aparte verantwoordelijkheden-laag.

**Decision (2026-05-26):**
- **Trainer/leider = vrijgesteld van 2-diensten-plicht.** Analoog aan commissielid (#2/#16). Het inherente werk dat ze al doen voor het team telt als ruimschoots voldoen aan vrijwilligersbijdrage.
- **Extra doordeweekse taken** (kleedkamer controle, verlichting) zijn impliciet onderdeel van de rol — alleen documenteren in beleidsdoc en in de team-kickoff communicatie (#13). Geen aparte tracking in Rondo.

**Plan:**
1. Vrijstellingsregel in eligibility-derivatie (#1) en counter (#6) uitbreiden: persoon met actieve `work_history` entry met `job_title` in toegestane trainer/leider-set EN `is_current=true` → vrijgesteld.
2. Welke job_titles als trainer/leider? Hergebruik bestaande "player roles" Settings-mechanisme — er is al een config van titels per categorie. Voeg analoge config toe voor "staff roles" (= vrijgesteld).
3. Geen UI-werk; vrijstelling wordt zichtbaar in de counter-widget op `/vrijwillig/mijn-shifts` ("Je bent vrijgesteld als Leider O15-1").

**Staff-roles voor vrijstelling (bestuursbesluit 2026-05-26):**
Trainer, Hoofdtrainer, Assistent-trainer, Leider, Teammanager, Coördinator, Scheidsrechter.

Deze waarden komen in een config-constante `RONDO_STAFF_ROLES_VRIJGESTELD`. Bij wijziging in `job_title` waarden uit Sportlink: lijst opnieuw kalibreren.

**Open punten:**
- Wat met ex-trainers (rol beëindigd half seizoen)? Vrijgesteld voor heel seizoen of pro-rato? Voorstel: voor het seizoen waarin ze actief waren, vrijgesteld; volgend seizoen vol mee.

---

### 13. Team-communicatie met beeldmateriaal

**From the doc:** "Guido gaat dit begin seizoen 26-27 per team uitleggen (met beeldmateriaal in rondo) en bespreken samen met Hans en/of Marieke."

**Status in Rondo:** Team-pagina's bestaan; geen content-management voor info-pagina's; geen kickoff-status tracking.

**Decisions (2026-05-26):**
- **Beeldmateriaal op website (Astro), openbaar.** Wijkt af van letterlijke beleidstekst ("in rondo") maar pragmatisch — openbare uitleg-pagina is breder bruikbaar (ouders, nieuwe leden, ALV, social media link). Astro-project zit in deze workspace.
- **Per-team kickoff-status: veld op team-CPT.** Twee ACF-velden: `kickoff_done_at` (date) + `kickoff_notes` (textarea). Guido vinkt af na elk gesprek, eventueel met opmerkingen (acties, openstaande vragen).
- **Uitnodigingen en herinneringen: geen Rondo-mail.** Guido regelt dat zelf (WhatsApp / persoonlijk contact). Geen UI bouwen.

**Plan:**
1. **Website (Astro):** nieuwe pagina `vrijwilligersbeleid` (of `/vrijwillig`) met beeldmateriaal, intro, FAQ. Beheerd in Astro-project, los van Rondo.
2. **Rondo:** twee ACF velden op `team` CPT: `kickoff_done_at`, `kickoff_notes`.
3. **Team-lijst UI:** kolom "Kickoff" (groen vink = gedaan met datum, grijs = nog niet). Filterbaar.
4. **Optioneel admin-widget:** "Kickoffs nog te doen" widget op dashboard met team-lijst die nog op rood staat.

**Open punten:**
- Wie maakt het beeldmateriaal? Voorstel: Guido + bestuur, eventueel met externe hulp voor design.
- Inhoudelijke FAQ: wat zijn de te verwachten vragen? "Wat als ik niet kan?", "Ik ben al actief, telt dat?", "Hoe meld ik me aan?", etc.

---

### 14. Onboarding nieuwe vrijwilliger

**From the doc:** Implied — nieuwe vrijwilligers moeten op de hoogte zijn van het beleid.

**Status in Rondo:** Onboarding-infrastructuur volledig aanwezig — `onboarding-email-lid-sent` en `onboarding-email-vrijwilliger-sent` timestamps, mail templates met editor, handmatige verzendschermen.

**Decisions (2026-05-26):**
- **Eénmalige welkomstmail**, geïntegreerd in bestaande welkomstmail-flow. Geen drip-campagne, geen extra mail-tracks.
- **Handmatige trigger** via bestaande verzendschermen (v30.0). Geen automatische trigger nodig op deze iteratie.
- **Content bewerkbaar** in bestaande mail-template-editor.

**Plan:**
1. Welkomstmail-template uitbreiden met blok over vrijwilligersbeleid:
   - Korte uitleg 2-diensten plicht
   - Link naar website uitleg-pagina (#13)
   - Magic-link naar `/vrijwillig` (uit #4)
   - VOG-aanvraag link met uitleg (uit #8)
   - Verwachte timing (bv. "Plan binnen 4 weken na bevestiging je eerste dienst")
2. Geen code-wijzigingen nodig — alleen content-update in editor.

**Open punten:**
- Inhoudelijke copy moet geschreven worden door bestuur/Guido/Hans/Marieke.
- Bij IVA-vereiste (kantine-vrijwilligers): is een aparte mail-track wenselijk of komt dit in dezelfde welkomstmail? Voorstel: zelfde mail, met conditioneel blok als de persoon in kantine-pool zit.

---

### 15. Vrijwilligersvergoeding

**From the doc:** Vergoedingen worden uitgefaseerd, met uitzonderingen: kantinebeheerder blijft betaald; weekend-vrijwilligers tijdelijk betaald "om in te werken"; KNVB-norm trainers via loonadministratie.

**Status in Rondo:** Geen veld voor vrijwilligersvergoeding. Geen tracking.

**Decisions (2026-05-26):**
- **Lichte status-flag op person, geen bedragen.** Twee ACF velden:
  - `betaalde_vrijwilliger` (boolean)
  - `vergoeding_reden` (select: `kantinebeheerder` / `weekend-inwerk` / `knvb-trainer` / `anders`)
- **Betaalde vrijwilliger = vrijgesteld van 2-diensten-plicht.** Logisch — hun bijdrage staat al vast via een andere route.
- **Geen bedragen in Rondo.** Uitbetalingen lopen via loonadministratie (extern). Rondo houdt zich bij contributie en boetes (geld dat IN komt), niet bij salarissen (geld dat UIT gaat).

**Plan:**
1. ACF veldgroep uitbreiding op person CPT met de twee velden boven.
2. Vrijstellingsregel (#1, #6) uitbreiden: `betaalde_vrijwilliger=true` → vrijgesteld.
3. Admin-UI: filter "Betaalde vrijwilligers" + kolom op personen-lijst.
4. Optionele rapportage: lijstje per reden (hoeveel weekend-inwerk-vrijwilligers, etc.) voor bestuurlijk overzicht.

**Open punten:**
- Weekend-inwerk is per definitie tijdelijk. Hebben we een einddatum-veld nodig (`vergoeding_tot`)? Voorstel: ja, optionele date. Zonder einddatum = doorlopend.
- Kantinebeheerder = misschien al gemarkeerd via Sportlink-functie? Check tijdens implementatie.

---

### 16. Huidig-vrijwilliger status / vrijstellingsregeling

**From the doc:** Wie valt onder de plicht en wie niet — beleidstekst noemt het impliciet (kantinebeheerder blijft betaald, trainers krijgen KNVB-contract, commissieleden doen al iets, etc.).

**Status in Rondo:** Sportlink `huidig-vrijwilliger` boolean bestaat (read-only). `CapabilitySync` met rol-mappings. Geen consolidatie van vrijstellingsregels.

**Decisions (2026-05-26):**

Eén heldere vrijstellingsregeling met vier auto-routes en één handmatige route:

| Route | Bron | Voorbeeld |
|---|---|---|
| Actief commissielid | Alle commissies (niet alleen Rondo-managed pools) | Activiteitencommissie, Jeugdbestuur, Sponsorcommissie |
| Actieve staff (trainer/leider) | `work_history` met `job_title` in staff-set + `is_current=true` | Hoofdtrainer JO13-1, Leider O15-1 |
| Betaalde vrijwilliger (#15) | `betaalde_vrijwilliger=true` | Kantinebeheerder, weekend-inwerk |
| Handmatige vrijstelling | `vrijgesteld_handmatig=true` | Ereleden, langdurig zieken, bestuur |

Sportlink `huidig-vrijwilliger` veld blijft bestaan voor informatie maar speelt **geen rol** in de automatische vrijstellingsregel. Reden: onduidelijke semantiek; we vertrouwen op expliciete bronnen (commissie, work_history, ACF-vlaggen).

**Nieuwe ACF velden op person (handmatige vrijstelling):**
- `vrijgesteld_handmatig` (boolean)
- `vrijstelling_reden` (textarea)
- `vrijstelling_seizoen` (string, bv. "2026-2027" — leeg = doorlopend)

**Service: `VolunteerExemptionResolver`**

```php
function is_exempt(Person $person, string $season): bool {
    // 1. Actief in een commissie?
    if ($this->has_active_commissie($person)) return true;

    // 2. Actieve staff-rol?
    if ($this->has_active_staff_role($person)) return true;

    // 3. Betaalde vrijwilliger?
    if ($person->get('betaalde_vrijwilliger')) return true;

    // 4. Handmatige vrijstelling voor dit seizoen?
    if ($person->get('vrijgesteld_handmatig') &&
        ($person->get('vrijstelling_seizoen') === '' ||
         $person->get('vrijstelling_seizoen') === $season)) return true;

    return false;
}
```

**Plan:**
1. ACF veldgroep `group_volunteer_exemption_fields` met de drie nieuwe velden.
2. `VolunteerExemptionResolver` service in `includes/services/`.
3. Eligibility-derivatie (#1) en counter (#6) consumeren deze service.
4. Admin-UI: filter "Vrijgesteld" op personen-lijst + reden zichtbaar.
5. Per-persoon detailpagina: vrijstellings-banner met bron ("Vrijgesteld via commissie X" / "Handmatig — reden Y").

**Besluiten bestuursvergadering 2026-05-26:**
- **Welke commissies tellen vrijstellend?** Alle. Geen whitelist of uitsluitingslijst.
- **Welke staff-roles?** Trainer, Hoofdtrainer, Assistent-trainer, Leider, Teammanager, Coördinator, Scheidsrechter (zie #12).

---

### 17. Communicatieplan / website + Vrijwilligers-dashboard

**From the doc:** ALV-toelichting + nieuwsbrief met link naar uitleg op de website. Oktober/november evaluatie.

**Status in Rondo:** Geen Vrijwilligers-IA. VOG-lijst staat momenteel los in admin. Geen aggregaat-dashboards.

**Decisions (2026-05-26):**

**Drie sporen, een structureel inzicht:**

**A. Website (Astro) — openbare uitleg-pagina**
- Pad: `/vrijwilligersbeleid` of `/vrijwillig`
- Inhoud: beleidsuitleg, FAQ, signup-instructies
- **Joost schrijft eerste versie, bestuur reviewt**
- Beheerd in Astro-project, los van Rondo

**B. Rondo — nieuwe top-level "Vrijwilligers" navigatie-sectie**

Dit is een **structurele IA-beslissing** die meerdere features raakt. In plaats van losse dashboard-widget bouwen we één samenhangende Vrijwilligers-sectie waar alles bij elkaar staat:

```
Vrijwilligers
├── Dashboard (overzicht & stats)
├── Shifts (planner / rooster — uit #3)
├── Aanmeldingen (admin-zicht op signups — uit #4)
├── Poules (commissie-views — uit #5)
├── Vrijstellingen (handmatig + automatisch — uit #16)
├── VOG (bestaande lijst — verplaatst van standalone naar onder Vrijwilligers)
├── IVA (uit #9 — nieuwe sub-sectie)
└── Boetes (uit #7 — na bestuursbesluit)
```

**Dashboard-content (volgens beleidsevaluatie oct/nov + lopend gebruik):**
- % huishoudens met ≥1 dienst gedaan dit seizoen
- % huishoudens met ≥2 (voldaan aan plicht)
- No-show rate per dienst_type
- Poule-bezetting per shift (komende 4 weken)
- Aantal openstaande VOG-aanvragen / IVA-approvals

**C. Nieuwsbrief**
- Hergebruikt bestaande Laposta-integratie via rondo-sync
- Geen Rondo-werk; content komt van bestuur/PR

**Plan:**
1. **Rondo:** nieuwe top-level admin route `/vrijwilligers` met sub-navigation.
2. **REST endpoint** `/rondo/v1/volunteer-stats` met aggregaten voor dashboard.
3. **Migratie:** bestaande VOG-lijst route verhuizen naar `/vrijwilligers/vog` (oude pad redirect).
4. **Sub-sectie IVA:** nieuwe route `/vrijwilligers/iva` met dezelfde patroon als VOG.
5. **Website:** nieuwe Astro-pagina, Joost schrijft draft, bestuur reviewt.

**Cross-cutting implicaties (impact op andere punten):**
- **#9 (IVA):** UI komt onder Vrijwilligers-sectie, niet standalone.
- **#3, #4, #5, #7, #16:** admin-UI's vallen allemaal onder Vrijwilligers in de IA. Geen losse menu-items.
- **VOG (huidige feature):** verhuist van standalone naar Vrijwilligers-sub-sectie. Geen functionele wijziging, alleen navigatie.

**Open punten:**
- Wie ontwerpt de Vrijwilligers-IA visueel? Hergebruik bestaand admin-design.
- Member-facing route `/vrijwillig` (uit #4) is iets anders dan admin-route `/vrijwilligers` — naamgevingsverschil duidelijk maken in UI.
- Inhoud website-pagina: Joost begint, draft maand mei/juni, bestuur reviewt voor ALV.

---

## Suggested phasing

Afhankelijkheids-analyse leidt tot vijf opeenvolgende fasen + één v2-fase:

### Fase A — Foundation (intern, geen user impact)
Eligibility en vrijstellingsregels eerst, want #3, #6, #4 hangen hieraan.

- **#1** Target group filtering — derived `eligible_units` + REST endpoint
- **#2** Task catalog — `dienst_type` CPT met regels
- **#5** Pool management — drie commissies + sync-whitelist
- **#15** Vrijwilligersvergoeding — ACF velden op person
- **#16** Vrijstellingsregeling — `VolunteerExemptionResolver` service
- **#12** Trainer/leider vrijstelling — config van staff-roles

**Output:** Backend kan voor elke persoon zeggen: (a) zit in doelgroep, (b) is vrijgesteld, (c) hoort tot welke pool/rol. Geen UI.

### Fase B — Vrijwilligers-shell + certificaten
Admin-zicht en certificatenflow voor #3.

- **#17 (admin gedeelte)** Top-level Vrijwilligers navigatie + VOG-verhuizing
- **#8** VOG hard-block voorbereiding (scope uitbreiden naar eligible pool)
- **#9** IVA — ACF velden + upload + admin-approval UI

**Output:** Admin kan personen-vrijstelling beheren, VOG en IVA bekijken/beheren onder één sectie.

### Fase C — Scheduling core (intern)
Het hart van het systeem — admin kan shifts plannen.

- **#3a–b** `shift_template` + `dienst_shift` CPTs + template-expander
- **#6** Counter service (`VolunteerObligationCalculator`)

**Output:** Admin kan shifts aanmaken, personen toewijzen, counters zien. Nog geen member-facing.

### Fase D — Member surface
Het beleid voor het eerst zichtbaar voor leden.

- **#4** `/vrijwillig` met Magic Login, signup/cancel
- **#3c–d** Frontend kalender + notifcaties
- **#14** Welkomstmail content-update
- **#13** Website-uitleg-pagina + `kickoff_done_at` op team
- **#17 (dashboard)** Evaluatie-stats

**Output:** Leden kunnen aanmelden. Beleid is operationeel.

### Fase E — Sancties (na bestuursbesluit)
- **#7** Boete-generatie (eindseizoen + no-show)
- **#6 multi-kind regel** (bestuursbesluit verwerken)
- **#8 bulk-VOG-rollout** (bestuursbesluit verwerken)
- **#9 IVA-geldigheidstermijn** (bestuursbesluit verwerken)

### Fase F — v2
- **#10** Talentregistratie

### Niet bouwen
- **#11** Sleutel-uitgifte (paper/whiteboard)

## Cross-cutting decisions

- **Eén top-level Vrijwilligers-sectie** in Rondo admin met sub-routes voor dashboard / shifts / aanmeldingen / poules / vrijstellingen / VOG / IVA / boetes. Bestaande VOG-route verhuist hierheen. Beslist in #17.
- **Magic Login plugin** voor passwordless auth in member-facing `/vrijwillig`. Beslist in #4.
- **Vrijstellingsregeling** uit #16 wordt consumeerd door eligibility (#1) en counter (#6) via dezelfde service. Geen logica-duplicatie.
- **Activiteiten blijven buiten Rondo** (#2) — alleen de poule (#5) wordt gemodelleerd, geen individuele evenementen of shifts.

## Bestuursbesluiten 2026-05-26 (verwerkt)

Zeven blokkerende vragen beantwoord; alle besluiten verwerkt in de feature-secties hierboven.

| # | Vraag | Besluit |
|---|---|---|
| 1 | Multi-child scaling | Per kind met contributie-korting: kind 1 = 2, kind 2 = 1,5, kind 3+ = 1 elk. Floor afronden (zie #6). |
| 2 | Boete-trigger | Alleen no-show direct (zie #7). |
| 3 | Boete-ontvanger | Primaire ouder uit relationships; speler zelf bij O17+ (zie #7). |
| 4 | Boete-bedrag | €30 per gemiste dienst (zie #7). |
| 5 | Vrijkoop | Nee — plicht blijft, boete is sanctie (zie #7). |
| 6 | VOG bulk-rollout | Handmatig per persoon door VOG-coördinator (zie #8). |
| 7 | IVA termijn | 5 jaar (zie #9). |
| extra | IVA approver-rol | Bestuurslid kantine (zie #9). |
| extra | Staff-roles vrijstelling | 7 rollen: Trainer, Hoofdtrainer, Assistent-trainer, Leider, Teammanager, Coördinator, Scheidsrechter (zie #12). |
| extra | Vrijstellende commissies | Alle (zie #16). |

## Next steps

1. ~~Roadmap-doc reviewen, eventueel bijstellen.~~ Done.
2. ~~Bestuursvergadering — zeven open vragen.~~ Done 2026-05-26.
3. **Fase A planning** — creëer phase-folders in `.planning/phases/` voor de eerste implementatie-laag (eligibility, taakcatalogus, vrijstellingen).
4. **Inhoudelijke content** voor website-uitleg-pagina (#13/#17) — Joost werkt eerste versie uit.
5. **Fase F36 milestone-tracking** — koppel deze roadmap aan bestaande `MILESTONES.md` zodra Fase A start.
