# Jaarlaagcoördinatoren: leden zoeken op leeftijdsgroep én team

**Status:** Geïmplementeerd in 35.83.0; productie-inrichting afzonderlijk te bevestigen.
**Datum:** 16 september 2026.
**Codebasis:** `main` op `e69eb66d`.
**Componenten:** Rondo Club; controle van bestaande Sportlink-sync.

## Gewenst gedrag

Een jaarlaagcoördinator kan via Relaties en de algemene zoekfunctie alle leden vinden die:

- de toegewezen leeftijdsgroep hebben; **of**
- momenteel als speler bij een toegewezen team horen.

De voorwaarden vormen samen één verzameling, zonder dubbele resultaten. Dit omvat leden zonder team, dispensatiespelers en spelers die een jaar hoger spelen. Een speler kan daardoor terecht zichtbaar zijn voor twee coördinatoren. De gebruiker heeft deze combinatie gekozen.

Voorbeeld voor een coördinator O13:

| Lid | Zichtbaar vanuit O13? | Reden |
|---|---|---|
| Onder 13, zonder team | Ja | Leeftijdsgroep |
| Onder 14, speelt in JO13-2 | Ja | Actueel speler van toegewezen team |
| Onder 12, speelt in JO13-1 | Ja | Actueel speler van toegewezen team |
| Onder 13, speelt in JO14-1 | Ja | Leeftijdsgroep |
| Onder 14, speelde vorig seizoen in JO13-2 | Nee | Geen actuele grond voor toegang |
| Trainer JO13-1, eigen leeftijdsgroep Senioren | Nee | Een staffunctie is geen spelersrelatie |

De voorbeelden veronderstellen dat JO13-1 en JO13-2 aan de rol zijn toegewezen en dat er geen andere toegangsrechten gelden. Bestaande leden- en oud-ledenfilters blijven bepalend voor welke toegankelijke records de lijst toont.

## Bestaande onderdelen die we hergebruiken

- `FunctieCapabilityMap` koppelt exacte Sportlinkfuncties aan Rondo-rollen.
- `CapabilitySync` kent deze rollen toe en trekt ze in, met behoud van bestaande handmatige toekenningen en intrekkingen.
- `rondo_age_group_access` bewaart toegestane leeftijdsgroepen per rol; de instellingenpagina ondersteunt aangepaste rollen en deze toewijzing.
- `AccessControl` begrenst personen op lijsten, zoekresultaten en individuele records. Veldrechten en schrijfrechten worden afzonderlijk gecontroleerd.
- Spelersrelaties staan in `work_history`. `VolunteerStatus::is_position_current()` beoordeelt begindatum, einddatum en historische status; `get_player_roles()` bepaalt welke functies spelersfuncties zijn.

Twee relevante tekortkomingen in de huidige code:

1. De persoonsselectie gebruikt alleen `leeftijdsgroep`, waaronder een eigen implementatie in `class-rest-people.php`. Alleen de centrale recordcontrole aanpassen is dus onvoldoende.
2. `CapabilitySync::derive_from_work_history()` controleert alleen `is_current`. Voor betrouwbare automatische toekenning moeten expliciete datums en historische status ook worden verwerkt.

## Voorstel voor inrichting

We gebruiken één aangepaste rol per verantwoordelijkheidsgebied, bijvoorbeeld **Coördinator O13**. Zowel de technische als organisatorische Sportlinkfunctie kan naar diezelfde rol verwijzen. De exacte functienamen worden vóór inrichting uit actuele records gecontroleerd; voorbeelden zijn geen bewezen productiewaarden.

De beheerder legt bij de rol twee zaken vast:

- de leeftijdsgroep(en), met de bestaande instelling;
- de bijbehorende teams, geselecteerd uit bestaande teamrecords.

Teamkoppelingen worden bewaard met stabiele Rondo-team-ID's, in een WordPress-optie per rol, bijvoorbeeld `rondo_team_access`. Daarmee blijven naamswijzigingen veilig en worden JO- en MO-teams alleen meegenomen wanneer ze daadwerkelijk onder die coördinator vallen. De UI toont de geselecteerde teamnamen. Een teamkeuze verleent zelf geen schrijfrechten.

De bestaande Functies-instelling koppelt Sportlinkfuncties aan deze rol. De automatische toekenning verloopt via de bestaande synchronisatie; er komt geen tweede systeem voor individuele uitzonderingen.

Nieuwe of vervangen teams worden bij de seizoensinrichting aan de rol gekoppeld. Automatische teamselectie op basis van alleen een naam of de leeftijden van spelers is geen betrouwbare toegangsregel: juist dispensatie vertekent die laatste bron. Als later automatische teamindeling gewenst is, vergt dat gecontroleerde Sportlink-teammetadata.

## Uitwerking van de toegang

Breid de centrale persoonsselectie uit met de unie van toegestane leeftijdsgroepen en actuele spelers van toegewezen teams. Combineer de toewijzingen van alle relevante rollen van de gebruiker en verwijder dubbele personen.

Gebruik WordPress-query's en `Rondo\Fields\Fields` voor de selectie. Een teamrelatie telt alleen als de relatie naar een bestaand `team` verwijst, de functie een erkende spelersfunctie is en de periode actueel is. Commissie-, trainers-, historische en toekomstige relaties geven via dit mechanisme geen toegang.

Laat `can_view_person()`, `scope_person_query_args()` en `visible_person_ids_or_null()` dezelfde regel gebruiken. Ook de bestaande SQL-lijst in `class-rest-people.php` moet de centrale toegestane selectie gebruiken; voeg daar geen afzonderlijke teamregel aan toe. Pas de afbakening toe vóór paginering en tellingen.

Neem alle afnemers van `get_permitted_age_groups()` mee in de controle. Alleen teamtoegang mag niet onbedoeld als een gewoon ledenaccount worden behandeld. Rollen met het geïsoleerde Kaderlijstrecht blijven uitgesloten van algemene persoonstoegang, conform de bestaande regeling. De aparte Kaderlijstweergave wordt niet uitgebreid met nieuwe soorten personen.

Zoeken, Relaties, directe profiellinks, exports en de getypeerde MCP-abilities moeten dezelfde persoonsgrens hanteren. Bestaande filters mogen de resultaten verder beperken. Zet voor coördinatoren niet automatisch het bestaande leeftijdsgroepfilter aan: dat zou dispensatiespelers opnieuw verbergen.

Bestaande clubbrede rechten blijven voorgaan. De nieuwe coördinatorrol krijgt zelf alleen de benodigde leestoegang. Financiële gegevens, supportinformatie, wijzigingen en verwijderen volgen de bestaande afzonderlijke rechten. Ouderschap levert geen nieuwe toegang tot andere ouderrecords op.

## Automatische toekenning en vervallen van toegang

Hergebruik de datumcontrole voor actieve functies bij het afleiden van Sportlinkrollen. Een beëindigde coördinatorfunctie verleent na verwerking geen automatische jaarlaagrol meer; een toekomstige functie nog niet. Bestaande expliciete handmatige overrides blijven gelden.

Controleer de volledige keten: reguliere synchronisatie, individuele synchronisatie en opnieuw toepassen van de functie-instellingen. Datumgrenzen moeten ook zonder een gewijzigde Sportlink-payload worden verwerkt; controleer hiervoor de geplande rolherberekening en voeg die zo nodig toe. Maak geen belofte van onmiddellijke verwerking vóórdat de syncvertraging bekend is.

Een teamwisseling verandert de teamgrond voor toegang zodra de nieuwe relaties zijn verwerkt. De leeftijdsgroep kan nog steeds zelfstandig toegang geven. Begin met caching binnen één request; eventuele persistente caching moet correct vervallen bij wijzigingen in rollen, instellingen, personen en teamrelaties én bij datumgrenzen.

## Implementatie en verificatie

1. Breid de instellingen voor roltoegang uit met gevalideerde team-ID's en de bijbehorende teamkeuze. Bewaar de bestaande leeftijdsgroepconfiguratie compatibel.
2. Implementeer de gezamenlijke persoonsselectie en sluit alle leesroutes daarop aan. Controleer navigatie voor rollen met leeftijdsgroep-, team- of gecombineerde toegang.
3. Maak de automatische functietoewijzing datumbewust en controleer de sync- en intrekkingspaden, inclusief handmatige overrides.
4. Test de voorbeeldmatrix, gecombineerde rollen, ontbrekende/verwijderde teams, teamwissels, historische en toekomstige functies, verlopen datumgrenzen en toegang buiten de jaarlaag. Controleer ook paginering, tellingen, exports, directe profielen en veldafscherming.
5. Draai de relevante PHP-tests, JavaScript-tests, lint en build. Werk de ontwikkelaarsdocumentatie voor toegangscontrole en instellingen bij; verhoog versie en changelog bij de implementatiemijlpaal.
6. Inventariseer vóór productie-inrichting de exacte functies, rollen, leeftijdsgroepen en team-ID's via de getypeerde Rondo-abilities waar die volstaan. Leg de concrete wijzigingen voor aan de beheerder voordat bestaande productierechten worden aangepast.
7. Lever de implementatie via een aparte PR. Na merge en geslaagde productie-deploy: verifieer met een coördinatoraccount dat een dispensatiespeler via zoeken én Relaties vindbaar en te openen is, en een persoon buiten beide selecties ontoegankelijk blijft. Verifieer ook intrekking na het eindigen van de functie.

De implementatie gebruikt `team_roles` naast `roles` op het bestaande instellingenendpoint. De uurlijkse taak herberekent uitsluitend rollen met jaarlaag- of teamtoegang en behoudt andere rollen. Browsercontroles met fictieve gegevens bevestigen de teamkeuze, herladen, zoeken, profielen en CSV-export. De concrete rol-, functie- en teamkoppelingen op productie zijn nog niet gewijzigd.
