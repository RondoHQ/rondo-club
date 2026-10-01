# Nieuwsbriefeditor: klikbaar ontwerp

Status: goedgekeurd ontwerp geïmplementeerd in Rondo. De gebruiker koos op 1 oktober 2026 een apart bewerkscherm vanuit het communicatie-item, met invoer links en een direct bijgewerkt voorbeeld rechts. De onderstaande prototypecontrole blijft afzonderlijk van de productiecontrole onderaan.

## Direction contract

**THESIS:** de verantwoordelijke bepaalt de ondertekening in één vaste template; de maker ziet het resultaat tijdens het schrijven. Geen templatekiezer.

**OWN-WORLD:** bestaande Rondo-opmaak: Montserrat-koppen, systeemletters voor invoer, neutrale vlakken, dunne grijze randen en cyanacties. Het mailvoorbeeld behoudt de groene AWC-huisstijl.

**STORY:** kies verantwoordelijke en expliciete lijst/segmentcombinaties, schrijf, controleer, maak een concept. Ontvangers en verzending worden vervolgens in Laposta gecontroleerd. Een export rondt het communicatiekanaal niet af.

**FIRST VIEWPORT:** compacte Rondo-header en itemcontext boven twee kolommen. Links staan afzender en doelgroep vóór inhoud; rechts staat een inboxvoorbeeld boven de mail. Een vaste actiebalk biedt opslaan en controleren. Op mobiel wisselt de gebruiker tussen bewerken en voorbeeld.

**FORM:** door de gebruiker vastgelegde lokale uitbreiding; geen conceptloting. Klikbaar HTML-prototype, geen nieuwe visuele identiteit of applicatieroute. Profielwisseling is de centrale interactie: afzender, antwoordadres, naam, rol en handtekening veranderen samen.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Grenzen

- Voorbeeldinhoud en doelgroepkeuzes; geen ontvangersaantallen en geen API-verzoeken. Export en opslaan zijn herkenbare demonstraties; opslaan gebruikt alleen browseropslag.
- Joost en Xander gebruiken de gecontroleerde profielen uit de technische proef. Een onvolledig voorbeeldprofiel maakt de blokkade zichtbaar.
- Eén of meer lijsten met per lijst één segment of expliciet de hele lijst. Wijzigingen vereisen opnieuw controleren.
- Desktop, mobiel, toetsenbord, validatie, lokaal concept, controle en gesimuleerd exportresultaat behoren bij het ontwerp. Productierechten, gelijktijdigheid en herstel bij API-fouten horen bij de implementatie.
- Bestaande PRODUCT.md, DESIGN.md en de ontwerp-sidecar blijven intact. De bestaande contextdocumenten hebben oudere metadata; een afzonderlijke `init`/`document`-beurt kan die later bijwerken.

## Bestanden

Het prototype staat in `evidence/laposta-campagnes/interface/`. Alleen die map en de bestaande template/profielen zijn nodig. Het Rondo-logo is ongewijzigd overgenomen uit `public/icons/rondo-logo.png`; mailafbeeldingen worden geladen vanaf de reeds gecontroleerde Laposta-URLs in `base-template.html` en `profiles.json`.

## Ontwerpcontrole

Op 1 oktober 2026 zijn de prototypebron en de desktop-, mobiele en donkere schermafbeeldingen vergeleken met de bestaande Rondo-richting in `PRODUCT.md`, `DESIGN.md` en `src/index.css`: Montserrat-koppen, systeemletters voor velden, neutrale vlakken met dunne randen, cyanacties, een cyan-blauwe primaire knop en afgeronde velden (8px) en panelen (12px). De donkere weergave behoudt dezelfde hiërarchie; het mailvoorbeeld behoudt de groene AWC-template. De tweekolomseditor en de mobiele wissel tussen bewerken en voorbeeld zijn lokale compositiekeuzes voor dit scherm.

De afzonderlijke finish review gaf **SHIP**, zonder materiële bevindingen, voor het klikbare prototype op basis van broncode en schermafbeeldingen. Dit oordeel bewijst geen werkende productie-integratie, API-export, ontvangerscontrole of verzending; die blijven buiten deze ontwerpcontrole. De schermafbeeldingen staan onder `.impeccable/review/laposta-*.jpg`.

Dit is een uitbreiding binnen de bestaande Rondo-identiteit. Er zijn geen nieuwe duurzame ontwerpregels vastgesteld; `PRODUCT.md`, `DESIGN.md` en `.impeccable/design.json` zijn ongewijzigd gebleven. In deze prototypefase is niets naar productie gedeployd.

## Productie-implementatie

De React-editor staat op `/communicatie/planning/:id/nieuwsbrief`, bereikbaar na opslaan van een communicatie-item met het kanaal Nieuwsbrief. Instellingen en ondertekeningsprofielen staan op `/communicatie/nieuwsbrief-instellingen`. Conceptinhoud gebruikt native WordPress-velden; de server rendert hetzelfde HTML-document voor voorbeeld en export. De Laposta-client biedt uitsluitend conceptaanmaak en -wijziging. Export rondt het planningskanaal niet af.

De productiefunctie is vanaf 35.118.0 uitgerold; 35.118.1 maakt lokaal opslaan en voorvertonen ook zonder API-sleutel mogelijk. Op productie is testitem 12704 (`Nieuwsbrief-integratietest (niet versturen)`) opgeslagen, herladen en tussen Joost en Xander gewisseld. Afzender en handtekening wisselden mee. De mobiele editor en voorbeeldtab zijn op 390 × 844 pixels gecontroleerd, zonder horizontale overflow.

De lokale PHP-suite slaagde met 1.270 tests, 7.149 assertions en 22 skips. De nieuwsbriefsuite bevat 10 tests met 61 assertions, waaronder permissies, wijzigingsconflicten, expliciete doelgroepen, herstel na onbekende uitkomst, herhaalde export, externe wijzigingen en bescherming tegen wijzigen van geplande campagnes. JavaScript-lint, productiebuild en PHP-codingstandards slaagden. API-gedrag in deze producttests gebruikt gesimuleerde providerantwoorden; de eerdere technische proef is het afzonderlijke bewijs van echte Laposta-aanmaak.

De gedeelde template en vier profielen zijn ingesteld. Joost en Xander zijn actief met goedgekeurde afzendadressen. Guido en Jeroen blijven inactief tot hun afzendadres is gekozen. Er is geen API-sleutel in Rondo opgeslagen: hergebruik van de bestaande proefsleutel wacht op een expliciete keuze. De productieroute voor export is daarom nog niet live getest. Het testitem heeft geen doelgroep of gekoppelde Laposta-campagne; er is niets verzonden of ingepland.

De technische beheerdocumentatie staat in [developer PR 75](https://github.com/RondoHQ/developer/pull/75).

Versie 35.118.2 herstelt de initialen en afzenderidentiteit boven het inboxvoorbeeld, toont het antwoordadres tijdens bewerken en markeert het gekozen voorbeeldformaat zichtbaar. De volledige CI- en deployworkflow [36906729724](https://github.com/RondoHQ/rondo-club/actions/runs/36906729724) slaagde. Deze elementen zijn daarna op productie opnieuw vastgelegd in `.impeccable/review/newsletter-production-desktop.jpg`, `newsletter-production-mobile.jpg` en `newsletter-production-mobile-preview.jpg`. Dezelfde onafhankelijke reviewer beoordeelde beide eerdere bevindingen als **resolved**, met disposition **ship** uitsluitend voor die twee correcties.

De afsluitende ontwerpdocumentatiecontrole vergeleek de twee productpagina's, de gedeelde rich-text-editor en `src/index.css` met `PRODUCT.md`, `DESIGN.md` en `.impeccable/design.json`. Er zijn geen nieuwe duurzame systeemregels; deze bestanden blijven ongewijzigd. Oudere dashboardgerichte metadata en een bestaande afwijking in de kleur van secundaire knoppen zijn niet als nieuwe ontwerpregels vastgelegd of buiten de opdracht gerepareerd.
