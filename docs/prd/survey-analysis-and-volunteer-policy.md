# Herbruikbare enquêteanalyse en vrijwilligersbeleid

**Status:** Mijlpaal 1 geïmplementeerd in 35.172.0; mijlpalen 2 en 3 blijven voorstellen.
**Datum:** 15 november 2026.
**Scope:** Rondo Club, binnen de bestaande enquêtes en vrijwilligersstatistieken.
**Codebasis:** `afe8d441` op `main`.

Rondo krijgt een analysewerkruimte voor iedere enquête. Bestuursleden kunnen daarin
resultaten vergelijken, open antwoorden ordenen, conclusies onderbouwen en acties
opvolgen. Een herbruikbaar analyseprofiel bepaalt welke vragen en groepen belangrijk
zijn. Het vrijwilligersbeleid is de eerste toepassing, inclusief een zelfstandig
dashboard met actuele Rondo-statistieken en historische dagstanden.

De gebruiker heeft mijlpaal 1 goedgekeurd voor implementatie en uitrol. De uitwerking
van mijlpalen 2 en 3 blijft een voorstel. Het dashboard wijzigt geen
vrijwilligersbeleid; de kostencalculator is uitsluitend een rekenvoorbeeld.

## Doel en afbakening

Een bestuurslid moet vanuit een enquête kunnen beantwoorden: hoeveel reacties zijn
er, wat zeggen de antwoorden, verschillen groepen van elkaar, welke voorbeelden
onderbouwen een conclusie en wie doet er iets mee? Een volgende enquête moet
dezelfde mogelijkheden krijgen zonder code voor specifieke vraagteksten of namen.

De eerste oplevering bestaat uit drie afzonderlijk te beoordelen mijlpalen:

1. Vrijwilligersdashboard met correcte tellingen, rekenvoorbeeld en dagstanden.
2. Algemene analysewerkruimte met profielen, filters, thema's en conclusies.
3. Opvolging en vergelijking tussen enquêterondes.

Buiten deze oplevering vallen automatische AI-analyse, externe CSV-import,
automatische benadering van respondenten, wijzigingen aan de vrijwilligersplicht,
echte afkoopbetalingen en koppelingen met externe enquêtesystemen. Handmatige
conclusies en bewerkbare trefwoordregels zijn voldoende voor de eerste versie.

## Bestaande basis

| Onderdeel | Huidige implementatie | Uitbreiding |
|---|---|---|
| Vragenlijsten | Single choice, multiple choice, schaalvragen, tekst en conditionele vragen | Hergebruik de bestaande vraag- en optie-ID's |
| Publicaties | Onveranderlijke gepubliceerde vragenlijstversies | Analyse blijft gekoppeld aan de juiste versie |
| Resultaten | Vraagverdelingen, open antwoorden, één antwoordfilter, gepagineerde inzendingen en CSV-export | Gecombineerde filters, vergelijkingen, opgeslagen weergaven en geselecteerde kerncijfers |
| Respondenten | Enquêtes met en zonder opgeslagen persoonskoppeling | Respecteer die keuze in analyse en opvolging |
| Vrijwilligers | Bezetting, spreiding, verplichting, teamoverzicht en inschrijfverloop | Beleidskengetallen, kostenscenario en echte dagstanden |
| Taken | Auteur/toegewezene hebben toegang; toewijzing kan taakinhoud mailen | Vertrouwelijke enquêteopvolging krijgt een eigen gedeelde werkvoorraad |

Bronnen: [enquêteopslag](../../includes/class-surveys.php),
[vragenlijstenschema](../../includes/class-survey-schema.php),
[resultaten-API](../../includes/class-rest-surveys.php),
[enquête-interface](../../src/pages/Surveys/Surveys.jsx),
[vrijwilligersstatistieken](../../includes/class-volunteer-statistics.php) en
[taken-API](../../includes/class-rest-todos.php).

De functionele referentie is het [AWC-artifact](https://claude.ai/artifact/F5Pep1acptYQEFi6gpe1sa#rondo).
Voorbeelden in dit PRD zijn fictief; namen en individuele antwoorden uit het artifact
worden niet overgenomen naar de repository.

## Navigatie en gebruik

Behoud de bestaande tab **Resultaten** binnen een enquête. Maak daaronder de
onderdelen **Overzicht**, **Vragen**, **Open antwoorden**, **Conclusies** en
**Opvolging**. Toon **Rondo-context** alleen wanneer een contextmodule is gekoppeld
en de gebruiker daarvoor bevoegd is. Bestaande bewerk-, deel- en uitnodigingsfuncties
blijven op hun huidige plek.

Filters en selectie van de vragenlijstversie gelden voor de hele analyseweergave.
Toon actieve filters boven de resultaten, met een duidelijke reset. Een geselecteerd
opgeslagen overzicht kan via een URL worden geopend; zet geen namen, antwoordteksten
of persoonsgegevens in URL-parameters.

Het zelfstandige vrijwilligersdashboard komt onder
`/vrijwilligers/statistieken?tab=policy`, naast **Algemeen** en **Per team**, als tab
**Beleid**. Een enquête met vrijwilligerscontext gebruikt dezelfde berekeningen en
weergavecomponenten. Enquêtefilters veranderen de clubbrede Rondo-statistieken niet;
beide groepen en peildatums blijven expliciet zichtbaar.

## Direction contract

**THESIS:** De Beleid-tab laat bestuursleden de actuele vrijwilligersplicht begrijpen
en kosten doorrekenen, zonder een losse verzameling grote cijferkaarten.

**OWN-WORLD:** Bestaande Rondo-kaarten, invoervelden, tabs, typografie en
licht/donker-kleuren vormen de herkenbare statistiekenpagina.

**STORY:** Lees eerst de verplichting en bezetting, verander daarna uren en uurkosten
voor een direct reagerend rekenvoorbeeld en vergelijk vervolgens de opgeslagen dagstanden.

**FIRST VIEWPORT:** De seizoenskeuze en tabs staan boven een compacte
kengetallentabel links en de bestaande bezettingsbalken en taakspreiding rechts.
Daaronder volgen de calculator en horizontaal scrollbare historietabel. Op mobiel
staan de secties onder elkaar; ontbrekende percentages blijven expliciet benoemd.

**FORM:** Uitbreiding van de bestaande statistiekenpagina in Operate-modus;
geen nieuwe visuele wereld of concept-seed. De bestaande pagina bepaalt de vorm.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Mijlpaal 1 Vrijwilligersdashboard

### Kengetallen en definities

Bereken vanuit de bestaande eligibility-, vrijstellings- en verplichtingsservices.
Introduceer geen tweede definitie van een spelerplicht of gezinsplicht. Een eenheid
zonder resterende beleidsverplichting door vrijstelling hoort niet in de populatie
van plichtigen; een eenheid met een verplichting van nul telt evenmin mee.

Voor iedere niet-vrijgestelde eenheid `i` met `required_i > 0`:

```text
completed_i = min(required_i, erkende uitgevoerde taken)
covered_i   = min(required_i, completed_i + erkende ingeplande taken)
open_i      = required_i - covered_i
on_track_i  = covered_i == required_i
not_started_i = covered_i == 0
```

De bestaande calculator blijft bepalend voor taakcredits, no-shows, annuleringen,
gezinsattributie en pro-rata verplichtingen. Extra inzet van één gezin mag geen
openstaande verplichting van een ander gezin compenseren.

| Kengetal | Definitie |
|---|---|
| Bezetting | Geldige huidige koppelingen / capaciteit van gepubliceerde, niet-geannuleerde diensten in het seizoen |
| Plichtige eenheden | Aantal niet-vrijgestelde eenheden met een positieve verplichting |
| Op schema | Eenheden met voldoende uitgevoerde plus ingeplande taken / plichtige eenheden |
| Nog niets gepland of uitgevoerd | Eenheden met `covered_i = 0`, als aantal en percentage |
| Vereiste taken uitgevoerd | Som `completed_i`, als aantal en percentage van de totale verplichting |
| Uitgevoerd of ingepland | Som `covered_i`, als aantal en percentage van de totale verplichting |
| Vereiste taken nog open | Som `open_i` |
| Basisverplichting | Huidige basis uit de beleidsservice, met uitleg over gezins- en instroomregels |
| Max. bezetting vanuit verplichting | `min(100%, totale verplichting / capaciteit)`; zonder capaciteit: niet van toepassing |

Een lege populatie toont nul aantallen en **Niet van toepassing** voor percentages
zonder noemer. Deze maximumbezetting is een scenario op basis van taak-aantallen,
geen roosteroptimalisatie of belofte dat taken op passende tijden beschikbaar zijn.
Extra inzet en vrijgestelde vrijwilligers kunnen de werkelijke bezetting verhogen.

Hergebruik de bestaande bezetting per taaksoort en spreiding over vrijwilligers.
Maak zichtbaar dat inschrijvingen, dienstplekken en nagekomen verplichtingen
verschillende tellingen zijn. Taakcredits tellen bijvoorbeeld mee voor verplichtingen,
maar vullen niet automatisch een dienstplek.

### Kostenscenario

Start met de aannames **3 uur per taak** en **€ 20 per uur**, beide aanpasbaar in de
weergave. Toon kosten per taak, een indicatie voor de basisverplichting en kosten
voor alle nog openstaande vereiste taken. Dit is `uren × uurkosten × aantal taken`.

De invoer is tijdelijk en verandert geen beleid, factuur of accountinstelling. Geef
een duidelijke validatiemelding bij lege, negatieve of ongeldige invoer. Vermeld dat
aanvullende kosten en alle overige onbezette dienstplekken buiten deze raming vallen.

### Dagstanden

Sla dagelijks één geaggregeerde observatie op voor het actuele seizoen, met lokale
peildatum, exact vastlegtijdstip en versie van de berekeningsdefinitie. Bewaar de
benodigde tellers én noemers voor de getoonde percentages. De actuele rij blijft
afzonderlijk zichtbaar; verversing van de pagina overschrijft geen opgeslagen stand.

Gebruik een niet-autoloadende WordPress-optie per seizoen met maximaal 366
observaties. De eerste opname volgt kort na inschakeling; daarna draait een dagelijks
WP-Cron-event. Herhaalde of overlappende jobs mogen dezelfde datum niet dupliceren
of eerdere standen overschrijven. Leg bij ontbrekende cronruns geen fictieve
tussenstanden vast. Een seizoenswisseling opent automatisch een nieuwe reeks.

Historie begint bij ingebruikname. Importeer de handmatig overgetypte artifactcijfers
niet automatisch: de rekenbasis en peildatum kunnen verschillen. Historische seizoenen
tonen hun opgeslagen standen naast duidelijk als actueel herberekend aangeduide cijfers.
Een gewijzigde formule krijgt een nieuwe definitieversie; markeer de breuk bij vergelijking.

### Acceptatie

- Vrijstellingen, nulverplichtingen, gezinsdeling, extra taken, credits, no-shows en
  geannuleerde diensten volgen de bestaande calculator en bovenstaande definities.
- In een test met twee eenheden die ieder twee taken moeten doen, waarvan één er
  zes heeft gedaan en de ander nul, blijven twee vereiste taken open.
- Lege en historische seizoenen werken zonder deling door nul of verzonnen historie.
- Alleen gebruikers met bestaande vrijwilligersrechten krijgen de gegevens.
- Dagstanden bevatten geen persoons-ID's, antwoorden, namen of e-mailadressen.
- De calculator, seizoenkeuze, verversing en toetsenbordnavigatie werken op mobiel
  en desktop, in licht en donker thema.

## Mijlpaal 2 Herbruikbare enquêteanalyse

### Analyseprofielen

Een beheerder kiest een leeg profiel of het profiel **Vrijwilligersbeleid**. Een
profiel bevat geselecteerde kernvragen, antwoordgroepen, vergelijkingsgroepen,
opgeslagen filters, thema's en optionele Rondo-context. Een profielversie wordt naar
de enquête gekopieerd; latere templatewijzigingen wijzigen bestaande analyses niet.

Een kerncijfer zoals **Tevreden** is een benoemde selectie van antwoordopties van
één vraag. De beheerder selecteert die opties expliciet. Vraagteksten en woorden
zoals “tevreden” bepalen geen berekening. Bij gebruik op een andere vragenlijst
bevestigt de beheerder de mapping van vragen en opties. Onopgeloste verwijzingen
blokkeren de betreffende tegel of vergelijking met een herstelmogelijkheid.

### Respons en vraagresultaten

Toon aantal inzendingen en antwoorden per vraag, met de gebruikte noemer. Voor
uitnodigingsenquêtes bereken je de respons op basis van unieke uitnodigingen waarvan
verzending bevestigd is en de daarvan voltooide uitnodigingen. Verzendfouten en
voorbereide uitnodigingen tellen niet als verstuurd. Een herinnering vergroot de
noemer niet. Tel inzendingen via een openbare link afzonderlijk wanneer beide routes
binnen dezelfde enquête mogelijk zijn.

Bij een openbare enquête zonder afgebakende doelgroep is een responspercentage niet
beschikbaar. Deel nooit automatisch het aantal inzendingen door het huidige
ledenbestand. Uitnodigingsstatistieken worden geaggregeerd teruggegeven aan
resultaatlezers zonder daarmee de ontvangerslijst toegankelijk te maken.

Voor vraagpercentages is de noemer het aantal inzendingen met een antwoord op die
vraag, na filtering. Overgeslagen conditionele vragen zijn geen negatieve antwoorden.
Bij meerkeuze kan de som boven 100% liggen; één inzending telt maximaal eenmaal per
optie en per benoemde antwoordgroep. “Weet ik niet” blijft zichtbaar; uitsluiten uit
een score vereist een expliciete profielinstelling en zichtbare noemer.

### Filters en vergelijkingen

- Meerdere antwoordfilters tegelijk: AND tussen vragen, OR tussen geselecteerde
  opties van dezelfde vraag. Filters kunnen worden opgeslagen met een naam.
- Kruistabellen vergelijken één gekozen uitkomstvraag met groepen uit een andere
  vraag. Een respondent met meerdere rollen kan in meerdere groepen voorkomen;
  tel de groepsgroottes daarom niet op tot een uniek totaal.
- Toon steeds aantallen naast percentages. Markeer groepen met minder dan tien
  antwoorden als **Kleine groep**; dit is een interpretatiehulp, geen statistische
  significantietoets of wijziging van toegangsrechten.
- Start met groepen uit enquêteantwoorden. Automatische persoonsverrijking met
  actuele team-, gezondheids- of andere profielgegevens valt buiten deze versie.
- Resultaten, geselecteerde kerncijfers en export gebruiken exact dezelfde selectie.

### Open antwoorden en thema's

Laat tekst doorzoeken en filteren op vraag en thema. Beheerders kunnen antwoorden
handmatig labelen. Optionele trefwoordregels doen controleerbare suggesties, met het
gevonden woord en het oorspronkelijke antwoord. Een suggestie telt pas als bevestigd
thema nadat een beheerder die accepteert. Bevestigingen blijven bestaan bij verversen.

Thematellingen tellen unieke inzendingen per thema, ook als hetzelfde onderwerp in
meerdere antwoorden voorkomt. Gebruik niet het woord “personen” wanneer unieke
personen niet betrouwbaar zijn vast te stellen. Thema's zijn onafhankelijk van de
vrijwilligersvragen en kunnen naar een andere enquête worden gekopieerd.

### Conclusies en vastgelegde rapporten

Een conclusie bevat titel, tekst, auteur, datum en verwijzingen naar relevante
vragen, selecties, thema's of antwoorden. Bij vastleggen wordt een onveranderlijk
rapport bewaard met de vragenlijstversie, profielversie, selectie, berekeningsversie,
bronrevisie en de gebruikte aggregaten. Individuele antwoorden blijven verwijzingen
naar de beschermde bron; maak geen extra volledige kopie van de responsdatabase.

Nieuwe reacties verversen live cijfers, maar niet het bewijs bij een eerder rapport.
Toon **Nieuwe reacties sinds dit rapport** wanneer dat van toepassing is. Een
correctie maakt een nieuwe rapportversie. Een verwijderde bron krijgt de melding
**Bron niet meer beschikbaar** en wordt niet teruggehaald uit een verborgen kopie.

Gebruik voor de eerste versie handgeschreven conclusies. Er worden geen antwoorden
naar een AI-dienst gestuurd. Eventuele AI-ondersteuning vergt een afzonderlijk voorstel.

### Rondo-context en eerste profiel

Het profiel Vrijwilligersbeleid koppelt kernvragen over tevredenheid, duidelijkheid
en deelname, vergelijkingen op rol en deelname, en de vrijwilligerscontext uit
mijlpaal 1. De exacte koppeling wordt gemaakt via de werkelijk aanwezige vraag-ID's,
niet via vaste nummers uit het artifact.

De contextmodule is optioneel en houdt haar eigen seizoen, populatie, peildatum en
rechten. Een geanonimiseerde enquête kan clubbrede context tonen zonder individuele
antwoorden aan leden te koppelen. Nieuwe contextmodules zijn later mogelijk; deze
oplevering bouwt alleen de vrijwilligersmodule en het gemeenschappelijke contract.

### Acceptatie

- Hetzelfde analysesysteem werkt met het vrijwilligersprofiel én een fictieve
  accommodatie-enquête met andere vraag-ID's, opties en secties, zonder codewijziging.
- Filters, percentages, kruistabellen, thema's en exports geven dezelfde selectie.
- Tests dekken conditionele vragen, meerdere rollen, dubbele themahits, lege
  selecties, verschillende publicatieversies en ontbrekende templatekoppelingen.
- Nieuwe antwoorden veranderen bestaande rapporten niet.
- Een resultaatlezer kan lezen, maar zonder beheerrechten geen profielen, thema's
  of conclusies wijzigen.
- Enquêtes zonder persoonskoppeling krijgen via analyse geen herleidbare identiteit.

## Mijlpaal 3 Opvolging en enquêterondes

### Opvolging

Een beheerder maakt vanuit een antwoord, thema of conclusie een actie. Elke actie
heeft een omschrijving, verantwoordelijke Rondo-gebruiker, deadline, bronverwijzing,
status en notities. Statussen: **Open**, **In behandeling**, **Afgerond**, **Geen actie**.
Bij Geen actie wordt een reden vastgelegd. Wijzigingen registreren auteur en tijdstip.

De lijst ondersteunt filters op eigenaar, status en verlopen deadline. Alle bevoegde
enquêtebeheerders zien de gezamenlijke werkvoorraad. Een actie wijst niet automatisch
een taak toe aan de respondent; de verantwoordelijke is een interne beheerder.

Configureerbare regels gebruiken expliciete antwoordopties en bevestigde thema's.
Voorbeeld: een gekozen lage waardering levert een voorstel op, geen automatisch
bericht of besluit over een persoon. De beheerder accepteert of verwerpt het voorstel.
Herhaalde verwerking van dezelfde bron en regelversie maakt geen dubbele voorstellen
of acties; een afgehandelde actie wordt niet automatisch heropend.

Bewaar dit in een eigen, afgeschermd survey-actierecord. Gebruik niet automatisch
`rondo_todo`: de huidige toegang is op auteur/toegewezene gebaseerd en de
toewijzingsmail kan inhoud bevatten. Een eventuele latere koppeling naar persoonlijke
taken moet vertrouwelijke broninhoud buiten generieke lijsten, e-mails en meldingen
houden en krijgt een afzonderlijke beoordeling.

Bij een enquête zonder persoonskoppeling blijven acties bij het antwoord of thema.
Bij een gekoppelde enquête mag een bevoegde gebruiker de bestaande respondent zien;
het openen van het ledenprofiel blijft onder de aparte persoonsrechten vallen.
Er worden in deze oplevering geen automatische opvolgmails verstuurd.

### Vergelijken tussen rondes

Een enquêteronde is een afzonderlijke enquête, bijvoorbeeld de meting van dit jaar
en die van volgend jaar. Gepubliceerde revisies binnen één enquête blijven versies
van die ronde en worden niet automatisch bij elkaar opgeteld.

Een beheerder koppelt overeenkomstige vragen en opties expliciet aan een gedeelde
meetdefinitie. Toon per ronde aantal antwoorden, periode, doelgroep, noemer en
vraagtekst. Toon verschillen in percentages als procentpunten. Veranderde schalen,
antwoordopties of doelgroepen krijgen een zichtbaar vergelijkbaarheidslabel; een
onverenigbare vraag krijgt geen automatisch verschilcijfer.

### Acceptatie

- Acties zijn gezamenlijk toegankelijk voor bevoegde beheerders; eigenaar en deadline
  blijven bewaard na herladen en zijn filterbaar.
- Herhaalde regels leveren geen dubbele voorstellen of acties op.
- Het intrekken van resultaatrechten ontneemt ook toegang tot bronantwoorden,
  notities en acties, ook als iemand eerder als eigenaar was geselecteerd.
- Anonieme opvolging maakt geen persoonskoppeling en verstuurt geen berichten.
- Vergelijking werkt voor expliciet gekoppelde vragen en weigert stilzwijgende
  vergelijking van incompatibele schalen.

## Rechten en opslag

| Handeling | Vereiste toegang |
|---|---|
| Resultaten, thema's en vastgelegde conclusies lezen | Bestaande `enquete_resultaten`-toegang |
| Profielen, labels, conclusies, regels en acties beheren | Zowel `enquetes` als `enquete_resultaten` |
| Eigenaar van een opvolgactie worden | Goedgekeurd account met beide bovenstaande rechten |
| Vrijwilligersdashboard lezen | Bestaande vrijwilligersrechten |
| Vrijwilligerscontext in een enquête lezen | Zowel resultaatrechten als vrijwilligersrechten |
| Gekoppeld ledenprofiel openen | Aanvullend de bestaande rij- en veldrechten voor die persoon |

Alle controles gebeuren server-side, inclusief exports, bronverwijzingen en directe
record-ID's. Voeg geen ruimere rollen toe om het scherm te laten werken. De enquête
blijft bruikbaar wanneer de contextmodule niet toegankelijk is.

Hergebruik de private WordPress-CPT-opslag en gevalideerde documentstructuren van de
bestaande surveyservice. Voorgestelde nieuwe typen passen binnen WordPress' limiet:

| Object | Opslag |
|---|---|
| Herbruikbaar analyseprofiel | Private CPT `rondo_survey_profile`; versies expliciet in het profiel |
| Enquêtespecifieke configuratie | Gevalideerd profiel op de enquête, met eigen revisie en bronprofielversie |
| Bevestigde antwoordthema's | Native metadata op bestaande antwoordrecords, via de surveyservice |
| Conclusie met vastgelegd bewijs | Private CPT `rondo_survey_report`, gekoppeld aan de enquête |
| Actie of regelvoorstel | Private CPT `rondo_survey_action`, gekoppeld aan enquête en bron |
| Actiewijzigingen | Private CPT `rondo_survey_event`, gekoppeld aan de actie |
| Vrijwilligersdagstanden | Niet-autoloadende optie per seizoen, uitsluitend aggregaten |

Geen custom tabellen of raw SQL. Registreer nieuwe domeinmetadatavelden in de native
field registry. Valideer alle opgeslagen configuratie en bronrelaties. Nieuwe private
typen zijn niet via generieke WordPress-REST, zoekresultaten of publieke exports
leesbaar. Gebruik verwachte revisies bij bewerken om gelijktijdige wijzigingen niet
stilzwijgend te overschrijven.

Verwijdering van een enquête of antwoord moet ook de bijbehorende labels en
bronverwijzingen afhandelen. Rapporten en acties mogen verwijderde broninhoud niet
opnieuw ontsluiten. Ondersteun bestaande export- en verwijderprocessen zonder een
nieuwe persoonsgegevenskopie buiten WordPress te introduceren.

## Technische implementatievolgorde

### 1 Vrijwilligersberekeningen en dagstanden

1. Beoordeel de lokale proef tegen dit PRD; vervang afwijkingen, waaronder
   percentages zonder noemer, gelijktijdige cronruns en definitieversies.
2. Breid `VolunteerStatistics` uit met geaggregeerde beleidskengetallen. Deel één
   berekening van de gedecoreerde eenheden met het bestaande voortgangsoverzicht.
3. Voeg de dagstandservice en cronregistratie/-opruiming toe. GET-verzoeken blijven
   alleen-lezen; alleen de geplande taak schrijft historie.
4. Voeg de Beleid-tab, gedeelde componenten en rekenmodule toe.
5. Test berekeningen, toegang, snapshot-idempotentie, seizoensgrenzen en schermgedrag.

### 2 Analysecontract en profielconfiguratie

1. Splits de huidige monolithische resultatenweergave op in overzicht, vraagresultaten,
   open antwoorden en conclusies, met behoud van bestaande routes en exports.
2. Introduceer een gevalideerd analyseprofielschema en één gedeelde selectie- en
   aggregatieservice voor scherm, rapport en export.
3. Breid de resultaten-API achterwaarts compatibel uit. Voeg expliciete endpoints toe
   voor configuratie, opgeslagen selecties, thema's en rapporten, met eigen
   lees-/schrijfcontroles. Retourneer stabiele antwoord-ID's alleen aan bevoegde lezers.
4. Voeg gecombineerde filters, kruistabellen en responsstatistieken toe. Paginateer
   tekstresultaten; stuur niet alle open antwoorden mee met iedere grafiekverversing.
5. Bouw de profielmapping, handmatige thematoekenning en controleerbare
   trefwoordsuggesties. Voeg daarna conclusies met onveranderlijke rapportversies toe.
6. Koppel de vrijwilligerscontext als eerste optionele module. Valideer daarnaast
   een volledig andere fictieve enquête als bewijs van herbruikbaarheid.

### 3 Acties en vergelijkingen

1. Voeg private actie- en wijzigingsrecords, toegangscontrole, toewijzing en deadlines toe.
2. Bouw de gezamenlijke opvolglijst en idempotente regels voor voorstellen.
3. Voeg expliciete ronde-, vraag- en optiemappings toe, inclusief vergelijkbaarheidscontrole.
4. Verifieer toegangsintrekking, dubbele verwerking, anonieme enquêtes en gewijzigde schalen.

Gebruik TanStack Query voor gedeelde serverstate en gerichte invalidatie. Als
aggregatie caching nodig heeft, bevat de cachesleutel enquête, publicatieversie,
bronrevisie, profielrevisie en filters. Nieuwe reacties, gewijzigde labels en
toegangsveranderingen mogen geen verouderde of te ruim toegankelijke resultaten geven.
Meet responstijd en payloadgrootte met de actuele dataset en een synthetische dataset
van 5.000 inzendingen; leg het resultaat bij de implementatiereview vast.

## Verificatie en uitrol

Per implementatiemijlpaal: relevante PHP- en JavaScript-tests, PHP coding standards,
JavaScript-lint en productiebuild. Controleer de UI op desktop en mobiel, licht en
donker, plus lege selectie, laadfout en herstel. Gebruik uitsluitend fictieve
respondenten in fixtures en repositorybestanden.

Contracttests dekken minimaal bevoegd resultaatlezen, beheer zonder resultaatrechten,
vrijwilligersrechten zonder enquêterechten, leden zonder beide, anonieme antwoorden,
vreemde bron-ID's, exports en gelijktijdige schrijfacties. Vergelijk de berekeningen
met handmatig narekenbare fixtures, niet uitsluitend met de implementatie zelf.

Na goedkeuring van uitvoering krijgt iedere functionele mijlpaal een passende
versieverhoging, changelog, commit en push. Gebruik de normale PR- en CI-route;
productie-uitrol volgt uitsluitend vanaf `main` na de vereiste checks. Geen directe
productie-uitrol vanuit een worktree. Bied productie-UAT pas aan nadat **CI and deploy**
succesvol is afgerond.

Activeer de vrijwilligersdagstanden bij oplevering van mijlpaal 1. Gebruik voor
mijlpaal 2 eerst een fictieve enquête en koppel daarna het vrijwilligersprofiel
expliciet aan de bestaande enquête. Maak bij mijlpaal 3 geen historische acties
automatisch aan; de beheerder kiest welke voorstellen worden geaccepteerd.

Een rollback herstelt de vorige codeversie en stopt nieuw geïntroduceerde jobs waar
nodig; verwijder opgeslagen rapporten of historie niet tijdens rollback. De
ontwikkelaarsdocumentatie wordt volgens de bestaande afspraak door de dagelijkse
documentatie-automatisering bijgewerkt.

Dit planningdocument vereist geen theme-versieverhoging, changelog of productie-uitrol.
