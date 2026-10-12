# Wijzigingsplan wedstrijdvergoedingen: feedback 14350

**Status:** Voorstel ter beoordeling; geen opdracht tot implementatie of productie-inrichting.
**Datum:** 2026-10-12.
**Bron:** [Feedback #14350](https://rondo.svawc.nl/feedback/14350).
**Voorganger:** [Wedstrijdregistratie en premies](wedstrijdregistratie-en-premies.md).

## 1. Beoogd resultaat

De financiële beheerder verwerkt AWC 1 per maand in twee afzonderlijke groepen:

- Spelers met een premieovereenkomst gaan naar een XLSX voor de loonadministratie.
- Spelers zonder premieovereenkomst krijgen €20 per meetellende wedstrijd plus €30 per
  behaald punt, via een afzonderlijk SEPA-bestand.

De registrator blijft alleen wedstrijdgegevens en deelname vastleggen. Een speler die vanuit
een ander team wordt toegevoegd, kan een premieovereenkomst hebben. Het registratielabel
`guest` bepaalt daarom nooit of iemand naar Nmbrs of SEPA gaat.

**Goedgekeurd door Joost op 12 oktober 2026:** Xanders gastspelersregeling van €20 per
meetellende wedstrijd plus €30 per punt wordt overgenomen voor spelers zonder
premieovereenkomst bij AWC 1. Dit vervangt voor AWC 1 de afspraak B3a uit de voorganger.
De bedragen zijn vast; er komt hiervoor geen tariefinstelling voor de penningmeester.
De JO23-1-regeling blijft €15 per punt, zonder vaste wedstrijdvergoeding.
Alleen competitie en nacompetitie tellen mee. Basis en Bank tellen beide; overige statussen niet.
Een rechtstreekse Nmbrs-koppeling, loonberekening, automatische bankbetaling en wijzigingen
aan Rondo Sync vallen buiten scope. Dit plan doet geen uitspraak over fiscale kwalificatie.

## 2. Vastgestelde uitgangssituatie

Onderstaande feiten komen uit de codecontrole en de productie-interface van 12 oktober 2026.
Het originele Nmbrs-voorbeeld is op diezelfde datum door Joost aangeleverd en read-only
gecontroleerd, zowel via de oorspronkelijke XLSX/XML-celopslag als met een onafhankelijke
XLSX-lezer. Het bestand is niet gewijzigd of in Nmbrs geïmporteerd.

Bronbestand: `23008 L 2026 08 25 Import_sheet_mutaties_SV_AWC_augustus.xlsx`.
SHA-256: `2d8c81109ff11e052cf80eef04e50cbd4410d5bc156650ed6132cf4832c2c91b`.
De bron blijft buiten Git; de namen en individuele personeelsnummerkoppelingen worden hier
niet gekopieerd. De andere in feedback genoemde registratiebijlage is niet gecontroleerd.

| Onderdeel | Bestaande werking | Verschil met het verzoek |
|---|---|---|
| Registratie | Programma, handmatige wedstrijd, uitslag, spelersstatussen, concept en afronden | Guido is nog niet toegewezen; een korte gebruikersinstructie ontbreekt |
| Loonexport | CSV met aantallen en een handmatig bevestigde Nmbrs-naam | Geen XLSX volgens Frans' indeling, personeelsnummer of beheer van premieovereenkomsten |
| Spelerselectie | Alleen Basis/Bank-deelnames leveren maandregels op | Loonspelers zonder deelname ontbreken |
| Gastspelers AWC 1 | Dezelfde telroute als alle andere AWC 1-spelers | Geen €20-berekening of aparte SEPA-route |
| SEPA | Gedeelde generator, gebruikt voor JO23-1 | Nog niet beschikbaar als tweede uitvoer voor AWC 1 |
| September AWC 1 | 21 maandregels; alle bevestigde Nmbrs-namen ontbreken | Ook spelers zonder premieovereenkomst worden door deze naamcontrole geblokkeerd |
| Instellingen | Alleen Xander als registrator; bankcode en bewaarbeleid leeg | Guido toewijzen, looncode controleren en vastgesteld bewaarbeleid invullen |

De maandafsluiting controleert het bewaarbeleid pas bij afsluiten. Dit wordt in de nieuwe
voorvertoning vooraf zichtbaar. Het beleid wordt niet door de implementatie verzonnen.

## 3. Financiële indeling en gegevensmodel

### Voorstel voor opslag

Gebruik de bestaande personen, team-ID's, private registratie- en batch-CPT's, Options API
en native veldlaag. Geen nieuwe database-tabellen en geen tweede personenbestand.

Voeg een financieel afgeschermde native repeater op de persoon toe voor afspraken per team
en geldigheidsperiode. Voorgestelde canonieke naam: `match_compensation_agreements`.
Kindvelden: `team_id`, `valid_from`, `valid_until` (nullable), `payment_route`
(`payroll` of `sepa_guest`), `nmbrs_employee_number` en `nmbrs_name`.

- Rondo `person_id` blijft de interne identiteit. Het personeelsnummer is de expliciet
  gecontroleerde koppeling met de loonadministratie; geen koppeling op naam of teamlidmaatschap.
- Geldigheid gebruikt de speeldatum; begin- en einddatum zijn inclusief. Overlappende afspraken
  voor dezelfde persoon en hetzelfde team worden geweigerd.
- Een ontbrekende afspraak betekent **onbekend**, nooit automatisch gastspeler of loonspeler.
  Een deelname zonder eenduidige afspraak blokkeert afsluiten met een herstelactie.
- Personeelsnummers worden als tekst opgeslagen om voorloopnullen te behouden. Hetzelfde nummer
  mag binnen dezelfde werkgever en overlappende periode niet bij twee personen horen.
- Een loonafspraak vereist een personeelsnummer en de door de loonadministratie bevestigde naam.
  De naam wordt meegeleverd in het bestand, maar wordt niet als sleutel gebruikt of geraden.
- De bestaande losse `nmbrs_name` mag als invoervoorstel dienen. Die bewijst geen overeenkomst
  of personeelsnummer en wordt niet stilzwijgend omgezet naar een bevestigde loonafspraak.
- IBAN en rekeninghouder blijven in de bestaande persoonsvelden; geen kopie in de afspraken.

Een financieel beheerder kan een bestaande persoon zoeken, een afspraak toevoegen en een
personeelsnummer vastleggen. Bij ontbrekend personeelsnummer blijft een loonspeler geblokkeerd;
die wordt nooit tijdelijk naar SEPA verplaatst. Dit ondersteunt ook later toegevoegde loonspelers.

**Besluit van Joost, 12 oktober 2026:** de penningmeester voert de ingangsdatum zelf in op de
pagina Wedstrijdvergoedingen, bij de betreffende afspraak, met een optionele einddatum.
De ingangsdatum is geen voorafgaand beslispunt of voorwaarde om de module te bouwen.
De pagina toont welke open maanden door de gekozen datum veranderen; afgesloten maanden
blijven hun vastgelegde afspraken gebruiken en volgen bij wijzigingen het correctieproces.

### Rechten en presentatie

De afspraken en personeelsnummers vallen onder dezelfde financiële veldbeveiliging als de
bestaande Nmbrs-gegevens, inclusief generieke REST- en abilities-antwoorden. Registratoren
krijgen hierdoor geen toegang tot salaris- of bankgegevens. Financieel beheer is nodig voor
wijzigingen en export; bestaande persoonstoegang blijft gelden. Leden mogen hun eigen
bankgegevens blijven wijzigen, maar niet hun betalingsroute of premieovereenkomst.

Verduidelijk in registratie dat 'gast' alleen betekent dat iemand buiten de teamsuggestie is
toegevoegd. Maandverwerking toont aparte tabellen **Loonadministratie** en **Direct uitbetalen**,
met een derde lijst **Nog in te delen** voor ontbrekende of tegenstrijdige afspraken.

## 4. Berekening en Nmbrs-export

### Loonspelers

Behoud de aantallen Basis, Bank, Winst, Gelijk en Dagen. Dagen is het aantal meetellende
wedstrijden met Basis/Bank, niet het aantal unieke kalenderdagen. Individuele loonbedragen
worden door de loonadministratie berekend.

De export bevat iedere loonspeler met een geldige afspraak die de maand overlapt, ook zonder
deelname. Alleen deelnames binnen de geldigheidsperiode tellen. Bij nul wedstrijden zijn de
telcellen leeg volgens Xanders verzoek; de identiteit en periode blijven ingevuld. Het voorbeeld
bevat helemaal geen ingevulde aantallen en bewijst daarom niet hoe nulwaarden bij spelers
met deelname moeten worden aangeleverd. Voorgesteld: ook nulstanden in de telkolommen leeg
exporteren, met expliciete bevestiging bij de proefimport vóór ingebruikname.

### Gecontroleerd XLSX-contract

Werkblad `LooncomponentenVar`, twee kopregels, gegevens vanaf rij 3 en deze exacte kopteksten:

| Kolom | Rij 1 | Rij 2 | Betekenis |
|---|---|---|---|
| A | Import | Debiteurnr | 23008 |
| B | Leeg | Bedrijfnr | 23008 |
| C | Leeg | Persnr | Personeelsnummer |
| D | Leeg | Naam | Bevestigde naam in de loonadministratie |
| E | Leeg | Periode | Tekst `YYYY-MM` |
| F | Leeg | Dagen | Aantal meetellende Basis/Bank-deelnames |
| G | Leeg | DagenVoorLC | Aanwezig, gegevens leeg |
| H | Wedstrijdvergoeding (basis) aantal | U2150 | Aantal Basis |
| I | Wedstrijdvergoeding (bank) aantal | U2151 | Aantal Bank |
| J | Wedstrijdpremie (winst) | L3090 | Aantal Winst |
| K | Wedstrijdpremie (gelijkspel) | L3091 | Aantal Gelijk |

De woorden 'Leeg' in deze tabel betekenen lege cellen, geen te exporteren tekst.

Gecontroleerde bronkenmerken:

- Rijen 3–20 bevatten 18 unieke personeelsnummers en 18 namen; periode is overal `2026-08`.
  Dit is een bronlijst voor augustus, geen bewijs van de actuele of historische geldigheid
  van iedere overeenkomst. Koppeling aan Rondo-personen wordt afzonderlijk gecontroleerd.
- Debiteurnummer A is tekst; bedrijfsnummer B en personeelsnummer C zijn numeriek opgeslagen.
  Namen D en periode E zijn tekst. Er staan geen formules in het importblad.
- F3 bevat één spatie; de overige cellen F3:K20 zijn leeg. De export schrijft echte lege cellen,
  geen spaties. Er zijn geen voorbeeldbedragen of aantallen om rekenuitkomsten tegen te toetsen.
- Het bestand heeft vier tabbladen: `Start`, `LooncomponentenVar`, verborgen `Blad1` en
  verborgen `Kostenverdeling (variabel)`. De twee verborgen bladen hebben geen celwaarden;
  `Start` bevat navigatielabels. Het importblad heeft geen samengevoegde cellen of datavalidaties.

Behoud voor de eerste export de tabbladstructuur en verborgen status van het voorbeeld;
kopieer geen echte spelerslijst naar een testsjabloon. Dit voorkomt een onbewezen vereenvoudiging
naar één tabblad. Of die aanvullende bladen voor Nmbrs noodzakelijk zijn, is nog niet getest.
Het aangeleverde bestand is alleen structureel gecontroleerd, niet als werkende import bewezen.

Bewaar personeelsnummers intern als tekst. Schrijf de in dit voorbeeld gebruikte numerieke
identificaties verliesloos als getallen in B/C, overeenkomstig het bronformaat. Bij toekomstige
identificaties met voorloopnullen of letters mag geen stille conversie plaatsvinden; toets dan
eerst het importcontract. Nieuwe aantallen worden numerieke waarden, geen tekst of formules.

De administratie- en componentcodes worden expliciete gevalideerde module-instellingen.
`U2151` is bevestigd in cel I2; administratiecodes 23008 zijn bevestigd in kolommen A en B.
Voorbeeld bestandsnaam: `23008 L 2026 09 Import_sheet_mutaties_SV_AWC_september.xlsx`.
De aangeleverde augustusbron bevat daarnaast `25` in de bestandsnaam; neem die niet als vaste
waarde over. De nieuwe maandbestandsnaam volgt Xanders expliciete verzoek in de feedback.

Gebruik de gecontroleerde kopregels en structuur voor een geanonimiseerd contractbestand met
fictieve personen voor tests. Controleer vóór ingebruikname de proefimport, nulwaarden en
correctiewerkwijze met de loonadministratie. Exporteer echte XLSX-bytes,
met tekstvelden als tekst en aantallen als getallen; geen formules uit gebruikersinvoer.
De bestaande tekst-download via `Blob([file.content])` moet voor binaire XLSX-uitvoer worden
aangepast. Selecteer de XLSX-bibliotheek tijdens implementatie na controle van de bestaande
dependencies; verifieer het resultaat met een onafhankelijke XLSX-lezer.

### Spelers zonder premieovereenkomst

Reken in gehele eurocenten, per meetellende Basis/Bank-deelname:

`bedrag = 2000 + 3000 × wedstrijdpunten`, met punten 0, 1 of 3.

Dat geeft €20 bij verlies, €50 bij gelijkspel en €110 bij winst. Deze deelnames komen nooit
in het loonbestand. Bij een echte wijziging van afspraak midden in de maand kan één persoon
in beide groepen staan, maar geen enkele deelname mag dubbel worden verwerkt.

Hergebruik `SepaCreditTransfer` en het bestaande `pain.001.001.09`-profiel. Alleen positieve
bedragen worden aangeboden. Ontbrekend/ongeldig IBAN of lege rekeninghouder bij een positief
bedrag blokkeert afsluiten. De omschrijving vermeldt team en maand, bijvoorbeeld
`Vergoeding AWC 1 september 2026`. Rondo genereert een bestand; betalen gebeurt buiten Rondo.

## 5. Afsluiting, exports en correcties

Behoud één onveranderlijke maandbatch met twee uitvoerroutes. Leg per regel de gebruikte
afspraak, betaalroute, tariefversie, personeelsnummer, naam, aantallen en eventueel bankgegevens
vast. Registreer de gebruikte bronversies en afspraakversies in de controlehash. Ook toevoeging
van een loonspeler zonder deelname moet een eerder geladen voorvertoning ongeldig maken.

De voorvertoning toont alle blokkades vooraf, waaronder ontbrekende afspraken, nummers,
bankgegevens, bewaarbeleid, onvolledige registraties en bestaande bronconflicten. Gastspelers
hebben geen Nmbrs-naam nodig; loonspelers hebben voor deze uitvoer geen IBAN nodig.

De huidige enkelvoudige `_match_export` en `_match_processing` volstaan niet voor twee routes.
Breid de private batchmetadata uit met afzonderlijke export- en verwerkingsbewijzen per route.
Elke route heeft een eigen hash, bestand, datum, referentie en idempotente aanvraag-ID.
Hergebruik de bestaande lock en ontvangstbewijzen; parallelle aanvragen mogen geen tweede
bestand of dubbel verwerkingsbewijs veroorzaken. Een lege route toont 'Niet van toepassing'.

- Afsluiten, exporteren en externe verwerking vastleggen blijven afzonderlijke handelingen.
- Herdownload levert exact hetzelfde opgeslagen bestand, inclusief export-ID's en timestamps.
- Nmbrs verwerkt en SEPA betaald worden apart vastgelegd. Een download markeert niets als betaald.
- Voor een ongebruikte route moet vóór vervanging worden bevestigd dat het oude bestand niet
  extern is gebruikt. Een reeds verwerkte route krijgt alleen een expliciete correctie.
- Correcties vergelijken per persoon én route met de verwerkte momentopname. De huidige
  vergelijking uitsluitend per persoon moet daarvoor worden aangepast.
- Bij een routewisseling na externe verwerking blokkeert automatische heruitbetaling totdat
  de oorspronkelijke verwerking aantoonbaar is gecorrigeerd. Geen stille betaling via beide routes.
- Negatieve SEPA-correcties worden handmatig afgehandeld. Het formaat en de verwerkingswijze
  van Nmbrs-correcties moeten door de loonadministratie zijn bevestigd vóór export daarvan.

Oude batches blijven leesbaar en oude bestanden blijven identiek te downloaden. Introduceer
een schema-/tariefversie; ontbrekende nieuwe velden maken een oude batch nooit automatisch
geschikt voor de nieuwe gastspelersregeling. Geen automatische herberekening van betaalde maanden.

## 6. Inrichting en overgang

Na goedkeuring van het plan volgen afzonderlijk controle en inrichting:

1. Gebruik Frans' gecontroleerde voorbeeldbestand en verifieer de actuele lijst met personeelsnummers.
   Verifieer de in feedback genoemde uitzonderingen op hun bestaande Rondo-ID's; leid de
   loonstatus niet af uit het huidige gastlabel. Sla de volledige personenlijst niet in Git op.
2. De penningmeester vult per afspraak de ingangsdatum op de pagina in en controleert vóór
   historische verwerking de status van augustus en september buiten Rondo. Xanders
   rekenvoorbeelden zijn geen bewijs dat deze maanden nog onbetaald zijn.
3. Maak een voorstel voor afspraken met controlevoorvertoning en backupreferentie. Schrijf
   alleen na akkoord de gecontroleerde koppelingen; verifieer teruglezen en identieke herhaling.
4. Controleer Guido's exacte account, koppel de twee bestaande moduleteams en verifieer de
   bestaande teamtoegang. Een registratortoewijzing mag geen onbedoelde financiële toegang geven.
5. Laat de club het bewaarbeleid aanleveren en vul de in het voorbeeld bevestigde looncode en
   administratiecodes in tijdens de geautoriseerde productie-inrichting.
6. Controleer maandtotalen zonder af te sluiten of bestanden voor betaling klaar te zetten.
   Vergelijk de proef-XLSX met Frans' voorbeeld en laat de loonadministratie de importindeling
   bevestigen. Controleer de SEPA-validatie zonder een echte betaling uit te voeren.

Maak bij oplevering een korte deelbare instructie voor Guido: team en wedstrijd kiezen of
handmatig toevoegen, datum/tegenstander/thuis-uit/wedstrijdsoort/uitslag controleren, per speler
een status kiezen, gastspelers toevoegen en het verschil tussen concept en afronden uitleggen.
Een concept telt niet mee; afronden valideert en maakt de wedstrijd beschikbaar voor financiën.
De gebruiker beslist zelf over het delen van die instructie.

## 7. Uitvoeringsvolgorde en acceptatie

| Fase | Werk | Gereed als |
|---|---|---|
| 0. Broncontract | Gecontroleerd voorbeeld gebruiken; correctieformaat en actuele loonlijst bevestigen | De resterende open punten hieronder zijn vastgelegd voor het betreffende onderdeel |
| 1. Afspraken | Native velden, financiële rechten, beheerinterface met ingangsdatum, geldigheids- en uniciteitscontrole | De penningmeester kan de ingangsdatum invoeren; onbekende afspraken blokkeren; een gastlabel verandert de betaalroute niet |
| 2. Maandberekening | Afspraken per speeldatum toepassen, twee groepen, alle loonspelers meenemen | Berekeningen en uitsluitingen kloppen onafhankelijk van de huidige teamsamenstelling |
| 3. Bestanden en historie | XLSX, SEPA, binaire download, aparte bewijzen en correcties | Beide bestanden voldoen aan hun contract; herdownload en gedeeltelijke verwerking blijven veilig |
| 4. Inrichting en uitleg | Gecontroleerde koppelingen, Guido, instellingen en instructie | Bevoegde gebruikers kunnen de bedoelde stappen uitvoeren en onbevoegden niet |

### Vereiste controles bij implementatie

- Rekenfixtures: verlies/gelijk/winst leveren €20/€50/€110; Basis en Bank zijn gelijkwaardig.
  Drie wedstrijden met één gelijkspel leveren €90; één verlies €20; twee verliezen €40.
  Gebruik fictieve personen; toets de echte voorbeelden afzonderlijk via geautoriseerde toegang.
- Alleen competitie/nacompetitie, juiste speeldatum, geen vergoeding voor afwezigheidsstatussen;
  JO23-1 blijft €15 per punt. Registratieconcepten tellen niet mee.
- Loonspeler zonder deelname staat in XLSX met lege telcellen. Dubbele personeelsnummers,
  ongeldige datums, overlappende afspraken en onbekende routes worden geweigerd.
- De penningmeester kan per afspraak de ingangsdatum en optionele einddatum invoeren en ziet
  de gevolgen voor open maanden. Een datumwijziging herschrijft geen afgesloten maand.
- Een loonspeler met `guest=true` blijft loonspeler; een speler zonder overeenkomst krijgt
  uitsluitend SEPA. Een afspraakwijziging midden in de maand telt elke deelname precies eenmaal.
- Test native opslag, gedeeltelijke writes, verwijderde repeaterregels, veldrechten en afscherming
  via REST/abilities. Controleer stale previews na wijzigingen en gelijktijdige schrijfaanvragen.
- Lees de gegenereerde XLSX onafhankelijk terug en vergelijk kopregels, typen, kolomvolgorde,
  maand, codes en nulwaarden. Controleer dat namen geen formules worden.
- Valideer SEPA tegen de bestaande XSD, inclusief ontvangers, centtotalen, omschrijving,
  export-ID's en identieke herdownload. Ongeldige bankgegevens blokkeren vóór afsluiting.
- Test gedeeltelijke externe verwerking, routewisseling, negatieve correcties, oude batches
  en herstel na onderbroken writes. Een nieuwe route mag een oude betaling nooit dupliceren.
- Voer de volledige PHP-suite, PHP coding standards en JS lint/build uit voor de codewijziging.
  Controleer na CI/deploy de productie-interface als financieel beheerder en registrator;
  maak geen fictieve productiebetalingen of registraties voor tests.

Dit document is alleen planning: geen versie- of changelogwijziging en geen deployment nodig.
Developer-documentatie wordt conform de repositoryafspraak door de dagelijkse automation bijgewerkt.

## 8. Beslispunten vóór de bouw

De gastspelersregeling is goedgekeurd. De penningmeester bepaalt de ingangsdatum later op
de pagina; daarvoor is vooraf geen besluit nodig. De volgende punten staan nog open:

1. **Looncontract:** het originele importformaat en de bronlijst met 18 personeelsnummers zijn
   gecontroleerd. Verifieer nog de actuele koppeling van die loonlijst aan Rondo-personen.
   Nulwaarden en correctiewerkwijze moeten bij een proefimport met de loonadministratie worden
   bevestigd vóór ingebruikname. De koppeling op personeelsnummer vervangt het handmatig raden
   van namen; de naamkolom blijft volgens het importcontract verplicht.
2. **Inrichting:** definitief bewaarbeleid, Guido's account/toegang en actuele externe verwerking
   moeten vóór productie-inrichting worden vastgesteld.

Deze resterende punten zijn nog geen geaccordeerde feiten. De keuze om dit plan uit te werken geeft
geen toestemming om betalingen uit te voeren, rechten toe te kennen of feedback af te sluiten.
