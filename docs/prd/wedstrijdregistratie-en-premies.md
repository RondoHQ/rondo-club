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
correcties, een Nmbrs-snelinvoeroverzicht, JO23-1-betaalexport, bankgegevens op personen die leden
zelf kunnen wijzigen via Mijn gegevens, en een eenmalige import.

Buiten de eerste versie vallen een rechtstreekse Nmbrs-koppeling, bankbetalingen uitvoeren,
loonberekening, fiscale kwalificatie van vergoedingen, speelminuten, wisselmomenten,
trainingsregistratie en wijzigingen aan Rondo Sync. De module gebruikt geen Mollie-betaallinks:
het gaat om uitgaande vergoedingen en een export voor verdere verwerking. De JO23-1-betaalexport
wordt Rabobank SEPA-XML, aansluitend op de bestaande export voor creditfacturen. De XLSX-export
uit de sheet blijft uitsluitend referentiemateriaal voor de oude werkwijze.

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
   daardoor in het exporttabblad voor. Het Apps Script waarschuwt alleen bij de exacte tekst
   `Niet gevonden` en laat de gebruiker dan toch exporteren. Zie de exportcontrole in hoofdstuk 10.
6. IBAN-koppeling gebeurt op naam. De Nmbrs-naamformule beschouwt het laatste woord als achternaam.
   Rondo moet stabiele persoons-ID's en bevestigde naamgegevens gebruiken.

Geen formulefouten gevonden in de opgeslagen uitkomsten. Google-specifieke formules worden in
de XLSX-controlekopie deels als compatibiliteitsformules opgeslagen. De sheet is niet opnieuw
doorgerekend na proefwijzigingen. Het Apps Script is inmiddels gelezen en lokaal met fictieve
gegevens en vervangende Google-services getest; echte XLSX-generatie en bankimport zijn niet getest.

Aanvullende controle op individuele uitzonderingen op 4 oktober 2026: de formules in
`Nmbrs AWC 1!C51:G74` en `Overzicht JO23-1!C56:E84` volgen voor iedere spelersrij hetzelfde patroon,
zonder persoonlijke tarief- of uitzonderingslogica. `Export JO23-1!A2` gebruikt voor alle spelers
dezelfde instellingen uit `H1:H3`. Geen afwijkende afspraken gevonden in de uitleg, celnotities
of het reactiespaneel. De sheet bevat alleen aantallen voor AWC 1; individuele loonbedragen of
afspraken in Nmbrs zijn hiermee niet gecontroleerd.

## 3. Bestaande onderdelen hergebruiken

| Onderdeel | Huidige code | Gebruik in dit voorstel |
|---|---|---|
| Wedstrijden per team | `includes/class-team-matches.php` | Programma, uitslagen, bron-ID en bronstatus ophalen |
| Wedstrijden-API | `includes/class-rest-team-matches.php` | Bestaande read-only feed intact laten |
| Sportlink-normalisatie | `includes/class-narrowcasting-sportlink.php` | Bronwedstrijdcode en thuis/uitgegevens gebruiken |
| Teaminterface | `src/pages/Teams/TeamDetail.jsx`, `TeamMatches.jsx` | Registratie openen vanuit een wedstrijd |
| Team- en persoonstoegang | `includes/class-access-control.php` | Bestaande zichtbaarheid als bovengrens |
| Financiële rechten | `includes/class-user-roles.php` | `can_view_finances()` en `can_manage_finances()` |
| Rabobank-betaalexport | `includes/class-credit-sepa-export.php`, `src/components/finance/CreditSepaExport.jsx` | Validatie, XML-opbouw, bevestiging en identieke herdownload als basis voor premiebetalingen |
| SEPA-contracttests | `tests/Wpunit/CreditSepaExportTest.php`, `tests/fixtures/sepa/pain.001.001.09.xsd` | Bestaande dekking behouden en uitbreiden voor meerdere transacties |
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

De financiële beheerder mag zelfstandig afsluiten en exporteren; een tweede fiatteur in Rondo
is niet vereist. De bestaande controles en bevestiging vóór export blijven gelden.

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
| Gastspelers, bevestigd | Dezelfde vergoeding en premie als vaste spelers, volgens de regeling van het team waarmee zij die wedstrijd meedoen; het eigen team bepaalt de regeling niet |
| Deelname aan beide teams, bevestigd | Twee afzonderlijke wedstrijden tellen beide mee volgens hun eigen teamregeling, ook op dezelfde dag; geen voorrangsregel of vermindering |
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
datum plaats. Bij deelname aan zowel AWC 1 als JO23-1 tellen beide afzonderlijke wedstrijden mee
volgens hun eigen teamregeling. Volgens Joost komt dit in de praktijk niet voor. Er is daarom
geen aparte waarschuwing, blokkade of voorrangsregel voor deze samenloop nodig.

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

Alle onderstaande nieuwe posttypes zijn privé: `public=false`, `show_in_rest=false`, geen algemene
zoekresultaten of publieke permalinks. Alleen de domeincontroller levert toegestane velden.

| Entiteit | Opslag | Belangrijkste velden |
|---|---|---|
| Wedstrijdregistratie | `rondo_match_reg` | `team_id`, `season`, `source_match_id`, `source_type`, `played_on`, `opponent_name`, `home_away`, `competition_type`, scores, status, versie, bronmoment |
| Selectie | Repeater op registratie | `person_id`, vastgelegde weergavenaam, deelnamestatus, gastspeler, toelichting indien noodzakelijk |
| Bankgegevens | Native velden op bestaande `person` | `iban` en voorgesteld `bank_account_holder` voor de tenaamstelling |
| Nmbrs-identiteit | Afgeschermde native velden op bestaande `person` | Bevestigde Nmbrs-naam en eventueel werknemersnummer; uitsluitend financieel beheer |
| Maandbatch | `rondo_match_batch` | Team, seizoen, maand, soort, status, bronversies, regelversie, totalen, afsluiter en tijdstip |
| Batchregels | Repeater op batch | Persoons-ID, naam, aantallen, premie in centen, rekeningmomentopname voor JO23-1, verwijzingen naar bronregistraties |
| SEPA-export | Privé postmeta op maandbatch | Onveranderlijk XML, export-ID, payloadhash, uitvoerdatum, afschrijfrekening en tenaamstelling, bericht-/betaal-/transactiereferenties, aantal transacties en controlesom |
| Regelversies | Optie per module met onveranderlijke versies | Team/seizoen, ingangsdatum, toegestane wedstrijdtypes, punten, tarief, Nmbrs-kolommapping |
| Toewijzing registrator | Usermeta | Expliciet toegestane team-ID's, beheerd door administrator |
| Gebeurtenislog | Privé WordPress-commenttype op registratie/batch | Actor, actie, tijdstip, vorige/nieuwe versie, reden, operatie-ID |

De registratielog is alleen toegankelijk via de module. Geen volledige IBAN's in logteksten; een
rekeningwijziging krijgt een gebeurtenis met actor, tijdstip en gemaskeerde aanduiding. Bankgegevens
en Nmbrs-identiteit krijgen afzonderlijke veldrechten op personen; bestaande toegang tot een
persoon geeft niet automatisch toegang tot deze velden. Generieke persoonresponses, exports en
abilities passen dezelfde afscherming toe. Deze nieuwe velden blijven lokaal in Rondo en gaan niet
naar Sportlink-reverse-sync. Bestaande former-member-beveiliging op personen blijft intact;
historisch registreren is geen wijziging van het persoonsprofiel.

### Bankgegevens op personen en Mijn gegevens

Er komt geen apart `rondo_payee`-posttype. Het IBAN staat als nullable, canoniek veld `iban` in de
person-registry en wordt via `Fields` opgeslagen. Normaliseer spaties en hoofdletters en gebruik
de gedeelde SEPA-IBAN-validatie. Ongeldige invoer wordt afgewezen; `null` wist het veld. Een leeg
IBAN is toegestaan op een persoon, maar blokkeert een positieve betaling bij maandafsluiting.
De aparte tenaamstelling `bank_account_holder` is het voorstel voor besluit B7; leid een afwijkende
rekeninghouder niet af uit de persoonsnaam.

Een lid kan via **Mijn gegevens → Bankgegevens** het eigen IBAN bekijken en direct wijzigen.
De server bepaalt het persoonsrecord via `rondo_linked_person_id`; de client kan geen andere
persoon als doel opgeven. De bestaande profielservice ondersteunt ook minderjarige kinderen;
de nieuwe bankroute gebruikt uitsluitend de eigen koppeling. Toegang tot een huishouden geeft
geen bankrechten op kinderen of andere gezinsleden. Financieel beheer en administrators kunnen
bankgegevens op een toegankelijk persoonsrecord beheren. De normale blokkade voor oud-leden en
overleden personen blijft gelden. Zelfservice verleent geen toegang tot Nmbrs-velden.

De bankvelden krijgen één gedeeld, persoonsgebonden rechtenbeleid voor alle lees- en schrijfpaden.
De bestaande algemene financiële veldgroep volstaat niet: die geeft financiële lezers toegang
en kent geen uitzondering voor de eigen persoon. Beperk generieke zoek-, sorteer- en exportpaden
zodat verborgen bankgegevens ook niet indirect uitlekken. Het wijzigingslog maskeert oude en
nieuwe IBAN's en markeert bankwijzigingen expliciet als lokaal, zonder Sportlink-syncopdracht.

Een wijziging geldt voor volgende conceptberekeningen. Bij afsluiten controleert Rondo ook de
versie van de gebruikte bankgegevens; een wijziging sinds de voorvertoning vraagt nieuwe controle.
Afgesloten batches en aangemaakte XML-bestanden bewaren hun rekeningmomentopname. Een later
gewijzigd persoonsveld herschrijft die bestanden nooit; een noodzakelijke vervanging volgt het
bestaande correctieproces met bevestiging dat de oude export niet meer wordt gebruikt.

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

Bevestigd: wedstrijdselecties invoeren en corrigeren gebeurt door expliciet per team aangewezen
registratoren. Bestaande teambeheerrechten zijn daarvoor op zichzelf onvoldoende.

Voorgestelde nieuwe capability: `wedstrijdregistratie`. Deze geeft alleen registratierechten
binnen expliciet toegewezen teams én bestaande teamtoegang. Bestaande `teams`- of `wedstrijdzaken`-
rechten maken iemand niet automatisch registrator. Nieuwe rechten worden niet stilzwijgend aan
alle trainers, bestuurders of leden toegekend.

| Handeling | Registrator voor team | Financieel lezen | Financieel beheren | Administrator |
|---|---|---|---|---|
| Selectie en noodzakelijke afwezigheidsstatus lezen | Toegewezen team | Geen afwezigheidsredenen | Geen afwezigheidsredenen | Ja |
| Concept invoeren en afronden | Toegewezen team | Nee | Alleen met registratorrecht | Ja |
| Selectie corrigeren volgens het versieproces | Toegewezen team | Nee | Alleen met registratorrecht | Ja |
| Financiële maandtotalen lezen | Nee | Ja | Ja | Ja |
| IBAN van andere personen volledig lezen of wijzigen | Nee | Alleen gemaskeerd, niet wijzigen | Ja, binnen persoonstoegang | Ja |
| Tarieven beheren, afsluiten en exporteren | Nee | Nee | Ja | Ja |
| Externe verwerking/betaling vastleggen | Nee | Nee | Ja | Ja |
| Registratoren en teams inschakelen | Nee | Nee | Nee | Ja |

Los van deze beheerdersrollen kan ieder goedgekeurd lid het eigen IBAN lezen en wijzigen via
Mijn gegevens volgens de persoonsgebonden controles hierboven. Dit geeft geen financiële
modulerechten of toegang tot bankgegevens van teamgenoten.

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
| `GET/POST /rules` | Regelversies lezen of toevoegen |

Bankgegevens gebruiken de bestaande personen-API met partiële `fields`-updates en aanvullende
veldrechten. Voeg voor Mijn gegevens `GET/PATCH /rondo/v1/user/profile-bank-account` toe aan de
bestaande profielcontroller; deze route staat buiten de match-compensation-namespace. De route
accepteert uitsluitend toegestane bankvelden voor de servermatig gekoppelde persoon, nooit een
vrij `person_id` of Nmbrs-velden. Beide paden gebruiken dezelfde validatie en afscherming.

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

**JO23-1, bevestigd door Joost:** een Rabobank SEPA-betaalbestand in XML, zoals bij de bestaande
creditfacturen. Het huidige codepad gebruikt `pain.001.001.09`. De gebruiker downloadt het bestand,
importeert het in Rabobank en controleert en ondertekent daar de betalingen. Rondo verstuurt geen
betaalopdracht naar de bank en markeert een export niet automatisch als betaald.

De bestaande `CreditSepaExport` is expliciet gebouwd voor één betaling per creditfactuur.
Haal de herbruikbare IBAN-/betaalvalidatie en XML-opbouw onder in een gedeelde SEPA-service die
één of meerdere transacties ondersteunt. Laat zowel de creditfactuurexport als de premie-export
die service gebruiken. Factuurvoorwaarden, ontvangerafleiding uit de oorspronkelijke betaling
en factuurstatus blijven in de creditfactuurservice; maandregels en premiebeleid blijven in
de premiemodule. Maak geen fictieve creditfacturen om spelers uit te betalen.

| Onderdeel | Contract voor de premie-export |
|---|---|
| Bestand | Eén XML per afgesloten JO23-1-maandbatch, met unieke export-ID in de bestandsnaam |
| Betalingen | Eén `CdtTrfTxInf` per speler met positief premiebedrag, gekoppeld aan de vaste batchregel |
| Afschrijfrekening | Bevestigd Nederlands Rabobank-IBAN van de club en bijbehorende tenaamstelling, vastgelegd bij de export |
| Ontvanger | Bevestigde tenaamstelling en geldig SEPA-IBAN uit de financiële momentopname |
| Bedrag | EUR, positief, uit gehele centen; binnen de grenzen van het bestaande betaalcontract |
| Uitvoering | Eén bevestigde uitvoerdatum voor de maandbatch, volgens de bestaande datumvalidatie |
| Omschrijving | `Premie <maandnaam> <jaar>`, via correct ge-escapete XML-tekstnodes |
| Referenties | Vaste `MsgId` en `PmtInfId` per export en unieke vaste `EndToEndId` per betaling |
| Controles | `NbOfTxs` is het werkelijke aantal betalingen; `CtrlSum` is exact hun som op groeps- en betaalniveau |
| Herdownload | Hetzelfde opgeslagen XML, dezelfde bedragen, referenties en uitvoerdatum |

Gebruik dezelfde normalisatie, SEPA-landlengtes en mod-97-validatie als de creditfacturen.
Behoud de controle op een Nederlands Rabobank-IBAN als afschrijfrekening en op een verschillende
ontvangerrekening. Tenaamstellingen worden bevestigd; ze worden niet zonder controle uit de
spelersnaam afgeleid. Neem de bestaande grenzen voor tenaamstelling, omschrijving en uitvoerdatum
over uit de gedeelde validator. Toon vóór export clubrekening, uitvoerdatum, aantal ontvangers en
totaalbedrag en vraag dezelfde expliciete bevestiging van gecontroleerde gegevens en nog niet
klaargezette/uitgevoerde betalingen als bij creditfacturen.

De eerste uitvoerdatum wordt bij export bevestigd, standaard de huidige clubdatum, en vastgelegd.
Herdownload verandert die datum niet automatisch. Een gewijzigde datum vereist een expliciete
nieuwe exportversie en waarschuwing over het eerdere bestand. Bedragen en ontvangers blijven
gebonden aan de afgesloten batch. Rondo kan herhaalde betaling buiten het systeem niet uitsluiten.

Gebruik de bestaande XML-opbouw met `DOMDocument`; voor deze betaalexport is geen XLSX-library
nodig. Bewaar het XML vóór het teruggeven in private postmeta, overeenkomstig de creditfacturen.
De download bevat `xml`, `filename`, `export_id` en `created_at`, gebruikt de bestaande Blob-aanpak
en is alleen beschikbaar via de geautoriseerde module-API met `Cache-Control: private, no-store`.
Geen bestanden in publieke uploads of Google Drive. Neem de bestaande replay- en herdownload-
controles over, maar koppel opslag en vergrendeling aan de maandbatch en de vereisten in hoofdstuk 9.

De bestaande creditfactuurtest controleert het XML tegen de meegeleverde Rabobank-XSD en test
identieke herdownloads en verloren responses. Dat is code- en schemadekking, geen bewijs van een
geaccepteerde bankimport voor de nieuwe batchvariant. Test die variant expliciet met meerdere
ontvangers, unieke referenties en juiste controlesommen en laat een proefbestand in Rabobank
controleren zonder betalingen te ondertekenen. De bestaande creditfactuurflow moet ongewijzigd werken.

### Controle van de bestaande export op 4 oktober 2026

Bron: [Apps Script Wedstrijdregistratie, Code.gs](https://script.google.com/u/0/home/projects/1MzHwRL5ZklNKUYRec1QyelNk2Pfd95RrAyKRa_aJMbypGcqLSety9_mP/edit),
functie `exportPremiesJO23()`. De volledige functie is read-only via de editor gelezen.
Het productiescript is niet uitgevoerd of gewijzigd.

Het script leest berekende waarden uit `Export JO23-1!A:E`, kopieert ze naar één tijdelijk
spreadsheet en vraagt daarvan de XLSX-export op. Kolom C krijgt getalnotatie `#,##0.00` en kolom E
`dd-mm-yyyy`; de eerste rij is vet. Het script berekent geen premies en stelt de uitvoerdatum
niet zelf in: die komt uit de sheetformule met `TODAY()`. De omschrijving komt eveneens uit de
sheet en is `Premie <maandnaam> <jaar>`.

Het XLSX-bestand wordt aangemaakt in de eerste bovenliggende Drive-map van de bronsheet, met
Mijn Drive als terugval wanneer er geen bovenliggende map wordt gevonden. Alle bestaande
XLSX-bestanden met dezelfde maandbestandsnaam worden eerst naar de prullenbak verplaatst.
Na het opslaan wordt het tijdelijke Google-spreadsheet naar de prullenbak verplaatst.

De gerichte Drive-zoekopdracht en de huidige bovenliggende map `Vergoedingen / 2026/2027`
leverden geen bestaand `Inputfile vergoedingen (voetbal)_JO23_september_2026.xlsx` op. Een eerder
gegenereerd betaalbestand kon daardoor niet worden geïnspecteerd. Het aanwezige oorspronkelijke
wedstrijdregistratie-XLSX is geen betaalexport. Uit de exportfunctie blijkt niet welk bankpakket
of welke tussenstap het bestand accepteert. Compatibiliteit van deze oude XLSX-export blijft
onbevestigd; Joost heeft voor Rondo inmiddels de bestaande Rabobank SEPA-route gekozen.

| Bevinding | Gevolg |
|---|---|
| Waarschuwing alleen bij exact `Niet gevonden`, met keuze om door te gaan | De huidige zeven ontbrekende rekeningen voor €255 kunnen als ongeldige betaalregels worden geëxporteerd |
| Filter verwijdert uitsluitend regels met een lege eerste cel | Een lege IBAN-regel verdwijnt zonder waarschuwing; een numerieke nul blijft staan |
| Geen rekening-, bedrag- of datumvalidatie | Een ongeldige IBAN of een niet-numeriek bedrag wordt door het script niet tegengehouden |
| Alleen `data.length < 2` controleert of er premies zijn | De formuletekst `Geen premies in deze maand` kan als betaalregel worden geëxporteerd |
| Oude bestanden worden verwijderd vóór het nieuwe bestand is opgeslagen | Bij een schrijffout staat het oude bestand al in de prullenbak en is er geen nieuwe export |
| Geen `try/finally` voor opruimen | Bij een fout na aanmaak kan het tijdelijke spreadsheet met betaalgegevens blijven staan |
| Dezelfde maandnaam wordt telkens hergebruikt, zonder batch- of verwerkingsregistratie | De export maakt geen onderscheid tussen een herdownload, correctie en al betaalde maand |

Acht lokale scenario's zijn doorlopen met een kopie van de functie, fictieve regels en volledig
vervangen Google-services: ontbrekende rekening met Ja/Nee, lege rekening, numerieke nul,
geen-premies-melding, ongeldig bedrag/IBAN, mislukte opslag en normale vervanging. De bovenstaande
controle- en volgordefouten zijn daarbij gereproduceerd. Er waren geen netwerkrequests,
Drive-wijzigingen of betalingen. Dit test de scriptlogica, niet Google-autorisatie, gedeelde
Drive-rechten, echte XLSX-celtypen of bankacceptatie.

Voor Rondo blijft het voorstel: expliciete validatie met blokkade bij ongeldige betaalregels,
een werkelijk lege maand zonder bestand afhandelen, en onveranderlijke batches met afzonderlijke
exportversies gebruiken. Bestandsaanmaak mag een bestaande geldige export niet eerst verwijderen.
Deze verbeteringen zijn onderdeel van het Rondo-voorstel; het bestaande script is ongewijzigd.

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

**B3a, bevestigd door Joost:** gastspelers krijgen dezelfde vergoeding en premie als de vaste
spelers van het team waarmee zij die wedstrijd meedoen. Een gastspeler bij AWC 1 valt onder de
AWC 1-regeling; een gastspeler bij JO23-1 valt onder de JO23-1-regeling. Het eigen team van de
speler verandert deze toepassing niet.

**B3b, bevestigd door Joost:** bij Basis/Bank in twee afzonderlijke wedstrijden van AWC 1 en
JO23-1 op dezelfde dag tellen beide wedstrijden mee volgens hun eigen teamregeling. Joost geeft
aan dat dit in de praktijk niet voorkomt. Geen aanvullende workflow of samenloopwaarschuwing bouwen.

**B3c, vastgesteld uit de sheet op verzoek van Joost:** de sheet bevat geen individuele
uitzonderingen op de tellingen of het JO23-1-premietarief. De eerste versie neemt die uniforme
rekenregels over en krijgt geen individuele uitzonderingsmodule. Eventuele persoonlijke
loonbedragen voor AWC 1 blijven in Nmbrs; de sheet bewijst niet dat die bedragen voor iedereen
gelijk zijn. Dit is een bronbevinding, geen bevestiging van individuele contractafspraken.

**B4a, vastgesteld uit het exportscript:** de huidige uitvoer is een XLSX met vijf kolommen,
berekende waarden en de maandbestandsnaam uit hoofdstuk 10. Het script laat ongeldige betaalregels
door en vervangt bestaande maandbestanden via de prullenbak. Bron- en lokale scenariocontrole
zijn afgerond; bankcompatibiliteit en een werkelijk gegenereerd bestand zijn nog niet geverifieerd.

**B4b, bevestigd door Joost:** de Rondo-betaalexport moet een Rabobank-export worden zoals die
al bestaat bij creditfacturen. De gecontroleerde bestaande implementatie levert SEPA-XML
`pain.001.001.09`. Hergebruik de validatie en generator via een gedeelde service en ondersteun
meerdere premiebetalingen in één maandbestand. De oude XLSX-uitvoer hoeft niet te worden nagebouwd.

**B4c, bevestigd door Joost:** voeg het IBAN toe aan bestaande personen en laat leden hun eigen
IBAN wijzigen via Mijn gegevens. Gebruik geen apart betaalprofiel. Pas persoonsgebonden veldrechten
toe en behoud de bankgegevens in eerder afgesloten batches en exports.

**B5a, bevestigd door Joost:** de financiële beheerder mag zelfstandig een maand afsluiten en
exporteren. Bouw geen verplichte tweede goedkeuringsstap in Rondo. Dit verandert niets aan de
controle en ondertekening van betalingen in Rabobank.

**B5b, bevestigd door Joost:** alleen expliciet per team aangewezen registratoren mogen de
wedstrijdselecties invoeren en corrigeren, binnen hun bestaande teamtoegang. Teambeheerrechten
geven deze bevoegdheid niet automatisch. De administrator beheert de toewijzingen; afgesloten
registraties blijven het vastgelegde correctie- en versieproces volgen.

### Nog te besluiten vóór de betreffende bouwfase

De overige voorgestelde defaults worden vóór de afhankelijke fase bevestigd. Vastlegging van
deze afspraken is nog geen opdracht tot implementatie of productie-import.

| ID | Open besluit | Voorgesteld uitgangspunt | Nodig vóór |
|---|---|---|---|
| B6 | Welke historische maanden zijn al verwerkt of betaald? | Geen automatische betaalstatus uit spreadsheetdata afleiden | Productie-import |
| B7 | Tariefingangsdata, negatieve correcties en tenaamstelling | Gesloten bedragen bewaren; negatieve correcties handmatig afhandelen | Correcties en export |
| B8 | Bewaartermijn voor afwezigheidsredenen, bankgegevens, batches en bestanden | Aansluiten op vastgesteld clubbeleid; geen termijn verzinnen | Productievrijgave |

## 13. Uitvoeringsfasen

| Fase | Resultaat | Voorwaarde om door te gaan |
|---|---|---|
| 0 | B1 vertalen naar broncategorieën, besluiten B6–B8, gedeelde SEPA-service en batchcontract uitwerken; Apps Script-controle is afgerond | Rekencontract bevestigd; bestaande Rabobank-contracten en broncontrolegrenzen vastgelegd |
| 1 | Privé opslag, registry, rechtenbeleid, versiebeheer, calculator en API | Rekentests, rechtenmatrix en gelijktijdigheids-/hersteltests slagen |
| 2 | Teamregistratie, gastspelers, afronden en bronconflicten | Volledige flow met synthetische data op desktop en mobiel gecontroleerd |
| 3 | Bankvelden op personen en Mijn gegevens, maandafsluiting, correcties en Rabobank SEPA-export via gedeelde service | Rekenverschillen nul, schema-/batchtests en creditfactuurregressietests slagen; proefimport gecontroleerd |
| 4 | Importvoorvertoning, goedgekeurde migratie en parallelle maand | Alle matches beoordeeld, totalen sluiten aan, externe verwerking bevestigd |
| 5 | Rondo als invoerbron en sheet archiveren | Registrator en penningmeester accepteren de pilot |

Elke codefase krijgt relevante JS/PHP-tests, lint, build, versie en changelog, commit en push.
Productievrijgave volgt de bestaande CI/deployroute en wordt daarna ingelogd gecontroleerd.
Nieuwe functionaliteit blijft tot de afgesproken pilottoewijzing uitgeschakeld. Vanuit een
worktree wordt niet naar productie gedeployed. De dagautomatisering onderhoudt de ontwikkelaarsdocs.
Deze planning zelf blijft beperkt tot `docs/prd/`, zonder themaversie, changelog of deployment.

## 14. Acceptatiecriteria

- [ ] Een financiële beheerder kan na geldige controles zelfstandig afsluiten en exporteren,
  zonder tweede fiatteur in Rondo. Exporteren registreert geen betaling.
- [ ] Een registrator kan alleen toegewezen én reeds toegankelijke teams registreren en corrigeren;
  bestaande teambeheerrechten alleen geven geen schrijfrechten. Een gewone teambezoeker ziet geen
  selecties. Directe URL's en API-requests respecteren dezelfde grenzen.
- [ ] Financieel lezen laat geen write, export of volledige IBAN toe. Een registrator krijgt geen
  financiële gegevens van anderen. Publieke ICS, zoeken, abilities en generieke persoonexports lekken niets.
- [ ] Een lid kan uitsluitend het eigen IBAN via Mijn gegevens lezen en wijzigen. Een ander
  persoons-ID, huishoudtoegang of meegestuurde Nmbrs-velden verruimen dit niet. Oud-leden blijven
  alleen-lezen; generieke personen-API en abilities handhaven dezelfde bankveldrechten.
- [ ] Ongeldige IBAN's worden afgewezen; wissen met `null` is mogelijk en blokkeert positieve
  betalingen bij afsluiten. Wijzigingslogs bevatten geen volledig IBAN en starten geen Sportlink-sync.
- [ ] Een rekeningwijziging na de voorvertoning wordt bij afsluiten ontdekt. Wijzigingen na
  afsluiten veranderen geen batchmomentopname of XML; herdownloads blijven exact gelijk.
- [ ] Elke persoon komt hooguit eenmaal per wedstrijd voor. Onbekend en nul blijven onderscheiden.
  Gastspelers hoeven niet tot het huidige team te behoren, maar moeten toegankelijk en gekoppeld zijn.
- [ ] Een gastspeler met dezelfde deelnamestatus ontvangt dezelfde vergoeding en premie als een
  vaste speler in die wedstrijd. Bij AWC 1 geldt de AWC 1-regeling; bij JO23-1 de JO23-1-regeling,
  ongeacht het eigen team van de speler.
- [ ] Thuis/uit, 0-0, verlies, ontbrekende uitslag, afwijkende notatie, afgelasting, verplaatsing,
  onduidelijke nacompetitie-uitslagen en december/januari worden volgens de vastgelegde regels verwerkt.
- [ ] Alleen competitie en nacompetitie tellen mee. Beker- en oefenwedstrijden leveren voor beide
  teams geen vergoeding, premie of Nmbrs-aantal op, ook niet als er Basis/Bank is geregistreerd.
- [ ] AWC 1 levert basis-/bankaantallen bij iedere meetellende deelname en daarnaast premies bij
  winst/gelijkspel. JO23-1 levert uitsluitend resultaatpremies, zonder basis-/bankvergoeding.
- [ ] Twee verschillende meetellende AWC 1-wedstrijden op dezelfde kalenderdag met Basis/Bank
  voor dezelfde speler leveren `Dagen = 2`; dezelfde wedstrijd dubbel aanbieden blijft één deelname.
- [ ] Basis/Bank bij AWC 1 en JO23-1 op dezelfde dag wordt per afzonderlijke wedstrijd volgens
  de eigen teamregeling berekend, zonder voorrang, vermindering of samenloopwaarschuwing.
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
- [ ] De premie-export valideert tegen de bestaande Rabobank-XSD `pain.001.001.09`. Meerdere
  ontvangers leveren elk één transactie met unieke `EndToEndId`; beide aantallen en controlesommen
  sluiten exact aan op de batch. Nulbedragen en negatieve bedragen worden niet als betaling geëxporteerd.
- [ ] Creditfactuur- en premie-export gebruiken dezelfde validatie en XML-generator. De bestaande
  creditfactuurtests blijven groen, inclusief rechten, gelijke herdownloads en verloren responses.
- [ ] De clubrekening, tenaamstellingen en uitvoerdatum volgen het bestaande Rabobank-contract.
  Een gecontroleerde proefimport van de batchvariant slaagt zonder betalingen te ondertekenen.
- [ ] Een maand zonder premies levert geen betaalbestand of tekstuele betaalregel op. Lege,
  ontbrekende en ongeldige IBAN's bij positieve bedragen blokkeren de export zonder regels te verliezen.
- [ ] Een fout tijdens bestandsaanmaak behoudt bestaande exports en laat geen publiek toegankelijk
  tijdelijk betaalbestand achter. Een herdownload vervangt geen eerdere batch of verwerkingshistorie.
- [ ] Herhaalde import is zonder extra wijzigingen; onbekende personen, wedstrijden en bestaande
  betaalstatussen zijn vóór migratie opgelost of expliciet als onopgelost geblokkeerd.
- [ ] De registrator en penningmeester voltooien de afgesproken flow op productie na succesvolle
  CI/deploy en accepteren één parallel verwerkte maand voordat de sheet wordt gearchiveerd.

Tests gebruiken synthetische personen en bankgegevens. PHP-tests booten de benodigde REST-controllers
expliciet en draaien tegen MySQL volgens `docs/testing.md`. De volledige bestaande suite moet groen
blijven. Een geslaagde build geldt niet als bewijs van juiste betaling of bankcompatibiliteit.
