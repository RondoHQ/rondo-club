# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [33.70.0] - 2026-07-21

### Added

- Feedbackstatussen kunnen veilig via WP-CLI worden gewijzigd met `wp rondo feedback set-status <id> <status>`. De opdracht gebruikt dezelfde statusovergangen, oplostijd en eenmalige e-mailnotificatie als de REST API.

## [33.69.0] - 2026-07-21

### Added

- De indiener van feedback ontvangt een eenmalige, opgemaakte e-mail met een link naar het feedbackitem zodra de status naar **Opgelost** verandert. Gezinsaccounts gebruiken daarbij het echte contactadres in plaats van een technisch WordPress-adres.

## [33.68.10] - 2026-07-21

### Fixed

- Bij het maken of bewerken van een losse inschrijftaak hoeft de datum nog maar één keer te worden ingevuld. Begin- en eindtijd gebruiken automatisch dezelfde datum en de eindtijd moet na de begintijd liggen.

## [33.68.9] - 2026-07-21

### Removed

- Kalenderdeelnemers worden niet langer automatisch aan personen gekoppeld. De matcher, cache-invalidatie bij persoonswijzigingen, uitgestelde hermatching en het bijbehorende WP-CLI-commando zijn verwijderd om onnodige databasequeries en cronwerk te stoppen.

## [33.68.8] - 2026-07-21

### Changed

- Kalenderkoppelingen worden alleen opnieuw gematcht wanneer een vast e-mailadres van een persoon wijzigt. Wijzigingen uit dezelfde importsessie worden per eigenaar samengevoegd tot één uitgestelde cronjob.
- Verwachte REST-responses voor authenticatie, autorisatie, overlapwaarschuwingen en paginering worden niet langer als applicatiefout naar `debug.log` geschreven.

### Fixed

- Lettermint-mails zonder expliciete `Content-Type`-header gebruiken nu betrouwbaar `text/plain`, zodat onder meer standaard WordPress-mails niet meer worden geweigerd vanwege lege `metadata.content_type`.

## [33.68.7] - 2026-07-20

### Fixed

- Personen kunnen niet meer naar de prullenbak worden verplaatst of definitief worden verwijderd zolang hun profiel nog een actieve relatie met een andere persoon bevat. De verwijdermelding noemt de gekoppelde personen, zodat de relatie eerst kan worden gecorrigeerd of verwijderd.

## [33.68.6] - 2026-07-20

### Fixed

- **Datakwaliteit** onder Vrijwilligers meldt alleen nog spelende leden waarbij Sportlink wel een spelactiviteit maar geen leeftijdsgroep bevat; niet-spelende ouders, sponsorcontacten en andere contacten worden niet langer ten onrechte als probleem geteld.
- Ex-leden worden nog steeds buiten de vrijwilligersdoelgroep gehouden, maar staan niet langer als datakwaliteitsprobleem op het dashboard.

## [33.68.5] - 2026-07-20

### Changed

- Een oud-lid met een actueel kind bij de club wordt op het persoonsprofiel nu herkenbaar getoond als **Oud-lid · ouder/verzorger**. De historische lidmaatschapsgegevens blijven alleen-lezen, terwijl de actuele oudercontactgegevens via Sportlink worden bijgehouden.

### Fixed

- De oudersync kan actuele contact- en adresgegevens van een oud-lid dat ook ouder/verzorger is verversen zonder diens naam, KNVB-ID of lidmaatschapshistorie te overschrijven.

## [33.68.4] - 2026-07-20

### Fixed

- De Sportlink-sync kan een terugkerende ouder nu ook op e-mailadres in de WordPress-prullenbak vinden, zodat het bestaande ouderrecord wordt hersteld in plaats van een sibling te hergebruiken of een duplicaat aan te maken.

## [33.68.3] - 2026-07-20

### Changed

- De catalogus met soorten inschrijftaken opent op **Vrijwilligers → Inschrijftaken** voortaan via de knop **Inschrijftaken** naast **Sjablonen**, in plaats van als vast blok onder de kalender.
- Diensten van een aangeklikte kalenderdag openen op de beheerpagina voortaan in een popover bij de gekozen datum.

## [33.68.2] - 2026-07-20

### Changed

- De namen in **Recente aanmeldingen** onder **Vrijwilligers → Inschrijftaken** linken nu rechtstreeks naar het bijbehorende ledenprofiel.

## [33.68.1] - 2026-07-20

### Fixed

- De knop **Wijzigen** onder **Beheer → Gebruikers** opent de accountkoppeling nu direct in een zichtbare modal, ook wanneer de gebruikerstabel langer is dan het scherm.

## [33.68.0] - 2026-07-20

### Changed

- Het blok onderaan **Vrijwilligers → Inschrijftaken** toont niet langer de laatst aangemaakte diensten, maar maximaal 50 diensten met de recentste actuele aanmeldingen. Per dienst staan de ingeschreven namen en het tijdstip van de laatste aanmelding; de dienstnaam opent direct de diensteditor.

## [33.67.0] - 2026-07-20

### Added

- Ledenprofielen tonen alle komende inschrijftaken en de twee meest recente verstreken inschrijftaken van die persoon, inclusief datum, tijd en status.
- Het nieuwe persoonsendpoint `GET /rondo/v1/people/{person_id}/shifts` levert dit beperkte overzicht met dezelfde toegangscontrole als het ledenprofiel en zonder persoonsgegevens van mede-vrijwilligers.

## [33.66.0] - 2026-07-20

### Added

- Ouders en verzorgers die nog niet als eigen persoon uit Sportlink zijn gesynchroniseerd, kunnen Rondo tijdelijk via het account van hun jeugdlid gebruiken. Rondo vraagt hun naam, toont direct de gezinsplicht en informeert de ledenadministratie.
- Beheerders kunnen onder **Beheer → Gebruikers** de persoonskoppeling van een account wijzigen zodra de ouder uit Sportlink is gesynchroniseerd.

### Changed

- Bij het wijzigen van een accountkoppeling verhuizen de via dat account gemaakte inschrijvingen en de VOG/IVA-gegevens van het kind naar de ouder. Afwijkende bestaande certificaatgegevens op de ouder blokkeren de verhuizing om overschrijven te voorkomen.
- Persoonlijke en beheerweergaven van inschrijftaken tonen tijdens de tijdelijke koppeling de opgegeven naam van de ouder/verzorger.

## [33.65.0] - 2026-07-20

### Added

- Het vrijwilligersdashboard toont hoeveel Rondo-gebruikersaccounts aan een persoon zijn gekoppeld.

## [33.64.2] - 2026-07-20

### Fixed

- De voortgangskaart op de persoonlijke vrijwilligerspagina trekt reeds ingeplande inschrijftaken nu af van het aantal dat nog moet worden ingepland. Wanneer de volledige plicht al is ingepland, bevestigt de kaart dat expliciet in plaats van ten onrechte om extra inschrijftaken te vragen.

## [33.64.1] - 2026-07-19

### Fixed

- Vrijwilligersbeheerders (rol met `vrijwilligers`-capability, zonder de generieke `edit_posts`) kunnen nu sjablonen uitrollen en opnieuw uitrollen. De REST-endpoints `POST /rondo/v1/shift-templates/expand` en `.../{id}/rerun` controleerden op `edit_posts` — die capability wordt bij niet-admin Rondo-rollen juist verwijderd — en checken nu op `manage_options` of `vrijwilligers`, gelijk aan de overige vrijwilligers-endpoints.

## [33.64.0] - 2026-07-19

### Added

- Sjablonen kunnen per stuk opnieuw worden uitgerold ("Opnieuw uitrollen" op het sjabloonscherm): nog niet aangepaste, toekomstige inschrijftaken worden verwijderd en opnieuw aangemaakt met de huidige sjablooninstellingen. Handmatig aangepaste inschrijftaken, inschrijftaken met aanmeldingen en geannuleerde inschrijftaken blijven ongewijzigd.
- Het inschrijftaakscherm toont nu of een taak van een sjabloon komt en of hij is losgekoppeld door een handmatige aanpassing.

### Changed

- Een uitgerolde inschrijftaak wordt automatisch losgekoppeld van zijn sjabloon zodra je hem handmatig bewerkt. De koppeling (herkomst) blijft zichtbaar, maar opnieuw uitrollen laat de taak voortaan met rust.

### Fixed

- Het bewerken (of opnieuw opslaan) van een sjabloon-inschrijftaak leverde geen dubbele inschrijftaak meer op bij de volgende nachtelijke uitrol: de idempotentiecheck herkent nu zowel de `Y-m-d H:i`- als de `Y-m-d H:i:s`-notatie van de starttijd.

## [33.63.6] - 2026-07-17

### Changed

- Inschrijftaakkaarten op de persoonlijke vrijwilligerspagina tonen geen diensttype-kleurbalk meer; de kleurcodering blijft beschikbaar in het beheeroverzicht.

## [33.63.5] - 2026-07-17

### Fixed

- Kalenderbijlagen bij bevestigingsmails bewaren de geplande Nederlandse aanvangs- en eindtijd met expliciete `Europe/Amsterdam`-tijdzone, zodat agenda-apps geen extra uur optellen.

## [33.63.4] - 2026-07-17

### Fixed

- Links in het informatieblok op de vrijwilligerspagina openen nu in een nieuw tabblad.

## [33.63.3] - 2026-07-17

### Changed

- De instelling voor de e-mail na IVA-goedkeuring staat nu logisch gegroepeerd onder **Beheer → E-mails → IVA-goedkeuring** in plaats van bij de algemene clubconfiguratie.

## [33.63.2] - 2026-07-17

### Fixed

- Datums in bevestigings-, herinnerings- en annuleringsmails voor inschrijftaken worden altijd met Nederlandse dag- en maandnamen weergegeven, onafhankelijk van de WordPress-locale.

## [33.63.1] - 2026-07-17

### Fixed

- De kalenderpopover voor inschrijftaken gebruikt de beschikbare ruimte beter, zodat datum- en bezettingsteksten niet onnodig smal worden weergegeven.

## [33.63.0] - 2026-07-17

### Added

- Leden ontvangen tien minuten na hun eerste aanmelding één gecombineerde bevestigingsmail voor alle nieuw geplande inschrijftaken, inclusief een `.ics`-bestand om ze direct aan hun agenda toe te voegen.

## [33.62.2] - 2026-07-17

### Fixed

- De vrijstellingsmelding op `/vrijwillig` noemt een actieve vrijwilligersrol niet langer ten onrechte altijd een commissierol.

## [33.62.1] - 2026-07-17

### Fixed

- Vrijwilligers kunnen zich op `/vrijwillig` aanmelden voor twee aansluitende inschrijftaken waarvan de eind- en starttijd in dezelfde minuut vallen.

## [33.62.0] - 2026-07-15

### Added

- Beheerders kunnen het onderwerp en de berichttekst van de e-mail na IVA-goedkeuring aanpassen via de clubinstellingen.

## [33.61.0] - 2026-07-15

### Added

- Leden ontvangen na goedkeuring van een geldig IVA-certificaat een e-mail met een directe link naar de beschikbare inschrijftaken.

## [33.60.5] - 2026-07-15

### Changed

- De interne WordPress-verwijzing is verwijderd uit de uitleg op het IVA-beheerscherm.

## [33.60.4] - 2026-07-15

### Fixed

- IVA-certificaten worden vanuit het beheer- en profielscherm geauthenticeerd opgehaald, zodat **Bekijk** niet meer eindigt in een `rest_forbidden`-fout.

## [33.60.3] - 2026-07-15

### Changed

- Op `/vrijwillig` verschijnen de inschrijftaken van een gekozen kalenderdatum nu in een popover bij die datum, zodat naar beneden scrollen niet meer nodig is.

## [33.60.2] - 2026-07-14

### Fixed

- Lokale `graphify-out/`-artefacten worden genegeerd door Git en uitgesloten van productiedeploys; Graphify blijft verwijderd uit het project.

## [33.60.1] - 2026-07-14

### Fixed

- Op het beheerscherm van een inschrijftaak worden aangemelde vrijwilligers met hun naam getoond in plaats van alleen als `Persoon {id}`.

## [33.60.0] - 2026-07-14

### Changed

- Op het vrijwilligersdashboard zijn de statistieken voor gezinnen en spelers vervangen door het totale aantal plekken in inschrijftaken en het aantal plekken waarvoor al een vrijwilliger is ingeroosterd.

## [33.59.3] - 2026-07-14

### Changed

- Op **Vrijwilligers → Inschrijftaken** staat het bezettingsoverzicht nu vóór de catalogus; recente inschrijftaken blijven onderaan.
- De gebruikersgerichte termen **Diensttypes**, **Diensten** en **Shifts** zijn binnen de vrijwilligersfunctionaliteit vervangen door **Inschrijftaken**; technische routes en datasleutels blijven ongewijzigd.

## [33.59.2] - 2026-07-14

### Changed

- Ook het bezettingsoverzicht voor leden op **Vrijwillig** toont nu de huidige en de komende vijf kalendermaanden.

## [33.59.1] - 2026-07-14

### Changed

- Het bezettingsoverzicht op **Vrijwilligers → Diensten** toont nu de huidige en de komende vijf kalendermaanden.

## [33.59.0] - 2026-07-14

### Added

- De knop **Uitrollen** bij vrijwilligerssjablonen vraagt nu om een einddatum en maakt diensten aan tot en met die gekozen datum.

## [33.58.2] - 2026-07-13

### Fixed

- Na het wijzigen van de sponsorrol wordt de persoonspagina volledig ververst, zodat alle rol-afhankelijke velden en rechten direct de nieuwe situatie tonen.

## [33.58.1] - 2026-07-13

### Fixed

- Het veld **Bedrijfsnaam** op de persoonspagina wordt alleen getoond bij een actieve sponsorrol, onafhankelijk van het persoonstype.

## [33.58.0] - 2026-07-13

### Added

- Personen kunnen nu tegelijk lid of contact én sponsor zijn via de onafhankelijke sponsorrol `is_sponsor`.
- De personenlijst heeft een apart sponsorfilter en toont **+ sponsor** naast het bestaande persoonstype.

### Changed

- `person_type` kent alleen nog `member` en `contact`; sponsorstatus en pasvariant staan daar los van.
- Een actieve sponsorrol krijgt voorrang bij de digitale pas en valt na beëindiging terug op een eventuele ledenpas.
- Sponsorbeheerders kunnen op dubbelrolrecords uitsluitend sponsorvelden wijzigen en kunnen het onderliggende lid niet verwijderen.

## [33.57.1] - 2026-07-13

### Fixed

- De velden **Persoonstype** en **Pasvariant** op de sponsorpagina hebben nu consistente horizontale en verticale tussenruimte.

## [33.57.0] - 2026-07-13

### Added

- Per diensttype zijn het onderwerp en de berichttekst van beide annuleringsmails instelbaar: minimaal 48 uur vooraf en binnen 48 uur. De bestaande standaardteksten blijven actief als terugval en ondersteunen dezelfde persoonlijke variabelen als de herinneringsmails.

## [33.56.0] - 2026-07-13

### Added

- Vrijwilligerscoördinatoren kunnen een bezette dienst gecontroleerd annuleren. Alle aangemelde vrijwilligers ontvangen automatisch een persoonlijke e-mail met de dienstgegevens en een optionele reden.
- Annuleringen binnen 48 uur voor aanvang tellen automatisch mee voor de vrijwilligersplicht; eerdere annuleringen niet. Geannuleerde diensten en hun aanmeldingen blijven als auditbare historie bewaard.

### Changed

- Diensten met aanmeldingen kunnen niet meer definitief worden verwijderd of rechtstreeks op **Geannuleerd** worden gezet. De ledenweergave toont geannuleerde diensten onder **Historie** met de melding of de dienst meetelt.

## [33.55.0] - 2026-07-13

### Added

- Beheerders kunnen onder **Instellingen → Club → Huisstijl** een apart Businessclub-logo uploaden voor Businessclub AWC-passen. Zonder instelling blijft het meegeleverde Businessclub-logo actief.

## [33.54.0] - 2026-07-13

### Added

- Bij het toevoegen van een sponsor is de pasvariant voortaan verplicht: **Businessclub AWC** gebruikt de witte Businessclub-pas met het Businessclub-logo, terwijl **AWC Sponsor** een witte pas met titel **AWC Sponsor** en het gewone AWC-logo krijgt. De keuze blijft wijzigbaar op de persoonspagina.

## [33.53.3] - 2026-07-13

### Changed

- Apple Wallet- en Google Wallet-passen voor sponsoren gebruiken voortaan het eigen Businessclub AWC-logo.

## [33.53.2] - 2026-07-13

### Changed

- De lidpasscanner toont bij een gescande sponsorpas **Bedrijf** met de bedrijfsnaam in plaats van een KNVB ID.

## [33.53.1] - 2026-07-13

### Changed

- Sponsorpassen tonen bovenaan **Businessclub AWC** en vervangen de velden Teams en Functies door **Bedrijf** met de bedrijfsnaam.

## [33.53.0] - 2026-07-13

### Added

- Businessclubleden en sponsoren kunnen als persoonstype **Sponsor** worden toegevoegd, beheerd en gefilterd. De ingebouwde rol **Rondo Sponsorbeheerder** mag uitsluitend sponsorrecords toevoegen, aanpassen en verwijderen.
- Sponsors krijgen automatisch een digitale toegangspas. De Apple Wallet- en Google Wallet-passen hebben een witte achtergrond en tonen **Sponsor** als pastype.

## [33.52.0] - 2026-07-13

### Added

- Beheerders kunnen onder **Instellingen → Club** een clubspecifiek informatieblok met links en opmaak instellen. Het blok verschijnt direct onder de introductie op `/vrijwillig` en blijft verborgen wanneer het veld leeg is.

## [33.51.1] - 2026-07-13

### Fixed

- Het totaal op `/vrijwilligers` telt nu de werkelijk vereiste diensten per plichteenheid op. Gezinnen met meerdere jeugdleden tellen daardoor volgens de bestaande gezinskorting mee voor meer dan twee diensten, in plaats van slechts als één eenheid te worden getoond.

## [33.51.0] - 2026-07-12

### Added

- Wie via `/activeren` een e-mailadres indient waarvoor alle accounts al bestaan, ontvangt voortaan een eenmalige Magic Login-link. Gedeelde gezinsadressen krijgen één e-mail met een herkenbare, benoemde link per account.

### Changed

- De neutrale bevestiging op `/activeren` vermeldt dat de e-mail zowel een activatie- als directe inloglink kan bevatten, zonder prijs te geven of het adres bekend is.

## [33.50.0] - 2026-07-12

### Added

- De openbare accountactivatiepagina heeft een eigen Open Graph-afbeelding van 1200×630 pixels en bijpassende Open Graph- en Twitter Card-metadata voor herkenbare previews bij het delen van `/activeren`.

## [33.49.0] - 2026-07-12

### Added

- Een gedeelde bezettingskalender op `/vrijwilligers/diensten` en `/vrijwillig` toont de komende drie kalendermaanden per datum: groen wanneer alle diensten gevuld zijn en rood wanneer nog plekken openstaan. Beide weergaven ondersteunen een deelbaar diensttypefilter en tonen details na het kiezen van een datum.
- Het beveiligde endpoint `GET /rondo/v1/shifts/calendar` levert geaggregeerde bezetting voor vrijwilligerscoördinatoren en een persoonsgebonden, privacyveilige aanmeldweergave voor leden.

### Changed

- De beschikbare-dienstenlijst op `/vrijwillig` is vervangen door de kalender; de persoonlijke lijst onder “Mijn diensten” blijft behouden.
- Terugkerende dienstsjablonen worden 93 dagen vooruit uitgerold, zodat iedere periode van drie kalendermaanden volledig gevuld kan worden.

### Fixed

- Rechtstreekse aanmeldverzoeken respecteren nu ook de status als actief lid en de vereiste vrijwilligerspool, naast de bestaande VOG- en IVA-controles.

## [33.48.6] - 2026-07-12

### Fixed

- Force fresh Memcached reads for volunteer-shift locks and assignees, so concurrent requests cannot retain stale per-request cache values.

## [33.48.5] - 2026-07-12

### Fixed

- Refresh the per-request WordPress caches while waiting for a volunteer-shift write lock, preventing stale lock and assignee values when Memcached is active.

## [33.48.4] - 2026-07-12

### Fixed
- Gelijktijdige aanmeldingen, afmeldingen en beheer-verwijderingen voor dezelfde vrijwilligersdienst worden per dienst geserialiseerd met een korte WordPress-option-lock. Daardoor kunnen parallelle wijzigingen aan de `assigned_persons`-lijst elkaar niet meer overschrijven.

## [33.48.3] - 2026-07-12

### Added
- Herbruikbare, strikt demo-only loadtesttooling voor de vrijwilligersreis. De fixturetool maakt en verwijdert gemarkeerde synthetische vrijwilligers, accounts en diensten; de k6-test meet unieke logins, vrijwilligerslijsten en gelijktijdige inschrijvingen met een afzonderlijke data-integriteitscontrole.

## [33.48.2] - 2026-07-12

### Changed
- De vrijstellingsmelding op `/vrijwillig` legt voor actieve commissieleden duidelijker uit waarom zij geen diensten hoeven in te plannen en dat zij wel mogen meedoen.

## [33.48.1] - 2026-07-12

### Removed
- De lokale Graphify-integratie, inclusief de verplichte agentinstructies, Claude-hook en deployconfiguratie. De verouderde kennisgraph indexeerde voornamelijk dependencies en gegenereerde bestanden en vertraagde daardoor ontwikkelwerk zonder bruikbare architectuurinformatie te leveren.

## [33.48.0] - 2026-07-12

### Added
- Op `/vrijwillig` kunnen beschikbare en eigen diensten worden gefilterd op diensttype. De keuze staat in de URL als `?diensttype=<id>`, zodat een gefilterd overzicht direct gedeeld kan worden.

## [33.47.3] - 2026-07-12

### Changed
- De bevestiging na een activatieaanvraag noemt `ledenadministratie@svawc.nl` als afzender en toont de hulptekst in een beter leesbare kleur.

### Fixed
- Accountactivatiemails worden verzonden namens `ledenadministratie@svawc.nl` in plaats van de algemene `noreply@`-afzender.

## [33.47.2] - 2026-07-12

### Changed
- De uitleg en formulieropmaak op `/activeren` zijn verduidelijkt: leden herkennen welk e-mailadres ze moeten gebruiken, en het veldlabel en de activatieknop hebben meer nadruk en ruimte.

### Fixed
- De browservalidatie van het e-mailadres op `/activeren` toont Nederlandse meldingen voor een leeg of ongeldig adres.

## [33.47.1] - 2026-07-11

### Fixed
- De horizontale scrollbar van de brede relatieslijst blijft onder in het venster bereikbaar zolang de gewone scrollbar nog buiten beeld staat. Gebruikers hoeven daardoor niet meer eerst langs alle 100 relaties naar beneden te scrollen om kolommen aan de rechterkant te bekijken. (feedback #6473)

## [33.47.0] - 2026-07-11

### Added
- Diensttypen hebben aanpasbare onderwerpen en teksten voor herinneringsmails en enquêtemails, plus een Google Forms-link. Aangemelde vrijwilligers ontvangen automatisch en eenmalig een herinnering 2 weken, 1 week en 2 dagen voor de dienst en, als een enquêtelink is ingesteld, één dag na afloop een enquête. De mails ondersteunen variabelen voor naam, dienst, datum, tijden en medevrijwilligers.
- Vrijwilligersbeheerders kunnen via het dienstbeheer altijd een deelnemer afmelden. Dit recht volgt de bestaande capability `vrijwilligers`, die per rol instelbaar is in de capabilitymatrix.

### Changed
- Leden kunnen zichzelf tot 3 weken vóór een dienst afmelden. Binnen die grens kan dat alleen nog gedurende 30 minuten na de eigen aanmelding om een foutklik te herstellen; daarna verwijst de interface naar de vrijwilligerscoördinator. Bij aanmelden binnen 3 weken verschijnt vooraf een duidelijke waarschuwing.

## [33.46.2] - 2026-07-11

### Changed
- De PWA-installatiecache bevat geen dubbele iconen of offlinepagina meer en laadt alleen de Latijnse Montserrat-fontbestanden vooraf. Andere schriftsets blijven op aanvraag beschikbaar en worden na gebruik gecachet.

## [33.46.1] - 2026-07-11

### Changed
- De PWA precachet bij installatie alleen nog de app-shell; zware paginascripts worden pas bij het eerste bezoek gedownload en daarna langdurig gecachet. Dit verlaagt de gelijktijdige downloadpiek bij nieuwe gebruikers aanzienlijk.
- Rondo start de dashboard-API alleen vervroegd op de daadwerkelijke dashboardroute en controleert nieuwe versies minder agressief en zonder overlappende verzoeken. Hierdoor veroorzaken directe links en terugkerende tabbladen minder onnodige WordPress REST-belasting.

## [33.46.0] - 2026-07-11

### Added
- Op **Mijn diensten** is de naam van iedere mede-vrijwilliger met een bekend mobiel nummer een WhatsApp-link, zodat mensen die samen een dienst uitvoeren rechtstreeks contact kunnen opnemen. De link wordt uitsluitend in de persoonlijke dienstenrespons gedeeld; beschikbare diensten blijven alleen namen tonen.

## [33.45.0] - 2026-07-11

### Added
- Leden zien bij beschikbare en eigen diensten de namen van de andere aangemelde vrijwilligers, zodat vooraf duidelijk is met wie zij de dienst uitvoeren. De leden-API deelt hiervoor alleen weergavenamen en blijft interne persoon-ID's en contactgegevens afschermen.

## [33.44.2] - 2026-07-11

### Changed
- Het hoofdmenu-item **Leden** heet voortaan **Relaties**, zodat het ook ouders en externe contacten omvat. De classificatiekolom op dit overzicht heet nu kortweg **Type**.

## [33.44.1] - 2026-07-10

### Changed
- De kolom **Type lid** op de personenlijst toont voortaan één duidelijke categorie: **Bondslid**, **Verenigingslid**, **Ouder** of **Contact**.

## [33.44.0] - 2026-07-10

### Added
- De overzichten **Tuchtzaken** en **Facturen** kunnen de huidige gefilterde selectie exporteren als een Excel-compatibel CSV-bestand. De export bevat ook relevante detailvelden die niet altijd als zichtbare kolom in het overzicht staan. (feedback #6857)

## [33.43.3] - 2026-07-10

### Added
- Op een commissiepagina opent **E-mail leden** een nieuw bericht in het standaard mailprogramma, gericht aan alle huidige commissieleden met een bekend e-mailadres. Dubbele adressen worden overgeslagen. (feedback #6861)

## [33.43.2] - 2026-07-10

### Fixed
- Factuurdetail en facturenoverzicht tonen bij een bedrijfscontact nu dezelfde primaire klantnaam als de PDF: de bedrijfsnaam. De persoonsnaam blijft zichtbaar als contactpersoon, zodat bijvoorbeeld **Businessclub AWC** niet meer als **Roel de Bruijn** in de administratie verschijnt.

## [33.43.1] - 2026-07-10

### Fixed
- Het formulier **Contact toevoegen** toont een ingevulde voornaam niet langer ten onrechte als foutmelding. Het veld Tussenvoegsel is voor handmatig aangemaakte contacten nu bewerkbaar in plaats van geblokkeerd door de Sportlink-regel voor leden.

## [33.43.0] - 2026-07-10

### Added
- Contacten kunnen nu een bedrijfsnaam hebben en mogen ook uitsluitend uit een bedrijfsnaam bestaan. Als geen persoonsnaam is ingevuld, gebruikt Rondo de bedrijfsnaam als weergavenaam in lijsten, zoekvelden, facturen, factuurmails en PDF's.

### Changed
- De CSV-export van personen bevat een aparte kolom Bedrijfsnaam. Op de persoonspagina kan de bedrijfsnaam van een contact worden bijgewerkt.

## [33.42.1] - 2026-07-10

### Changed
- De dialoog **Contact toevoegen** vermeldt nu duidelijk dat hij alleen voor externe contacten bedoeld is en dat leden en ouders/verzorgers uitsluitend via Sportlink worden toegevoegd en bijgewerkt. Bij het aanmaken kan het persoonstype daarom niet meer naar lid/ouder worden gewijzigd.

## [33.42.0] - 2026-07-10

### Added
- Externe verenigingscontacten kunnen als persoonstype **Contact** in hetzelfde adresboek als leden en ouders worden bijgehouden. De ledenlijst heeft hiervoor een knop "Contact toevoegen", een persoonstypefilter en herkenbare Contact-labels. Contacten gebruiken dezelfde adres-, relatie- en factuurgegevens als andere personen. (feedback #7921)

### Changed
- De Relaties-kaart blijft voor bevoegde gebruikers zichtbaar wanneer een persoon nog geen relaties heeft, zodat ook bij een nieuw contact direct de eerste relatie kan worden toegevoegd.

## [33.41.5] - 2026-07-10

### Fixed
- `/mijn-gegevens` gebruikt nu een eigen household-endpoint en toont altijd alleen de gekoppelde persoon en minderjarige kinderen. Beheerders zien op deze persoonlijke pagina niet langer het volledige ledenbestand.

## [33.41.4] - 2026-07-10

### Fixed
- De migratie van bestaande IVA-bestanden draait niet langer tijdens de bootstrap van iedere REST-aanvraag. Daardoor blijven de app-API en navigatie direct beschikbaar; de migratie wordt als gecontroleerde eenmalige beheeractie uitgevoerd.

## [33.41.3] - 2026-07-10

### Fixed
- De eenmalige migratie van bestaande IVA-certificaten gebruikt nu afzonderlijke, geïndexeerde WordPress-metaqueries. De eerdere gecombineerde OR-query kon op productie een timeout veroorzaken voordat de migratie begon.

## [33.41.2] - 2026-07-10

### Security
- Het deployscript sluit nu lokale `.env`-bestanden, agentconfiguratie, worktrees, tests en overige ontwikkelbestanden uit. Ook bij `--with-node-modules` wordt uitsluitend de benodigde dependency-map meegestuurd.

## [33.41.1] - 2026-07-10

### Security
- Alle Rondo-posttypes gebruiken nu eigen WordPress-capabilities. Gewone leden hebben geen generieke schrijf-, verwijder- of uploadrechten meer en kunnen daardoor geen facturen, tuchtzaken, kledingrecords of vrijwilligersconfiguratie via de standaard REST API lezen of vervalsen.
- Financiële overzichten, VOG-bulkacties, vrijwilligersdiagnostiek en zoeken op e-mailadres zijn afgeschermd op hun specifieke functierecht. Dashboardcaches zijn per gebruiker gescheiden en dienstreacties lekken geen persoon-ID's van andere vrijwilligers meer.
- IVA-certificaten worden buiten de publieke webmap opgeslagen en alleen via een geauthenticeerd endpoint aan het gekoppelde lid of een bevoegde functionaris geleverd. Bestaande certificaten worden eenmalig uit de mediabibliotheek gemigreerd.
- Axios, React Router, Vite, Guzzle, phpseclib, FPDI en overige kwetsbare indirecte dependencies zijn bijgewerkt naar gepatchte versies. `npm audit` en `composer audit` melden geen bekende kwetsbaarheden meer.

### Fixed
- De CRUD-tests beschrijven weer het huidige ledenmodel en de actuele rechten per posttype. De todo-fixture en ACF-locatieregel gebruiken nu `rondo_todo` en het huidige meervoudige `related_persons`-veld.
- Een gekoppeld ledenaccount kan zijn persoonskoppeling niet meer zelf verwijderen of verplaatsen; de provisioning-marker blijft behouden als bescherming tegen dubbele accounts.

## [33.41.0] - 2026-07-10

### Added
- **Facturen inplannen om automatisch te verzenden.** Je kunt een conceptfactuur nu een verzenddatum in de toekomst geven; de factuur blijft een concept en wordt op die dag automatisch verstuurd (met betaallink, PDF en e-mail, net als een handmatige verzending). Ideaal om bijvoorbeeld BSO-facturen in één keer aan te maken en elk op een eigen datum te laten uitgaan. (feedback #6632)
  - Stel de datum in via het veld "Automatisch verzenden op" in het factuurformulier, of via "Automatisch verzenden op" op de factuurpagina zelf (met een knop om de inplanning bij te werken of te annuleren).
  - Op het Facturen-overzicht kun je meerdere concepten selecteren en in één keer inplannen voor dezelfde datum ("Inplannen voor…" in de selectiebalk).
  - Ingeplande concepten krijgen een "Ingepland · {datum}"-label in het overzicht en op de factuurpagina. Handmatig "Verstuur nu" blijft altijd mogelijk en annuleert de inplanning.
  - Een dagelijkse achtergrondtaak verstuurt de ingeplande facturen; de verzending wordt toegeschreven aan degene die de factuur heeft ingepland.

### Added
- **Bestaande factuur kopiëren als nieuw concept.** Op het Facturen-overzicht staat nu bij elke factuur een kopieer-knop, en op de factuurpagina zelf een knop "Kopiëren naar nieuwe factuur". Je komt dan in het factuurformulier met alle gegevens al ingevuld — lid/klant, regels, bedragen, e-mailtekst en eigen velden — zodat je terugkerende facturen (bijvoorbeeld de maandelijkse BSO-facturen) niet opnieuw hoeft in te tikken. Er wordt een nieuw factuurnummer toegekend bij versturen en de vervaldatum wordt opnieuw gezet. Een gekopieerde contributiefactuur wordt als handmatige factuur aangemaakt, omdat contributiefacturen automatisch worden gegenereerd. (feedback #6631)

### Fixed
- **Creditfacturen kregen de tekst van een reguliere factuur in de e-mail.** Bij het aanmaken van een creditfactuur vulde het factuurformulier het e-mailtekstveld altijd met de reguliere-factuurtekst, ongeacht de factuursoort. Omdat de editor die tekst bij het laden opnieuw opmaakt, werd die reguliere tekst vervolgens als handmatige override meegestuurd en overschreef zo het ingestelde creditfactuur-template. Het formulier gebruikt nu de juiste standaardtekst per factuursoort (creditfactuur-template voor creditfacturen) en wisselt die correct om als je de factuursoort wijzigt. (feedback #6633)

### Changed
- De herinneringen voor overige facturen staan nu op een eigen tabblad "Overige herinneringen" onder Instellingen → Financieel, in plaats van onder het tabblad "Contributieherinneringen".

## [33.39.0] - 2026-07-10

### Fixed
- **Herinneringen voor niet-contributiefacturen spraken ten onrechte over contributie.** Bij een betalingsherinnering voor een handmatige factuur of een tuchtzaakfactuur werd de contributie-herinneringstekst gebruikt — inclusief verwijzingen naar "je contributie", "je betaalwijze kiezen" en "eventueel in termijnen", die op dat soort facturen niet van toepassing zijn. De verzender koos één vaste herinneringstekst voor álle factuursoorten. (feedback #6687)

### Added
- **Aparte herinneringstemplates voor overige facturen.** Onder Instellingen → Financieel → Factuurherinneringen staat nu een tweede blok "Herinneringen voor overige facturen". Contributiefacturen blijven de bestaande contributie-herinneringen gebruiken; handmatige facturen en tuchtzaakfacturen krijgen een neutrale herinnering zonder contributie- of termijncontext. De juiste template wordt automatisch gekozen op basis van het factuurtype. Beide herinneringen (eerste en tweede) hebben een eigen titel en zijn met een testmail te controleren.

### Added
- **Conceptfacturen in bulk verwijderen.** Op de Facturen-overzichtspagina kun je nu meerdere conceptfacturen aanvinken en in één keer verwijderen, naast de bestaande "verstuur alle"-actie. Er verschijnt een "Verwijder alle"-knop in de selectiebalk met een bevestiging vooraf; alleen conceptfacturen zijn selecteerbaar, dus verstuurde of betaalde facturen kunnen niet per ongeluk verwijderd worden.

## [33.37.0] - 2026-07-10

### Changed
- **De Kaderlijst is nu server-side afgeschermd.** De pagina haalde tot nu toe álle leden op en filterde in de browser, waardoor een coördinator via de Kaderlijst het hele ledenbestand kon inzien. Er is nu een eigen endpoint (`GET /rondo/v1/kaderlijst/people`) dat alleen kaderleden teruggeeft (mensen met een lopende functie in de werkhistorie) en alleen de velden die de lijst toont. De zichtbaarheid wordt op de server bepaald: beheerders zien alle kaderleden, een coördinator ziet de kaderleden van de teams die hij coördineert (bepaald op basis van de leeftijdsgroep van de huidige spelers in dat team), en een lid ziet alleen het eigen huishouden.

### Removed
- **`suppress_age_group` is volledig verwijderd.** Deze query-parameter was de laatste plek waar een gescopete account de leeftijdsgroep-afscherming kon omzeilen. De parameter, `AccessControl::can_suppress_age_group()`, de `$suppress_age_group_filter`-vlag en alle bijbehorende vertakkingen zijn weg; de Kaderlijst gebruikt nu het afgeschermde endpoint.
- De gedeelde Kaderlijst-snapshot (`rondo_kaderlijst_snapshot`, opgeslagen in de opties) is vervangen door een live, per-gebruiker afgeschermde query. Eén gedeelde snapshot is niet verenigbaar met per-gebruiker zichtbaarheid.

## [33.36.0] - 2026-07-10

### Added
- **Datakwaliteit: "actieve leden zonder e-mailadres".** De ledenadministratie ziet nu op het Vrijwilligers-dashboard hoeveel actieve leden geen geldig e-mailadres hebben (email_1 én email_2 leeg of ongeldig). Zij kunnen geen account activeren via `/activeren` tot iemand een adres verzamelt. Doorklikken toont de lijst met naam, KNVB-ID, leeftijdsgroep en adres, met KNVB-ID erbij zodat ze snel op te zoeken zijn. Deze categorie is afgeschermd op het `ledenadministratie`-recht (beheerders inbegrepen); de bestaande categorieën blijven zichtbaar voor alle goedgekeurde gebruikers.
- **CSV-export op elke Datakwaliteit-lijst.** De drill-downpagina's (wees-gezinnen, adres-overeenkomst, geen leeftijdsgroep, ex-leden, geen e-mailadres) hebben nu een "Exporteer CSV"-knop, zodat de lijst buiten Rondo nagebeld of afgewerkt kan worden.

## [33.35.0] - 2026-07-10

### Added
- **Financieel is nu gesplitst in lezen en bewerken.** Wie mee wil kijken met de contributie en de facturen hoeft daarvoor niet langer het recht te krijgen om facturen te versturen, te verwijderen of als betaald te markeren. Een nieuwe capability `financieel_read` geeft alleen inzage.
- Nieuwe rol **Rondo Financieel Lezen** — direct toe te kennen vanuit de gebruikerslijst. `financieel_read` is daarnaast aan te vinken in de rechtenmatrix onder Instellingen → Beheer.
- De gebruiker-endpoint geeft nu `can_edit_financieel` terug naast `can_access_financieel`, zodat de interface knoppen kan verbergen in plaats van ze te laten mislukken op een 403.

### Changed
- `financieel` impliceert `financieel_read`. Iedereen die vandaag financieel beheert, ziet en kan exact hetzelfde als voorheen; de rechten worden bij het laden van het thema eenmalig bijgewerkt.
- Leesrechten geven inzage in facturen, de contributiepagina, de FinanciënKaart op een persoonspagina en facturen in de zoekresultaten. Ze geven **geen** recht om personen te bewerken, en geen toegang tot Instellingen → Financieel.

## [33.34.1] - 2026-07-10

### Fixed
- **De penningmeester zag geen seizoenen en geen categorieën op de contributiepagina.** Het Financiën-menu is afgeschermd op de rol-rechten "financieel", maar de bijbehorende endpoints eisten beheerdersrechten (`manage_options`). Wie financieel beheert zonder beheerder te zijn, kreeg dus een leeg scherm. Alle contributie- en factuur-endpoints luisteren nu naar hetzelfde recht als het menu.
- `/rondo/v1/werkfuncties/available` — nodig om categorieën aan functies te koppelen — is nu ook bereikbaar voor financieel beheerders.

### Changed
- `Invoices::check_financieel_permission()` was een woordelijke kopie van de methode in `Base` en is verwijderd.

## [33.34.0] - 2026-07-10

### Added
- **"Mijn gegevens" voor leden.** Een nieuw scherm waar je je eigen gegevens ziet en die van je kinderen onder de 18, precies zoals ze bij de club bekend staan. Alleen-lezen; kloppen ze niet, dan geef je het door aan de ledenadministratie.

### Fixed
- **Coördinatoren kwamen op een dashboard zonder menu.** "Kader" werd op twee plekken los van elkaar bepaald: de router telde poule- en coördinatorrollen mee, de zijbalk niet. Wie zo'n rol had, belandde op het dashboard terwijl er geen enkel menu-item naartoe wees. Beide lezen nu hetzelfde `is_kader`-veld, dat de server bepaalt.

## [33.33.0] - 2026-07-10

### Added
- **Leden kunnen zelf een account aanmaken op `/activeren`.** Je vult je e-mailadres in, wij sturen een link naar het adres dat bij de club bekend is. Staan er meerdere leden op dat adres, dan kies je wie je bent. Daarna stel je meteen een wachtwoord in — er komt geen tweede mail aan te pas.
- `PublicPageChrome` — de gedeelde HTML-schil van `/betaling` en `/activeren`, zodat de twee pagina's niet uit elkaar lopen.
- `ActivationServiceTest` — 20 tests, met nadruk op misbruik: een link van het ene adres kan geen lid op een ander adres activeren, is eenmalig, en werkt niet na afloop.

### Security
- De pagina antwoordt **precies hetzelfde** of het e-mailadres nu bekend is of niet. Anders zou het een opzoekregister van clubleden worden.
- Activatie geeft nooit rechtstreeks toegang: de link gaat altijd naar het adres dat al bij de club bekend staat. Wie een adres gokt, leert niets en ontvangt niets.
- Van de token wordt alleen de SHA-256 bewaard, en hij wordt na gebruik direct ongeldig.
- Snelheidsbegrenzing: 3 aanvragen per e-mailadres en 10 per IP-adres per uur.

## [33.32.0] - 2026-07-10

### Added
- **Inloggen met je KNVB-ID of je eigen e-mailadres.** Leden hoeven de door Rondo aangemaakte gebruikersnaam niet te kennen. Voor het tweede lid van een gezin is dit de enige manier om binnen te komen: het gedeelde adres hoort bij het account van de eerste.
- `LoginResolverTest` — 10 tests, waaronder de gedeelde gezinsbrievenbus.

### Security
- Een gedeeld e-mailadres logt nooit in op het verkeerde account. Hoort het adres bij niemands WordPress-account, dan is het dubbelzinnig en weigert het systeem te gokken — die leden gebruiken hun KNVB-ID.

## [33.31.0] - 2026-07-09

### Added
- **Gezinnen kunnen één e-mailadres delen.** Wie als tweede op een adres een account krijgt, krijgt een onbezorgbaar WordPress-adres (`person-{id}@members.rondo.invalid`); het echte adres staat in `rondo_contact_email`. Voorheen weigerde het systeem zo iemand met "Dit e-mailadres is al in gebruik".
- **`ContactEmailRouter`** stuurt alle post die naar zo'n plaatsvervangend adres gaat door naar het echte adres — inclusief de wachtwoord-herstelmail van WordPress zelf. Zonder dit zou een tweede gezinslid nooit meer kunnen inloggen.
- `UserProvisioningEmailTest` — 12 tests, waaronder de wachtwoord-herstelmail naar de gezinsbrievenbus.

### Changed
- **Ouders zijn nu te vinden in de accountkiezer.** De eis van een KNVB-ID verborg 269 actieve mensen — precies de ouders die de ouderplicht dragen en géén Sportlink-lid zijn. Een geldig e-mailadres is voortaan de enige eis.
- Bestaat er al een account dat naar de persoon verwijst, maar ontbreekt de terugverwijzing, dan wordt dat account overgenomen in plaats van een tweede aangemaakt.

## [33.30.0] - 2026-07-09

### Added
- **Leden zien hun eigen gegevens en die van hun minderjarige kinderen.** `AccessControl::can_view_person()` is nu de enige plek waar zichtbaarheid van personen wordt bepaald: beheerders zien iedereen, coördinatoren hun eigen leeftijdsgroepen, gewone leden alleen zichzelf en hun kinderen onder de 18. Leden lezen een uitgeklede set velden — betaalblokkade, wacht-op-overschrijving en freescout-id blijven verborgen, en een later toegevoegd ACF-veld is standaard privé.
- `PersonVisibilityTest` — 18 tests, inclusief de REST-collectie, de losse persoon en de veldafscherming.

### Fixed
- **Beveiliging: notities en activiteiten van elk lid waren leesbaar voor elke ingelogde gebruiker.** `user_can_access_post()` gaf voor personen altijd `true` terug, waardoor `/people/{id}/notes`, `/activities` en `/timeline` niet werkelijk afgeschermd waren. Deze routes volgen nu dezelfde zichtbaarheidsregel. Er stonden nog geen notities in het systeem, dus er is niets gelekt.

### Changed
- De leeftijdsgroep-afscherming stond op twee plekken los van elkaar geïmplementeerd (`filter_rest_query` en `apply_age_group_filter`). Beide gebruiken nu één gedeelde `person_scope()`.

## [33.29.1] - 2026-07-09

### Fixed
- **Een dienst telde voor twee plichten tegelijk.** Wie zowel een eigen dienstplicht als een gezinsplicht heeft, kreeg elke dienst bij beide plichten bijgeschreven — 3 diensten voldeden zo aan een plicht van 2 + 3. Een dienst telt nu voor precies één plicht: eerst de eigen dienstplicht, daarna de gezinsplicht. Spelers zonder kinderen houden al hun diensten op hun eigen plicht staan, zodat extra werk niet verdwijnt uit de clubtotalen. Een no-show telt één keer, bij de eigen plicht.

### Added
- `VolunteerShiftAttributionTest` — 8 tests op de toerekening van diensten aan plichten.

## [33.29.0] - 2026-07-09

### Fixed
- **Spelende ouders zagen hun gezinsplicht niet op /vrijwillig.** Wie zelf O17+ speelt én een kind onder 17 heeft, kreeg alleen de eigen spelersplicht te zien, terwijl het vrijwilligersdashboard de gezinsplicht apart meetelde. Beide plichten gelden — ze worden nu allebei getoond, met de eigen dienstplicht eerst.

### Changed
- **Meedoen mag ook zonder dienstplicht.** Wie niet onder de vrijwilligersplicht valt — een sponsor, een grootouder, een ouder van wie de kinderen zijn doorgestroomd — kreeg een leeg scherm met "Je valt niet onder de vrijwilligersplicht-doelgroep". Iedereen die actief lid is kan zich nu gewoon aanmelden voor diensten; alleen oud-leden worden geweigerd.

### Added
- Documentatie van het vrijwilligersplicht-systeem (`features/vrijwilligersplicht`) en van de leeftijdsgroep-afscherming (`features/access-control`).
- `VolunteerObligationUnitsTest` — 10 tests op de plicht-afleiding, inclusief de cumulatieve plicht van een spelende ouder.

## [33.28.3] - 2026-07-09

### Fixed
- **De PHP-testsuite draait weer.** `codeception.yml` verwees nog naar het oude thema `stadion` en `composer test` riep de suite aan met de verkeerde hoofdletters. `AgeGroupAccessTest` is bijgewerkt naar de huidige "niet-beheerders zien niemand"-semantiek en dekt nu ook de `suppress_age_group`-fix uit 33.28.2 (18 tests groen).

### Changed
- Documentatie toegevoegd over het draaien van de testsuite, inclusief de waarschuwing dat 118 van de 153 tests verouderd zijn (geschreven voor het verwijderde goedkeuringssysteem).

## [33.28.2] - 2026-07-09

### Fixed
- **Beveiliging: `suppress_age_group` kon de leeftijdsgroep-afscherming volledig omzeilen.** Elke ingelogde gebruiker — ook een gewoon lid zonder rechten — kon met `?suppress_age_group=1` op `/wp/v2/people` alle persoonsrecords opvragen, inclusief e-mailadressen. De parameter wordt nu alleen nog gehonoreerd voor gebruikers met een expliciet ingestelde, niet-lege leeftijdsgroep-lijst (coördinatoren, waarvoor de Kaderlijst hem nodig heeft). Beheerders waren al onbeperkt; voor gebruikers die "niemand" mogen zien wordt de parameter genegeerd.

## [33.28.1] - 2026-07-09

### Fixed
- **Gebruikers met een extra rol komen weer op het dashboard uit.** Wie naast de standaardrol (Rondo User) nog een andere rol heeft — bijvoorbeeld een poule-rol of een zelf aangemaakte rol zonder eigen rechten — werd doorgestuurd naar `/vrijwillig`. Zij zien nu gewoon het dashboard op `/`.

## [33.28.0] - 2026-06-26

### Added
- **Diensttypes beheren op de frontend.** Je kunt nu diensttypes toevoegen en bewerken vanuit het Diensten-scherm (knop "Diensttype" en het potloodje), in plaats van in wp-admin. Het formulier dekt naam, omschrijving, VOG/IVA/sleutel-vereisten, capaciteit, kleur en een optionele poule.

### Changed
- **Vrijwilligers-coördinatoren mogen alle vrijwilligers-CPT's bewerken.** De gedeelde-toegangsregel die al voor taakuitleg gold, geldt nu ook voor diensttypes, sjablonen en concrete diensten — ook als die door de seeder of een beheerder zijn aangemaakt. Voorheen kon een coördinator alleen items bewerken die hij zelf had aangemaakt.

### Fixed
- **Potloodje bij diensttypes ging naar de startpagina.** De bewerk-link verwees naar wp-admin en belandde voor niet-admins op de homepage; hij opent nu het nieuwe frontend-bewerkscherm.
- **"Update beschikbaar" werkte niet altijd op PWA-installs.** Bij het herladen bleef de oude service worker de gecachte build serveren, waardoor nieuwe pagina's (zoals een net opgeslagen taakuitleg) onvindbaar leken. De herlaadknop ruimt nu de service worker en caches op vóór het verversen.

## [33.27.0] - 2026-06-16

### Changed
- **Filter "Spelend lid" is nu een Ja/Nee-keuze.** In plaats van alleen spelende leden te tonen, kun je nu ook filteren op niet-spelende leden (geen Spelactiviteit of "-"). De bestaande URL `?spelendLid=1` blijft werken.

## [33.26.0] - 2026-06-16

### Added
- **Filter "Spelend lid" op /leden.** Nieuwe boolean-schakelaar in de sectie Lidmaatschap die leden toont met een ingevulde Spelactiviteit (niet leeg en niet "-"). Werkt door in de export en "selecteer alles".

## [33.25.0] - 2026-06-11

### Added
- **Inklapbare zijbalksecties.** Menu-items met subitems (Leden, Teams, Vrijwilligers, Financiën) hebben nu een chevron waarmee je de subitems in- en uitklapt. De keuze wordt per sectie onthouden in `localStorage`, dus blijft staan tussen bezoeken. De sectie waar je je huidige pagina in zit, staat altijd open.

## [33.24.0] - 2026-06-09

### Added
- **Nieuw posttype Taakuitleg.** Vrijwilligers-gerichte taakinstructies ("hoe gebruik en reinig je de koekenpan") met rich text, inline afbeeldingen en printbare QR-codes.
  - Nieuw CPT `taakuitleg` (`includes/class-post-types.php`), `public`/`publicly_queryable` op `false` — geen SEO-oppervlak. Titel + body (`post_content`, met `editor`/`revisions`-support) + ACF-`relationship` naar één of meer `dienst_type`'s (`acf-json/group_taakuitleg_fields.json`).
  - **Publieke leespagina** op `/uitleg/{slug}` (`includes/class-public-taakuitleg-page.php`) — standalone, print-vriendelijke HTML zonder login, naar het voorbeeld van de betaalpagina. Dit is het doel van de QR-codes; een vrijwilliger scant een sticker zonder in te loggen. Alleen `publish`-status wordt getoond; `noindex`.
  - **Bewerken in de SPA** onder `/vrijwilligers/taakuitleg` (lijst + formulier), gated op de `vrijwilligers`-capability. Gedeeld bewerken voor alle vrijwilligers via een `map_meta_cap`-filter in `class-access-control.php`.
  - **Inline afbeeldingen** in de Tiptap-editor via een opt-in `enableImages`-prop op `RichTextEditor` (upload naar `/wp/v2/media`); bestaande notitie-/taakvelden blijven ongewijzigd.
  - **Printbare QR-codes** (`qrcode`) via een stickervoorbeeld-dialoog; printen gebeurt in een geïsoleerde iframe.
  - Rewrite-regels worden na deploy één keer geflusht via een versie-optie (`rondo_rewrite_rules_version`), zodat `/uitleg/{slug}` zonder handmatige permalink-flush werkt.
- **Rondo-lokale commissie-informatie.** Commissies kunnen nu extra gegevens bevatten die alleen in Rondo worden bijgehouden, te bewerken via een nieuwe "Commissie-informatie"-kaart op de commissie-detailpagina (`/commissies/:id`):
  - `lange_omschrijving` — uitgebreide omschrijving (tekstveld).
  - `taakomschrijving` — wat doet een lid van deze commissie (tekstveld).
  - `uren_aantal` + `uren_periode` — geschatte tijdsinvestering (aantal uren per week/maand).
  - `dagen_flexibel` — vrij tekstveld voor vaste dagen of flexibel.
  - `max_leden` — maximaal aantal leden in de commissie.
  - `max_wachtlijst` — maximaal aantal personen op de wachtlijst.
  - Velden zijn ACF-meta op het `commissie`-posttype, blootgesteld via `wp/v2/commissie`. `uren_periode` (select) en de numerieke velden worden op de client genormaliseerd in `sanitizeCommissieAcf` (lege string → `null`) om de ACF REST-enum/number-schemavalidatie te respecteren.

## [33.23.2] - 2026-05-31

### Added
- **`iva_waived` ook op sjablonen.** Een sjabloon kan nu de IVA-eis voor alle uitgerolde diensten ineens uitschakelen — handig voor de wekelijkse "Kantine bar — zaterdag 08:00"-sjabloon. De expander schrijft `iva_waived` door naar elke nieuw aangemaakte dienst. Bestaande, eerder uitgerolde diensten worden niet aangepast (handmatig aanvinken via dienst-edit blijft mogelijk).

## [33.23.1] - 2026-05-31

### Added
- **Per-dienst IVA-override.** Op `dienst_shift` is een nieuw veld `iva_waived` (ACF + `register_post_meta`) dat de IVA-eis van het diensttype voor één specifieke dienst uitschakelt. Use case: kantine-bardienst op zaterdag voor 15:00 — geen alcoholschenking, dus geen IVA nodig. De checkbox verschijnt alleen in het dienst-formulier als het gekozen diensttype IVA vereist. Server-side enforcement zit in zowel `get_available_shifts` (lijst-filtering) als in `signup` (laatste check).

## [33.23.0] - 2026-05-31

### Added
- **Profielsubpagina's voor certificaten.** `/profile/vog` (nieuw, leesalleen) toont VOG-status, afgiftedatum en vervaldatum + uitleg over het aanvraagproces. `/profile/iva` is de bestaande IVA-uploadpagina, verplaatst van `/vrijwillig/profiel`. Het hoofd-Profiel toont nu een "Mijn certificaten"-sectie met cards naar beide.
- **REST endpoint `GET /rondo/v1/vog/me`** — geeft het lid zelf zijn VOG-status, datum-vog en vervaldatum terug. Read-only; aanvraag/uitgifte regelt de VOG-coördinator extern.

### Changed
- **`/vrijwillig/profiel` redirect naar `/profile/iva`** (bookmarks blijven werken).
- **"Mijn certificaten"-link op `/vrijwillig`** wijst nu naar `/profile` (toont beide subpagina's) in plaats van direct naar de IVA-pagina.
- **Admin IVA-pagina** verwijst nu naar het nieuwe `/profile/iva`-pad.

## [33.22.3] - 2026-05-31

### Changed
- **"Mijn profiel" rechtsboven op `/vrijwillig` → "Mijn certificaten".** Voorkomt verwarring met de algemene `/profile` (nu via zijbalk bereikbaar voor iedereen), en dekt naast IVA ook toekomstige certificaten (VOG, EHBO, KNVB-diploma's).

## [33.22.2] - 2026-05-31

### Changed
- **IVA-blokmelding op `/vrijwillig` herschreven.** "IVA-certificaat ontbreekt of is verlopen" → "Je kunt nog geen bardiensten draaien", met uitleg over wat de IVA-cursus inhoudt en directe link naar de NOC*NSF e-learning.

## [33.22.1] - 2026-05-31

### Changed
- **Kader-routes hard gegate.** `/people`, `/people/:id`, `/people/jubilarissen`, `/teams`, `/teams/:id`, `/kaderlijst`, `/commissies`, `/commissies/:id`, `/todos`, `/feedback`, `/feedback/:id`, `/settings`, `/settings/:tab`, `/settings/relationship-types`, `/settings/custom-fields` redirecten plain leden naar `/vrijwillig` als ze de URL handmatig intypen. Voorheen rendererden ze de pagina-chrome (zonder data dankzij REST-filtering).
- **Profiel-pagina nu in zijbalk voor iedereen.** Plain leden zien daar alleen Account, Thema en Wachtwoord wijzigen. **Meldingen** en **Sportlink koppeling** blijven kader-only; de notification-API wordt voor plain leden niet eens aangeroepen.

## [33.22.0] - 2026-05-31

### Changed
- **Plain leden (account zonder kader-rol) zien een minimale zijbalk.** Voorheen kreeg iedereen die inlogde Dashboard, Leden, Jubilarissen, Teams, Kaderlijst, Commissies, Taken, Feedback en Instellingen te zien — ook leden die alleen voor hun eigen vrijwilligerswerk inloggen. Die items zijn nu gegate achter `requiresKader` (= admin of een `can_access_*`-capability). Een plain lid ziet alleen **Mijn diensten** (`/vrijwillig`).
- **Dashboard `/` redirect naar `/vrijwillig` voor plain leden.** Voorkomt dat een lid zonder kader-rol per ongeluk op het stafdashboard belandt met openstaande taken, ledenstatistieken e.d. Nieuwe component `KaderOrVrijwilligRedirect` in `router.jsx`.
- **Voorbereiding op "elk lid krijgt een account".** Met deze gates blijft de UI bruikbaar voor zowel kader als plain leden zodra accounts breed worden uitgerold.

## [33.21.2] - 2026-05-31

### Changed
- **IVA-knop "Goedkeur" → "Keur goed"** (grammaticaal correcter Nederlands).

## [33.21.1] - 2026-05-31

### Fixed
- **"Reeds aangemeld" in plaats van "Niet beschikbaar" op /vrijwillig.** Shifts waarvoor het lid al ingeschreven is staan ook nog in de "Beschikbaar"-tab zolang er nog plek over is voor anderen. De knop toonde verwarrend "Niet beschikbaar" terwijl `is_signed_up=true` allang in de REST response zat. Vervangen door een groene "Reeds aangemeld"-knop die ook als afmeldknop werkt (hover → rood).

## [33.21.0] - 2026-05-31

### Added
- **"Uitrollen"-knop op de Sjablonen-pagina.** Rolt direct alle actieve sjablonen uit naar concrete diensten voor de komende 12 weken — handig als de gebruiker een sjabloon heeft aangemaakt vóór de auto-expansion live ging, of bestaande sjablonen alsnog wil uitrollen zonder te wachten op de nachtelijke cron. Nieuwe endpoint: `POST /rondo/v1/shift-templates/expand` (vereist `edit_posts`). Geeft `created` (aantal nieuwe diensten) terug. Idempotent — bestaande diensten worden niet gedupliceerd dankzij `find_existing_shift()`.

## [33.20.4] - 2026-05-31

### Changed
- **Sjabloon opslaan rolt direct 12 weken aan diensten uit, niet pas de volgende dag.** `ShiftTemplateExpander::expand_on_template_save()` haakt nu op `acf/save_post` voor `shift_template`-posts en roept dezelfde idempotente `expand_template()` aan die de cron 's nachts gebruikt. Bestaande diensten worden niet gedupliceerd (de `(template_id, start_datetime)`-check in `find_existing_shift()` blokkeert dat). Sjabloon-form invalideert nu ook de `dienst-shifts`-cache zodat de Diensten-lijst direct de nieuwe shifts toont.

## [33.20.3] - 2026-05-31

### Fixed
- **Nieuwe sjabloon/dienst verschijnt direct in het overzicht (geen hard refresh meer).** De QueryClient heeft globaal `refetchOnMount: false` staan; `invalidateQueries` markeerde de lijst alleen als stale en omdat de lijst tijdens het bewerken niet gemount was werd er nooit opnieuw gefetcht. Beide formulieren gebruiken nu `refetchQueries({ type: 'all' })` — dezelfde aanpak als `usePeople.js`.

## [33.20.2] - 2026-05-31

### Fixed
- **Sjabloon `Dag van de week` werd niet bewaard.** ACF's `day_of_week`-select heeft een string-enum (`"1"`–`"7"`); de frontend stuurde een JS-getal, waardoor ACF de waarde stilletjes verwierp en de oude waarde bleef staan (titel wel bijgewerkt, dag niet). Form stuurt nu `String(form.day_of_week)`.

## [33.20.1] - 2026-05-30

### Fixed
- **Sjabloon- en dienst-formulieren tonen en bewaren nu de velden correct.** De frontend las/schreef onder `meta` terwijl ACF deze CPT-velden onder `acf` exposeert; WP REST liet de `meta`-payload stilletjes vallen bij opslag. Resultaat: nieuw aangemaakte diensten/sjablonen hadden geen postmeta (kolommen Start/Eind leeg in het overzicht, edit-form leeg). Zowel `VrijwilligersDienstForm`, `VrijwilligersSjabloonForm`, `VrijwilligersDiensten` als `VrijwilligersSjablonen` lezen/schrijven nu via `acf`. ACF-datums (`active_from`/`active_until`) worden bij weergave van `YYYYMMDD` genormaliseerd naar `YYYY-MM-DD`.
- **Bestaande diensten zonder meta blijven leeg** — handmatig aangemaakte diensten (bv. shift 6810) hebben geen opgeslagen waarden en moeten opnieuw bewerkt of verwijderd worden.

## [33.20.0] - 2026-05-28

### Added
- **Nieuw veld `wacht_op_overschrijving` op personen.** Leden die van een andere club afkomen staan in Sportlink (en dus in Rondo) zodra ze aangemeld zijn, maar hebben pas een voetbalactiviteit zodra de KNVB-overschrijving rond is. Sportlink markeert ze tot die tijd met de Tooltip "Actie van een ander (overschrijving)". De rondo-sync herkent dat nu en zet `wacht_op_overschrijving` op `true`; zodra de overschrijving verwerkt is wordt de flag weer op `false` gezet bij de volgende sync.
  - Read-only ACF-veld onder Basic Information (zelfde plek als Oud-lid).
  - Oranje "Wacht op overschrijving"-badge op de personenlijst (naast Oud-lid) en op de persoondetailpagina (naast Afmelding in de toekomst).
  - Boolean-filter "Wacht op overschrijving" in de Lidmaatschap-sectie van de People-pagina, via nieuwe REST-query `?wacht_op_overschrijving=1` op `GET /rondo/v1/people-filtered`.

## [33.19.0] - 2026-05-28

### Changed
- **Contributievrijstelling sluit niet langer uit van vrijwilligers-doelgroep.** `_exclude_from_contributie` (donateurs, ereleden, Lid van Verdienste, handmatig contributievrij) bepaalde tot nu toe ook of iemand in de vrijwilligers-doelgroep zat — wat conceptueel onjuist is: contributie-vrijstelling en vrijwilligers-vrijstelling zijn losse beslissingen. Vrijstelling van vrijwilligerstaken loopt nu uitsluitend via `VolunteerExemptionResolver` (commissie, staf-rol, betaalde vrijwilliger, handmatige vrijstelling) of via een actieve honorary role (Donateur/Erelid/Lid van Verdienste/Verenigingslid voor het leven met die job_title in `work_history`).
  - Nieuwe `VolunteerEligibilityService::is_active_member()` checkt alleen `former_member`.
  - `is_contributie_member()` is verwijderd (had geen consumers meer).
  - Diagnostic `skipped_non_paying` → `skipped_former_members`; drill-down `get_non_paying_ids()` → `get_former_member_ids()`; REST-categorie `non_paying` → `former_members`.
  - Dashboard- en data-quality-teksten bijgewerkt.

### Removed
- **`/wp-json/rondo/v1/volunteer/data-quality?category=non_paying`** — vervangen door `category=former_members`. Drill-down toont nu alleen ex-leden, niet meer contributievrije leden (want die zitten gewoon in de doelgroep).

## [33.18.0] - 2026-05-28

### Added
- **`?former_member=0|1` query param op `GET /wp/v2/people`** — server-side filter via `rest_person_query`. Voorheen moest de rondo-sync change detector ALLE recent gewijzigde personen ophalen (incl. ACF-blob) en dan in JavaScript op `former_member` filteren; nu kan de query 95% van die rijen op WP_Query-niveau wegfilteren. `former_member=0` matcht ook posts zonder `former_member` meta (zoals net-aangemaakte personen). `former_member=1` matcht alleen oud-leden. Andere waarden negeren we, dus oudere clients blijven ongewijzigd werken.

## [33.17.0] - 2026-05-28

### Changed
- **Oud-leden zijn nu alleen-lezen in Rondo Club.** Sportlink weigert sowieso elke wijziging voor hun lidsoort ("Oud bondslid" / "Oud verenigingslid"), dus elke bewerking werd door de reverse-sync afgewezen en bleef in een oneindige re-detectie-loop hangen (één lid had 17 identieke pogingen sinds februari). Vanaf nu:
  - **Frontend** (`PersonDetail.jsx`) toont een prominente "Oud-lid — alleen-lezen"-banner en verbergt alle bewerk-knoppen wanneer `former_member` waar is. `canEditPeople` wordt onder de motorkap op `false` gezet zodra de persoon als oud-lid geladen is, waardoor de bestaande edit-affordances vanzelf verdwijnen (Bewerken-knoppen, foto-upload, relaties/adressen/contact-modals etc.).
  - **Backend** (`class-rest-people.php`, nieuwe `block_former_member_edits()` op het `rest_pre_insert_person` filter) weigert ACF-edits met HTTP 403 (`rondo_former_member_readonly`) wanneer de bestaande persoon `former_member=true` is en de aanvragende gebruiker geen `manage_options` heeft. Admins (en daarmee de sync-service-account) zijn vrijgesteld zodat de forward-sync z'n werk kan blijven doen.
  - De enige toegestane wijziging voor niet-admins blijft het `former_member`-veld zelf, zodat een beheerder de status kan terugzetten naar actief vóórdat de overige velden bewerkbaar worden.

## [33.16.0] - 2026-05-28

### Added
- **Functiegeschiedenis-paneel toont nu historische teams als tekst** — als een work_history-entry geen gekoppeld team-post heeft maar wel `team_name_text` (gevuld door de player-history-sync voor oude Sportlink-seizoenen), wordt die naam nu getoond. Geldt voor zowel de Functiegeschiedenis-lijst in de Werk-tab als de huidige-positie-regel in de header (bv. "Teamspeler bij Zaterdag E5 (seizoen 2014/'15)").

### Fixed
- **YYYYMMDD-datums in `work_history` worden nu correct gerenderd.** ACF slaat de `date_picker` velden op als `YYYYMMDD` (bv. "20140708"), wat `new Date()` niet parset — dus alle entries die door de sync zijn geschreven toonden tot nu toe een lege " - " in plaats van een datumbereik. Nieuwe `parseAcfDate()`-helper in `formatters.js` herkent beide formaten (YYYY-MM-DD én YYYYMMDD); `isValidDate` delegeert ernaar, en `PersonDetail.jsx` gebruikt de helper voor work_history-datums. Lost zichtbaar het probleem op voor álle bestaande work_history-entries die in compact formaat zijn opgeslagen.

## [33.15.0] - 2026-05-28

### Added
- **`team_name_text` veld op `work_history` repeater** — vrij-tekst teamnaam voor historische Sportlink-teams die niet als post in Rondo Club bestaan (bv. "Zaterdag E5 (seizoen 2014/'15)"). Alleen zichtbaar/gebruikt als het normale Team / Commissie-veld leeg is. Wordt automatisch gevuld door de `player-history`-sync wanneer een Sportlink-team niet matcht met een bestaand Rondo Club-team.

## [33.14.8] - 2026-05-28

### Changed
- **"Actieve leden zonder leeftijdsgroep"-rapport** filtert nu ook honorary leden eruit (Donateur, Erelid, Lid van Verdienste, Verenigingslid voor het leven — komt uit `VolunteerStatus::get_excluded_roles()`, dus volgt de instellingen in Instellingen → Vrijwilligers), én ouders die een directe `Kind`-relatie hebben — ook als hun kind zélf geen leeftijdsgroep heeft (dan wordt er geen gezin-unit gebouwd en bleef de ouder eerder onterecht in de lijst staan).

## [33.14.7] - 2026-05-28

### Changed
- **"Actieve leden zonder leeftijdsgroep"-rapport** filtert nu ook ouders/huisgenoten eruit die al via een gezin-unit (relationships of adres-fallback) gekoppeld zijn. Die hebben terecht geen Sportlink-spelactiviteit en stonden onterecht als data-gap. Wat overblijft zijn alleen nog actieve, contributieplichtige, niet-vrijwilligende, niet-aan-een-gezin-gekoppelde leden zonder leeftijdsgroep — echte sync-issues of leden die als donateur/erelid/contributievrij gemarkeerd moeten worden.

## [33.14.6] - 2026-05-28

### Changed
- **"Actieve leden zonder leeftijdsgroep"-rapport** filtert nu ook huidige vrijwilligers eruit. Vrijwilligers zonder spelactiviteit hebben terecht geen leeftijdsgroep en horen niet in de doelgroep — ze stonden onterecht als data-quality issue gemarkeerd. Backend (`get_skipped_no_leeftijdsgroep_ids()` en de diagnostic-teller) en frontend-intro bijgewerkt.

## [33.14.5] - 2026-05-28

### Fixed
- **Relatie wijzigen gaf 400-fout** voor personen die geen betaalde vrijwilliger zijn. De frontend stuurde `vergoeding_reden: ""` mee in elke ACF-update, maar ACF's REST-schema accepteert alleen de vier gedefinieerde keuzes — niet een lege string. `sanitizePersonAcf()` zet nu, net als bij `gender`, lege waarden voor `vergoeding_reden` om naar `null`.

## [33.14.4] - 2026-05-27

### Removed
- "Status van de uitrol"-blok van het Vrijwilligers-dashboard verwijderd.

## [33.14.3] - 2026-05-27

### Changed
- **"Personen zonder leeftijdsgroep"-diagnostic** filtert nu ex-leden en handmatig uitgesloten leden eruit. Wat overblijft zijn actieve leden waar de Sportlink-sync gehaperd heeft of waar het veld om een andere reden leeg is — daar kan een admin daadwerkelijk iets aan doen. Dashboard copy + drill-down intro bijgewerkt.

## [33.14.2] - 2026-05-27

### Changed
- **Relatie-kwaliteitscheck**: regel "zelfde leeftijdsgroep" verwijderd. Twee mensen in dezelfde Sportlink-groep (bv. allebei Senioren) kunnen prima ouder/kind zijn als het leeftijdsverschil > 14 jaar is. De `age_gap_too_small`-regel vangt het echte probleem af; de `same_age_group`-regel produceerde false positives bij volwassen ouder/kind-koppels die toevallig in dezelfde leeftijdsgroep zaten.

## [33.14.1] - 2026-05-27

### Performance
- **Vrijwilligers-dashboard laadt nu in milliseconden** in plaats van tientallen seconden. Drie wins:
  1. `VolunteerEligibilityService::get_eligibility_view()` en `RelationshipQualityChecker::find_suspect_pairs()` hebben elk een 5-minuten transient-cache. Eerste call doet het zware werk, daarna O(1).
  2. `address_adults_map()` vervangt N losse SQL queries (één per orphaned youth player) door 2 bulk-queries die in PHP de adres→volwassenen-mapping bouwen.
  3. Hot-loop `get_field()` calls voor `leeftijdsgroep`, `birthdate`, `former_member` vervangen door directe `get_post_meta()` — 10–100× sneller in iteraties over 1000+ personen.
- Nieuwe `VolunteerCacheInvalidator` wist beide transients automatisch bij elke person-mutatie (save_post, REST insert/update, ACF save, post delete) zodat data wel vers blijft.
- **Handmatige "Ververs"-knop** rechtsbovenaan het dashboard + nieuwe `POST /rondo/v1/volunteer-cache/refresh` endpoint voor admins die nu meteen iets hebben aangepast en niet 5 minuten willen wachten.

## [33.14.0] - 2026-05-27

### Added
- **Frontend CRUD voor sjablonen en diensten** — geen WP-admin meer nodig voor het beheren van shift_templates en dienst_shifts.
  - `/vrijwilligers/sjablonen` — overzicht van alle sjablonen, sorteerbaar op dag + tijd.
  - `/vrijwilligers/sjablonen/nieuw` en `/vrijwilligers/sjablonen/:id` — create/edit form met dienst_type, dag-van-de-week, start/eindtijd, capaciteit, actief-vanaf/tot, notities. Auto-generated titel.
  - `/vrijwilligers/diensten/nieuw` en `/vrijwilligers/diensten/:id` — ad-hoc dienst form (datetime, capaciteit, status, notities). Bij bestaande diensten is er een sectie "Aanmeldingen" met handmatig-verwijderen-knop per aangemelde persoon.
  - Verwijderknop met confirm op beide editors.
- "Nieuwe dienst" en "Sjablonen" knoppen op het Diensten-overzicht openen nu de frontend forms in plaats van WP-admin.

### Changed
- Recente-diensten-tabel: de titel + actie-icoon linken naar de frontend-editor (`/vrijwilligers/diensten/:id`) i.p.v. WP-admin.



### Added
- **`/vrijwillig/profiel` — lid-facing IVA-upload flow.** Geen WP-admin meer nodig: een lid kiest datum + bestand (PDF/JPG/PNG, max 10 MB), upload, en ziet de huidige status (Geldig / Wacht op goedkeuring / Verlopen / Niet ingeleverd) plus de vervaldatum. Bij upload wordt `iva-approved` automatisch teruggezet zodat de bestuurslid kantine het opnieuw beoordeelt.
- **`POST /rondo/v1/iva/upload`** voor de upload zelf (multipart, gebruikt `rondo_linked_person_id` om het juiste persoonsrecord te vinden).
- **`GET /rondo/v1/iva/me`** voor de lid-facing status — geen admin-cap nodig.
- "Mijn profiel"-link rechtsbovenaan `/vrijwillig` en directe deeplink in de IVA-hard-block banner.

### Changed
- Welkomstmail voor nieuwe vrijwilligers verwijst nu naar **Vrijwilligers → Mijn profiel** voor het uploaden van het IVA-certificaat in plaats van "mail het naar de kantinebeheerder".
- Admin-IVA-pagina footer verwijst niet meer naar de WP-admin person-bewerk-flow.

## [33.12.0] - 2026-05-27

### Added
- **Relatie-kwaliteitscheck** — `RelationshipQualityChecker` doorloopt alle `relationships`-entries en vlagged ouder/kind-koppels met te klein leeftijdsverschil (vaak in werkelijkheid siblings) en sibling-koppels met een wel erg groot verschil. Drie regels: zelfde leeftijdsgroep (rood), <14 jaar verschil (amber), >30 jaar voor siblings (paars).
- **`GET /rondo/v1/relationship-quality`** retourneert de verdachte paren met namen, thumbnails en leeftijdsgroepen. Aantal verdachte relaties zit ook in de `diagnostics` van `/volunteer-eligibility` zodat de Datakwaliteit-kaart in één call alles kan tonen.
- **`/vrijwilligers/relatie-check`** drill-down pagina toont de paren met een directe link naar elke persoonspagina om het `relationship_type` te corrigeren. InverseRelationships hangt de wijziging automatisch aan de andere kant aan.
- Nieuwe rij op de Datakwaliteit-kaart die naar deze pagina linkt.

## [33.11.0] - 2026-05-27

### Changed
- **Alleen spelend/contributie-plichtige leden vallen onder de vrijwilligersplicht.** `VolunteerEligibilityService` filtert speler- en gezin-trigger-personen nu op `is_contributie_member()`: ex-leden (`former_member`) en handmatig uitgesloten leden (`_exclude_from_contributie`) tellen niet meer mee. Ouders en huisgenoten in een gezin-unit worden niet gefilterd — het kind blijft het ankerpunt.
- Nieuwe diagnostic `skipped_non_paying` op `GET /rondo/v1/volunteer-eligibility` + drill-down `/vrijwilligers/datakwaliteit/non_paying` zodat admins kunnen zien wie er buiten de doelgroep valt en waarom (donateurs, ereleden, contributievrij, ex-leden).

## [33.10.0] - 2026-05-27

### Added
- **Doorklik-pagina's voor de Datakwaliteit-categorieën.** Elk telcijfer op het Vrijwilligers-dashboard linkt nu naar een eigen pagina `/vrijwilligers/datakwaliteit/{category}` met de daadwerkelijke personen + adres, leeftijdsgroep en aantal relaties, plus directe link naar de persoonspagina om de data te repareren.
  - `orphan` — JO16- spelers zonder ouder-relatie én zonder huisgenoot.
  - `address_fallback` — personen waar het gezin uit gedeeld adres is afgeleid (gegroepeerd per adres voor context).
  - `missing_leeftijdsgroep` — personen zonder leeftijdsgroep-veld.
- **`GET /rondo/v1/volunteer-data-quality/{category}`** levert de personen achter elke categorie. Drie nieuwe public methods op `VolunteerEligibilityService`: `get_orphan_youth_ids()`, `get_address_fallback_person_ids()`, `get_skipped_no_leeftijdsgroep_ids()`.

## [33.9.0] - 2026-05-27

### Changed
- **Eligibility-derivatie verliest geen JO16-spelers meer.** Voorheen dropte `VolunteerEligibilityService` stilletjes elke jeugdspeler zonder ouder-relatie én zonder gedeelde-adres-volwassene. Nu krijgt elk JO16-kind gegarandeerd een gezin-unit, met een `data_quality` vlag (`ok` / `address_fallback` / `orphan`). Ook personen zonder `leeftijdsgroep` worden niet meer onzichtbaar — ze worden geteld als diagnostic.
- **`GET /rondo/v1/volunteer-eligibility`** retourneert nu een `diagnostics` blok met: aantal gezinnen via relaties, via adres-fallback, orphan-gezinnen, en het aantal personen overgeslagen wegens ontbrekende `leeftijdsgroep`.
- **Vrijwilligers-dashboard toont een "Datakwaliteit"-kaart** zodra een van de diagnostics > 0 is, met klikbare uitleg per categorie. Helpt admins gericht stuk-voor-stuk de relaties of leeftijdsgroep-velden bij te werken.

Verklaart waarom het Gezin-totaal eerst te laag was: een fors deel van de JO16-spelers heeft (nog) geen ouder-record in `relationships`.

## [33.8.2] - 2026-05-27

### Fixed
- **IVA-pagina gaf 400.** `VrijwilligersIva` riep `/rondo/v1/people/filtered?per_page=1000` aan terwijl die endpoint `per_page` op 100 capt. Vervangen door een dedicated `GET /rondo/v1/iva/people` endpoint dat alleen de personen met IVA-relevante velden teruggeeft (datum-iva, iva-certificaat of iva-approved). Geen paginering nodig — typische scope is tientallen records. De status (missing/pending/valid/expired) wordt server-side bepaald via `IvaStatus`, dus de UI hoeft geen datum-rekenwerk meer te doen.

## [33.8.1] - 2026-05-27

### Changed
- **Ouderplicht uitgebreid van t/m JO15 naar t/m JO16.** `VolunteerEligibilityService::YOUTH_MAX_AGE` is opgerekt naar 16 (was 15). Spelers in "Onder 16" en hun ouders vallen nu in de doelgroep. `ADULT_MIN_AGE` blijft 17 — geen gap. Dashboard copy en interne docstrings bijgewerkt.

### Fixed
- **Vrijwilligers-dashboard "Status van de uitrol"** liet nog steeds zien dat IVA-geldigheidstermijn, multi-child-regel en boete-pipeline op het bestuur wachtten. Het bestuur heeft alles besloten — copy bijgewerkt om de feitelijke status weer te geven.

## [33.8.0] - 2026-05-26

### Added — Member-facing /vrijwillig surface (Fase D #4)

- **`/vrijwillig` route** — logged-in members see their personal obligation card (X van Y diensten gedaan, progress bar, status bucket), two tabs (Beschikbaar / Mijn diensten), and one-click signup/afmelden. Page resolves the caller via `rondo_linked_person_id` user meta; unlinked accounts get a friendly "contact ledenadministratie" prompt instead of a stack trace.
- **Hard-block banners** for missing VOG or IVA per #8/#9 — the same eligibility-filtered shift list hides VOG-/IVA-vereiste shifts until the cert is valid (bestuursbesluit: hard block, no soft warning route).
- **Overlap warning** — when signing up for a shift that overlaps with an existing assignment the server returns `overlap_warning` with a `can_force=true` hint; the UI shows a "toch aanmelden" prompt.
- **Auto-fill on capacity** — signups that reach capacity flip the shift status to `vol`; afmeldingen flip it back to `open`.
- **Pool-only shifts** are hidden from non-pool members. `dienst_type.required_pool` (commissie post id) is honored server-side.
- **`GET /rondo/v1/my-shifts`** returns the caller's assigned + completed shifts plus the decorated obligation unit and exemption (if any) for the current season.
- **`GET /rondo/v1/shifts/available`** lists open shifts within the 84-day window, filtered by eligibility/VOG/IVA/pool.
- **`POST /rondo/v1/shifts/{id}/signup`** and **`/cancel`** drive the member flow. Afmelden is altijd toegestaan (bestuursbesluit) tot de shift voltooid is.

### Operational follow-up (not in this release)

Member-facing flow assumes Magic Login is configured on production and that eligible members have WP-accounts with `rondo_linked_person_id` set. Bulk-provisioning of accounts for the full eligible pool is a one-time operation that happens outside the codebase.

## [33.7.0] - 2026-05-26

### Added — Volunteer Policy: trainings/leider exemption UI, team kickoff, welkomstmail

- **Settings → Beheer → Rollen** now has a "Staf-rollen — vrijgesteld van vrijwilligersplicht" checkbox section below the player/excluded matrix (#12). Defaults to the seven board-approved roles (Trainer, Hoofdtrainer, Assistent-trainer, Leider, Teammanager, Coördinator, Scheidsrechter); admin can adjust per club's actual Sportlink job-title spelling. Persists via the existing `/rondo/v1/volunteer-roles/settings` endpoint.
- **Team CPT gets `kickoff_done_at` (date) + `kickoff_notes` (textarea)** ACF fields exposed in REST (#13). Lets Guido tick off the per-team vrijwilligersbeleid-gesprek with optional notes — input for the dashboard "kickoffs nog te doen" widget (Fase D follow-up). Website-uitleg-pagina blijft in het Astro-project.
- **Welkomstmail voor nieuwe vrijwilligers** is uitgebreid met een vrijwilligersbeleid-blok (#14): 2-diensten-plicht in het kort, links naar VOG/IVA, en de uitleg dat trainers/commissieleden/betaalde vrijwilligers automatisch vrijgesteld zijn. Volledig editable in **Settings → Beheer → Welkomstmail → Nieuwe vrijwilliger**.

## [33.6.0] - 2026-05-26

### Added — Volunteer Policy scheduling core + sancties (Fase C & E)

Implements every roadmap item that the 2026-05-26 bestuursvergadering unblocked.

- **Multi-child scaling (#6).** `VolunteerEligibilityService` now applies the contribution-discount rule: kid 1 = 2 diensten, kid 2 = 1,5 (75%), kid 3+ = 1 elk, floor-rounded. Each gezin unit carries a `child_count` plus the scaled `required_count`. The single-person resolver merges all youth children before scaling so a parent of 3 kids sees the full 4-diensten obligation.
- **IVA 5-year validity (#9).** New `Rondo\Volunteer\IvaStatus` helper with status enum (missing / pending / valid / expired), `expires_at()` and `needs_renewal_reminder()` (3-month window). New REST: `GET /rondo/v1/iva/{person_id}/status`, `POST /rondo/v1/iva/{person_id}/approve` (replaces the previous direct ACF write).
- **`rondo_iva_approve` capability + `rondo_iva_approver` role** for the bestuurslid kantine. Administrator and `rondo_bestuur` inherit it. IVA approval endpoint is gated on this cap.
- **IVA admin UI extended:** new "Geldig" / "Verlopen" tabs, dedicated "Verloopt" column with red highlighting when expired. Approval uses the new dedicated endpoint.
- **`VolunteerObligationCalculator` service (#6).** Per-unit counter: required / completed / pending / no-show counts + a status bucket (voldaan / op-weg / risico / geen-actie). Transient cache (`rondo_vobligation_*`, 5 min TTL) auto-invalidated by shift completion and no-show events. Aggregate dashboard stats too.
- **No-show endpoint (#6).** `POST /rondo/v1/shifts/{id}/no-show` (+ `revert=true` to undo). 72-hour window after `end_datetime` is enforced. Fires `rondo_volunteer_no_show_marked` action.
- **Hourly shift-completion cron (#6).** `rondo_complete_shifts` flips shifts past `end_datetime + 1h` to `voltooid`, clears obligation cache.
- **`VolunteerFineGenerator` (#7).** Hooks `rondo_volunteer_no_show_marked` and creates a €30 `rondo_invoice` with `invoice_type=volunteer_fine`, routed to the primary parent (first `relationship_type=parent` entry on the child's repeater) or the player themselves for O17+. Idempotent — back-references the shift to prevent double-billing. New invoice-number prefix `V` (e.g. `2026V0001`).
- **Daily `ShiftTemplateExpander` cron (#3b).** Rolls out `shift_template` records into concrete `dienst_shift` posts for the next 84 days. Idempotent — keyed on (template_id, start_datetime).
- **`GET /rondo/v1/volunteer-obligations`** — surfaces the decorated units + aggregate dashboard stats consumed by the Vrijwilligers dashboard.

### Changed
- `invoice_type` enum on `rondo_invoice` accepts `volunteer_fine` (was `discipline|membership|manual`). All REST validators and the InvoiceNumbering format map updated accordingly.

## [33.5.0] - 2026-05-26

### Added — Volunteer Policy admin section (Fase B)

- **New top-level Vrijwilligers section in the sidebar** with four sub-routes:
  - `/vrijwilligers` — Dashboard with eligibility stats (totaal, gezinnen, spelers) and quick navigation cards.
  - `/vrijwilligers/vog` — VOG management (existing page accessible at both the legacy `/vog` and the new canonical path).
  - `/vrijwilligers/iva` — Three-tab IVA approval queue (Wacht op goedkeuring / Goedgekeurd / Niet ingeleverd) with one-click approve/intrekken.
  - `/vrijwilligers/diensten` — Diensttype catalog (cards with VOG/IVA/sleutel badges, capacity) + recent shifts list, links into WP admin for editing.
  - `/vrijwilligers/vrijstellingen` — Filterable view of vrijgestelde personen by reason (commissielid / trainer-leider / betaalde vrijwilliger / handmatig).
- **`VrijwilligersRoute` capability guard** mirrors the existing `VOGRoute` / `FinancieelRoute` pattern; uses `can_access_vrijwilligers` from the current-user response.
- **API client additions:** `getVolunteerEligibility()`, `getVolunteerExemption()`, `getManagedCommissies()`.

## [33.4.0] - 2026-05-26

### Added — Volunteer Policy foundation (Fase A)

Backend foundation for the AWC vrijwilligersbeleid. No user-visible UI changes yet — Fase B will surface this in a Vrijwilligers admin section. See `.planning/VOLUNTEER-POLICY-ROADMAP.md` for the full plan.

- **`rondo_vrijwilligers` capability + role.** Mirrors the existing `vog` / `financieel` / `ledenadministratie` pattern. `rondo_bestuur` inherits it; administrators auto-receive it. New pool roles: `rondo_pool_schoonmaak`, `rondo_pool_activiteiten`, `rondo_pool_werkploeg`. `can_access_vrijwilligers` flag added to `GET /rondo/v1/user/current`.
- **Three new CPTs** (admin-only): `dienst_type` (task catalog), `shift_template` (seasonal recurring rules), `dienst_shift` (concrete scheduled shifts). Includes ACF field groups and REST-exposed post meta.
- **`VolunteerSeeder`.** Idempotent on-activation seed of six initial dienst types (Terreinmeester, Kantine bar/keuken-prep/keuken-verkoop, Schoonmaak, Terreinonderhoud) and three Rondo-managed pool commissies (Schoonmaakpoule, Activiteitenpoule, Werkploeg terreinonderhoud). Stored option `rondo_volunteer_pool_commissies` maps pool slugs to commissie IDs.
- **`VolunteerExemptionResolver`.** Single-source-of-truth service for the 4 auto + 1 manual vrijstellingsroutes (active commissie / staff role / betaalde vrijwilliger / handmatig). Consumed by every downstream feature so we never duplicate the rule.
- **`VolunteerEligibilityService` + `GET /rondo/v1/volunteer-eligibility`.** Pure derived view of the eligible units per KNVB-seizoen — one gezin-unit per huishouden with a JO15- player (parents-relationship primary, address fallback), one speler-unit per O17+ player. Multi-child scaling defaults to per-gezin (board decision pending).
- **`GET /rondo/v1/volunteer-exemption/{person_id}`.** Returns the resolved exemption reason for a single person, or null.
- **`GET /rondo/v1/managed-commissies`.** Public list of Rondo-managed commissie IDs so `rondo-sync` can skip them during its untracked-commissie cleanup. The `rondo-sync` repo's `submit-rondo-club-commissies.js` now consumes this whitelist.
- **`VolunteerStatus::OPTION_STAFF_ROLES` + default list** (Trainer, Hoofdtrainer, Assistent-trainer, Leider, Teamleider, Teammanager, Coördinator, Scheidsrechter). Surfaced in `GET/POST /rondo/v1/volunteer-roles/settings` next to `player_roles` and `excluded_roles` so admins can refine via the Capabilities settings UI.
- **New ACF field group on `person`:** `betaalde_vrijwilliger` + `vergoeding_reden` + `vergoeding_tot` (vrijwilligersvergoeding), `vrijgesteld_handmatig` + `vrijstelling_reden` + `vrijstelling_seizoen` (handmatige vrijstelling), `datum-iva` + `iva-certificaat` + `iva-approved` (IVA alcoholtraining tracking — admin-approval flow).

### Deferred (board decisions)
- Multi-child scaling rule for ouderplicht (#6).
- Boete-pipeline (#7) — trigger, ontvanger, vrijkoop, bedrag.
- VOG bulk-rollout mechanism (#8).
- IVA geldigheidstermijn (#9).

## [33.3.1] - 2026-05-26

### Added
- **New `ledenadministratie` capability and `rondo_ledenadministratie` role.** Admins now control who can see the Leden → Onboarding screen and send onboarding emails. Editable in **Instellingen → Beheer → Capabilities** (Ledenadministratie column) and `Rollen` (the new base role). Administrators auto-receive the capability; `rondo_bestuur` includes it.
- `can_access_ledenadministratie` flag on `GET /rondo/v1/user/me`.

### Changed
- **Onboarding screen is now gated behind the new `ledenadministratie` capability** (previously visible to every approved user). The sidebar item disappears for users without it; direct navigation to `/people/onboarding` yields the "Geen toegang" page. `POST /rondo/v1/people/onboarding-email` rejects callers without the capability (admins always pass).

## [33.3.0] - 2026-05-26

### Added
- **People list: "Nieuw lid dit seizoen" filter.** New boolean filter on the People list (under Lidmaatschap) that shows every member whose `lid-sinds` (membership start date) falls inside the current Dutch sports season (1 July through 30 June). Same season-window logic as "Afgemeld dit seizoen" but on the opposite end of the membership lifecycle. Backed by `lid_sinds_season=1` on `GET /rondo/v1/people`. Does NOT auto-include former members — the goal is current members who joined this season, not people who joined and already left.
- When this filter is active, the People list force-shows `Lid sinds` and `Type lid` as the first two columns after Name (parallel to how the cancellation filter force-shows lid-sinds + lid-tot). User's stored Column Settings are untouched while the filter is off.

### Changed
- DRY: extracted the season-window computation in `class-rest-people.php` into a private `get_current_season_window()` helper shared by `lid_tot_season` and `lid_sinds_season`. Behaviour unchanged for the existing filter.

## [33.2.2] - 2026-05-26

### Changed
- People list filter sections re-tuned: `Foto datum` moved to **Persoon** (it's a profile attribute, not a VOG-volunteer concern) and `Gewijzigd` moved to **Administratief** (the Activiteit section is gone — recent-edits sits better with bookkeeping filters like financiële blokkade).

## [33.2.1] - 2026-05-26

### Changed
- **People list filter dropdown is now grouped + two-column.** Filters are organised into named sections (Lidmaatschap / Persoon / Activiteit / Vrijwilliger & VOG / Administratief) and laid out in a two-column grid on screens ≥ 640px (single column below). The panel widens from 256px → 576px on roomy viewports, caps at 80vh with internal scroll, and clamps its left edge so the wider panel can't run off the right side of the viewport. Resolves the "too tall to fit on a laptop screen" complaint without losing any filters.
- `createColumn()` accepts an optional `filterSection` argument (surfaced as `meta.filterSection`) — columns without a section land in a default "Overige" group. Backwards-compatible: existing consumers (NogTeFactureren, CommissiesList, PeopleAnniversaries) still render correctly without sections.

## [33.2.0] - 2026-05-26

### Added
- **Onboarding screen for new members and new volunteers.** New page under Leden → Onboarding with two tabs: Nieuwe leden (lid-sinds ≤ 30 days, no member onboarding email sent) and Nieuwe vrijwilligers (vrijwilliger-sinds ≤ 60 days, huidig-vrijwilliger=1, no volunteer onboarding email sent). Each row has a Verstuur button; the toolbar has a multi-select bulk send. Sent recipients drop out of the list automatically because the server stamps a timestamp on the person.
- **Two new ACF datetime fields on person:** `onboarding-email-lid-sent` and `onboarding-email-vrijwilliger-sent` (readonly). Server stamps these on successful send so the same person can't be re-onboarded for the same type.
- **Two new welkomstmail templates.** Settings → Beheer → Welkomstmail now has three sub-tabs: Account aanmaken (existing), Nieuw lid (new), Nieuwe vrijwilliger (new). Each onboarding template stores subject + HTML body; supports `{first_name}`, `{infix}`, `{last_name}`, `{full_name}`, `{email}`, `{club_naam}` placeholders. Templates fall back to a sensible Dutch default when unset.
- **New REST endpoints:**
  - `POST /rondo/v1/people/onboarding-email` — body `{ person_ids: int[], type: 'lid'|'vrijwilliger' }`. Sends per person, stamps the timestamp only on a successful `wp_mail()`, returns per-id status (sent / already_sent / no_email / send_failed / not_found). People with no email are reported back, not errored.
  - `GET|POST /rondo/v1/onboarding/email-settings/{lid|vrijwilliger}` — read/update the new welkomstmail templates (admin only).
  - `GET /rondo/v1/people/filtered` gains two parameters: `onboarding_new_members=1` and `onboarding_new_volunteers=1`.
- Onboarding emails get a timeline entry on the person via `CommentTypes::create_email_log()`, same as account-provisioning welcome emails.

### Fixed
- Birthday entries in the daily digest email and the dashboard reminder widget now display the person's birth year (instead of the current year) and append the age they're turning, e.g. `15 mei 1990 (wordt 36)`.
- Credit invoice emails now use the dedicated credit template body and subject instead of the regular payment-request template. `InvoiceEmailSender::send()` now detects `_invoice_kind === 'credit'` before template selection and pulls `FinanceConfig::get_credit_email_template()` and `get_credit_email_subject()`. Previously only the heading was swapped, so credit invoices still went out with "pay this amount" body copy + QR code + betaallink placeholders.

### Added (other)
- `rondo_finance_credit_email_subject` option + settings UI field for configuring the credit invoice email subject (default: `Creditfactuur {factuur_nummer} - {organisatie_naam}`).

## [33.1.1] - 2026-05-26

### Changed
- When the "Afgemeld dit seizoen" filter is active, the People list now force-shows `Lid sinds` and `Lid tot` as the first two columns after Name. User's stored Column Settings are untouched — the override only applies while the filter is on, so the matching reason is always visible without manual column tweaking.

## [33.1.0] - 2026-05-26

### Added
- **People list: "Afgemeld dit seizoen" filter.** New boolean filter on the People list that shows every member whose `lid-tot` (membership end date) falls inside the current Dutch sports season (1 July through 30 June). The server computes the season window from `today`: before 1 July it spans (year-1)-07-01 → year-06-30, on/after 1 July it spans year-07-01 → (year+1)-06-30. Backed by `lid_tot_season=1` on `GET /rondo/v1/people`. Auto-flips `include_former=1` on the server because Sportlink marks members as former once their `lid-tot` has passed, so without it the season's cancellations would silently disappear from the list.
- **`Lid tot` as a list column.** Added `lid-tot` to the available Sportlink columns so it can be enabled via Column Settings, sorted on, and exported via CSV. Pairs naturally with the new filter — turn on the filter, add the column, sort by date.

## [33.0.0] - 2026-04-09

### Changed
- **Fee system god class retired.** `Rondo\Fees\MembershipFees` (2,137 lines, 65 methods) has been decomposed into 8 focused classes across 5 direct-style phases (214–218) of the v33.0 Fee Service Decomposition milestone. No user-visible behaviour changes — this is a pure internal refactor, validated at every phase with a production fee snapshot diff (4,021 active members) and (for phases 217–218) a `wp option list` byte-for-byte diff of all 101 `rondo_*` option keys.
- Phase 214: Extracted `Rondo\Fees\FeeCategoryResolver` (362 lines) with 8 category-matching methods (`predict_next_season_age_class`, `get_category_by_age_class`, `get_category`, `get_category_by_team_match`, `get_category_by_werkfunctie_match`, `is_recreational_team`, `is_donateur`, `find_recreational_team_ids`). Added the `bin/fee-snapshot.sh` / `bin/fee-snapshot.php` regression harness used by every subsequent phase. `is_donateur` signature changed from `int $person_id` to `array $werkfuncties` so the resolver stays stateless.
- Phase 215: Extracted `Rondo\Fees\FamilyGroupingService` (487 lines) with 7 family-discount methods (`build_family_groups`, `get_family_key`, `recalculate_all_family_positions`, `recalculate_family_positions_for_person`, `clear_all_family_discount_meta`, `normalize_postal_code`, `extract_house_number`). Fixed the STRU-04 coupling smell in `FeeCacheInvalidator`: it now holds a typed `FamilyGroupingService` property instead of reaching through a god-object reference.
- Phase 216: Extracted `Rondo\Fees\FeeCalculator` (454 lines) with the 4 fee-math methods (`calculate_fee`, `calculate_fee_with_family_discount`, `calculate_full_fee`, `get_prorata_percentage`). Explicit typed constructor collaborators per STRU-02. The `FeeCalculator ↔ FamilyGroupingService` cycle is broken with a deferred callable on `FamilyGroupingService`. `get_effective_werkfuncties` and `normalize_werkfuncties_for_fee_match` promoted from private to public.
- Phase 217: Extracted `Rondo\Fees\MembershipFeeSettings` (590 lines) with 26 Options API storage methods + 2 legacy payload migrations (`maybe_migrate_age_classes`, `maybe_migrate_matching_rules`). Zero constructor dependencies. The matching-rules migration inlines its own recreational-team lookup to avoid coupling the settings repository to `FeeCategoryResolver`. 42 call sites across 4 files (`class-rest-fees.php`, `class-public-payment-page.php`, `class-bulk-invoice-creator.php`, `class-rest-google-sheets.php`) rewired to go through `$fees->settings()->X()`.
- Phase 218: **Deleted `includes/class-membership-fees.php` entirely** (Option A from the roadmap). Remaining methods distributed into three new focused classes: `Rondo\Fees\FeeCache` (277 lines, 10 cache/snapshot storage methods), `Rondo\Fees\PersonFeeContext` (244 lines, 4 person-data helpers with zero dependencies), and `Rondo\Fees\FeeServices` (193 lines, static service locator with 6 lazy accessors and zero methods of its own). 17 `new MembershipFees()` instantiations deleted and ~74 method calls rewired to `FeeServices::accessor()->X()` across 5 files.
- `FeeCacheInvalidator` constructor now pulls its `FeeCache` and `FamilyGroupingService` references from `FeeServices` instead of constructing its own `MembershipFees` instance.

### Fixed
- `bin/deploy.sh` wrinkle documented: rsync doesn't `--delete` theme files, so Phase 218's deleted `class-membership-fees.php` required a manual `ssh + rm + composer dump-autoload -o --quiet + wp cache flush` to clear the orphan on production. See `.planning/phases/218-retire-membershipfees/NOTES.md` for the recipe.

### Removed
- `includes/class-membership-fees.php` — the god class is gone.
- `MembershipFees::get_fee_for_person()` (non-cached variant) — dead code, no callers.
- `MembershipFees::get_calculation_status()` — dead diagnostic, never wired into any REST endpoint or UI.

## [32.8.0] - 2026-03-30

### Changed
- Dashboard now loads with a single API call (down from 10): user profile, dashboard settings, VOG counts, discipline case count, and todos all consolidated into `/rondo/v1/dashboard`
- Dashboard API response preloaded via fetch in wp_head so the browser starts fetching before JS boots
- Birthday reminders query rewritten with SQL date math instead of loading all people and filtering in PHP
- Anniversary data cached in a shared transient (1 day TTL) instead of per-user since the data is the same for all users
- Added composite database index on wp_postmeta (meta_key, meta_value) for faster dashboard count queries
- Anniversary query loads only person IDs first, does date math on raw meta, then loads full objects only for matches
- Replaced 3 separate WP_Query count queries (people, volunteers, open feedback) with a single SQL query
- VOG counts computed server-side in one SQL query instead of 3 separate filtered people API calls
- Transient cache TTL increased from 5 to 15 minutes (with same invalidation triggers)
- Frontend staleTime aligned to 15 minutes to match server cache
- Removed debug console.log from useDashboard hook

## [32.6.1] - 2026-03-15

### Changed
- Dashboard REST endpoint now uses transient caching (5-minute TTL per user) with automatic invalidation on post/comment changes
- Fixed N+1 query in `get_recently_contacted_people()` — single batched query with meta cache warmup instead of individual `get_post()` calls
- Pre-filter former members in anniversary query at database level instead of per-row PHP checks
- Added `update_post_meta_cache` to recent people query to reduce ACF field lookups
- Added `staleTime` to `useDashboardSettings()`, `useTodos()`, `useCurrentSeason()`, and discipline case count queries to prevent unnecessary refetches

## [32.6.0] - 2026-03-13

### Changed
- Extracted `/user/*` REST endpoints into new `Rondo\REST\UserSettings` controller
- Extracted `/users/*` REST endpoints into new `Rondo\REST\Users` controller
- Extracted `/reminders/*` and `/anniversaries/*` REST endpoints into new `Rondo\REST\Reminders` controller
- Extracted `/vog/*` REST endpoints and person/discipline-case response filters into new `Rondo\REST\Vog` controller
- Extracted `/fees/*`, `/membership-fees/*`, `/current-season`, and billing settings into new `Rondo\REST\Fees` controller
- Moved `get_sportlink_fields()` static method from `Api` to `UserSettings` class
- Reduced `class-rest-api.php` from 7,854 to 3,566 lines (-4,288 lines, 55% reduction)
- Updated `class-rest-custom-fields.php` to reference `UserSettings::get_sportlink_fields()`
- Replaced `\RONDO_Reminders` alias usage with proper `\Rondo\Collaboration\Reminders` in extracted controllers
- Extracted `/lettermint/*` REST endpoints into new `Rondo\REST\Lettermint` controller
- Extracted `/finance/settings`, `/finance/branding` into new `Rondo\REST\FinanceSettings` controller
- Extracted capability matrix, age-group access, volunteer roles, werkfuncties, custom roles into `Rondo\REST\Capabilities` controller
- Extracted shared post-sharing code (check_post_owner, get_shares, add_share, remove_share) to `Base` class, eliminating ~390 duplicated lines across People/Teams/Commissies
- Added shared `upload_entity_logo` and `set_entity_logo` helper methods to `Base` class
- Removed 18 unused backward-compatibility class aliases from `functions.php` (kept 13 that are still referenced)
- Removed orphaned cron cleanup code for hooks removed in v29.0
- Final `class-rest-api.php` size: 1,620 lines (79% reduction from 7,854)

## [32.5.0] - 2026-03-13

### Added
- Invoice detail: "Markeer als onbetaald" button on paid invoices to revert status back to "Verstuurd"
- Backend audit trail for marking invoices as unpaid (`_manually_marked_unpaid_at`, `_manually_marked_unpaid_by`)
- Unpaid audit trail fields included in invoice detail API response

### Changed
- Marking an invoice as unpaid preserves the original sent date and due date instead of overwriting them

## [32.4.0] - 2026-03-12

### Added
- "Rol toevoegen" input on Capabilities tab — admin can create custom roles from the UI
- Delete button (trash icon) per custom role on Capabilities tab with confirmation dialog
- `is_custom` flag in capability matrix API response to distinguish base vs custom roles

### Changed
- Ledendata hint text corrected from "Geen selectie = alle leden" to "Geen selectie = geen leden"
- Ledendata display shows "Geen leden" (not "Alle leden") for roles without age-group config
- Capabilities matrix table: sticky first column with horizontal scroll for many-column support
- Functies mapping table: sticky first column with horizontal scroll
- Commissie mapping table: sticky first column with horizontal scroll

## [32.3.0] - 2026-03-12

### Added
- Custom role management — admin can create and delete custom roles via REST API (`POST/DELETE /rondo/v1/settings/roles`)
- Dynamic role system — `UserRoles::get_all_roles()` merges built-in base roles with admin-created custom roles from wp_option
- Custom roles automatically appear in capability matrix, Functies mapping, and Commissie mapping
- Custom roles included in CapabilitySync (syncable from Sportlink functies)
- API client methods `createCustomRole()` and `deleteCustomRole()` for frontend

### Changed
- **BREAKING (security):** Ledendata default inverted — roles without age-group config and without management capabilities now see **zero members** instead of all members. Management-capability users (fairplay, vog, financieel, etc.) are unaffected.
- `UserRoles::ROLES` renamed to `UserRoles::BASE_ROLES` — all consumers updated to use `get_all_roles()` for dynamic role resolution
- Empty age-group arrays in filter queries use safe SQL (`1 = 0` or impossible match) instead of invalid `IN ()` syntax

## [32.2.0] - 2026-03-12

### Added
- Age-group info banner on People list showing permitted leeftijdsgroepen for restricted users
- Access-denied message on PersonDetail for age-group restricted persons (distinct from generic errors)
- Kaderlijst bypass for age-group filtering — snapshot rebuild works correctly for all users

## [32.1.0] - 2026-03-12

### Added
- Age-group access filtering — per-role leeftijdsgroep restrictions for member data visibility
- "Ledendata" column in Settings → Beheer → Capabilities with multi-select per role
- REST endpoints `GET/POST /rondo/v1/settings/age-group-access` for age-group access configuration
- `permitted_age_groups` field in `/rondo/v1/user/me` response
- Users with management capabilities (manage_options, fairplay, vog, financieel, toegangscontrole, manage_clothing) bypass age-group filtering automatically

## [32.0.0] - 2026-03-12

### Added
- Role-capability matrix UI in Settings → Beheer → Capabilities subtab — admin can view and toggle 5 custom Rondo capabilities (fairplay, vog, financieel, toegangscontrole, manage_clothing) per role
- REST endpoints `GET/POST /rondo/v1/settings/capability-matrix` for reading and updating role capabilities

### Changed
- All 6 `current_user_can('administrator')` checks replaced with `current_user_can('manage_options')` for proper capability-based authorization
- Role capability system changed from hardcoded constants to admin-configurable matrix via WordPress role definitions

### Fixed
- `register_role()` no longer re-adds capabilities to existing roles, allowing matrix changes to persist across page loads

## [31.15.0] - 2026-03-12

### Changed
- Person detail page: hide Relaties card when person has no relationships
- Person detail page: show Account card only when person has a linked WordPress account (instead of for all volunteers)
- Person detail page: tab labels now show item counts (Tijdlijn, Rollen, Kleding, Tuchtzaken)

### Removed
- Person detail page: VOG status pill removed from person header (VOG info still available in VOG card on profile tab)

## [31.14.0] - 2026-03-12

### Added
- Credit badge (rose) and "Credit" filter option on the Facturen list page — credit invoices now display a distinct rose "Credit" badge instead of cyan "Handmatig", and can be filtered separately via the Type filter

## [31.13.1] - 2026-03-12

### Removed
- iCal feed feature — deleted `ICalFeed` class, all PHP/JS references, and developer documentation

## [31.13.0] - 2026-03-12

### Added
- Audit trail bij handmatig betaald markeren — toont wie en wanneer in de Betaalgegevens kaart

## [31.12.0] - 2026-03-12

### Added
- Spelactiviteit field displayed in Sportlink card on person profiles
- "Spelactiviteit zonder team" filter in People list to find people with a spelactiviteit but no team assigned

## [31.11.0] - 2026-03-12

### Added
- Confirmation dialog before toggling contributie exclusion/inclusion with Dutch messages
- Immediate FinancesCard refresh after exclusion toggle (no page reload required)
- Email notification to Secretaris and Penningmeester on contributie exclusion toggle

### Changed
- Extracted `RoleFinder` helper from `LettermintWebhook` for reusable role-based user lookup
- RoleFinder uses case-sensitive matching to exclude "Wedstrijdsecretaris" when searching for "Secretaris"

## [31.10.0] - 2026-03-12

### Added
- Credit invoice email template configurable in Finance Settings (E-mail > Creditfacturen)

### Changed
- Credit invoices now stay in "Verstuurd" status after sending (no longer auto-marked as paid)

## [31.9.0] - 2026-03-12

### Added
- Mollie payment details (method, paid-at, dashboard URL, consumer info) extracted and stored when webhook confirms payment
- "Betaalgegevens" section on invoice detail page showing payment method, timestamp, and Mollie Dashboard link
- Per-installment payment method and Mollie Dashboard link in installment timeline table
- Consumer name and IBAN displayed for iDEAL payments
- One-time backfill script (`bin/backfill-mollie-details.php`) to populate payment details for already-paid invoices

## [31.8.1] - 2026-03-12

### Changed
- VOG email templates now use rich text editor (Tiptap) instead of plain textareas
- Backend stores HTML content via `wp_kses_post()` instead of stripping tags with `sanitize_textarea_field()`
- Email sending applies inline styles to HTML templates for email client compatibility
- Legacy plain-text templates are still supported via automatic detection and conversion

## [31.8.0] - 2026-03-12

### Removed
- CardDAV server and all related backend code (server, backends, REST endpoint, WP-CLI command, rewrite rules)
- CalDAV provider class (unused since Google Calendar sync removal in v29.0)
- `sabre/dav` Composer dependency
- CardDAV subtab from Settings > Connections UI
- CardDAV URL API endpoint and frontend method

## [31.7.0] - 2026-03-08

### Changed
- Replaced contact_info repeater with 6 fixed contact fields (email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2)
- ContactEditModal now uses simple fixed-field form instead of dynamic repeater
- Removed social link types (LinkedIn, Twitter, Bluesky, etc.) from person contact fields
- Removed backward-compatible contact_info array from REST API responses

### Removed
- Legacy contact_info repeater field group from ACF JSON
- normalizeContactInfo utility function

## [31.6.49] - 2026-03-07

### Changed
- Financieel instellingen beheren meerdere rekeningen nu uitsluitend in de `Mollie`-tab, met per rekening een eigen API-sleutel en standaardrekening per factuurtype (`contributie`, `tuchtzaken`, `handmatig`).
- Alleen handmatige facturen tonen nog een rekeningkeuze, en alleen wanneer Mollie actief is en er meerdere bruikbare Mollie-rekeningen zijn.
- Contributie- en tuchtzaakfacturen leggen nu automatisch de ingestelde standaard Mollie-rekening voor hun factuurtype vast.

### Removed
- De oude globale Mollie API-sleutel en legacy `tr_` webhook/payment-flow zijn verwijderd; Mollie werkt nu alleen nog met payment links per geselecteerde rekening.

## [31.6.48] - 2026-03-06

### Fixed
- De rich text editor behoudt nu bestaande regeleindes uit plain-text e-mailsjablonen en invoice body overrides bij het openen/bewerken.

## [31.6.47] - 2026-03-06

### Changed
- De per-factuur override voor `E-mail body` in het aanmaak-/bewerkformulier gebruikt nu ook de rich text editor in plaats van een plain textarea.

## [31.6.46] - 2026-03-06

### Changed
- Factuur-PDF's blijven de juridische naam uit de financiële instellingen gebruiken.
- Overige finance-uitingen zoals factuurmails, herinneringen, de publieke betaalpagina en membership-pass branding gebruiken nu primair `Clubnaam`, met terugval op de juridische naam als `Clubnaam` leeg is.

## [31.6.45] - 2026-03-06

### Changed
- `Standaard e-mail voor gewone facturen` gebruikt nu dezelfde rich text editor als de andere finance e-mailsjablonen.
- Gewone factuurmails accepteren nu HTML-opmaak in de standaard bodytemplate, terwijl bestaande platte-tekstsjablonen automatisch compatibel blijven.

## [31.6.44] - 2026-03-06

### Added
- Factuurdetailpagina ondersteunt nu een expliciete `Verstuur testmail` actie met vrij invulbaar testadres voor concept-, verstuurde en verlopen facturen.

### Changed
- Testverzending van een conceptfactuur gebruikt de bestaande factuurmailer met override-adres en `[TEST]` onderwerp, maar zet de factuur niet op `Verstuurd`.

## [31.6.43] - 2026-03-06

### Changed
- Outbound emails now use a shared branded HTML wrapper with improved spacing, typography, footer treatment, and CTA buttons.
- Finance emails, VOG emails, welcome emails, todo assignment emails, mention notifications, weekly digests, and Lettermint test/verification mails now render inside the same HTML layout while keeping their existing placeholders and business logic intact.

## [31.6.42] - 2026-03-06

### Fixed
- De factuurdetailpagina toont `Ter attentie van` niet langer dubbel wanneer een opgeslagen waarde al met `T.a.v.` begon.

## [31.6.41] - 2026-03-06

### Fixed
- De interne bankrekeningnaam wordt niet langer getoond in de betaalgegevens op facturen of in de factuurdetailweergave.

## [31.6.40] - 2026-03-06

### Changed
- De onderste `Bewerk concept` knop is verwijderd van de factuurdetailpagina; alleen de knop in de header blijft over.

## [31.6.39] - 2026-03-06

### Changed
- Op externe handmatige facturen staan de velden `E-mail` en `CC` nu naast elkaar in plaats van onder elkaar.

## [31.6.38] - 2026-03-06

### Added
- Handmatige externe facturen hebben nu een apart `CC`-veld naast het hoofd-e-mailadres.

### Changed
- Het `CC`-adres wordt niet op de factuur of PDF getoond, maar wordt wel als echte cc-ontvanger meegenomen bij het verzenden van de factuurmail.

## [31.6.37] - 2026-03-06

### Added
- Financieel instellingen ondersteunen nu meerdere bankrekeningen met per rekening een interne naam, tenaamstelling en IBAN.
- Facturen hebben nu een expliciete bankrekeningkeuze; bij nieuwe conceptfacturen wordt standaard de rekening gekozen die aan de actieve betalingsprovider gekoppeld is.

### Changed
- Betaalgegevens op de factuur en in de factuurdetailweergave tonen nu de gekozen bankrekening in plaats van alleen een globale IBAN.
- Rabobank-betaallinks gebruiken nu de op de factuur vastgelegde rekening, zodat providerkoppeling en factuurweergave dezelfde rekening blijven gebruiken.

## [31.6.36] - 2026-03-06

### Changed
- De knop `Bewerk concept` staat nu in de factuurheader bovenaan de detailpagina.
- Op de factuurdetailpagina toont `Ter attentie van` nu alleen de naam, zonder dubbel `T.a.v.`-prefix.
- De twee extra factuurvelden onder de vervaldatum worden nu ook in de factuur-PDF opgenomen.
## [31.6.35] - 2026-03-06

### Added
- Conceptfacturen hebben nu een volledige bewerkmodus waarmee alle invoervelden van de factuur opnieuw aangepast kunnen worden zolang de factuur nog op `Concept` staat.

### Changed
- Nieuwe handmatige facturen en het bewerken van conceptfacturen gebruiken nu hetzelfde conceptformulier, zodat velden en validatie consistent blijven.

## [31.6.34] - 2026-03-06

### Added
- Handmatige externe facturen hebben nu extra velden `Ter attentie van` en `E-mail` naast klantnaam en adres.

### Changed
- Het externe e-mailadres komt nu mee in de factuurweergave en op de PDF.
- Handmatige externe facturen kunnen nu rechtstreeks naar het ingevulde externe e-mailadres worden verzonden.
## [31.6.33] - 2026-03-06

### Changed
- De knop `Ververs uit Sportlink` op de persoon-detailpagina is nu zichtbaar voor gebruikers met `toegangscontrole` (waaronder `Rondo Bestuur`), naast admins.
- De endpoint `POST /rondo/v1/sportlink/sync-individual` accepteert nu eveneens `toegangscontrole` gebruikers, zodat de knop voor Bestuur ook functioneel is.

## [31.6.32] - 2026-03-06

### Changed
- Facturenoverzicht gebruikt nu standaard een actieve statusfilter `Alle niet betaalde`.
- Als die statusfilter wordt weggeklikt (of op `Alle` gezet), worden weer alle facturen getoond.

## [31.6.31] - 2026-03-06

### Changed
- Invoice emails now send to up to two email addresses on the invoice person record, instead of only the first email.
- For minors (<18), invoice emails now also include up to two parent records and send to up to two email addresses per parent when available.
- The same multi-recipient behavior now applies consistently to direct invoice sends, installment emails, and invoice reminders.
- Contact phone numbers that start with `+316` or `06` are now automatically classified as `mobile` during person creation and when saving profile contact details.
- Person profile pages now normalize existing contact data at render time, so previously stored `phone` entries with Dutch mobile numbers are treated as mobile and automatically get a WhatsApp quick link.

## [31.6.30] - 2026-03-05

### Fixed
- Invoice reminder cron now also includes discipline invoices (including legacy discipline invoices) so unpaid discipline invoices receive the same 14/28-day reminder flow as membership invoices.

## [31.6.29] - 2026-03-03

### Fixed
- Removed stale dashboard meetings widget wiring that still triggered requests to `/wp-json/rondo/v1/calendar/today-meetings` after the endpoint had been removed.
- Removed obsolete dashboard meetings customization/default-card entries so users no longer keep a defunct `meetings` card in saved layout settings.

## [31.6.28] - 2026-03-02

### Added
- Todo assignees now automatically receive an email when a task is assigned or reassigned to them.
- Assignment emails use subject format `[Rondo] Nieuwe taak: {titel taak}` and include assigner name, task title, and task description.

## [31.6.27] - 2026-03-02

### Changed
- Person contact type choices now include `email2` so Rondo Sync can persist a second email address (`Email2`) without being dropped by ACF validation.
- Sportlink secondary phone values (`Mobile2`, `Telephone2`) continue to sync as extra `mobile` / `phone` contact rows with label `2`, and are now documented as part of the contact sync contract.
- Contact editing and person detail rendering now treat `email2` as an email contact (mail icon + `mailto:` behavior), consistent with synced Sportlink data.
- vCard export (frontend and backend paths) now includes `email2` entries as email fields.

### Fixed
- Global search email fragment matching now also checks `contact_type = email2`, so members can be found by secondary email addresses synced from Sportlink.

## [31.6.26] - 2026-03-01

### Fixed
- Fixed a regression where KNVB/email search logic was mistakenly injected into `get_current_user()` instead of `global_search()`, causing no results in the global search modal.
- Global search email matching now queries ACF repeater storage keys (`contact_info_%_contact_value`) directly, restoring e-mail search results.

## [31.6.25] - 2026-03-01

### Fixed
- Global search (`/rondo/v1/search`) now correctly finds people by KNVB ID (`knvb-id` and `custom_knvb-id` meta keys).
- Global search now correctly finds people by e-mail address from `contact_info` (ACF repeater e-mail contacts).

## [31.6.24] - 2026-03-01

### Fixed
- Lettermint bounce/complaint follow-up tasks are now deduplicated per mail via `message_id + recipient` (with existing `event_id` dedupe as primary key), preventing multiple todos for the same sent email when providers send retry/follow-up events.

## [31.6.23] - 2026-03-01

### Added
- Lettermint settings now include a dedicated `Verificatiemail` block with separate verification sender fields:
  - `Verificatiemail From e-mailadres`
  - `Verificatiemail From naam`

### Changed
- Verification emails now send with an explicit `From` header resolved from verification sender settings, with fallback to default Lettermint sender settings.

## [31.6.22] - 2026-03-01

### Changed
- Verification-bounce todo notes now hide all Lettermint technical event details (event type, message ID, tag, timestamp, reason, etc.) and show only the actionable contact text template.

## [31.6.21] - 2026-03-01

### Changed
- Verification-bounce follow-up todos now use the requested actionable copy:
  - Title: `Het email adres van {naam} werkt niet meer.`
  - Notes include the full contact request text and ready-to-send message template with resolved `{naam}`, `{firstname}`, and `{email}` values.

## [31.6.20] - 2026-03-01

### Fixed
- Lettermint webhook signature verification now accepts server-normalized header variants and maps them to canonical `X-Lettermint-*` names before SDK verification, fixing missed bounce processing when headers were present but differently keyed.

## [31.6.19] - 2026-03-01

### Fixed
- Lettermint verification mails now send metadata values as strings, matching Lettermint API validation and fixing `422 Unprocessable Content` errors when clicking the verification mail button on bounce tasks.

## [31.6.18] - 2026-03-01

### Added
- Added a new Lettermint verification email flow for bounce follow-up tasks, including a task action that sends a verification email from the todo.
- Added configurable Lettermint verification email subject/body templates in `Instellingen > Koppelingen > Lettermint`.
- Todo API responses now include `lettermint` bounce metadata and an `email_verification` action hint (`can_send`, `recipient`) for the frontend.

### Changed
- Lettermint transport now accepts `X-Rondo-Metadata` headers and forwards sanitized values into Lettermint metadata for webhook correlation.
- Lettermint webhook handling now routes verification-email bounces back to the user who sent the verification email (instead of always Secretaris/admin fallback).

### Fixed
- Verification-email bounces now mark the matched person contact email as inactive (label suffix `(inactief)` and `_rondo_inactive_emails` post meta), and create a follow-up todo for the sender to collect a working address.

## [31.6.17] - 2026-03-01

### Added
- Todo create/edit modals now support assigning a task to another user via a searchable user picker.
- Todo API responses now include assignee data (`assigned_user_id` and `assignee`) for UI display.

### Changed
- Todo visibility now includes tasks you created and tasks assigned to you.
- Todo overview messaging now reflects the new creator+assignee visibility model.

### Fixed
- Dashboard task counters now include assigned tasks (not just self-authored tasks).

## [31.6.16] - 2026-03-01

### Added
- Sidebar menu item `Taken` now shows the number of open tasks using dashboard stats.

### Changed
- Sidebar menu counts are now locale-formatted (`nl-NL`) so larger values (including over 100) remain clearly readable.

## [31.6.15] - 2026-03-01

### Added
- Dashboard `Open taken` card now shows clickable related persons that navigate directly to `/people/{id}`.
- Todo view popover now shows related persons as links to their person detail page.

### Fixed
- Pressing `Esc` now closes the todo popover on the dashboard.

## [31.6.14] - 2026-03-01

### Changed
- People list team display now prefers the direct `team` meta value from the filtered people endpoint (with fallback to legacy work history parsing in the frontend).
- Filtered people endpoint now returns `team_id` directly, reducing frontend dependency on `acf.work_history` for team resolution.

## [31.6.13] - 2026-03-01

### Added
- Added backend support for `orderby=organization` on `/rondo/v1/people/filtered`.

### Changed
- Team column in `/people` is now sortable from the UI and maps to backend organization sorting.

## [31.6.12] - 2026-03-01

### Fixed
- Production birthday sorting bug: `_birthdate` ACF field-key values (e.g. `field_birthdate`) are now ignored as non-date values.
- Birthday sorting/filtering now only uses validated date-formatted meta values and prefers `birthdate` data, with safe fallback to `_birthdate` only when it is an actual date.

## [31.6.11] - 2026-03-01

### Fixed
- People birthday sorting now falls back to both `_birthdate` and `birthdate` post meta, preventing silent fallback to name sorting on datasets that only store one of the two keys.
- Birthday year/month filters now use the same dual-key fallback logic for consistent results across legacy and current data.

## [31.6.10] - 2026-03-01

### Fixed
- Birthday sorting in `/people` now uses full chronological `Y-M-D` ordering instead of partial month/day ordering.
- Birth year and birth month filters now parse both `YYYY-MM-DD` and `YYYYMMDD` values consistently from denormalized `_birthdate` meta.

## [31.6.9] - 2026-03-01

### Fixed
- People list sorting now only enables for columns with supported backend sort fields, preventing invalid `orderby` values from causing unreliable sorting behavior.
- Added a defensive frontend `orderby` fallback so stale/unsupported sort query values resolve safely to `first_name`.

## [31.6.8] - 2026-03-01

### Added
- Added a new `Verjaardag` column to the `/people` ledenoverzicht and made it available in the column settings metadata.

### Changed
- Updated people list default visible columns to include `Verjaardag` between `Team` and `Laatst gewijzigd`.
- Added a new month-based birthday filter (`Verjaardagmaand`) to the `/people` filter dropdown.

### Fixed
- People list preference reset now restores the updated default columns including `Verjaardag`.

## [31.6.7] - 2026-02-28

### Fixed
- Updated public payment CTA copy from "Betalen in {x} termijnen" to "Betaal in {x} termijnen" for installment buttons.

## [31.6.6] - 2026-02-28

### Fixed
- Public payment page now supports a late-season 2-installment fallback when only two payment dates remain (typically March/April), so members can still choose spread payments near season end.
- Plan-selection POST validation now mirrors UI plan availability checks, preventing invalid forged plan submissions when a plan is not currently available.

## [31.6.5] - 2026-02-28

### Added
- Added `bin/person-values.sh`, a remote-safe REST utility to find people and get/set person field values using application password auth (`RONDO_API_URL`, `RONDO_API_USER`, `RONDO_API_PASSWORD`), including automatic `fields`/`acf`/`meta` fallback.

## [31.6.4] - 2026-02-27

### Fixed
- Demo fixture export now includes `rondo_anniversary_milestones`, so imported demo sites keep Jubilarissen milestone configuration used by both the Jubilarissen page and dashboard card.
- Demo import cleanup now also removes `rondo_anniversary_milestones` to avoid stale anniversary settings between imports.

## [31.6.3] - 2026-02-27

### Changed
- Lettermint testmail toont nu duidelijke transportcontext: project-ID en of de route handmatig ge-override is of automatisch via de project default route loopt.

## [31.6.2] - 2026-02-27

### Fixed
- Lettermint verzending gebruikt nu standaard de project default route (geen verplichte `route` parameter meer op `POST /v1/send`), waardoor `422 Invalid route provided` wordt voorkomen.
- Added optional per-message route override via email header `X-Lettermint-Route` (or `X-LM-Route`) for advanced use cases.

## [31.6.1] - 2026-02-27

### Fixed
- Replaced temporary direct Lettermint class includes with a fallback `Rondo\\*` autoloader in theme bootstrap to prevent class-not-found fatals when Composer classmaps are stale.

## [31.6.0] - 2026-02-27

### Added
- Added Lettermint settings for a default `From` email address and `From` name in `Instellingen > Koppelingen > Lettermint`.

### Changed
- Lettermint transport now uses the configured default sender values when a `wp_mail()` call does not provide an explicit `From` header.

## [31.5.1] - 2026-02-27

### Changed
- Simplified Lettermint webhook status UI to a single green check/red cross indicator.
- Moved detailed webhook metadata (project, route ID, webhook ID, secret state) into `Geavanceerde velden`.
- Added direct help links in Lettermint settings for finding Project API tokens and Team API tokens in the Lettermint dashboard.
- Removed the webhook endpoint field from the Lettermint settings UI because endpoint management is fully internal.

## [31.5.0] - 2026-02-27

### Added
- Added project discovery endpoint `GET /wp-json/rondo/v1/lettermint/projects` to list Lettermint projects with resolved default-route metadata for settings UI selection.
- Added a Lettermint project dropdown in `Instellingen > Koppelingen > Lettermint` so admins can explicitly choose which Lettermint project is used for webhook provisioning.

### Changed
- Lettermint webhook creation now resolves the route from the selected project via `GET /v1/projects/{projectId}/routes` and uses that project’s `is_default` route.
- Stored Lettermint project selection (`rondo_lettermint_project_id`) is now reused automatically for future webhook creation calls.
- Multi-project validation errors now include discovered project names to clarify available choices.

## [31.4.0] - 2026-02-27

### Changed
- Lettermint webhook setup now auto-detects a route ID via Team API projects (`default_route_id`) when no route ID is configured.
- Lettermint webhook creation now automatically stores the generated webhook secret from the API response when available.
- Lettermint settings UI no longer requires route ID and webhook secret in the default flow; both moved to optional advanced overrides.

## [31.3.0] - 2026-02-27

### Added
- Added a Lettermint test email action in `Instellingen > Koppelingen > Lettermint`, including a configurable recipient field and inline delivery feedback.
- Added a new admin endpoint `POST /wp-json/rondo/v1/lettermint/test-email` to send a tagged test message through `wp_mail()` and the Lettermint transport.

## [31.2.0] - 2026-02-27

### Added
- Added a new Lettermint settings section under `Instellingen > Koppelingen > Lettermint` with secure fields for project token, team token, route ID, and webhook secret.
- Added a new admin endpoint `POST /wp-json/rondo/v1/lettermint/webhook/create` that provisions a Lettermint webhook through the Team API and stores the resulting webhook metadata in WordPress options.

### Changed
- Extended club config API responses with Lettermint configuration status flags (`has token/secret`) and the resolved webhook URL so the settings UI can safely manage credentials without exposing stored secrets.

## [31.1.0] - 2026-02-27

### Added
- Added native Lettermint transport in Rondo Club by intercepting `wp_mail()` and sending through the Lettermint PHP SDK (including support for HTML/text bodies, CC/BCC/Reply-To, custom headers, tags, metadata, and attachments).
- Added a signed public webhook endpoint at `POST /wp-json/rondo/v1/lettermint/webhook` to process Lettermint delivery events.
- Added automatic follow-up task creation for Secretaris users (fallback: admins) on `message.hard_bounced`, `message.soft_bounced`, and `message.spam_complaint` events.
- Added persistent suppressed-email tracking in WordPress option `rondo_lettermint_suppressed_emails` with event history per recipient.

### Changed
- Theme bootstrap now initializes Lettermint mail transport on all requests and Lettermint webhook handling on REST requests.
- Email delivery configuration now supports Lettermint credentials via constants, environment variables, or options.

## [31.0.21] - 2026-02-27

### Added
- Credit invoice PDFs now render a `CREDIT` watermark using the same style as paid-invoice watermarks.

## [31.0.20] - 2026-02-27

### Fixed
- Credit invoice line amounts now preserve the sign entered by finance users (both positive and negative values), in both draft total preview and backend invoice creation.

## [31.0.19] - 2026-02-27

### Fixed
- Fixed paid-invoice PDF generation crash by passing watermark color as a supported hex string to mPDF watermark rendering.

## [31.0.18] - 2026-02-27

### Changed
- Paid invoice PDFs no longer render the `Betaalgegevens` section.
- Paid `BETAALD` watermark now uses native mPDF watermark color set to the primary club accent color.

## [31.0.17] - 2026-02-27

### Fixed
- Paid invoice PDF watermark now uses native mPDF watermark rendering, so `BETAALD` is applied at 45° with 50% opacity reliably.

## [31.0.16] - 2026-02-27

### Changed
- Paid invoice detail now hides payment links consistently (API + UI), including legacy paid invoices that still had a stored link.
- Paid invoice PDF watermark styling updated to a 45° diagonal `BETAALD` stamp at 50% opacity.

## [31.0.15] - 2026-02-27

### Changed
- When an invoice is marked as `paid`, payment artifacts are now cleared automatically: `payment_link`, payment-provider IDs, and QR code.

## [31.0.14] - 2026-02-27

### Fixed
- Paid invoice detail now shows a `Genereer PDF` action when no PDF exists yet, instead of only a disabled `Download PDF` button.

## [31.0.13] - 2026-02-27

### Changed
- Invoice PDFs now show a large `BETAALD` watermark when invoice status is paid.
- Payment QR codes are now omitted from paid invoice PDFs.

## [31.0.12] - 2026-02-27

### Added
- Added a direct "Markeer als betaald (zonder versturen)" action on draft invoices in invoice detail, with a dedicated confirmation dialog to prevent accidental clicks.

### Changed
- The paid-status confirmation flow now uses a stricter warning message when transitioning from `draft` to `paid` without sending first.

## [31.0.11] - 2026-02-27

### Added
- Added a new finance endpoint `POST /rondo/v1/invoices/{id}/draft-line-items` to append a manual correction line to draft invoices with a positive (surcharge) or negative (discount) amount.
- Added a new "Extra regel toevoegen" action in invoice detail for draft invoices to update totals by adding a correction line.

### Changed
- Draft invoice total recalculation now runs after adding manual correction lines and clears any generated PDF to avoid stale totals.

## [31.0.10] - 2026-02-26

### Changed
- Refactored person meta registration in `PostTypes` to use one shared string-meta list (including `vrijwilliger-sinds` and `team`) while keeping `_exclude_from_contributie` as a separately auth-gated boolean meta field.

## [31.0.9] - 2026-02-26

### Changed
- Registered `person` post meta `vrijwilliger-sinds` for REST read/write access via native WordPress `meta`.
- Person Detail Sportlink card now reads `vrijwilliger-sinds` from `meta` first (with ACF fallback for existing data), so display no longer depends on ACF.

## [31.0.8] - 2026-02-26

### Changed
- Sportlink card now always shows the `Vrijwilliger sinds` row on person detail pages, rendering `-` when no date is available.

## [31.0.7] - 2026-02-26

### Fixed
- Fixed People list sorting when switching to a different column by applying `sort` and `order` URL updates in a single state update, so `Lid sinds` and other columns now sort reliably.

## [31.0.6] - 2026-02-26

### Fixed
- Fixed People column toggling for older saved column preferences by appending newly available columns (including `lid-sinds`) to `column_order`, so enabling them in column settings now immediately shows them.

## [31.0.5] - 2026-02-26

### Fixed
- Sportlink card team display now uses `person.meta.team` as a plain string value (no team-ID lookup), so values like `JO19-2` always render.

## [31.0.4] - 2026-02-26

### Added
- Added new Sportlink-synced person field `vrijwilliger-sinds` (Vrijwilliger sinds), including People list sorting support and display in the Sportlink card on person detail.

### Changed
- Anniversary volunteer start-date resolution now uses the earliest available date from `vrijwilliger-sinds` and `work_history` start dates, and stores that minimum in the volunteer start-date cache.

## [31.0.3] - 2026-02-26

### Added
- Added support to adjust both gezinskorting and instapkorting on sent/unpaid contributiefacturen via `POST /rondo/v1/invoices/{id}/membership-discount`.
- Added a second instapkorting input in invoice detail so both discounts can be edited together.

### Changed
- Membership discount endpoint now accepts `family_discount_percent` and `entry_discount_percent` and recalculates both discount line items before updating totals.

## [31.0.2] - 2026-02-26

### Added
- Added a new finance endpoint `POST /rondo/v1/invoices/{id}/membership-discount` to update family discount percentage on sent/unpaid membership invoices.
- Added an inline "Gezinskorting (%)" action in the invoice detail page for sent/overdue membership invoices.

### Changed
- Membership invoice discount updates now recalculate `line_items` and `total_amount` and clear `pdf_path` to prevent stale PDF totals.

### Fixed
- Prevented unsafe discount changes when installment payment links were already issued or when any installment is already paid.

## [31.0.1] - 2026-02-25

### Added
- Added native WordPress person post meta field `team` (REST-exposed) to store a direct primary team link for sync workflows (e.g. `rondo-sync`) without requiring ACF.

## [31.0.0] - 2026-02-25

### Added
- Added a new `/kaderlijst` roster page using DataTable with grouped age/year/team rendering, persisted snapshot storage in WordPress options, and a manual refresh button that clears query cache before rebuilding.

### Changed
- Updated Kaderlijst structure and ordering to match club expectations: age-group coordinators at the top of each age group, year coordinators at the top of each year block, then team rows.
- Updated Kaderlijst labels and normalization: `Jaargroep` renamed to `Jaarlaag`, `Overig` mapped to `Senioren`, `Mini's` grouped under `JO7` in `Pupillen`, and `AWC ` prefixes removed for `JO` team names.
- Updated intra-team sorting to use explicit role priority order for coaches/staff roles.

### Fixed
- Fixed missing coordinator visibility in Kaderlijst (including `Technisch Coördinator` and `Organisatorisch coördinator` rows) and corrected Junioren/Pupillen coordinator placement.
- Fixed Kaderlijst loading performance and reliability by removing heavy fan-out requests and optimizing roster build behavior.
- Fixed `Invalid time value` crashes on person detail pages by hardening date parsing/formatting across PersonDetail and related cards/components.
- Fixed duplicate work-history roles introduced by repeated Sportlink syncs by deduplicating rows server-side after individual sync.

## [30.11.5] - 2026-02-25

### Changed
- Kaderlijst column label `Jaargroep` is now `Jaarlaag`.
- Team names in Kaderlijst now strip the `AWC ` prefix when the team name contains a `JOxx` pattern.

### Fixed
- Kaderlijst now excludes non-team senior roles except the explicit `Coördinator Senioren` role.

## [30.11.4] - 2026-02-25

### Fixed
- Corrected Kaderlijst age-group inference for coordinator roles without `JOxx` in the role text, so `Coördinator Junioren` and `Coördinator Pupillen` no longer fall back to `Senioren`.
- Corrected Kaderlijst ordering to render per-year blocks as expected: age-group coordinator first, then `JO19` coordinator + `JO19` teams, then `JO17` coordinator + `JO17` teams, etc.

## [30.11.3] - 2026-02-25

### Changed
- Kaderlijst ordering now enforces coordinator hierarchy inside groups: age-group coordinators first, then year-group coordinators, then team-level roles.

### Fixed
- Optimized Kaderlijst loading performance by fetching paginated teams/people in parallel batches and limiting REST payloads to required fields only.

## [30.11.2] - 2026-02-25

### Fixed
- Kaderlijst now maps `Mini's` entries to `JO7` under `Pupillen`.
- Replaced `Overig` labeling in Kaderlijst with `Senioren`.
- Included current coordinator roles without a linked team (such as `Organisatorisch coördinator` and `Technisch Coördinator`) by inferring grouping from role text when needed.

## [30.11.1] - 2026-02-25

### Fixed
- Fixed `/kaderlijst` infinite loading by replacing per-team people fan-out requests with a bulk build from `people.work_history`, reducing request count and preventing stalled loading states.

## [30.11.0] - 2026-02-25

### Added
- New **Kaderlijst** page at `/kaderlijst`, built with the shared DataTable component, to replace static trainer/coaching sheets with a live roster view.
- Roster rows now aggregate active team assignments across all teams, include first name, surname (`infix + last_name`), role, mobile, and email, and allow people to appear multiple times for different roles/teams.
- Nice-to-have filters added for `leeftijdsgroep` and `jaargroep` in the roster toolbar.

### Changed
- Sidebar navigation now includes **Kaderlijst** as a child item under Teams.
- Kaderlijst ordering now follows youth structure from older to younger (`Junioren` JO19→JO12, then `Pupillen` JO11→JO6) with grouped display that suppresses repeated age group/year/team values on consecutive rows.

## [30.10.17] - 2026-02-25

### Added
- New manual finance invoice creation screen (`/financien/facturen/nieuw`) with unified normal/credit workflow, multi-line items, optional member link, customer override data, due-date override, email override, and invoice number preview.
- Finance settings now include a dedicated `E-mail -> Gewone facturen` tab with configurable default subject and body template for manual invoices.

### Changed
- Invoice numbering now uses a shared yearly sequence in `{year}F{0001}` format for all invoice kinds, including credits.
- Invoice API now supports manual invoices (`invoice_type=manual`), optional customer metadata, invoice kind (`normal|credit`), and next-number preview endpoint.
- Sending credit invoices now records a payment-adjustment timestamp and auto-transitions to paid after finalize.
- On `/financien/facturen/nieuw`, replaced the manual `Lid-ID (optioneel)` input with a member name search field that selects and stores the linked `person_id`.
- On `/financien/facturen`, moved `Nieuwe factuur` into the DataTable toolbar so it appears next to the column settings cog.
- New invoice form now pre-fills e-mail subject/body with the configured defaults as editable values, while keeping template variables functional.

### Fixed
- New invoice due date now defaults to 14 days from today.
- Due-date input layout no longer shifts when toggling between `Lid` and `Extern`; it always renders on its own row.
- Added a down chevron to the “PO nummer of andere gegevens toevoegen?” control to make the expandable behavior explicit.
- Fixed a runtime initialization error on `/financien/facturen/nieuw` caused by default email template variables being referenced before initialization.

## [30.10.16] - 2026-02-25

### Fixed
- Switched the app shell to use document-level scrolling on mobile (while keeping fixed-shell scrolling on desktop) to prevent long pages from getting clipped at the bottom.
- Kept global safe-area/mobile bottom spacing in the shared `<main>` container for consistent end-of-page reachability across screens.

## [30.10.15] - 2026-02-25

### Fixed
- Applied a global mobile scroll-end buffer in the shared app layout (`<main>`) so long pages like `/people/jubilarissen`, `/vog`, and other list/detail screens can reliably scroll to the bottom.
- Removed page-specific bottom padding workaround from PersonDetail in favor of the shared layout fix.

## [30.10.14] - 2026-02-25

### Fixed
- Added extra mobile bottom padding on `/people/:id` so the end of PersonDetail content remains reachable above Safari bottom UI and the floating todos button.

## [30.10.13] - 2026-02-25

### Fixed
- Fixed mobile scrolling on person detail pages by making the app shell height account for the WordPress admin bar offset.
- Added `min-h-0` constraints in the main layout flex containers to prevent content clipping and allow full scroll to the end.

## [30.10.12] - 2026-02-24

### Fixed
- Improved anniversaries endpoint performance for volunteer jubilees by batching oldest `work_history_*_start_date` lookup across people instead of per-person ACF repeater reads.
- Prevented `/people/jubilarissen` from hanging during long anniversary queries on larger datasets.

## [30.10.11] - 2026-02-24

### Changed
- Volunteer anniversaries are now calculated from the oldest `work_history.start_date` instead of `lid-sinds`.

## [30.10.10] - 2026-02-24

### Changed
- In **Settings → Beheer → Jubilarissen**, custom milestones now use the same left checkbox interaction as built-in milestones.
- Unchecking a custom milestone checkbox now removes that custom milestone.

## [30.10.9] - 2026-02-24

### Changed
- In **Settings → Beheer → Jubilarissen**, custom milestones now render in the same grid tile style as built-in milestones.
- Custom milestone tiles now include a remove cross (`x`) on the right side.

## [30.10.8] - 2026-02-24

### Added
- Active users endpoint (`GET /rondo/v1/users`) now returns `last_active` per user.
- Last activity tracking for authenticated users (stored in user meta) with 5-minute write throttling.

### Fixed
- Settings → Gebruikers “Actieve gebruikers” table no longer uses an internal vertical scroll cap and grows naturally with content.
- Added “Laatst actief” column to the active users table with `-` fallback when no activity data exists yet.

## [30.10.7] - 2026-02-23

### Added
- Jubilarissen periode-dropdown now includes:
  - `Afgelopen 6 maanden`
  - `Afgelopen 12 maanden`
  - `Afgelopen 18 maanden`

### Changed
- Anniversaries API now supports a backward-looking window via `days_back`.
- Jubilarissen page now supports both future and past period windows.

## [30.10.6] - 2026-02-23

### Fixed
- Clothing member search dropdown is no longer clipped by the card border on `/kleding` (parent card now allows visible overflow).

## [30.10.5] - 2026-02-23

### Fixed
- Clothing member search now uses server-side search instead of loading all people via `/people`.
- Search dropdown now activates from 3 typed characters and shows a guidance message below that threshold.
- Selected member details are fetched only after selection, reducing initial load and improving search responsiveness.

## [30.10.4] - 2026-02-23

### Changed
- Clothing handout/return flow on `/kleding` redesigned to be member-first:
  - First search/select a member.
  - Then show member card with name, photo, team, and currently assigned clothing.
  - Existing assigned items now include direct `Retourneren` actions.
  - New handout now starts from `Nieuw item uitgeven`, then item + size selection.

### Fixed
- Reworked member search selector on clothing page to use a clickable filtered dropdown for reliable selection performance.
- Team display is now resolved for the selected member using entity lookup.

## [30.10.3] - 2026-02-23

### Fixed
- Replaced the clothing member picker input with a proper searchable selector, so `Selecteer lid (typ om te zoeken)` now reliably sets the selected member.
- Transaction form now enforces item sizes:
  - `Maat` is a dropdown populated from the selected item's configured `available_sizes`.
  - Maat selection is reset automatically when switching to an item that doesn't include the previously selected size.
- Backend now validates transaction size against the selected item's configured sizes and rejects invalid sizes.

## [30.10.2] - 2026-02-23

### Changed
- Kledingpagina tabs aangepast op basis van feedback:
  - `Uitgifte / inname registreren` staat weer op de eerste tab (`Overzicht`).
  - `Transacties` tab bevat nu alleen transactielijst + CSV export.
- Conditie-opties voor kledingtransacties vertaald naar Nederlands:
  - `Nieuw` (default)
  - `Goed`
  - `Redelijk`

### Fixed
- Het `Regels` blok op `/kleding` wordt nu niet getoond als eligibility is uitgeschakeld.
- Bij uitgeschakelde eligibility verdwijnen seizoensinvoer en eligibility-gerelateerde invoervelden volledig uit het transactiesformulier.

## [30.10.1] - 2026-02-23

### Added
- New `Instellingen → Kleding` settings tab for clothing managers with:
  - Eligibility toggle (on/off)
  - Cooldown seasons field
  - Current season field

### Changed
- Clothing page (`/kleding`) now uses 3 tabs:
  - `Overzicht`
  - `Items` (includes item create form)
  - `Transacties` (includes transaction form, transaction table, CSV export)
- Eligibility settings were removed from `/kleding` and moved to `Instellingen → Kleding`.
- When eligibility is disabled, related rule fields are hidden in settings and override input is disabled in transaction form.

### Fixed
- Clothing settings API now supports `eligibility_enabled` to fully bypass eligibility checks when turned off.

## [30.10.0] - 2026-02-23

### Added
- New clothing management backend module with:
  - `rondo_clothing_item` CPT for clothing catalog items.
  - `rondo_clothing_txn` CPT for handout/return transactions.
  - `clothing_category` taxonomy.
  - New role `rondo_clothing_manager` and capability `manage_clothing`.
  - New REST API endpoints under `/rondo/v1/clothing/*` for items, transactions, person profile, overview, export, and settings.
- New frontend page `/kleding` with inventory metrics, item management, transaction registration, CSV export, and eligibility settings.
- New `Kleding` tab on member detail pages showing eligibility, current items, history, and outstanding deposit.
- Developer documentation for clothing feature and API in the docs site.

### Changed
- Current-user API (`GET /rondo/v1/user/me`) now returns `can_access_clothing`.
- Access control now includes clothing post types and hides clothing data from users without `manage_clothing`.

## [30.9.18] - 2026-02-23

### Changed
- Jubilarissen page now defaults to a **6-month** lookahead instead of 3 months.
- Added period dropdown on Jubilarissen page with selectable windows: **3, 6, 9, 12 months**.

## [30.9.17] - 2026-02-23

### Added
- New **Jubilarissen** page under **Leden** (`/people/jubilarissen`) with:
  - fixed 3-month lookahead window
  - filters for `Alle`, `Leden`, and `Vrijwilligers`
  - direct links to member detail pages
- New sidebar navigation item **Jubilarissen** indented under **Leden**.

## [30.9.16] - 2026-02-23

### Added
- New admin settings UI for anniversaries at **Settings → Beheer → Jubilarissen**.
- Milestone editor for both categories:
  - Member jubilees
  - Volunteer jubilees
- Support for adding and removing custom milestones from the UI (whole and half years).

### Changed
- Anniversary milestone settings are now manageable fully in the app UI (no API client/manual call needed).

## [30.9.15] - 2026-02-23

### Added
- New anniversaries (jubilarissen) backend API endpoints:
  - `GET /rondo/v1/anniversaries` for upcoming anniversaries.
  - `GET/POST /rondo/v1/anniversaries/settings` for milestone configuration.
- New dashboard card: `Jubilarissen`, including dashboard customization support (show/hide/order).
- Developer docs page for the anniversaries feature and API.

### Changed
- Dashboard summary endpoint now includes `upcoming_anniversaries`.
- Anniversary calculation now supports Dutch half-year milestones (for example `12,5 jaar`) and uses `lid-sinds` as the canonical start date.
- Added configurable default milestone sets for member and volunteer anniversaries.

## [30.9.14] - 2026-02-23

### Changed
- Public membership pass page role selection now requires exactly one active role when multiple roles are available:
  - Removed the implicit "all roles/functions" option.
  - Replaced dropdown with radio options.
  - Wallet actions stay unavailable until one role is selected.
- Team label `Verenigingsbreed` is now normalized to `Vereniging` in wallet role labels and generated pass content.
- Google Wallet object payload now includes:
  - `textModulesData`: `FUNCTIE`, `TEAM`, optional `KNVB ID`, and `SEIZOEN`
  - `barcode.alternateText` as empty string
  - `hexBackgroundColor` derived from accent settings (fallback `#006935`)
  - `logo.contentDescription` localized in Dutch

## [30.9.13] - 2026-02-23

### Changed
- Public membership pass page now uses a local Dutch Apple Wallet badge SVG (`NL_Add_to_Apple_Wallet_RGB_101921.svg`) for the Add to Apple Wallet button instead of the custom text button.

## [30.9.12] - 2026-02-23

### Fixed
- Google Wallet club logo now uses a generated padded PNG variant (transparent margin on all sides) to prevent crest clipping in wallet card previews.

## [30.9.11] - 2026-02-23

### Changed
- Public membership pass page now uses a local Dutch Google Wallet badge SVG (`nl_add_to_google_wallet_add-wallet-badge.svg`) for the Add to Google Wallet button instead of the external hosted image.

## [30.9.10] - 2026-02-23

### Changed
- Google Wallet membership pass now sets `subheader` to member type (`Bondslid` or `Verenigingslid`) so it appears above the member name.
- Google Wallet pass data section now uses two text modules matching the requested layout:
  - `FUNCTIE` (`id: functie`)
  - `TEAM` (`id: team`)
- Removed the extra `Seizoen` text module from Google Wallet pass details to keep the card data compact.

## [30.9.9] - 2026-02-23

### Fixed
- Google Wallet membership pass layout now uses the club logo as a standard wallet logo (instead of a large hero image), removing the oversized crest panel.
- Google Wallet pass top row now shows the club name as `cardTitle` while keeping the member name in the main header to avoid duplicate names.
- Existing Google Wallet objects are now fully updated via `update` (not `patch`) so old hero-image styling is replaced.

## [30.9.8] - 2026-02-23

### Fixed
- Sidebar `Tuchtzaken` badge now uses the WordPress REST total count header (`X-WP-Total`) instead of the loaded page length, so counts above 100 display correctly.

## [30.9.7] - 2026-02-23

### Added
- Demo fixture export now includes membership pass wallet configuration keys (`rondo_membership_pass_*`) using portable demo-safe values.

### Changed
- Demo fixture export now includes the newer finance configuration used by the finance dashboard and reminder flows, including invoice reminder template keys.
- Demo data docs were updated to document finance and membership pass settings coverage in fixtures.

### Fixed
- `wp rondo demo import --clean` now also removes `rondo_membership_pass_%` options to prevent stale wallet config from leaking between demo imports.

## [30.9.6] - 2026-02-23

### Changed
- Updated global heading typography:
  - `h1`/`h2` use Montserrat with stronger title weight by default.
  - Brand-gradient heading style is applied to `h1.text-brand-gradient`, `h2.text-brand-gradient`, and `.brand-heading`.
  - Added `.heading-plain` opt-out utility for headings that should remain non-gradient.
- Refreshed the shared `.card` component style to match the current website design:
  - Light background (`#F2F7FA`), subtle border, stronger shadow, and gradient top accent line.
  - Hover state now uses a slightly darker surface and elevated shadow.
  - Dark mode cards keep existing dark surface behavior.

## [30.9.5] - 2026-02-23

### Changed
- Updated copy on the public membership pass page:
  - Added intro text under `Digitale ledenpas`.
  - Changed `Voeg toe aan Wallet` to `Voeg de ledenpas toe aan je wallet!`.
  - Changed `Rol/functie op pas` label to a clearer question.
- Increased spacing below the wallet section heading and below the role dropdown for improved readability.
- Wallet action buttons are now device-aware on the public pass page:
  - iOS devices show Apple Wallet only.
  - Android devices show Google Wallet only.
  - Other devices continue showing both options.

## [30.9.4] - 2026-02-23

### Fixed
- `Lidpas Scanner` menu item is now consistently hidden on desktop for all users, including admins.

## [30.9.3] - 2026-02-23

### Changed
- Settings page layout now uses full available width to improve usability for larger role/permissions screens.

## [30.9.2] - 2026-02-23

### Changed
- `Lidpas Scanner` menu item is now hidden on desktop and shown only in the mobile sidebar.

## [30.9.1] - 2026-02-23

### Fixed
- Google Wallet object payload now includes required top-level `header`, fixing Android error: `header must be set` (`invalidResource`).

## [30.9.0] - 2026-02-23

### Added
- New `Wallets` subtab under **Settings → Koppelingen** for Apple/Google wallet pass configuration.

### Changed
- Wallet pass settings moved out of **Financieel** and into **Koppelingen**.
- `Lidpassen` labels in the settings UI are renamed to `Wallets`.
- Updated the Info tab product description text to reflect Rondo Club (removed legacy CRM copy).

## [30.8.2] - 2026-02-23

### Changed
- Added more spacing between the role/function selector and wallet buttons on the public membership pass page.
- Public membership pass page now uses the configured club logo as favicon (including Apple touch icon).

## [30.8.1] - 2026-02-23

### Fixed
- Membership pass tier selection now depends on `type-lid` (not KNVB ID presence), so `Verenigingslid` remains `Verenigingslid` even when a KNVB ID exists.
- KNVB ID row/field is now shown only for `Bondslid` passes and hidden for `Verenigingslid` passes.

## [30.8.0] - 2026-02-23

### Added
- Membership passes now support a second eligibility tier for `Verenigingslid` members without a KNVB ID.
- One-time v2 backfill (`rondo_membership_pass_backfill_v2_done`) to generate pass URLs for newly eligible members.

### Changed
- Pass label now uses member tier naming (`Bondslid` / `BONDSLID` and `Verenigingslid` / `VERENIGINGSLID`) instead of generic `Lid` / `LID`.
- Public `/lidpas/{token}` page now accepts eligible non-KNVB members and hides KNVB row when no KNVB ID exists.
- Apple pass now omits the KNVB ID field when no KNVB ID is present.
- Google pass generation no longer requires KNVB ID when member is otherwise eligible.

## [30.7.4] - 2026-02-23

### Changed
- Swapped field order on Apple Wallet membership pass card so `FUNCTIES` appears before `KNVB ID`.

## [30.7.3] - 2026-02-23

### Changed
- Scanner invalid-state card now shows the red helper text `Geen lid meer` directly below `Ongeldige ledenpas`.

## [30.7.2] - 2026-02-23

### Changed
- Simplified `Lidpas Scanner` UI to camera-only flow by removing the top intro card and hidden manual token section.
- Scanner result now prominently shows member photo + name for identity check.
- Scanner result heading now reflects membership validity: `Geldige ledenpas` (green) for active, `Ongeldige ledenpas` (red) for non-active.

### Fixed
- Membership pass verify response now includes KNVB ID reliably for scanner output (`knvb_id` plus compatibility alias `knvb-id`).

## [30.7.1] - 2026-02-23

### Fixed
- Added a JS-based QR scan fallback (`jsQR`) for browsers without `BarcodeDetector` support (notably iOS Chrome), so camera scanning remains available outside Safari.

## [30.7.0] - 2026-02-22

### Added
- New in-app `Lidpas Scanner` page with camera QR scanning (where browser-supported) and manual token verification fallback
- Scanner verification result view with member and pass status details
- New role `Rondo Toegangscontrole` with dedicated capability `toegangscontrole`

### Changed
- Scanner route and sidebar navigation are now capability-gated behind `toegangscontrole`
- Current user REST payload now includes `can_access_toegangscontrole`
- `Rondo Bestuur` role now includes `toegangscontrole` capability
- Capability sync now auto-adds `Rondo Toegangscontrole` role when `Rondo Bestuur` is assigned

## [30.6.0] - 2026-02-22

### Added
- Person detail header now shows a membership pass quick link (card icon) next to social/Sportlink/Freescout links when a member has a pass URL

## [30.5.1] - 2026-02-22

### Changed
- Added extra spacing under the "Voeg toe aan Wallet" heading on the public membership pass page
- Increased vertical spacing between Apple and Google wallet buttons on mobile

## [30.5.0] - 2026-02-22

### Added
- Role/function selector on the public `/lidpas/{token}` page for members with multiple active roles

### Changed
- Apple Wallet pass generation now supports a selected role and shows only that team/function when chosen
- Google Wallet pass generation now supports a selected role and uses role-specific object IDs to allow separate passes per selected role

## [30.4.4] - 2026-02-22

### Changed
- Aligned Apple and Google Wallet badges on the public membership pass page to a consistent height and baseline
- Improved wallet badge layout responsiveness for small screens with a stacked mobile layout

## [30.4.3] - 2026-02-22

### Changed
- Updated public membership pass page wallet CTAs to badge-style buttons
- Google Wallet CTA now uses the official Google-hosted Add to Google Wallet badge asset
- Apple Wallet CTA now uses a dedicated Add to Apple Wallet badge treatment for stronger platform consistency

## [30.4.2] - 2026-02-22

### Changed
- Membership pass display name now includes `infix` (tussenvoegsel) for Apple and Google Wallet passes
- Membership pass content now aggregates all active team/commissie links instead of only the first active work-history record
- Membership pass content now includes active functies from work history
- Public `/lidpas/{token}` landing page now shows full member name with infix

## [30.4.1] - 2026-02-22

### Added
- In-app help popup in Finance Settings → Lidpassen with step-by-step setup instructions for Google Wallet and Apple Wallet
- Guidance in popup covers where to obtain Google service-account JSON, Google Issuer ID, Apple `.p12`, Apple Team ID, and Pass Type Identifier

## [30.4.0] - 2026-02-22

### Added
- New Finance Settings tab: **Lidpassen** for Apple/Google Wallet configuration
- Upload-based credentials for membership passes (Apple `.p12` certificate and Google service-account `.json`) stored as media attachment IDs
- Support for `.p12` and `.json` uploads for financieel/admin users
- New finance settings fields for Apple pass identifiers and Google Wallet issuer/class configuration

### Changed
- Membership pass generators now resolve uploaded media files (attachment IDs) instead of requiring path-based configuration

## [30.3.0] - 2026-02-22

### Added
- Public membership pass landing page at `/lidpas/{token}` with wallet actions for Apple and Google
- Per-person pass token + URL storage in post meta (`_membership_pass_token`, `_membership_pass_url`) for members with KNVB ID
- Automatic pass URL generation/update on person save and ACF save
- `MembershipPassApple` class for Apple Wallet `.pkpass` generation
- `MembershipPassGoogle` class for Google Wallet object creation and Add-to-Wallet URL generation
- `GET /rondo/v1/membership-passes/people/{person_id}/landing-url` endpoint to retrieve ensured landing URLs
- `membership_pass_url` added to person REST responses

## [30.2.0] - 2026-02-22

### Added
- Membership pass QR token service with signed JWT issuance (`GET /rondo/v1/membership-passes/people/{person_id}/qr-token`)
- Membership pass scanner verification endpoint (`POST /rondo/v1/membership-passes/verify`) with token validation and member status response
- Frontend API client helpers for issuing and verifying membership pass QR tokens

## [30.1.0] - 2026-02-22

### Added
- Automatic reminder emails for membership invoices where member hasn't selected a payment plan (first reminder at 2 weeks, second at 4 weeks with BCC to treasurer)
- Configurable email templates for invoice reminders in Finance Settings > E-mail > Factuurherinneringen

## [30.0.0] - 2026-02-20

### Added
- Sidebar avatar: users with a linked Sportlink person see their profile photo in the sidebar
- Default avatar icon (User icon) for users without a linked person or photo
- User name displayed in sidebar footer next to avatar
- `linked_person_photo` field added to GET /rondo/v1/user/me response

## [29.4.0] - 2026-02-20

### Added
- In-app Profile page with Sportlink identity display and password change form
- POST /rondo/v1/user/password endpoint for in-app password management
- GET /rondo/v1/user/me now returns linked_person_name and active_functies
- Sidebar UserMenu links to in-app profile instead of wp-admin for non-admin users

## [29.3.0] - 2026-02-20

### Added
- Automatic capability sync: user roles are kept in sync with Sportlink Functies during rondo-sync runs (grant and revoke)
- Manual override support: admin-granted roles survive automatic sync
- Administrator guard: administrator users are never modified by capability sync
- On-demand "Sync nu uitvoeren" button in Functies settings to re-apply mapping
- rondo-sync capability sync step integrated into functions pipeline as Step 5

## [29.2.0] - 2026-02-20

### Added
- User provisioning: admin can create WordPress accounts from person records
- Branded welcome email with 7-day password-set link
- Bidirectional person-user linking (person stores user ID, user stores person ID)
- KNVB ID stored on user meta for sync dedup
- Idempotent provisioning (re-trigger is a safe no-op)
- AccountCard on person detail showing provisioning status
- Welkomstmail settings subtab for customizing welcome email template
- Provisioning REST endpoints (POST provision, GET/POST settings)
- Enhanced users list with linked person info
- Enhanced person REST response with linked_user_id and welcome_email_sent_at

## [29.1.0] - 2026-02-20

### Added
- Functie-to-Role mapping configuration: admin can configure which Sportlink Functies (job titles) grant which Rondo WordPress roles via a checkbox matrix in Settings > Beheer > Functies
- `FunctieCapabilityMap` PHP class with `get_map()`, `update_map()`, `get_roles_for_functie()` static methods
- REST API endpoints `GET/POST /rondo/v1/functie-capability-map` (admin only) for reading and persisting the mapping

## [29.0.0] - 2026-02-20

### Added
- CSV export on People, VOG, and Contributie list pages (semicolon delimiter, UTF-8 BOM for Dutch Excel)

### Changed
- Email delivery switched from Gravity SMTP/Postmark (US) to Lettermint (EU) for European data sovereignty
- Google OAuth simplified to Sheets-only scope (removed Contacts and Calendar scopes)
- GoogleOAuth namespace changed from `Rondo\Calendar` to `Rondo\Sheets`
- Email FROM addresses use root domain extraction for Lettermint-compatible verified domain

### Removed
- Google Contacts sync (5 PHP classes, REST endpoints, WP-CLI commands, cron hooks)
- Google Calendar sync (4 PHP classes, REST endpoints, WP-CLI commands, cron hooks)
- Gravatar REST endpoint and frontend integration
- Settings Connections UI for Calendar and Contacts (now only CardDAV and API-toegang)
- ~1,700 lines of dead frontend code from Settings, API client, and hooks

## [28.1.0] - 2026-02-19

### Added
- Invoice type filter on Facturen page (Contributie / Tuchtrecht)
- Payment plan filter on Facturen page (Volledig / 3 termijnen / 8 termijnen)
- Type badge column in Facturen list showing invoice type at a glance
- Installment timeline card on invoice detail page showing per-installment progress
- Installment plan and payment plan fields in invoice API responses
- Invoice type and installment plan fields in invoice list API response

### Changed
- Refactored invoice list API meta_query to support composable filters (status + type + payment_plan + person_id)

## [28.0.0] - 2026-02-19

### Added
- Bulk membership fee invoice creation via async WP-Cron batching (50 invoices per batch)
- Single-member membership invoice creation from person detail Financieel card
- Billing method toggle (Nikki/Rondo) per season in Contributie Instellingen
- Installment plan 3/8 enable/disable per season in Contributie Instellingen
- Public payment page respects installment plan toggle settings
- Progress indicator for bulk invoice creation with automatic 2-second polling

### Changed
- Nikki columns hidden in ContributieList when billing method is set to Rondo

## [27.4.0] - 2026-02-19

### Added
- Installment email scheduler: daily cron sweeper sends installment payment emails on the 25th of each scheduled month
- Overdue reminder system: automatic reminder emails at 14 and 21 days past due, second reminder BCCs treasurer
- Configurable email templates for installment payments, first reminder, and second reminder in Finance Settings
- Installment due dates now stored at plan selection time for sweeper-friendly scheduling
- InstallmentEmailSender class for installment-specific email composition with fresh Mollie payment links

## [27.3.0] - 2026-02-19

### Added
- Installment payment service (`InstallmentPaymentService`) for shared Mollie payment creation — extracted from `PublicPaymentPage` (DRY)
- Dual-path Mollie webhook: installment reverse-lookup (`_mollie_pid_{id}`) runs first; legacy `_mollie_payment_id` lookup preserved as fallback
- Automatic next-installment payment creation after each installment is confirmed by Mollie webhook
- All-paid check — invoice transitions to `rondo_paid` only when every installment is confirmed (idempotent)

## [27.2.0] - 2026-02-18

### Added
- Public payment landing page at /betaling/{token} for membership fee invoices
- Members can view invoice details and select payment plan (full, 3 or 8 installments) without logging in
- Mollie payment creation for installment payments with per-installment admin fee
- Mobile-friendly standalone HTML page with touch-friendly buttons (48px min-height)

## [27.1.2] - 2026-02-18

### Added
- Configurable administration fee (administratiekosten) for discipline case invoices

## [27.1.1] - 2026-02-18

### Changed
- Reset button in test mode renamed from 'Reset betaalstatus (test)' to 'Reset factuur (test)'
- Invoice detail header no longer shows duplicate member name (already shown in Lid card)
- Invoice email tuchtzaken list upgraded from `<ul>` to HTML `<table>` with Datum, Wedstrijd, Kaart, Bedrag columns
- Kaart column shows Geel/Rood based on charge codes, with ' en schorsing' for uitsluiting sanctions

## [27.1.0] - 2026-02-18

### Changed
- Invoice emails now sent as HTML instead of plain text
- QR code embedded inline in email body via CID (no longer a separate attachment)
- Payment link rendered as clickable HTML anchor in emails
- Discipline cases list rendered as HTML unordered list in emails
- Default email template updated to clean HTML with inline styles
- Email template editor upgraded from plain textarea to Tiptap rich text editor
- Email settings header renamed to "Template e-mail voor boetes"

### Added
- `{qr_code}` template variable for inline QR code image in emails
- Variable usage help text in email template settings

## [27.0.0] - 2026-02-18

### Added
- Mollie payment provider integration for discipline case invoices
- Mollie API key storage with sodium encryption (key never exposed via REST API)
- Mollie payment link creation via Payments API with automatic checkout URL storage
- Mollie webhook endpoint for automatic invoice status updates on payment confirmation
- Payment provider routing: invoices route to Mollie or Rabobank based on configured provider
- Finance Settings: Mollie tab with masked API key input and test/live environment badge
- Finance Settings: payment provider selector (Rabobank / Mollie) in Betaling tab

## [26.1.0] - 2026-02-16

### Added
- Club branding settings: logo upload and accent color in Finance Settings
- Invoice PDFs now use club's own logo and accent color instead of Rondo branding

## [26.0.0] - 2026-02-16

### Added
- Discipline case invoicing system with full invoice lifecycle management
- Finance settings page for club details, bank account, payment terms, and email template
- Invoice creation from discipline cases with automatic numbering (format: 2026T001)
- PDF invoice generation using mPDF with club branding and payment details
- Rabobank betaalverzoek payment link integration via OAuth 2.0
- Email delivery with configurable template and PDF attachment
- Facturen page with sortable invoice list and status filters
- Invoice detail view with send, mark paid, resend, and PDF download actions
- Invoice history on member profile page with links to detail view
- Finance section in sidebar navigation with Contributie, Facturen, and Instellingen

## [25.2.0] - 2026-02-15

### Removed
- Former members card from Commissie detail page
- Sponsors/investors functionality from Commissie detail page and edit modal

## [25.1.3] - 2026-02-14

### Fixed
- Primary buttons unreadable in dark mode — now use electric-cyan background with dark text for proper contrast

## [25.1.2] - 2026-02-14

### Changed
- Feedback page filters (type, status, project) changed from segmented button groups to compact dropdown selects

## [25.1.1] - 2026-02-14

### Changed
- Reorder dashboard stats widgets: Totaal leden, Vrijwilligers, Teams, VOG Status, Tuchtzaken

### Fixed
- Feedback page filters (status, type, project) now persist in the URL, surviving page refresh and back navigation

## [25.1.0] - 2026-02-14

### Added
- "In Review" status filter on Feedback page
- Project filter on Feedback page (Rondo Club, Rondo Sync, Website)
- "Binnenkort" tab on /vog page showing volunteers whose VOG expires within 30 days
- `vog_expiring_within_days` filter parameter on filtered people API endpoint
- Sportlink fields (KNVB ID, Type lid, Leeftijdsgroep, Lid sinds, Datum foto, Datum VOG, Is ouder, Huidig vrijwilliger, Financiële blokkade, FreeScout ID) available as column options on the /people page
- Show feedback ID in feedback list and admin feedback management table

### Changed
- Replace "In afwachting" stats card on dashboard with "Vrijwilligers" count card
- Dashboard birthday card now shows all of today's birthdays even when there are more than 5

### Removed
- Remove "Openstaand" (awaiting todos) dashboard card from customizable cards

## [25.0.1] - 2026-02-14

### Fixed
- Exclude former members (oud-leden) from "Komende herinneringen" on dashboard and in email digests

## [25.0.0] - 2026-02-14

### Added
- Autonomous feedback agent with PR workflow (`bin/get-feedback.sh`)
- Agent creates GitHub PRs instead of deploying directly
- `needs_info` feedback status for agent follow-up questions
- Feedback comments system (`rondo_feedback_comment` comment type)
- REST endpoints: `GET/POST /rondo/v1/feedback/{id}/comments`
- Agent-to-user conversation thread on FeedbackDetail page
- Reply form on `needs_info` feedback (auto-transitions to `approved` on reply)
- `pr_url` and `agent_branch` meta fields on feedback REST API
- PR link display on FeedbackDetail and FeedbackManagement pages
- "Waiting for your response" banner on `needs_info` feedback
- Idle-mode code optimization (`--optimize` flag)
- Optimization file tracker (`logs/optimization-tracker.json`)
- Agent prompt at `.claude/agent-prompt.md`
- Optimization prompt at `.claude/optimize-prompt.md`
- launchd plist template for Mac Mini scheduling
- Developer docs for feedback agent system

### Changed
- `get-feedback.sh` rewritten with PR workflow, branch management, and crash recovery
- Feedback status set to `in_progress` before Claude runs (prevents re-pickup)
- Crash cleanup resets feedback status and returns to main branch
- Branch cleanup after each run (merged `feedback/*` and `optimize/*` branches)

## [24.1.0] - 2026-02-13

### Removed
- Removed `person_label` and `team_label` taxonomy registrations and all database data
- Removed `commissie_label` taxonomy registration and database data
- Removed Settings/Labels management page from frontend
- Removed BulkLabelsModal component and label bulk actions from all list views
- Removed label columns, badges, and filters from PeopleList, TeamsList, CommissiesList
- Removed label add/remove controls from PersonDetail
- Removed all label-related API client methods (12 methods)
- Removed `date_type` field from reminders and iCal birthday data
- Removed `CATEGORIES` line from iCal birthday events
- Removed deprecated `RONDO_Dates_CLI_Command` WP-CLI class
- Removed deprecated `migrate_birthdates` and `update_date_references` WP-CLI commands
- Removed residual `important_date` references from route map, REST API, and CLI
- Removed teams and commissies bulk-update endpoints (were labels-only)

### Changed
- Updated email notification wording from "important dates" to "birthdays"
- Updated CLI reminder messages to reflect birthday-only system
- Simplified people bulk-update endpoint (organization_id only)
- Updated AGENTS.md and developer docs to reflect simplified data model

## [24.0.0] - 2026-02-12

### Added
- Demo data pipeline: `wp rondo demo export` creates anonymized fixture from production data
- Demo data pipeline: `wp rondo demo import [--clean]` loads fixture into any WordPress instance
- Dutch fake data generator (names with infixes, addresses, phone numbers, emails)
- Date-shifting on import so demo data always looks current relative to today
- Data anonymization: fake names, emails, phones, addresses replace real PII
- Weighted fake financial amounts for realistic fee patterns
- Season-aware date shifting for fee configs and discipline case seasons
- Demo fixture file (`fixtures/demo-fixture.json`) committed for portable demo environments
- Demo site banner ("DEMO OMGEVING") distinguishes demo from production
- `bin/deploy-demo.sh` script for deploying to demo.rondo.club

### Removed
- Photos and avatars stripped from demo fixture (photos are identity, not anonymizable)

## [23.2.1] - 2026-02-10

### Changed
- Move VOG settings from Settings > Admin > VOG into the VOG page itself as an admin-only Instellingen tab
- VOG page now uses tabbed layout matching the Contributie page pattern (Overzicht + Instellingen)
- Non-admin users only see the Overzicht tab on the VOG page

### Removed
- VOG subtab from Settings Admin section (all VOG state, effects, handlers, and component removed)

## [23.2.0] - 2026-02-09

### Added
- Former member fee calculation logic: eligible former members appear in contributie list
- `is_former_member_in_season()` method checks if former member qualifies for season (lid-sinds before season end)
- Former members use normal pro-rata based on lid-sinds (leaving doesn't affect fee)
- Former member exclusion from fee forecast (won't be members next season)
- Family discount calculation excludes ineligible former members from family groups
- `is_former_member` field in fee API responses (`/rondo/v1/fees`, `/rondo/v1/fees/person/{id}`)
- Fee cache invalidation on `former_member` field changes
- Former member season eligibility diagnostics in `get_calculation_status()`
- Google Sheets export applies former member fee rules
- Contributie Logic section in developer documentation (`features/former-members.md`)

## [23.1.0] - 2026-02-09

### Added
- "Toon oud-leden" toggle in People list filter dropdown to show former members
- "Oud-lid" badge visual indicator for former members in People list rows
- "Oud-lid" badge in global search results for former members
- `include_former` parameter on `/rondo/v1/people/filtered` endpoint (1=include, empty=exclude)
- `former_member` boolean field in filtered people response
- `former_member` field in `format_person_summary()` (affects search, dashboard, all person summaries)
- Reduced opacity (60%) styling for former member rows in People list
- Former member filter state persisted in URL (`?oudLeden=1`)
- Former member toggle counted in active filters badge
- Export to Google Sheets includes former members when toggle is active
- Developer documentation for former member system (`features/former-members.md`)

## [22.0.0] - 2026-02-09

### Added
- Tailwind CSS v4 with CSS-first @theme configuration and OKLCH color space brand tokens
- Brand color palette: electric-cyan, bright-cobalt, deep-midnight, obsidian
- Montserrat font for headings via @fontsource/montserrat (weights 600, 700)
- Cyan-to-cobalt gradient utilities (bg-brand-gradient, text-brand-gradient)
- Gradient text treatment on page headings and section titles
- Primary gradient buttons (cyan → cobalt) with hover lift effect
- Glass button variant with transparent background
- Card components with 3px gradient top border
- Input/textarea focus states with electric-cyan border and cyan glow ring
- Rondo logo integrated as favicon, login page logo, and sidebar brand mark
- PWA icon generation script using Rondo logo source

### Changed
- Migrated from Tailwind CSS v3.4 to v4 (clean break, no backward compatibility)
- Dark mode adapted to use brand colors (preserved, not removed)
- PWA manifest theme-color updated to electric-cyan (#0891B2)
- Login page restyled with brand gradient and Rondo logo
- Hover transitions standardized to 200ms ease-in-out with translateY(-2px) lift
- useTheme hook simplified (dark mode toggle only, no dynamic color injection)

### Removed
- Dynamic accent color system (CSS variable injection, accent-* scale, data-accent attributes)
- react-colorful color picker dependency
- Color picker UI from Settings page
- ClubConfig accent_color WordPress option and REST API field
- Dead REST API theme endpoints (/rondo/v1/config accent_color field)
- Contact import feature (vCard and Google CSV file upload) - replaced by live Google Contacts API sync
- User approval system
- how_we_met and met_date person fields

## [21.1.0] - 2026-02-09

### Added
- Configurable family discount: admin can set second child and third child discount percentages per season
- Family discount configuration stored per season in WordPress options with fallback to defaults (25%/50%)
- FamilyDiscountSection component in fee category settings UI
- REST API validation for family discount percentages (0-100 range, warning if 2nd >= 3rd)
- Configurable matching rules: each fee category can specify matching teams (by ID) and matching werkfuncties
- Admin UI for selecting teams and werkfuncties per fee category in Settings
- GET /rondo/v1/werkfuncties/available endpoint for listing distinct werkfunctie values
- Auto-migration: existing 'recreant' categories pre-populated with recreational team IDs, 'donateur' with Donateur werkfunctie

### Changed
- `get_family_discount_rate()` reads from per-season config instead of returning hardcoded values
- GET/POST `/rondo/v1/membership-fees/settings` now includes `family_discount` field
- `calculate_fee()` now uses config-driven team and werkfunctie matching instead of hardcoded `is_recreational_team()` and `is_donateur()`
- `is_recreational_team()` and `is_donateur()` deprecated (kept for migration only)

## [21.0.0] - 2026-02-09

### Added
- Per-season fee category management UI in Settings > Beheer > Contributie
- Season selector toggle for managing current and next season categories
- Drag-and-drop reordering of fee categories with sort order persistence
- Inline editing of category fields: label, amount, age classes, youth flag
- Age class coverage summary showing which Sportlink age classes map to which categories
- API validation feedback: errors (blocking) and warnings (informational) displayed in UI
- Fee category data model with per-season storage in WordPress options
- Config-driven fee calculation replacing hardcoded category constants
- REST API endpoints for category CRUD with structured validation

### Changed
- Fee calculation now reads from per-season category configuration instead of hardcoded values
- Fee settings UI is fully dynamic - no hardcoded fee type names
- Category sort order derived from config, removing duplicated arrays across codebase
- Fee list REST API response includes category metadata for dynamic frontend rendering

### Removed
- Hardcoded fee type definitions (mini, pupil, junior, senior, recreant, donateur) from Settings UI
- Hardcoded category_order arrays from REST API, Google Sheets export, and ContributieList
- Old flat-amount fee settings UI replaced with full category management

## [20.0.0] - 2026-02-08

### Added
- Dynamic filter options on People list derived from database values instead of hardcoded arrays
- REST API endpoint `/rondo/v1/people/filter-options` returning available filters with counts
- Filter dropdowns show count of matching people per option (e.g., "Junior (42)")
- Loading and error states for filter dropdowns with retry functionality
- Generic filter infrastructure for easily adding future dynamic filters

### Changed
- People list "Type lid" filter now shows only values that exist in the database
- People list "Leeftijdsgroep" filter now shows only values that exist in the database
- Team detail page player/staff split now reads from configured role settings instead of hardcoded array

### Fixed
- Volunteer role settings API endpoint now accessible to all authenticated users (was admin-only for GET)

### Removed
- Family tree visualization feature (route `/people/:id/family-tree`, vis-network/vis-data dependencies)
- Hardcoded filter option arrays for Type lid and Leeftijdsgroep in PeopleList

## [19.1.0] - 2026-02-07

### Added
- Configurable role classification for volunteer status in Settings > Beheer > Rollen
- REST API endpoints for volunteer role management (`/rondo/v1/volunteer-roles/available`, `/rondo/v1/volunteer-roles/settings`)
- WordPress options `rondo_player_roles` and `rondo_excluded_roles` replace hardcoded constants
- Admin UI to classify Sportlink job titles as Speler, Uitgesloten, or Vrijwilliger
- Saving role classifications triggers automatic volunteer status recalculation for all people

## [19.0.1] - 2026-02-06

### Fixed
- Documentation references to "important dates" updated to reflect birthdate-on-person model
- Removed stale `/dates` route documentation from frontend architecture
- CardDAV sync docs now correctly reference "Birthday (from person record)"
- API docs now correctly describe birth_year as "derived from birthdate field"
- iCal feed docs now describe birthday subscription feature
- Daily digest docs now reference "Upcoming birthdays" instead of "important dates"

## [19.0.0] - 2026-02-06

### Removed
- Important Dates custom post type and all associated functionality
- Important Dates page in frontend navigation
- Date type taxonomy
- Important Dates widget on PersonDetail page
- ImportantDateModal component
- useDates hook and all date-related API endpoints
- Death date display (is_deceased flag now returns false)
- Backend PHP classes: STADION_Important_Dates, STADION_Date_Types
- 1069 important_date records deleted from production database

### Changed
- Birthdays now stored directly on person records via birthdate ACF field
- iCal feeds generate birthday events from person.birthdate instead of important_date posts
- Daily digest reminders now source birthdays from person records
- FamilyTree and PersonDetail now use person.is_deceased flag from allPeople data
- Import (vCard/Google Contacts) saves birthday to person.birthdate field instead of creating important_date

## [18.1.1] - 2026-02-06

### Fixed
- Prognosis (forecast) mode now uses next season's fee rates instead of current season's rates

## [18.1.0] - 2026-02-05

### Added
- Per-season membership fee settings (current and next season)
- Automatic migration of existing global fees to current season
- Documentation for membership fees system (docs/membership-fees.md)

### Changed
- Settings UI shows two fee sections: current season and next season
- API returns both seasons, accepts season parameter for updates
- Each season saves independently with its own button

## [18.0.0] - 2026-02-05

### Removed
- Slack integration (OAuth, REST endpoints, settings UI, and notifications)
- Slack contact type support in contact editing and vCard import/export
- Monica CRM import (backend importer and settings UI)

## [17.0.0] - 2026-02-05

### Added
- ClubConfig backend service for club-wide settings (club name, accent color, FreeScout URL)
- REST API endpoint `/stadion/v1/config` with admin write + all-users read permissions
- Admin-only club configuration section in Settings with react-colorful color picker
- Live preview for club color changes in Settings
- Dynamic WordPress login page styling from club configuration
- PWA theme-color meta tags read from club config

### Changed
- Renamed "awc" accent color to "club" throughout codebase (Tailwind, CSS, React, PHP)
- FreeScout integration URL now reads from club config (hidden when not configured)
- All AWC-specific comments and references removed from source code
- Documentation uses generic placeholders instead of club-specific domains
- Theme now installable by any sports club without code changes

### Removed
- Legacy awc→club migration code (users migrated in v16.0)
- Hardcoded FreeScout URL in PersonDetail component

## [16.0.0] - 2026-02-05

### Added
- Infix (tussenvoegsel) field for person records — supports Dutch naming convention (e.g., "Jan van de Berg")
- ACF `infix` text field between first_name and last_name (read-only, synced from Sportlink)
- Auto-title generation includes infix: "First Infix Last" with no double spaces when empty
- Infix included in REST API filtered endpoint response and JOIN query
- Global search matches infix field (score: 50)
- vCard export/import: infix maps to N field position 3 (Additional Names)
- Google Contacts export: infix maps to middleName
- Google Contacts API import: reads middleName as infix at all 3 import locations
- Google CSV export: populates "Additional Name" column
- Google CSV import: reads "Additional Name" / "Middle Name" column
- CardDAV: infix in create/update flows
- Frontend PersonEditModal: read-only infix field with "Komt van Sportlink" tooltip
- Frontend PeopleList, VOGList: display infix in name column
- `formatPersonName()` utility for consistent name formatting across frontend

## [14.0.0] - 2026-02-04

### Changed
- Optimized QueryClient defaults to prevent duplicate API calls on page load
- Migrated from BrowserRouter to createBrowserRouter for better route handling
- Modal people selectors now load data only when modal opens (lazy loading)
- Created centralized useCurrentUser hook for query deduplication
- VOG count now cached with 5-minute staleTime
- Backend todo count queries now use SQL COUNT instead of fetching all records

## [8.4.0] - 2026-02-01

### Added
- Google Sheets export for Contributie page (export fee list with all 10 columns, Euro formatting, and totals row)

## [8.3.4] - 2026-01-31

### Added
- Justis status filter on VOG page (filter by whether VOG is submitted to Justis)

## [8.3.3] - 2026-01-31

### Added
- Leeftijdsgroep filter dropdown on /people page
- Custom sorting for leeftijdsgroep (Onder 6 < Onder 10 < Senioren)

## [8.3.2] - 2026-01-30

### Added
- VOG exempt commissies setting - exclude commissies without child contact from VOG requirements

## [8.3.1] - 2026-01-29

### Added
- Visual indicators (small vertical lines) on column resize handles for better discoverability

### Changed
- Removed redundant sort dropdown from People list header (table headers now handle sorting)

### Fixed
- Column resize no longer crashes with "Maximum update depth exceeded" error
- Column resize handles pointer capture release errors gracefully

## [8.3.0] - 2026-01-28

### Added
- Smart Android install prompt after user engagement (2 page views or 1 note)
- iOS install instructions modal with visual Add to Home Screen guide
- Periodic service worker update checking (hourly)
- Engagement tracking for install prompt timing

### Changed
- ReloadPrompt text localized to Dutch
- Install prompts respect dismissal preferences with 7-day cooldown

## [8.2.0] - 2026-01-28

### Added
- Pull-to-refresh gesture on all list views (People, Teams, Commissies, Dates, Todos, Feedback)
- Pull-to-refresh on detail views (Person, Team, Commissie) and Dashboard
- Native-like refresh indicator matching Stadion's accent color

### Fixed
- iOS standalone mode no longer triggers page reload from overscroll bounce

## [7.1.0] - 2026-01-26

### Removed
- **Favorites feature:** Removed the ability to mark people as favorites
  - Removed is_favorite ACF field from person records
  - Removed favorites filter from People list
  - Removed favorites dashboard widget
  - Removed favorites star indicator from person cards and detail views
  - Removed favorites from dashboard customization options
  - Removed is_starred import from Monica importer
- **Workspaces feature:** Completely removed multi-user collaboration functionality
  - Removed workspace CPT and workspace_invite CPT
  - Removed workspace_access taxonomy
  - Removed visibility settings (private/workspace/shared) from all entities
  - Removed workspace navigation, routes, pages, and components
  - Removed workspace member management and invites
  - Removed VisibilitySelector from edit modals
  - Removed workspace-related ACF field groups
  - Simplified access control to author-only model
  - Removed bulk visibility/workspace updates from REST APIs

## [7.0.0] - 2026-01-25

### Changed
- **BREAKING:** Forked from Caelis, renamed project to Stadion
- **BREAKING:** Renamed all `PRM_` prefixes to `STADION_`
- **BREAKING:** Renamed REST API namespace from `prm/v1` to `stadion/v1`
- **BREAKING:** Renamed Organizations to Teams (post type `company` → `team`)
- **BREAKING:** Renamed taxonomy from `company_label` to `team_label`
- **BREAKING:** REST endpoints changed from `/companies` to `/teams`
- Renamed frontend config from `prmConfig` to `stadionConfig`
- Updated user role from `caelis_user` to `stadion_user`
- Teams are now designed to be synced from Sportlink (read-only)
- Work History renamed to Team History in person profiles

### Removed
- Investors field from teams (not needed for sports teams)

## [6.0.0] - 2026-01-20

### Added
- Custom Fields system for admin-defined fields on People and Teams
- Settings UI for creating, editing, and deleting custom fields
- Support for 14 field types: Text, Textarea, Number, Email, URL, Date, Select, Checkbox, True/False, Image, File, Link, Color, Relationship
- Drag-and-drop field reordering with optimistic updates
- Required and unique field validation options
- Custom field display in detail views with type-appropriate rendering
- Custom field columns in list views (configurable per field)
- Custom field search integration in People and Teams
- ACF-native storage using field groups and subfield patterns

## [5.0.0] - 2026-01-18

### Added
- Google Contacts bidirectional sync with Stadion as source of truth
- OAuth connection for Google Contacts in Settings > Connections
- Automatic import from Google Contacts with duplicate detection
- Export Stadion contacts to Google Contacts
- Delta sync using Google syncToken for efficient updates
- Configurable sync frequency (15min, hourly, 6hr, daily)
- Conflict detection with Stadion-wins resolution strategy
- Sync history log in Settings showing recent sync operations
- "View in Google Contacts" link on synced person profiles
- WP-CLI commands for Google Contacts management:
  - `wp stadion google-contacts sync --user-id=ID` - trigger sync
  - `wp stadion google-contacts sync --user-id=ID --full` - full resync
  - `wp stadion google-contacts status --user-id=ID` - check status
  - `wp stadion google-contacts conflicts --user-id=ID` - list conflicts
  - `wp stadion google-contacts unlink-all --user-id=ID` - reset sync

## [4.10.0] - 2026-01-17

### Added
- Manual sync trigger button in Google Contacts settings
- Sync frequency dropdown (15min, hourly, 6 hours, daily)
- Background sync status display showing last sync time
- REST endpoints for sync trigger (/google-contacts/sync) and frequency update (/google-contacts/sync-frequency)
- sync_frequency field in GoogleContactsConnection class
- sync_user_manual() method in GoogleContactsSync for on-demand sync

## [4.9.0] - 2026-01-17

### Added
- Fixed height dashboard widgets (280px) with internal scrolling
- 6 skeleton widgets shown during dashboard loading for layout stability
- Multi-calendar selection for Google Calendar connections via checkbox UI
- Sync events from multiple selected calendars in a single connection
- Connection card shows "N calendars selected" for multi-calendar connections
- Backend `get_calendar_ids()` helper normalizes old single-calendar to new array format
- Two-column EditConnectionModal layout (calendar list left, sync settings right)

### Changed
- Calendar selector changed from dropdown to checkbox list
- Save button disabled when no calendars selected for Google connections
- EditConnectionModal width increased (max-w-2xl) to accommodate two columns

### Fixed
- Events widget no longer jumps during date navigation (placeholderData pattern)

## [4.8.0] - 2026-01-17

### Added
- Meeting detail modal with full meeting information (title, time, location, description)
- Meeting attendee list with avatars showing known vs unknown attendees
- Meeting notes section with auto-save for meeting prep
- Add person from meeting attendee with name extraction from email
- Date navigation on meetings widget with prev/next/today buttons
- Add email to existing person flow with choice popup to avoid duplicates

### Fixed
- HTML entity encoding (&amp;) in calendar event titles

## [4.7.0] - 2026-01-17

### Added
- Dinner activity type for tracking dinner meetings
- Zoom activity type for tracking video calls

### Changed
- Phone call activity type renamed to Phone for brevity

### Fixed
- Topbar z-index now stays above selection toolbar on People screen
- Person header spacing between "at" and team name

## [4.5.0] - 2026-01-16

### Added
- Per-connection sync_to_days setting (1 week to 90 days forward)
- Per-connection sync_frequency setting (15 min, 30 min, hourly, 4 hours, daily)
- Background sync respects per-connection frequency settings with `is_sync_due()` check
- Calendar list API endpoint `GET /stadion/v1/calendar/connections/{id}/calendars`
- Calendar selector dropdown in EditConnectionModal
- Connection card displays selected calendar name as subtitle
- `list_calendars()` method in GoogleProvider for fetching available calendars
- `next_sync` timestamp in sync status endpoint

### Fixed
- Duplicate calendar events from race conditions via transient-based sync lock
- Contact matching in CLI/cron contexts by setting user context before queries
- Google vendor class namespace resolution (added absolute namespace prefixes)

## [4.4.0] - 2026-01-16

### Added
- Comprehensive codebase audit (AUDIT.md) with namespace hierarchy design
- PSR-4 namespaces for 38 PHP classes across 9 namespace groups
- Composer autoloading with classmap for includes/ directory
- 38 backward-compatible class aliases (STADION_* → Stadion\*)
- PHPCS Generic.Files.OneClassPerFile rule enabled
- Daily debug.log rotation via WP-Cron with 7-day retention

### Changed
- Split notification channel classes into separate files (one-class-per-file compliance)
- Removed manual stadion_autoloader() function (52 lines)
- All classes now use proper PHP namespaces (Stadion\Core, Stadion\REST, etc.)

## [4.3.0] - 2026-01-16

### Added
- WordPress Coding Standards (WPCS 3.3) installed via Composer
- PHPCS configuration file (phpcs.xml.dist) with WordPress-Extra standard
- Composer lint scripts (`composer lint`, `composer lint:fix`)
- Complete wp-config.php configuration documentation in README.md

### Changed
- Converted entire codebase to short array syntax ([] instead of array())
- Disabled Yoda conditions for improved code readability
- PHPCS violations reduced from 49,450 to 46 (99.9% reduction)
- Text domain standardized to 'stadion' throughout codebase
- All date() calls converted to gmdate() for timezone safety

## [4.2.0] - 2026-01-15

### Added
- DomErrorBoundary component for graceful recovery from browser extension DOM conflicts
- DOM modification prevention via `translate="no"` attribute and Google notranslate meta tag
- Connections tab in Settings with subtabs for Calendars, CardDAV, and Slack
- Automatic calendar event re-matching when person email addresses change
- WP-CLI command `wp prm calendar rematch --user-id=ID` for manual re-matching

### Changed
- Settings page reorganized: external service connections consolidated under Connections tab
- Notifications tab simplified to show only channel toggles and preferences
- OAuth redirect URLs updated to use new Connections tab structure

## [4.1.0] - 2026-01-15

### Added
- Dynamic favicon that updates when accent color changes
- Dashboard restructured to 3-row layout (Stats | Activity | Favorites)
- Timezone-aware meeting times using ISO 8601 format

### Fixed
- Dark mode contrast for CardDAV connection details and search modal
- Deploy procedure now uses two-step rsync to prevent MIME type errors from stale artifacts

## [4.0.0] - 2026-01-15

### Added
- Google Calendar OAuth2 integration with automatic token refresh
- CalDAV provider supporting iCloud, Fastmail, Nextcloud, and generic servers
- Calendar event custom post type for caching synced events
- Email-first contact matching algorithm with confidence scores
- Calendar settings UI with connection management
- Person profile Meetings tab with upcoming/past meetings
- Log as Activity functionality for past meetings
- Background sync via WP-Cron every 15 minutes
- Today's Meetings dashboard widget
- WP-CLI commands: `wp prm calendar sync/status/auto-log`

## [3.8.0] - 2026-01-15

### Added
- Color scheme toggle (Light/Dark/System) in Settings Appearance
- Accent color picker with 4 color options (blue, violet, rose, amber)
- useTheme hook with localStorage caching
- Dark mode support across all components

### Fixed
- Dark mode contrast issues in menus, icons, and overdue items

## [3.7.0] - 2026-01-15

### Added
- TodoModal opens from Dashboard when clicking todo cards (no navigation away)
- View-first mode for existing todos showing formatted date, rendered notes, and person chips
- Edit button in view mode to switch to edit mode

### Changed
- Default due date for new todos changed from today to tomorrow
- Cancel button in edit mode returns to view mode (for existing todos) instead of closing modal

## [3.6.0] - 2026-01-14

### Changed
- Reduced initial bundle from 460 KB to 50 KB via modal lazy loading
- GlobalTodoModal, PersonEditModal, TeamEditModal, and ImportantDateModal now load on demand
- TipTap editor (~370 KB) only loads when opening modals that need it
- Initial load improved from ~767 KB to ~400 KB total

## [3.4.0] - 2026-01-14

### Added
- Labels CRUD interface at `/settings/labels` with tabbed UI for person and team labels
- 8 new API methods for label management (getPersonLabels, createPersonLabel, etc.)
- Awaiting todos count in dashboard stats (5-column grid layout)
- Build-time based refresh detection using manifest.json mtime

### Changed
- Teams list website URLs are now clickable blue links opening in new tab
- Slack contact details simplified to show only label as clickable link
- Timeline panel on person profile now uses full 2-column width on desktop
- Dashboard stats grid expanded from 4 to 5 columns on desktop

### Removed
- Labels column from Teams list (column, sorting, bulk action removed)

## [3.3.0] - 2026-01-14

### Added
- WYSIWYG notes field for todo descriptions (ACF field)
- Multi-person todo linking with related_persons multi-value field
- TodoModal collapsible notes editor with RichTextEditor
- Multi-person chip selector in TodoModal (edit mode only)
- GlobalTodoModal multi-person selection and notes support
- Stacked avatar display in TodosList (max 3 + overflow)
- Stacked avatar display in PersonDetail todos sidebar (max 2 + overflow)
- Cross-person todo visibility with "Also:" indicator
- `wp prm todos migrate-persons` WP-CLI command for data migration
- Thumbnail field in timeline API persons array for avatar display

### Changed
- TodoModal now shows multi-person selector when editing (new todos context-bound to person page)
- PersonDetail sidebar uses smaller avatars (w-5 h-5) for compact display
- Current person filtered from "Also:" display (only shows OTHER linked people)

## [3.2.0] - 2026-01-14

See previous changelog entry for v1.79.0 (Person Profile Polish milestone).

## [1.79.0] - 2026-01-14

### Added
- Current position (job title + team) display in PersonDetail header
- Persistent todos sidebar visible across all PersonDetail tabs
- Mobile todos floating action button (FAB) for screens below lg breakpoint
- Mobile todos slide-up panel with full CRUD functionality
- CSS keyframe animation for mobile panel slide-up effect

### Changed
- PersonDetail layout changed from flex to 3-column CSS grid for equal-width columns
- Timeline endpoint now queries all todo post statuses (stadion_open, stadion_awaiting, stadion_completed)
- Timeline endpoint returns proper `status` field instead of deprecated `is_completed`

### Fixed
- Timeline endpoint wasn't returning todos (was querying 'publish' status instead of custom statuses)

## [1.78.0] - 2026-01-14

### Added
- `TodoCptTest.php` PHPUnit test class with 16 tests covering:
  - CPT registration (post type exists, REST support)
  - Access control (user isolation, admin visibility)
  - REST API CRUD operations (GET, POST, PUT, DELETE)
  - Dashboard integration (open_todos_count)
  - Completion filter functionality

### Changed
- `SearchDashboardTest.php` updated to use CPT-based todos instead of comment-based
  - `createTodo()` helper now creates `stadion_todo` posts
  - Added `STADION_REST_Todos` route registration

## [1.77.0] - 2026-01-14

### Added
- WP-CLI command `wp prm todos migrate` to migrate comment-based todos to CPT
  - Supports `--dry-run` flag to preview changes without modifying data
  - Preserves all metadata: related_person, is_completed, due_date
  - Sets visibility to private (default for migrated todos)
  - Deletes original comments after successful migration

### Changed
- Dashboard `count_open_todos()` now queries `stadion_todo` CPT instead of comments
  - Uses `WP_Query` with access control filtering via `STADION_Access_Control` hooks

### Removed
- Legacy comment-based todo code from `STADION_Comment_Types`:
  - `TYPE_TODO` constant
  - Todo REST routes (`/people/{id}/todos`, `/todos/{id}`)
  - Todo methods: `get_todos()`, `create_todo()`, `update_todo()`, `delete_todo()`
  - Todo meta registration (`is_completed`, `due_date`)
- Legacy `get_all_todos()` method from `STADION_REST_API` (now handled by `STADION_REST_Todos`)
- Legacy `/stadion/v1/todos` route from `STADION_REST_API` (now handled by `STADION_REST_Todos`)

## [1.76.0] - 2026-01-14

### Added
- `STADION_REST_Todos` class for full CRUD operations on the todo CPT via REST API
  - Person-scoped endpoints: GET/POST `/stadion/v1/people/{person_id}/todos`
  - Global endpoints: GET `/stadion/v1/todos` with optional `completed` filter parameter
  - Single todo endpoints: GET/PUT/DELETE `/stadion/v1/todos/{id}`
  - Response format matches existing comment-based todo system for seamless frontend migration
  - Proper permission callbacks using `check_person_access()` and `check_user_approved()`
  - Access control integration via existing `STADION_Access_Control` filters

## [1.75.0] - 2026-01-14

### Added
- `stadion_todo` custom post type for tracking todos/tasks related to people
  - Post type slug: `stadion_todo`, REST base: `todos`
  - Internal only (public: false) but visible in admin and REST API
  - Supports title (todo text), editor (optional notes), and author
  - Menu position after Important Dates with dashicons-yes-alt icon
- ACF field group for todo metadata (`group_todo_fields.json`)
  - `related_person`: post_object field linking to a person (required)
  - `is_completed`: true_false toggle for completion status
  - `due_date`: date_picker for optional due date (Y-m-d format)
  - `_visibility`: select field for private/workspace visibility
  - `_assigned_workspaces`: taxonomy field for workspace assignment (conditional on visibility)
- Access control integration for `stadion_todo` post type
  - Added to `$controlled_post_types` array for automatic query filtering
  - REST API query filtering via `rest_stadion_todo_query` hook
  - REST API single item access via `rest_prepare_stadion_todo` hook
  - Workspace ID conversion via `rest_after_insert_stadion_todo` hook

## [1.74.0] - 2026-01-13

### Changed
- Lazy load heavy third-party libraries for further bundle optimization
  - vis-network (~526 KB) loads only when viewing family tree pages
  - TipTap editor (~371 KB) loads only when opening note/activity modals
  - FamilyTree page chunk reduced from 534 KB to 9 KB
  - QuickActivityModal chunk reduced from 383 KB to 13 KB
  - Initial load now 435 KB raw (no change in core bundles)
  - Extracted richTextUtils.js for proper code splitting

## [1.73.0] - 2026-01-13

### Changed
- Implemented route-based lazy loading with React.lazy and Suspense
  - All 16 page components now load on demand when routes are visited
  - Initial bundle reduced from 1,336 KB to 87 KB (main chunk only)
  - Total initial load (vendor + utils + main + CSS): ~435 KB raw / ~128 KB gzipped
  - Heavy libraries (vis-network, TipTap editor) only load when needed
  - Added PageLoader spinner for smooth loading states

## [1.72.0] - 2026-01-13

### Changed
- Implemented vendor chunking for improved bundle caching
  - Split vendor chunk (React, React DOM, React Router, TanStack Query): 210 KB
  - Split utils chunk (date-fns, clsx, zustand, axios, react-hook-form): 96 KB
  - Main application chunk remains at 1,336 KB (pending lazy loading in future plans)
  - Stable dependencies now cached separately, reducing cache invalidation on app updates

## [1.71.1] - 2026-01-13

### Fixed
- Team edit now properly saves visibility and workspace changes
  - Form was passing visibility values but handleSaveTeam was ignoring them
  - Now uses form values instead of just preserving existing values

## [1.71.0] - 2026-01-13

### Added
- WP-CLI command `wp prm dates regenerate-titles` to update existing Important Date titles
  - Supports `--dry-run` flag to preview changes
  - Skips dates with custom labels
  - Regenerates titles using full names for consistency

### Changed
- Important Date titles now use full names instead of first names only
  - Frontend auto-generation updated to use "Jan Ippen's Birthday" format
  - Backend already used full names (no changes needed)
  - Improves clarity when multiple people share the same first name

## [1.70.1] - 2026-01-13

### Fixed
- Important Date modal now defaults to today's date when creating new dates
- Editing important dates no longer fails with 400 error about required _visibility field
- Cache invalidation after editing important dates now uses correct query key

## [1.70.0] - 2026-01-13

### Added
- Bulk actions for Teams list view
  - Bulk visibility change (private/workspace)
  - Bulk workspace assignment
  - Bulk label management (add/remove mode toggle)
- Actions dropdown in Teams selection toolbar
- Bulk update REST endpoint for teams (`POST /stadion/v1/teams/bulk-update`)
- `useBulkUpdateTeams` hook for React components

### Changed
- Teams now have full bulk action parity with People list view

## [1.69.0] - 2026-01-13

### Added
- Teams list view with tabular layout (replacing card grid)
  - Columns: checkbox, logo, name, industry, website, workspace, labels
  - SortableHeader component for clickable column sorting
  - Selection checkboxes with select all/none functionality
  - Sticky table header and selection toolbar
  - Alternating row colors for better readability
- Header sort controls for Teams
  - Sort field dropdown (Name, Industry, Website, Workspace, Labels)
  - Sort direction toggle button

### Changed
- Teams page now uses list view instead of card grid
- Teams data includes team labels for display

### Removed
- TeamCard component (replaced by TeamListRow)
- Grid-based card layout for teams

## [1.68.0] - 2026-01-13

### Changed
- People list view now has dedicated image column (before First Name)
  - Images/initials shown in narrow column without header label
  - First Name header now aligns directly with first name values

### Removed
- Card view toggle from People list (list view is now the only view)
- PersonCard component (no longer needed)
- View mode localStorage persistence (no longer needed)

## [1.67.0] - 2026-01-13

### Added
- Bulk team assignment modal in list view Actions dropdown
  - Search/filter teams by name
  - Select team to assign to all selected people
  - "Clear team" option to remove current team
- Bulk labels management modal in list view Actions dropdown
  - Add/Remove mode toggle for label operations
  - Multi-select labels for batch operations
  - Add labels appends without replacing existing labels
  - Remove labels removes selected labels from people

## [1.66.1] - 2026-01-13

### Added
- Extended bulk-update endpoint with team and label support
  - `team_id`: Set current team for selected people (or clear with null)
  - `labels_add`: Add person labels to selected people in bulk
  - `labels_remove`: Remove person labels from selected people in bulk

## [1.66.0] - 2026-01-13

### Added
- Clickable column headers in list view with sort direction indicators
  - Click any column header to sort by that field
  - Shows arrow indicator (up/down) for active sort column
  - Click same header again to toggle sort direction
- Sticky table header that remains visible when scrolling
- Sticky selection toolbar when contacts are selected
  - Selection toolbar stays above the table header for easy access

## [1.65.0] - 2026-01-13

### Added
- Split Name column into separate First Name and Last Name columns in list view
- Labels column in list view displaying person labels as styled pills
- Zebra striping on list view rows for improved readability
- Extended sorting options: Team, Workspace, and Labels
  - Team sorting uses team name (empty sorts last)
  - Workspace sorting uses workspace names (empty sorts last)
  - Labels sorting uses first label name (empty sorts last)

## [1.64.1] - 2026-01-13

### Fixed
- Workspace column in list view now updates immediately after bulk workspace assignment (no refresh required)

## [1.64.0] - 2026-01-13

### Added
- Bulk actions UI for managing multiple contacts at once
  - Actions dropdown in selection toolbar with "Change visibility" and "Assign to workspace" options
  - Bulk visibility modal to change privacy settings for selected contacts
  - Bulk workspace modal to assign selected contacts to workspaces
  - Loading states and success/error handling for bulk operations

## [1.63.0] - 2026-01-13

### Added
- Bulk update REST endpoint `/stadion/v1/people/bulk-update` for batch operations
  - Accepts array of person IDs and updates object with visibility and/or workspace assignments
  - Validates ownership of each post before updating
  - Returns success/failure details for each person
- `useBulkUpdatePeople` React hook for bulk operations from the frontend
- `bulkUpdatePeople` API client method for REST endpoint access

## [1.62.4] - 2026-01-13

### Fixed
- Workspace assignments now correctly save when editing a person (using REST API action instead of ACF filter)

## [1.62.3] - 2026-01-13

### Fixed
- Workspace assignments now correctly save when editing a person (added ACF filter to convert workspace post IDs to taxonomy term IDs)

## [1.62.2] - 2026-01-13

### Fixed
- Person edit modal now correctly saves visibility and workspace assignments

## [1.62.1] - 2026-01-13

### Fixed
- List view workspace column now correctly displays workspace names (fixed type coercion for workspace IDs)
- View mode preference (card/list) now persists across page reloads via localStorage

## [1.62.0] - 2026-01-13

### Added
- List view for People screen with tabular layout
  - Toggle between card view and list view using LayoutGrid/List icons
  - Table displays Name (with avatar, deceased marker, favorite star), Team, and Workspace columns
  - Rows link to person detail page
- Multi-select infrastructure for bulk operations
  - Checkbox selection for individual rows and select all/none
  - Header checkbox shows checked/partial/unchecked state based on selection
  - Selection toolbar shows count and clear button
  - Selection automatically clears when filters change

## [1.61.1] - 2026-01-13

### Added
- Multi-user system documentation (`docs/multi-user.md`)
  - Comprehensive guide covering workspaces, visibility, sharing, and collaborative features
  - Migration instructions for upgrading single-user installations
  - REST API endpoint reference for workspace operations

### Changed
- Updated access control documentation (`docs/access-control.md`)
  - Documented permission resolution chain (author > private > workspace > shared > deny)
  - Added permission levels section explaining return values from `get_user_permission()`
  - Documented visibility settings and workspace access checking
  - Added direct sharing documentation with `_shared_with` meta structure

## [1.61.0] - 2026-01-13

### Added
- Multi-user migration WP-CLI command for upgrading existing installations
  - `wp prm multiuser migrate` sets visibility to "private" on all existing contacts, teams, and important dates
  - `wp prm multiuser migrate --dry-run` previews changes without making them
  - `wp prm multiuser validate` checks migration status and reports any posts missing visibility
  - User-friendly output with progress, summary, and next steps guidance

## [1.60.0] - 2026-01-13

### Added
- Workspace activity digest integration for collaborative awareness
  - Daily digest now includes @mention notifications queued via digest preference
  - Workspace activity section shows shared notes from other workspace members (last 24 hours)
  - Email subject line updates to indicate when team activity is included
  - Mentions shown with blue accent styling, workspace activity with green accent styling
- Slack digest support for collaborative content
  - Mentions and workspace activity sections added to Slack notification blocks
  - Consistent formatting with email digest presentation

### Changed
- STADION_Reminders now gathers mentions and workspace activity before sending digests
- Empty digests are now skipped when user has no dates, todos, mentions, or workspace activity

## [1.59.1] - 2026-01-13

### Fixed
- Team editing now includes visibility fields in REST API payload to satisfy ACF required field validation

## [1.59.0] - 2026-01-13

### Added
- @mention notification system for collaborative features
  - STADION_Mention_Notifications class handles notification delivery when users are mentioned
  - Immediate email notifications or queue for daily digest based on user preference
  - User preference stored in `stadion_mention_notifications` user meta (digest/immediate/never)
  - Self-mentions are automatically ignored (no notification sent)
- MentionInput integration in NoteModal for workspace contacts
  - NoteModal now uses MentionInput component for contacts shared with workspaces
  - Regular RichTextEditor used for private contacts (backward compatible)
  - Workspace IDs passed through to enable member autocomplete
- Mention notification preference in Settings
  - New "Mention notifications" dropdown in Notifications tab
  - Three options: Include in daily digest (default), Send immediately, Don't notify me
  - REST API endpoint `/stadion/v1/user/mention-notifications` for preference management

## [1.58.0] - 2026-01-13

### Added
- Note visibility controls for collaborative note-taking
  - Notes can be marked as private (only author sees) or shared (visible to all who can see the contact)
  - Default visibility is private, preserving single-user experience
  - `_note_visibility` comment meta field stores visibility setting
  - API endpoints support visibility parameter for create/update operations
  - Timeline and notes endpoints filter based on visibility
  - NoteModal includes visibility toggle when contact is shared with workspace or other users
  - TimelineView displays Lock/Globe icons indicating note visibility
  - Shared notes have subtle blue left border for visual distinction
- New @mentions infrastructure for collaborative notes
  - MentionInput React component using react-mentions library
  - Workspace member search API endpoint (`/stadion/v1/workspaces/members/search`)
  - STADION_Mentions PHP class for parsing, storing, and rendering @mentions
  - Mentioned user IDs stored in comment meta `_mentioned_users`
  - Action hook `stadion_user_mentioned` fires when users are mentioned
- Workspace iCal calendar feed support
  - New `/workspace/{id}/calendar/{token}.ics` endpoint for workspace calendars
  - Includes important dates for all contacts shared with the workspace
  - Token-based authentication validates user membership
  - Calendar Subscription UI in WorkspaceDetail page with copy button
  - iCal URL API endpoint now returns raw token for constructing workspace URLs

## [1.57.2] - 2026-01-13

### Added
- New `wp prm carddav reset_sync` CLI command to force full CardDAV resync

### Fixed
- CardDAV sync token key consistency (int vs string) causing sync to fail

## [1.57.1] - 2026-01-13

### Fixed
- CardDAV sync now tracks changes made via web UI (previously only tracked CardDAV-originated changes)
- New contacts created in Stadion web interface now properly sync to CardDAV clients (iPhone, etc.)

## [1.57.0] - 2026-01-13

### Changed
- Refactored team creation into shared `useCreateTeam` hook in `useTeams.js` (DRY principle)
- Updated TeamsList.jsx and Layout.jsx to use shared hook

### Fixed
- Add missing `_visibility` and `_assigned_workspaces` fields to team quick-add in Layout.jsx

## [1.56.0] - 2026-01-13

### Changed
- Refactored date creation into shared `useCreateDate` hook in `useDates.js` (DRY principle)
- Updated DatesList.jsx and Layout.jsx to use shared hook

## [1.55.1] - 2026-01-13

### Fixed
- Add missing `_visibility` field to all date creation payloads (DatesList, PersonDetail, Layout quick-add, birthday creation)

## [1.55.0] - 2026-01-13

### Added
- New WP-CLI command `wp prm visibility set_defaults` to set default visibility for existing posts

### Changed
- Refactored `useCreatePerson` hook to contain full person creation logic (payload building, Gravatar sideload, birthday creation)
- Updated PeopleList.jsx and Layout.jsx to use shared `useCreatePerson` hook (DRY principle)

## [1.54.1] - 2026-01-13

### Fixed
- Add missing `_visibility` and `_assigned_workspaces` fields to quick-add person mutation in Layout.jsx

## [1.54.0] - 2026-01-13

### Added
- `WorkspaceSettings` page for workspace owners to edit name/description and delete workspace
- `WorkspaceInviteAccept` page for users to accept workspace invitations via email links
- Routes for `/workspaces/:id/settings` and `/workspace-invite/:token`
- Delete confirmation requires typing workspace name to prevent accidental deletion

## [1.53.0] - 2026-01-13

### Added
- ShareModal component for sharing contacts/teams with specific users
- `useSharing.js` hook with `useShares`, `useAddShare`, `useRemoveShare`, `useUserSearch` hooks
- Share REST endpoints for People (`/stadion/v1/people/{id}/shares`)
- Share REST endpoints for Teams (`/stadion/v1/teams/{id}/shares`)
- User search endpoint (`/stadion/v1/users/search`) for finding users to share with
- Share button in PersonDetail page header
- Share button in TeamDetail page header

## [1.52.0] - 2026-01-13

### Added
- `WorkspacesList` page for viewing and managing workspaces
- `WorkspaceDetail` page with member list and role management
- `WorkspaceCreateModal` for creating new workspaces
- `WorkspaceInviteModal` for sending workspace invitations
- Role badges (Owner, Admin, Member, Viewer) with appropriate styling
- Workspace navigation item in sidebar
- Routes for /workspaces and /workspaces/:id

## [1.51.0] - 2026-01-13

### Added
- `VisibilitySelector` component for setting visibility (private/workspace) on contacts and teams
- Visibility controls integrated into PersonEditModal (add and edit modes)
- Visibility controls integrated into TeamEditModal (add and edit modes)
- Visibility and workspace assignment fields included in person/team create payloads

## [1.50.0] - 2026-01-13

### Added
- Ownership filter (All/My Contacts/Shared with Me) to People list
- Ownership filter (All/My Teams/Shared with Me) to Teams list
- Workspace filter dropdown to People and Teams lists
- Filter chips for active ownership and workspace filters
- "No results match your filters" empty state with clear filters button

## [1.49.0] - 2026-01-13

### Added
- TanStack Query hooks for workspace operations (`useWorkspaces.js`)
- Workspace API methods in `client.js` (workspaces, members, invites)
- Sharing API methods in `client.js` (shares, user search)
- `useWorkspaces`, `useWorkspace`, `useCreateWorkspace`, `useUpdateWorkspace`, `useDeleteWorkspace` hooks
- `useAddWorkspaceMember`, `useRemoveWorkspaceMember`, `useUpdateWorkspaceMember` hooks
- `useWorkspaceInvites`, `useCreateWorkspaceInvite`, `useRevokeWorkspaceInvite` hooks
- `useValidateInvite`, `useAcceptInvite` hooks for public invite flow

## [1.48.0] - 2026-01-13

### Added
- Workspace invitation REST API endpoints in `STADION_REST_Workspaces`
- POST /stadion/v1/workspaces/{id}/invites - Create and send invitation email
- GET /stadion/v1/workspaces/{id}/invites - List pending invites
- DELETE /stadion/v1/workspaces/{id}/invites/{invite_id} - Revoke pending invite
- GET /stadion/v1/invites/{token} - Validate invite (public, no auth required)
- POST /stadion/v1/invites/{token}/accept - Accept invite and join workspace
- HTML email template for invitation notifications

## [1.47.0] - 2026-01-13

### Added
- `workspace_invite` Custom Post Type for tracking workspace invitations
- ACF field group for invite metadata (email, role, token, status, expiry)
- Invites appear in admin under Workspaces menu for easy management

## [1.46.0] - 2026-01-13

### Added
- Workspace term sync functionality in `STADION_Taxonomies`
- Auto-creates `workspace-{ID}` terms when workspaces are published
- Updates term names when workspace titles change
- Removes terms when workspaces are permanently deleted
- ACF field for assigning contacts to workspaces (shown when visibility = workspace)
- New `STADION_REST_Workspaces` class for workspace REST API endpoints
- GET/POST /stadion/v1/workspaces for listing and creating workspaces
- GET/PUT/DELETE /stadion/v1/workspaces/{id} for workspace details and management
- POST /stadion/v1/workspaces/{id}/members for adding members
- DELETE/PUT /stadion/v1/workspaces/{id}/members/{user_id} for removing and updating members
- Permission callbacks for workspace access, admin, and owner checks

## [1.45.0] - 2026-01-13

### Added
- Extended access control for visibility, workspace membership, and direct shares
- `get_accessible_post_ids()` now includes workspace-visible and shared posts
- New `get_user_permission()` method returns permission level (owner, admin, member, viewer, edit, view)
- Full permission resolution chain: author → private → workspace → shares → deny

### Changed
- `user_can_access_post()` now checks full visibility/workspace/share chain

## [1.44.0] - 2026-01-13

### Added
- Workspace membership management system via `STADION_Workspace_Members` class
- User meta storage for workspace memberships with roles (admin, member, viewer)
- Methods for adding, removing, and updating user workspace memberships
- Query helpers to get user workspaces, workspace members, and role checks
- Automatic workspace owner membership: workspace creators are auto-added as admin members
- Protection against removing workspace owner from membership
- ACF visibility field group for Person, Team, and Important Date post types
- Visibility options: private (default), workspace, and shared with specific users
- `STADION_Visibility` helper class for managing visibility and sharing
- Share management methods: add_share, remove_share, get_shares, user_has_share
- `_shared_with` post meta for storing direct user shares with permissions

## [1.43.1] - 2026-01-13

### Added
- workspace_access taxonomy for assigning contacts to workspaces
- REST API endpoint `/wp/v2/workspace_access` for workspace access terms

## [1.43.0] - 2026-01-13

### Added
- Workspace Custom Post Type for multi-user collaboration support
- REST API endpoint `/wp/v2/workspaces` for workspace management

## [1.42.7] - 2026-01-13

### Changed
- Removed all console.error() calls from React components for cleaner production code
- Error handling now shows user-friendly alerts instead of logging to console

## [1.42.6] - 2026-01-13

### Fixed
- Fixed double-encoding of HTML entities in REST API responses (& showing as &amp;)
- sanitize_text() now correctly decodes entities for JSON output (React handles XSS escaping)

## [1.42.5] - 2026-01-13

### Changed
- Added XSS sanitization to REST API responses using WordPress native functions
- Added sanitize_text(), sanitize_rich_content(), sanitize_url() helpers to STADION_REST_Base
- All user-supplied content in API responses now properly escaped (defense-in-depth)

## [1.42.4] - 2026-01-13

### Changed
- Webhook URL validation now restricts to hooks.slack.com domain only (prevents SSRF attacks)
- Added domain validation in both validate_callback and update_slack_webhook method

## [1.42.3] - 2026-01-13

### Changed
- Slack bot tokens are now encrypted using sodium_crypto_secretbox instead of base64 encoding
- Added encrypt_token/decrypt_token helper methods to STADION_REST_Slack class
- Legacy base64-encoded tokens are automatically migrated on first read
- Graceful fallback to base64 if STADION_ENCRYPTION_KEY constant is not defined

## [1.42.2] - 2026-01-09

### Fixed
- ImportantDateModal now correctly displays selected people when editing a date
- ImportantDateModal now handles date_type names from API (converts to IDs for form)
- ImportantDateModal no longer sends `[null]` for date_type when saving

## [1.42.1] - 2026-01-09

### Fixed
- STADION_THEME_VERSION now reads from style.css instead of being hardcoded to 1.0.0

## [1.42.0] - 2026-01-09

### Added
- Version check system for PWA/mobile app cache invalidation
- New `/stadion/v1/version` REST API endpoint returns current theme version
- `useVersionCheck` hook monitors for new versions every 5 minutes and on tab visibility change
- Update banner appears at top of screen when a new version is available, with one-click reload

## [1.41.3] - 2026-01-09

### Fixed
- Gender symbol now aligns properly with pronouns and age on mobile

## [1.41.2] - 2026-01-09

### Fixed
- Pronouns are now properly saved when editing or creating a person

## [1.41.1] - 2026-01-09

### Changed
- PersonDetail: Pronouns now displayed between gender symbol and age (e.g., "♂ — they/them — 35 years old")

## [1.41.0] - 2026-01-09

### Added
- Pronouns field added to person records
- vCard export: Pronouns exported as both PRONOUNS (RFC 9554) and X-PRONOUNS (Apple)
- vCard import: Pronouns parsed from PRONOUNS and X-PRONOUNS properties
- CardDAV: Full pronouns sync support
- PersonEditModal: Pronouns field added alongside gender

## [1.40.1] - 2026-01-09

### Added
- vCard import: Base64 encoded photos are now imported and sideloaded as featured images
- CardDAV: Photo sync support for both base64 encoded and URL-based photos

## [1.40.0] - 2026-01-09

### Added
- vCard export: Social links now use X-SOCIALPROFILE for better client compatibility
- vCard export: GENDER field is now exported (M/F/O/N codes)
- vCard export: Slack contacts exported as IMPP;X-SERVICE-TYPE=Slack
- vCard import: X-SOCIALPROFILE parsing for social network profiles
- vCard import: GENDER field parsing and mapping to system gender values
- vCard import: IMPP parsing for Slack contacts

### Changed
- vCard export: LinkedIn, Twitter, Instagram, Facebook use X-SOCIALPROFILE instead of generic URL

## [1.39.6] - 2026-01-09

### Added
- vCard import: NOTE lines are now imported as timeline notes

### Changed
- vCard import: Multiple NOTE entries are now supported (all imported as separate timeline notes)
- vCard import: Phone labels (Home/Work) now preserved during import
- STADION_VCard_Import now uses notes array internally for consistency

## [1.39.5] - 2026-01-09

### Changed
- vCard import: Email with TYPE=HOME/WORK now sets label to "Home"/"Work"
- vCard import: Phone with TYPE=CELL (even with VOICE/pref) now correctly imports as mobile
- vCard import: Phone with TYPE=HOME/WORK now sets label accordingly
- vCard export: Photos now embedded inline as base64 per RFC 2426 instead of URI reference

## [1.39.4] - 2026-01-09

### Added
- WP-CLI command `wp prm vcard get <person_id>` to export vCard for a person
- WP-CLI command `wp prm vcard parse <file>` to preview what a vCard import would contain

## [1.39.3] - 2026-01-09

### Changed
- Timeline Note and Activity buttons now use distinct icons (StickyNote and MessageCircle) visible on mobile

## [1.39.2] - 2026-01-09

### Changed
- Moved "View family tree" button to Relationships card header
- Simplified Add buttons to just "+" icon with tooltip for: Relationships, Important dates, Addresses, Todos, Work history

## [1.39.1] - 2026-01-09

### Changed
- Tab content within PersonDetail now uses masonry layout (CSS columns)
- Responsive: 1 column on mobile, 2 columns on tablet/desktop
- Cards flow vertically first, then into next column for optimal space usage

## [1.39.0] - 2026-01-09

### Changed
- PersonDetail page now uses tab-based interface with three tabs: Profile, Timeline, and Work
- Profile tab contains: Contact information, Addresses, Important dates, How we met, Relationships
- Timeline tab contains: Todos, Timeline (activities/notes)
- Work tab contains: Work history, Investments, Colleagues
- "Events" section renamed to "Important dates"
- Removed three-column grid layout in favor of cleaner tab-based team

## [1.38.1] - 2026-01-09

### Fixed
- Links in activities/notes now visually styled as links (blue, underlined)
- Links in activities/notes now open in new tab with proper security attributes
- List items (ul/ol) in activities/notes now display proper bullets/numbers

## [1.38.0] - 2026-01-09

### Added
- Colleagues card on PersonDetail page - shows current employees from the same team/teams
- Colleagues are only displayed if the person has a current job (no end date)
- Colleagues sorted alphabetically with job title displayed

## [1.37.0] - 2026-01-09

### Changed
- Slack contacts now appear in Contact Details section (with link) instead of social icons
- WhatsApp icon now appears in social icons if a mobile number exists
- Removed WhatsApp button next to mobile numbers (now in social icons row)
- Links in activities and notes are now automatically clickable (using WordPress make_clickable)

## [1.36.0] - 2026-01-09

### Added
- TeamEditModal now supports full editing with all fields (parent team, investors)
- Parent team selector with searchable dropdown in TeamEditModal
- Investors multi-select (people and teams) in TeamEditModal

### Changed
- TeamDetail "Edit" button now opens TeamEditModal instead of navigating to separate page
- Logo upload remains on TeamDetail page (hover over logo to upload)

### Removed
- TeamForm.jsx removed - all team creation/editing now via TeamEditModal
- Routes `/teams/new` and `/teams/:id/edit` removed

## [1.35.0] - 2026-01-09

### Added
- PersonEditModal now supports full editing with all fields (nickname, gender, how we met, favorite)
- vCard import support in PersonEditModal when creating new people (drag & drop or browse)
- Gravatar auto-fetch when email is provided in person creation
- Birthday creation support when creating a new person

### Changed
- PersonDetail "Edit" button now opens PersonEditModal instead of navigating to separate page
- PersonEditModal shows email/phone/birthday fields only when creating (editing contacts separately)

### Removed
- PersonForm.jsx removed - all person creation/editing now via PersonEditModal
- Routes `/people/new` and `/people/:id/edit` removed

## [1.34.0] - 2026-01-09

### Added
- PersonEditModal: Quick add person from header + button, People list, and empty states
- TeamEditModal: Quick add team from header + button, Teams list, and empty states
- All "Add" buttons throughout the app now open modals instead of navigating to separate pages

### Changed
- Header + button now opens modals for Person, Team, Todo, and Date creation
- People list "Add person" button now opens modal
- Teams list "Add team" button now opens modal
- Dates list "Add date" button now opens modal

## [1.33.0] - 2026-01-09

### Added
- ImportantDateModal: Add/edit important dates directly from person detail page via modal
- RelationshipEditModal: Add/edit relationships directly from person detail page via modal
- AddressEditModal: Add/edit addresses directly from person detail page via modal
- WorkHistoryEditModal: Add/edit work history directly from person detail page via modal

### Changed
- All person-related forms now open as modals instead of navigating to separate pages
- Improved UX with "Add one" links when sections are empty

### Removed
- Standalone page routes for dates, relationships, addresses, and work history forms
- Old form components (DateForm, RelationshipForm, AddressForm, WorkHistoryForm)

## [1.32.0] - 2026-01-09

### Added
- Contact Edit Modal: Edit all contact details (email, phone, social links) in a single modal dialog
- Ability to add, edit, and remove multiple contact entries at once

### Changed
- Replaced individual contact detail edit pages with unified modal editor
- "Add contact detail" button changed to "Edit" button on Contact information card

### Removed
- Individual contact detail edit routes (`/people/:id/contact/new`, `/people/:id/contact/:index/edit`)

## [1.31.0] - 2026-01-09

### Added
- Slack links support for contacts - add direct DM links like `https://workspace.slack.com/archives/D08V2DLMQ13`
- Multiple Slack links can be added per person (e.g., different workspaces) using the label field

## [1.30.2] - 2026-01-09

### Changed
- Add activity modal: Description field now takes 2/3 width on desktop with taller input area (280px)
- Modal width increased to max-w-4xl for better proportions

## [1.30.1] - 2026-01-09

### Fixed
- Chat activity type now shows the correct MessageCircle icon instead of a generic circle in the timeline

### Changed
- Add activity modal redesigned with two-column layout for better UX
- Description field is now larger and prominently placed on the right, making it easier to add call/chat notes

## [1.30.0] - 2026-01-09

### Added
- "Recently contacted" section on Dashboard showing people with most recent activities

## [1.29.1] - 2026-01-09

### Added
- Sort people by "Last modified" date on the People page

## [1.29.0] - 2026-01-09

### Added
- Filter people by birth year on the People page
- Filter people by last modified date (7 days, 30 days, 90 days, 1 year) on the People page

## [1.28.0] - 2026-01-09

### Added
- "Year unknown" option for important dates (birthdays, etc.) when you don't know the year
- Dates with unknown year display without year and skip age calculation

## [1.27.2] - 2026-01-09

### Changed
- Teams in "Add work history" form are now sorted alphabetically

## [1.27.1] - 2026-01-09

### Changed
- Import and Export functionality now integrated directly into Settings Data tab (removed separate pages)
- Updated labels to use sentence case throughout Settings page

### Removed
- Separate Import and Export pages (`/settings/import`, `/settings/export`)

## [1.27.0] - 2026-01-09

### Changed
- Settings page reorganized into tabbed interface for better navigation
  - **Sync** tab: Calendar subscription and CardDAV sync settings
  - **Notifications** tab: Email and Slack notification preferences
  - **Data** tab: Import and export functionality
  - **Admin** tab: User approval, relationship types, and system actions (admin only)
  - **About** tab: Version information

## [1.26.3] - 2026-01-08

### Added
- Admins now receive an email notification when a new user registers and is waiting for approval

## [1.26.2] - 2026-01-08

### Fixed
- Dashboard now live-updates when creating, editing, or deleting contacts and teams (no hard reload needed)

## [1.26.1] - 2026-01-08

### Fixed
- Backend now properly stores HTML content for notes and activities (changed from `sanitize_textarea_field` to `wp_kses_post`)

## [1.26.0] - 2026-01-08

### Added
- Rich text editor for notes and activities with support for bold, italic, lists, and links
- TipTap-based editor with formatting toolbar

## [1.25.9] - 2026-01-08

### Fixed
- CardDAV now stores and uses client-provided URIs for contact lookups, enabling proper sync with Apple Contacts and other clients that use custom URI formats

## [1.25.8] - 2026-01-08

### Added
- `.well-known/carddav` auto-discovery endpoint for proper CardDAV client setup

## [1.25.7] - 2026-01-08

### Added
- Detailed CardDAV logging for create, update, and delete operations (includes person IDs)
- Enhanced auth logging for debugging intermittent failures

## [1.25.6] - 2026-01-08

### Fixed
- CardDAV authentication now uses `wp_verify_fast_hash()` for WordPress 6.8+ BLAKE2b hashing (`$generic$` prefix) with fallback to `wp_check_password()` for older versions
- Reverted custom password storage in favor of native WordPress Application Passwords

## [1.25.5] - 2026-01-08

### Changed
- CardDAV now uses its own password storage with standard WordPress hashing, bypassing SiteGround's custom `$generic$` hash format that couldn't be verified

### Fixed
- CardDAV authentication now works on SiteGround hosting

## [1.25.4] - 2026-01-08

### Fixed
- CardDAV authentication - directly validate against WP_Application_Passwords instead of wp_authenticate() which restricts app passwords to REST/XML-RPC requests

## [1.25.3] - 2026-01-08

### Fixed
- CardDAV authentication - use wp_authenticate() instead of wp_authenticate_application_password() which is only a filter callback

## [1.25.2] - 2026-01-08

### Fixed
- App password creation failing with "Invalid parameter(s): app_id" error - removed unnecessary app_id parameter

## [1.25.1] - 2026-01-08

### Added
- Time field for activities - activities now support recording both date and time
- Activity form defaults to current date and time
- Timeline displays activity time alongside date (e.g., "Yesterday at 14:30", "Jan 5, 2026 at 09:00")
- Relative time display now correctly uses combined date+time (e.g., "30 minutes ago" instead of "about 10 hours ago")

## [1.25.0] - 2026-01-08

### Added
- CardDAV server for bidirectional contact sync with Apple Contacts, Android (DAVx5), Thunderbird, and other CardDAV clients
- App Passwords management UI in Settings for secure CardDAV authentication
- PHP vCard export class for server-side vCard generation
- Sabre/DAV integration with custom backends for authentication, principals, and contacts
- Sync token support for efficient incremental synchronization
- CardDAV documentation with setup guides for all major platforms
- Composer dependency management with sabre/dav 4.7.0

### Technical Details
- CardDAV endpoint at `/carddav/` with full RFC 6352 compliance
- Uses WordPress native Application Passwords for authentication
- Sync tokens track changes per-user for efficient delta sync
- Respects existing access control (users only see their own contacts)

## [1.24.8] - 2026-01-07

### Changed
- Person detail page layout: Timeline now appears above Work history
- Person detail page layout: Addresses moved to sidebar, below Relationships

## [1.24.7] - 2026-01-07

### Added
- Click-to-upload logo on team detail page (hover over logo to see camera icon, click to upload)

## [1.24.6] - 2026-01-07

### Changed
- Major performance improvement: People list now loads significantly faster
- Deceased status (`is_deceased`) is now computed server-side and included in person REST responses
- Eliminated N+1 API queries when loading the People screen (was making one API call per person)

## [1.24.5] - 2026-01-07

### Changed
- Reminder card avatars now match the size of favorites and recently edited people (40px)

## [1.24.4] - 2026-01-07

### Added
- Phone number field on Add Person form with type selector (Mobile/Phone)
- vCard import on Add Person screen now properly imports phone numbers with correct type

### Fixed
- vCard parse endpoint now returns phone_type for proper mobile/phone distinction

## [1.24.3] - 2026-01-07

### Fixed
- vCard import now correctly handles phone numbers with multiple TYPE parameters (e.g., `TEL;type=CELL;type=VOICE;type=pref`)
- Phone type detection prioritizes meaningful types (CELL, MOBILE) over generic ones (VOICE, pref)

## [1.24.2] - 2026-01-07

### Changed
- Dashboard reminder cards now show the reminder title instead of the person name
- Related people avatars in reminder cards moved to the right side for a cleaner layout

## [1.24.1] - 2026-01-07

### Fixed
- Checkbox labels now properly toggle their associated checkboxes when clicked (PersonForm, DateForm)

## [1.24.0] - 2026-01-07

### Added
- Structured address fields: addresses now have separate fields for Street, Postal code, City, State/Province, and Country
- Dedicated Addresses section on person detail page with multi-line display format
- AddressForm component for adding and editing addresses with structured fields
- WP-CLI migration command `wp prm migrate addresses` to migrate existing single-line addresses to new format
- vCard import/export now uses structured address format (ADR property with all components)
- Google Contacts import now maps address components to structured fields
- Monica import now uses structured address fields

### Changed
- Removed "Address" option from contact detail types (addresses now have their own dedicated section)
- Addresses display format: Street / Postal code City / State, Country (each on own line)

## [1.23.0] - 2026-01-07

### Added
- New "Calendar link" contact type for Calendly, Cal.com, and similar scheduling links
- Calendar links display with a calendar icon and open in a new tab when clicked

## [1.22.5] - 2026-01-07

### Fixed
- Work history form: "Currently works here" label is now clickable to toggle the checkbox

## [1.22.4] - 2026-01-07

### Changed
- Email notifications: Removed calendar emoji from section headings (Today, Tomorrow, This week)
- Email notifications: Changed section headings from ALL CAPS to sentence case

## [1.22.3] - 2026-01-07

### Fixed
- Timeline: Edit button for activities now works - opens the activity modal with data prefilled for editing

## [1.22.2] - 2026-01-07

### Added
- Dashboard: Completing a todo now shows the same "Complete & log activity" option as on other screens

## [1.22.1] - 2026-01-07

### Changed
- Todos page now always shows recently completed todos (last 3 days) in a separate "Recently completed" section
- "Show all completed" button only appears when there are older completed todos
- Improved UI: recent completions always visible, older completions hidden by default

## [1.22.0] - 2026-01-07

### Added
- "Complete & log activity" option when completing todos - converts the todo into a timeline activity
- New CompleteTodoModal component that offers choice between just completing or logging as activity
- QuickActivityModal now accepts initialData prop to prefill form fields

### Changed
- When completing a todo, users are now prompted with options: "Just complete" or "Complete & log activity"
- The activity modal is prefilled with the todo content when converting to activity
- After logging the activity, the todo is automatically marked as complete

## [1.21.0] - 2026-01-07

### Added
- "Investments" section on person detail page showing teams they've invested in
- "Invested in" section on team detail page showing teams they've invested in
- New REST API endpoint `/stadion/v1/investments/{id}` to query reverse investor relationships

## [1.20.2] - 2026-01-07

### Fixed
- Investors field now saves and loads properly (changed from ACF relationship to post_object field type)
- Existing investors now appear when editing an team

## [1.20.1] - 2026-01-07

### Fixed
- Investor names now display correctly on team detail page (was showing "Team" instead of actual names)

## [1.20.0] - 2026-01-07

### Added
- New "Investors" field for teams allowing both people and teams to be listed as investors
- Investors section displayed on team detail page with links to people/teams
- Multi-select investor picker in team form with search

## [1.19.3] - 2026-01-07

### Added
- Todos are now included in email and Slack notifications alongside important dates
- Notifications show todos grouped by today, tomorrow, and rest of week with overdue indicators

## [1.19.2] - 2026-01-07

### Added
- New "Chat" activity type for logging chat/messaging interactions

## [1.19.1] - 2026-01-07

### Changed
- Dashboard todo card: moved due date to the right side for cleaner layout

## [1.19.0] - 2026-01-07

### Added
- vCard import on "Add new person" screen - drop a .vcf file to pre-fill the form
- New API endpoint `/stadion/v1/import/vcard/parse` to parse single vCard contact data

## [1.18.0] - 2026-01-07

### Changed
- Search is now a lightbox/modal instead of inline in the header
- Press ⌘K (Mac) or Ctrl+K (Windows/Linux) to open search from anywhere
- Search modal supports keyboard navigation (arrow keys and Enter to select)
- Search button in header shows keyboard shortcut hint

## [1.17.0] - 2026-01-07

### Added
- Teams can now have parent teams (hierarchical structure)
- Parent team selector in team form with searchable dropdown
- Subsidiaries section on team detail page showing child teams
- "Subsidiary of" link displayed on team header when team has a parent

## [1.16.1] - 2026-01-07

### Changed
- Todo due date now defaults to today when creating new todos

## [1.16.0] - 2026-01-07

### Added
- Collapsible search bar in header - opens on click for cleaner UI
- Quick Add menu (+) in header for creating new Person, Team, Todo, or Date
- Global Todo creation with person dropdown - accessible from header and Todos page
- "Add todo" button on Todos list page

### Changed
- Search results dropdown now positioned to the right for better UX

## [1.15.1] - 2026-01-07

### Fixed
- Navigation menu labels now always visible (were incorrectly hidden on mobile)

## [1.15.0] - 2026-01-07

### Added
- New Todos page accessible from the main navigation menu
- Dashboard now shows "Open todos" stat card (4th card in the stats row)
- Dashboard now displays open todos alongside upcoming reminders
- REST API endpoint `GET /stadion/v1/todos` to fetch all todos across all people
- Dashboard API now returns `open_todos_count` in stats

### Changed
- Dashboard layout reorganized: Row 1 has Upcoming reminders + Open todos, Row 2 has Favorites + Recently edited people
- Stats row now shows 4 cards (People, Teams, Events, Open todos)
- Todos page shows all open todos with ability to toggle completion, edit, and delete
- Todos are sorted by due date (earliest first), with completed todos at the bottom

## [1.14.8] - 2026-01-07

### Changed
- Todos now displayed in their own card in the right sidebar, above Relationships
- Todos removed from Timeline section (Timeline now only shows notes and activities)
- Todo card includes toggle, edit, and delete functionality
- Incomplete todos shown first (sorted by due date), completed todos at bottom

## [1.14.7] - 2026-01-07

### Changed
- Todos no longer display creation date (the "• Yesterday" line is removed)
- Only notes and activities show the date/time indicator

## [1.14.6] - 2026-01-07

### Fixed
- Fixed "Failed to update todo" error when updating todo metadata (due date, completion status) without changing content
- Fixed same issue in note updates
- `wp_update_comment` returns 0 when content is unchanged, which was incorrectly treated as an error

## [1.14.5] - 2026-01-07

### Changed
- Todos now display in their own section at the top of the timeline, separate from notes and activities
- Todos are ordered by due date (earliest first) instead of creation date
- Completed todos are shown at the bottom of the todos section
- Todos without due dates appear after those with due dates

## [1.14.4] - 2026-01-07

### Fixed
- Todos now correctly display on person timeline (was being excluded by comment filter)
- Fixed `exclude_from_regular_queries` filter to check for `type__in` in addition to `type` to prevent excluding our custom comment types from timeline queries

## [1.14.3] - 2026-01-07

### Added
- WordPress backend URLs now redirect to SPA frontend routes (e.g., `?post_type=person&p=291` → `/people/291`)

## [1.14.2] - 2026-01-07

### Fixed
- Notification time picker now rounds to nearest 5 minutes (browsers don't enforce step attribute)

## [1.14.1] - 2026-01-07

### Changed
- Notification time picker now uses 5-minute steps to match server cron frequency

## [1.14.0] - 2026-01-07

### Added
- Per-user cron jobs for precise notification timing at each user's preferred time
- Admin button to reschedule all user reminder cron jobs in Settings
- REST API endpoint `POST /stadion/v1/reminders/reschedule-cron` to reschedule all cron jobs
- User cron job cleanup when user is deleted via `delete_user` hook

### Changed
- Notification timing now uses individual cron jobs per user instead of single daily cron
- `update_notification_time` API endpoint now reschedules user's cron job automatically
- `get_cron_status` API endpoint now returns per-user cron status information
- Updated reminders documentation to reflect per-user cron architecture

### Deprecated
- `process_daily_reminders()` method - use `process_user_reminders($user_id)` instead

## [1.13.1] - 2026-01-04

### Added
- WP-CLI command `wp prm reminders trigger` to manually trigger daily reminders
- REST API endpoint `/stadion/v1/reminders/cron-status` to check cron job status

## [1.13.0] - 2026-01-04

### Added
- Enhanced timeline view with visual timeline design, date grouping, and icons
- Quick activity logging modal with activity type selector, date picker, and participant selection
- Todo system: person-specific todos with completion status and optional due dates
- Todo creation and editing modals
- Note creation modal
- Timeline utilities for date formatting, grouping, and activity type icons
- REST API endpoints for todos: GET, POST, PUT, DELETE `/stadion/v1/people/{id}/todos` and `/stadion/v1/todos/{id}`
- Todo comment type (`stadion_todo`) with meta fields: `is_completed` (boolean) and `due_date` (string)
- WP-CLI command `wp prm reminders trigger` to manually trigger daily reminders
- REST API endpoint `/stadion/v1/reminders/cron-status` to check cron job status

### Changed
- Timeline section now displays notes, activities, and todos in a unified view
- Timeline items are grouped by date (Today, Yesterday, This Week, Older)
- Activity types show appropriate icons (call, email, meeting, coffee, lunch, note)
- Completed todos are visually distinct with strikethrough and muted colors
- Overdue todos are highlighted in red
- Timeline endpoint now includes todos alongside notes and activities

## [1.12.2] - 2026-01-04

### Added
- Slack notification target configuration interface in Settings
- Ability to select multiple Slack channels and users for notifications
- REST API endpoints to fetch Slack channels/users and manage notification targets
- Support for sending notifications to multiple targets simultaneously

### Changed
- Simplified notification format: if a person's name appears in the date title (e.g., "Eva Douma's Birthday"), that name becomes the clickable link and the duplicate name below is removed
- Removed header "Your Important Dates - <date>" from Slack notifications

### Fixed
- Fixed Slack API calls to use POST instead of GET for conversations.list and users.list
- Fixed Slack data loading when Slack is already connected on page load
- Fixed checkbox interaction by adding proper cursor styling

## [1.12.1] - 2026-01-04

### Fixed
- Fixed Slack OAuth URL construction to properly pass client_id parameter
- Fixed Slack OAuth authorize endpoint to return JSON instead of redirect (REST API endpoints cannot use wp_redirect)

## [1.12.0] - 2026-01-04

### Added
- Slack OAuth 2.0 integration replacing webhook-based integration
- OAuth flow with "Connect Slack" button in Settings
- Slack Web API support for messaging channels and users directly
- Slash command `/stadion` to view recent reminders in Slack
- Automatic Slack user ID mapping for direct messaging
- Slack workspace name display in Settings
- Event subscription endpoint for Slack URL verification

### Changed
- Slack notifications now use Web API (`chat.postMessage`) instead of incoming webhooks
- Slack connection status shown in Settings instead of webhook URL input
- Legacy webhook support maintained for backward compatibility during migration

### Technical Details
- New REST API endpoints: `/stadion/v1/slack/oauth/authorize`, `/stadion/v1/slack/oauth/callback`, `/stadion/v1/slack/disconnect`, `/stadion/v1/slack/commands`, `/stadion/v1/slack/events`
- User meta keys: `stadion_slack_bot_token`, `stadion_slack_workspace_id`, `stadion_slack_workspace_name`, `stadion_slack_user_id`
- Requires WordPress constants: `STADION_SLACK_CLIENT_ID`, `STADION_SLACK_CLIENT_SECRET`, `STADION_SLACK_SIGNING_SECRET`

## [1.11.4] - 2026-01-04

### Changed
- Changed all Title Case text to Sentence case across the entire app for consistency (e.g., "First Name" → "First name", "Add Person" → "Add person", "Save Changes" → "Save changes")

## [1.11.3] - 2026-01-04

### Changed
- Updated dashboard text labels for consistency: "Upcoming Reminders" → "Upcoming reminders", "Recent People" → "Recently edited people", "Total People" → "Total people", "Important Dates" → "Events"
- Updated "View all" links to be more descriptive: "View all reminders" and "View all people"

## [1.11.2] - 2026-01-04

### Changed
- Dashboard card headers now display appropriate icons (Star for Favorites, Calendar for Upcoming Reminders, Users for Recent People)
- Removed star icons from individual favorite items in the Favorites section (star icon now only shown in header)

## [1.11.1] - 2026-01-04

### Changed
- Search functionality now only searches People and Teams - dates removed from search as they have a dedicated Dates page

## [1.11.0] - 2026-01-04

### Added
- Daily digest reminder system - users receive one notification per day with dates for today, tomorrow, and rest of week
- Multi-channel notification support (Email and Slack)
- User preferences for enabling/disabling notification channels in Settings
- Slack webhook configuration with automatic testing
- Manual trigger button for reminders (admin only) in Settings → Administration
- REST API endpoints for notification channel management and manual triggering

### Changed
- Removed `reminder_days_before` field from important dates - all dates are now included in daily digest if they occur within 7 days
- Reminder emails now use daily digest format instead of individual per-date reminders
- Notification system refactored to use channel-based architecture for extensibility

### Removed
- `reminder_days_before` ACF field from important date post type
- Per-date reminder timing configuration

## [1.10.1] - 2026-01-04

### Fixed
- Fixed pre-selection of newly created person in relationship form - now waits for person to be loaded before selecting

## [1.10.0] - 2026-01-04

### Added
- Added seamless flow to add a new person from the relationship form
- "Add New Person" button appears below the person selector when creating a new relationship
- After creating a person from the relationship form, automatically returns to relationship form with new person pre-selected
- Cancel button in PersonForm now returns to relationship form when coming from relationship flow

## [1.9.2] - 2026-01-04

### Changed
- VCard import now adds to existing contact information instead of replacing it when updating contacts
- Email addresses, phone numbers, addresses, and URLs from VCard are merged with existing entries
- Work history entries are also merged instead of replaced
- Duplicate entries are prevented by checking contact_type + contact_value combinations

## [1.9.1] - 2026-01-04

### Fixed
- VCard import now always imports photos, even when updating existing contacts that already have a photo
- Existing photos are replaced with imported photos to ensure VCard data takes precedence

## [1.9.0] - 2026-01-04

### Added
- Added ability to delete user accounts from the admin approval screen
- Delete button available for both unapproved/denied users and approved users
- When a user is deleted, all their related data (people, teams, dates) is automatically deleted
- Added WordPress hook to clean up user posts when user is deleted via any method

### Changed
- User deletion now permanently removes all associated CRM data

## [1.8.1] - 2026-01-04

### Fixed
- Fixed approval screen display for unapproved users - now shows a properly styled "Account Pending Approval" screen instead of an error
- Changed `/stadion/v1/user/me` endpoint permission callback to allow logged-in users (not just approved) so approval status can be checked

## [1.8.0] - 2026-01-04

### Changed
- Renamed "Teams" to "Teams" throughout the user interface
- Updated all user-facing labels, navigation items, page titles, and form labels
- Post type slug (`team`) and API endpoints (`/wp/v2/teams`) remain unchanged for backward compatibility
- Updated documentation to reflect the new terminology

## [1.7.1] - 2026-01-04

### Changed
- Contact information now always displays in a fixed order: Email, Phone numbers, Addresses, Other

## [1.7.0] - 2026-01-04

### Added
- Added support for Bluesky and Threads social links
- Bluesky and Threads icons appear after Twitter/X in the social icons order

### Changed
- Updated social icons display order: LinkedIn, Twitter/X, Bluesky, Threads, Instagram, Facebook, Website

## [1.6.8] - 2026-01-04

### Changed
- Social icons now always display in a fixed order: LinkedIn, Twitter/X, Instagram, Facebook, Website

## [1.6.7] - 2026-01-04

### Changed
- Replaced LinkedIn icon with custom SVG using official LinkedIn brand icon

## [1.6.6] - 2026-01-04

### Changed
- Replaced social icons with Simple Icons from @icons-pack/react-simple-icons
- Using Simple Icons for Facebook, Instagram, and Twitter/X
- LinkedIn and Website icons remain from Lucide React (Simple Icons doesn't include LinkedIn in this package version)

## [1.6.5] - 2026-01-04

### Fixed
- Replaced Lucide React social icons with Font Awesome solid icons from react-icons for proper solid/filled appearance
- Added react-icons package dependency

## [1.6.4] - 2026-01-04

### Changed
- Increased spacing above social icons (changed from mt-2 to mt-4)
- Changed social icons from outline to solid fill style

## [1.6.3] - 2026-01-04

### Fixed
- Increased spacing between lines in profile header section (changed from space-y-2 to space-y-3)

## [1.6.2] - 2026-01-04

### Changed
- Social icons moved below tags/labels in the profile header
- Increased spacing between lines in the profile header section
- Increased profile photo size by ~17% (from 96px to 112px)

## [1.6.1] - 2026-01-04

### Changed
- Social links (Facebook, LinkedIn, Instagram, Twitter/X, Website) are now displayed in the profile header bar alongside the person's name and picture
- Social links appear as icons on one line without labels
- Social links are no longer shown in the contact information section

## [1.6.0] - 2026-01-04

### Changed
- Social links (Facebook, LinkedIn, Instagram, Twitter/X, Website) are now grouped together and displayed as icons in a row underneath a "Social Links:" label
- Social link icons are clickable and use brand colors (Facebook blue, LinkedIn blue, Instagram pink, Twitter blue)
- Social links no longer show the full URL text, only the icon

## [1.5.3] - 2026-01-04

### Changed
- WhatsApp icon is now always visible (not just on hover) and displayed in WhatsApp green after phone numbers

## [1.5.2] - 2026-01-04

### Fixed
- Fixed missing import for `useQueryClient` in ContactDetailForm component

## [1.5.1] - 2026-01-04

### Changed
- WhatsApp links now use `https://wa.me/` format instead of `whatsapp:` protocol

## [1.5.0] - 2026-01-04

### Added
- Confirmation dialog when deleting relationships - asks if inverse relationship should also be deleted
- Automatic Gravatar sideloading when adding email address to person without an image

### Changed
- Relationship deletion now gives user control over inverse relationship deletion
- Email addresses added to people without images will automatically check for and load Gravatar

## [1.4.9] - 2026-01-04

### Fixed
- Fixed critical error when creating/deleting relationships via REST API
- Fixed handling of WP_Post object parameter from REST API hooks

## [1.4.8] - 2026-01-04

### Fixed
- Fixed bug where sibling relationships weren't creating inverse relationships automatically
- Improved normalization of inverse relationship type IDs to handle different ACF return formats
- Added fallback for symmetric relationships when inverse mapping is missing
- Added REST API hooks to ensure inverse sync happens

## [1.4.7] - 2026-01-04

### Changed
- Changed team logo backgrounds from gray to white for better visibility

## [1.4.6] - 2026-01-04

### Changed
- Increased team logo size in work history from 10x10 to 20x20 (2x bigger)
- Increased team logo size on team detail page from 16x16 to 24x24 (1.5x bigger)

## [1.4.5] - 2026-01-04

### Changed
- Changed team logo display from `object-cover` to `object-contain` so logos are fully visible without cropping
- Added light gray background to team logo containers for better visibility

## [1.4.4] - 2026-01-04

### Changed
- Changed "Register For This Site" notice text to "Register for Stadion"
- No longer hiding the register notice (now displays with updated text)

## [1.4.3] - 2026-01-04

### Changed
- Changed registration page title to "Register for Stadion"
- Changed login page title to "Log in to Stadion"
- Changed lost password page title to "Lost your password for Stadion?"
- Added left border color (#d97706) and 20px top margin to notice.info.message divs

## [1.4.2] - 2026-01-04

### Changed
- Modified registration confirmation message to include approval notice: "Your account is then subject to approval."
- Hidden "Register For This Site" notice on registration page

## [1.4.1] - 2026-01-04

### Changed
- Removed Administration section from Settings page (no longer needed)
- Renamed Configuration section to Administration
- Moved user approval interface to frontend Settings page (Administration section, admin only)
- Added Export Data sub-section to Settings page Data section
- Export supports vCard (.vcf) and Google Contacts CSV formats
- Added back buttons to Import and Export sub-sections

### Removed
- Administration section with WordPress admin links (moved functionality to frontend)

## [1.4.0] - 2026-01-04

### Added
- User approval system - new users default to "Stadion User" role but require admin approval before accessing the system
- Admin interface for approving/denying users:
  - Approval status column in users list
  - Bulk approve/deny actions
  - Individual approve/deny actions in user row
  - Email notification sent when user is approved
- Frontend approval check - unapproved users see a message instead of accessing the app
- Approval status included in user API response

### Changed
- Default role for new registrations is now "Stadion User" instead of Subscriber
- All REST API endpoints now check approval status before allowing access
- Access control system blocks unapproved users from viewing any data
- Users are marked as unapproved by default when registered

## [1.3.1] - 2026-01-04

### Added
- Custom "Stadion User" role automatically created on theme activation
- Role has minimal permissions: can create/edit/delete their own people and teams, upload files
- Role cannot access WordPress admin settings, manage users, or install plugins/themes
- Role is automatically removed on theme deactivation (users reassigned to Subscriber)

## [1.3.0] - 2026-01-04

### Removed
- Removed sharing functionality - the `shared_with` ACF field has been removed from all post types (person, team, important_date)
- Users can now only see posts they created themselves
- Removed sharing tab from person fields in ACF
- Removed all sharing-related logic from access control, reminders, and import classes
- Updated documentation to reflect removal of sharing functionality

### Changed
- Access control simplified - users can only access posts they authored
- Reminder notifications now only go to post authors (no longer includes shared users)

## [1.2.7] - 2026-01-04

### Changed
- Administrators are now restricted on the frontend - they can only see and access people/teams/dates they created themselves
- Administrators still have full access in the WordPress admin area for system management
- This ensures data privacy is maintained even for administrators when using the frontend React SPA

## [1.2.6] - 2026-01-04

### Fixed
- Person and team deletion now properly redirects to list page after successful deletion
- Added error handling for deletion operations with user feedback
- Trashed people and teams can no longer be accessed - users are automatically redirected to the list page
- Access control now filters out trashed posts in REST API responses
- Deletion now uses force delete to permanently remove items instead of moving to trash

## [1.2.5] - 2026-01-04

### Changed
- Hide button label text on mobile devices for buttons with icons and labels to improve mobile UI space efficiency
- Button labels are now visible on medium screens and larger (768px+)
- Affects all action buttons, navigation links, and form buttons throughout the interface

## [1.2.4] - 2026-01-04

### Changed
- Contact information rows now highlight with a subtle background color when hovering over the edit/delete buttons for better visual feedback

## [1.2.3] - 2026-01-04

### Added
- vCard export: Export individual person contacts as vCard (.vcf) files
- Export button in PersonDetail page to download contact as vCard
- vCard export includes: name, nickname, email, phone, mobile, address, website, social media links, team, job title, and birthday
- Compatible with Apple Contacts, Outlook, Android, and other vCard-compatible applications

## [1.2.2] - 2026-01-04

### Added
- Comprehensive documentation covering all system components:
  - `docs/data-model.md` - Post types, taxonomies, and ACF field definitions
  - `docs/access-control.md` - Row-level security system documentation
  - `docs/rest-api.md` - Complete API endpoint reference
  - `docs/frontend-architecture.md` - React SPA structure and patterns
  - `docs/ical-feed.md` - Calendar subscription system
  - `docs/reminders.md` - Email notification system
  - `docs/family-tree.md` - Family tree visualization feature

### Changed
- Updated `docs/import.md` with Google Contacts duplicate detection feature
- Updated `docs/architecture.md` with documentation index linking to all docs

## [1.2.1] - 2026-01-04

### Fixed
- Google Contacts import: Fixed duplicate detection not finding existing contacts due to access control filter interference

## [1.2.0] - 2026-01-04

### Added
- Google Contacts import: Duplicate detection with user choice for each match
- When a contact in the CSV matches an existing person by name, users can choose to:
  - **Update existing**: Merge the CSV data into the existing person (default)
  - **Create new**: Import as a new person (for different people with the same name)
  - **Skip**: Don't import this contact at all
- Duplicate resolution UI shows both CSV data and existing contact details including photo
- Backend returns potential duplicates during validation with existing person details

## [1.1.6] - 2026-01-04

### Added
- Google Contacts import now sideloads profile photos from Google Photos URLs
- Photos are downloaded and set as the person's featured image
- Photo count shown in validation summary and import results

## [1.1.5] - 2026-01-04

### Fixed
- Google Contacts import now supports both old and new Google CSV export formats
- Added support for "First Name"/"Last Name" columns (new format) alongside "Given Name"/"Family Name" (old format)
- Added support for "Team Name"/"Team Title" columns (new format) alongside "Team 1 - Name"/"Team 1 - Title" (old format)
- Added support for "E-mail X - Label"/"Phone X - Label" columns (new format) alongside "E-mail X - Type"/"Phone X - Type" (old format)
- Added support for `--MM-DD` birthday format (dates without year)
- Added Team Department import (appended to job title)
- Improved label formatting to handle Google's special prefixes (e.g., "* Other", "* Work")

## [1.1.4] - 2026-01-04

### Fixed
- Fixed "acf[gender] is not one of..." validation error when editing contact details, work history, or relationships for people without a gender set
- Added `sanitizePersonAcf()` utility function to properly handle empty enum fields and ensure repeater arrays

## [1.1.3] - 2026-01-04

### Changed
- Performance: Implemented conditional class loading - PHP classes are now only loaded when needed
- Added SPL autoloader for on-demand class file loading
- Core classes (Post Types, Taxonomies, Access Control) load on every request
- REST API and Import classes only load for REST requests
- Reminders class only loads for admin and cron contexts
- iCal Feed class loads early for feed requests with optimized early return

## [1.1.2] - 2026-01-04

### Changed
- DRY refactor: Created shared `src/utils/formatters.js` utility module
- Added `decodeHtml()`, `getTeamName()`, `getPersonName()`, and `getPersonInitial()` utility functions
- Removed 7 duplicate `decodeHtml` function definitions across codebase
- All team and person name display now uses consistent utility functions

## [1.1.1] - 2026-01-04

### Fixed
- Team names now properly decode HTML entities (e.g., "Twynstra &amp; Gudde" now displays as "Twynstra & Gudde")
- Fixed on People list, Teams list, Team detail page, and Person detail work history

## [1.1.0] - 2026-01-04

### Added
- vCard import: Import contacts from vCard (.vcf) files exported from Apple Contacts, Outlook, Android, or any vCard-compatible app
- Google Contacts import: Import contacts from Google Contacts CSV export files
- Import page: New tabbed interface for selecting between vCard, Google Contacts, and Monica CRM import methods
- Both imports support: names, nicknames, phone numbers, emails, addresses, websites/social media, teams with job titles, birthdays, notes, and photos (vCard only)
- Duplicate detection: Contacts with matching names are updated instead of duplicated
- Multi-contact support: vCard files containing multiple contacts are fully supported

### Changed
- Import settings page now has a tabbed interface to switch between import methods
- Import page shows helpful instructions for Google Contacts export

## [1.0.123] - 2026-01-04

### Changed
- Settings page: Moved Import functionality to a dedicated submenu page at `/settings/import`
- Settings page: Added "Data" section with link to Import page

### Removed
- Settings page: Removed "Account" section (profile link already in user menu)
- Settings page: Removed "Session" section with Log Out button (already in sidebar)

## [1.0.122] - 2026-01-04

### Changed
- Settings page: Administration and Configuration sections now only visible to administrators
- Relationship Types page: Non-admin users now see "Access Denied" message instead of the management interface
- About section: Now displays the actual theme version from style.css

### Added
- Disabled WordPress admin color scheme picker for all users
- Disabled application passwords for improved security

## [1.0.121] - 2026-01-04

### Fixed
- Dashboard statistics now respect access control: new users see only their own people/teams/dates counts, not totals from all users
- Fixed `wp_count_posts()` bypassing access control by using `get_accessible_post_ids()` for non-admin users

## [1.0.120] - 2026-01-04

### Added
- Relationships panel: Deceased people now show † symbol next to their name
- Family Tree: Deceased people now show † symbol next to their name
- Family Tree: Deceased people have muted gray styling (border, text, placeholder)

## [1.0.119] - 2026-01-04

### Added
- Stadion favicon now displays on the WordPress login page

## [1.0.118] - 2026-01-04

### Fixed
- Login button now properly uses Stadion amber colors (overrides WordPress defaults)
- Added more margin above the login button for better spacing

## [1.0.117] - 2026-01-04

### Added
- Custom Stadion-branded WordPress login page with amber theme colors
- Login page displays Stadion logo and name
- Users are redirected to homepage after successful login

## [1.0.116] - 2026-01-04

### Changed
- Event names now use full names instead of first names only (e.g., "Joost de Valk's Birthday" instead of "Joost's Birthday")
- Updated auto-title generation for important dates to use full names
- Updated Monica import to use full names for birthdays, special dates, and life events

## [1.0.115] - 2026-01-04

### Added
- AGENTS.md: Added production deployment instructions (Rule 5)
- AGENTS.md: Added production server details for automated deployments

## [1.0.114] - 2026-01-04

### Fixed
- Photo uploads now use properly named files based on person/team name instead of original filename
- New REST API endpoints: `/stadion/v1/people/{id}/photo` and `/stadion/v1/teams/{id}/logo/upload`
- Files are saved as `{sanitized-name}.{ext}` (e.g., `john-doe.jpg`) for consistent file paths

## [1.0.113] - 2026-01-04

### Changed
- Rebranded application from "Koinastra" to "Stadion" across all user-facing text
- Updated theme name, description, email notifications, and documentation

## [1.0.112] - 2026-01-04

### Added
- iCal calendar feed: Subscribe to important dates from any calendar app (Apple Calendar, Google Calendar, Outlook)
- iCal feed authentication: Secure token-based URLs for private calendar subscriptions
- Settings: Calendar subscription section with feed URL, copy button, and regenerate token option
- REST API endpoints: `/stadion/v1/user/ical-url` and `/stadion/v1/user/regenerate-ical-token`
- Clickable events: Calendar events link directly to the related person's detail page
- Recurring dates: Dates marked as recurring automatically repeat yearly in the feed

## [1.0.111] - 2024-12-19

### Changed
- Moved "View Family Tree" button to top right of profile header card
- Removed "View Family Tree" button from relationships card

## [1.0.110] - 2024-12-19

### Changed
- Family Tree: Disabled physics, manually reposition spouses after layout
- Family Tree: Spouses are now placed 120px apart (60px from center each)
- Family Tree: More reliable spouse positioning that doesn't conflict with hierarchical layout

## [1.0.109] - 2024-12-19

### Changed
- Family Tree: Much stronger spouse attraction - spouse edges 50px, parent-child 300px
- Family Tree: Spring constant increased to 0.2 (4x stronger)
- Family Tree: Node distance reduced to 100px to allow closer positioning
- Family Tree: 300 stabilization iterations for better settling

## [1.0.108] - 2024-12-19

### Changed
- Family Tree: Stronger spouse attraction - spouse edges 80px, parent-child edges 200px
- Family Tree: Increased spring constant (0.05) for stronger edge attraction
- Family Tree: Increased stabilization iterations (200) for better settling

## [1.0.107] - 2024-12-19

### Changed
- Family Tree: Enabled physics simulation to better position spouses next to each other
- Family Tree: Spouse edges have shorter preferred length (100px) to pull partners together
- Family Tree: Uses hierarchical repulsion to maintain levels while optimizing positions

## [1.0.106] - 2024-12-19

### Changed
- Family Tree: Nodes are now 1.5x bigger (size 45 instead of 30)
- Family Tree: Generation levels now calculated relative to the start person
- Family Tree: Parents at level -1, grandparents -2, children +1, etc.
- Family Tree: Spouses/partners correctly placed on same level via BFS traversal
- Family Tree: Increased spacing between nodes and levels for cleaner layout
- Family Tree: Changed sort method to 'hubsize' for better spouse positioning

## [1.0.105] - 2024-12-19

### Changed
- Family Tree: All nodes now same size (placeholder with initials for people without photos)
- Family Tree: All lines are now straight instead of curved
- Family Tree: Spouses/partners are now on the same level (generation)
- Family Tree: Labels always appear below the circle

## [1.0.104] - 2024-12-19

### Added
- Family Tree: Partner relationships now also shown as spouse connections

## [1.0.103] - 2024-12-19

### Added
- Family Tree: Spouse and lover relationships now shown with pink dashed lines
- Family Tree: Spouses/lovers appear connected horizontally in the tree

## [1.0.102] - 2024-12-19

### Changed
- Family Tree: Switched from react-d3-tree to vis.js (vis-network) for visualization
- Family Tree: Now properly supports multiple parents per child (true family tree structure)
- Family Tree: Hierarchical layout with parents above children
- Family Tree: Interactive zoom, pan, and click-to-navigate functionality
- Family Tree: Nodes show name, gender symbol, age, and birth date

### Added
- vis-network and vis-data packages for graph visualization

### Removed
- react-d3-tree package (replaced by vis.js)
- PersonNode component (vis.js handles node rendering)

## [1.0.101] - 2024-12-19

### Changed
- Family Tree: Reverted to individual person nodes (removed couple merging)
- Family Tree: Simplified tree building - shows primary lineage from eldest ancestor
- Family Tree: Removed virtual root - single tree from eldest ancestor down

### Fixed
- Family Tree: No more empty node at top of tree
- Family Tree: Each person shown as individual node (no incorrect parent-child relationships)

### Known Limitations
- Due to react-d3-tree's single-parent hierarchy, children connect to only one parent
- The tree shows the primary lineage; other parents appear but children only connect once

## [1.0.100] - 2024-12-19

### Changed
- Family Tree: Parents who share children are now shown as couples ("Person & Partner")
- Family Tree: Couple nodes show both photos side-by-side
- Family Tree: Properly hides virtual root node when multiple lineages exist
- Family Tree: Children branch from the couple instead of individual parents

### Fixed
- Family Tree: Both parents now appear in the tree (as a couple unit)
- Family Tree: Virtual root no longer shows as empty node

## [1.0.99] - 2024-12-19

### Fixed
- Family Tree: Fixed inverted getParents/getChildren logic
- Family Tree: Relationship type describes WHO the neighbor is (not the person's role)
- Family Tree: If person has "parent" relationship to neighbor, neighbor IS their parent
- Family Tree: Tree now correctly shows parents above children

## [1.0.98] - 2024-12-19

### Changed
- Family Tree: Complete rewrite of tree building algorithm with clear two-phase approach
- Family Tree: Phase 1 collects all relevant family members (ancestors + their siblings, descendants)
- Family Tree: Phase 2 builds tree from root ancestors downward
- Family Tree: Clean helper functions (getParents, getChildren, getSiblings, findRoots)
- Family Tree: Properly handles multiple lineages with virtual root
- Family Tree: Each person included only once in tree
- Family Tree: Removed complex legacy logic and excessive comments

## [1.0.97] - 2024-12-19

### Fixed
- Family Tree: Fix adjacency list to use correct inverse relationship types
- Family Tree: When edge is "parent", reverse edge should be "child" (and vice versa)
- Family Tree: Fix tree hierarchy so parents appear above children (not inverted)
- Family Tree: Ensure siblings are correctly identified as children of same parent

## [1.0.96] - 2024-12-19

### Fixed
- Family Tree: Refactor findUltimateAncestor to collect ALL ancestors and find eldest by birth date
- Family Tree: Use BFS traversal to find all ancestors (not just those with no parents)
- Family Tree: Ensure current person is included in tree and verify inclusion
- Family Tree: Tree now flows from eldest ancestor (top) down to current person and all descendants

## [1.0.95] - 2024-12-19

### Fixed
- Family Tree: Find oldest ancestor by birth date when multiple people have no parents
- Family Tree: Include siblings in tree visualization (children of same parents)
- Family Tree: Sort siblings by birth date (oldest first)
- Family Tree: Ensure tree flows from oldest (top) to youngest (bottom)

## [1.0.94] - 2024-12-19

### Fixed
- Family Tree: Auto-center and auto-zoom tree on initial render
- Family Tree: Remove blue connector dots, center nodes on connector points
- Family Tree: Fix children ordering - ensure oldest appears first (leftmost)
- Family Tree: Position nodes so connector lines connect to center-top of cards

## [1.0.93] - 2024-12-19

### Changed
- Family Tree: Switched back to react-d3-tree from react-family-tree
- Family Tree: Rewrote TreeVisualization component to use react-d3-tree
- Family Tree: Updated PersonNode to work with foreignObject rendering
- Family Tree: Configured vertical orientation with proper spacing
- Family Tree: Added zoom and pan controls

## [1.0.92] - 2024-12-19

### Fixed
- Family Tree: Center nodes on connector points by offsetting by half node size
- Family Tree: Add padding to container so connectors above nodes are visible
- Family Tree: Ensure connecting lines align with center of person blocks

## [1.0.91] - 2024-12-19

### Fixed
- Family Tree: Separate node display size from spacing (160x100 display, 220x140 spacing)
- Family Tree: Increase spacing between nodes for visible connecting lines
- Family Tree: Reverse children order to compensate for library's rendering order
- Family Tree: Ensure oldest person appears at top of tree

## [1.0.90] - 2024-12-19

### Fixed
- Family Tree: Sort children by birth date (oldest first) in tree builder
- Family Tree: Increase node dimensions (180x120) for better spacing and visibility
- Family Tree: Sort children when building relationships to ensure correct order

## [1.0.89] - 2024-12-19

### Fixed
- Family Tree: Fix node positioning using transform translate instead of absolute positioning
- Family Tree: Library calculates positions using left/top multiplied by half dimensions
- Family Tree: Add absolute positioning class to PersonNode for proper rendering

## [1.0.88] - 2024-12-19

### Fixed
- Family Tree: Prevent duplicate relations in parents/children/siblings arrays
- Family Tree: Added comprehensive validation before rendering
- Family Tree: Create deep copy of nodes array for immutability
- Family Tree: Added detailed logging of node structure for debugging
- Family Tree: Better error handling when root node not found

## [1.0.87] - 2024-12-19

### Fixed
- Family Tree: Filter relations to only include IDs that exist in nodes array
- Family Tree: Prevent library errors from referencing non-existent node IDs
- Family Tree: Added validation to ensure all relation IDs are valid
- Family Tree: Added debugging logs to help diagnose issues

## [1.0.86] - 2024-12-19

### Fixed
- Family Tree: Added comprehensive validation and normalization of node structure
- Family Tree: Ensure all relation objects have valid id and type properties
- Family Tree: Filter out any invalid nodes before passing to library
- Family Tree: Normalize all IDs to strings in relations

## [1.0.85] - 2024-12-19

### Fixed
- Family Tree: Fixed "Cannot read properties of undefined (reading 'find')" error
- Family Tree: Ensure all arrays (parents, children, siblings) are always initialized
- Family Tree: Added defensive checks to prevent undefined array access
- Family Tree: Final validation pass to ensure all nodes have required arrays

## [1.0.84] - 2024-12-19

### Fixed
- Family Tree: Convert IDs to strings (react-family-tree expects string IDs, not numbers)
- Family Tree: Added better error handling and logging for debugging
- Family Tree: Prevent duplicate nodes in flat nodes array
- Family Tree: Improved validation of node structure before processing

## [1.0.83] - 2024-12-19

### Fixed
- Family Tree: Fixed JavaScript error "Cannot read properties of undefined (reading 'length')"
- Family Tree: Added proper null/undefined checks in tree traversal
- Family Tree: Ensure children array always exists (even if empty) for react-family-tree compatibility

## [1.0.82] - 2024-12-19

### Changed
- Family Tree: Switched from react-d3-tree to react-family-tree library
- Family Tree: Now correctly displays oldest ancestors at top, youngest descendants at bottom
- Family Tree: Updated TreeVisualization and PersonNode components for new library API

## [1.0.81] - 2024-12-19

### Fixed
- Family Tree: Ensured tree builds downward from ultimate ancestor (oldest person)
- Family Tree: Ultimate ancestor (oldest, no parents) now appears at top of tree
- Family Tree: All descendants appear below, maintaining proper hierarchy

## [1.0.80] - 2024-12-19

### Fixed
- Family Tree: Fixed duplicate people appearing in tree
- Family Tree: Changed logic to traverse up to find ultimate ancestor, then build tree downward
- Family Tree: Prevents cycles and ensures each person appears only once

## [1.0.79] - 2024-12-19

### Fixed
- Family Tree: Simplified to only show parent/child relationships (ignores niece/nephew/aunt/uncle)
- Family Tree: Fixed hierarchy - parents now correctly appear above root person
- Family Tree: Fixed multiple parents display - all parents now show as siblings at top level
- Family Tree: Corrected relationship direction logic

## [1.0.78] - 2024-12-19

### Fixed
- Family Tree: Fixed hierarchy display - parents now appear above root, children below
- Family Tree: Fixed name truncation by increasing node width and using break-words
- Family Tree: Fixed relationship direction logic (child relationship means parent of root)
- Family Tree: Improved tree structure to show proper up/down hierarchy

## [1.0.77] - 2024-12-19

### Changed
- Family Tree: Increased person node size to fully display gender icon
- Family Tree: Added date of birth display in dd-mm-yyyy format on person nodes
- Family Tree: Improved node spacing and layout

## [1.0.76] - 2024-12-19

### Fixed
- Family Tree: Fixed "Unknown" node names by properly extracting person names from various data formats
- Family Tree: Fixed relationship parsing to handle REST API expanded relationship format (relationship_slug field)
- Family Tree: Improved age calculation from birth_date field
- Family Tree: Added better error handling and debugging

## [1.0.75] - 2024-12-19

### Added
- Family Tree visualization: New family tree feature to visualize family relationships
- Family Tree page: Accessible from person detail page, shows hierarchical family tree
- Tree visualization component: Interactive tree with zoom, pan, and node navigation
- Person nodes: Display person photos, names, ages, and gender symbols in tree
- Tree builder utilities: Builds family tree structure from relationship data
- Family relationship filtering: Automatically filters to show only family relationships (parent, child, sibling, etc.)

### Changed
- Person Detail page: Added "View Family Tree" button in Relationships section

## [1.0.74] - 2024-12-19

### Fixed
- JavaScript error: Fixed "data is not defined" error when saving relationships
- Gender-dependent inverse resolution: Fixed logic to correctly resolve aunt/uncle → niece/nephew based on related person's gender
- Inverse mapping: Aunt can now correctly map to either Niece or Nephew depending on the related person's gender

### Changed
- Gender resolution: When source type is gender-dependent (aunt/uncle), inverse is resolved to target group (niece/nephew) based on related person's gender

## [1.0.73] - 2024-12-19

### Added
- Default relationship configurations: System now ships with pre-configured inverse mappings and gender-dependent settings
- Restore defaults button: Added "Restore Defaults" button in Relationship Types settings page
- REST API endpoint: `/stadion/v1/relationship-types/restore-defaults` to restore default configurations
- Automatic setup: Default configurations are applied when relationship types are first created

### Changed
- Relationship type initialization: Now automatically sets up inverse mappings and gender-dependent groups on first run

## [1.0.72] - 2024-12-19

### Added
- Gender-dependent relationship types: Support for gender-aware inverse relationship resolution
- ACF fields: Added `is_gender_dependent` and `gender_dependent_group` fields to relationship types
- Automatic gender resolution: System automatically resolves gender-dependent types (e.g., aunt/uncle → niece/nephew) based on related person's gender
- Helper functions: `resolve_gender_dependent_inverse()`, `get_types_in_gender_group()`, `infer_gender_type_from_group()`

### Changed
- Inverse relationship sync: Now checks for gender-dependent types and resolves to correct specific type
- Relationship type configuration: Can now mark types as gender-dependent and assign them to groups

## [1.0.71] - 2024-12-19

### Added
- Documentation: Created comprehensive docs/ folder with relationship system documentation
- docs/relationships.md: Complete guide to how bidirectional relationships work
- docs/relationship-types.md: Configuration guide for relationship types and inverse mappings
- docs/architecture.md: Technical architecture documentation with extension points
- README.md: Added links to documentation

## [1.0.70] - 2024-12-19

### Changed
- Relationship Types page: Inverse relationship type selector is now searchable dropdown
- Relationship Types page: Inverse selector includes the type itself (e.g., "Acquaintance" can have "Acquaintance" as inverse)
- Relationship Types page: Improved UX with searchable dropdown similar to person selector

## [1.0.69] - 2024-12-19

### Added
- Settings: New Relationship Types management page accessible from Settings
- Relationship Types page: Edit relationship type names and inverse relationships from the frontend
- Relationship Types page: Create new relationship types with inverse mappings
- Relationship Types page: Delete relationship types
- REST API: Added ACF field support for relationship_type taxonomy terms

## [1.0.68] - 2024-12-19

### Changed
- Inverse relationships: Moved inverse relationship mappings from hardcoded PHP array to ACF taxonomy field
- Relationship types now have an "Inverse Relationship Type" field that can be configured in WordPress admin
- Removed hardcoded `$inverse_mappings` array from `STADION_Inverse_Relationships` class

## [1.0.67] - 2024-12-19

### Fixed
- Monica import: Gender field is now properly imported from Monica CRM SQL exports (maps M→male, F→female, O→prefer_not_to_say)

## [1.0.66] - 2024-12-19

### Added
- Person form: Added gender field with dropdown selection (Male, Female, Non-binary, Other, Prefer not to say)
- Person detail: Gender symbol (♂/♀/⚧) now displays left of age
- Relationships: Automatic bidirectional relationship synchronization - when a relationship is created/updated/deleted from person A to person B, the inverse relationship is automatically created/updated/deleted from B to A
- Inverse relationship mappings for all relationship types (e.g., Parent ↔ Child, Boss ↔ Subordinate, Spouse ↔ Spouse)

### Changed
- Person form: Gender field changed from text input to select dropdown
- Cache invalidation: Related person cache is now invalidated when relationships are updated, ensuring UI reflects inverse relationships immediately

## [1.0.65] - 2024-12-19

### Changed
- Person detail: Contact information section is now hidden for deceased people

## [1.0.64] - 2024-12-19

### Added
- People list: Deceased people now show † next to their name

## [1.0.63] - 2024-12-19

### Added
- Person detail: For deceased people, shows † next to their name
- Person detail: Displays death date and age at death instead of current age for deceased people
- Person detail: "Died" date type now displays † as its icon

## [1.0.62] - 2024-12-19

### Changed
- Teams list: Teams are now sorted alphabetically by name

## [1.0.61] - 2024-12-19

### Changed
- Person detail: Relationships are now sorted by age (descending - oldest first)

## [1.0.60] - 2024-12-19

### Fixed
- Team form: Fixed `getTeam` API method to accept params (including `_embed`), ensuring logos display on team list and in work history
- Team form: Added explicit query refetching after logo upload to ensure embedded media data is refreshed

## [1.0.59] - 2024-12-19

### Fixed
- Team form: Created custom REST endpoint to set team logo using WordPress `set_post_thumbnail()` function, ensuring featured image is properly saved

## [1.0.58] - 2024-12-19

### Fixed
- Team form: Fixed logo upload payload structure - featured_media now properly saved

## [1.0.57] - 2024-12-19

### Fixed
- Team detail: Logo now properly loads and displays on team detail page

## [1.0.56] - 2024-12-19

### Added
- Team form: Logo upload functionality when editing a team
- Person detail: Team logos now displayed in work history section instead of generic icon

## [1.0.55] - 2024-12-19

### Changed
- Dates overview: Today's dates now display in green (matching dashboard reminders)
- Dates overview: Removed days-until indicators, showing only the date number

## [1.0.54] - 2024-12-19

### Added
- Favicon: Added sparkles favicon (SVG) to match the app branding

## [1.0.53] - 2024-12-19

### Changed
- Layout: Changed sidebar logo icon from Home to Sparkles

## [1.0.52] - 2024-12-19

### Changed
- Rebranded application from "Oikos" to "Koinastra"
- Centralized app name configuration in `src/constants/app.js` for easy future changes
- All app name references now use the centralized `APP_NAME` constant

## [1.0.51] - 2024-12-19

### Fixed
- Date form: Prevented form reset from clearing date type selection after user selects a value
- Date form: Added form initialization tracking to prevent unwanted resets

## [1.0.50] - 2024-12-19

### Fixed
- Date form: Date type select now properly updates when selecting a value

## [1.0.49] - 2024-12-19

### Changed
- Person detail: Important dates are now ordered by date ascending (earliest first)

## [1.0.48] - 2024-12-19

### Added
- Layout: Added home icon next to Oikos title in the sidebar

## [1.0.47] - 2024-12-19

### Changed
- Design: Switched color palette from blue to warmer amber tones throughout the application

## [1.0.46] - 2024-12-19

### Changed
- Rebranded application from "Personal CRM" to "Oikos" across all user-facing text
- Updated logo, welcome message, document titles, and email reminders

## [1.0.45] - 2024-12-19

### Added
- User menu: Shows current user's avatar with dropdown menu
- User menu: "Edit profile" link to WordPress user profile page
- User menu: "WordPress admin" link (only visible for admin users)
- Backend: New REST endpoint `/stadion/v1/user/me` to get current user information

## [1.0.44] - 2024-12-19

### Changed
- Dashboard: Upcoming reminders happening today now display in green instead of red

## [1.0.43] - 2024-12-19

### Added
- Person form: Email field when creating a new person
- Person form: Automatically fetches and sets Gravatar profile photo if email has a Gravatar
- Backend: New REST endpoint to sideload Gravatar images for people

## [1.0.42] - 2024-12-19

### Fixed
- Date form: People selector now shows all people, not just the first 100
- Date form: Uses pagination-aware usePeople hook instead of limited direct query

## [1.0.41] - 2024-12-19

### Added
- Person detail: Click on person's photo to upload/change their picture
- Person detail: Photo upload with file validation (image files only, max 5MB)
- Person detail: Loading indicator during photo upload

## [1.0.40] - 2024-12-19

### Added
- Work history: Can now set both "current job" and a future end date simultaneously
- Work history: Daily cron job automatically sets is_current=false when end_date passes

### Changed
- Work history form: End date field is no longer disabled when "current job" is checked
- Work history form: End date can be set even for current positions to schedule automatic transition

## [1.0.39] - 2024-12-19

### Fixed
- Team detail: Employees with end dates in the future are now correctly shown as current employees, not former

## [1.0.38] - 2024-12-19

### Fixed
- Person form: Now properly sets post title when creating or updating a person
- Person form: Data storage now works correctly

### Added
- Person form: Birthday field when creating a new person
- Person form: Automatically creates an important_date post for birthday when provided

## [1.0.37] - 2024-12-19

### Added
- People list: Sort controls to sort by first name or last name, ascending or descending
- People list: Default sorting is now first name ascending (changed from last name)

### Changed
- People list: Sorting now uses a dropdown selector and order toggle button

## [1.0.36] - 2024-12-19

### Changed
- Relationship form: Related Person field now uses a searchable dropdown instead of a simple select
- Relationship form: People are sorted alphabetically by first name, ascending
- Relationship form: Search works by name, first name, or last name

## [1.0.35] - 2024-12-19

### Changed
- Person detail: Work history is now sorted by start date descending (most recent first)
- Person detail: Current positions appear at the top of the work history list

## [1.0.34] - 2024-12-19

### Fixed
- Person detail: HTML entities in relationship names and labels are now properly decoded and displayed

## [1.0.33] - 2024-12-19

### Fixed
- Relationship form: Relationship type dropdown now correctly pre-selects the current relationship type when editing

## [1.0.32] - 2024-12-19

### Fixed
- Relationship form: Relationship type dropdown now shows all available types (increased limit from 10 to 100)

## [1.0.31] - 2024-12-19

### Fixed
- Person detail: Edit and Add Relationship buttons now navigate to dedicated relationship form instead of person edit form
- Person detail: Relationship form allows editing individual relationships without editing the entire person

### Added
- Person detail: New RelationshipForm component for adding and editing relationships independently

## [1.0.30] - 2024-12-19

### Added
- Person detail: Add and remove labels directly from the person detail page
- Person detail: Remove button (X) appears on hover for each label
- Person detail: Dropdown selector to add new labels from available labels

## [1.0.29] - 2024-12-19

### Changed
- Person detail: LinkedIn contact types now show LinkedIn icon instead of label text
- Person detail: LinkedIn icon styled with brand color (blue-600)

## [1.0.28] - 2024-12-19

### Changed
- Team detail: Removed person-level access control restriction - if you can view a team, you can see all its employees
- Team detail: Now checks team access instead of filtering employees by person-level access permissions

## [1.0.27] - 2024-12-19

### Fixed
- Team detail: Fixed team people query by removing unreliable meta_query with ACF repeater fields and filtering in PHP instead
- Team detail: Now properly finds people by checking work_history using ACF's get_field() function which handles repeater fields correctly

## [1.0.26] - 2024-12-19

### Fixed
- Team detail: Fixed bug where employees weren't showing due to missing admin check in access control filtering
- Team detail: Fixed type comparison issue between team IDs (string vs integer) that prevented matching work history entries

## [1.0.25] - 2024-12-19

### Changed
- Performance: Optimized team people query to apply access control filtering early, reducing query scope
- Performance: People list now batches team fetches into a single API call instead of individual queries (fixes N+1 query problem)
- Performance: Made `get_accessible_post_ids` method public in access control class for reuse in optimized queries

## [1.0.24] - 2024-12-19

### Changed
- Team detail: Reorganized employee display into two separate sections: "Current Employees" and "Former Employees"
- Team detail: Each section now has its own card for better visual separation
- Team detail: Improved empty states for both current and former employee sections

## [1.0.23] - 2024-12-19

### Changed
- People list: Now fetches all people using pagination (removed 100 person limit)
- People list: Added lazy loading for person thumbnails to improve page load performance
- Dashboard: Added lazy loading for person thumbnails in Recent People and Reminders sections
- Dates list: Added lazy loading for person thumbnails
- Team detail: Added lazy loading for employee thumbnails

## [1.0.22] - 2024-12-19

### Added
- People list: Filter functionality with favorites toggle and label multi-select
- People list: Active filter chips showing applied filters with quick remove options
- People list: Filter button shows badge count when filters are active
- People list: "No results" state when filters don't match any people

## [1.0.21] - 2024-12-19

### Removed
- Removed duplicate search box from People list page (global search in top bar is sufficient)

## [1.0.20] - 2024-12-19

### Changed
- Dashboard: Upcoming reminders now link to the related person's detail page when clicked
- Dashboard: Increased reminder photo size from 6x6 to 10x10 pixels for better visibility

## [1.0.19] - 2024-12-19

### Added
- Added WhatsApp button for mobile phone numbers in contact details (opens WhatsApp with the phone number)

## [1.0.18] - 2024-12-19

### Fixed
- Fixed tel: links for phone numbers: now properly removes Unicode marks and all non-digit characters (except + at the start)

## [1.0.17] - 2024-12-19

### Fixed
- Changed ACF repeater fields to use empty arrays `[]` instead of `null` when empty, as WordPress REST API requires arrays

## [1.0.16] - 2024-12-19

### Fixed
- Fixed error when saving contact details: ACF repeater fields (work_history, relationships) are now properly formatted as arrays or null

## [1.0.15] - 2024-12-19

### Fixed
- Person names now properly decode HTML entities (e.g., &#8211; displays as a normal dash)

## [1.0.14] - 2024-12-19

### Changed
- People list: People are now sorted alphabetically by last name (with first name as secondary sort)
- People list: Team name is now displayed below each person's name (shows current team or most recent)

## [1.0.13] - 2024-12-19

### Added
- Contact information: Website and URL type contacts (LinkedIn, Twitter, Instagram, Facebook) are now clickable links opening in a new tab
- Contact information: Address type contacts now link to Google Maps opening in a new tab

## [1.0.12] - 2024-12-19

### Fixed
- Date type dropdown now fetches all date types (increased limit from 10 to 100)

## [1.0.11] - 2024-12-19

### Changed
- Date type dropdown now properly fetches and displays all date types from the Date Types taxonomy
- Date types are sorted alphabetically for better user experience

## [1.0.10] - 2024-12-19

### Changed
- Removed "Anniversary" date type, replaced with "Wedding" date type
- Wedding date type now auto-generates title as "Wedding of <person 1> & <person 2>" format
- Updated auto-title generation logic to handle wedding dates with proper format

## [1.0.9] - 2024-12-19

### Changed
- Person detail page: Contact detail edit button now navigates to dedicated contact detail edit form instead of person edit screen
- Person detail page: "Add contact detail" button now navigates to dedicated contact detail form instead of person edit screen

### Added
- Added dedicated Contact Detail form page for adding and editing individual contact details

## [1.0.8] - 2024-12-19

### Changed
- Person detail page: Work history edit button now navigates to dedicated work history edit form instead of person edit screen
- Added dedicated Work History form page for editing individual work history items

## [1.0.7] - 2024-12-19

### Changed
- Person detail page: Work History section now always visible (even when empty)
- Person detail page: Team names now displayed instead of "View Team" link in work history
- Person detail page: Added "Add Work History" button to Work History section

### Added
- Person detail page: Added edit button for each work history item
- Person detail page: Added delete button for each work history item

## [1.0.6] - 2024-12-19

### Added
- Person detail page: Added delete button for each contact detail
- Person detail page: Added delete button for each important date
- Person detail page: Added delete button for each relationship
- Person detail page: Added delete button for each note/timeline item
- Person detail page: Added edit button for each relationship

## [1.0.5] - 2024-12-19

### Changed
- Person detail page: Show person labels underneath age in the main card
- Person detail page: Removed birthday from main card, now appears as first date in Important Dates card
- Person detail page: Contact Information card now always visible with "Add contact detail" button
- Person detail page: Email fields are now clickable mailto: links
- Person detail page: Phone numbers are now clickable tel: links (spaces and dashes removed)
- Person detail page: Added edit button for each contact field
- Person detail page: Important Dates card now always visible with "Add Important Date" button
- Person detail page: Relationships card now always visible with "Add Relationship" button

## [1.0.4] - 2024-12-19

### Fixed
- Fixed 404 errors when navigating to individual person, team, or date pages
- WordPress now properly serves index.php for all app routes, allowing React Router to handle routing
- Disabled rewrite rules for custom post types to prevent URL conflicts
- Fixed React error #310 ("Rendered more hooks than during the previous render") by removing `useParams()` from `useRouteTitle` hook
- `useRouteTitle` now extracts route IDs from pathname instead of using `useParams()`, ensuring consistent hook calls regardless of route context
- Fixed React error #310 on Person Detail pages by moving `useDocumentTitle` hook calls before early returns in all detail and form components
- All hooks are now called consistently on every render, even during loading/error states
- Fixed "Rendered more hooks than during the previous render" error by ensuring useSearch always receives a string (never null)
- Fixed minified React error caused by improper handling of empty search queries
- Added safety checks for search results to prevent property access errors
- Note: After updating, you may need to flush rewrite rules by going to Settings > Permalinks and clicking "Save Changes"

## [1.0.3] - 2024-12-19

### Fixed
- Search form now works and displays results in a dropdown
- Search form is now center-aligned in the header
- User menu placeholder is now right-aligned

## [1.0.2] - 2024-12-19

### Fixed
- Page title now updates dynamically based on current route instead of always showing "Page not found"
- Document title now shows appropriate page names (Dashboard, People, Teams, etc.) and entity names for detail pages

## [1.0.1] - 2024-12-19

### Changed
- Important Dates overview now uses masonry layout for date blocks
- Increased month heading size on Important Dates overview screen
