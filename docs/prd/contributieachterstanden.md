# Contributieachterstanden en opvolging

**Status:** Gedeeltelijk vastgesteld; de besluitstatus per onderwerp staat bij de beleidskeuzes. Nog niet geïmplementeerd.
**Datum:** 18 november 2026.
**Aanleiding:** Verzoek van Xander om inzicht, vaste aanmaningen, speelblokkades en ondersteuning bij betalingsproblemen.
**Volgorde:** Inzicht, automatische opvolging met hulpoptie, speelblokkades, uitgebreidere zelfservice.

## Doel en afbakening

Rondo geeft de penningmeester één betrouwbare werklijst voor achterstallige contributie en
voert de afgesproken opvolging uit. Leden kunnen betalen of hulp aanvragen. Trainers en
leiders krijgen alleen de informatie die zij nodig hebben om een clubbesluit uit te voeren.

Xander noemt voor 2026–2027 820 facturen: 606 betaald, 162 in termijnen en 52 open,
met 77,5% van het bedrag geïnd. Dit is de aangeleverde momentopname, geen live telling.
De 52 open facturen en de 162 termijnregelingen moeten op vervaldata worden beoordeeld:
een regeling kan achterlopen, terwijl een openstaand bedrag nog niet opeisbaar is.

Dit project omvat uitsluitend contributiefacturen. Boetes, handmatige facturen,
Businessclubfacturen en toernooien houden hun eigen verwerking. Automatisch royement,
automatische overdracht aan incasso en wijzigingen in Sportlink vallen buiten scope.

## Bestaande basis

De onderstaande onderdelen zijn aanwezig in de broncode van commit `7ddfd694`.
Hun aanwezigheid bewijst niet welke instellingen of verzendingen op productie actief zijn.

| Onderdeel | Bestaand gedrag | Uitbreiding |
|---|---|---|
| Betaalstatistieken | Ontvangsten, betaalstatussen en termijnregelingen | Achterstandsbedragen, uitsplitsingen en een exporteerbare werklijst |
| Factuurherinneringen | Voor contributie zonder gekozen plan: controle na 14 en 28 dagen sinds verzending | Instelbare opvolging vanaf de toepasselijke vervaldatum |
| Termijnherinneringen | Controle na 14 en 21 dagen achterstand | Dezelfde opvolgregels en dossierweergave als hele facturen |
| Betaalpagina | Planselectie zonder inloggen; 3 en 8 termijnen afhankelijk van instellingen | Hulpaanvraag en verzoek om een afwijkende afspraak |
| Achterstallige factuurstatus | Houdt rekening met onbetaalde termijnvervaldata | Eén berekening voor bedragen, opvolging en contributieblokkade |
| Financiële rechten | Leesrecht `financieel_read`, schrijfrecht `financieel` | Dezelfde controle voor werklijst, export, uitzonderingen en acties |

De bestaande herinneringspaden worden voor deelnemende contributiefacturen vervangen;
er komt geen tweede scheduler die dezelfde leden onafhankelijk aanschrijft.

## Begrippen en rekenregels

| Begrip | Definitie |
|---|---|
| Openstaand | Resterende hoofdsom na verwerkte betalingen en toegepaste correcties, inclusief toekomstige termijnen |
| Achterstallig | Onbetaald deel van de hoofdsom waarvan de toepasselijke vervaldatum voorbij is |
| Gemiste termijn | Een termijn met een resterend bedrag en een vervaldatum vóór vandaag |
| Ouderdom achterstand | Kalenderdagen sinds de oudste nog onbetaalde vervaldatum, in de clubtijdzone |
| Dossier | Opvolging bij één contributiefactuur; de persoonweergave kan meerdere dossiers samen tonen |
| Opvolgronde | Reeks stappen voor een afgebakende achterstand binnen een dossier |
| Contributieblokkade | Clubbesluit dat deelname beperkt wegens contributie; geen uitspraak over KNVB-speelgerechtigdheid |

Bedragen worden intern in gehele eurocenten berekend. Hoofdsom, administratiekosten en
eventuele incassokosten blijven afzonderlijk zichtbaar. Een betaling telt eenmaal mee,
ongeacht het aantal ontvangen betaalmeldingen. Geen negatieve achterstanden tonen.

Bij termijnen telt alleen het vervallen, onbetaalde gedeelte mee als achterstand. Een
gemiste termijn maakt toekomstige termijnen niet automatisch opeisbaar. Deelbetalingen,
bankbetalingen en creditcorrecties tellen pas na vastgelegde toewijzing aan de juiste
factuur en termijn; onduidelijke toewijzingen gaan naar de beoordelingslijst.

Concepten, geannuleerde facturen en losse creditfacturen zijn geen incassodossiers.
Een toegepaste credit vermindert wel de betreffende vordering. Ontbrekende vervaldata,
onvolledige betaalschema's of onverklaarde saldi krijgen de status **Controle nodig**;
ze mogen geen automatische aanmaning of blokkade veroorzaken.

## Fase 1 Inzicht en werklijst

Voeg aan **Financiën → Betaalstatistieken** een blok **Contributieachterstanden** toe,
met seizoenkeuze en totalen voor unieke leden, facturen, achterstallige hoofdsom en
openstaande hoofdsom. Toon daarnaast het aantal dossiers dat controle nodig heeft.
Een doorklik opent de werklijst binnen dezelfde financiële omgeving.

De standaardlijst toont dossiers met achterstand. Via filters zijn ook toekomstige
betalingen, gepauzeerde dossiers en dossiers zonder betrouwbare berekening zichtbaar.
De rij bevat:

- lid en factuurnummer met doorklik naar de bestaande detailpagina's;
- actuele spelende teams, leeftijdscategorie en jeugd of senioren;
- openstaande en achterstallige hoofdsom, met kosten afzonderlijk;
- oudste onbetaalde vervaldatum, dagen achterstand en aantal gemiste termijnen;
- aantal bevestigde herinneringsverzendingen, laatste verzending en huidige stap;
- volgende actie met datum, dossierbehandelaar en eventuele pauzereden.

Filter op seizoen, team, leeftijdscategorie, jeugd of senioren, ouderdom, gemiste
termijnen, opvolgstap en behandelaar. Gebruik de bestaande clubindeling voor leeftijd;
een ontbrekende indeling verschijnt als **Onbekend**, niet als een gegokte categorie.
Een ontbrekend spelend team verschijnt als **Geen team**.

Oude herinneringsregistraties zonder bevestigde verzenduitkomst blijven zichtbaar als
historie met onbekende uitkomst. Presenteer die niet als bewezen aflevering en gebruik
ze niet zonder beoordeling om een nieuwe ronde bij een zwaardere stap te laten beginnen.

Bij meerdere teams is het lid in ieder toepasselijk teamfilter vindbaar. Het clubtotaal
telt facturen en bedragen eenmaal. Teamtotalen worden expliciet als overlappend getoond
en mogen niet als optelbaar clubtotaal worden gepresenteerd.

CSV-export bevat alle gefilterde rijen, ook buiten de huidige pagina, en vermeldt seizoen
en peildatum. Exporteer geen betaaltokens, provider-ID's of vrije hulpnotities. Neutraliseer
formule-invoer in tekstcellen zodat namen en opmerkingen geen spreadsheetformules worden.

### Acceptatiecriteria fase 1

- [ ] Een factuur van €300 met €100 betaald, €100 vervallen en €100 toekomstig toont €200 openstaand en €100 achterstallig.
- [ ] Een regeling zonder gemiste termijn staat niet in de standaardachterstandslijst.
- [ ] Deelbetalingen en toegepaste credits verminderen het juiste saldo zonder dubbeltelling.
- [ ] Leden zonder team of leeftijdscategorie verdwijnen niet uit totalen of export.
- [ ] Twee teams bij hetzelfde lid verdubbelen de clubtotalen niet.
- [ ] CSV, schermtotalen en factuurdetails sluiten op dezelfde peildatum op elkaar aan.
- [ ] Onvolledige gegevens zijn zichtbaar als controlewerk en veroorzaken geen geschatte achterstand.
- [ ] Gebruikers zonder financieel leesrecht krijgen ook via rechtstreekse API-aanroepen geen lijst of export.

## Fase 2 Automatische opvolging en hulp

### Opvolgstappen en vastgestelde timing

De beginwaarden voor de herinneringsintervallen zijn op 18 november 2026 door Joost
vastgesteld: 7 dagen na de vervaldatum, vervolgens telkens 14 dagen na bevestigde
verzending van de vorige stap. Ze gelden zolang achterstand resteert en het dossier
niet gepauzeerd is. De overige voorwaarden voor de veertiendagenbrief blijven gelden.
De penningmeester kan de intervallen instellen; iedere nieuwe ronde bewaart de gebruikte
beleidsversie. Wijzigingen schuiven lopende rondes niet stilzwijgend naar voren.

| Stap | Start en voorwaarden | Resultaat |
|---|---|---|
| Herinnering | 7 dagen na de toepasselijke vervaldatum | Vriendelijke uitleg, actuele betaallink en hulpoptie |
| Aanmaning | Minimaal 14 dagen na bevestigde verzending van de herinnering | Nieuwe betaaloproep en uitleg van mogelijke vervolgstappen |
| Veertiendagenbrief | Minimaal 14 dagen na bevestigde verzending van de aanmaning | Goedgekeurde brief voor de vastgelegde vordering, na controle van ontvanger en ontvangstproces |
| Beoordeling | Nadat de geldige brieftermijn volledig verstreken is en achterstand resteert | Werkvoorraad voor persoonlijk contact, speelblokkade of incassobeoordeling |

Een datum op zichzelf geeft geen toestemming voor een volgende stap. Vlak voor elke
actie worden saldo, vervaldata, pauze, ontvanger en eerdere verzending opnieuw gelezen.
Bij een storing worden stappen niet ingehaald met meerdere mails kort achter elkaar;
het volgende interval begint bij de daadwerkelijk bevestigde vorige verzending.

Iedere mail bevat de toepasselijke achterstand, betaalmogelijkheid en contact- of
hulpoptie. De ontvanger kan via Mollie betalen zonder een Rondo-account te moeten maken.
De link moet het actuele verschuldigde bedrag tonen en mag toekomstige termijnen niet
zonder geldige afspraak vervroegen. Gebruik de bestaande publieke betaalpagina en
Mollie Payment Links; lang bruikbare mails krijgen geen kortlevende payment-URL.

### Rondes en gemiste termijnen

Een factuur heeft maximaal één actieve opvolgronde. Vóór de veertiendagenbrief worden
nieuwe gemiste termijnen in het actuele achterstandsoverzicht verwerkt, zonder de
lopende wachttijd te verkorten of voor elke termijn een aparte mailreeks te starten.

De veertiendagenbrief legt de betrokken termijnnummers, bedragen en vordering vast.
Latere achterstanden mogen niet achteraf onder die brief worden gebracht. Ze blijven
zichtbaar en krijgen zo nodig een eigen latere ronde met volledige termijnen. Bij
samenloop beoordeelt de penningmeester de vervolgstap; het systeem stapelt geen
incassokosten of parallelle brieven automatisch op.

Een betaling vermindert eerst de vastgelegde, toegewezen achterstand. Als de vordering
van de ronde is voldaan, sluit die ronde. Een eventuele nieuwe achterstand blijft
afzonderlijk zichtbaar. De factuur blijft open zolang nog toekomstige termijnen bestaan.

### Veertiendagenbrief en incassokosten

De termijn van een veertiendagenbrief begint op de dag na ontvangst. Alleen de
verzenddatum is daarom onvoldoende om de einddatum vast te stellen. Deelbetalingen
en hun ontvangstdatum zijn relevant voor de verdere beoordeling van kosten.
Zie de [aanbeveling van Rechtspraak over de veertiendagenbrief](https://www.rechtspraak.nl/voor-advocaten-en-juristen/reglementen-procedures-en-formulieren/civiel/tarieven-kosten-vergoedingen/aanbeveling-14-dagenbrief).

Op 18 november 2026 is gekozen voor een eenmalige juridische toets van de standaardbrief
en de manier van versturen, inclusief de registratie van ontvangst, vóór activering.
Die toets moet vaststellen of deze werkwijze geschikt is om later incassokosten voor
de contributievordering te kunnen rekenen. Na goedkeuring verstuurt Rondo de brief
automatisch bij dossiers die aan de voorwaarden voldoen; afzonderlijke goedkeuring
van iedere brief is niet vereist. De juridische toets zelf is nog niet uitgevoerd.

Per dossier moet de identiteit van de betalingsplichtige zijn vastgesteld.
Een oudercontact is niet automatisch de juridisch betalingsplichtige.
Onzekerheid over ontvanger of ontvangst leidt tot **Controle nodig**. Een technisch
afgeleverde e-mail bewijst op zichzelf niet dat aan alle vereisten is voldaan.

Bewaar de exacte brief, de genoemde vordering, ontvanger, verzendgegevens en onderbouwing
van de ontvangst en deadline. De wettelijke minimumtermijn is niet verkortbaar via een
instelling. Het berekenen van een kostenvoorstel vereist eerst vastgelegde, getoetste
regels voor onder meer deelbetalingen en opeenvolgende gemiste termijnen. Incassokosten
zijn geen voorwaarde voor het aflopen van de gewone herinnerings- en aanmaningsstappen.

### Incassokosten goedkeuren voor meerdere dossiers

Op 18 november 2026 is vastgesteld dat de penningmeester incassokosten expliciet moet
goedkeuren, met de mogelijkheid om meerdere dossiers in één handeling goed te keuren.
Rondo voegt geen incassokosten toe op basis van alleen een verstreken termijn.

De werklijst biedt selectievakjes en de actie **Incassokosten goedkeuren**. De
penningmeester kan afzonderlijke dossiers, een pagina of expliciet alle gefilterde
dossiers selecteren. Toon steeds het aantal geselecteerde dossiers, ook over pagina's
heen. Een gewijzigde filter wist de selectie om onbedoelde goedkeuring te voorkomen.

Vóór bevestiging toont één overzicht per dossier het lid, factuurnummer, de resterende
hoofdsom, voorgestelde incassokosten en het nieuwe totaal, plus het totaal aan toe te
voegen kosten voor de selectie. Toon ook waarom een dossier niet in aanmerking komt.
De penningmeester kan dossiers uitvinken en bevestigt de resterende selectie één keer;
er volgt geen aparte bevestigingsdialoog voor ieder dossier.

Direct vóór verwerking controleert Rondo per dossier opnieuw het saldo, de brieftermijn,
eventuele pauze en eerdere kostenboekingen. Dossiers die sinds het getoonde voorstel
zijn veranderd of niet meer voldoen, worden overgeslagen met een concrete reden en
moeten opnieuw worden beoordeeld. Ongewijzigde, geschikte dossiers kunnen wel worden
verwerkt. Het resultaat toont welke dossiers verwerkt, overgeslagen of mislukt zijn.

Bewaar de goedkeurder, het tijdstip, de grondslag en het goedgekeurde bedrag per dossier,
met een gezamenlijke verwijzing naar de groepsactie. Dubbel klikken, opnieuw proberen
of een gedeeltelijk mislukte verwerking mag geen dubbele kosten opleveren. Deze actie
voegt uitsluitend de goedgekeurde kosten toe; zij verstuurt geen dossiers naar incasso
en legt geen speelblokkade op.

### Pauzeren en hulp vanaf de eerste mail

Voeg vanaf deze fase **Ik heb hulp nodig** toe aan de betaalpagina en alle opvolgmails.
Een lid kan een terugbelverzoek, betalingsprobleem of bezwaar doorgeven. Toon door de
club beheerde verwijzingen naar Jeugdfonds Sport & Cultuur en gemeentelijke regelingen,
zonder een toezegging dat het lid daarvoor in aanmerking komt.

Op 18 november 2026 is vastgesteld dat de penningmeester nieuwe hulpaanvragen binnen
vijf werkdagen beoordeelt. Een nieuwe aanvraag krijgt een ontvangstbevestiging,
behandelaar en beoordelingsdatum. Verdere aanmaningen en automatische escalatie
pauzeren vanaf de aanvraag tot de beoordeling. Herhaalde inzendingen vullen dezelfde open aanvraag aan en
verlengen de beoordelingsdatum niet. Bij overschrijding wordt het dossier zichtbaar
voor de penningmeester; het systeem hervat niet stilzwijgend een sanctieproces.

De penningmeester kan ook handmatig pauzeren wegens bezwaar, regeling of fondsenaanvraag.
Reden, behandelaar en herbeoordelingsdatum zijn verplicht. Hervatten vereist een
bewuste keuze voor de volgende actie en een nieuwe redelijke reactietermijn. Een
hulpaanvraag heft een al bestaande speelblokkade niet zelfstandig op; daarvoor geldt
het afzonderlijke besluitproces uit fase 3.

Vraag geen inkomensbewijzen of medische gegevens in het formulier. Beperk vrije tekst
en maak duidelijk wie het verzoek leest. Hulpnotities zijn uitsluitend beschikbaar
voor bevoegde financiële behandelaars, ook niet via algemene persoonnotities.

### Logboek en betrouwbaarheid

Toon op de factuur een tijdlijn met type actie, tijd, actor, ontvanger, beleidsversie,
bedrag, uitkomst en volgende stap. Onderscheid **Gepland**, **Aangeboden aan maildienst**,
**Verzending bevestigd**, **Afgeleverd**, **Mislukt** en **Uitkomst onbekend**, voor zover
de maildienst die informatie levert. Een verzendpoging telt niet als geslaagde herinnering.
Bij onbekende uitkomst eerst reconciliëren met de maildienst, niet blind opnieuw sturen.

Een duurzame unieke actie-identiteit voorkomt dubbele verzending bij overlappende
cronruns, handmatige acties en retries. Controleer betalingen opnieuw onmiddellijk vóór
verzending. Betaling na verzending blijft een verklaarbare gebeurtenis in het logboek;
er volgen geen nieuwe aanmaningen voor voldane achterstand. Afleverfouten stoppen verdere
escalatie en verschijnen in een werkvoorraad voor adrescontrole of alternatief contact.

### Eindlijst

De fase levert tevens een filter **Vervolgbeoordeling** op, met de resterende vordering,
gevolgde stappen, ontvangstonderbouwing, afspraken en laatste contact. Een bevoegde
gebruiker kan een dossierpakket voorbereiden voor een bestuursbesluit of incasso.
Export is een bewuste actie, wordt gelogd en bevat alleen voor die bestemming noodzakelijke
gegevens. Een export verstuurt niets, beëindigt geen lidmaatschap en wijzigt geen Sportlink-record.

### Acceptatiecriteria fase 2

- [ ] Hele facturen en gemiste termijnen gebruiken dezelfde configureerbare opvolgregels.
- [ ] Een deelnemende factuur krijgt geen extra herinnering uit de oude schedulers.
- [ ] Gelijktijdige cron- en handmatige acties veroorzaken maximaal één bevestigde verzending per geplande actie.
- [ ] Een mislukte of onzekere mail laat de ronde niet doorgaan naar een zwaardere stap.
- [ ] Een betaling vóór verzending onderdrukt de mail; herhaalde betaalmeldingen blijven zonder dubbele boeking.
- [ ] Een betaling van de achterstand stopt die ronde, ook als toekomstige termijnen nog openstaan.
- [ ] Een hulpaanvraag krijgt een beoordelingsdatum binnen vijf werkdagen en pauzeert verdere aanmaningen en escalatie tot beoordeling door de penningmeester; verlopen beoordelingsdata blijven zichtbaar.
- [ ] Herhaalde hulpaanvragen creëren geen dubbele dossiers en verlengen de pauzedatum niet.
- [ ] Na een storing blijven de volledige intervallen tussen vervolgstappen behouden.
- [ ] Nieuwe termijnen veranderen een al verstuurde veertiendagenbrief niet.
- [ ] De veertiendagenbrief kan pas na vastgelegde juridische goedkeuring van de standaardbrief en verzendwijze worden geactiveerd; daarna is geen afzonderlijke goedkeuring per brief nodig bij dossiers die aan de voorwaarden voldoen.
- [ ] Onbekende ontvangst of betalingsplichtige verhindert automatische afronding van de briefstap.
- [ ] Het logboek kan reconstrueren welke tekst, bedragen en beleidsversie voor een besluit zijn gebruikt.
- [ ] Incassokosten worden alleen na expliciete goedkeuring door de penningmeester toegevoegd.
- [ ] Meerdere dossiers, ook over pagina's heen, kunnen vanuit één overzicht met bedragen in één keer worden goedgekeurd.
- [ ] Een gewijzigd saldo, pauze of eerdere kostenboeking tussen voorstel en bevestiging leidt tot overslaan met een reden, niet tot verwerking van een achterhaald voorstel.
- [ ] De groepsactie toont het resultaat per dossier, bewaart de individuele goedkeuringen en kan zonder dubbele kosten worden hervat na een gedeeltelijke fout.
- [ ] De eindlijst en export veroorzaken geen overdracht, royement of speelblokkade.

## Fase 3 Contributieblokkades

Xander controleert het huishoudelijk reglement. Het bestuur bepaalt vervolgens welke
deelname wordt beperkt, wie het besluit mag nemen, welke uitzonderingen gelden en hoe
het lid hierover wordt geïnformeerd. Deze clubregels staan los van de wettelijke
voorwaarden voor incassokosten; een veertiendagenbrief legt op zichzelf geen speelverbod op.

Voorgestelde eerste uitvoering: na het volledig doorlopen traject ontstaat een
**Blokkade beoordelen**-regel. Een bevoegde functionaris ziet de actuele achterstand,
ontvangstonderbouwing, contacthistorie en eventuele afspraken en bevestigt het besluit.
Het besluit legt reden, reikwijdte, ingangsdatum, actor en kennisgeving vast. Volledig
automatisch blokkeren is een afzonderlijke latere beleidskeuze, geen standaardinstelling.

Trainers en leiders zien in hun eigen teamoverzicht alleen **Deelname geblokkeerd**,
de ingangsdatum en een verwijzing naar de clubcontactpersoon. Coördinatoren zien dit
alleen binnen hun toegewezen teams of leeftijdsgroepen. Financiële redenen, bedragen,
achterstandsduur, hulpaanvragen en regelingdetails ontbreken ook in hun API-respons.
De blokkade geldt voor de betreffende speler in alle relevante teams, niet voor
broers, zussen of andere leden met dezelfde betaler.

De contributieblokkade verwijst naar de concrete vordering waarop het besluit berust.
Zodra die vordering is voldaan, vervalt deze blokkade automatisch na verwerking van
de betaling. Andere achterstanden vereisen hun eigen geldige besluitgrond. Een
goedgekeurde regeling kan op grond van clubbeleid aanleiding zijn om een blokkade
eerder op te heffen; alleen een aanvraag is daarvoor onvoldoende.

Een dagelijkse controle herstelt gemiste statusupdates. Tijdelijke uitzonderingen
vereisen een reden en einddatum. Opheffing van een contributieblokkade verwijdert geen
andere clubblokkade of bondsschorsing. Het ontbreken van deze blokkade wordt dus niet
als algemene verklaring **Speelgerechtigd** gepresenteerd. Er gaat geen automatische
wijziging naar Sportlink of wedstrijdopstellingen.

### Acceptatiecriteria fase 3

- [ ] Zonder vastgesteld beleid en expliciete activering kan geen contributieblokkade ontstaan.
- [ ] In de eerste uitvoering is een vastgelegd individueel besluit verplicht.
- [ ] Betaalde, gepauzeerde of onduidelijke dossiers leveren geen automatische blokkadevoorstellen op.
- [ ] Trainers buiten het betrokken team krijgen geen blokkadegegevens, ook niet via directe API-aanroepen.
- [ ] De teamrespons bevat geen financiële details of hulpinformatie.
- [ ] Verwerkte betaling van de betrokken vordering heft de blokkade direct op; gemiste updates worden binnen 24 uur hersteld.
- [ ] Toekomstige termijnen verhinderen de opheffing van de contributieblokkade niet; andere blokkadegronden blijven intact.
- [ ] Handmatige uitzondering, kennisgeving en opheffing zijn herleidbaar in het dossier.

## Fase 4 Afwijkende termijnregelingen aanvragen

Breid de hulpoptie uit met een gestructureerd verzoek om een afwijkende termijnregeling.
De bestaande keuze uit standaardplannen blijft beschikbaar waar de seizoensinstellingen
dat toelaten. Een lid vraagt bijvoorbeeld een latere betaaldatum of ander termijnbedrag
aan; dit verandert het betaalschema pas na beoordeling door de penningmeester.

Een goedgekeurde afspraak bewaart de oude en nieuwe planning, reden, goedkeurder en
bevestiging aan de betalingsplichtige. Reeds betaalde bedragen blijven behouden. Oude
betaallinks mogen daarna geen achterhaald bedrag innen; dit moet vóór activering van
deze fase zijn opgelost in de betaalverwerking. Bij afwijzing krijgt de aanvrager
uitleg en een expliciete nieuwe reactietermijn vóór hervatting.

### Acceptatiecriteria fase 4

- [ ] Een aanvraag wijzigt geen saldo, betaalschema of speelblokkade zonder beoordeling.
- [ ] Een goedgekeurd schema sluit exact aan op de resterende hoofdsom en overeengekomen kosten.
- [ ] Reeds betaalde termijnen worden niet opnieuw gefactureerd of aangemaand.
- [ ] Oude links en overlappende aanvragen veroorzaken geen dubbele of verkeerde inning.
- [ ] De aanvrager ontvangt de beslissing en kan de nieuwe afspraken terugvinden.
- [ ] Fondseninformatie is door de club te beheren en belooft geen individuele toelating.

## Rechten en technische aansluiting

De financiële werklijst, tellingen en CSV gebruiken dezelfde bestaande leesrechten en
persoonsbeperkingen als facturen. Dossiermutaties vereisen `financieel`; het recht om
clubblokkades te besluiten wordt expliciet toegewezen en volgt niet automatisch uit
financieel leesrecht. Gedetailleerde hulpinformatie blijft beperkt tot behandelaars.

Publieke toegang wordt per factuur via het bestaande geheime token begrensd. Een token
geeft geen toegang tot andere gezinsleden, interne notities of export. Inzendingen
krijgen invoervalidatie, bescherming tegen herhaling en misbruik, en logging zonder
tokenwaarden. Een hulpaanvraag geldt niet als bewijs van de identiteit van een schuldenaar.

Gebruik bestaande `rondo_invoice`-posts voor dossiergegevens en WordPress-native
postmeta of private commenttypen voor gebeurtenissen. Bewaar beleidsinstellingen via
de Options API. Domeinvelden lopen via `Rondo\Fields\Fields` en de registry. Er komen
geen eigen databasetabellen of directe SQL-mutaties. De precieze velden en endpoints
worden bij implementatie uitgewerkt binnen deze randvoorwaarden.

De berekening van achterstanden wordt gedeeld door statistieken, dossier, scheduler
en blokkadebeoordeling. Betaalstatus blijft gescheiden van opvolgstatus en clubbesluit;
de bestaande factuurstatussen worden niet vervangen door bijvoorbeeld **Aanmaning**.
Een fout in mailverwerking mag de verwerking van een ontvangen betaling nooit blokkeren.

| Bestaande bron | Aansluiting |
|---|---|
| `src/pages/Finance/Betaalstatistieken.jsx` | Overzicht en werklijst |
| `src/pages/Finance/FactuurDetail.jsx` | Dossier, logboek en acties |
| `includes/class-invoice-statistics.php` | Financiële tellingen |
| `includes/class-invoice-overdue-status.php` | Toepasselijke vervaldata |
| `includes/class-rest-invoices.php` | Financiële API en rechten |
| `includes/class-invoice-reminder-scheduler.php` | Bestaande opvolging van facturen |
| `includes/class-installment-scheduler.php` | Termijnvervaldata en bestaande herinneringen |
| `includes/class-invoice-reminder-sender.php` en `includes/class-installment-email-sender.php` | Betaallinks, teksten en verzending |
| `includes/class-public-payment-page.php` | Planselectie en publieke hulpaanvraag |
| `includes/class-mollie-webhook.php` | Betalingsverwerking en herberekening |
| `includes/class-access-control.php` | Bestaande persoons-, team- en leeftijdsscope |

## Invoering en verificatie

1. Lever fase 1 op zonder nieuwe verzendingen. Reconcileer totalen met facturen en
   betaalverwerking; onderzoek ook gemiste termijnen binnen de 162 genoemde regelingen.
2. Laat fase 2 eerst in proefmodus de volgende acties en uitsluitredenen tonen. Proefmodus
   verstuurt niets, voegt geen kosten toe en verandert geen speelstatus.
3. Laat de penningmeester bestaande dossiers expliciet laten instromen. De 52 genoemde
   open facturen vormen een beginlijst, geen automatisch goedgekeurde populatie.
   Historische herinneringen blijven zichtbaar maar bewijzen geen geldige WIK-brief.
4. Leg per bestaand dossier startstap, startdatum, ontvanger en eventuele pauze vast.
   Zonder verifieerbare historie begint het dossier met een nieuwe reactietermijn.
5. Activeer eerst een beperkte, beoordeelde groep en controleer ontvangers, bedragen,
   links en logboek. Leg daarna een omslagdatum vast voor nieuwe achterstanden.
6. Schakel per deelnemende factuur de oude herinneringen uit voordat de nieuwe flow
   verzendt. Niet-deelnemende facturen houden hun bestaande gedrag. Terugdraaien
   activeert niet automatisch oude herinneringen die mogelijk al vervangen zijn.
7. Activeer fase 3 pas na beleidsvaststelling en aantoonbaar correcte werking van fase 2.
   Bouw fase 4 daarna als uitbreiding van de bestaande hulpafhandeling.

Een noodstop stopt nieuwe mails en blokkadebesluiten. Betalingsverwerking, inzage,
hulpontvangst en het opheffen van voldane contributieblokkades blijven werken. Bewaar
dossierhistorie bij stoppen of herstarten en voorkom een automatische inhaalslag.

Test bij implementatie minimaal datumgrenzen en zomer-/wintertijd, deelbetalingen,
creditcorrecties, meerdere facturen en teams, veranderde ontvangers, ontbrekende
termijngegevens, dubbele webhooks, gelijktijdige cronruns, afleverfouten, pauzes en
rechten via directe API-verzoeken. Test ook een volledige ronde met proefbetalingen
en gecontroleerde testontvangers voordat echte aanmaningen worden geactiveerd.

De productiemijlpaal volgt de bestaande lint-, build-, PHP-test- en CI/deployprocedure.
Dit planningdocument zelf vereist geen themaversie, changelog of productiedeploy.

## Succescriteria

- Iedere achterstand heeft een volgende actie met datum of een zichtbare controle- of pauzereden.
- De penningmeester kan per team en leeftijdscategorie de achterstand zonder handmatige lijst samenstellen.
- Er zijn geen dubbele opvolgmails en geen escalaties op basis van onzekere gegevens.
- Nieuwe hulpaanvragen hebben een eigenaar en worden binnen de afgesproken termijn beoordeeld.
- Iedere contributieblokkade is herleidbaar en wordt tijdig opgeheven zodra de grond vervalt.

Meet per maand aantallen achterstandsleden, achterstallige hoofdsom, ouderdom,
betalingen na iedere stap, hulpaanvragen, verlopen beoordelingen en handmatige
handelingen. Leg tijdens de proefperiode een nulmeting vast; stel daarna samen
een haalbare verbeterdoelstelling vast.

## Beleidskeuzes vóór activering

| Keuze | Uitgangspunt en besluitstatus | Nodig vóór |
|---|---|---|
| Herinneringsintervallen | Vastgesteld op 18 november 2026: 7 dagen na verval, daarna telkens 14 dagen na bevestigde verzending van de vorige stap; alleen bij resterende achterstand en zonder pauze | Besluit genomen; toepassen bij fase 2 |
| Behandeling hulp | Vastgesteld op 18 november 2026: de penningmeester beoordeelt binnen vijf werkdagen; verdere aanmaningen en escalatie pauzeren tot beoordeling | Besluit genomen; toepassen bij fase 2 |
| Betalingsplichtige en briefproces | Vastgesteld op 18 november 2026: eenmalige juridische toets van standaardbrief en verzendwijze, inclusief ontvangstregistratie; daarna automatische verzending voor dossiers die aan de voorwaarden voldoen, met vastgestelde betalingsplichtige | Besluit genomen; juridische toets nog uitvoeren vóór activering van de WIK-stap |
| Incassokosten | Vastgesteld op 18 november 2026: expliciete goedkeuring door de penningmeester; meerdere geselecteerde dossiers in één keer goedkeuren met vooraf zichtbare bedragen en registratie per dossier | Besluit genomen; berekeningsregels toetsen vóór kosten opnemen of innen |
| Reikwijdte speelblokkade | Bestuur specificeert wedstrijden, trainingen en eventuele uitzonderingen | Fase 3 |
| Besluitbevoegdheid | Individuele beoordeling door aangewezen functionaris | Fase 3 |
| Regeling en opheffing | Goedkeuring kan opheffing toestaan; aanvraag alleen niet | Fase 3 en 4 |
| Bewaartermijnen en export | Club bepaalt bewaartermijnen per dossier- en hulpgegeven en toegestane ontvangers | Opslaan hulpgegevens en dossieroverdracht |

Leg dit vast als één hoofdverzoek met vier bouwfasen. De eindlijst hoort bij fase 2;
hulp begint eveneens in fase 2, zodat automatische opvolging direct een uitweg biedt.
