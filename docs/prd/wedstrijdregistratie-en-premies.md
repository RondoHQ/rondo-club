# PRD Wedstrijdregistratie en premies

**Status:** Voorstel voor besluitvorming, nog niet geïmplementeerd

**Datum:** 2026-10-04

**Pilot:** AWC 1 en JO23-1, seizoen 2026-2027

**Betrokkenen:** Joost, Xander en de aangewezen wedstrijdregistrator

**Repository:** Rondo Club

## 1. Doel en afbakening

Vervang de losse spreadsheet door wedstrijdregistratie in Rondo. De registrator legt per wedstrijd
de selectie vast. De financiële beheerder controleert vervolgens per maand de aantallen voor
Nmbrs bij AWC 1 en de premies en betaalexport bij JO23-1.

De eerste versie biedt registratie onder **Teams → Wedstrijden** en maandverwerking onder
**Financiën → Wedstrijdvergoedingen**. Bestaande personen, teams, toegangscontroles en het
Sportlink-wedstrijdprogramma vormen de basis. Onderstaande architectuur is een voorstel;
de open besluiten in hoofdstuk 12 moeten vóór de betreffende bouwfase worden vastgelegd.

Bevestigd door Joost op 4 oktober 2026: alleen competitie en nacompetitie tellen mee.
Beker- en oefenwedstrijden geven geen recht op vergoeding of premie. AWC 1 krijgt een vergoeding
voor Basis/Bank plus premie bij winst of gelijkspel. JO23-1 krijgt uitsluitend premie bij winst
of gelijkspel, zonder basis- of bankvergoeding.

Binnen scope vallen basis/bankregistratie, afwezigheidsstatussen, gastspelers, maandafsluiting,
correcties, een Nmbrs-snelinvoeroverzicht, JO23-1-betaalexport en een eenmalige import.

Buiten de eerste versie vallen een rechtstreekse Nmbrs-koppeling, bankbetalingen uitvoeren,
SEPA-XML, loonberekening, fiscale kwalificatie van vergoedingen, speelminuten, wisselmomenten,
trainingsregistratie en wijzigingen aan Rondo Sync. De module gebruikt geen Mollie-betaallinks:
het gaat om uitgaande vergoedingen en een export voor verdere verwerking.

## 2. Bronnen en gecontroleerde uitgangssituatie

Bron: [Wedstrijdregistratie AWC 1 en JO23-1 2026-2027](https://docs.google.com/spreadsheets/d/1hA0NSQtPtAmEY6M83u4OsjfF4uoHwoU87yxz18aW2hA/edit),
gelezen op 4 oktober 2026 via de geopende browser en een lokale XLSX-controlekopie.
Er zijn zes tabbladen: Uitleg, AWC 1, JO23-1, Nmbrs AWC 1, Overzicht JO23-1 en Export JO23-1.
De bron is niet gewijzigd. Persoonslijsten en IBAN's worden niet in deze PRD of testfixtures opgenomen.

### Vastgestelde werking

- `Uitleg!A22:A30`: datum, tegenstander, thuis/uit en uitslag per wedstrijd. De uitslag vermeldt
  de thuisploeg eerst. De keuzes zijn Basis, Bank, Niet in selectie, Geblesseerd, Geschorst en
  Ander team. Leeg betekent nog onbekend.
- `Uitleg!A44:A58`: Nmbrs krijgt aantallen voor handmatige snelinvoer. JO23-1 krijgt €15 per punt,
  dus €45 bij winst en €15 bij gelijkspel; zowel Basis als Bank telt mee.
- `Nmbrs AWC 1!A7:G8`: naam, Nmbrs-naamnotatie, dagen, L3090 winst, L3091 gelijkspel,
  U2150 basisvergoeding en bankvergoeding zonder vermelde looncode.
- `Export JO23-1!A1:E1`: IBAN begunstigde, naam begunstigde, bedrag, omschrijving en uitvoerdatum.
  De instellingen staan in `G1:H3`. De uitleg noemt een apart Apps Script dat XLSX aanmaakt.

### Rekenkundige controle van september 2026

Alle spelersregels zijn onafhankelijk nagerekend uit de geregistreerde statussen, datums,
thuis/uit en uitslagen. Ze sluiten aan op de opgeslagen Google Sheets-uitkomsten.

| Controle | AWC 1 | JO23-1 |
|---|---:|---:|
| Wedstrijden in september | 4 | 4 |
| Basisregistraties | 44 | 44 |
| Bankregistraties | 24 | 17 |
| Deelnames, basis plus bank | 68 | 61 |
| Winstpremies, aantal spelerdeelnames | 0 | 15 |
| Gelijkspelpremies, aantal spelerdeelnames | 17 | 31 |
| Premiebedrag | Niet berekend in sheet | €1.140 |

JO23-1 heeft 21 betaalregels. Zeven regels hebben geen bruikbaar IBAN, samen €255.
Het bedrag volgt uit `15 × €45 + 31 × €15 = €1.140`. Dit is een controle van de ingevoerde
gegevens, geen bevestiging dat de opstellingen of uitslagen overeenkomen met wedstrijdformulieren.

### Beperkingen en bevindingen

1. De wedstrijdformules verwijzen naar `E:AH`, maximaal 30 wedstrijden. Alleen een 31e kolom
   kopiëren, zoals de uitleg adviseert, vergroot de bestaande maandbereiken niet.
2. De maandblokken lezen spelersrijen 9–32 voor AWC 1 en 9–37 voor JO23-1. Nieuwe spelers binnen
   die ruimte worden meegenomen; toevoegen daarbuiten vereist aanpassing van alle vervolgblokken.
3. Vier ingevulde uitslagen zijn als datum opgeslagen: `AWC 1!F7/I7` en `JO23-1!G7/H7`.
   Hulpformules reconstrueren momenteel dag-maand als uitslag. Gescheiden numerieke scorevelden
   maken deze herstelregel overbodig.
4. De controle op ontbrekende IBAN's in `Overzicht JO23-1!J8` kijkt naar de prefix `NL`.
   Dat is geen volledige IBAN-validatie en behandelt buitenlandse IBAN's niet correct.
5. De betaalformule selecteert op naam en positief bedrag; de ontbrekende rekeningregels komen
   daardoor in het exporttabblad voor. Volgens de uitleg waarschuwt het Apps Script hierover.
   Of dat script export daadwerkelijk blokkeert, is nog niet onderzocht.
6. IBAN-koppeling gebeurt op naam. De Nmbrs-naamformule beschouwt het laatste woord als achternaam.
   Rondo moet stabiele persoons-ID's en bevestigde naamgegevens gebruiken.

Geen formulefouten gevonden in de opgeslagen uitkomsten. Google-specifieke formules worden in
de XLSX-controlekopie deels als compatibiliteitsformules opgeslagen. De sheet is niet opnieuw
doorgerekend na proefwijzigingen; de Apps Script-export en bankimport zijn nog niet getest.

## 3. Bestaande onderdelen hergebruiken

| Onderdeel | Huidige code | Gebruik in dit voorstel |
|---|---|---|
| Wedstrijden per team | `includes/class-team-matches.php` | Programma, uitslagen, bron-ID en bronstatus ophalen |
| Wedstrijden-API | `includes/class-rest-team-matches.php` | Bestaande read-only feed intact laten |
| Sportlink-normalisatie | `includes/class-narrowcasting-sportlink.php` | Bronwedstrijdcode en thuis/uitgegevens gebruiken |
| Teaminterface | `src/pages/Teams/TeamDetail.jsx`, `TeamMatches.jsx` | Registratie openen vanuit een wedstrijd |
| Team- en persoonstoegang | `includes/class-access-control.php` | Bestaande zichtbaarheid als bovengrens |
| Financiële rechten | `includes/class-user-roles.php` | `can_view_finances()` en `can_manage_finances()` |
| Domeinvelden | `includes/config/field-registry.php`, `includes/class-fields.php` | Canonieke velden en native repeaters |

De bestaande wedstrijdfeed is een cache voor het huidige seizoen. De cache wordt ongeldig bij
een seizoen- of identiteitswijziging. Registraties en financiële historie moeten daarom zelfstandig
bewaard blijven. De publieke ICS-feed mag nooit selecties, afwezigheidsredenen of bedragen bevatten.

## 4. Gebruikersstromen

### Wedstrijd registreren

1. De registrator kiest een wedstrijd van een voor deze module ingeschakeld team.
2. Rondo vult bronwedstrijd, datum, tegenstander en thuis/uit vooraf in en stelt spelers voor.
   Een voorstel op basis van de huidige teamsamenstelling is geen bewijs van historische deelname.
3. Per speler kiest de registrator een status. Gastspelers worden uit toegankelijke personen
   geselecteerd. Een ontbrekende of verborgen persoon wordt door een bevoegde beheerder gekoppeld.
4. De registrator controleert twee gehele, niet-negatieve scorevelden en de speeldatum.
5. Opslaan maakt een concept. Afronden valideert de invoer en maakt de registratie beschikbaar
   voor maandverwerking. Rondo meldt ontbrekende antwoorden en een afwijkend aantal basisspelers.

Geen vooraf ingevulde Basis/Bank en geen omzetting van onbekend naar afwezig. Een ongebruikelijke
opstelling kan na een vastgelegde toelichting worden afgerond; een ontbrekende uitslag of
onbekende status voor een opgenomen speler blokkeert afronden. Een concept mag onvolledig zijn.
De interface moet op een telefoon bruikbaar zijn zonder een horizontale matrix van 30 wedstrijden.

### Maand verwerken

1. De financiële beheerder kiest team, seizoen en maand en ziet afgeronde registraties,
   openstaande wedstrijden en eventuele bronconflicten.
2. Het overzicht toont aantallen per speler met doorklik naar de onderliggende wedstrijden.
3. De beheerder lost ontbrekende gegevens op en controleert totalen en exportinstellingen.
4. Afsluiten legt een onveranderlijke batch met bronversies, regels, bedragen en naamgegevens vast.
5. Download levert de export van die batch. Een herhaalde download gebruikt dezelfde batch.
6. De beheerder registreert afzonderlijk dat de gegevens in Nmbrs zijn verwerkt of dat een
   JO23-1-betaling buiten Rondo is uitgevoerd, met datum en referentie.

Voorgesteld: alleen volledige maanden afsluiten, zonder stilzwijgend spelers of wedstrijden uit
te sluiten. Voor JO23-1 blokkeert een ontbrekend/ongeldig IBAN bij een positief bedrag de afsluiting
en betaalexport. Een speler met €0 heeft voor die maand geen rekening nodig.

## 5. Rekenregels

| Regel | Voorgestelde verwerking |
|---|---|
| Meetellende wedstrijden, bevestigd | Alleen competitie en nacompetitie voor beide teams; beker en oefenwedstrijden uitgesloten |
| Periode | Bevestigde speeldatum in Europe/Amsterdam, maand inclusief jaar |
| Seizoen | 1 juli tot 1 juli van het volgende jaar, gelijk aan de bestaande feed |
| Basis of Bank | Eén deelname per persoon per wedstrijd; de statussen zijn exclusief |
| Nmbrs Dagen, bevestigd | Aantal meetellende wedstrijden met Basis/Bank; twee wedstrijden op dezelfde kalenderdag tellen als twee |
| Overige statussen | Geen deelname, vergoeding of premie |
| Ontbrekende invoer | Onvolledig, nooit automatisch nul |
| Winst/gelijk | Berekenen uit thuisdoelpunten, uitdoelpunten en de kant van het geregistreerde team |
| AWC 1, bevestigd | Vergoeding voor Basis/Bank plus premie bij winst of gelijkspel; Rondo levert de aantallen voor Nmbrs en berekent geen loonbedragen |
| JO23-1, bevestigd | Alleen premie bij winst of gelijkspel, geen basis-/bankvergoeding; `(winst × punten_bij_winst + gelijk × punten_bij_gelijk) × premie_per_punt` |
| Geld | Gehele eurocenten, standaard 1500 cent per punt; geen floating-pointbedragen |
| Tariefwijziging | Nieuwe regelversie met ingangsdatum; afgesloten batches blijven ongewijzigd |
| Afgelast/uitgesteld | Geen financiële verwerking zolang niet daadwerkelijk gespeeld en bevestigd |
| Gestopt of reglementaire uitslag | Geen automatische verwerking; eerst expliciet beoordelen |

De Nmbrs-kolom `Dagen` telt bevestigde deelnames aan meetellende wedstrijden, overeenkomstig de
sheet. Twee wedstrijden op dezelfde kalenderdag tellen als twee; er vindt geen samenvoeging op
datum plaats. Deelname aan zowel AWC 1 als JO23-1 wordt wel gemeld zolang de afspraken daarover
niet zijn bevestigd; de software kiest niet zelfstandig welk recht vervalt.

De configuratie voor beide pilotteams bevat uitsluitend competitie en nacompetitie. Het
Sportlink-wedstrijdtype moet eenduidig naar die categorieën worden vertaald; onbekende types
blokkeren financiële verwerking totdat ze zijn beoordeeld. Beker- en oefenwedstrijden mogen in
het bestaande programma zichtbaar blijven, maar tellen nooit mee in de aantallen of betaalexport.
Een onduidelijke uitslag, bijvoorbeeld na strafschoppen in de nacompetitie, wordt niet automatisch
als winst verwerkt en vereist beoordeling van het toepasselijke premiebeleid.

## 6. Voorgesteld gegevensmodel

Gebruik uitsluitend WordPress-posts, postmeta, opties en usermeta. Nieuwe domeinvelden krijgen
een registry-context en worden gelezen/geschreven via `Rondo\Fields\Fields`. Repeaters gebruiken
de bestaande genummerde native opslag. Geen custom tabellen en geen directe SQL.

Alle onderstaande posttypes zijn privé: `public=false`, `show_in_rest=false`, geen algemene
zoekresultaten of publieke permalinks. Alleen de domeincontroller levert toegestane velden.

| Entiteit | Opslag | Belangrijkste velden |
|---|---|---|
| Wedstrijdregistratie | `rondo_match_reg` | `team_id`, `season`, `source_match_id`, `source_type`, `played_on`, `opponent_name`, `home_away`, `competition_type`, scores, status, versie, bronmoment |
| Selectie | Repeater op registratie | `person_id`, vastgelegde weergavenaam, deelnamestatus, gastspeler, toelichting indien noodzakelijk |
| Financieel profiel | `rondo_payee` per persoon | `person_id`, IBAN, tenaamstelling, bevestigde Nmbrs-naam en eventueel werknemersnummer |
| Maandbatch | `rondo_match_batch` | Team, seizoen, maand, soort, status, bronversies, regelversie, totalen, afsluiter en tijdstip |
| Batchregels | Repeater op batch | Persoons-ID, naam, aantallen, premie in centen, rekeningmomentopname voor JO23-1, verwijzingen naar bronregistraties |
| Regelversies | Optie per module met onveranderlijke versies | Team/seizoen, ingangsdatum, toegestane wedstrijdtypes, punten, tarief, Nmbrs-kolommapping |
| Toewijzing registrator | Usermeta | Expliciet toegestane team-ID's, beheerd door administrator |
| Gebeurtenislog | Privé WordPress-commenttype op registratie/batch | Actor, actie, tijdstip, vorige/nieuwe versie, reden, operatie-ID |

De log is alleen toegankelijk via de module. Geen volledige IBAN's in logteksten; een rekeningwijziging
krijgt een gebeurtenis met gemaskeerde aanduiding. Financiële profielen blijven buiten generieke
persoonresponses, exports, abilities en Sportlink-reverse-sync. Bestaande former-member-beveiliging
op personen blijft intact; historisch registreren is geen wijziging van het persoonsprofiel.

Registraties gebruiken de echte Sportlink-wedstrijdcode, niet een hash van datum en tegenstander.
Een handmatige wedstrijd krijgt een eigen UUID en verplichte reden; een latere bronkoppeling is
expliciet en gecontroleerd op duplicaten. De business key is team + seizoen + bronidentiteit.
Naamwijzigingen, teamwissels en een nieuwe feed mogen historische registraties niet herschrijven.

## 7. Statussen en correcties

Registratie: `draft → completed → locked`. De opname in een afgesloten batch vergrendelt de
bijbehorende registratieversie. Voor afsluiten mag een afgeronde registratie met reden terug
naar concept. Daarna ontstaat een nieuwe correctieversie, met behoud van de afgesloten versie.

Een maandbatch krijgt een fase `draft`, `closed` of `superseded`, plus afzonderlijke export- en
verwerkingsgebeurtenissen. De UI onderscheidt **Afgesloten**, **Geëxporteerd**, **Verwerkt in Nmbrs**
en **Betaald buiten Rondo**. Een download of technisch geslaagde bestandsaanmaak bewijst geen betaling.

Een correctie maakt het verschil met de laatste geldige afgesloten berekening zichtbaar.
De oorspronkelijke batch blijft onveranderd. Een vervangende batch vóór externe verwerking
vereist expliciete bevestiging dat het oude bestand niet meer zal worden gebruikt. Na verwerking
krijgt de correctie een eigen referentie en wordt alleen het nog niet verwerkte verschil aangeboden.
Een negatieve JO23-1-correctie blokkeert automatische betaalexport en vraagt handmatige afhandeling;
er ontstaat geen negatieve bankbetaling of automatische verrekening zonder vastgesteld beleid.

Bronwijzigingen na bevestiging leveren een conflict op. Een gewijzigde uitslag of een verplaatste
speeldatum wordt nooit stilzwijgend toegepast op een gesloten maand. Een datumcorrectie over een
maandgrens vraagt beoordeling van beide maanden. Tijdelijk ontbrekende Sportlink-data verwijdert
geen registratie en betekent niet dat er geen wedstrijd heeft plaatsgevonden.

## 8. Rechten en afscherming

Voorgestelde nieuwe capability: `wedstrijdregistratie`. Deze geeft alleen registratierechten
binnen expliciet toegewezen teams én bestaande teamtoegang. Bestaande `teams`- of `wedstrijdzaken`-
rechten maken iemand niet automatisch registrator. Nieuwe rechten worden niet stilzwijgend aan
alle trainers, bestuurders of leden toegekend.

| Handeling | Registrator voor team | Financieel lezen | Financieel beheren | Administrator |
|---|---|---|---|---|
| Selectie en noodzakelijke afwezigheidsstatus lezen | Toegewezen team | Geen afwezigheidsredenen | Geen afwezigheidsredenen | Ja |
| Concept invoeren en afronden | Toegewezen team | Nee | Alleen met registratorrecht | Ja |
| Financiële maandtotalen lezen | Nee | Ja | Ja | Ja |
| IBAN volledig lezen of wijzigen | Nee | Alleen gemaskeerd | Ja | Ja |
| Tarieven beheren, afsluiten en exporteren | Nee | Nee | Ja | Ja |
| Externe verwerking/betaling vastleggen | Nee | Nee | Ja | Ja |
| Registratoren en teams inschakelen | Nee | Nee | Nee | Ja |

Gebruik de bestaande financiële helpers met een expliciete administratorroute voor deze module;
de helpers zelf geven niet automatisch een `manage_options`-bypass. Alle toegang vereist een
ingelogde, goedgekeurde gebruiker. Financiële toegang tot minimale batchregels verleent geen
clubbrede toegang tot volledige persoonsdossiers.

Pas dezelfde controles toe op routes, REST, zoekresultaten, aantallen, downloads en caches.
Normale teambezoekers zien het openbare wedstrijdprogramma, geen registratiedetails.
Geblesseerd blijft hooguit de afgesproken keuzestatus, zonder medische vrije tekst. Gebruik geen
algemene WordPress-media-URL voor betaalbestanden. Responses en downloads zijn privé en `no-store`.

## 9. Services en API

Voorgestelde services: `MatchRegistrationService`, `MatchCompensationCalculator`,
`MatchSettlementService`, `MatchCompensationExport` en één gedeelde `MatchRegistrationPolicy`.
De calculator is een zuivere functie van bevestigde registraties en regelversies; scherm,
afsluiting en export gebruiken exact dezelfde berekening. De browser berekent geen gezaghebbende bedragen.

Nieuwe controller onder `/rondo/v1/match-compensation`. Bestaande teammatches- en ICS-contracten
blijven behouden. Exacte bestandsnamen worden bij implementatie op de repositoryconventies afgestemd.

| Methode en route binnen deze namespace | Doel |
|---|---|
| `GET /teams/{id}/registrations?season=...` | Registraties, ontbrekende invoer en bronconflicten |
| `POST /registrations` | Concept voor bronwedstrijd of gemotiveerde handmatige wedstrijd |
| `GET /registrations/{id}` | Toegestane registratiedetails |
| `PATCH /registrations/{id}` | Partiële `fields`-update met verwachte versie |
| `POST /registrations/{id}/complete` | Valideren en afronden |
| `POST /registrations/{id}/reopen` | Terug naar concept vóór afsluiting, met reden |
| `POST /registrations/{id}/corrections` | Nieuwe correctieversie na afsluiting |
| `GET /months?team_id=...&season=...&month=YYYY-MM` | Voorvertoning, totalen en blokkerende controles |
| `POST /batches` | Gecontroleerd afsluiten met verwachte bronversies |
| `GET /batches/{id}` | Afgesloten momentopname en gebeurtenissen |
| `POST /batches/{id}/exports` | Export vastleggen met uitvoerdatum en bestandscontractversie |
| `GET /batches/{id}/exports/{export_id}` | Geautoriseerde herhaalde download |
| `POST /batches/{id}/processing` | Externe verwerking of betaling vastleggen met referentie |
| `GET/PATCH /payees/{person_id}` | Financieel profiel volgens veldrechten |
| `GET/POST /rules` | Regelversies lezen of toevoegen |

Mutaties gebruiken sessie-authenticatie, nonce, domeinrechten en veldvalidatie. Datumvelden zijn
`YYYY-MM-DD`, tijdstippen RFC 3339 met timezone. Ongeldige invoer geeft veldspecifieke HTTP 400;
een verouderde versie of conflicterende operatie geeft HTTP 409.

### Gelijktijdige updates en herhalen

Een UI-knop uitschakelen is onvoldoende. Create, afsluiten, corrigeren en exporteren krijgen
een duurzame idempotentiesleutel met payloadhash en resultaat. Dezelfde sleutel met dezelfde
inhoud retourneert hetzelfde resultaat; dezelfde sleutel met andere inhoud wordt geweigerd.

Serialiseer wijzigingen op registratie en afsluiting op team/periode via een gedeeld slot met
eigenaar en gecontroleerde vrijgave. Leg operatievoortgang duurzaam vast met WordPress-API's,
bijvoorbeeld een uniek `add_option`-claim. Een verlopen slot alleen maakt een operatie niet veilig
om opnieuw uit te voeren: herstel controleert eerst reeds geschreven records en batchreferenties.
Controleer alle registratieversies opnieuw onder het slot vóór publicatie van de batch.

Postmeta en meerdere veldwrites vormen niet vanzelf één databasetransactie. Onvolledige writes
blijven buiten leesbare afgesloten resultaten tot de operatie compleet is. Fase 1 moet met tests
bewijzen dat dubbele requests, parallelle saves en uitval na schrijven herstelbaar zijn zonder
dubbele registraties of batches. Transients alleen zijn geen voldoende beschermingsmechanisme.

## 10. Exportcontracten

**AWC 1:** een scherm en download met dezelfde aantallen als het huidige Nmbrs-overzicht.
De kolommen heten Naam, Naam in Nmbrs, Dagen, L3090 winst, L3091 gelijkspel, U2150 basis en
Bankvergoeding. Dagen telt wedstrijden, ook als die op dezelfde kalenderdag vallen. De looncode
voor Bankvergoeding blijft voorlopig leeg en is instelbaar door een financiële beheerder.
Zolang de code ontbreekt, blijft de kolom zichtbaar als `Bankvergoeding (code nog niet ingesteld)`;
registratie en berekening kunnen doorgaan en er wordt geen looncode afgeleid of verzonnen.
Vóór overname in Nmbrs moet de beheerder de juiste code controleren. De export wordt pas als
Nmbrs-importbestand gepresenteerd als een echt importcontract is bevestigd;
de eerste versie ondersteunt aantoonbaar handmatige snelinvoer.

**JO23-1:** XLSX met uitsluitend de vijf bronkolommen in dezelfde volgorde. IBAN en namen zijn
tekst, bedrag is numeriek met twee decimalen, uitvoerdatum is een echte datum. Geen formules,
instellingscellen, extra tabbladen of totalen in de betaalregels. Namen die op `=`, `+`, `-` of
`@` beginnen blijven tekst. Bestandsnaam volgt na bevestiging het bestaande exportpatroon.

Normaliseer IBAN door spaties te verwijderen en hoofdletters te gebruiken. Controleer landlengte,
structuur en mod-97 voor ondersteunde landen; accepteer een geldige buitenlandse rekening als
het bevestigde bankcontract die ondersteunt. Tenaamstelling is een bevestigd profielveld en wordt
niet zonder controle uit de spelersnaam afgeleid. Valideer alleen betaalregels met positief bedrag.

De eerste uitvoerdatum wordt bij export bevestigd, standaard de huidige clubdatum, en vastgelegd.
Herdownload verandert die datum niet automatisch. Een gewijzigde datum vereist een expliciete
nieuwe exportversie en waarschuwing over het eerdere bestand. Bedragen en ontvangers blijven
gebonden aan de afgesloten batch. Rondo kan herhaalde betaling buiten het systeem niet uitsluiten.

Voor de exportlibrary is nog geen keuze gemaakt. `composer.json` bevat geen directe
XLSX-writerdependency. Fase 0 selecteert een onderhouden library die de serverversie ondersteunt
en toetst die aan een geanonimiseerd daadwerkelijk exportvoorbeeld. Geen eigen XLSX-formaatbouw.
Bestanden worden geautoriseerd gestreamd of buiten de publieke webroot bewaard, met vastgelegde
hash, bestandscontractversie en beperkte bewaartermijn voor tijdelijke bestanden.

## 11. Migratie en pilot

1. Leg een gedateerde bronkopie en een hash vast in afgeschermde opslag buiten Git.
2. Maak een read-only importvoorvertoning: team, seizoen, wedstrijd, broncel, spelerskoppeling,
   statussen, afwijkingen en verwachte maandtotalen.
3. Koppel personen aan stabiele Rondo-ID's. Een naamvergelijking mag kandidaten opleveren;
   ontbrekende of dubbele matches worden expliciet beoordeeld. Maak niet automatisch personen aan.
4. Koppel wedstrijden op bevestigde bron-ID. Datum/tegenstander is alleen een hulpmiddel;
   verschillen, datumgeworden uitslagen en dubbele kandidaten vereisen controle.
5. Importeer na akkoord op het rapport en na backup. Gebruik vaste migratiesleutels zodat een
   herhaalde import dezelfde records herkent en handmatige Rondo-wijzigingen niet overschrijft.
6. Importeer bestaande maanden als te controleren historie. Markeer ze pas extern verwerkt of
   betaald na bevestiging van de penningmeester, zodat oude premies niet opnieuw worden aangeboden.
7. Vergelijk augustus en september per speler en per wedstrijd; september gebruikt de controles
   uit hoofdstuk 2. Laat vervolgens één volledige maand parallel lopen.
8. Maak na akkoord Rondo de invoerbron. Bewaar de oorspronkelijke sheet als archief.

Rollback van de import gebeurt op de gemarkeerde importrecords en vooraf vastgelegde wijzigingen.
Geen verwijdering van bestaande personen of teams. Een productiecode-rollback bewaart financiële
historie; bij een fout kan de module worden uitgeschakeld zonder data te wissen.

## 12. Vastgelegde en open besluiten

### Vastgelegd op 4 oktober 2026

**B1, bevestigd door Joost:** voor AWC 1 en JO23-1 tellen alleen competitie en nacompetitie mee.
Beker- en oefenwedstrijden zijn uitgesloten. AWC 1 krijgt basis-/bankvergoeding plus premie bij
winst of gelijkspel; JO23-1 krijgt alleen premie bij winst of gelijkspel.

**B2a, bevestigd door Joost:** `Dagen` in Nmbrs is het aantal meetellende wedstrijden met Basis/Bank,
niet het aantal unieke kalenderdagen. Twee wedstrijden op dezelfde dag tellen als twee.

**B2b, bevestigd door Joost:** de Nmbrs-code voor Bankvergoeding is nog onbekend. Laat deze
voorlopig leeg en instelbaar door een financiële beheerder; de verdere uitwerking gaat door.
De telling blijft zichtbaar zonder een code te verzinnen.

### Nog te besluiten vóór de betreffende bouwfase

De overige voorgestelde defaults worden vóór de afhankelijke fase bevestigd. Vastlegging van
deze afspraken is nog geen opdracht tot implementatie of productie-import.

| ID | Open besluit | Voorgesteld uitgangspunt | Nodig vóór |
|---|---|---|---|
| B3 | Gastspelers en deelname bij beide teams, leeftijd/contractuitzonderingen? | Per wedstrijd registreren, mogelijke dubbele aanspraken laten beoordelen | Calculator en afsluiting |
| B4 | Welk bankpakket/formaat en wat doet het Apps Script exact? | Huidige vijfkoloms-XLSX behouden na bewezen compatibiliteit | Export |
| B5 | Wie registreert, corrigeert en sluit af; is een tweede fiatteur vereist? | Registrator per team en financiële beheerder zoals rechtenmatrix | Rechten en vrijgave |
| B6 | Welke historische maanden zijn al verwerkt of betaald? | Geen automatische betaalstatus uit spreadsheetdata afleiden | Productie-import |
| B7 | Tariefingangsdata, negatieve correcties en tenaamstelling | Gesloten bedragen bewaren; negatieve correcties handmatig afhandelen | Correcties en export |
| B8 | Bewaartermijn voor afwezigheidsredenen, profielen, batches en bestanden | Aansluiten op vastgesteld clubbeleid; geen termijn verzinnen | Productievrijgave |

## 13. Uitvoeringsfasen

| Fase | Resultaat | Voorwaarde om door te gaan |
|---|---|---|
| 0 | B1 vertalen naar broncategorieën, besluiten B3–B8, inspectie Apps Script, geanonimiseerd exportvoorbeeld, librarykeuze | Reken- en bestandscontract bevestigd; broncontrolegrenzen vastgelegd |
| 1 | Privé opslag, registry, rechtenbeleid, versiebeheer, calculator en API | Rekentests, rechtenmatrix en gelijktijdigheids-/hersteltests slagen |
| 2 | Teamregistratie, gastspelers, afronden en bronconflicten | Volledige flow met synthetische data op desktop en mobiel gecontroleerd |
| 3 | Financiële profielen, maandafsluiting, correcties en exports | Rekenverschillen nul, bestandscontract bewezen, export/privacytests slagen |
| 4 | Importvoorvertoning, goedgekeurde migratie en parallelle maand | Alle matches beoordeeld, totalen sluiten aan, externe verwerking bevestigd |
| 5 | Rondo als invoerbron en sheet archiveren | Registrator en penningmeester accepteren de pilot |

Elke codefase krijgt relevante JS/PHP-tests, lint, build, versie en changelog, commit en push.
Productievrijgave volgt de bestaande CI/deployroute en wordt daarna ingelogd gecontroleerd.
Nieuwe functionaliteit blijft tot de afgesproken pilottoewijzing uitgeschakeld. Vanuit een
worktree wordt niet naar productie gedeployed. De dagautomatisering onderhoudt de ontwikkelaarsdocs.
Deze planning zelf blijft beperkt tot `docs/prd/`, zonder themaversie, changelog of deployment.

## 14. Acceptatiecriteria

- [ ] Een registrator kan alleen toegewezen én reeds toegankelijke teams registreren; een gewone
  teambezoeker ziet geen selecties. Directe URL's en API-requests respecteren dezelfde grenzen.
- [ ] Financieel lezen laat geen write, export of volledige IBAN toe. Een registrator krijgt geen
  financiële gegevens. Publieke ICS, zoeken, abilities en generieke persoonexports lekken niets.
- [ ] Elke persoon komt hooguit eenmaal per wedstrijd voor. Onbekend en nul blijven onderscheiden.
  Gastspelers hoeven niet tot het huidige team te behoren, maar moeten toegankelijk en gekoppeld zijn.
- [ ] Thuis/uit, 0-0, verlies, ontbrekende uitslag, afwijkende notatie, afgelasting, verplaatsing,
  onduidelijke nacompetitie-uitslagen en december/januari worden volgens de vastgelegde regels verwerkt.
- [ ] Alleen competitie en nacompetitie tellen mee. Beker- en oefenwedstrijden leveren voor beide
  teams geen vergoeding, premie of Nmbrs-aantal op, ook niet als er Basis/Bank is geregistreerd.
- [ ] AWC 1 levert basis-/bankaantallen bij iedere meetellende deelname en daarnaast premies bij
  winst/gelijkspel. JO23-1 levert uitsluitend resultaatpremies, zonder basis-/bankvergoeding.
- [ ] Twee verschillende meetellende AWC 1-wedstrijden op dezelfde kalenderdag met Basis/Bank
  voor dezelfde speler leveren `Dagen = 2`; dezelfde wedstrijd dubbel aanbieden blijft één deelname.
- [ ] De bankvergoedingcode kan leeg blijven zonder de telling te verliezen. Alleen een financiële
  beheerder of administrator kan de code instellen; een ontbrekende code is zichtbaar in het overzicht
  en de download. Rondo vult nooit zelfstandig een looncode in.
- [ ] Meer dan 30 wedstrijden en extra spelers blijven correct meetellen. Historie blijft bestaan
  na seizoenwissel, teamwissel, naamswijziging en tijdelijk wegvallen van de bronfeed.
- [ ] September 2026 reproduceert per speler de gecontroleerde sheet, met AWC 1 44 basis/24 bank
  en JO23-1 €1.140. Geconstateerde inhoudelijke broncorrecties worden apart verklaard.
- [ ] Bij de zeven ontbrekende rekeningen uit de controle ontstaat een zichtbare blokkade van
  €255; niets wordt stilzwijgend overgeslagen of als betaald gemarkeerd.
- [ ] Geldige buitenlandse IBAN's, ongeldige checksum, nulbedragen, samengestelde namen en
  tenaamstellingen worden volgens het bevestigde exportcontract getest met fictieve gegevens.
- [ ] Tariefwijzigingen veranderen geen afgesloten maand. Broncorrecties tonen het verschil,
  behouden eerdere versies en veroorzaken geen dubbele uitbetaling of negatieve betaalregel.
- [ ] Twee gelijktijdige afsluitingen, dubbele aanmaak, een afgebroken request ná persist en een
  herhaalde download leveren geen dubbele registratie/batch en geen gewijzigde uitvoerdatum op.
- [ ] Een XLSX opent met de juiste celtypen en uitsluitend de afgesproken kolommen. Een
  geanonimiseerde proefimport bij de beoogde ontvanger slaagt zonder een betaling te initiëren.
- [ ] Herhaalde import is zonder extra wijzigingen; onbekende personen, wedstrijden en bestaande
  betaalstatussen zijn vóór migratie opgelost of expliciet als onopgelost geblokkeerd.
- [ ] De registrator en penningmeester voltooien de afgesproken flow op productie na succesvolle
  CI/deploy en accepteren één parallel verwerkte maand voordat de sheet wordt gearchiveerd.

Tests gebruiken synthetische personen en bankgegevens. PHP-tests booten de benodigde REST-controllers
expliciet en draaien tegen MySQL volgens `docs/testing.md`. De volledige bestaande suite moet groen
blijven. Een geslaagde build geldt niet als bewijs van juiste betaling of bankcompatibiliteit.
