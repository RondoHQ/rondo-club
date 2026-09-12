# Automatische onboarding van leden en vrijwilligers

**Status:** Uitvoerbaar voorstel op basis van de gemaakte productkeuzes; nog niet geïmplementeerd of ingeschakeld.
**Datum:** 12 september 2026.
**Volgorde:** Eerst leden, daarna vrijwilligers met VOG- en kledingopvolging.

## Doel

Nieuwe leden en nieuwe vrijwilligers ontvangen automatisch een passende welkomstmail,
nadat de club 24 uur gelegenheid heeft gehad functies en rollen te verwerken. De
persoonspagina toont de planning, uitkomst en bediening. Het losse onboarding-scherm
verdwijnt. De VOG- en kledingcoördinatoren werken vanuit hun bestaande vakpagina's;
er worden hiervoor geen algemene Rondo-taken aangemaakt.

Dit document legt het ontwerp en de uitvoering vast. Het wijzigt geen instellingen,
verstuurt geen e-mails en activeert geen productieautomatisering. De standaardteksten
hieronder zijn voorgestelde beginwaarden voor de bewerkbare mailblokken.

## 1. Gemaakte keuzes

| Onderdeel | Afspraak |
|---|---|
| Ledenwelkomstmail | Automatisch, minimaal 24 uur na de ingangsdatum of eerste betrouwbare herkenning als nieuw lid in Rondo, afhankelijk van welke later is |
| Openstaande overschrijving | Houd de welkomstmail tegen totdat de overschrijving is afgerond |
| Vrijwilligerswelkomstmail | Automatisch, minimaal 24 uur na herkenning als nieuwe vrijwilliger |
| Handmatige vrijgave | Geen verplicht vinkje of goedkeuring |
| Bediening | Planning, uitstellen, resultaat en fouten op de persoonspagina |
| Onzekere verzending | Registreer iedere verzending per adres; controleer bij twijfel eerst de verzendstatus en verstuur niet blind opnieuw |
| Uitstellen | Beheerder kiest een nieuwe datum en tijd |
| Ledenontvangers | Alle opgegeven adressen van het lid en de ouders/verzorgers, ontdubbeld per lid |
| Ontbrekende oudernaam | Importeer het opgegeven ouderadres met de vervangende naam `Ouder van {voornaam kind}` |
| Vrijwilligers- en VOG-mailontvangers | Alle eigen adressen; bij vrijwilligers jonger dan 18 ook opgegeven ouderadressen, steeds ontdubbeld |
| Herinschrijving | Opnieuw een ledenwelkomstmail na 24 uur bij een echte nieuwe inschrijving; geen nieuwe ronde bij seizoenswisseling |
| Terugkerende vrijwilliger | Na aantoonbaar stoppen en opnieuw beginnen een nieuwe vrijwilligerswelkomstmail na 24 uur |
| Nieuw lid meteen vrijwilliger | Eén gecombineerde mail met ledeninformatie en relevante vrijwilligersblokken; alle blokken bewerkbaar |
| Ledenaanhef | `Beste {first_name} en eventuele ouders/verzorgers,` |
| Accountactivatie | Vaste knop naar `/activeren`; geen kortlevende tokenlink in de welkomstmail |
| Vrijwilligersinhoud | Alleen toepasselijke blokken, bepaald bij verzending |
| Mailinstellingen | Onderwerp en alle blokken afzonderlijk bewerkbaar; voorbeeld van de complete mail |
| VOG-opvolging | Op de bestaande VOG-pagina, zonder algemene taken |
| VOG-herinneringen | Eerste na 14 dagen, tweede na nog eens 14 dagen; maximaal twee per aanvraagronde |
| Herinneringsinstellingen | Wachttijd, interval, maximum, onderwerp en tekst aanpasbaar via Instellingen → VOG |
| Gewijzigde termijnen/maxima | Alleen voor nieuwe aanvraagrondes; lopende rondes behouden hun oorspronkelijke afspraken |
| Bestaande VOG-aanvragen bij inschakelen | Handmatige opvolging; geen automatische herinneringen voor die rondes |
| Afwijzing / opnieuw aanleveren | Direct één mail met toelichting en vervolgstap; geen nieuwe herinneringsreeks |
| Kledingopvolging | Tab **Te regelen** op de bestaande Kleding-pagina |
| Kledingdoelgroep | Trainers, assistent-trainers, keeperstrainers, leiders en teammanagers |
| Kledingpakket | De geselecteerde leiders en teammanagers krijgen hetzelfde pakket als trainers |
| Kleding afronden | Handmatig met **Kleding geregeld**; uitgiftes zichtbaar, reden verplicht bij geen uitgifte |
| Kleding bij terugkeer | Nieuwe werkvoorraadvermelding met eerdere uitgiftes; coördinator bepaalt wat nodig is |
| Kleding zonder VOG-plicht | Direct **Kleding regelen** zodra de overige kledingvoorwaarden zijn vervuld |
| Hervatten na langere onderbreking | Achterstallige welkomstmails eerst laten beoordelen; herinneringen hervatten zonder inhaalslag |

### Uitroluitgangspunt

Bij het inschakelen worden bestaande leden niet automatisch alsnog verwelkomd. Ook
bestaande vrijwilligers en kledinghistorie worden niet als nieuwe onboarding behandeld.
Open VOG-werk blijft zichtbaar en wordt handmatig opgevolgd. Automatische herinneringen
gelden alleen voor nieuwe aanvraagrondes na inschakeling. Leg dit per ronde vast;
het opnieuw starten van de automatisering mag oude rondes niet alsnog activeren.

## 2. Leden: herkenning, planning en verzending

### Herkenning

- Leg het eerste betrouwbare herkenningsmoment vast. Start de 24-uurswachttijd op
  de ingangsdatum van het lidmaatschap, of bij dit herkenningsmoment als dat later is.
  Een toekomstige ingangsdatum stelt de start dus uit; een ingangsdatum in het
  verleden verkort de wachttijd na herkenning niet. Gebruik `lid_sinds` niet als
  enige basis, omdat die datum uit Sportlink komt en in het verleden kan liggen.
- Een herhaalde sync mag dit moment niet verschuiven of een tweede mail plannen.
- Maak onderscheid tussen een nieuwe inschrijving en het voor het eerst importeren
  van een al bestaand lid. Een technische migratie of herstelimport is geen inschrijving.
- Laat de sync nieuwe leden gericht volledig controleren: persoonsgegevens,
  oudercontacten en team-/vrijwilligersfuncties moeten succesvol zijn opgehaald én
  opgeslagen. Wacht hiervoor niet op de wekelijkse teamsync. Dit is een gemaakte
  productkeuze; het voltooiingscontract wordt in fase 1 geïmplementeerd.
- Een expliciet gecontroleerde lege uitkomst, zoals geen functies, telt als compleet.
  Een nog niet uitgevoerde controle of fout telt als incompleet. Een tussentijdse
  person-save bewijst geen volledigheid.
- Verstuur alleen als zowel de 24-uurswachttijd voorbij is als de gerichte controle
  volledig is afgerond. Een onvolledige controle houdt de mail tegen.
- Houd de welkomstmail ook tegen zolang een overschrijving openstaat. Controleer
  vóór verzending dat deze is afgerond; daarnaast blijven de afgesproken wachttijd
  en volledige gegevenscontrole gelden.
- Een vertraagd proces stuurt nooit vóór de 24 uur voorbij zijn. Bewaar tijden in UTC
  en toon ze in de clubtijdzone, ook rond zomer-/wintertijd.

### Persoonspagina

Een compacte kaart toont bijvoorbeeld **Welkomstmail gepland voor morgen 14:00**, met
**Uitstellen**. Na verzending toont de kaart het tijdstip en de uitkomst per ontvanger.
Bij gedeeltelijk falen blijft zichtbaar welke adressen nog niet zijn afgehandeld.

Zichtbare mailstatussen: **Gepland**, **Uitgesteld**, **Verstuurd**, **Deels verstuurd**,
**Verzenden mislukt**, **Geen geldig e-mailadres** en **Vervallen**. Een technisch
onzekere uitkomst krijgt expliciet **Verzendstatus controleren** en geen automatische
claim dat de mail wel of niet is verstuurd.

### Controle op het verzendmoment

Controleer de actuele inschrijving, benaderbaarheid volgens het bestaande
communicatiebeleid, geldige adressen, functies, rollen, eerdere verzendingen en
uitstelstatus opnieuw. Overleden personen worden niet benaderd. Een beëindigde of
niet toepasselijke inschrijving wordt niet alsnog verwelkomd.

Wordt het nieuwe lid tijdens de wachttijd vrijwilliger, dan vervalt de gewone
ledenmail en neemt de vrijwilligersroute over met één gecombineerde welkomstmail:
ledeninformatie plus de relevante vrijwilligersblokken. Ook de ledenblokken in deze
mail zijn via de instellingen bewerkbaar. De gecombineerde mail is niet eerder
verschuldigd dan 24 uur na herkenning als vrijwilliger. Wie de ledenmail al heeft
ontvangen en later vrijwilliger wordt, kan vervolgens één vrijwilligersmail krijgen.

Een reeds verstuurde mail wordt niet opnieuw verstuurd door een extra functie, een
gewijzigd adres of een tweede team. Bij een echte herinschrijving na beëindigd
lidmaatschap begint wel een nieuwe ledenwelkomstronde, met opnieuw 24 uur wachttijd.
Een seizoenswisseling telt niet als herinschrijving. Behoud eerdere verzendingen als
historie. Een herstelimport of tijdelijke sync-fout bewijst geen beëindiging/terugkeer.

### Ontvangers van de ledenmail

Verzamel geldige, opgegeven adressen van het lid en de vastgestelde ouder-/verzorger-
relaties via de bestaande gegevens- en toegangsregels. Gebruik geen afgeleide
verwantschap op basis van alleen een gedeeld e-mailadres. Neem ook een tweede
opgegeven adres mee. Leg in de technische inventarisatie vast hoe Sportlink-
ouderslots en zelfstandige ouderrecords samenkomen, zodat geen bron wordt gemist.

Ontbreekt de oudernaam bij een geldig opgegeven ouderadres, importeer de ouder dan
met de vervangende naam **Ouder van {voornaam kind}**, bijvoorbeeld **Ouder van Emma**.
Het ontbreken van de naam mag het adres niet meer uitsluiten. Gebruik een beschikbare
echte oudernaam vóór deze vervangende naam en overschrijf een bekende echte naam niet
met de vervangende naam. De koppeling aan het kind blijft gebaseerd op het opgegeven
ouderslot, niet op de gegenereerde naam.

Normaliseer witruimte en hoofdletters volgens het bestaande communicatiebeleid en
ontdubbel per lid. Verander geen provider-specifieke punten of plus-adressering.
Hetzelfde gezinsadres mag voor twee verschillende nieuwe leden twee persoonlijke
welkomstmails ontvangen. Verzend per uniek adres, zodat ontvangers elkaars adressen
niet in To/CC zien en een fout per adres kan worden afgehandeld.

### Inhoud van de ledenmail

Behoud de ingestelde inhoud over contributie, kleding, trainingen/wedstrijden en
vrijwilligerswerk als startpunt; overschrijf bestaande clubteksten niet stilzwijgend.
De afgesproken introductie is:

> Beste {first_name} en eventuele ouders/verzorgers,
>
> Welkom bij AWC! We zijn blij dat {first_name} lid is geworden.

Voeg een knop **Activeer je Rondo-account** toe naar de vaste clubpagina `/activeren`:

> Gebruik het e-mailadres waarop je deze mail ontvangt. Ouders/verzorgers kunnen
> een eigen account aanmaken.

Controleer tijdens implementatie dat ieder gebruikt ontvangeradres door de bestaande
activatieroute herkend wordt. Een koppeling van het lid aan één account bewijst niet
dat iedere ouder of iedere andere ontvanger al een eigen account heeft.

## 3. Vrijwilligersmail en instelbare blokken

Gebruik de huidige rollenregistratie om nieuwe vrijwilligers te herkennen. Een extra
functie of team bij een bestaande vrijwilliger veroorzaakt geen tweede onboarding.
Wie aantoonbaar stopt en later opnieuw vrijwilliger wordt, krijgt wel een nieuwe
welkomstronde na 24 uur, met inhoud passend bij de dan actuele functies. Bewaar de
vorige ronde; overschrijf niet slechts de oude verstuurdatum. Een tijdelijke ontbrekende
rol tijdens een sync is geen bewezen onderbreking van het vrijwilligerswerk.
De VOG-status en kledingvoorwaarden worden opnieuw bepaald bij verzending. Een
lopende VOG-aanvraag moet bij de huidige aanvraagronde horen, niet bij een oude ronde.

### Ontvangers van vrijwilligers- en VOG-mails

Gebruik alle geldige eigen e-mailadressen van de vrijwilliger. Voeg bij vrijwilligers
jonger dan 18 de opgegeven adressen van vastgestelde ouders/verzorgers toe. Ontdubbel
de verzameling volgens hetzelfde beleid als bij leden en leg het resultaat per adres
vast. Controleer leeftijd en relaties opnieuw vóór verzending; voeg geen ouderadressen
automatisch toe als de leeftijd niet betrouwbaar vastgesteld kan worden.

Kies accountblokken per ontvangercontext: de aanwezigheid van het vrijwilligersaccount
betekent niet dat de ouder ook een account heeft. De ledenmail houdt haar eerder
afgesproken ruimere ontvangerregel. De precieze toepassing op een gecombineerde mail
moet worden vastgelegd, zodat die geen vrijwilligers-/VOG-informatie naar een volgens
deze regel uitgesloten ontvanger stuurt; zie hoofdstuk 9.

### Instellingen

Maak in de bestaande e-mailinstellingen een herkenbare sectie
**Vrijwilligerswelkomstmail**. Onderwerp, introductie, alle VOG-varianten,
kledingvarianten, accountblokken en afsluiting zijn afzonderlijk bewerkbaar. Voeg
voor de gecombineerde welkomstmail ook afzonderlijk bewerkbare ledeninformatie toe. Toon
welke voorwaarde ieder blok activeert en bied een voorbeeld per situatie zonder te
verzenden. Voorgestelde variabelen zoals clubnaam, contactadres en URL zijn getypeerde,
ondersteunde invulvelden; behandel ze niet als willekeurige uitvoerbare templatecode.

De club bepaalt de tekst. De server bewaakt de logica: tekstwijzigingen veranderen
geen VOG-plicht, accountrechten of voorwaarden voor kleding. Een wijziging geldt voor
nog niet begonnen verzendingen. Bewaar voor een begonnen verzending de gebruikte
templateversie en inhoud, zodat een retry dezelfde logische mail blijft.

### Standaardteksten per blok

De AWC-contactgegevens en naam hieronder komen uit de bestaande mail. Maak ze via
de instellingen bewerkbaar en gebruik voor andere clubs hun eigen invulling.

| Blok | Wanneer | Voorgestelde standaardtekst |
|---|---|---|
| Onderwerp | Altijd | Welkom als vrijwilliger bij {club_naam} |
| Welkom | Altijd | Beste {first_name},<br><br>Wat fijn dat je vrijwilliger bent geworden bij {club_naam}! Bedankt dat je je wilt inzetten voor onze club. |
| VOG ontbreekt | VOG vereist, geen geldige registratie en geen actuele aanvraag | Voor jouw functie vragen we een Verklaring Omtrent het Gedrag (VOG). Je ontvangt apart uitleg over de gratis aanvraag via de club. Heb je al een VOG? Neem dan contact op met onze VOG-coördinator om te bespreken of je die kunt gebruiken. |
| VOG-aanvraag loopt | Huidige aanvraag bij Justis klaargezet | Voor jouw VOG loopt al een aanvraag. Volg de instructies die je daarvoor ontvangt. Zodra je de VOG hebt ontvangen, kun je deze uploaden in Rondo. |
| VOG geldig | VOG vereist en geldig geregistreerd | Er staat al een geldige VOG voor je geregistreerd. Hiervoor hoef je nu niets te doen. |
| VOG vernieuwen | Vernieuwing nodig, nog geen actuele aanvraag | Je VOG moet worden vernieuwd. Je ontvangt apart uitleg over de gratis aanvraag via de club. |
| Kleding, VOG nog nodig | Geselecteerde kledingfunctie, vereiste VOG nog niet in orde | Voor jouw functie krijg je kleding van de club. Zodra je VOG in orde is, verschijnt dit bij de kledingcoördinator. Die neemt contact met je op over het ophalen. |
| Kleding, voorwaarden voldaan | Geselecteerde kledingfunctie en VOG-voorwaarde voldaan | Voor jouw functie krijg je kleding van de club. De kledingcoördinator ziet dat dit geregeld kan worden en neemt contact met je op over het ophalen. |
| Account activeren | Ontvanger moet nog een passend account activeren | In Rondo kun je je gegevens bekijken en aanpassen. Activeer je account met het e-mailadres waarop je deze mail ontvangt.<br><br>**Activeer je Rondo-account** → `/activeren` |
| Account bestaat | Een passend account voor deze ontvanger is vastgesteld | Je hebt al een Rondo-account. Daarmee kun je je gegevens bekijken en aanpassen.<br><br>**Log in op Rondo** → de bestaande loginroute |
| Afsluiting | Altijd | Heb je vragen? Mail gerust naar secretaris@svawc.nl of vrijwilligers@svawc.nl.<br><br>Met sportieve groet,<br>Joost de Valk<br>Secretaris AWC |

De kledingtekst is aangepast aan de later gekozen werkvoorraad en aan opname van
leiders en teammanagers. De accounttekst belooft niet aan iedere vrijwilliger een
VOG-uploadactie als die niet van toepassing is. Dit zijn redactionele voorstellen.

Bij geen VOG-plicht wordt het VOG-blok weggelaten. Bij meerdere rollen kiest Rondo
één toepasselijke variant, zonder dubbele blokken. Een inzending die al wordt
gecontroleerd of opnieuw moet worden aangeleverd mag geen onjuiste tekst over een
nieuwe aanvraag activeren. Voor die twee situaties moeten nog eigen bewerkbare
teksten worden afgestemd; ze mogen niet stilzwijgend onder 'aanvraag loopt' vallen.

## 4. VOG-pagina als werkvoorraad

De VOG-pagina selecteert al mensen met actuele VOG-plicht en een ontbrekende of
verlopen VOG. Behoud **Binnenkort** voor naderende vernieuwingen en **Te beoordelen**
voor documentbeoordeling. Maak processtatussen direct zichtbaar en filterbaar in
het overzicht. De persoonskaart gebruikt dezelfde statusberekening.

| Status | Betekenis en overgang |
|---|---|
| **Aanvraag klaarzetten** | VOG ontbreekt of moet vernieuwd worden, zonder actuele aanvraag. Coördinator zet bij Justis klaar en bevestigt dit in Rondo. |
| **Wachten op VOG** | Klaarzetten is bevestigd. Vrijwilliger levert het document aan. |
| **Te beoordelen** | Document wacht op menselijke beoordeling; bestaande goedkeuringsvoorwaarden blijven gelden. |
| **Opnieuw aanleveren** | Document is afgewezen of onbruikbaar; vrijwilliger krijgt een concrete toelichting. |
| **Afgerond** | Geldige VOG geregistreerd. Verdwijnt uit de open werkvoorraad en blijft terugvindbaar op de persoonspagina. |

**Controle loopt** is een zichtbare tussentoestand voor een automatische of technisch
herhaalde documentcontrole. Die hoeft geen nieuwe hoofdtab te worden, maar mag niet
worden weergegeven als 'wachten op upload'. **Opvolging nodig** is een aandachtssignaal
bij de lopende aanvraag nadat het herinneringsmaximum is bereikt.

### Overgangen en grenzen

- De 24 uur geldt voor de welkomstmail. De VOG-coördinator mag ondertussen al werken.
- 'Klaargezet bij Justis' wordt alleen vastgelegd na die handmatige handeling. Een
  verzonden welkomstmail of instructiemail bewijst geen ingediende aanvraag.
- Na bevestiging kan de ingestelde aanvraaguitleg één keer voor deze ronde worden
  verstuurd. Stem de bestaande losse VOG-mail hierop af, zodat niet twee onafhankelijke
  routes dezelfde instructie verzenden. Een fout laat de aanvraagstatus intact.
- Een upload start controle; automatische goedkeuring of handmatige goedkeuring kan
  afronden. Een upload alleen is nooit voldoende bewijs voor een geldige VOG.
- Hergebruik de bestaande controles op document, persoon, functie, organisatie en
  screeningsprofiel. Dit plan verruimt geen automatische goedkeuringsregels.
- Valt de VOG-plicht weg, stop dan toekomstige opvolgmails en verwijder de persoon
  uit de actieve werkvoorraad. Behoud de aanvraaghistorie.
- Bij vernieuwing ontstaat een nieuwe ronde. Oude aanvraag-, mail- en herinnerings-
  datums mogen niet als bewijs voor die nieuwe ronde worden gebruikt.
- Laat de volledige werkvoorraad bereiken via paginering; filters en tellingen
  moeten over alle resultaten lopen, ook bij meer dan 100 personen.

### Automatische herinneringen

| Instelling | Afgesproken beginwaarde |
|---|---|
| Eerste herinnering | 14 dagen na bevestiging van klaarzetten bij Justis |
| Interval | 14 dagen |
| Maximum | 2 succesvolle herinneringen per aanvraagronde |
| Onderwerp en tekst | Bewerkbaar in Instellingen → VOG |

Controleer vóór iedere verzending: juiste ronde, nog VOG-plicht, nog geen geldige
VOG, geen upload in controle/beoordeling, geldig ontvangeradres en maximum niet bereikt.
Verstuur gemiste herinneringen na een storing niet direct achter elkaar. Het volgende
interval begint na de werkelijke succesvolle vorige herinnering.

Na het maximum volgt **Opvolging nodig** op de VOG-pagina. Een mislukte verzending
telt niet als verstuurde herinnering en wordt zichtbaar. Bij meerdere ontvangers
telt één herinneringsronde, niet iedere afzonderlijke e-mail, tegen het maximum;
succesvolle ontvangers krijgen geen kopie wanneer alleen een ander adres faalde.

Bewaar wachttijd, interval en maximum bij het openen van een aanvraagronde. Wijzigingen
in de instellingen gelden alleen voor daarna geopende rondes. Bewerkbare teksten
gelden voor nog niet begonnen verzendingen, zoals beschreven bij de mailinstellingen.

Bij afwijzing of **Opnieuw aanleveren** gaat direct één e-mail met de toelichting en
de concrete vervolgstap uit. Deze melding hoort bij de betreffende beoordelingsuitkomst;
een herhaalde verwerking van dezelfde uitkomst stuurt haar niet nogmaals. Gebruik de
afgesproken VOG-ontvangers en bewerkbare mailtekst. Start geen nieuwe Justis-aanvraag
of reeks aanvraagherinneringen. Eventuele verdere opvolging gebeurt vanuit de VOG-pagina.

## 5. Kleding-pagina als werkvoorraad

Voeg **Te regelen** toe aan de bestaande Kleding-pagina. Deze heeft al artikelen,
uitgifte/inname, persoonlijke kledinghistorie en transacties. Gebruik die registraties
in de nieuwe werkvoorraad; er is geen nieuwe algemene takenlijst nodig.

### Instelbare functies

Deze in productie aangetroffen functienamen vormen de afgesproken beginselectie:

- Trainer / trainer
- Trainer/coach
- Assistent trainer
- Assistent-trainer/coach
- Ass.-trainer/coach
- Keeperstrainer
- Leider
- Leider Senioren
- Teammanager
- Teammanager senioren
- Teammanager Recreanten Zaterdag

Beheer de selectie via **Instellingen → Kleding**. Hoofdlettervarianten mogen één
betekenis krijgen, maar voeg niet automatisch andere functies toe op basis van een
gedeeltelijke naam. Leiders en teammanagers vallen onder hetzelfde kledingpakket.

Alleen actuele functies tellen. Expliciete begin- en einddatums en een expliciet
inactieve historische rol moeten correct worden verwerkt. Meerdere teams of rollen
leveren één actieve kledingopvolging per persoon op. Een nieuwe functie na eerdere
afronding veroorzaakt niet automatisch een nieuwe uitgifte.

Een echte terugkeer als vrijwilliger opent wel een nieuwe vermelding wanneer de
actuele functie binnen de kledingselectie valt. Toon eerdere uitgiftes en eerdere
afronding. De coördinator bepaalt wat nog nodig is; dit is geen automatische toezegging
van een compleet nieuw pakket. Pas de bewerkbare kledingtekst in de welkomstmail bij
terugkeer daarop aan. Een extra team/functie tijdens doorlopend vrijwilligerswerk
blijft onderdeel van dezelfde opvolging.

### Statussen en bediening

| Status | Betekenis / bediening |
|---|---|
| **Wachten op VOG** | Functie komt in aanmerking; vereiste VOG is nog niet in orde |
| **Kleding regelen** | Kleding kan worden opgepakt; VOG-voorwaarde is voldaan |
| **In behandeling** | Coördinator heeft het opgepakt; korte notitie mogelijk |
| **Afgerond** | Coördinator heeft **Kleding geregeld** gekozen |

Toon naam, actuele relevante functie(s)/team(s), bereikbare contactgegevens, status,
notitie en eerdere uitgiftes voor zover de coördinator die mag zien. Geef alleen de
benodigde VOG-voorwaarde weer, niet het VOG-document of de beoordelingsdetails.

De coördinator kan vanuit de werkvoorraad kledinguitgifte registreren. Gedeeltelijke
uitgifte blijft **In behandeling**. Automatische pakketcontrole rondt niets af: de
coördinator beslist expliciet. Bij afronding zonder uitgifte is een reden verplicht,
bijvoorbeeld 'heeft al een passend pakket'. Bewaar wie wanneer afrondde en waarom.

Houd rekening met bestaande uitgiftes en de bestaande artikel-/seizoensregels.
Een onboardingstatus mag die uitgifteregels niet ongemerkt omzeilen. Als de relevante
functie vervalt, verdwijnt de open opvolging met behoud van historie; reeds uitgegeven
kleding wordt niet automatisch ingenomen.

Een ontbrekende VOG verhindert alleen wanneer die voor de actuele situatie vereist
is; een vrijgestelde functie mag niet eindeloos op een niet vereiste VOG wachten.
Zonder VOG-plicht volgt direct **Kleding regelen** zodra de overige kledingvoorwaarden
zijn vervuld. Er is daarvoor geen afzonderlijke handmatige vrijgave nodig.

## 6. Betrouwbare technische uitvoering

Dit is een implementatievoorstel binnen de bestaande WordPress-architectuur, geen
opdracht om tijdens de planningsfase nieuwe infrastructuur aan te maken.

### Opslag en services

- Gebruik uitsluitend WordPress-native opslag: postmeta/native field registry voor
  persoonsstatus, options voor instellingen, en waar historie dat vereist een privé
  custom post type voor aanvraagrondes/verzendregistraties. Geen eigen databasetabellen.
- Leg definitieve veldnamen, CPT-keuze en contracten in fase 1 vast. Domeinvelden gaan
  via `Rondo\Fields\Fields`, canonieke namen en de bestaande toegangscontrole.
- Scheid onboarding, VOG-rondes en kledingopvolging. Laat gedeelde services dezelfde
  actuele voorwaarden berekenen voor REST, pagina's, handmatige acties en cron.
- Gebruik herhaalbare WordPress-cronverwerking met een herstelcontrole voor gemiste
  geplande acties. Controleer de echte productie-aansturing van cron; vertrouw niet
  uitsluitend op bezoek aan de site.
- Maak automatische verzending afzonderlijk aan/uit zetbaar. Uitzetten stopt nog
  niet verstuurde acties zonder historie of werkvoorraden te verwijderen.

### Bescherming tegen dubbele uitvoering

**Gemaakte keuze:** registreer iedere verzending per adres. Een onzekere uitkomst
wordt eerst gecontroleerd voordat een nieuwe verzending mag plaatsvinden. Als de
uitkomst niet kan worden vastgesteld, blijft **Verzendstatus controleren** staan;
onzekerheid is geen bewijs dat de mail niet is verstuurd.

Bewaar een stabiele sleutel per persoon, lidmaatschaps-/vrijwilligerswelkomstronde
of VOG-aanvraagronde, berichtsoort,
herinneringsnummer en genormaliseerd adres. Handmatige verzending en cron gebruiken
dezelfde registratie en dezelfde exclusieve claim. Een losse controle op een
sent-timestamp gevolgd door `wp_mail()` is onvoldoende bij twee gelijktijdige acties.

Leg het resultaat per adres vast, inclusief poging, gebruikte template, tijdstip en
fout. Stel de totale mailstatus samen uit die resultaten. Herhaal alleen nog niet
succesvol afgehandelde ontvangers waarvoor opnieuw proberen veilig is vastgesteld,
en controleer daarbij nog steeds actuele voorwaarden.
Een succesvolle oude verzending blijft historie als het adres later wordt verwijderd.

`wp_mail()`-succes betekent acceptatie voor verzending, niet bewezen bezorging. Bij
een crash nadat de maildienst heeft geaccepteerd maar vóór de lokale registratie is
exact-eenmalige verzending zonder medewerking van die dienst niet te garanderen.
Lettermint ondersteunt idempotentie gedurende 24 uur vanaf de eerste aanvraag; de
huidige Rondo-mailer gebruikt dit nog niet. Zie hoofdstuk 10 voor de gecontroleerde
beperking en benodigde koppeling. Bij een onzekere uitkomst: toon **Verzendstatus
controleren**, herstel via de verzendregistratie en herhaal niet blind. Een eigen
blijvende registratie blijft nodig nadat de providertermijn verstrijkt.

Leg ontvangers en inhoud vast zodra een verzending begint. Een instellingwijziging
of nieuw adres halverwege creëert geen tweede batch. Dit voorkomt dubbele of
onderling verschillende mails tijdens gedeeltelijke retries.

### Rechten

Gebruik bestaande bevoegdheden voor ledenadministratie, VOG, kleding en instellingen.
Gebruikers zonder die bevoegdheid mogen geen planning wijzigen, VOG-status zetten,
kleding afhandelen of andere ontvangeradressen inzien. Test de persoonspagina ook met
een gewone gebruiker, ouder/verzorger, VOG-coördinator en kledingcoördinator.

## 7. Gecontroleerde uitgangssituatie

Deze bevindingen komen uit de broncode en de gerichte productie-inspecties tijdens
het ontwerpoverleg. Ze zijn geen bewijs dat de nieuwe automatisering al werkt.

| Bestand/onderdeel | Huidige basis en relevante beperking |
|---|---|
| `src/pages/People/PeopleOnboarding.jsx` | Handmatige selectie en verzending, aparte leden-/vrijwilligerstab |
| `includes/class-onboarding-email-sender.php` | Twee mailtypes, één primair adres, één timestamp per persoon/type; geen eigen scheduler |
| `includes/class-person-communication-policy.php` | Geldige `email_1`/`email_2`, ontdubbeling en blokkering bij overlijden; geen volledige ouderontvangerverzameling |
| `includes/class-rest-people.php` | Huidige ledenfilter gebruikt 30 dagen, vrijwilligersfilter 60 dagen; vrijwilligers uitgesloten van gewone ledenlijst |
| `includes/class-volunteer-status.php` | Herkenning uit actieve rollen; `vrijwilliger_sinds` is een datum, geen betrouwbaar 24-uurs startmoment |
| `includes/class-activation-page.php`, `includes/class-activation-service.php` | Vaste `/activeren`-route, e-mailbewijs, ouderkeuze; persoonlijke tokens nu twee uur geldig |
| `src/pages/VOG/VOGList.jsx` | Filters en acties voor e-mail, Justis-markering en herinnering; maximaal 100 resultaten zonder vervolgpagina |
| `includes/class-rest-vog.php` | Justis-markering schrijft alleen een datum; geen automatische instructiemail bij die overgang |
| `includes/class-rest-people.php` | 'Aangevraagd' kijkt naar aanwezigheid van een datum, niet naar de huidige vernieuwingsronde |
| `includes/class-vog-email.php` | Instructies en herinneringen bestaan als verzendfuncties; in de gecontroleerde routes is verzending handmatig |
| `includes/class-vog-requirement.php` | Centrale bepaling van VOG-plicht en functie-/commissie-uitzonderingen |
| `includes/class-vog-submissions.php`, `src/pages/VOG/VOGReview.jsx` | Controle, beoordeling en registratie van een geldige VOG bestaan al |
| `src/pages/Clothing/ClothingPage.jsx`, `includes/class-rest-clothing.php` | Artikelen, transacties, uitgifte/inname en eerdere uitgifteregels; geen onboardingwerkvoorraad |

Controleer bij de implementatie bovendien de huidige functie-evaluatie: de onderzochte
`is_position_current()` kan `is_current` vóór expliciete datums laten winnen. Neem dit
niet ongecontroleerd over in automatische selectie. Los alleen de noodzakelijke
gedeelde logica met gerichte regressietests op; geen brede rollenherindeling.

## 8. Uitvoeringsfasen en acceptatie

### Fase 1 — Fundament en simulatie

Inventariseer de benodigde sync-signalen, ouderadresbronnen, mailkanaalgaranties,
bevoegdheden en definitieve opslagcontracten. Bouw actuele selectie en stabiele
verzendregistratie. Voer eerst een simulatie uit die kandidaten, geplande tijden,
ontvangers en geselecteerde blokken toont, zonder te verzenden.

**Gereed wanneer:** een herhaalde import niets dubbel plant; de bestaande populatie
niet onbedoeld wordt verwelkomd; alle gekozen ontvangers verklaarbaar zijn; een
gedeelde mailbox geen accounts verwisselt; gelijktijdige verwerking is afgedekt.

### Fase 2 — Automatische ledenmail

Voeg planning, uitstellen en uitkomsten toe aan de persoonspagina. Verbind de
verzendservice met de 24-uursplanning en pas ontvangers, aanhef en activatieknop aan.
Behoud bestaande clubinhoud. Verbind de bestaande handmatige verzendroute met dezelfde
controle, zodat die de wachttijd, status en ontdubbeling niet kan omzeilen.

**Gereed wanneer:** vóór 24 uur niets uitgaat; uitstellen werkt; alle unieke adressen
één logische mail krijgen; stoppen/overgaan naar vrijwilliger vóór verzending werkt;
gedeeltelijk falen veilig kan worden hersteld; wijzigingen in rollen vlak vóór
verzending effect hebben; echte herinschrijving een nieuwe ronde opent terwijl een
seizoenswisseling dat niet doet. Vrijwilligersverzending blijft uit totdat fase 3 gereed is.

### Fase 3 — Vrijwilligersmail en blokinstellingen

Voeg de vrijwilligersplanning, voorwaardelijke blokken en voorbeeldweergave toe.
Migreer ingestelde mails zorgvuldig: toon vóór overschakelen de resulterende inhoud;
probeer vrij geschreven HTML niet blind in betekenisvolle blokken op te delen.

**Gereed wanneer:** alle blokken bewerkbaar zijn; iedere VOG-/account-/kledingvariant
juist wordt gekozen; meerdere rollen geen dubbele mail opleveren; een reeds
verwelkomd lid later correct als vrijwilliger kan worden verwelkomd; een echte terugkeer
een nieuwe ronde opent; de jonger-dan-18-grens de juiste ontvangers oplevert; gelijktijdige
leden-/vrijwilligersinstroom één gecombineerde mail oplevert. Verwijder daarna
de losse onboardingnavigatie en bied voor de oude URL een passende doorverwijzing.

### Fase 4 — VOG-werkvoorraad en opvolgmails

Introduceer aanvraagrondes, processtatussen, de koppeling van Justis-bevestiging aan
uitleg en de instelbare herinneringen. Verbind bestaande beoordelingsuitkomsten met
dezelfde voortgang. Herstel paginering en tellingen. Importeer bestaande registratie
als historie/actuele ronde alleen waar de betekenis eenduidig is.

**Gereed wanneer:** een oude aanvraagdatum geen nieuwe ronde overslaat; upload en
beoordeling herinneringen stoppen; maximaal twee logische herinneringen uitgaan;
mislukkingen geen succesvolle ontvangers opnieuw mailen; 'Opvolging nodig' zichtbaar
blijft; ook meer dan 100 personen bereikbaar zijn.

Test daarnaast dat bestaande aanvraagrondes bij inschakelen handmatig blijven,
gewijzigde termijnen/maxima alleen nieuwe rondes beïnvloeden en een beoordelingsuitkomst
één toelichtingsmail veroorzaakt zonder een nieuwe herinneringsreeks.

### Fase 5 — Kledingwerkvoorraad

Voeg de instelbare functie-selectie en **Te regelen** toe, met status, notitie,
uitgiftehistorie en handmatige afronding. Koppel actuele VOG-voorwaarden en het
vervallen van functies. Behoud bestaande uitgifte-/inname- en seizoensregels.

**Gereed wanneer:** de gekozen trainers/leiders/teammanagers één keer verschijnen;
gedeeltelijke uitgifte niet automatisch afrondt; **Kleding geregeld** wordt gelogd;
geen uitgifte een reden vereist; functie- of VOG-wijzigingen de open werkvoorraad
correct aanpassen zonder eerdere kledinghistorie te veranderen.

Test terugkeer met eerdere kledinguitgifte: opnieuw één werkvoorraadvermelding,
zichtbare historie en handmatige beslissing. Test dat ontbreken van VOG-plicht
direct **Kleding regelen** oplevert zonder extra vrijgave.

### Fase 6 — Gecontroleerd inschakelen

Controleer de productieconfiguratie, ingestelde teksten, ontvangers en simulatieresultaat.
Schakel alleen de afgesproken populatie en berichtsoorten in. Controleer de echte
verzendregistratie en de geauthenticeerde persoon-, VOG- en kledingpagina's. Een
geslaagde build of HTTP-respons alleen is geen bewijs van een werkende gebruikersflow.

Uitschakelen stopt toekomstige automatische verzending; behoud planning/historie en
werkvoorraden. Na een langere pauze of storing moeten achterstallige welkomstmails
eerst worden beoordeeld. Ze blijven geblokkeerd totdat een bevoegde beheerder de
betreffende kandidaten heeft beoordeeld en vrijgegeven of laten vervallen. Deze
uitzondering bij herstel verandert niets aan de normale automatische 24-uursroute.

Herinneringen van automatisch gevolgde rondes hervatten zonder inhaalslag: geen
reeks gemiste mails achter elkaar, behoud van maximum en minstens het afgesproken
interval vóór de volgende herinnering. Oude handmatige rondes blijven handmatig.
De grens voor een 'langere onderbreking' en de concrete beoordeling op de bestaande
pagina's moeten in fase 1/6 nog worden vastgelegd.

### Checks per implementatiemijlpaal

Gerichte tests voor selectie, datums/tijdzone, herhaalde/concurrente verwerking,
ontvangerontdubbeling, accountkeuze, veldrechten, aanvraagrondes, reminderlimieten en
handmatige kledingafronding. Voer de vereiste JavaScript-lint/build, PHP-codingstandards
en relevante PHP-tests uit; houd de volledige suite groen volgens `docs/testing.md`.

Werk bij implementatie de bijbehorende API-/featuredocumentatie bij in
`../developer/src/content/docs/`, en versie/changelog volgens de repositoryregels.
Commit en push per mijlpaal. Productiereleases lopen via de CI/deploy-workflow op
`main`; vanuit een worktree wordt niet naar productie uitgerold. Zet geen automatische
productiemails aan als bijeffect van een code-deploy.

Deze planningswijziging blijft uitsluitend in `docs/prd/`: geen versie-update,
changelog, developer-sitewijziging of productiedeployment nodig.

## 9. Beslispunten vóór de betreffende implementatiefase

De volgende details zijn nog niet expliciet gekozen. Ze veranderen de hierboven
vastgelegde hoofdlijn niet en mogen niet onzichtbaar als productbeleid worden ingevuld.

| Beslispunt | Nodig vóór | Voorstel / consequentie |
|---|---|---|
| Voorinschrijving | Fase 1/2 | Bepaal nog wanneer een voorinschrijving als echte inschrijving telt. Toekomstige ingangsdatum en overschrijving zijn gekozen: wacht minimaal 24 uur vanaf de latere van ingangsdatum/herkenning en verstuur niet zolang de overschrijving openstaat |
| Betrouwbaar bewijs van stoppen en terugkeer | Fase 1/2/3 | Productkeuze staat vast: opnieuw verwelkomen; technische detectie moet tijdelijke sync-gaten en seizoenswisselingen uitsluiten |
| Gecombineerde mail en verschillende ontvangerregels | Fase 3 | Ledenmail omvat ouderadressen; vrijwilligers-/VOG-mail alleen onder 18. Voorstel: één gecombineerde mail per toegestane ontvanger, met alleen ledenblokken voor eventuele andere ledenmailontvangers |
| Welkomstmail bij VOG in controle of opnieuw aanleveren | Fase 3 | Eigen bewerkbare tekst nodig die de werkelijke vervolgstap beschrijft |
| Vernieuwing binnenkort en aanvraag al actief | Fase 4 | Bestaande geldigheids-/vernieuwingsgrenzen centraal gebruiken; actuele aanvraag voorkomt een dubbele start |
| Kledingtekst bij terugkeer | Fase 3/5 | Werkvoorraad wordt heropend met historie; tekst moet beoordeling van benodigde kleding beloven, geen automatisch nieuw volledig pakket |
| Definitie en bediening van langere onderbreking | Fase 1/6 | Achterstallige welkomstmails eerst beoordelen is gekozen; bepaal tijdsgrens en bediening binnen persoon-/bestaande beheerpagina's |

## 10. Technische inventarisatie — 12 september 2026

**Conclusie:** de bestaande functies bieden bruikbare bouwstenen, maar de huidige
verzending kan nog niet betrouwbaar automatisch worden ingeschakeld. Vooral complete
syncgegevens, alle bronadressen en blijvende verzendregistratie ontbreken als gedeeld
contract. Deze inventarisatie verandert geen software of productie-instellingen.

### Sync: 24 uur wachten bewijst niet dat alle gegevens binnen zijn

De live crontab van de Rondo Sync-service bevat onderstaande planning. Dit bevestigt
de ingestelde frequentie, niet dat iedere uitvoering daadwerkelijk is geslaagd. De
tijden zijn de crontabwaarden; de servertijdzone is hierbij niet apart geverifieerd.

| Pipeline | Ingestelde uitvoering |
|---|---|
| Mensen en ouders | Dagelijks 08:00, 11:00, 14:00 en 17:00 |
| Functies, recente selectie | Dagelijks 07:30, 10:30, 13:30 en 16:30 |
| Teams | Zondag 06:00 |
| Functies, volledige selectie | Zondag 01:00 |

`rondo-sync/pipelines/sync-people.js`, `sync-functions.js`, `sync-teams.js` en
`sync-all.js` verwerken verschillende delen van de benodigde gegevens. Alleen een
persoon opslaan of 24 uur laten verstrijken bewijst dus niet dat ouderrelaties,
teamfuncties en commissiefuncties compleet zijn.

Ook bestaande tijdstempels zijn onvoldoende:

- `prepare-rondo-club-members.js` vertaalt `MemberSince`, `RelationEnd` en de
  lidsoort naar onder meer `lid_sinds`, `lid_tot` en `former_member`. Een bevestigde
  opeenvolging van lidmaatschapsperiodes wordt daarbij niet als aparte historie bewaard.
- `submit-rondo-club-sync.js` kan een bestaande ouderpersoon als lid hergebruiken;
  de WordPress-aanmaakdatum is daardoor geen lidmaatschapsstart. Ontbreken in de
  bron kan bovendien tot `former_member` leiden zonder bewijs van echte uitschrijving.
- `lib/rondo-club-db.js` bewaart `created_at`, actualiseert `last_seen` en vervangt
  brongegevens vóór succesvolle verwerking in WordPress. Deze waarden bewijzen geen
  afgeronde import. De sync heeft bovendien een pad dat een fout als `skipped`
  teruggeeft zonder die in de algemene foutenlijst op te nemen.
- `rondo_fields_saved_post` in `includes/class-fields.php` is een bruikbaar
  wijzigingssignaal, maar wordt alleen bij gewijzigde velden uitgevoerd en markeert
  geen voltooide sync. `vrijwilliger_sinds` is alleen een datum en wordt niet bij
  iedere echte terugkeer opnieuw gezet.

**Implementatievoorstel:** laat Sync via een geauthenticeerd contract per persoon
melden welke relevante bronnen succesvol verwerkt zijn, met stabiele uitvoeringssleutel
en waarnemingstijd. Ongewijzigde personen moeten dat signaal eveneens kunnen krijgen.
WordPress beheert vervolgens de uitgangspopulatie, lidmaatschaps-/vrijwilligersrondes
en planning. Ontbrekende dekking houdt verzending tegen en betekent niet 'geen rol'.
Een fout bij een niet relevante foto- of nieuwsbriefstap hoeft geen blokkade te zijn.
Zowel de volledige als de afzonderlijke pipelines moeten dit contract ondersteunen.

**Gekozen werkwijze:** start voor nieuwe leden een gerichte volledige controle van
de benodigde gegevens, zonder op de reguliere wekelijkse teamsync te wachten. Het
voltooiingssignaal bevestigt per onderdeel zowel ophalen als opslaan, inclusief een
succesvol gecontroleerde lege uitkomst. Een ontbrekende of mislukte stap blokkeert
verzending, ook na 24 uur. Hoe Sync deze gerichte controle uitvoert en hervat, wordt
binnen fase 1 uitgewerkt; de keuze activeert nog geen sync of productiemails.

Leg bij de eerste volledige waarneming bestaande leden/vrijwilligers als uitgangssituatie
vast zonder welkomstmail. Gebruik voor terugkeer aantoonbare beëindiging en een nieuwe
periode; alleen verdwijnen en terugkomen in een import mag geen ronde openen. De
concrete bronvoorwaarden voor voorinschrijving blijven een beslispunt. Voor
overschrijving is gekozen dat de welkomstmail op afronding wacht; verifieer bij
implementatie welk brongegeven die afronding betrouwbaar bevestigt.

Corrigeer vóór automatische rolselectie de gedeelde datumlogica in
`includes/class-volunteer-status.php`: expliciete begin-/einddatums moeten vóór een
verouderd `is_current` gelden; een toekomstige rol mag niet alvast activeren.

### Ontvangers: bewaar ook een ouderadres zonder oudernaam

`rondo-sync/steps/prepare-rondo-club-parents.js` slaat bij de eerste waarneming van
een mailbox de ouder over wanneer `NameParent1`/`NameParent2` ontbreekt, ook bij een
geldig `EmailAddressParent1`/`EmailAddressParent2`. Alleen de bestaande gekoppelde
ouderpersonen verzamelen voldoet daardoor niet aan 'alle opgegeven adressen'. Ouders
worden bovendien per mailbox samengevoegd; een mailbox is geen unieke natuurlijke persoon.

**Gekozen oplossing:** sla een ouder met een geldig opgegeven adres niet langer over
bij een ontbrekende naam. Gebruik **Ouder van {voornaam kind}** als vervangende naam
in de ouderimport en behoud het adres en de bronkoppeling aan het kind. Een echte
oudernaam heeft voorrang; de vervangende naam is geen bewijs van identiteit. Eén
gedeelde ontvangerservice combineert eigen `email_1`/`email_2` en de toegestane
ouderadressen/-relaties, valideert en ontdubbelt per persoon. De bestaande samenvoeging
op mailbox mag geen dubbele ouderrecords veroorzaken door verschillende kindnamen.
Voor vrijwilligers/VOG bepaalt de geboortedatum of ouderadressen mogen meedoen; een
onbekende leeftijd is geen bewijs van minderjarigheid. De omgang met ontbrekende
geboortedatums moet zichtbaar zijn in de simulatie.

`includes/class-activation-service.php` zoekt nu alleen gepubliceerde personen via
`email_1`/`email_2`. Door het adres bij de geïmporteerde ouder te bewaren kan deze
bestaande zoekroute worden gebruikt. Verifieer bij implementatie de activatie van zo'n
ouder, met e-mailbewijs en behoud van de bestaande account-/kindkeuze. Een uitsluitend
bij het kind bewaard adres zou deze zoekroute nog missen. Gebruik geen synthetische
WordPress-gebruikersadressen uit `ContactEmailRouter` als mailontvangers.

De bestaande `PersonCommunicationPolicy` dekt overlijden, geldigheid en ontdubbeling
van eigen adressen, maar raadpleegt niet de Lettermint-suppressieregistratie. Neem
bekende blokkades mee in de nieuwe verzending en toon het betreffende adresprobleem;
maak zo'n adres niet automatisch opnieuw verzendbaar.

### Mailkanaal: ondersteuning aanwezig, koppeling nog niet

Productie heeft een geconfigureerde Lettermint-integratie; alleen de aanwezigheid van
configuratie is gecontroleerd, zonder tokenwaarden te tonen of een testmail te sturen.
De geïnstalleerde SDK biedt `EmailEndpoint::idempotencyKey()` en retourneert een
berichtnummer en status. `includes/class-lettermint-mailer.php` gebruikt die sleutel
niet en gooit het resultaat van `send()` weg. Een exception wordt `false`, zonder
onderscheid tussen aantoonbaar niet verzonden en mogelijk al geaccepteerd.

Lettermint bewaart een sleutel per project gedurende **24 uur vanaf eerste gebruik**.
Dezelfde sleutel en identieke inhoud leveren binnen die termijn hetzelfde resultaat
zonder nieuwe verzending. Afwijkende inhoud of een gelijktijdige aanvraag geeft een
conflict; na 24 uur wordt de sleutel als nieuw behandeld. Dit is onafhankelijk van
de gekozen wachttijd vóór de welkomstmail.
Bron: [Lettermint: idempotency](https://lettermint.co/docs/platform/emails/idempotency),
gecontroleerd op 12 september 2026.

**Benodigde koppeling:** leg vóór de eerste aanvraag per ontvanger een blijvende
verzendsleutel, exclusieve claim en volledige onveranderlijke payload vast, inclusief
metadata. Gebruik expliciet de SDK-methode voor de API-sleutel; een willekeurige extra
mailheader via de huidige mailer is hiervoor geen bewezen koppeling. Bewaar daarna
berichtnummer, acceptatiestatus, pogingstijden en foutsoort. Herhaal een onzekere
aanvraag uitsluitend met dezelfde sleutel/payload binnen de providertermijn; zet een
onzekere oudere aanvraag op **Verzendstatus controleren**. Nieuwe sleutels of gewijzigde
inhoud mogen de bescherming niet omzeilen. Als het vereiste transport ontbreekt, mag
deze automatische stroom niet ongemerkt terugvallen op een ander mailkanaal.

`includes/class-lettermint-webhook.php` handelt nu hard bounces en spamklachten af,
bewaart suppressies en maakt daarvoor algemene taken. De nieuwe mailstromen moeten
via expliciete persoon-, ronde- en verzendmetadata hun eigen uitkomst bijwerken en
problemen op de persoon-/VOG-pagina tonen. Alleen zoeken op ontvangeradres is onvoldoende
bij een gedeelde oudermailbox. Behoud de bestaande taakafhandeling voor andere
mailstromen. Verwerk herhaalde en verkeerd geordende callbacks zonder een succes terug
te zetten naar 'opnieuw verzenden'. Acceptatie is nog geen bewezen bezorging.

### Productieplanning: externe WordPress-trigger nog te verifiëren

De live WordPress-configuratie heeft `DISABLE_WP_CRON=true` en tijdzone
`Europe/Amsterdam`. Bij de gerichte inspectie van onboarding-/VOG-hooks was alleen
de dagelijkse `rondo_vog_cleanup` geregistreerd; geen nieuwe onboarding- of
VOG-herinneringsscheduler. Dit is geen volledige inventaris van alle WordPress-cron.

De hosting-shell biedt geen `crontab`-commando. Daardoor is de externe aansturing van
WordPress-cron nog niet vastgesteld; dit bewijst niet dat deze ontbreekt of defect is.
Controleer vóór inschakelen de hostingplanner en een werkelijk uitgevoerde, onschuldige
controleactie. Een geregistreerde WordPress-hook alleen is onvoldoende bewijs.

### Afgebakende eerste bouwstap en bewijs

Begin met het voltooiingscontract tussen Sync en Club, de uitgangspopulatie en rondes,
het behoud van alle bronadressen, en de gedeelde ontvanger-/verzendregistratie. Kies
hiervoor definitieve native opslagcontracten en toets de exclusieve claim met werkelijk
gelijktijdige verwerking; een 'uniek' postmeta-kenmerk alleen bewijst geen exclusiviteit.
Voeg vervolgens een simulatie toe met kandidaat, reden, brondekking, geplande tijd,
ontvangers, blokkades en geselecteerde mailblokken. De simulatie verstuurt niets.

Minimaal te bewijzen vóór fase 2: herhaalde en gedeeltelijk mislukte imports, hergebruik
van een ouderpersoon, tijdelijke bronafwezigheid, echte terugkeer, rollen met toekomstige
datums, ouderadres met vervangende naam en werkende activatie, voorrang van een echte
oudernaam, twee kinderen met dezelfde mailbox, gedeeltelijke
mailacceptatie, crash na acceptatie, providertermijn verstreken en herhaalde callbacks.
Test handmatig verzenden en cron tegen dezelfde claim. De simulatie en deze tests zijn
tijdens deze inventarisatie nog niet gebouwd of uitgevoerd.

De fases in hoofdstuk 8 beschrijven bouwvolgorde. Vrijwilligersmails die VOG- of
kledingopvolging beloven mogen pas worden ingeschakeld wanneer ook die werkvoorraden
en bijbehorende flows gereed zijn. Een code-release is geen toestemming om bestaande
personen alsnog automatisch te mailen.
