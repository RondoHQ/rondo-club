# PRD: Laposta-campagnes vanuit Rondo

**Status:** Technische proef uitgevoerd; klikbaar interfaceontwerp beschikbaar voor beoordeling; productfunctie nog niet geïmplementeerd

**Datum:** 1 oktober 2026

**Product:** Rondo Club, uitbreiding van Communicatie → Planning

## 1. Doel en gekozen richting

Een nieuwsbrief voorbereiden vanuit een communicatie-item in Rondo: verantwoordelijke kiezen, doelgroep selecteren, inhoud schrijven en een gevuld concept in Laposta aanmaken.

We gebruiken **één basistemplate**. De verantwoordelijke bepaalt de naam, functie, handtekening, afzender en het antwoordadres. Er komt geen templatekeuze per persoon. Deze richting is door de gebruiker gekozen; de verdere productregels hieronder zijn voorstellen voor implementatie.

De eerste versie eindigt bij een controleerbaar concept in Laposta. Verzenden en inplannen gebeuren daar. Een geplande datum, statuswijziging of herhaling in Rondo verstuurt niets en maakt niet automatisch een campagne aan.

## 2. Gecontroleerde uitgangssituatie

- In Laposta staan vier voorbereide drag-and-drop-sjablonen: Guido Ronnes (voorzitter), Jeroen Pots (bestuurslid Accommodatie), Xander Notté (penningmeester) en Joost de Valk (secretaris). De gebruiker bevestigt dat dit één opmaak met wisselende ondertekening is.
- De vier sjablonen bieden HTML-downloads. Onder Op maat stonden tijdens de inspectie geen eigen HTML-sjablonen. De exports van Joost en Xander zijn inmiddels onderzocht; één gedeelde template is via de API als concept geïmporteerd en tussen beide profielen gewisseld. Zie [de proefresultaten](evidence/laposta-campagnes/README.md).
- De previews bevatten een titel, persoonlijke aanhef, ondertekening en sociale links. Bij alle vier heet de alternatieve tekst van de handtekeningafbeelding nog “Handtekening van Joost de Valk”; de juiste afbeeldingen en alternatieve teksten moeten bij overname afzonderlijk worden gecontroleerd.
- De openbare [Laposta API-documentatie](https://api.laposta.nl/doc/index.nl.php#campaigns) beschrijft het aanmaken en wijzigen van campagnes, het kiezen van lijsten/segmenten en het vullen met HTML. Er is geen gedocumenteerde templatekeuze. Drag-and-drop-campagnecontent kan niet via het content-leesendpoint worden opgehaald.
- Rondo heeft al communicatie-items, verantwoordelijken, opmerkingen, herhalingen en een checklist per kanaal. De huidige code ondersteunt meerdere kanalen per item; dat gaat verder dan de oorspronkelijke tekst van [Communicatieplanning](communicatieplanning.md).
- `rondo-sync` verzorgt al de Laposta-relatiesynchronisatie. Rondo Club heeft gedeelde Laposta-authenticatortoegang; die is geen API-campagnekoppeling.

Bronnen in de code: `includes/class-rest-communication.php`, `includes/config/field-registry.php`, `includes/class-club-config.php`, `src/pages/Communication/Planning.jsx`, `src/utils/communicationNavigation.js`, `includes/class-app-access.php` en `../rondo-sync/lib/laposta-client.js`.

## 3. Gebruikersflow

1. **Communicatie-item openen.** Bij een item met het actieve kanaal Nieuwsbrief verschijnt het onderdeel Nieuwsbrief. Andere kanalen behouden hun eigen inhoud en afhandeling.
2. **Verantwoordelijke kiezen.** Gebruik de bestaande verantwoordelijke. Rondo toont het gekoppelde ondertekeningsprofiel, inclusief afzend- en antwoordadres. Zonder compleet profiel kan het item wel als lokaal concept worden opgeslagen, maar niet naar Laposta.
3. **Doelgroep kiezen.** Kies bestaande Laposta-lijsten en per lijst expliciet de hele lijst of één bestaand segment. Toon de selectie in gewone taal. Geen vooraf geselecteerde hele lijst.
4. **Inhoud invullen.** Onderwerp, optionele previewtekst, titel en berichttekst met eenvoudige opmaak: alinea's, koppen, nadruk, links en lijsten. De vaste aanhef en ondertekening komen uit de renderer. De interne planningsbeschrijving blijft apart en wordt nooit automatisch mailingtekst.
5. **Voorbeeld controleren.** Toon desktop- en mobiele weergave, ondertekening, afzender en exacte lijst/segmentkeuze. Toon alleen aantallen en ontvangers die aantoonbaar bij deze selectie horen; vermeld het controlemoment.
6. **Concept aanmaken in Laposta.** Een expliciete knop maakt de campagne en vult de HTML. Na teruglezen van instellingen en content toont Rondo “Concept in Laposta aangemaakt”, de koppeling en het tijdstip.
7. **Afronden in Laposta.** Daar worden ontvangers, testmail en verzending gecontroleerd. Aanmaken in Laposta vinkt het nieuwsbriefkanaal in Rondo niet af. De bestaande handmatige afhandeling blijft beschikbaar.

## 4. Eén template, ondertekeningsprofielen per gebruiker

De basistemplate bevat clubhuisstijl, logo, inhoudsruimte, sociale links, webversielink en afmeldlink. De implementatie neemt één gecontroleerde HTML-export als uitgangspunt en vervangt alleen de bedoelde inhoud en profielvelden via benoemde placeholders.

| Profielveld | Regel |
|---|---|
| Gebruiker | Eén profiel gekoppeld aan een bestaande Rondo-gebruiker met communicatierecht |
| Naam en functie | Expliciet ingesteld; geen afleiding uit een rolnaam |
| Handtekening | Optionele afbeelding met passende alternatieve tekst; zonder afbeelding blijft de tekstuele ondertekening zichtbaar |
| Afzendernaam | Naam die in de inbox verschijnt |
| Afzendadres | In Laposta goedgekeurd afzendadres |
| Antwoordadres | Expliciet ingesteld, mag afwijken van de afzender |
| Actief | Inactieve profielen zijn niet bruikbaar voor nieuwe exports |

Beheerders beheren de clubtemplate, verbinding en profielen. Campagnemakers gebruiken de profielen van bevoegde verantwoordelijken; zij hoeven geen HTML te bewerken. Eén gedeeld afzendadres voor meerdere profielen is mogelijk. Namen en adressen worden niet hardgecodeerd voor AWC.

Een wissel van verantwoordelijke vernieuwt het getoonde profiel en maakt een eerdere voorbeeldcontrole ongeldig. Een al geëxporteerde campagne verandert alleen via een bewuste update. Sla bij iedere export een kopie op van het gebruikte profiel, de templateversie en de uiteindelijke HTML, zodat latere profielwijzigingen historische campagnes niet veranderen.

De HTML-export blijft geen drag-and-drop-template. Rondo is de editor voor nieuwe campagnes uit deze flow. Bestaande Laposta-sjablonen blijven beschikbaar.

## 5. Doelgroepen en bestaande synchronisatie

De eerste versie gebruikt bestaande Laposta-lijsten en segmenten. Nieuwe segmenten, selectie vanuit een willekeurig Rondo-personenfilter en het wijzigen van inschrijvingen vallen buiten deze fase.

- Lees lijsten en segmenten server-side uit Laposta. Controleer dat een gekozen segment bij de gekozen lijst hoort en nog bestaat.
- Bewaar lijst- en segment-ID's als de daadwerkelijke selectie; het bestaande vrije veld Doelgroep / bestemming blijft planningscontext en stuurt de API nooit aan.
- Een ontbrekend, verwijderd of ongeldig segment blokkeert export. Val nooit terug op de hele lijst.
- Controleer de teruggelezen campagne-instellingen op dezelfde lijst/segmentcombinaties, onafhankelijk van de campagnenaam.
- Laposta bepaalt de actuele inschrijvingsstatus. Campagne-export schrijft geen relaties, heractiveert niemand en start geen People-sync.
- De documentatie bevestigt geen endpoint voor een volledige, berekende segmentontvangerslijst of exact uniek campagnebereik. Onderzoek dit in de technische proef. Toon bij ontbrekend bewijs “Ontvangers controleren in Laposta”, geen totaalaantal van de bovenliggende lijst als segmentaantal.
- Bij meerdere lijsten moeten overlap en deduplicatie eerst worden geverifieerd voordat Rondo een uniek totaal presenteert. De eerste versie selecteert maximaal één segment per lijst, zodat geen ongeteste combinatie van segmenten wordt aangeboden.
- Segmenten kunnen tussen conceptaanmaak en verzending veranderen. Een eventuele momentopname is geen garantie voor het uiteindelijke bereik; de definitieve ontvangercontrole vindt in Laposta plaats.

## 6. Content en afbeeldingen

Render de beperkte berichtopmaak server-side naar e-mailgeschikte HTML. Sanitize ingevoerde content, escape profielvelden en behoud alleen expliciet ondersteunde Laposta-personalisatie. Verifieer aanhef en terugvaltekst voor alle geselecteerde lijsten.

Logo en handtekeningen moeten voor mailontvangers bereikbaar zijn. Gebruik daarvoor expliciet voor nieuwsbrieven bestemde media. Bestaande afgeschermde planningsbijlagen worden niet automatisch openbaar gemaakt of in de mail opgenomen. Vrije afbeeldingen in de berichttekst zijn een vervolgstap; vaste templateafbeeldingen en handtekeningen horen bij de eerste versie.

De technische proef controleert webversie- en afmeldtags, beeld-URL's, mobiele weergave en de importmeldingen van Laposta. Ontbrekende afbeeldingen of afmeldlinks blokkeren een succesvolle exportstatus totdat ze zijn opgelost. Gebruik een geïsoleerde voorbeeldweergave voor de gegenereerde HTML.

Uit de proef: geïmporteerde nieuwsbrief-HTML vereist `<unsubscribe>…</unsubscribe>` en `<webversion>…</webversion>`, zoals getoond in Laposta's importscherm. Drag-and-drop-links (`/tag/...`) en Joe-sjabloontags (`<lp-unsubscribe>`) leverden bij de contentimport een afmeldwaarschuwing op. De basistemplate bevatte ook een oude achtergrondafbeelding met HTTP 404; die referentie is alleen in de proefversie verwijderd. De uiteindelijke import is zonder meldingen geverifieerd.

## 7. Opslag, rechten en technische aansluiting

- Breid `rondo_comm_item` uit met nieuwsbriefvelden via de native field registry: onderwerp, previewtekst, titel, berichtinhoud, doelgroepselectie, gekoppeld Laposta-campagne-ID en exportgegevens. Gebruik de bestaande `assignee_id` voor de verantwoordelijke.
- Bewaar de koppeling bij de concrete keer van een communicatie-item. Kopiëren en het genereren van een volgende herhaling nemen geen campagne-ID, exportstatus of eerdere ontvangercontrole over.
- Bewaar profielen in gebruikersmetadata, configuratie in WordPress-options en auditgegevens in de bestaande communicatiehistorie. Geen eigen databasetabellen.
- Gebruik het bestaande communicatierecht voor itemacties en servercontrole op het specifieke item. Beheerders beheren de verbinding en profielen. Deze voorgestelde rechtenverdeling moet vóór implementatie worden bevestigd.
- Voeg een PHP Laposta-client toe met WordPress HTTP-functies, vaste API-host, timeouts, gemaskeerde fouten en verwerking van `429`/`Retry-After`. Gebruik `CredentialEncryption` voor een apart ingestelde API-sleutel; verstuur deze nooit naar de browser.
- Houd deze client gescheiden van de Node-client in `rondo-sync`: relatiesynchronisatie blijft daar, campagnebeheer komt in Rondo Club. Neem geen configuratie of credentials impliciet over uit de sync-installatie.
- Voorgestelde routes onder `/rondo/v1`: `GET /laposta/audiences`, `POST /communications/{id}/newsletter/preview` en `POST /communications/{id}/newsletter/export`. Breid bestaande itemroutes uit voor de lokale nieuwsbriefvelden. Definitieve contracten volgen bij implementatie.
- Gebruik het stabiele kanaal-ID `newsletter`, niet het aanpasbare kanaallabel. Voor clubs met een andere kanaalconfiguratie wordt de koppeling expliciet ingesteld.

## 8. Betrouwbaar aanmaken en bijwerken

Campagne aanmaken en content vullen zijn afzonderlijke API-stappen. Bewaar het ontvangen campagne-ID direct; bij een mislukte contentstap wordt dezelfde campagne hervat. Een lock per item en een lokale exportrevisie voorkomen dubbele acties bij dubbelklikken of gelijktijdig gebruik.

Bij een timeout tijdens aanmaken kan Laposta al een campagne hebben gemaakt. Gebruik een herkenbare unieke Rondo-referentie in de interne campagnenaam en zoek de uitkomst eerst terug. Zonder eenduidige uitkomst volgt een herstelmelding; niet blind opnieuw aanmaken. De openbare documentatie belooft geen idempotentiesleutel.

Statussen voor de koppeling staan los van de planningsstatus: lokaal concept, bezig, concept in Laposta, lokale wijzigingen, fout of controle nodig. Alleen na succesvolle terugleescontrole van instellingen en inhoud is de export geslaagd.

Lees vóór bijwerken de Laposta-campagne en content opnieuw. Vergelijk met de laatst geëxporteerde versie. Externe wijzigingen leiden tot een conflictmelding; geplande of verzonden campagnes worden niet overschreven. Een update vereist een nieuwe controle bij gewijzigde inhoud, profiel of doelgroep.

## 9. Uitvoering in drie fasen

1. **Technische proef:** één HTML-export onderzoeken, profielonderdelen scheiden, API-doelgroepinformatie en conceptbewerkbaarheid bevestigen. Vooraf testaccount of afgeschermde testlijst vastleggen; uitsluitend een concept aanmaken. API-resultaat én zichtbaar concept in Laposta controleren. Ontwerp daarna de Rondo-flow en laat de visuele richting beoordelen.
2. **Eerste release:** profielbeheer, één template, nieuwsbriefvelden binnen de bestaande planning, doelgroepkeuze, voorbeeld, conceptaanmaak en gecontroleerde updates inclusief foutafhandeling en historie.
3. **Verificatie en oplevering:** relevante PHP-tests, frontend-lint/build en PHP-codingstandaarden; daarna het normale commit-, CI- en deploymentproces en controle op productie. Werk bij implementatie de ontwikkelaarsdocumentatie bij voor communicatieplanning en Laposta.

Verzenden/inplannen vanuit Rondo, statistieken, Google Docs-import, een visuele templatebouwer en nieuwe doelgroepregels zijn vervolgstappen waarvoor apart scope wordt bepaald.

## 10. Acceptatiecriteria

| ID | Controleerbaar resultaat |
|---|---|
| AC-01 | De vier verantwoordelijken gebruiken één renderer; profiel wisselen verandert alleen de bedoelde ondertekening en afzendergegevens. |
| AC-02 | Een onvolledig profiel of ongeldig afzendadres geeft een concrete fout; er wordt geen willekeurige afzender gebruikt. |
| AC-03 | De gekozen lijst/segmentcombinaties komen exact terug in de Laposta-campagne; een ongeldig segment kan nooit de hele lijst selecteren. |
| AC-04 | Rondo presenteert alleen aantoonbare doelgroepaantallen, met controlemoment en zonder onbewezen overlapberekening. |
| AC-05 | Onderwerp, aanhef, inhoud, ondertekening, afbeeldingen, webversie en afmeldlink werken in het geïmporteerde concept en in een afzonderlijk geautoriseerde testmail. |
| AC-06 | Export maakt een ongevuld concept herstelbaar als contentimport faalt; dubbelklikken, timeouts en gelijktijdige verzoeken maken geen blinde duplicaten. |
| AC-07 | Externe wijzigingen worden herkend en geplande/verzonden campagnes worden niet overschreven. |
| AC-08 | Onbevoegde gebruikers kunnen nieuwsbriefvelden, profielen en exports niet via directe API-verzoeken benaderen; de sleutel blijft server-side. |
| AC-09 | Een duplicaat of nieuwe herhaling krijgt geen oude campagnekoppeling; historische HTML en profielen blijven intact. |
| AC-10 | Conceptaanmaak verzendt niets, vinkt geen kanaal af en verandert geen andere kanalen of Laposta-inschrijvingen. |
| AC-11 | Voorbeeld en geëxporteerde HTML gebruiken dezelfde renderer; desktop, mobiel en toetsenbordbediening worden gecontroleerd. |

## 11. Te bevestigen vóór bouwen

Het [klikbare interfaceontwerp](evidence/laposta-campagnes/interface/index.html) volgt de gekozen aparte editor met invoer links en direct voorbeeld rechts. Zie [ontwerpkeuzes](laposta-campagnes-interface.md) en [startinstructies](evidence/laposta-campagnes/README.md#klikbaar-interfaceontwerp). Het ontwerp gebruikt demonstratiegegevens en maakt geen echte campagnes aan.

Aanbevolen start: conceptaanmaak en bijwerken binnen de bestaande planning, doelgroepkeuze uit bestaande lijsten/segmenten en profielbeheer door beheerders. De gebruiker bevestigt deze scope vóór implementatie. Vervolgens worden de gebruiker-profielkoppelingen, afzendadressen en juiste handtekeningafbeeldingen vastgesteld.

De technische proef heeft één aparte lege testlijst, testvelden, een leeg segment en een niet-ingepland concept aangemaakt. Er zijn geen relaties toegevoegd of mails verstuurd; bestaande campagnes en sjablonen zijn ongewijzigd. De bronbestanden onder `docs/prd/evidence/` zijn uitsluitend proefmateriaal. Omdat deze wijziging uitsluitend in `docs/prd/` staat, zijn geen theme-versieophoging, changelog of deployment nodig.
