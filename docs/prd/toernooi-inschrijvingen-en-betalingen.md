# PRD: Toernooi-inschrijvingen en betalingen

**Status:** In uitvoering — mijlpalen 1 en 2 geïmplementeerd; externe verwerking, export en programma volgen nog
**Datum:** 2026-08-27
**Eigenaar:** Toernooiplanning
**Raakt:** Rondo Club, Kaderlijst, Financiën, Mollie en Lettermint

## 1. Samenvatting

Rondo krijgt een onderdeel **Toernooien** waarmee een toernooiplanner één toernooi kan aanmaken,
de relevante Rondo-teams kan selecteren en de inschrijving kan toewijzen aan de actieve kaderleden
van ieder team.

Een toegewezen kaderlid logt in op Rondo en ziet de opdracht onder **Mijn toernooien**. Het kaderlid
kan uitsluitend een positieve inschrijving indienen. Er is geen knop of status voor "wij doen niet
mee". Bij de inschrijving worden het aantal deelnemende teams, het aantal spelers en de
spelersverdeling per deelnemend team vastgelegd. Iedere inschrijving heeft één contactpersoon voor
alle deelnemende teams samen.

Na bevestiging berekent Rondo het bedrag vanuit de voor dat toernooi vastgelegde tarieven en maakt
Rondo via de bestaande Mollie-integratie een blijvende betaallink aan. De inschrijving en de
betaling blijven twee afzonderlijke statussen. Mollie bevestigt de betaling via de bestaande
webhook. Alleen ingeschreven maar nog niet betaalde teams ontvangen betaalherinneringen; Rondo
verstuurt geen herinneringen om een team alsnog te laten inschrijven.

De toernooiplanner krijgt één overzicht met inschrijvingen, aantallen, contactpersonen, bedragen,
betaalstatussen en de voortgang van de inschrijving bij de externe organisatie. Wanneer het
programma beschikbaar is, kan de planner het vanuit Rondo versturen naar de toegewezen kaderleden
en de contactpersoon van ieder definitief ingeschreven Rondo-team. Teams die niet zijn ingeschreven
ontvangen het programma niet.

## 2. Bevestigde productkeuzes

De volgende keuzes zijn vastgesteld voor de eerste versie:

1. Alleen ingelogde Rondo-gebruikers kunnen een toernooi-inschrijving invullen.
2. De opdracht wordt per Rondo-team toegewezen aan de actieve kaderleden die via hun werkhistorie
   aan dat team zijn gekoppeld en een actief Rondo-account hebben.
3. Alle toegewezen kaderleden van hetzelfde team werken met dezelfde inschrijving; het eerste
   kaderlid dat definitief bevestigt, rondt de teamopdracht af.
4. Het kaderlid kan alleen inschrijven. Er is geen negatieve reactie, afmeldknop of status
   "geen deelname".
5. Een team waarvoor niets is ingediend blijft in het planner-overzicht staan als
   **Niet ingeschreven**. Dit is een systeemstatus, geen expliciete afmelding.
6. Rondo verstuurt één initiële toewijzingsmail, maar geen automatische of handmatige
   inschrijfherinneringen vanuit deze module.
7. Betaalherinneringen worden uitsluitend verstuurd nadat een inschrijving definitief is ingediend
   en zolang de bijbehorende betaling openstaat.
8. De toernooiplanner kan vanuit het overzicht zien welke inschrijvingen betaald zijn, zonder een
   afzonderlijke controle bij de penningmeester.
9. De daadwerkelijke inschrijving in een extern systeem of een wisselend PDF-formulier van de
   toernooiorganisatie blijft in de eerste versie een handmatige stap.
10. Rondo gebruikt voor alle betalingen de Mollie Payment Links API; reguliere kortlopende Mollie
    Payments worden niet gebruikt.
11. Eén Rondo-team kan binnen zijn ene inschrijving meerdere deelnemende toernooiteams opgeven. Per
    deelnemend team wordt het spelersaantal vastgelegd; de hele inschrijving heeft één gezamenlijke
    contactpersoon.
12. Iedere definitieve Rondo-teaminschrijving krijgt één factuur en één gezamenlijke betaallink voor
    het totaalbedrag van alle deelnemende teams.
13. Automatische betaalherinneringen gaan standaard zeven en twee dagen vóór de betaaldeadline naar
    alle toegewezen kaderleden. De planner kan daarnaast handmatig een betaalherinnering sturen.
14. Alleen toegewezen kaderleden en de contactpersoon van definitief ingeschreven Rondo-teams
    ontvangen het programma.
15. De externe voortgang wordt eenmaal voor het hele toernooi bijgehouden als niet verwerkt,
    ingediend of bevestigd.
16. De planner krijgt zowel een CSV als een printvriendelijke PDF-export.
17. Na de interne deadline kunnen kaderleden niet meer inschrijven; alleen de planner kan de
    deadline verlengen.
18. Toernooibetalingen gebruiken een eigen Rondo-factuurtype met prefix `O` en de ene, in
    Financiële instellingen gekozen standaard-Mollie-rekening voor toernooien.
19. Toernooibeheer is beschikbaar voor administrators en gebruikers met de actieve kaderfunctie
    `Coördinator toernooien`.

## 3. Aanleiding en huidig proces

Het huidige proces bestaat uit losse e-mails, een kaderlijst in Google Sheets, handmatige
bankoverschrijvingen en terugkerende afstemming met de penningmeester. De toernooiplanner moet:

- zelf bepalen welke kaderleden voor welke teams benaderd moeten worden;
- aantallen teams en spelers uit losse antwoorden samenvoegen;
- contactpersonen voor de toernooidag apart verzamelen;
- controleren of bankoverschrijvingen kloppen met de inschrijvingen;
- achter betalingen aan gaan;
- totalen opnieuw overnemen in het formulier van de organisator;
- het uiteindelijke programma weer naar kaderleden en andere contactpersonen sturen.

Dit kost vooral in de vier weken voor de externe deadline veel handmatig werk en veroorzaakt risico
op verkeerde bedragen, verkeerde rekeningnummers, gemiste contactpersonen en verschillen tussen de
inschrijflijst en de ontvangen betalingen.

## 4. Doelen

- Eén vaste plek bieden voor de volledige interne toernooi-inventarisatie.
- De bestaande team- en kadergegevens uit Rondo als bron gebruiken.
- Een inschrijving expliciet aan bekende, ingelogde kaderleden toewijzen.
- Alleen daadwerkelijke inschrijvingen vastleggen, zonder negatieve reactiestroom.
- Aantallen deelnemende teams, spelers en contactpersonen gestructureerd verzamelen.
- Het te betalen bedrag server-side en reproduceerbaar berekenen.
- Na inschrijving direct een blijvende Mollie-betaallink aanbieden.
- De betaalstatus automatisch en zonder navraag bij de penningmeester tonen.
- Alleen openstaande betalingen gericht herinneren.
- De toernooiplanner een spreadsheetachtig operationeel overzicht en bruikbare export geven.
- Het programma later vanuit dezelfde doelgroepselectie kunnen verspreiden.

## 5. Niet in de eerste versie

- Anonieme of openbare inschrijfformulieren met geheime links.
- Inschrijven zonder Rondo-account.
- Een expliciete reactie "wij doen niet mee".
- Herinneringen aan teams die nog niet zijn ingeschreven.
- Individuele spelers selecteren of een spelerslijst met persoonsgegevens aanleggen.
- Automatisch invullen of indienen van een formulier bij de externe toernooiorganisatie.
- Universele PDF-veldherkenning voor formulieren van verschillende organisatoren.
- Betalen in termijnen, gesplitste betalingen of meerdere betaallinks per inschrijving.
- Zelfbediening voor annuleringen, terugbetalingen of wijzigingen na betaling.
- Automatische teamindeling, samenvoegen van halve teams of wedstrijdplanning.
- Toernooien voor externe verenigingen die niet als Rondo-team bestaan.

## 6. Gebruikers en rechten

### 6.1 Toegewezen kaderlid

Een toegewezen kaderlid is een goedgekeurde Rondo-gebruiker met een gekoppeld persoonsrecord en een
actieve, teamgebonden kaderfunctie in `work_history`. Spelersrollen tellen niet als kaderfunctie.

Een toegewezen kaderlid mag:

- de toernooi-informatie bekijken;
- de gedeelde conceptinschrijving van het eigen team invullen en wijzigen;
- één of meer deelnemende teams opgeven;
- per deelnemend team het spelersaantal vastleggen;
- één contactpersoon voor de volledige Rondo-teaminschrijving vastleggen;
- de inschrijving definitief bevestigen;
- de betaalstatus en betaallink van de eigen inschrijving bekijken;
- de betaallink doorsturen aan iemand die namens het team betaalt.

Een toegewezen kaderlid mag niet:

- andere Rondo-teams of hun inschrijvingen bekijken;
- tarieven, deadlines of toernooi-informatie wijzigen;
- een negatieve reactie indienen;
- een definitieve inschrijving na betaling wijzigen;
- een betaalstatus handmatig aanpassen;
- andere gebruikers aan de opdracht toevoegen.

Toewijzing geeft alleen toegang tot die ene toernooi-teamcombinatie en verleent geen algemeen
toernooibeheer, financieel beheer of extra toegang tot persoonsrecords.

### 6.2 Toernooiplanner

Een toernooiplanner is een goedgekeurde gebruiker van wie het gekoppelde persoonsrecord een actieve
kaderfunctie `Coördinator toernooien` in `work_history` bevat. Administrators hebben altijd dezelfde
beheertoegang. De server bepaalt dit via één centrale `can_manage_tournaments()`-controle; er komt
geen afzonderlijke, handmatig toe te wijzen Rondo-rol of capability.

Een toernooiplanner mag:

- toernooien aanmaken, wijzigen, publiceren, sluiten en archiveren;
- leeftijdslagen en individuele teams selecteren;
- de automatisch gevonden kaderleden per team controleren;
- toegewezen kaderleden met een actief account toevoegen of verwijderen vóór publicatie;
- na publicatie een opdracht handmatig herverdelen;
- alle inschrijvingen, contactpersonen en betaalstatussen van het toernooi bekijken;
- een mislukte betaallink opnieuw laten aanmaken;
- een onbetaalde inschrijving heropenen voor correctie;
- handmatig een betaalherinnering versturen;
- de externe-inschrijfstatus beheren;
- totalen en detailregels exporteren;
- een programma uploaden en de programmamail versturen;
- de volledige activiteitengeschiedenis bekijken.

De kaderfunctie geeft geen algemeen personenbeheer of recht om betaalde facturen als betaald,
onbetaald of vervallen te markeren. Die handelingen blijven onder de bestaande financiële rechten.

### 6.3 Financieel beheerder en administrator

Gebruikers met bestaande financiële rechten kunnen de toernooifacturen in het financiële overzicht
zien en volgens de bestaande factuurregels beheren. Zij kiezen in **Financiële instellingen →
Mollie** de verplichte standaardrekening voor alle nieuwe toernooifacturen. Administrators hebben
daarnaast alle rechten van de toernooiplanner.

## 7. Hoofdproces

```text
Concepttoernooi
  -> teams en kaderleden selecteren
  -> publiceren en eenmaal toewijzen
  -> kaderlid vult gedeeld concept in
  -> kaderlid bevestigt positieve inschrijving
  -> Rondo maakt factuur en Mollie-betaallink
  -> betaling open, met uitsluitend betaalherinneringen
  -> Mollie-webhook bevestigt betaling
  -> planner schrijft totalen extern in
  -> planner verstuurt programma
```

### 7.1 Toernooi aanmaken

De planner legt minimaal vast:

- naam en editie, bijvoorbeeld `Kerst Zaaltoernooi 2026`;
- organisator;
- locatie;
- algemene toernooidatum of meerdere data;
- interne inschrijfdeadline;
- betaaldeadline, standaard gelijk aan de interne inschrijfdeadline;
- externe deadline;
- algemene uitleg en praktisch bericht;
- eventueel goed doel of aanvullende motivatie;
- programma-informatie per leeftijdslaag;
- tariefregels per leeftijdslaag;
- spelvorm per leeftijdslaag;
- doelgroep van teams;
- betaalherinneringsmomenten;
- verantwoordelijke toernooiplanner.

Een tariefregel bevat een minimum- en maximumleeftijd, bedrag per deelnemend team en toelichting.
Voor het voorbeeldtoernooi kunnen dit zijn:

| Leeftijd | Bedrag per team | Spelvorm |
|---|---:|---|
| O6 t/m O7 | EUR 28,00 | 4 tegen 4, zonder doelverdediger |
| O8 t/m O20 | EUR 48,00 | 5 tegen 5, inclusief doelverdediger |

De exacte tarieven worden per toernooieditie opgeslagen en nooit uit een algemene hardcoded tabel
gelezen. Daardoor kunnen bedragen jaarlijks wijzigen zonder historische inschrijvingen te veranderen.

### 7.2 Teams en kaderleden selecteren

De planner selecteert één of meer leeftijdslagen, bijvoorbeeld O6 tot en met O19. Rondo toont
vervolgens alle actieve teams in die scope. De planner kan individuele teams uit de selectie halen
of toevoegen.

Voor ieder geselecteerd team bepaalt Rondo de actieve kaderleden uit dezelfde brongegevens als de
Kaderlijst:

- de functie is actueel op de publicatiedatum;
- de functie is direct aan het geselecteerde team gekoppeld;
- de functie is geen spelersrol;
- het persoonsrecord is geen oud-lid;
- de persoon heeft een gekoppeld, goedgekeurd en actief Rondo-account.

De planner krijgt vóór publicatie een controleoverzicht met per team de gevonden kaderleden,
functie, e-mailadres en accountstatus. Een team zonder toewijsbaar kaderlid blokkeert publicatie
voor dat team. De planner kan zo'n team uit de doelgroep halen of een ander actief, teamgebonden
kaderlid met account selecteren.

Bij publicatie maakt Rondo per toernooi-teamcombinatie precies één opdracht aan en legt het een
snapshot vast van de toegewezen gebruikers, personen, teamnaam, leeftijdslaag en tariefregel.
Latere wijzigingen in `work_history` veranderen een gepubliceerde opdracht niet stilzwijgend. De
planner kan de toewijzing handmatig synchroniseren of herverdelen; iedere wijziging wordt gelogd.

Publicatie wordt geblokkeerd wanneer financieel beheer nog geen bruikbare standaard-Mollie-rekening
voor toernooien heeft ingesteld. Zo ontstaat nooit een open inschrijfopdracht waarvan de betaling
bij bevestiging bij voorbaat niet kan worden aangemaakt.

### 7.3 Initiële toewijzing

Na publicatie:

- verschijnt de opdracht bij ieder toegewezen kaderlid onder **Mijn toernooien**;
- ontvangt ieder toegewezen kaderlid eenmaal een e-mail met de toernooi-informatie en een normale
  Rondo-link naar de opdracht;
- opent de link na authenticatie dezelfde gedeelde opdracht voor dat team;
- ontvangt niemand later een herinnering om alsnog in te schrijven.

Het ontbreken van een inschrijving blijft zichtbaar voor de planner, maar start geen e-mailproces.

### 7.4 Gedeelde conceptinschrijving

Alle toegewezen kaderleden van hetzelfde Rondo-team zien en bewerken hetzelfde concept. Het openen
of tussentijds opslaan van het formulier geldt niet als inschrijving en blijft voor de planner
functioneel **Niet ingeschreven**.

Het formulier toont bovenaan:

- naam, datum en locatie van het toernooi;
- de interne deadline;
- informatie voor de leeftijdslaag van het Rondo-team;
- het bedrag per deelnemend team;
- de relevante spelvorm;
- algemene uitleg en eventuele instructies over het samenvoegen van spelers.

Het kaderlid kiest het aantal deelnemende teams. Voor ieder deelnemend team verschijnt één regel met:

| Veld | Verplicht | Toelichting |
|---|---:|---|
| Deelteam | Automatisch | Bijvoorbeeld `O15-1 · team 1` en `O15-1 · team 2` |
| Aantal spelers | Ja | Positief geheel getal |

Onder de deelnemende teams legt het kaderlid eenmaal de gezamenlijke contactpersoon vast:

| Veld | Verplicht | Toelichting |
|---|---:|---|
| Naam contactpersoon | Ja | Contactpersoon voor alle deelnemende teams binnen deze inschrijving |
| E-mailadres contactpersoon | Ja | Voor het latere programma en wijzigingen |
| Mobiel nummer contactpersoon | Ja | Voor praktische bereikbaarheid rond het toernooi |

Rondo berekent en toont het totale aantal teams, het opgetelde aantal spelers en het totaalbedrag
voordat het kaderlid bevestigt.

Er is uitsluitend een knop **Inschrijving bevestigen**. Er is geen knop voor afmelden of geen
deelname. Minimaal één deelnemend team is vereist om te kunnen bevestigen.

### 7.5 Definitief inschrijven en betalen

Bij bevestiging voert Rondo de volgende stappen uit:

1. Controleer opnieuw dat de gebruiker aan de opdracht is toegewezen.
2. Controleer de deadline, velden en actuele versie van het gedeelde concept.
3. Bereken het bedrag server-side vanuit het aantal deelnemende teams en de vastgelegde tariefregel.
4. Sla een onveranderlijk snapshot op van aantallen, contactpersonen, tarief en totaalbedrag.
5. Markeer de opdracht als **Ingeschreven, betaling open**.
6. Maak precies één `rondo_invoice` van type `tournament` aan en koppel die aan de opdracht.
7. Maak via de Mollie Payment Links API een blijvende betaallink aan.
8. Toon de betaallink aan alle toegewezen kaderleden en verstuur de betaalmail eenmaal.

De Mollie-omschrijving bevat minimaal:

```text
Kerst Zaaltoernooi 2026 · O15-1 · 2 teams · 12 spelers
```

De factuur bevat één regel per deelnemend team, bijvoorbeeld `Inschrijving O15-1 · team 1`, met het
vastgelegde tarief. Het gekoppelde persoonsrecord van het bevestigende kaderlid is de primaire
factuurontvanger. De betaalmail gaat naar alle toegewezen kaderleden, zodat de betaalactie niet
afhankelijk is van één persoon. Mollie registreert wie de link daadwerkelijk betaalt voor zover de
provider die gegevens beschikbaar stelt.

Registratieopslag en de externe Mollie-aanroep vormen bewust twee stappen. Als Mollie tijdelijk
niet bereikbaar is, blijft de inschrijving geldig en krijgt zij de betaalstatus
**Betaallink mislukt**. Rondo maakt nooit stilzwijgend een tweede inschrijving of factuur. Een
geautoriseerde gebruiker kan de idempotente aanmaak van de betaallink opnieuw proberen.

### 7.6 Betaalbevestiging

De bestaande openbare Mollie-webhook haalt de payment link opnieuw bij Mollie op en accepteert
alleen een door Mollie bevestigde betaling. Daarna:

- wordt de gekoppelde toernooifactuur betaald gemarkeerd;
- toont de teamopdracht **Ingeschreven en betaald**;
- worden betaaldatum, betaalmethode en Mollie-dashboardlink volgens de bestaande financiële regels
  opgeslagen;
- stoppen toekomstige betaalherinneringen onmiddellijk;
- blijft een dubbele webhook zonder neveneffecten.

De toernooiplanner ziet de betaalstatus, maar kan die niet zelf aanpassen.

### 7.7 Betaalherinneringen

Betaalherinneringen gelden alleen voor opdrachten die:

- definitief zijn ingeschreven;
- een openstaande, bruikbare betaallink hebben;
- nog niet betaald of vervallen zijn;
- het ingestelde herinneringsmoment hebben bereikt.

Een toernooi heeft configureerbare momenten ten opzichte van de betaaldeadline, standaard zeven en
twee dagen vóór de deadline. Per moment wordt maximaal één herinnering verstuurd. De planner kan
daarnaast vanuit één openstaande inschrijving handmatig een betaalherinnering versturen.

Iedere betaalherinnering gaat naar de actuele toegewezen kaderleden, bevat de betaallink, het
totaalbedrag, de aantallen en de betaaldeadline en wordt in de activiteitengeschiedenis vastgelegd.
Rondo verstuurt geen betaalherinnering aan teams zonder definitieve inschrijving en gebruikt deze
mailstroom nooit als indirecte inschrijfherinnering.

### 7.8 Wijzigen en corrigeren

Een concept kan tot de deadline door ieder toegewezen kaderlid worden gewijzigd. Na definitieve
bevestiging is de inschrijving voor kaderleden alleen-lezen.

De planner kan een definitieve inschrijving alleen heropenen wanneer de betaling nog niet is
ontvangen. Rondo archiveert dan de bestaande Mollie-betaallink, laat de gekoppelde factuur volgens
de bestaande financiële regels vervallen en zet de opdracht terug naar concept. Een nieuwe
bevestiging maakt een nieuwe factuur en betaallink met een nieuwe snapshot.

Een betaalde inschrijving kan in de eerste versie niet worden heropend. Een correctie, terugbetaling
of aanvullende betaling wordt door de planner en financieel beheerder buiten deze selfserviceflow
afgehandeld en als activiteit bij de inschrijving genoteerd. Betaalde records worden nooit
overschreven of verwijderd.

### 7.9 Externe inschrijving

De planner beheert per toernooi een afzonderlijke voortgangsstatus:

| Status | Betekenis |
|---|---|
| Nog niet verwerkt | De interne inschrijvingen zijn nog niet extern overgenomen |
| Ingediend bij organisatie | De aantallen zijn doorgegeven |
| Bevestigd door organisatie | De organisator heeft de inschrijving bevestigd |

De status geldt voor het hele toernooi. De planner kan daarnaast per Rondo-team een korte interne
notitie opslaan wanneer de externe organisatie een uitzondering of correctie meldt.

### 7.10 Programma verspreiden

De planner kan na ontvangst een programmabestand uploaden of een programmalink vastleggen. Voor
verzending toont Rondo een voorbeeld van de doelgroep en ongeldige of ontbrekende e-mailadressen.

De programmamail gaat uitsluitend naar de unieke, geldige e-mailadressen van:

- alle gebruikers die op dat moment zijn toegewezen aan een definitief ingeschreven Rondo-team;
- de ene contactpersoon van iedere definitieve inschrijving.

Niet-ingeschreven teams ontvangen geen programmamail.

Adressen worden ontdubbeld. De planner verstuurt de programmamail handmatig; er is geen automatische
verzenddatum in de eerste versie. Onderwerp, bericht, bestand of link, verzendtijd en resultaten
worden als snapshot opgeslagen.

## 8. Planner-overzicht

### 8.1 Toernooienlijst

Het menu-item **Toernooien** toont:

- naam en editie;
- interne deadline;
- externe deadline;
- aantal geselecteerde Rondo-teams;
- aantal ingeschreven Rondo-teams;
- totaal aantal deelnemende teams;
- openstaand en betaald bedrag;
- status: concept, open, gesloten of gearchiveerd.

### 8.2 Toernooidetail

Het detail bestaat uit drie tabs:

1. **Overzicht** — totalen, deadlines en externe voortgang.
2. **Teams en betalingen** — de operationele tabel.
3. **Communicatie** — initiële mail, betaalherinneringen en programma.

De tabel **Teams en betalingen** bevat minimaal:

| Kolom | Inhoud |
|---|---|
| Leeftijdslaag | Bijvoorbeeld O15 |
| Rondo-team | Bijvoorbeeld O15-1 |
| Toegewezen kaderleden | Namen en account/e-mailstatus |
| Inschrijving | Niet ingeschreven, betaling open, betaallink mislukt of betaald |
| Deelnemende teams | Aantal binnen de inschrijving |
| Spelers | Totaal en uitklapbare verdeling per deelnemend team |
| Contactpersoon | Eén naam, e-mail en mobiel voor de volledige Rondo-teaminschrijving |
| Bedrag | Vastgelegd totaalbedrag |
| Betaling | Open, betaald of vervallen; met betaaldatum indien bekend |
| Laatste betaalmail | Datum en type van de laatste betaalmail |
| Interne notitie | Alleen zichtbaar voor planners |

De tabel kan worden gefilterd op leeftijdslaag, inschrijfstatus en betaalstatus. Niet-ingeschreven
teams blijven zichtbaar, maar hebben geen bedrag, betaling of contactpersonen.

### 8.3 Totalen

Rondo toont voor het hele toernooi en per leeftijdslaag:

- aantal geselecteerde Rondo-teams;
- aantal ingeschreven Rondo-teams;
- aantal deelnemende teams;
- aantal spelers;
- te ontvangen bedrag;
- ontvangen bedrag;
- openstaand bedrag;
- aantal openstaande betalingen.

### 8.4 Export

De planner kan een CSV en een printvriendelijk PDF-overzicht downloaden met:

- toernooigegevens en deadlines;
- totalen per leeftijdslaag;
- per Rondo-team het aantal deelnemende teams en spelers;
- per Rondo-teaminschrijving de gezamenlijke contactpersoon;
- bedrag en betaalstatus;
- datum van de laatst verwerkte betaling.

De export is bedoeld om een extern formulier betrouwbaar over te nemen en vormt geen automatische
indiening bij de organisator.

## 9. Mijn toernooien

Toegewezen kaderleden krijgen een pagina **Mijn toernooien**. De pagina toont alleen eigen
opdrachten en vermeldt per opdracht:

- toernooi en Rondo-team;
- datum en locatie;
- interne deadline;
- status;
- totaalbedrag na inschrijving;
- betaallink zolang de betaling openstaat;
- betaaldatum nadat de betaling is bevestigd.

Wanneer meerdere toegewezen kaderleden dezelfde opdracht openen, zien zij elkaars opgeslagen
concept. Opslaan gebruikt optimistische versiecontrole. Als iemand een verouderde versie probeert
op te slaan, weigert Rondo de overschrijving en toont het de actuele gegevens.

## 10. Gegevensmodel

Alle persistente gegevens gebruiken WordPress-contenttypes, postmeta, media, comments en de native
Rondo-veldlaag. Er worden geen custom databasetabellen toegevoegd.

### 10.1 `rondo_tournament`

Privé contenttype voor één toernooieditie.

| Veld | Type | Toelichting |
|---|---|---|
| `name` | Titel | Bijvoorbeeld Kerst Zaaltoernooi 2026 |
| `organizer` | Tekst | Externe organisator |
| `location` | Tekst | Algemene locatie |
| `tournament_dates` | Repeater | Datum, locatie en leeftijdsscope |
| `internal_deadline` | Datum | Laatste dag om intern in te schrijven; geldig tot het einde van die dag |
| `payment_deadline` | Datum | Basis voor betaalherinneringen |
| `external_deadline` | Datum | Deadline van organisator; geldig tot het einde van die dag |
| `description` | Rich text | Algemene uitnodiging en praktische informatie |
| `charity_information` | Rich text | Optionele informatie over goed doel |
| `pricing_rules` | Repeater | Leeftijd van/tot, bedrag per team, spelvorm en toelichting |
| `target_team_ids` | Relaties | Geselecteerde Rondo-teams |
| `payment_reminder_days` | Repeater | Dagen vóór de betaaldeadline; standaard 7 en 2 |
| `planner_user_ids` | Gebruikers | Verantwoordelijke planners |
| `status` | Keuze | `draft`, `open`, `closed`, `archived` |
| `published_at` | Datum/tijd | Eerste publicatie |
| `published_by_user_id` | Gebruiker | Actor van publicatie |
| `external_status` | Keuze | `not_processed`, `submitted`, `confirmed` |
| `external_status_changed_at` | Datum/tijd | Laatste voortgangswijziging |
| `program_attachment_id` | Media | Optioneel programmabestand |
| `program_url` | URL | Optionele externe programmalink |
| `program_message` | Rich text | Tekst van programmamail |
| `program_sent_at` | Datum/tijd | Laatste programmaverzending |

Publicatie bevriest doelgroep, uitnodigingstekst, tariefregels en spelvormen voor bestaande
opdrachten. Een planner kan tekstuele correcties aan toekomstige programmaberichten blijven maken,
maar prijswijzigingen vereisen een nieuwe toernooieditie zolang er al opdrachten gepubliceerd zijn.

### 10.2 `rondo_tourn_entry`

Privé contenttype voor precies één combinatie van toernooi en Rondo-team. Het record wordt bij
publicatie als opdracht aangemaakt, ook wanneer het team uiteindelijk niets indient.

| Veld | Type | Toelichting |
|---|---|---|
| `tournament_id` | Relatie | Verplicht toernooi |
| `team_id` | Relatie | Verplicht Rondo-team |
| `team_name_snapshot` | Tekst | Historische teamnaam |
| `age_group_snapshot` | Tekst | Bijvoorbeeld O15 |
| `assigned_user_ids` | Gebruikers | Actuele toegewezen accounts |
| `assigned_person_ids_snapshot` | Personen | Oorspronkelijke personen bij publicatie |
| `assignment_snapshot` | Repeater | Gebruiker, persoon, naam, functie en e-mail bij publicatie |
| `registration_status` | Keuze | `open` of `submitted` |
| `draft_team_entries` | Repeater | Bewerkbaar concept vóór bevestiging |
| `submitted_team_entries` | Repeater | Onveranderlijk inschrijfsnapshot |
| `contact_name` | Tekst | Eén gezamenlijke contactpersoon voor de inschrijving |
| `contact_email` | E-mail | E-mailadres van de gezamenlijke contactpersoon |
| `contact_mobile` | Telefoon | Mobiel nummer van de gezamenlijke contactpersoon |
| `registered_team_count` | Getal | Afgeleid en vastgelegd bij bevestiging |
| `player_count` | Getal | Afgeleid en vastgelegd bij bevestiging |
| `price_per_team` | Bedrag | Tariefsnapshot |
| `total_amount` | Bedrag | Server-side berekend snapshot |
| `submitted_at` | Datum/tijd | Definitieve inschrijftijd |
| `submitted_by_user_id` | Gebruiker | Bevestigend kaderlid |
| `invoice_id` | Relatie | Gekoppelde toernooifactuur |
| `payment_state` | Keuze | `not_applicable`, `creating`, `open`, `error`, `paid`, `expired` |
| `last_payment_email_at` | Datum/tijd | Laatste betaalmail |
| `payment_reminder_log` | Repeater | Moment, type, actor en resultaat |
| `planner_note` | Tekst | Interne operationele notitie |
| `version` | Getal | Optimistische versiecontrole |

De combinatie `tournament_id` en `team_id` is logisch uniek. De aanmaakroute controleert deze
uniciteit vóór `wp_insert_post()` en hergebruikt bij herhaalde verzoeken idempotent hetzelfde record.

### 10.3 Deelnemend team binnen een inschrijving

Zowel `draft_team_entries` als `submitted_team_entries` gebruiken dezelfde repeaterstructuur:

| Veld | Type | Toelichting |
|---|---|---|
| `sequence` | Getal | Stabiel volgnummer binnen het Rondo-team |
| `display_name` | Tekst | Automatisch afgeleid, bijvoorbeeld O15-1 · team 2 |
| `player_count` | Getal | Positief geheel getal |

### 10.4 Toernooifactuur

De bestaande `rondo_invoice` krijgt het type `tournament`:

- factuurnummerprefix `O` voor toernooi;
- één factuur per definitieve `rondo_tourn_entry`;
- `_tournament_entry_id` als onveranderlijke koppeling;
- `_mollie_description` als snapshot van toernooi, team, aantallen en spelers;
- de verplichte standaard-Mollie-rekening voor toernooien uit **Financiële instellingen → Mollie**;
- het bij factuurcreatie vastgelegde rekening-ID blijft op die factuur staan als de globale
  standaardrekening later wijzigt;
- bestaande betaling-, webhook-, PDF-, verval- en auditlogica blijft leidend.

De factuur en de entry dupliceren alleen de financiële snapshot die nodig is om historie en
webhookverwerking zelfstandig controleerbaar te houden. De factuur is de bron voor betaalstatus;
de entry exposeert een permission-filtered afgeleide status aan planners en toegewezen kaderleden.

### 10.5 Activiteitengeschiedenis

Gebruik WordPress-comments met een apart, niet-publiek activiteitstype. Leg minimaal vast:

- toernooi gepubliceerd;
- opdracht aangemaakt of herverdeeld;
- concept gewijzigd;
- inschrijving bevestigd;
- factuur en betaallink aangemaakt of mislukt;
- betaalmail of herinnering verstuurd of mislukt;
- Mollie-betaling bevestigd;
- onbetaalde inschrijving heropend;
- externe status gewijzigd;
- programma verstuurd.

Iedere activiteit bevat actor, tijdstip, entry of toernooi, actie, relevante voor/na-status en een
beperkte technische context zonder API-sleutels of andere geheimen.

## 11. Statusmodel

### 11.1 Gebruikersstatus

| Situatie | Label voor kaderlid | Label voor planner |
|---|---|---|
| Geen definitieve inschrijving | Nog niet ingeschreven | Niet ingeschreven |
| Definitief, link wordt gemaakt | Ingeschreven, betaling voorbereiden | Betaling voorbereiden |
| Definitief, Mollie-fout | Ingeschreven, betaallink niet beschikbaar | Betaallink mislukt |
| Definitief, betaling open | Ingeschreven, betaling open | Betaling open |
| Mollie bevestigd | Ingeschreven en betaald | Betaald |
| Onbetaalde factuur vervallen | Neem contact op met de toernooiplanner | Betaling vervallen |

Een opgeslagen concept krijgt bewust geen aparte plannerstatus: zolang niet is bevestigd, is het
team operationeel **Niet ingeschreven**.

### 11.2 Toernooistatus

| Status | Gedrag |
|---|---|
| `draft` | Alleen planners; instellingen en doelgroep bewerkbaar |
| `open` | Toegewezen kaderleden kunnen concepten invullen en bevestigen |
| `closed` | Geen nieuwe inschrijvingen; planner kan verwerken, exporteren en communiceren |
| `archived` | Alleen-lezen historie |

Na de interne deadline gedraagt een open toernooi zich voor kaderleden als gesloten. Een planner kan
de deadline bewust verlengen; de wijziging wordt gelogd en wijzigt geen financiële snapshots.

## 12. REST-API en autorisatie

De module gebruikt een eigen controller onder `/rondo/v1` en schakelt generieke WordPress REST-
toegang voor de twee contenttypes uit.

Voorgestelde routes:

| Methode en route | Gebruik |
|---|---|
| `GET /tournaments` | Plannerlijst of permission-filtered lijst voor huidige gebruiker |
| `POST /tournaments` | Concepttoernooi aanmaken |
| `GET /tournaments/{id}` | Detail volgens rol en veldrechten |
| `PATCH /tournaments/{id}` | Instellingen wijzigen als planner |
| `POST /tournaments/{id}/preview-assignments` | Teams en toewijsbare kaderaccounts controleren |
| `POST /tournaments/{id}/publish` | Opdrachten maken en initiële mail sturen |
| `GET /tournaments/{id}/entries` | Planner-overzicht en totalen |
| `GET /tournament-entries/mine` | Eigen opdrachten van ingelogde gebruiker |
| `GET /tournament-entries/{id}` | Eigen gedeelde opdracht of plannerdetail |
| `PATCH /tournament-entries/{id}/draft` | Concept opslaan met verwachte versie |
| `POST /tournament-entries/{id}/submit` | Positief inschrijven en betaling starten |
| `POST /tournament-entries/{id}/retry-payment-link` | Idempotente herstelactie |
| `POST /tournament-entries/{id}/payment-reminder` | Handmatige betaalherinnering door planner |
| `POST /tournament-entries/{id}/reopen` | Alleen onbetaalde inschrijving heropenen |
| `POST /tournaments/{id}/external-status` | Externe voortgang wijzigen |
| `GET /tournaments/{id}/export.csv` | Plannerexport |
| `GET /tournaments/{id}/export.pdf` | Printvriendelijk planner-overzicht |
| `POST /tournaments/{id}/program` | Programma opslaan en/of versturen |

Iedere entry-route controleert server-side of de huidige gebruiker in `assigned_user_ids` staat,
administrator is of via `can_manage_tournaments()` een actieve kaderfunctie
`Coördinator toernooien` heeft. De frontendstatus is nooit een autorisatiebeslissing. Responses aan
kaderleden bevatten geen andere teams, planner-notities, financiële dashboardlinks of e-mailstatus
van andere ontvangers.

## 13. E-mailstromen

Alle e-mail gaat via de bestaande `wp_mail()`-route en Lettermint-integratie.

### 13.1 Toewijzingsmail

Eenmalig bij publicatie of later afzonderlijk bij een nieuwe toewijzing. Bevat:

- toernooi, datum, locatie en leeftijdsinformatie;
- interne deadline;
- tarief en spelvorm;
- relevante toelichting;
- normale Rondo-link naar de opdracht.

Deze e-mail krijgt geen automatische herinnering.

### 13.2 Betaalmail

Eenmalig na definitieve inschrijving en succesvolle aanmaak van de betaallink. Bevat:

- Rondo-team;
- aantal deelnemende teams en spelers;
- bedrag;
- betaaldeadline;
- blijvende Mollie-betaallink;
- contactmogelijkheid voor correcties.

### 13.3 Betaalherinnering

Alleen voor ingeschreven, onbetaalde opdrachten. De mail gebruikt dezelfde financiële snapshot en
betaallink en vermeldt duidelijk dat de inschrijving al is ontvangen en alleen de betaling nog
openstaat.

### 13.4 Programmamail

Handmatig verstuurd naar de eerder beschreven gecombineerde doelgroep. Een verzendpreview toont
aantallen, ontbrekende adressen en ontdubbeling vóór definitieve verzending.

### 13.5 Afleverproblemen

Een individuele mislukte verzending blokkeert andere ontvangers niet. Resultaten worden per
ontvanger gelogd. Bounces volgen de bestaande Lettermint-afhandeling; de planner ziet in deze module
alleen een operationele waarschuwing en geen providergeheimen.

## 14. Validatie en bedrijfsregels

1. Eén toernooi-teamcombinatie heeft maximaal één actieve entry.
2. Alleen toegewezen gebruikers en planners mogen een entry lezen.
3. Alleen toegewezen gebruikers mogen het kaderconcept bevestigen; planners kunnen ondersteunen,
   maar niet stilzwijgend namens een team positief inschrijven zonder gelogde actor en reden.
4. Bevestiging vereist minimaal één deelnemend team.
5. Ieder deelnemend team vereist een spelersaantal; iedere volledige Rondo-teaminschrijving vereist
   precies één contactnaam, geldig e-mailadres en mobiel nummer.
6. Bedrag en totalen worden uitsluitend server-side berekend.
7. Leeftijdslaag en tarief komen uit de gepubliceerde assignment-snapshot.
8. Een tariefwijziging verandert nooit een bestaande opdracht, inschrijving of factuur.
9. Een definitieve inschrijving maakt idempotent maximaal één actieve factuur.
10. Een mislukte Mollie-aanroep maakt geen tweede factuur bij opnieuw proberen.
11. Alleen een door Mollie opnieuw opgehaalde en als betaald bevestigde link zet de factuur op betaald.
12. Een betaald record wordt nooit door een kaderlid of planner teruggezet.
13. Een conceptsave met een verouderd versienummer overschrijft geen nieuwere data.
14. Alle datums gebruiken de WordPress-sitezone `Europe/Amsterdam`; API-datetimes blijven RFC 3339
    met expliciete tijdzone.
15. Na de interne deadline zijn nieuwe conceptwrites en bevestigingen geblokkeerd, tenzij de planner
    de deadline heeft verlengd.
16. Programmaontvangers worden vlak vóór verzending opnieuw gevalideerd en ontdubbeld.
17. Geen enkele e-mail bevat Mollie API-sleutels, interne tokens of persoonsgegevens van andere teams.
18. Een toernooifactuur kan alleen worden gemaakt wanneer financieel beheer een bruikbare
    standaard-Mollie-rekening voor toernooien heeft ingesteld.

## 15. Randgevallen

### Meerdere kaderleden werken tegelijk

De eerste geldige bevestiging wint. Een tweede bevestiging is idempotent en toont de reeds
bevestigde inschrijving. Conceptwijzigingen gebruiken versiecontrole om stil verlies te voorkomen.

### Een kaderfunctie verandert na publicatie

De gepubliceerde opdracht blijft ongewijzigd totdat de planner expliciet herverdeelt of
synchroniseert. Zo verdwijnt een lopende opdracht niet zonder waarschuwing.

### Een toegewezen gebruiker wordt geblokkeerd

Die gebruiker verliest direct toegang door de bestaande accountcontrole. Andere toegewezen
kaderleden houden toegang. De planner ziet dat de opdracht een niet-actieve toewijzing bevat.

### Teamnaam of leeftijdslaag verandert

De gepubliceerde snapshot blijft voor het bestaande toernooi leidend. De actuele teamnaam kan naast
de snapshot als waarschuwing worden getoond, maar verandert tarief of historie niet.

### Mollie is tijdelijk niet bereikbaar

De inschrijving blijft opgeslagen met `payment_state=error`. Opnieuw proberen hergebruikt de entry
en factuur en maakt alleen een nieuwe payment link als er nog geen bruikbare link bestaat.

### Betaling komt vlak na een herinneringsselectie binnen

De verzendactie controleert de factuurstatus opnieuw direct vóór iedere mail. Een inmiddels betaalde
factuur ontvangt geen herinnering.

### Een betaalde inschrijving blijkt fout

De entry en factuur blijven onveranderd. Planner en financieel beheerder handelen correctie of
terugbetaling buiten de eerste selfserviceversie af en voegen een activiteit toe.

### Een team schrijft niet in

Er gebeurt niets. De planner ziet **Niet ingeschreven** en Rondo verstuurt geen vervolgbericht.

## 16. Acceptatiecriteria

### Toernooi en toewijzing

- [x] Een planner kan een concepttoernooi met deadlines, leeftijdsinformatie, tarieven en doelgroep
  aanmaken.
- [x] Rondo toont vóór publicatie per team de actuele, teamgebonden kaderleden met accountstatus.
- [x] Een team zonder toewijsbaar actief account kan niet ongemerkt worden gepubliceerd.
- [x] Publicatie maakt per geselecteerd team precies één gedeelde opdracht.
- [x] Alle toegewezen kaderleden krijgen toegang tot dezelfde opdracht en ontvangen eenmaal de
  initiële mail.
- [x] Niet-toegewezen gebruikers kunnen de entry niet via UI of REST lezen.

### Inschrijving

- [x] Het formulier biedt alleen een positieve inschrijving en geen afmeldmogelijkheid.
- [x] Het kaderlid kan één of meer deelnemende teams met ieder een eigen spelersaantal opgeven.
- [x] Iedere Rondo-teaminschrijving heeft precies één gezamenlijke contactpersoon, ongeacht het
  aantal deelnemende teams.
- [x] Een opgeslagen concept telt voor de planner als niet ingeschreven.
- [x] Bevestiging legt aantallen, contactpersonen, tarief en totaalbedrag als snapshot vast.
- [x] Het totaalbedrag wordt server-side berekend en kan niet vanuit de client worden gemanipuleerd.
- [x] Een verouderde gelijktijdige wijziging overschrijft geen nieuwere conceptdata.
- [x] Na de interne deadline kan een kaderlid niet meer bevestigen.

### Betaling

- [x] Een definitieve inschrijving maakt precies één toernooifactuur en één blijvende Mollie-
  betaallink aan.
- [x] Iedere toernooifactuur gebruikt de in Financiële instellingen gekozen standaardrekening voor
  toernooien en bewaart die rekening als factuursnapshot.
- [x] De Mollie-omschrijving bevat toernooi, Rondo-team, aantal teams en aantal spelers.
- [x] Een Mollie-storing verliest de inschrijving niet en kan idempotent worden hersteld.
- [x] Alleen een geverifieerde Mollie-webhook zet de betaling op betaald.
- [x] De planner ziet de actuele betaalstatus zonder financiële mutatierechten te krijgen.
- [x] Betaalde inschrijvingen zijn voor kaderleden en planners financieel vergrendeld.

### Herinneringen en communicatie

- [x] Rondo verstuurt geen inschrijfherinneringen aan niet-ingeschreven teams.
- [x] Alleen definitief ingeschreven, onbetaalde teams ontvangen betaalherinneringen.
- [x] Iedere geplande herinnering wordt per entry maximaal eenmaal verstuurd.
- [x] Een betaling die vóór verzending binnenkomt voorkomt de herinnering.
- [ ] De programmamail bereikt alleen de unieke adressen van toegewezen kaderleden en de ene
  contactpersoon van definitief ingeschreven Rondo-teams.
- [ ] Niet-ingeschreven teams ontvangen geen programmamail.
- [ ] Verzendresultaten en mislukkingen zijn voor de planner controleerbaar.

### Overzicht en export

- [ ] Het planner-overzicht toont geselecteerde teams, inschrijvingen, aantallen, contactpersonen,
  bedragen en betaalstatus in één tabel.
- [ ] Totalen per leeftijdslaag en voor het hele toernooi sluiten exact aan op de definitieve
  inschrijvingen.
- [ ] CSV- en PDF-export bevatten voldoende informatie om het externe formulier over te nemen.
- [ ] Niet-ingeschreven teams blijven zichtbaar zonder dat zij als afgemeld worden weergegeven.

## 17. Teststrategie

### PHP/WPUnit

- toernooi- en entryveldcontracten, opslag en formatting;
- doelgroepselectie op actieve teamgebonden kaderfuncties;
- uitsluiten van spelersrollen, oud-leden en gebruikers zonder actief account;
- unieke toernooi-teamcombinaties en idempotente publicatie;
- REST-permissies voor toegewezen gebruiker, actieve Coördinator toernooien, administrator,
  financieel beheerder en buitenstaander;
- server-side tariefberekening en snapshotgedrag;
- deadline- en versieconflicten;
- idempotente factuur- en payment-linkaanmaak;
- Mollie-webhook voor onbekende, openstaande, betaalde en dubbele callbacks;
- heropenen van onbetaalde en blokkeren van betaalde inschrijvingen;
- selectie en deduplicatie van betaal- en programmaontvangers;
- planner-totalen en exportinhoud.

REST-routetests booten de relevante controllers expliciet met de bestaande testhelper.

### Frontend

- Mijn toernooien toont uitsluitend toegewezen opdrachten;
- dynamisch toevoegen en verwijderen van deelnemende teams;
- clientvalidatie naast verplichte servervalidatie;
- juiste totalen vóór bevestiging;
- conflictmelding bij een verouderd concept;
- correcte read-only status na bevestiging en betaling;
- plannerfilters, totalen en foutstatussen;
- toegankelijke toetsenbord- en formulierinteractie.

### Integratie en operationele test

- testmode-Mollie-link openen en betalen;
- webhook verwerkt de juiste factuur en entry;
- betaalherinnering gaat alleen naar een onbetaalde testentry;
- Lettermint registreert initiële mail, betaalmail en programmamail;
- CSV en PDF handmatig vergelijken met planner-overzicht;
- twee kaderaccounts van hetzelfde team bewerken dezelfde opdracht zonder dataverlies.

## 18. Implementatiemijlpalen

### Mijlpaal 1 — Toernooien, doelgroep en opdrachten

- contenttypes, veldcontracten en capabilities;
- toernooibeheer en tariefregels;
- selectie van teams en toewijsbare kaderaccounts;
- publicatie, assignmentsnapshot en initiële e-mail;
- Mijn toernooien en gedeelde conceptinschrijving;
- planner-overzicht met niet-ingeschreven en ingeschreven teams.

**Uitkomst:** de interne inventarisatie werkt volledig in Rondo, maar betaling en programma zijn nog
niet operationeel.

### Mijlpaal 2 — Factuur, Mollie en betaalherinneringen

- factuurtype `tournament` en nummering;
- server-side prijsberekening en financiële snapshots;
- blijvende Mollie-betaallink en webhookkoppeling;
- betaalmail, geplande betaalherinneringen en handmatige betaalherinnering;
- veilige correctieflow voor onbetaalde inschrijvingen;
- financiële en permission-tests.

**Uitkomst:** de planner kan inschrijving en betaling zelfstandig controleren; Rondo herinnert
alleen openstaande betalingen.

### Mijlpaal 3 — Externe verwerking, export en programma

- externe voortgangsstatus;
- totalen per leeftijdslaag;
- CSV- en PDF-export;
- programmabestand of -link;
- doelgroepvoorbeeld, deduplicatie en programmamail;
- volledige activiteitengeschiedenis en operationele documentatie.

**Uitkomst:** de hele interne toernooicyclus staat in Rondo; alleen de feitelijke invoer bij de
externe organisator blijft handmatig.

Alle drie mijlpalen zijn nodig voordat de module als eerste productierelease aan toernooiplanners
wordt aangeboden.

## 19. Documentatie bij implementatie

Bij implementatie worden minimaal de volgende ontwikkelaarsdocumenten toegevoegd of bijgewerkt in
`../developer/src/content/docs/`:

- featuredocumentatie voor Toernooien;
- REST-referentie voor alle toernooi- en entryroutes;
- autorisatiematrix voor planner en toegewezen kaderlid;
- financiële documentatie voor `invoice_type=tournament` en webhookrouting;
- operationele uitleg voor toewijzing, betaling, export en programmaverzending.

De gebruikersinterface moet daarnaast bij deadlines, betalingsfouten en vergrendelde inschrijvingen
voldoende uitleg geven, zodat een kaderlid geen ontwikkelaarsdocumentatie nodig heeft.

## 20. Eerste productierelease

De eerste live pilot gebruikt één werkelijk toernooi met een beperkte selectie teams en minimaal
twee kaderaccounts op hetzelfde team. Voor volledige uitrol worden gecontroleerd:

1. juiste toewijzing vanuit actuele kaderfuncties;
2. positieve inschrijving met meerdere deelnemende teams;
3. correcte prijs en Mollie-omschrijving;
4. echte testbetaling en automatische statusovergang;
5. geen inschrijfherinnering aan een niet-ingeschreven pilotteam;
6. één betaalherinnering aan een ingeschreven, onbetaald pilotteam;
7. correcte totalen en export;
8. programmamail uitsluitend naar toegewezen kaderleden en de gezamenlijke contactpersoon van
   definitief ingeschreven Rondo-teams.

Pas na deze echte ketentest wordt de module voor alle jeugdteams gebruikt.
