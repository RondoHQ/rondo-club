# Ouder/verzorger toevoegen vanuit Rondo

**Status:** voorstel

**Datum:** 2026-08-17

**Repositories:** `rondo-club`, `rondo-sync`

**Doel:** vanuit de relatiekaart van een kind een bestaande persoon als ouder/verzorger koppelen of een nieuwe ouder/verzorger aanmaken, waarna rondo-sync deze relatie veilig naar een vrij ouder/verzorger-slot van het kind in Sportlink schrijft.

## Samenvatting

De bestaande basis is bruikbaar:

- Rondo bewaart personen als WordPress `person` posts en relaties in het native `relationships` veld.
- De relatietypen `parent` en `child` bestaan al en worden automatisch in beide richtingen bijgehouden.
- rondo-sync leest de twee Sportlink-slots `NameParent1/2`, `EmailAddressParent1/2` en `TelephoneParent1/2` en maakt daar nu al zelfstandige ouderprofielen en relaties van.
- De reverse-sync draait elke vijf minuten, maar verwerkt momenteel alleen contact-, adres- en administratieve velden van leden. Relaties en oudergegevens worden nog niet teruggeschreven.

De aanbevolen oplossing bestaat daarom uit twee delen:

1. **rondo-club** voegt een begeleide, server-side gevalideerde actie toe voor "bestaande persoon" of "nieuwe ouder/verzorger". Een nieuwe ouder en de relatie worden als één domeinactie opgeslagen.
2. **rondo-sync** breidt de bestaande reverse-sync uit met een persistente ouder-slotwachtrij. Die vergelijkt de gewenste Rondo-relaties met verse Sportlink-data, gebruikt een bestaand passend slot of het eerste volledig vrije slot en overschrijft nooit stilzwijgend een andere ouder.

## Productbeslissingen

### 1. Keuzescherm achter het plusje

Het bestaande plusje in de kaart **Relaties** opent eerst twee keuzes:

- **Bestaande persoon koppelen** — opent de huidige persoonszoeker en relatietypekeuze. Als het type `Ouder / Verzorger` is, valt de relatie ook onder de Sportlink-sync.
- **Nieuwe ouder / verzorger** — opent een compact formulier met naam, e-mailadres en optioneel telefoonnummer. Het relatietype staat vast op `Ouder / Verzorger`.

Andere bestaande relatietypen blijven via de eerste route beschikbaar.

### 2. Gegevens van een nieuwe ouder

Het formulier schrijft:

| Invoer | Rondo-veld | Sportlink-veld |
|---|---|---|
| Naam, verplicht | `first_name` (volledige weergavenaam), `last_name` leeg | `NameParentN` |
| E-mail, verplicht | `email_1` | `EmailAddressParentN` |
| Telefoon, optioneel | `telephone_1` | `TelephoneParentN` |
| Persoonstype | `person_type = member` | Niet van toepassing |

Een enkel naamveld sluit aan op de huidige Sportlink-parentpipeline: Sportlink levert ook één volledige oudernaam en rondo-sync slaat die nu al als `first_name` op. Automatisch splitsen van Nederlandse tussenvoegsels is te foutgevoelig.

Een geldig e-mailadres blijft verplicht. De huidige parentpipeline gebruikt genormaliseerde e-mail als identiteit en deduplicatiesleutel; zonder e-mail kan de eerstvolgende voorwaartse sync de handmatig gemaakte ouder niet betrouwbaar terugvinden.

### 3. Slotbeleid in Sportlink

Sportlink heeft per kind maximaal twee ouder/verzorger-slots. De reverse-sync hanteert daarom deze vaste volgorde:

1. Lees de actuele oudervelden rechtstreeks van de Sportlink-pagina van het kind.
2. Als een slot al dezelfde genormaliseerde e-mail bevat, werk precies dat slot bij.
3. Anders: kies het eerste slot waarvan naam, e-mail en telefoon alle drie leeg zijn.
4. Als beide slots door andere ouders bezet zijn, schrijf niets en registreer een zichtbare foutstatus.

Een gedeeltelijk gevuld slot geldt als bezet. De sync wist of vervangt nooit automatisch een andere ouder.

### 4. Identiteit en duplicaten

- Bij **nieuwe ouder** controleert de backend eerst op een bestaande persoon met hetzelfde genormaliseerde e-mailadres.
- Een geldige bestaande match geeft `409 Conflict` terug met de suggestie om **Bestaande persoon koppelen** te gebruiken; er wordt niet stil een duplicaat gemaakt.
- Matches op het kind zelf of een bekende broer/zus worden niet als ouder hergebruikt. Dit volgt dezelfde beveiliging als de bestaande parentpipeline.
- Twee verschillende ouders met hetzelfde e-mailadres blijven buiten scope van deze eerste versie. De huidige voorwaartse sync voegt zulke Sportlink-ouders ook samen op e-mail; ondersteuning vereist een afzonderlijke migratie naar een andere ouderidentiteit.

### 5. Rechten

Aanbevolen: alleen beheerders en gebruikers met `ledenadministratie` mogen een ouderrelatie aanmaken die naar Sportlink wordt geschreven. Zij mogen zowel de bestaande- als nieuwe-ouderroute gebruiken. Andere gebruikers behouden hun huidige mogelijkheden voor niet-Sportlink-relaties.

Reden: een ouder toevoegen verandert het officiële ledenrecord in Sportlink en is daarmee ledenadministratie, niet alleen lokaal relatiebeheer.

### 6. Asynchrone gebruikersfeedback

Na de lokale opslag toont Rondo de relatie direct met een compacte status:

- `Wacht op Sportlink`
- `Gesynchroniseerd`
- `Actie nodig` met een concrete reden, bijvoorbeeld "Beide oudervelden in Sportlink zijn bezet"

De UI belooft geen directe Sportlink-mutatie. Normaal wordt de wijziging binnen één reverse-synccyclus van maximaal ongeveer vijf minuten verwerkt.

## Rondo Club-ontwerp

### REST-domeinactie

Voeg één endpoint toe dat beide ouderroutes afhandelt:

`POST /rondo/v1/people/{child_id}/parents`

Bestaande persoon:

```json
{
  "mode": "existing",
  "parent_id": 123
}
```

Nieuwe ouder:

```json
{
  "mode": "new",
  "name": "Sam de Vries",
  "email": "sam@example.org",
  "phone": "06 12345678"
}
```

Het endpoint:

1. controleert de bevoegdheid;
2. controleert dat het kind een gepubliceerd `person` record met `knvb_id` is en geen read-only oud-lid;
3. zoekt het relatietype op slug `parent` op, nooit op een hard-coded term-ID;
4. valideert de ouder en het e-mailadres;
5. maakt bij `mode=new` het ouderrecord via WordPress- en `Fields`-API's;
6. voegt de ouderrelatie aan het volledige `relationships` veld van het kind toe;
7. laat de bestaande inverse-relatieservice automatisch de `child`-relatie op de ouder maken;
8. zet de syncstatus voor deze combinatie op `pending`;
9. retourneert ouder, kind en status.

Bij een fout vóór het leggen van de relatie wordt een zojuist gemaakt, nog ongerelateerd ouderrecord weer verwijderd. Er komen geen custom WordPress-tabellen.

### Domeinvalidatie

Centraliseer de volgende regels in een `ParentRelationshipService`, zodat REST, native field-validatie en tests dezelfde logica gebruiken:

- geen relatie met zichzelf;
- geen dubbele ouderrelatie;
- een Sportlink-kind mag niet door een nieuwe write boven twee syncbare ouderrelaties uitkomen;
- een syncbare ouder heeft een naam en geldig `email_1`;
- een oudertoevoeging gebruikt altijd de slugs `parent` en `child`, niet productie-specifieke IDs;
- bestaande data met meer dan twee ouders blijft leesbaar en bewerkbaar voor niet-gerelateerde velden; alleen een verdere toename wordt geblokkeerd.

### Syncstatus-opslag

Gebruik native post meta op het kind, ontsloten via een read-only canoniek veld, bijvoorbeeld `sportlink_parent_sync_statuses`. Elke rij bevat minimaal:

- `parent_id`
- `state` (`pending`, `synced`, `error`)
- `slot` (`1`, `2` of `null`)
- `message`
- `updated_at`

rondo-sync schrijft resultaten terug via een service-endpoint dat alleen voor de bestaande sync-integratie toegankelijk is:

`POST /rondo/v1/people/{child_id}/parent-sync-status`

De status is ondersteunende informatie; de Rondo-relatie blijft de gewenste toestand.

### Frontend

Splits de huidige `RelationshipEditModal` conceptueel in:

- een kleine keuzeweergave;
- de bestaande generieke relatie-editor;
- een `NewParentModal` met naam, e-mail en telefoon;
- een statusbadge in de relatiekaart voor ouderrelaties van personen met een `knvb_id`.

Na succes worden de cache-items van kind, ouder, personenlijst en relatiegegevens ongeldig gemaakt. Bij `409` wordt de bestaande match getoond met een directe actie om die persoon te koppelen.

## rondo-sync-ontwerp

### Waarom een aparte wachtrij

Ouderrelaties zijn geen enkel plat veld: één Rondo-ouder bestaat uit drie waarden, wordt aan een kind gekoppeld en moet op een veilig gekozen Sportlink-slot landen. Dit past niet goed in `rondo_club_change_detections`, dat losse veldwijzigingen per KNVB-ID verwerkt.

Voeg daarom een eigen SQLite-tabel toe, bijvoorbeeld `parent_slot_sync_jobs`:

| Kolom | Doel |
|---|---|
| `id` | Job-ID |
| `child_knvb_id` | Sportlink-identiteit van het kind |
| `child_rondo_id` | Rondo-ID voor statuscallback |
| `parent_rondo_id` | Gewenste ouder |
| `desired_json` | Naam, genormaliseerde e-mail en telefoon |
| `desired_hash` | Idempotente wijzigingsdetectie |
| `state` | `pending`, `retry`, `blocked`, `synced`, `cancelled` |
| `slot` | Gekozen Sportlink-slot |
| `attempts` | Aantal pogingen |
| `last_error` | Laatste fout |
| tijdstempels | Detectie, volgende poging en afronding |

Een unieke logische sleutel op kind, ouder en gewenste hash voorkomt dubbele writes. Een nieuwere gewenste hash maakt oudere open jobs voor dezelfde kind/ouder-combinatie overbodig.

### Detectie

Breid de vijfminuten-reversepipeline uit met `detectParentRelationshipChanges()`:

1. Gebruik een eigen incrementele cursor met de bestaande `modified_after`-query. Deel niet de cursor of `sync_origin`-skip van de platte contactveld-detectie: een ouderrelatie moet ook worden gezien wanneer de laatst bekende voorwaartse persoons-sync als origin staat geregistreerd.
2. Beschouw zowel gewijzigde kinderen als gewijzigde ouderprofielen.
3. Expandeer een gewijzigde ouder via diens `child`-relaties naar alle actuele kinderen.
4. Haal elk geraakt kind en zijn `parent`-relaties vers uit Rondo.
5. Haal naam, `email_1` en `telephone_1` van de gekoppelde ouders op.
6. Vergelijk de gewenste combinaties met de bekende Sportlink-snapshot en bestaande jobs.
7. Maak of actualiseer persistente jobs voordat de detectiecursor opschuift.

Daardoor worden niet alleen nieuw aangemaakte ouders verwerkt, maar ook:

- een bestaande persoon die later als ouder wordt gekoppeld;
- dezelfde ouder die aan een tweede kind wordt gekoppeld;
- een wijziging van naam, e-mail of telefoon op een gekoppelde ouder.

### Sportlink-write

Voeg een gespecialiseerde parent-slotwriter toe aan de bestaande `SportlinkSession`-architectuur:

1. navigeer naar `/member/member-details/{knvb_id}/general`;
2. onderschep de verse `MemberParentalInfo` response;
3. kies idempotent het passende of vrije slot volgens het slotbeleid;
4. open de juiste oudersectie via de bestaande uitputtende `Wijzig`-knopstrategie en een verwachte parent-selector;
5. vul naam, e-mail en telefoon;
6. sla op;
7. lees de waarden opnieuw en verifieer alle drie;
8. markeer de job pas daarna als `synced` en stuur de Rondo-statuscallback.

Voor implementatie moet op productie eerst een **read-only selector-spike** plaatsvinden: bevestig inputnamen, de juiste sectie en de save-response op leden met nul, één en twee ingevulde ouder-slots. Geen enkele schrijfactie wordt gebouwd op veronderstelde selectors.

### Foutafhandeling

- Browser-, sessie- en netwerkfouten zijn tijdelijk: job blijft bestaan en krijgt een volgende poging met back-off.
- Twee bezette slots is een functionele blokkade: job wordt `blocked`, geen slot wordt overschreven en Rondo toont `Actie nodig`.
- Een ontbrekend of ongeldig e-mailadres wordt al door Rondo geblokkeerd; als oude data dit toch veroorzaakt, wordt de job functioneel geblokkeerd.
- Een inmiddels verwijderde relatie annuleert een nog niet uitgevoerde toevoegjob.
- Geblokkeerde jobs worden opnieuw bekeken als de familie in Rondo wijzigt en daarnaast periodiek, zodat een handmatig vrijgemaakt Sportlink-slot later alsnog benut kan worden.
- RunTracker en de operatorrapportage nemen pending, retry, blocked en synced aantallen afzonderlijk op.

### Voorwaartse reconciliatie

Na een geslaagde Sportlink-write leest de gewone people-pipeline de ouder weer terug. Die moet:

- het handmatig gemaakte Rondo-profiel op exacte e-mail vinden;
- diens `rondo_club_parents` mapping vastleggen;
- geen tweede ouderpost maken;
- de bestaande relatie behouden;
- dezelfde naam en contactgegevens zonder oscillatie terugschrijven.

Dit gedrag bestaat grotendeels al, maar krijgt expliciete regressietests voor een vanuit Rondo aangemaakte ouder.

## Niet in de eerste versie

- Automatisch leegmaken van een Sportlink-slot wanneer een ouderrelatie in Rondo wordt verwijderd. Dat is destructiever dan toevoegen en vraagt een aparte bevestiging, auditregel en beleid voor gedeelde ouders. Een open toevoegjob wordt wel geannuleerd.
- Meer dan twee Sportlink-ouders per kind.
- Twee verschillende ouderprofielen met hetzelfde e-mailadres.
- Synthetisch splitsen van een volledige oudernaam in voornaam, tussenvoegsel en achternaam.

## Implementatiefasen

### Fase 0 — Sportlink-spike en contract vastleggen

- Inspecteer de oudersectie read-only met `SportlinkSession` voor nul, één en twee slots.
- Leg selectors, editsectie, savegedrag en responsevorm vast in tests/fixtures.
- Bevestig dat `NameParentN`, `EmailAddressParentN` en `TelephoneParentN` via de UI wijzigbaar zijn voor actieve jeugdleden.
- Stop en herontwerp als Sportlink geen betrouwbare parent-write via de UI toelaat.

**Gate:** selectorcontract is bewezen; er is nog niets voor gebruikers zichtbaar.

### Fase 1 — Rondo Club domein en API

- Bouw `ParentRelationshipService` en beide REST-endpoints.
- Los de benodigde `parent`/`child` termen per slug op. Voeg geen nieuwe afhankelijkheid toe op de productie-specifieke numerieke constanten in `InverseRelationships`; vervang die constanten in het geraakte ouder/kindpad waar nodig door één gedeelde slugresolver.
- Voeg native read-only statusopslag toe.
- Voeg validatie, rollback en capabilitychecks toe.
- Test nieuwe en bestaande ouder, inverse relatie, duplicaat-e-mail, derde ouder, oud-lid en ontbrekende KNVB-ID.
- Documenteer endpoint en relatiecontract.

**Gate:** lokale ouderactie is volledig getest, maar de UI blijft nog uit.

### Fase 2 — rondo-sync detectie en wachtrij

- Migreer SQLite idempotent met `parent_slot_sync_jobs`.
- Detecteer geraakte gezinnen en upsert gewenste jobs.
- Voeg unit tests toe voor hashing, superseden, annuleren en retrybeleid.
- Voeg read-only previewtooling toe om per KNVB-ID gewenste versus actuele slots te tonen.

**Gate:** een dry-run toont exact welk slot zou veranderen en bewijst dat bezette slots intact blijven.

### Fase 3 — rondo-sync Sportlink-write

- Implementeer de parent-slotwriter met verse read, veilige slotkeuze, save en verificatie.
- Integreer in `pipelines/reverse-sync.js`, RunTracker en rapportage.
- Schrijf status terug naar Rondo.
- Test matching slot, eerste/tweede vrije slot, twee bezette slots, retry, idempotente herhaling en gedeeltelijk gevuld slot.

**Gate:** gecontroleerde productieproef op één testlid slaagt en een tweede run is een no-op.

### Fase 4 — Rondo Club UI

- Voeg het tweekeuzescherm en nieuwe-ouderformulier toe.
- Routeer oudertoevoegingen via het nieuwe endpoint.
- Toon pending/synced/error bij de relatie.
- Voeg concrete foutteksten en de bestaande-persoon-suggestie toe.
- Voer lint en productiebuild uit.

**Gate:** volledige flow werkt op mobiel en desktop en andere relatietypen zijn niet veranderd.

## Waarschijnlijke bestandsimpact

### rondo-club

- `src/pages/People/PersonDetail.jsx` — keuzeflow, mutations en statusweergave.
- `src/components/RelationshipEditModal.jsx` — bestaande route behouden en keuze integreren.
- nieuw component voor het compacte ouderformulier.
- `src/api/client.js` en `src/hooks/usePeople.js` — domeinendpoint en cache-invalidatie.
- `includes/class-rest-people.php` of een aparte REST-controller — create/link- en statusroutes.
- nieuwe `includes/class-parent-relationship-service.php` — validatie en atomaire domeinactie.
- `includes/class-inverse-relationships.php` — uitsluitend de gedeelde termresolutie die deze feature nodig heeft.
- `includes/config/field-registry.php` — read-only syncstatus.
- nieuwe en bestaande `tests/Wpunit/*` relatie-, permission- en REST-contracttests.

### rondo-sync

- `lib/rondo-club-db.js` — idempotente SQLite-migratie en jobquery's.
- nieuw `lib/detect-parent-relationship-changes.js` — gezinsdetectie en gewenste hashes.
- nieuw `lib/reverse-sync-parent-slots.js` — verse read, slotkeuze, write en verificatie.
- `lib/reverse-sync-sportlink.js` — gedeelde browserhelpers hergebruiken/uitpakken, niet dupliceren.
- `pipelines/reverse-sync.js` — detectie en verwerking in de bestaande vijfminutenrun.
- `lib/run-tracker.js`/rapportage waar nodig — ouderjobstatistieken.
- nieuw read-only previewtool onder `tools/`.
- unit tests voor detectie, database, slotbeleid, browsercontract en voorwaartse reconciliatie.

### Fase 5 — Reconciliatie, documentatie en uitrol

- Voeg end-to-end regressietests toe voor de eerstvolgende people-sync.
- Werk developer docs bij: people API, relationship system, contacts, people pipeline en reverse-sync.
- Verhoog bij implementatie de versies semantisch en werk de changelog van rondo-club bij.
- Rol eerst rondo-sync uit, daarna rondo-club, zodat de consumer klaarstaat voordat gebruikers jobs kunnen maken.
- Laat beide deploys en hun checks slagen voordat UAT start.

## Testmatrix

| Scenario | Verwacht resultaat |
|---|---|
| Kind zonder ouders, nieuwe ouder | Ouder 1 gevuld; relatie en inverse bestaan; status synced |
| Kind met ouder 1, nieuwe ouder | Alleen ouder 2 gevuld |
| Kind met twee andere ouders | Geen Sportlink-write; foutstatus; bestaande slots ongewijzigd |
| Bestaande Rondo-persoon gekozen | Geen nieuwe persoon; bestaand profiel gekoppeld en gesynced |
| E-mail bestaat al bij andere geldige persoon | Nieuwe route geeft 409 en biedt koppelen aan |
| Kind deelt e-mail met gezin | Kind/sibling wordt niet als ouder hergebruikt |
| Zelfde job tweemaal | Tweede uitvoering is een geverifieerde no-op |
| Tijdelijke Sportlink-storing | Lokale relatie blijft; job retryt; geen duplicaat |
| Ouderdata gewijzigd | Zelfde slot wordt op e-mail gevonden en bijgewerkt |
| Ouder aan twee kinderen | Elk kind krijgt onafhankelijk een veilig slot |
| Relatie verwijderd vóór verwerking | Pending toevoegjob wordt cancelled; geen Sportlink-write |
| People-sync na succes | Geen duplicaat; lokale mapping en relatie blijven stabiel |
| Oud-lid als kind | Ouderactie wordt vooraf geblokkeerd |
| Kind zonder KNVB-ID | Ouder kan niet via deze Sportlink-flow worden toegevoegd |

## Acceptatiecriteria

- Het plusje biedt duidelijk de keuze tussen een bestaande persoon en een nieuwe ouder/verzorger.
- Naam en e-mail zijn verplicht; telefoon is optioneel.
- Een nieuwe ouder wordt als zelfstandig Rondo-persoon opgeslagen en beide relatiekanten zijn aanwezig.
- Iedere syncbare oudertoevoeging levert een persistente, auditbare rondo-sync-job op.
- rondo-sync vult alleen een passend of volledig vrij Sportlink-slot en overschrijft nooit een andere ouder.
- De gebruiker kan in Rondo zien of de Sportlink-sync wacht, gelukt is of actie nodig heeft.
- Herhaalde runs maken geen duplicaten en veranderen een reeds correct slot niet.
- De eerstvolgende voorwaartse people-sync koppelt het Sportlink-resultaat terug aan dezelfde Rondo-persoon.
- Alle PHP-, JavaScript- en rondo-sync-tests, lint, build en CI/deploychecks zijn groen.

## Beslissingen die vóór implementatie bevestigd moeten worden

1. **Rechten:** alleen `ledenadministratie` + beheerders (aanbevolen), of alle huidige gebruikers die relaties mogen wijzigen.
2. **Zelfde e-mail voor twee ouders:** in versie 1 expliciet blokkeren (aanbevolen), of eerst de volledige ouderidentiteit in rondo-sync herontwerpen.
3. **Verwijderen:** niet naar Sportlink terugschrijven in versie 1 (aanbevolen), of de scope uitbreiden met veilig slot leegmaken en extra bevestiging/audit.
