# PRD: Communicatieplanning

**Status:** Geïmplementeerd in versie 35.101.0

**Datum:** 26 september 2026

**Product:** Rondo Club

**Onderdeel:** Nieuw hoofdmenu-item Communicatie

## 1. Doel en afbakening

Bestuursleden hebben één gedeeld overzicht van communicatie die nog voorbereid of verstuurd moet worden en communicatie die al is verstuurd of gepubliceerd. Zij kunnen items toevoegen, samen voorbereiden, toewijzen en handmatig afhandelen. Maandelijkse en jaarlijkse berichten keren automatisch terug in de planning, met behoud van de historie per afzonderlijke keer.

Rondo houdt de planning bij. Berichten worden buiten Rondo verstuurd of gepubliceerd. Een statuswijziging in Rondo voert geen actie uit in WhatsApp, een nieuwsbriefsysteem of een website.

Dit PRD omvat alle aanvullingen uit het besproken voorstel. De onderstaande detailkeuzes zijn voorgestelde productregels voor de eerste versie, waaronder optionele conceptvelden, de vooruitblik van herhalingen en het omgaan met pauzes.

### Buiten de eerste versie

- Automatisch versturen of publiceren en koppelingen met communicatiekanalen.
- Google Docs aanmaken, inhoud synchroniseren of documentrechten beheren.
- Leesstatistieken, bereik, ontvangstbevestigingen of verzendbewijzen ophalen.
- Automatische meldingen of herinneringsmails, formele goedkeuringsrondes en kalenderweergave.
- Wekelijkse herhaling, aangepaste intervallen en meerdere kanalen binnen één item.

## 2. Gebruikers en rechten

Alle bestuursleden en beheerders kunnen alle communicatie-items bekijken, toevoegen, bewerken, dupliceren, van opmerkingen voorzien en afhandelen. Deze rechten gelden ook voor items van andere bestuursleden. De verantwoordelijke is de aanspreekpersoon; toewijzing beperkt de bewerkrechten niet.

Introduceer het recht `communicatie`, standaard toegekend aan de bestaande rol `rondo_bestuur` en beheerders. Sluit aan op de bestaande rechtenmatrix, zodat een beheerder dit recht later ook aan een andere rol kan toekennen. Gebruikers zonder dit recht zien het menu niet en krijgen geen toegang via een directe URL of API-aanroep. Afbeeldingen en opmerkingen volgen dezelfde toegangsregels.

## 3. Navigatie en overzicht

Het hoofdmenu bevat **Communicatie**, met route `/communicatie`. De pagina heeft twee tabbladen:

| Tabblad | Inhoud | Standaardsortering |
|---|---|---|
| Te communiceren | Concept, In voorbereiding en Klaar | Geplande datum oplopend; items zonder datum onderaan |
| Verstuurd / gepubliceerd | Handmatig afgehandelde items | Werkelijke verzend- of publicatiedatum aflopend |

Beide tabbladen bieden zoeken op titel en beschrijving, filters op kanaal en verantwoordelijke, en een datumfilter. Het eerste tabblad heeft daarnaast een statusfilter en een filter **Te laat**. Bij items zonder datum staat **Nog niet gepland**. De datumfilter gebruikt de geplande datum in het eerste tabblad en de werkelijke datum in het tweede.

Een rij toont titel, kanaal, doelgroep/bestemming, relevante datum, verantwoordelijke, status en een herkenbaar herhalingssymbool indien van toepassing. Een openstaand item met een geplande datum vóór vandaag krijgt de tekst **Te laat**, naast een visuele markering. Een item voor vandaag is nog niet te laat. Datums volgen de ingestelde clubtijdzone.

De pagina bevat een duidelijke knop **Nieuw item**. Een rij opent het detail met alle velden, afbeeldingen, opmerkingen en wijzigingshistorie. Op mobiel blijven dezelfde functies bruikbaar via een compacte lijst. Lege resultaten en laad- of opslagfouten krijgen begrijpelijke teksten.

Overgeslagen en geannuleerde items zijn standaard verborgen. Via **Toon overgeslagen en geannuleerde items** in het eerste tabblad zijn ze terug te vinden; ze tellen niet mee als openstaand of verstuurd.

## 4. Velden per item

| Veld | Verplicht | Gedrag |
|---|---|---|
| Titel | Ja | Korte, herkenbare titel; alleen witruimte is ongeldig |
| Beschrijving | Vanaf In voorbereiding | Meerregelige tekst met inhoud, instructies of context |
| Kanaal | Ja | Radiogroep met exact één keuze: WhatsApp, Nieuwsbrief of Website; geen vooraf gekozen kanaal |
| Google Docs-link | Nee | Geldige HTTPS-link naar een Google-document; knop om het document te openen |
| Afbeeldingen | Nee | Meerdere uploads met voorvertoning, downloaden, volgorde wijzigen en verwijderen uit het item |
| Geplande datum | Vanaf In voorbereiding | Dag waarop het bericht verstuurd of gepubliceerd moet worden; geen tijdstip in versie 1 |
| Verantwoordelijke | Vanaf In voorbereiding | Eén gebruiker met toegang tot Communicatie; standaard de aanmaker, aanpasbaar |
| Doelgroep / bestemming | Vanaf In voorbereiding | Vrij tekstveld, bijvoorbeeld “WhatsApp trainers O13” of “Alle leden” |
| Status | Ja | Standaard Concept; zie de workflow |
| Herhaling | Ja | Geen, Maandelijks of Jaarlijks; standaard Geen |
| Startdatum herhaling | Bij herhaling | Eerste geplande datum van de reeks |
| Einddatum herhaling | Nee | Laatste toegestane geplande datum, inclusief deze datum |
| Werkelijke verzend-/publicatiedatum | Bij afhandelen | Standaard vandaag, aanpasbaar naar het verleden |
| Link naar gepubliceerd bericht | Nee | Geldige HTTPS-link; ook beschikbaar bij achteraf bewerken |

Titel en kanaal volstaan om een idee als Concept op te slaan. Een herhalend concept vereist ook een startdatum. Een optionele Google Docs-link voorkomt dat ideeën pas kunnen worden vastgelegd wanneer er al een document is.

Onder de Google Docs-link staat dat toegang via Google wordt geregeld. Rondo verleent geen documentrechten en haalt de inhoud niet op. Een later aangepast Google-document verandert dus niet de opgeslagen velden in Rondo; het document zelf is geen onveranderlijke historische kopie.

Afbeeldingen ondersteunen minimaal JPEG, PNG en WebP, met maximaal 10 afbeeldingen van 10 MB per bestand per item, binnen eventuele strengere serverlimieten. Ongeldige bestanden leveren een fout per bestand op zonder reeds ingevulde tekst te verliezen. Het verwijderen van een afbeelding uit één item verwijdert geen bestand dat nog bij een ander item of een reeks hoort. Voeg optioneel een beschrijving per afbeelding toe voor toegankelijkheid.

Systeemvelden registreren de aanmaker, aanmaakdatum, laatste bewerker, laatste wijzigingsdatum en degene die de afhandeling in Rondo heeft geregistreerd. Die laatste hoeft niet degene te zijn die het bericht daadwerkelijk heeft verstuurd.

## 5. Workflow en acties

De gebruikelijke route is **Concept → In voorbereiding → Klaar → Verstuurd / gepubliceerd**. Gebruikers mogen tussen de drie open statussen wisselen. Een stap overslaan is toegestaan zodra de verplichte velden voor de doelstatus zijn ingevuld; er is geen verplichte goedkeurder.

### Handmatig afhandelen

- WhatsApp en Nieuwsbrief tonen **Markeer als verstuurd**; Website toont **Markeer als gepubliceerd**.
- De actie vraagt om de werkelijke datum en biedt ruimte voor een publicatielink.
- De werkelijke datum kan niet in de toekomst liggen. Voor afhandelen zijn de velden vanaf In voorbereiding verplicht.
- Na opslaan verhuist het item naar het tweede tabblad. Geplande en werkelijke datum blijven beide bewaard.
- Meerdere klikken of herhaalde verzoeken leveren één afhandeling op.
- **Afhandeling ongedaan maken** herstelt de vorige open status. De actuele afhandelingsgegevens worden gewist; de eerdere registratie en correctie blijven in het log staan. Een reeds bestaande volgende herhaling blijft ongewijzigd bestaan.

### Bewerken, annuleren en dupliceren

- Bestuursleden mogen inhoud en datum achteraf corrigeren; wijzigingen worden gelogd.
- **Annuleren** haalt een openstaand los item uit de actieve planning, met een optionele reden. Herstellen brengt het terug naar de vorige open status.
- Gebruik bij één geplande herhaling **Deze keer overslaan**, zodat de reeks doorloopt.
- Permanent verwijderen is geen standaardactie in deze module; annuleren behoudt de historie.
- **Dupliceren** opent een nieuw concept met titel, beschrijving, documentlink, afbeeldingen, kanaal en doelgroep overgenomen. De gebruiker kan een ander kanaal kiezen.
- Een duplicaat krijgt de huidige gebruiker als verantwoordelijke, geen geplande datum, geen herhaling en geen afhandelingsgegevens. Opmerkingen en wijzigingshistorie worden niet gekopieerd. Opslaan maakt een onafhankelijk item.

## 6. Maandelijkse en jaarlijkse herhaling

### Reeks en afzonderlijke keren

Een reeks bevat de herhaalregel en de standaardinhoud. Iedere geplande keer is een afzonderlijk item met eigen datum, status, inhoud, opmerkingen en afhandeling. Nieuwe keren beginnen als Concept, ook wanneer een eerdere keer Klaar of Verstuurd is. Beschrijving, documentlink, afbeeldingen, kanaal, doelgroep en verantwoordelijke worden overgenomen uit de reeks.

Toon en genereer bij een actieve reeks alle keren vanaf de startdatum tot en met twaalf maanden vooruit vanaf vandaag, begrensd door een eventuele einddatum. Ligt de startdatum verder weg, dan is ten minste die eerste keer zichtbaar. De detailpagina toont de herhaalregel en tot welke datum de planning is uitgewerkt. De vooruitblik wordt automatisch aangevuld; afhandeling van een vorige keer is daarvoor geen voorwaarde.

Een startdatum in het verleden vraagt vóór opslaan om een zichtbare bevestiging van het aantal historische keren dat als openstaand zal worden toegevoegd. Dit voorkomt dat een oude startdatum ongemerkt een grote achterstand oplevert.

### Datumregels

- Maandelijks: dezelfde dag van de maand als de startdatum.
- Bestaat die dag niet, gebruik dan de laatste dag van die maand. De oorspronkelijke dag blijft het anker: 31 januari → 28 februari → 31 maart.
- Jaarlijks: dezelfde maand en dag. Voor 29 februari wordt in niet-schrikkeljaren 28 februari gebruikt; in een volgend schrikkeljaar weer 29 februari.
- Te laat afhandelen of één datum verplaatsen verschuift de rest van de reeks niet.
- Een einddatum mag niet vóór de startdatum liggen. Er ontstaan geen keren na de einddatum.
- Iedere combinatie van reeks en oorspronkelijke geplande datum bestaat maximaal eenmaal, ook bij gelijktijdige verwerking of herhaalde achtergrondtaken.

### Wijzigen, overslaan en pauzeren

- Bij inhoudelijke wijzigingen kiest de gebruiker **Alleen deze keer** of **Deze en toekomstige openstaande keren**. In het tweede geval worden de reeksstandaard en betreffende openstaande keren aangepast.
- Een wijziging aan één keer blijft een uitzondering en wordt niet bij periodiek aanvullen overschreven. Een expliciete latere reekswijziging toont vooraf hoeveel openstaande keren, inclusief uitzonderingen, worden geraakt.
- Afgehandelde, overgeslagen en geannuleerde keren blijven bij reekswijzigingen ongewijzigd.
- Een andere frequentie of ander datumanker wordt ingevoerd door de bestaande reeks te beëindigen en een nieuwe reeks te maken. De interface ondersteunt dit als vervolgactie met overgenomen inhoud en toont welke toekomstige open keren vervallen.
- **Deze keer overslaan** bewaart de keer als Overgeslagen, met optionele reden. De volgende datum blijft volgens het oorspronkelijke ritme gepland. Ongedaan maken herstelt de vorige open status.
- **Reeks pauzeren** stopt nieuwe keren. Reeds geplande open keren vanaf vandaag worden behouden maar tijdelijk uit de actieve planning gehaald; ze zijn zichtbaar in het reeksdetail. Achterstallige keren van vóór vandaag blijven openstaan.
- **Reeks hervatten** herstelt nog toekomstige keren en vult de vooruitblik aan vanaf vandaag op het oorspronkelijke ritme. Datums tijdens de pauze worden overgeslagen met reden “Reeks gepauzeerd”; ze worden geen onverwachte achterstand. Afgehandelde keren veranderen niet.
- **Reeks beëindigen** stopt verdere herhaling en annuleert nog open keren vanaf de gekozen beëindigingsdatum. Eerdere openstaande keren en alle historie blijven bestaan. De interface toont vooraf de gevolgen.

## 7. Samenwerken en historie

Het detail bevat interne opmerkingen met auteur en tijdstip, chronologisch weergegeven. Alle gebruikers met het communicatierecht kunnen opmerkingen toevoegen en lezen. De eerste versie bevat geen mentions of notificaties. Opmerkingen worden niet doorgestuurd naar het communicatiekanaal.

Een automatisch log vermeldt aanmaak, inhoudelijke wijzigingen, toewijzing, datumwijzigingen, statusovergangen, wijzigingen aan afbeeldingen, afhandeling, correcties en reeksacties. Het log toont wie, wanneer en wat veranderde; gewone gebruikers kunnen het log niet wijzigen. Opmerkingen zijn in versie 1 niet achteraf te bewerken of te verwijderen; correcties kunnen als nieuwe opmerking worden toegevoegd.

Bij gelijktijdige bewerking mag een oudere versie een nieuwere wijziging niet stilzwijgend overschrijven. Toon een conflictmelding en behoud de invoer zodat de gebruiker de actuele versie kan vergelijken en opnieuw kan opslaan.

Wanneer een verantwoordelijke toegang verliest of wordt gedeactiveerd, blijft de historie intact. Openstaande items tonen **Opnieuw toewijzen**. Nieuwe keren krijgen geen ongeldige toewijzing; zij blijven zichtbaar als niet toegewezen concepten totdat de reeks is aangepast.

## 8. Voorbeeldscenario's

1. **Maandelijks nieuwsbriefonderwerp:** een bestuurslid plant “Update vrijwilligers” op de 15e. September wordt op 18 september afgehandeld; oktober blijft gepland op 15 oktober. Beide keren hebben hun eigen status.
2. **Jaarlijkse aankondiging:** “Start contributie-inning” staat jaarlijks op 1 juli. Een nieuwe keer begint als Concept, zodat bedragen en tekst opnieuw gecontroleerd worden.
3. **Twee kanalen:** een websitebericht wordt gedupliceerd voor WhatsApp. Het websitebericht kan al gepubliceerd zijn terwijl het WhatsApp-item nog openstaat.
4. **Nog onuitgewerkt idee:** een bestuurslid slaat een titel en kanaal op zonder document of datum. Het item staat onder Nog niet gepland en kan later door een collega worden aangevuld.
5. **Verkeerd afgevinkt:** iemand herstelt een afhandeling. Het item verschijnt opnieuw in de planning en de correctie blijft zichtbaar in de historie.

## 9. Acceptatiecriteria

| ID | Controleerbaar resultaat |
|---|---|
| AC-01 | Een bestuurslid en beheerder zien Communicatie en kunnen items van elkaar bekijken, toevoegen, bewerken en afhandelen. |
| AC-02 | Een gebruiker zonder communicatierecht kan geen items, opmerkingen of bijlagen ophalen of wijzigen, ook niet via directe routes of API-verzoeken. |
| AC-03 | Titel en kanaal volstaan voor een los concept; ontbrekende verplichte velden blokkeren In voorbereiding, Klaar en afhandelen met fouten bij de velden. |
| AC-04 | De kanaalkeuze werkt als toegankelijke radiogroep en staat exact één van de drie toegestane waarden toe. |
| AC-05 | Meerdere afbeeldingen kunnen worden toegevoegd, bekeken, gedownload, geordend en losgekoppeld zonder bijlagen van andere items te beschadigen. |
| AC-06 | Filters, zoeken en sortering werken samen; ongedateerde items staan onderaan en Te laat wordt volgens de clubtijdzone bepaald. |
| AC-07 | Afhandelen bewaart geplande datum, werkelijke datum en registrerende gebruiker, verplaatst het item en verstuurt of publiceert niets extern. |
| AC-08 | Ongedaan maken herstelt de vorige open status, behoudt het log en creëert of verwijdert geen andere herhalingen. |
| AC-09 | Maandelijkse en jaarlijkse reeksen verschijnen vooruit met afzonderlijke statussen, ook als een vorige keer nog openstaat. |
| AC-10 | 31 januari, 29 februari, inclusieve einddatums en vertraagde afhandeling volgen de datumregels zonder ritmeverschuiving. |
| AC-11 | Herhaalde of gelijktijdige generatie maakt geen dubbele keren; na uitval wordt ontbrekende planning aangevuld zonder bestaande wijzigingen te overschrijven. |
| AC-12 | Alleen deze keer wijzigen raakt geen andere keren; een reekswijziging bewaart afgehandelde historie en toont vooraf de impact. |
| AC-13 | Overslaan, herstellen, pauzeren, hervatten en beëindigen werken volgens §6 en verliezen geen historie. |
| AC-14 | Een duplicaat heeft overgenomen inhoud maar begint onafhankelijk als Concept zonder herhaling, datum, opmerkingen of afhandelingsgegevens. |
| AC-15 | Interne opmerkingen en log zijn voor alle bevoegde gebruikers zichtbaar, met auteur en tijdstip; gelijktijdige bewerking veroorzaakt geen stil gegevensverlies. |
| AC-16 | Een niet langer geldige verantwoordelijke blokkeert inzage niet en wordt bij open items herkenbaar voor herverdeling gemarkeerd. |
| AC-17 | De belangrijkste handelingen zijn bruikbaar op mobiel en met toetsenbord; status en fouten zijn niet uitsluitend via kleur herkenbaar. |
| AC-18 | Een Google Docs-link is optioneel, opent het document en voert geen synchronisatie of wijziging van Google-rechten uit. |

## 10. Technische kaders voor implementatie

- Sluit aan op de bestaande React-interface, router, TanStack Query en WordPress REST-architectuur.
- Gebruik native WordPress-entiteiten en postmetadata voor items en reeksen; geen eigen databasetabellen. Leg domeinvelden vast in de bestaande field registry.
- Maak afzonderlijke entiteiten voor de reeks en de concrete keren, zodat een reekswijziging nooit historische inhoud impliciet wijzigt.
- Pas autorisatie op de server toe voor lezen, schrijven, opmerkingen en bestandslevering. Verifieer expliciet dat de bestaande mediaopslag de vereiste afscherming kan leveren; alleen een verborgen menuknop of attachment-record is onvoldoende.
- Maak generatie en afhandeling bestand tegen herhaalde verzoeken en gelijktijdige verwerking. Bewaar het oorspronkelijke reeksanker afzonderlijk van een incidenteel verplaatste datum.
- Gebruik achtergrondverwerking om de vooruitblik bij te houden, met herstel na gemiste uitvoeringen en aanvulling bij het openen van de planning indien nodig.
- Bewaar kalenderdatums zonder tijdzoneverschuiving en registreer auditmomenten als tijdstippen; presenteer deze in de clubtijdzone.
- Gebruik paginering en serverfilters zodat de groeiende historie het overzicht niet onnodig vertraagt.

## 11. Oplevering en verificatie

De eerste release omvat alle functionele onderdelen uit dit PRD. Implementeer in drie controleerbare stappen:

1. Rechten, hoofdmenu, velden, afbeeldingen, overzicht, detail en handmatige afhandeling.
2. Reeksen, generatie, uitzonderingen, overslaan, pauzeren en beëindigen.
3. Dupliceren, opmerkingen, wijzigingshistorie, conflictbehandeling en mobiele afwerking.

Verifieer vooral rechten via API en bestanden, datumgrenzen, het behoud van historie, gelijktijdige acties en het herstel na gemiste generatie. Doorloop daarnaast de vijf voorbeeldscenario's met minstens twee bestuursaccounts. De functionaliteit is gereed wanneer alle acceptatiecriteria zijn behaald en de vereiste projectchecks slagen.

Deze documentwijziging implementeert de functionaliteit nog niet en vereist geen versieophoging of deployment.
