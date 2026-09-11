# VOG uploaden en automatisch controleren

**Status:** Uitgewerkt voorstel; nog niet geïmplementeerd.
**Datum:** 11 september 2026.
**Basis:** Rondo Club `main` op `95209387`.

## Doel en aanbevolen eerste versie

Een lid levert de originele digitale VOG in via **Profiel → Mijn VOG**. Rondo
controleert het bestand bij de officiële GAAV-dienst, leest de gegevens uit en
vergelijkt die met het lid en de ingestelde clubvoorwaarden. Bij een volledige
match wordt de bestaande VOG-datum bijgewerkt. Twijfelgevallen verschijnen bij
de VOG-coördinator. Een lopende controle tast een eerder goedgekeurde VOG niet aan.

De eerste versie ondersteunt originele digitale PDF's. Voor een papieren VOG blijft
de bestaande controle door de coördinator beschikbaar. Een foto, scan of opnieuw
opgeslagen PDF kan niet via deze flow als originele digitale VOG worden goedgekeurd.
Aanvragen bij Justis, de IVA-controle en het algemene vrijwilligersbeleid vallen
buiten deze wijziging.

## Wat de technische proef heeft aangetoond

De door de gebruiker aangeleverde recente VOG is na expliciete toestemming eenmalig
naar `https://www.validatie.nl/api/valideer/` gestuurd. De dienst antwoordde met
HTTP 200 en `{"response_code":0}`: authentiek en integer. Er was voor deze proef
geen API-sleutel of account nodig. Dit bewijst de werking voor dit ene document;
beschikbaarheidsafspraken en volumelimieten zijn hiermee niet vastgesteld.

Naam, tussenvoegsel, geboortedatum, afgiftedatum, kenmerk, organisatie, functie en
de daadwerkelijk geselecteerde screeningscode konden lokaal worden uitgelezen.
De tweede pagina bevat een algemene lijst met functieaspecten: die lijst mag
nooit als de selectie voor deze VOG worden behandeld.

De bestaande PHP-library `smalot/pdfparser` faalde op de beveiligde PDF. `pypdf`
kon hetzelfde originele bestand wel lezen. Het origineel is niet aangepast. De
test-VOG, identiteit, geboortedatum en het documentkenmerk worden niet in de
repository, fixtures of dit voorstel opgenomen. Er zijn geen Rondo-records gewijzigd.

### Aansluiting op de bestaande code

| Onderdeel | Huidige situatie | Voorgestelde uitbreiding |
|---|---|---|
| `src/pages/Profile/ProfileVog.jsx` | Eigen VOG-status en informatie | Upload en voortgang van de eigen inzending |
| `includes/class-rest-volunteer.php` | `GET /vog/me`; IVA-upload en private documentopslag | Bestaande eigen VOG-respons uitbreiden met minimale inzendstatus |
| `includes/class-rest-vog.php` | VOG-instellingen, e-mails en aanvraagregistratie | Coördinatorroutes en clubvoorwaarden |
| `src/pages/VOG/` | VOG-overzichten | Tab **Te beoordelen**, met reden en documentinzage |
| `includes/config/field-registry.php` | Canoniek `datum_vog`, opslag `datum-vog` | Datum pas na afgeronde goedkeuring schrijven via `Fields` |
| `rondo-sync/lib/reverse-sync-sportlink.js` | Mapping voor `datum_vog` naar Sportlink | Bestaande wijzigingsdetectie en terugkoppeling testen; geen nieuwe rechtstreekse Sportlink-write |

## Wat het lid ziet

Op **Mijn VOG** blijft de geldigheid van de bestaande registratie zichtbaar.
Daaronder staat **Digitale VOG inleveren**, ook als iemand al een geldige VOG heeft.

Tekst bij de upload:

> Upload de originele PDF uit MijnOverheid. We controleren deze bij de officiële
> validatiedienst van Justid en vergelijken de gegevens met je profiel. Na
> afronding verwijderen we het bestand. Bewaar zelf je originele VOG.

De server accepteert maximaal 10 MB en maximaal 5 pagina's. De eerste versie
herkent het geteste documentformaat; andere formaten krijgen geen automatische
goedkeuring. De knop **Uploaden en controleren** maakt de verzending naar Justid
duidelijk. Er komt geen vrij invoerveld voor de VOG-datum bij het lid.

| Uitkomst | Tekst en vervolg |
|---|---|
| Volledig goedgekeurd | **Je VOG is goedgekeurd.** Toon de afgiftedatum en de bestaande clubtermijn. |
| Inhoudelijke twijfel | **De VOG-coördinator beoordeelt je document.** Toon een begrijpelijke reden zonder gegevens van een ander lid. |
| Tijdelijke storing | **De controle lukt nu niet. We proberen het opnieuw.** Geen afwijzing en geen wijziging van de geldigheid. |
| Origineel niet bevestigd | **We kunnen dit bestand niet als originele digitale VOG bevestigen.** Vraag de originele download of verwijs naar de coördinator. |
| Afgewezen door coördinator | Toon de toegestane toelichting en de mogelijkheid opnieuw in te leveren. |

Een inzending krijgt een eigen status. **In behandeling** vervangt dus niet
**VOG geldig** als er al een geldige registratie bestaat. Herhaald klikken of
dezelfde PDF opnieuw insturen veroorzaakt geen extra goedkeuring of datumwijziging.

## Automatische goedkeuring

Alle onderstaande controles moeten slagen op de server en op hetzelfde bestand:

1. GAAV geeft de gedocumenteerde succescode `0` terug in een geldige respons.
2. De parser herkent een VOG, vindt precies één betekenisvolle waarde per vereist
   veld en gebruikt alleen de geselecteerde screeningssectie op de eerste pagina.
3. Geboortedatum, voornaamgegevens, tussenvoegsel en achternaam passen eenduidig
   bij de gekoppelde persoon. Normaliseren van spaties, Unicode en hoofdletters
   mag; geen automatische goedkeuring op basis van roepnaam, typefouttolerantie,
   alleen initialen of een gedeeltelijke naam. Ontbrekende of afwijkende officiële
   voornamen in Sportlink gaan naar de coördinator; we veranderen het profiel niet.
4. Organisatie en functie komen overeen met een expliciet ingestelde clubregel.
   De geselecteerde codes bevatten alle voor die regel vereiste codes. Niet iedere
   functie krijgt automatisch dezelfde eis.
5. De afgiftedatum is een echte kalenderdatum, niet toekomstig, valt binnen de
   bestaande clubtermijn van drie jaar en vervangt geen nieuwere geregistreerde datum.
6. Bij het opslaan kloppen de accountkoppeling, inzendversie, clubregel en actuele
   persoonsgegevens nog steeds. Een oude taak mag een nieuwere inzending niet overschrijven.

### Clubvoorwaarden

Aanbevolen compacte instelling onder **Instellingen → VOG**: toegestane
organisatienamen, functienaam en verplichte screeningscodes per goedkeuringsregel.
Alleen beheerders mogen deze regels wijzigen. Een regel heeft een versie, zodat
herleidbaar is welke voorwaarden bij een goedkeuring zijn toegepast.

Voor AWC is **Vrijwilliger / Sport Vereniging Alverna-Wijchen Combinatie / code 84**
een kandidaatregel op basis van de proef. Het document bewijst niet dat code 84
voor elke AWC-vrijwilligersfunctie voldoende is. Die beleidskeuze moet vóór
activering door de club worden bevestigd. Zonder ingestelde passende regel kan
echtheid wel automatisch worden vastgesteld, maar blijft goedkeuring handmatig.

## Afhandeling van GAAV en twijfelgevallen

| GAAV-code | Betekenis volgens specificatie | Rondo-afhandeling |
|---|---|---|
| `0` | Authentiek en integer | Voer de inhoudelijke controles uit; dit is op zichzelf geen goedkeuring. |
| `1` | Bekend, niet integer | Blokkeer goedkeuring van dit digitale bestand; vraag origineel. |
| `2` | Onbekend | Geen automatische goedkeuring; originele download of afzonderlijke controle nodig. |
| `3`, `4`, `5`, `7` | Controle niet mogelijk of fout in onderliggende dienst | Bewaar als technische fout, maximaal twee automatische retries na de eerste poging, na 5 en 30 minuten. Daarna coördinatoractie. |
| `6` | Ongeldige handtekening | Blokkeer goedkeuring van dit digitale bestand; vraag origineel. |
| Overig, time-out, HTTP-fout of ongeldige JSON | Onverwachte of ontbrekende uitslag | Nooit als succes interpreteren; begrensd opnieuw proberen en vervolgens coördinatoractie. |

De coördinator ziet in **VOG → Te beoordelen** het gekoppelde lid, afgiftedatum,
echtheidsresultaat en concrete inhoudelijke verschillen. De PDF is alleen via een
afgeschermde documentroute in te zien. Documenttekst wordt als tekst weergegeven,
nooit als HTML uitgevoerd of als instructie verwerkt.

**Goedkeuren** is in deze digitale flow alleen beschikbaar na GAAV-code `0`.
De coördinator kan een inhoudelijke twijfel oplossen met een verplichte korte
toelichting en controle van persoon, functie, organisatie en profiel. **Afwijzen**
en **Opnieuw controleren** zijn aparte acties. Een ongunstige echtheidsuitslag kan
niet met dezelfde knop worden overruled. Een eventueel gecontroleerd papieren
origineel blijft een afzonderlijke bestaande handmatige registratie en wordt niet
als GAAV-goedgekeurd gelabeld.

## PDF-lezer en uitvoering

**Voorkeur: een kleine lokale Python-helper met gepinde `pypdf` en AES-ondersteuning.**
Dit is de lezer waarmee de proef al slaagde. Alleen de VOG-flow gebruikt deze
helper; de IVA-parser blijft ongewijzigd. De helper levert begrensde, gestructureerde
JSON aan PHP en doet zelf geen netwerkverkeer.

De productieserver is op 11 september alleen-lezen gecontroleerd: Python 3.14.7
en `timeout` staan op het CLI-pad; `proc_open` is beschikbaar in PHP CLI.
`pdftotext` staat niet op het CLI-pad en `pypdf` en `cryptography` zijn niet
geïnstalleerd. Beschikbaarheid in het PHP-webproces is nog niet bewezen.

Bij implementatie komen de Python-pakketten in een geïsoleerde, versiegebonden
runtime met vastgelegde versies en hashes. De release controleert OS/ABI-compatibiliteit,
import en werking vanuit het echte PHP-webproces voordat automatische controle
wordt ingeschakeld. Geen installatie tijdens een ledenrequest. Het huidige
deploy- en rollbackproces moet de helper en bijbehorende pakketten als één release
kunnen uitrollen en terugzetten. Beschikbaarheid voor demo apart controleren.

De helper opent alleen PDFs die zonder gebruikerswachtwoord leesbaar zijn. Geen
wachtwoordraden, OCR, taalmodel of externe extractiedienst. Begrens verwerking op
10 seconden, 256 MB procesgeheugen en 256 KB uitvoer; forceer beëindiging bij
overschrijding. Bestandsnamen en documenttekst komen niet in een shellcommando.
Een ontbrekende helper of onbekend formaat leidt tot handmatige inhoudelijke
beoordeling, nooit tot goedkeuring zonder controle.

De GAAV-call gebeurt met het ongewijzigde originele bestand als multipart-veld
`file`. Alleen de vaste HTTPS-host en route zijn toegestaan, TLS-validatie blijft
aan en redirects worden niet gevolgd. Stel een time-out van 15 seconden en een
responslimiet van 64 KB in. De exacte originele bytes worden gehasht en vormen
de verbinding tussen API-resultaat, extractie en inzending. Geen conversie vóór
verzending en geen goedkeuring op grond van een door de browser aangeleverde uitslag.

## Opslag, bevoegdheden en opruimen

Gebruik een niet-openbaar WordPress-posttype `rondo_vog_submission` met native
postmeta. Er komen geen eigen tabellen. Bewaar persoonsrelatie, inzendversie,
status, bestandshash, technische resultaatcode, tijdstippen, regelversie,
goedgekeurde afgiftedatum en beoordelaar. Parsergegevens die voor beoordeling
nodig zijn worden afgeschermd opgeslagen en na afronding verwijderd; kopieer
geen geboortedatum of volledige documenttekst naar blijvende auditvelden.

Het tijdelijke origineel staat in een private directory buiten de webroot,
met willekeurige naam, naar het bestaande IVA-opslagpatroon. Alleen het gekoppelde
lid en gebruikers met VOG-bevoegdheid plus toegang tot die persoon mogen de
inzending lezen. Vrijwilligersbeheerders of IVA-goedkeurders krijgen hierdoor
geen extra toegang. Eigen uploads gebruiken uitsluitend de actuele accountkoppeling;
geen vrij meegegeven persoons-ID, geen upload namens huisgenoten. Bestaande
regels voor niet-toegelaten accounts, demo en oud-leden blijven gelden.

Na goedkeuring, afwijzing of vervangen: verwijder het document en tijdelijke
extractiegegevens. Voor onafgeronde dossiers is **30 dagen na upload** de
voorgestelde maximale bewaartermijn, zichtbaar bij de upload. Daarna verloopt de
inzending, wordt het bestand verwijderd en moet zo nodig opnieuw worden ingeleverd.
Een dagelijkse WordPress-crontaak ruimt ook verweesde bestanden op; leesroutes
weigeren toegang na de deadline, ook wanneer cron achterloopt. Controleer bij
uitrol dat de tijdelijke map niet via backups langer wordt bewaard dan dit beleid.
Bewaar het minimale controleresultaat zolang die VOG-registratie nodig is; koppel
opruimen aan bestaande persoonsverwijdering en privacyprocessen.

Een nieuwe inzending vervangt alleen de vorige nog lopende inzending, niet de
goedgekeurde datum. Per persoon wordt opslag/goedkeuring geserialiseerd met
hercontrole onder een lock, inclusief herstel na time-outs; gebruik native
WordPress-opslag voor die coördinatie. Beperk uploads en retries per account.
Dubbele bestandshashes leveren dezelfde lopende uitslag, zolang context en
persoon overeenkomen. Cache een uitslag niet als toestemming voor een ander lid.

Schrijf bij goedkeuring `datum_vog` via de native fieldlaag. Verifieer expliciet
dat wijzigingsdetectie, Sportlink-terugkoppeling, VOG-overzichten en inschrijftaken
de update verwerken. Een JSON-succes uit de nieuwe route mag niet ten onrechte
beloven dat Sportlink al is bijgewerkt.

## Voorgestelde API en componenten

Alle routes vallen onder `/rondo/v1` en gebruiken de bestaande sessie- en
noncebeveiliging en server-side persoonscontrole.

| Route | Doel en toegang |
|---|---|
| `POST /vog/upload` | Eigen PDF inleveren; server bepaalt het gekoppelde lid. |
| `GET /vog/me` | Bestaande datum/status plus minimale status van de nieuwste inzending. |
| `GET /vog/submissions` | Gefilterde, gepagineerde beoordelingslijst; alleen VOG-bevoegden. |
| `GET /vog/submissions/{id}/document` | Tijdelijke private documentinzage; geen publieke attachment-URL. |
| `POST /vog/submissions/{id}/review` | Goedkeuren/afwijzen met verwachte versie en toelichting. |
| `POST /vog/submissions/{id}/retry` | Begrensde hercontrole; alleen VOG-bevoegden. |

Nieuwe services scheiden GAAV-verkeer, PDF-extractie, inhoudelijke beslisregels
en inzendingbeheer. Houd controllers dun. Hergebruik het private opslagpatroon
zonder een brede IVA-refactor. Breid `src/api/client.js`, de profielpagina,
VOG-tabs en relevante query-invalidatie gericht uit. De eerste versie toont de
reviewwachtrij in Rondo en voegt geen nieuwe e-mailnotificaties toe.

## Uitvoering en acceptatie

1. **Lezer en beslisregels:** helper, GAAV-client en synthetische fixtures voor
   het geteste formaat. Test normale en versleutelde PDFs en gewijzigde, onbekende,
   onvolledige en dubbelzinnige documenten. Geen echte VOG in CI; mock GAAV-responsen.
2. **Opslag en routes:** eigen upload, bevoegdheden, toestanden, retries, limieten,
   vervanging, gelijktijdigheid, verwijderen en canonieke datumupdate.
3. **Interface:** upload/status op Mijn VOG, beoordelingslijst en afgeschermde
   documentinzage, heldere foutmeldingen, toetsenbordbediening en mobiel gebruik.
4. **Release:** Python-runtime samen met code testen; passende versie en changelog,
   developer-documentatie, lint, build, PHP-tests en CI/deploy. Daarna de echte
   ingelogde ervaring als lid en coördinator op productie verifiëren.

Minimale acceptatiegevallen:

- GAAV `0` + passende inhoud + bevestigde clubregel keurt precies eenmaal goed.
- Naam, geboortedatum, functie, organisatie of benodigde code wijkt af: geen
  automatische datumwijziging; concreet beoordelingsgeval.
- Algemene codelijst op pagina 2 wordt nooit als geselecteerd profiel gebruikt.
- Een echte digitale VOG van een ander lid wordt nooit automatisch goedgekeurd.
- GAAV-uitval, ongeldige response, parserfout en ontbrekende runtime laten een
  eerder geldige VOG intact; herstel en retries veroorzaken geen dubbele writes.
- Een vervangen inzending of gewijzigde accountkoppeling kan niet alsnog door
  een oude taak worden goedgekeurd. Een oudere VOG overschrijft geen nieuwere datum.
- Onbevoegde toegang tot lijst, detail, download, review en retry is geblokkeerd;
  test ook directe URL's, ingetrokken toegang en oud-leden.
- Afgeronde/verlopen documenten zijn niet meer te downloaden of publiek aanwezig.
- Na datumupdate is de bestaande Sportlink-keten getest zonder een extra
  rechtstreekse Sportlink-write in deze functionaliteit.

### Nog te bevestigen vóór automatische activering

- Is kandidaatregel **Vrijwilliger / AWC / 84** inhoudelijk voldoende voor de
  bedoelde groep? Andere functies blijven bij ontbrekende regels handmatig.
- Zijn de voorgestelde 30 dagen voor een open beoordeling passend?
- De releaseproef moet de geïsoleerde PDF-runtime vanuit het PHP-webproces bevestigen.

Dit voorstel zelf verandert geen software, clubregels, documenten of live records.
Omdat de wijziging beperkt is tot `docs/prd/`, zijn een themaversie, changelog,
developer-sitewijziging en productie-deploy hiervoor niet van toepassing.

## Bronnen

- [Officiële GAAV API-specificatie, versie 1.0, 10 juni 2025](https://www.validatie.nl/static/files/API-specificatie%20GAAV%20v1.0.pdf).
- [Justis: controleren van de VOG](https://www.justis.nl/producten/verklaring-omtrent-het-gedrag/informatie-over-de-vog-voor-werkgevers-en-organisaties/controleren-van-de-vog).
- [pypdf: installatie en cryptografische afhankelijkheden](https://pypdf.readthedocs.io/en/6.12.0/user/installation.html).
- [Smalot: beperkingen bij beveiligde PDFs](https://github.com/smalot/pdfparser/blob/master/doc/Usage.md).
- Lokale documentproef en alleen-lezen hostingcontrole in deze taak op 11 september 2026.
