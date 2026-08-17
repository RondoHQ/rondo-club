# PRD: Sponsorbedrijven en contactpersonen

**Status:** Voorstel  
**Datum:** 2026-08-17  
**Eigenaar:** Sponsorbeheer  
**Raakt:** Rondo Club, Rondo Sync, personen, toegangspassen, Club TV, Sponsit en Laposta

## 1. Samenvatting

Rondo behandelt een sponsor nu als een `person` met `is_sponsor=true`. Daardoor staan een bedrijf,
de contactpersoon, privégegevens, bedrijfsgegevens, de sponsorcategorie en het logo op hetzelfde
record. Dit werkt niet zodra een bedrijf meerdere contactpersonen heeft en is geen goede basis voor
Club TV.

We introduceren daarom **Sponsorbedrijven** als zelfstandig contenttype. Een sponsorbedrijf bevat
de bedrijfsnaam, het bedrijfsadres, het logo en de sponsorrol. Personen blijven personen en kunnen
via een expliciete relatie als contactpersoon aan één of meer sponsorbedrijven worden gekoppeld.

De sponsorbeheerder kan vervolgens:

- sponsorbedrijven aanmaken, wijzigen en archiveren;
- bestaande personen als contactpersoon koppelen;
- nieuwe externe contactpersonen aanmaken en direct koppelen;
- de rol van een contactpersoon en diens toegangspas beheren;
- bedrijfslogo's gebruiken in Club TV zonder persoonsgegevens vrij te geven.

## 2. Aanleiding en productiebeeld

Een read-only inventarisatie van productie op 17 augustus 2026 laat zien:

- 225 personen met een actieve sponsorrol;
- 205 daarvan zijn externe contacten en 20 zijn ook lid/ouder;
- 196 genormaliseerde, niet-lege bedrijfsnamen;
- 15 sponsorpersonen zonder bedrijfsnaam;
- 13 bedrijfsnamen komen bij meerdere personen voor, samen goed voor 27 personen;
- één dubbel bedrijf heeft tegenstrijdige sponsorrollen;
- slechts 8 sponsorpersonen hebben nu een uitgelichte afbeelding;
- 134 sponsorpersonen zijn Businessclub AWC en 91 AWC Sponsor.

Dit betekent dat een één-op-één omzetting 225 dubbele of foutieve bedrijven zou kunnen maken.
Migratie moet personen groeperen op de stabiele Sponsit-bedrijfs-ID en daarna pas op opgeschoonde
bedrijfsnaam. Twijfelgevallen moeten vóór de omzetting worden beoordeeld.

Voorbeeld: persoon 1154, Geert Sanders, heeft nu bedrijfsnaam `Sterre - Arend B.V.`, de rol
Businessclub AWC en zowel een privé-adres als een hoofdadres. In het doelmodel blijft Geert een
persoon en wordt hij als **Contactpersoon** gekoppeld aan **Arend BV**. Alleen het hoofdadres gaat
naar het bedrijf; het privé-adres blijft bij Geert.

## 3. Doelen

- Bedrijf en mens als afzonderlijke records beheren.
- Meerdere contactpersonen per sponsorbedrijf ondersteunen.
- Eén persoon aan meerdere sponsorbedrijven kunnen koppelen.
- Sponsorbeheer zelfstandig bedrijven en contactrelaties laten beheren.
- `Sponsorrol` van de persoon naar het sponsorbedrijf verplaatsen.
- Sponsorlogo's veilig en direct beschikbaar maken voor Club TV.
- Bestaande sponsor-toegangspassen tijdens de migratie geldig houden.
- De Sponsit-bron-ID's behouden voor een toekomstige of bestaande synchronisatie.

## 4. Niet in de eerste versie

- Een sponsorportaal waarin bedrijven zelf gegevens of advertenties beheren.
- Contracten, facturen, omzet, pakketten of uitgebreide CRM-pijplijnen.
- Automatische verrijking van logo's of adressen vanaf externe websites.
- Bewijs van vertoning of facturatie op basis van Club TV-weergaven.
- Het definitief verwijderen van oude personen tijdens de eerste migratie.

## 5. Voorgesteld gegevensmodel

### 5.1 Sponsorbedrijf

Nieuw privé WordPress-contenttype: `rondo_sponsor` met eigen rechten en een eigen Rondo REST-API.
Het generieke WordPress REST-endpoint blijft uitgeschakeld.

| Veld | Type | Verplicht | Toelichting |
|---|---|---:|---|
| Naam | Titel | Ja | Officiële/weergegeven bedrijfsnaam |
| Sponsorrol | Keuze | Ja | `Businessclub AWC` of `AWC Sponsor` |
| Adres | Samengesteld veld | Nee | Straat, huisnummer, toevoeging, postcode, plaats, land |
| Logo | Media/uitgelichte afbeelding | Nee | Transparante PNG/SVG/WebP heeft voorkeur |
| Status | WordPress-status | Ja | Actief (`publish`) of gearchiveerd (`draft`) |
| Sponsit bedrijfs-ID | Tekst | Nee | De huidige `sponsit_contact_id`; uniek indien gevuld |
| Migratiebron | Tekst | Nee | Legacy person-ID's voor controle, herhaalbaarheid en herstel |

De eerste versie gebruikt één bedrijfsadres. Website, algemeen e-mailadres, telefoonnummer,
contractperiode en advertentiegewicht kunnen later worden toegevoegd zonder het model te wijzigen.

### 5.2 Relatie tussen sponsorbedrijf en persoon

De canonieke relatie wordt als een native, genummerde repeater op het sponsorbedrijf opgeslagen.
Rondo toont dezelfde relatie vanuit beide kanten, maar bewaart geen tweede handmatig te
synchroniseren kopie op de persoon.

| Veld | Type | Verplicht | Toelichting |
|---|---|---:|---|
| Persoon | Relatie naar `person` | Ja | Bestaand of nieuw extern contact |
| Contactrol | Keuze/tekst | Ja | Standaard `Contactpersoon`; later bv. administratie of directie |
| Primair contact | Ja/nee | Ja | Maximaal één primair contact per bedrijf |
| Krijgt sponsorpas | Ja/nee | Ja | Recht op de pas die hoort bij de sponsorrol van het bedrijf |
| Primaire pasrelatie | Ja/nee | Ja | Nodig als deze persoon via meerdere bedrijven pasrecht heeft |
| Sponsit persoon-ID | Tekst | Nee | De huidige `sponsit_person_id` |

Een persoon mag aan meer dan één sponsorbedrijf gekoppeld zijn. Als meerdere relaties een pas
geven, moet één relatie als primaire pasrelatie worden aangewezen; zonder die keuze wordt geen
willekeurige sponsorrol gekozen.

### 5.3 Waarom geen bedrijf als persoon

Het bestaande `person`-type blijft uitsluitend voor mensen en externe contactpersonen. Zo blijven
persoonsrechten, Sportlink-sync, privacy, huishoudrelaties en naamvelden voorspelbaar. De bestaande
persoon-tot-persoonrelaties worden niet hergebruikt: een sponsorcontact is een relatie tussen twee
verschillende domeinen met eigen velden zoals primair contact en pasrecht.

## 6. Gebruikerservaring

### 6.1 Nieuwe hoofdpagina Sponsorbedrijven

De sponsorbeheerder krijgt een menu-item **Sponsorbedrijven** met:

- zoeken op bedrijfsnaam en contactpersoon;
- filters op sponsorrol, actief/gearchiveerd en logo aanwezig/ontbreekt;
- lijstkolommen voor logo, naam, sponsorrol, primair contact en status;
- knop **Sponsorbedrijf toevoegen**.

### 6.2 Detailpagina sponsorbedrijf

De detailpagina toont bovenaan logo, bedrijfsnaam, sponsorrol en status. Daaronder staan het adres
en de contactpersonen. Vanuit dezelfde pagina kan de beheerder:

- een bestaand persoon zoeken en koppelen;
- een nieuw extern contact aanmaken en koppelen;
- contactrol, primair contact en pasrecht wijzigen;
- een contact ontkoppelen zonder de persoon te verwijderen;
- het bedrijf archiveren.

Een bedrijf met actieve Club TV-content of contactrelaties wordt standaard gearchiveerd en niet
hard verwijderd.

### 6.3 Persoonsdetail

Op een persoon verschijnt een blok **Sponsorcontact voor** met de gekoppelde bedrijven, de
contactrol, het primaire-contactlabel en eventueel pasrecht. Een sponsorbeheerder kan hier ook een
bedrijf koppelen of de relatie bewerken.

Het huidige veld `Sponsorrol` en de huidige sponsorbadge verdwijnen van de persoon. De badge wordt
vervangen door bijvoorbeeld `Contactpersoon · Arend BV`. De bestaande personenfilter `Sponsor`
wordt vervangen door `Sponsorcontact`.

## 7. Rechten

| Handeling | Sponsorbeheerder | Ledenadministratie/admin |
|---|---:|---:|
| Sponsorbedrijven bekijken | Ja | Ja |
| Sponsorbedrijf maken/wijzigen/archiveren | Ja | Ja |
| Bestaande personen zoeken en koppelen | Ja | Ja |
| Nieuw extern contact maken | Ja | Ja |
| Contactgegevens van een gekoppeld extern contact wijzigen | Ja | Ja |
| Gegevens van een lid/ouder wijzigen | Nee | Volgens bestaande rechten |
| Persoon ontkoppelen | Ja | Ja |
| Persoon verwijderen | Nee | Volgens bestaande rechten |
| Sponsorcontent in Club TV beheren | Volgens bestaande `sponsorbeheer`-rechten | Ja |

Een sponsorbeheerder krijgt dus geen algemeen personenbeheer. Bij een bestaand lid kan die alleen
de sponsorrelatie beheren. Bij een extern contact dat aan een sponsor is gekoppeld, mogen naam en
contactgegevens worden onderhouden. Ontkoppelen verwijdert nooit automatisch de persoon.

## 8. API en technische grenzen

De eerste implementatie levert een aparte controller onder `/rondo/v1/sponsors` voor lijst,
detail, aanmaken, wijzigen en archiveren. Relatiewijzigingen zijn onderdeel van een gevalideerde,
atomaire sponsorupdate. De personen-API verrijkt een persoon met een read-only lijst
`sponsor_relationships`.

Belangrijke regels:

- alle opslag gebruikt WordPress-posts, postmeta, media en de native Rondo-veldlaag;
- geen custom databasetabellen;
- `sponsit_contact_id` is uniek op sponsorbedrijven indien aanwezig;
- één primair contact per sponsorbedrijf;
- één primaire sponsorpasrelatie per persoon;
- hard verwijderen wordt geweigerd zolang relaties of Club TV-content bestaan;
- publieke Club TV-responses bevatten alleen bedrijfsnaam en logo, nooit contactgegevens;
- wijzigingen in relaties legen relevante personen-, pas- en Club TV-caches.

## 9. Gevolgen voor toegangspassen

Een sponsorpas blijft bij een persoon horen, omdat de QR-code en Wallet-pass persoonsgebonden zijn.
De geldigheid wordt voortaan afgeleid van een actieve sponsorrelatie met `krijgt sponsorpas=true`.
De pasvariant komt van de `Sponsorrol` van het gekoppelde bedrijf.

Bij migratie krijgt iedere huidige sponsorpersoon standaard pasrecht, zodat geen bestaande pas
onbedoeld vervalt. Daarna kan sponsorbeheer het per contact corrigeren. Bestaande tokens en
Wallet-links blijven op het persoonrecord staan; alleen de berekening van de variant verandert.

## 10. Aanpassingen in Rondo Sync

Rondo Sync importeert Sponsit nu wekelijks naar personen. Per Sponsit-contactpersoon schrijft de
pipeline `is_sponsor`, `company_name`, `sponsor_pass_variant`, `sponsit_contact_id` en
`sponsit_person_id` naar een `person`. Bij een verdwenen Sponsit-record verwijdert de pipeline de
sponsorrol weer van die persoon. Deze logica moet tegelijk met het nieuwe model veranderen.

De nieuwe mapping wordt:

- één Sponsit **contact/company** wordt één `rondo_sponsor`, gekoppeld op `sponsit_contact_id`;
- de Sponsit bedrijfsnaam, sponsorstatus, sponsorrol en bedrijfsadressen gaan naar dat bedrijf;
- iedere Sponsit **person** wordt gekoppeld aan een bestaande of nieuwe Rondo-persoon op
  `sponsit_person_id`, met e-mail plus identiteit alleen als gecontroleerde fallback;
- de koppeling wordt een sponsorcontactrelatie met standaardrol `Contactpersoon` en pasrecht;
- een bedrijf zonder personen blijft een geldig sponsorbedrijf, maar krijgt geen fictieve persoon;
- een inactief Sponsit-bedrijf wordt gearchiveerd en zijn pasrelaties worden inactief; de personen
  zelf blijven bestaan;
- handmatig in Rondo gemaakte sponsorbedrijven zonder Sponsit bedrijfs-ID worden nooit door de
  sync gearchiveerd of overschreven.

De sync gebruikt na de cutover de nieuwe `/rondo/v1/sponsors`-API naast de bestaande personen-API.
De dry-run toont afzonderlijk bedrijven, personen, relaties, archiveringen en quarantaines. De
Laposta-stap blijft gevoed vanuit Sponsit, maar bepaalt `islid` en de gekozen pasrelatie via het
nieuwe Rondo-model in plaats van `is_sponsor` op de persoon.

### Veilige omschakelvolgorde

1. Rondo Club uitrollen met het nieuwe model en tijdelijk ondersteuning voor het oude model.
2. Rondo Sync gereedmaken en testen tegen fixtures en een read-only productie-preview.
3. De wekelijkse Sponsit apply-run tijdens de daadwerkelijke migratie tijdelijk pauzeren.
4. Bedrijven en relaties migreren en aantallen/bron-ID's controleren.
5. Rondo Sync naar de nieuwe endpoints omschakelen en eerst een dry-run uitvoeren.
6. Eén gecontroleerde apply-run uitvoeren en daarna pas de wekelijkse planning hervatten.
7. Na een stabiele overgangsperiode de oude persoonsvelden en writes verwijderen.

Een langdurige dual-write naar zowel bedrijven als legacy persoonsvelden wordt vermeden: twee
schrijfbronnen maken conflicten waarschijnlijk. De compatibiliteitsperiode is daarom vooral
**dual-read** in Rondo Club, met één kort en gecontroleerd omschakelmoment voor Rondo Sync.

## 11. Gevolgen voor Club TV

Club TV verwijst voortaan naar een `rondo_sponsor` in plaats van naar een sponsorpersoon. De
sponsorkeuzelijst toont alleen actieve bedrijven en geeft alleen naam en logo aan de speler door.

Voor de eerder ontworpen wedstrijden-slide geldt als vervolgstap:

- twee sponsorlogo's rechtsboven en vier linksonder;
- alleen actieve sponsorbedrijven met een bruikbaar logo doen mee;
- geen dubbel logo binnen dezelfde slide;
- eerlijke rotatie over tijd;
- een ontbrekend of afgekeurd logo laat een positie leeg en veroorzaakt nooit een kapot beeld.

De huidige Club TV-velden en bestaande content met `sponsor_person_id` worden tijdens de cutover
omgezet naar `sponsor_id`. Tot de migratie is afgerond kan de manifestresolver beide velden lezen.

## 12. Migratie

### 12.1 Eerst een dry-runrapport

Een herhaalbare WP-CLI-migratie maakt eerst uitsluitend een rapport met vier groepen:

1. **Automatisch:** één duidelijke Sponsit bedrijfs-ID, bedrijfsnaam, rol en hoofdadres.
2. **Samenvoegen:** meerdere personen met dezelfde Sponsit bedrijfs-ID of genormaliseerde naam.
3. **Handmatig controleren:** ontbrekende bedrijfsnaam, verschillende sponsorrollen, twijfel over
   privé- versus bedrijfsadres of een afbeelding die mogelijk een portret is.
4. **Pseudo-persoon:** het huidige persoonrecord lijkt feitelijk alleen een bedrijfsnaam te zijn.

Het rapport bevat de voorgestelde bedrijfsnaam, contactpersonen, rol, adres, logo en bron-ID's. De
sponsorbeheerder kan overrides vastleggen, waaronder `Sterre - Arend B.V.` naar `Arend BV`.

### 12.2 Uitvoering

Na goedkeuring:

1. maak of hergebruik het sponsorbedrijf op basis van Sponsit bedrijfs-ID;
2. verplaats sponsorrol en het goedgekeurde bedrijfsadres;
3. verplaats alleen een afbeelding die als bedrijfslogo is bevestigd;
4. koppel de echte personen als contactpersonen;
5. zet voor huidige sponsorpersonen `krijgt sponsorpas=true`;
6. zet Club TV-verwijzingen om;
7. controleer aantallen, unieke bron-ID's, passen en relaties;
8. markeer legacy sponsorvelden als alleen-lezen gedurende één overgangsversie.

Pseudo-personen worden eerst gearchiveerd en pas in een latere, afzonderlijk goedgekeurde opruiming
verwijderd. Echte leden, ouders en externe contactpersonen worden nooit door deze migratie
verwijderd.

### 12.3 Herhaalbaarheid en herstel

De migratie is idempotent: opnieuw uitvoeren maakt geen dubbele bedrijven of relaties. Elk bedrijf
onthoudt zijn legacy person-ID's en Sponsit-ID. Voor de cutover wordt een JSON/CSV-rapport bewaard,
zodat iedere omzetting controleerbaar en zo nodig gericht terug te draaien is.

## 13. Fasering

### Fase 0 — beslissingen en data-opschoning

- onderstaande open keuzes bevestigen;
- dry-runrapport bouwen en samenvoegingen beoordelen;
- Rondo Sync-mapping en verwachte bedrijf-/persoon-/relatie-aantallen vastleggen;
- vaststellen welke bestaande afbeeldingen echt logo's zijn.

**Resultaat:** goedgekeurde migratiemapping zonder productiewijzigingen.

### Fase 1 — sponsorbedrijf en rechten

- contenttype, velden, REST-controller en rechten toevoegen;
- lijst- en detailpagina bouwen;
- bedrijf aanmaken, wijzigen en archiveren testen.

**Resultaat:** bedrijven kunnen naast het oude sponsormodel worden beheerd.

### Fase 2 — contactrelaties

- relatieveld en validatie toevoegen;
- koppelen vanuit bedrijf en persoon;
- beperkt aanmaken/bewerken van externe contacten;
- primaire contact- en pasregels afdwingen.

**Resultaat:** Geert Sanders kan correct als contactpersoon van Arend BV worden vastgelegd.

### Fase 3 — migratie en passen

- Rondo Sync apply-run tijdelijk pauzeren;
- goedgekeurde mapping uitvoeren;
- sponsorpasberekening omzetten;
- aantallen en alle bestaande passvarianten controleren;
- Rondo Sync eerst in preview en daarna gecontroleerd tegen de nieuwe API uitvoeren;
- legacy velden gedurende de overgang blijven lezen en daarna de sync hervatten.

**Resultaat:** sponsorbedrijven zijn de bron; bestaande sponsorcontacten verliezen geen toegang.

### Fase 4 — Club TV en overige consumenten

- Club TV-content en sponsorkeuzes naar bedrijven omzetten;
- automatische zes-logo-rotatie toevoegen;
- personenlijsten, zoekresultaten en badges aanpassen;
- API- en beheerdocumentatie publiceren.

**Resultaat:** Club TV gebruikt bedrijfslogo's en lekt geen persoonsgegevens.

### Fase 5 — opruiming

- na minimaal één stabiele release legacy `is_sponsor`, `company_name` en
  `sponsor_pass_variant` niet meer gebruiken voor sponsorlogica;
- alleen goedgekeurde pseudo-personen verwijderen;
- compatibiliteitscode en oude Club TV-referenties verwijderen.

**Resultaat:** één helder sponsormodel zonder dubbele bronnen.

Iedere implementatiefase krijgt eigen tests, versie- en changelogwijziging, commit en productie-
controle. De data-migratie krijgt daarnaast een aparte read-only verificatie na uitvoering.

## 14. Test- en acceptatiecriteria

- [ ] Sponsorbeheer kan een bedrijf met naam, adres, logo en sponsorrol maken en wijzigen.
- [ ] Sponsorbeheer kan een bestaand persoon koppelen zonder diens lidgegevens te kunnen wijzigen.
- [ ] Sponsorbeheer kan een nieuw extern contact maken en koppelen.
- [ ] Een bedrijf ondersteunt meerdere contacten en een persoon meerdere bedrijven.
- [ ] Er kan per bedrijf maar één primair contact zijn.
- [ ] Een persoon met pasrecht krijgt de variant van het juiste bedrijf.
- [ ] Alle huidige sponsorpersonen behouden na migratie hun bestaande pasvariant, behalve expliciet
      goedgekeurde correcties.
- [ ] Persoon 1154 is gekoppeld aan Arend BV en zijn privé-adres is niet naar het bedrijf gekopieerd.
- [ ] Club TV ontvangt alleen sponsor-ID, naam en logo.
- [ ] Oude sponsorcontent blijft tijdens de overgang afspeelbaar.
- [ ] De migratie kan tweemaal worden uitgevoerd zonder dubbele bedrijven of relaties.
- [ ] Een Rondo Sync dry-run toont bedrijven, personen, relaties en archiveringen afzonderlijk.
- [ ] Een tweede Rondo Sync apply-run maakt geen duplicaten en rapporteert alles als ongewijzigd.
- [ ] Een inactieve Sponsit-sponsor archiveert het bedrijf en verwijdert geen persoon.
- [ ] Een handmatig sponsorbedrijf zonder Sponsit-ID blijft onaangeraakt door Rondo Sync.
- [ ] Laposta behoudt bedrijfsnaam, sponsorvariant, bron-ID's en correcte `islid`-waarde.
- [ ] Archiveren verbreekt geen historische koppelingen; hard verwijderen met verwijzingen wordt
      geweigerd.
- [ ] PHP-tests, frontendtests, lint en productiebuild zijn groen.

## 15. Beslissingen nodig vóór implementatie

1. **Pasrecht per contact — aanbevolen:** voeg `Krijgt sponsorpas` toe aan iedere relatie en zet dit
   tijdens migratie voor alle huidige sponsorpersonen aan. Alternatief is iedere contactpersoon
   automatisch een pas geven; dat is eenvoudiger maar minder beheersbaar.
2. **Contactrol — aanbevolen:** start met vrij invoerbare rol met `Contactpersoon` als standaard.
   Alternatief is een vaste keuzelijst; die is consistenter maar zal snel uitzonderingen missen.
3. **Club TV-deelname — aanbevolen:** ieder actief bedrijf met logo doet automatisch mee aan de
   zes-logo-rotatie. Later kan advertentiegewicht of uitsluiting worden toegevoegd. Alternatief is
   nu al een extra aan/uit-veld, wat meer beheer maar ook meer controle geeft.
