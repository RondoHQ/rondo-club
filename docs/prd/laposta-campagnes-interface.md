# Nieuwsbriefeditor: klikbaar ontwerp

Status: ontwerp voor beoordeling, geen productiefunctionaliteit. De gebruiker koos op 1 oktober 2026 een apart bewerkscherm vanuit het communicatie-item, met invoer links en een direct bijgewerkt voorbeeld rechts.

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

Dit is een uitbreiding binnen de bestaande Rondo-identiteit. Er zijn geen nieuwe duurzame ontwerpregels vastgesteld; `PRODUCT.md`, `DESIGN.md` en `.impeccable/design.json` zijn ongewijzigd gebleven. Er is niets naar productie gedeployd.
