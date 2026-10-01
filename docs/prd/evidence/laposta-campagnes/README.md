# Laposta-campagnes: technische proef

Datum: 1 oktober 2026. Onderdeel van [het campagneplan](../../laposta-campagnes.md).

## Conclusie

Eén HTML-template met afzonderlijke ondertekeningsprofielen werkt voor conceptaanmaak en bijwerken via de Laposta API. Dit is geverifieerd met Joost en Xander in dezelfde campagne, inclusief afzender, antwoordadres, handtekeningafbeelding en tekstuele ondertekening. Deze pagina legt de technische proef vast; de latere [productie-implementatie en openstaande verificatie](../../laposta-campagnes-interface.md#productie-implementatie) staan afzonderlijk beschreven.

## Geïsoleerde proefobjecten

| Object | ID | Eindtoestand |
|---|---|---|
| Lijst `Rondo API proef 2026-10-01 - LEEG` | `audzmkbwte` | 0 actief, 0 uitgeschreven, 0 opgeschoond |
| Segment `Rondo technische proef - leeg` | `q0bTXeArb1` | Proefnummer > 0 op de lege testlijst |
| Campagne `RONDO API PROEF 2026-10-01 - NIET VERSTUREN [01a0f7f8]` | `tomad4bo3z` | In bewerking; geen verzendtijdstip of verzendstart |

De lege lijst heeft uitsluitend de aanvullende testvelden Voornaam en Proefnummer. Er zijn geen relaties toegevoegd. Er is niets verzonden of ingepland. De proefobjecten zijn behouden voor inspectie en niet verwijderd.

[Controlepagina van het concept](https://app.laposta.nl/c.campaign/s.browse/edit/confirm/?campaign=tomad4bo3z) (inloggen vereist).

## Bewijs per stap

1. De eigen HTML-export van Joost is gedownload; de export van Xander leverde diens eigen handtekeningafbeelding en hoogte. De andere twee profielafbeeldingen zijn niet getest.
2. `POST /v2/campaign` accepteerde de expliciete mapping `list_ids[audzmkbwte][]=q0bTXeArb1`. `GET /v2/campaign/tomad4bo3z` gaf exact die mapping terug.
3. De HTML is met `inline_css=true` geïmporteerd. De eerste import signaleerde een ontbrekende achtergrondafbeelding en afmeldlink; de uiteindelijke import gaf geen `report`-meldingen.
4. Onderwerp, afzender en antwoordadres zijn via het campagne-update-endpoint naar Xander gewijzigd; content is op hetzelfde campagne-ID vervangen. Teruggelezen HTML bevatte Xanders naam, functie en afbeelding en geen `Joost de Valk` meer.
5. De laatste controle in Laposta toonde het gekozen testsegment, 0 ontvangers, Xanders onderwerp en `penningmeester@svawc.nl` als afzender. De API bevestigde hetzelfde antwoordadres; `delivery_requested` en `delivery_started` waren `null`.
6. De browserpreview toonde de juiste huisstijl, Xanders afbeelding, naam en functie. Alle 8 afbeeldingen inclusief Laposta-logo waren geladen. Op 390 pixels was de documentbreedte exact 390 pixels, zonder horizontale overflow.
7. De nieuwsbriefpreview zette beide importtags om naar de campagnegebonden webversie- en afmeld-URL's. Er is niet op de afmeldlink geklikt.

## Correcties die nodig waren

- De export had geen afmeldlink. De dynamische links uit de drag-and-drop-editor werden bij API-import niet herkend; ook `<lp-unsubscribe>` uit de documentatie voor Joe-sjablonen bleef een waarschuwing geven. Het importscherm onder Variabelen toonde het juiste formaat: `<unsubscribe>` en `<webversion>`. Dat formaat importeerde zonder waarschuwing en werd in de preview naar echte links omgezet.
- De achtergrondafbeelding `e69df11_w2048_dji0034.jpg` op de oude `sv-awc.email-provider.nl`-host gaf HTTP 404. De proef verwijdert die achtergrondreferentie, inclusief CSS/Outlook-verwijzingen. Header, footer en overige bestaande beelden bleven behouden. De originele Laposta-sjablonen zijn niet aangepast.
- Xanders export gebruikte zijn eigen afbeelding, maar de alternatieve tekst en titel noemden Joost. De renderer vult beide vanuit het profiel.
- De API-documentatie toont bij segmenten een tekstuele definitie die live werd afgewezen met `definition is no valid JSON`. Het live uitgelezen segmentformaat gebruikt `blocks → rules → input`, met een veld-ID als `subject`. Dat formaat is op een nieuw testveld in de lege lijst toegepast. Een productie-editor voor segmentregels hoort niet bij deze proef.

## Herbruikbaar proefmateriaal

`base-template.html` bevat één template met benoemde placeholders. `profiles.json` bevat de twee daadwerkelijk geteste profielconfiguraties. `render.mjs` maakt uitsluitend lokale HTML en doet geen API-verzoeken; de berichttekst is een vaste, vertrouwde fixture. Dit is geen productie-editor of sanitizer voor gebruikersinvoer.

```bash
node docs/prd/evidence/laposta-campagnes/render.mjs joost /tmp/rondo-joost.html
node docs/prd/evidence/laposta-campagnes/render.mjs xander /tmp/rondo-xander.html
```

Laposta verwijdert de laatste newline bij import; de renderer houdt daar rekening mee. Beide definitieve renderresultaten zijn byte voor byte vergeleken met de opnieuw via de API opgehaalde HTML, zonder importmeldingen:

| Profiel | SHA-256 van verzonden HTML |
|---|---|
| Joost | `0ad4cf613906221d765d0a3a8a37b517c95c086c81864c8b170f723962bedf06` |
| Xander | `c6c77f144b1e2b9536bd74c16e68da3fd5f9a7a3179945d005ad432e125b34ec` |

De bestaande lokale Laposta-sleutel is alleen voor deze proef server-side ingelezen; er is geen sleutel naar Rondo-configuratie, browser, log of repository gekopieerd. Bestaande lijsten zijn alleen op naam/aantallen en segmentmetadata gelezen. De People-pipeline is niet gestart.

## Resterende verificatie vóór productiegebruik

- De segment-API retourneerde definitie en metadata, geen ontvangers of bereik. Het getoonde nulaantal is bewezen in de Laposta-interface en door de volledig lege lijst. Exact bereik en overlap tussen gevulde lijsten blijven onbewezen via de API.
- Personalisatie bleef als `{{voornaam,AWCer}}` zichtbaar in de algemene preview. Ontvangerspecifieke invulling en terugvaltekst vereisen een afzonderlijk geautoriseerde testmail of ontvangerspreview; ze zijn niet bewezen door deze lege proef.
- De browserpreview is geen bewijs voor Outlook/Gmail-rendering of daadwerkelijke aflevering. Ook daadwerkelijke afmelding is niet getest.
- Het geïmporteerde concept is zichtbaar in Laposta; de controlepagina biedt Aanpassen en de importflow biedt HTML-invoer. Een handmatige edit in Laposta en conflictherkenning door Rondo zijn nog niet getest.
- Deze succesvolle sequentiële update bewijst geen bescherming tegen dubbelklikken, timeouts of parallelle exports. Die bescherming wordt bij de productimplementatie gebouwd en getest.
- Voor ingebruikname: de overige profielen controleren, de gewenste rechtenverdeling vaststellen en de Rondo-interface ontwerpen.

## Bronnen

- [Laposta API: campagnes, content, lijsten, velden en segmenten](https://api.laposta.nl/doc/index.nl.php).
- De live importflow van dit proefconcept, onderdeel Variabelen, voor de correcte HTML-importtags.
- De eigen HTML-downloads uit [de Laposta-sjablonen](https://app.laposta.nl/c.campaign/s.template/t.dand/).

De opgeslagen proefbestanden zijn uitsluitend documentatie en prototype; ze worden niet door de Rondo-app geladen.

## Klikbaar interfaceontwerp

Het [prototype](interface/index.html) werkt vanuit een lokale webserver:

```bash
python3 -m http.server 8765 --bind 127.0.0.1 --directory docs/prd/evidence/laposta-campagnes
```

Open daarna `http://127.0.0.1:8765/interface/`. De [ontwerpkeuzes](../../laposta-campagnes-interface.md) beschrijven de afgesproken aparte editor met invoer links en voorbeeld rechts.

- Wissel tussen Joost en Xander om ondertekening, afzender en antwoordadres te zien veranderen. Het derde voorbeeld toont een ontbrekend profiel.
- Kies per lijst een segment of expliciet de hele lijst. Alle doelgroepkeuzes zijn demonstratiegegevens; er worden geen actuele aantallen of relaties opgehaald.
- Schrijf onderwerp, previewtekst, titel en bericht. Vet, cursief, opsommingen en weblinks zijn klikbaar. De voorbeeldnaam in de aanhef is Sam; het prototype bewijst geen daadwerkelijke personalisatie.
- Controleer de selectie en voer de gesimuleerde conceptaanmaak uit. Geen API-sleutel, campagneaanmaak of verzending. Het kanaal blijft onafgerond.
- Concept opslaan bewaart uitsluitend in deze browser. Opnieuw beginnen wist dat lokale prototypeconcept.
- De template en profielen komen uit de technische proef. Het prototype voegt alleen alinea-afstand en een voorbeeldnaam toe, maakt previewlinks inert en toont de mail in een gesandboxed iframe. Het is geen productie-editor of mailclienttest.

De interface is gecontroleerd op 1440 en 1728 pixels en op 390 pixels mobiel, inclusief donkere weergave, profiel-/doelgroepvalidatie, selectie van meerdere lijsten, inhoud wijzigen, linkinvoer, lokaal bewaren/herladen, controle en gesimuleerd resultaat. JavaScript-syntaxcontrole en `git diff --check` zijn geslaagd. Voor de productimplementatie moeten de React-editor, rechten en API-afhandeling afzonderlijk worden gebouwd en getest.
