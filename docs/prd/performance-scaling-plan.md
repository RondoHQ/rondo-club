# Performance- en schaalplan voor Rondo Club

## Doel

Rondo moet tijdens een aankondiging snel en stabiel blijven wanneer veel leden kort na elkaar inloggen. De grootste druk komt dan van gepersonaliseerde WordPress REST-verzoeken. Statische bestanden worden al via Cloudflare geleverd en krijgen een cacheduur van één jaar.

We sturen op deze productiedoelen:

- minder dan 1% HTTP-fouten tijdens een piek;
- een p95-responstijd onder 750 ms voor normale REST-reads;
- een p95-responstijd onder 1,5 seconde voor het dashboard en zware filtervragen;
- geen oplopende wachtrij of foutpiek bij 25, 50 en 100 gelijktijdige gebruikers;
- geen achterstallige cronjobs na een verkeerspiek.

De aantallen zijn testniveaus, geen voorspelling. Een loadtest bepaalt welk niveau de huidige hosting veilig haalt.

## Nulmeting op 11 juli 2026

- De publieke HTML had zonder ingelogde sessie een TTFB van 0,19 tot 0,30 seconde.
- Cloudflare staat voor de site. Gehashte JavaScriptbestanden hebben `Cache-Control: max-age=31536000`.
- WordPress meldde `wp_using_ext_object_cache() = false`. Er was dus nog geen persistente objectcache actief.
- SiteGround Speed Optimizer 7.8.0 was actief, maar `wp-content/object-cache.php` ontbrak. De Memcached-integratie was dus nog niet verbonden met WordPress.
- `DISABLE_WP_CRON` staat aan. De geplande taken stonden bij controle niet achter; de externe crontrigger lijkt daarom te werken.
- De PWA precachete 138 bestanden en 2.768,67 KiB. De app downloadde daardoor bij installatie ook zelden gebruikte schermen.
- De dashboardrespons wordt vijftien minuten per gebruiker gecachet. Persoonsgegevens in die cache blijven per gebruiker gescheiden vanwege leeftijdsgroep- en roltoegang.
- `wp_postmeta` krijgt via het thema een samengestelde index op `meta_key` en `meta_value`.

Een volledige Core Web Vitals-meting met een echte ingelogde browsersessie ontbreekt nog. De benodigde Chrome DevTools-koppeling was tijdens deze nulmeting niet beschikbaar.

## Fase 1: directe codeverbeteringen

Status: uitgevoerd in versie 33.46.1.

### Verklein de installatiepiek van de PWA

De serviceworker precachet alleen nog de app-shell. Scripts voor afzonderlijke pagina's en niet-Latijnse fontsets worden bij het eerste gebruik geladen en daarna maximaal één jaar in `rondo-assets` bewaard. De definitieve precache bevat 19 bestanden en 762,73 KiB. Dat is 72% minder dan de nulmeting; dubbele iconen en de offlinepagina zijn uit de installatielijst verwijderd.

### Voorkom ongebruikte dashboardverzoeken

De HTML startte voor iedere ingelogde SPA-route een dashboardrequest. Een directe link naar een persoon, vrijwilligerspagina of instelling leverde daardoor een dure respons op die de app niet gebruikte. De preload draait nu alleen op de site-root waar het dashboard werkelijk kan verschijnen.

### Verminder versiepolling

De versiecheck start nu na zestig seconden, gebruikt standaard een interval van vijftien minuten en laat geen overlappende verzoeken toe. Terugkeren naar een tab veroorzaakt alleen een request als het interval verstreken is.

## Fase 2: serververbeteringen

Memcached is op 12 juli 2026 geactiveerd op demo en productie. De overige serverwijzigingen worden pas uitgevoerd na akkoord.

### 1. Activeer persistente Memcached-objectcache

Status: uitgevoerd en technisch geverifieerd. Zowel demo als productie melden `wp_using_ext_object_cache() = true`; de PHP-extensie, object-cache-drop-in en SiteGround-integratie zijn actief.

Dit is de eerste serverwijziging met de grootste verwachte winst. Zonder persistente objectcache belanden transients in de options-tabel en kan WordPress resultaten niet tussen requests uit geheugen hergebruiken.

Voorgestelde uitvoering:

1. Zet Memcached aan via Site Tools → Speed → Caching.
2. Activeer de Memcached-integratie van SiteGround Speed Optimizer.
3. Controleer met WP-CLI dat `wp_using_ext_object_cache()` daarna `true` teruggeeft.
4. Warm het dashboard en de belangrijkste lijsten op.
5. Vergelijk TTFB, databasevragen, foutpercentage en cache-hitratio voor en na de wijziging.
6. Draai een functionele controle op login, rechten, dashboard, personenlijst, facturen en cronjobs.

Rollback: zet de Memcached-integratie uit en verwijder alleen de object-cache drop-in die de integratie heeft geplaatst. Wis daarna de WordPress- en SiteGround-caches.

### 2. Verifieer OPcache in de web-PHP-runtime

De CLI-controle meldde geen actieve OPcache, maar de webserver kan een andere PHP-configuratie gebruiken. Controleer daarom in Site Tools of via een tijdelijke, afgeschermde PHP-statuspagina of OPcache voor webrequests actief is. Zet OPcache aan als dat niet zo is; verwijder een tijdelijke statuspagina direct na de controle.

### 3. Controleer proces- en databaselimieten

Vraag SiteGround hoeveel gelijktijdige PHP-workers, CPU-seconden, databaseverbindingen en account-executions het huidige pakket toestaat. Vergelijk deze grenzen met de loadtest. Een pakketupgrade is pas zinvol als de meting aantoont dat workers of CPU verzadigen.

### 4. Houd gepersonaliseerde REST-responses buiten full-page caching

Cloudflare en SiteGround mogen gehashte assets lang cachen. `/wp-json/` en HTML met een WordPress-login-cookie bevatten echter gebruikersspecifieke gegevens en nonces. Schakel daarom geen gedeelde full-page cache voor deze responses in. Een afzonderlijke cache per ingelogde gebruiker kan later worden onderzocht, maar alleen met expliciete tests op rechten, uitloggen en cache-invalidering.

### 5. Bevestig de externe crontrigger

`DISABLE_WP_CRON` staat terecht aan om page-loadpieken te voorkomen. Leg in Site Tools vast welke echte cronjob `wp cron event run --due-now` of `wp-cron.php` aanroept en hoe vaak. Gebruik voor Rondo maximaal vijf minuten tussen triggers en voeg een melding toe als taken meer dan tien minuten achterlopen.

## Fase 3: meten onder echte belasting

De eerste loadtest draait op `demo.rondo.club`, met uitsluitend synthetische vrijwilligers en diensten. Demo gebruikt een aparte database maar deelt de server met productie. Bouw de belasting daarom stapsgewijs op en controleer tussen runs of productie stabiel blijft. Gebruik geen echte persoonsgegevens en verstuur geen e-mail of betalingen.

Test drie scenario's:

1. **Aankondigingspiek:** 5, 25, 50 en conditioneel 100 unieke vrijwilligers loggen in en openen rechtstreeks `/vrijwillig`.
2. **Vrijwilligerslijsten:** iedere gebruiker vraagt `user/me`, `my-shifts` en `shifts/available` op.
3. **Gelijktijdige inschrijving:** twintig vrijwilligers schrijven vrijwel tegelijk in op dezelfde synthetische dienst. Controleer daarna apart dat alle succesvolle inschrijvingen uniek en blijvend zijn opgeslagen.

De tooling staat in `tests/load/volunteer-journey.js`; `bin/load-test-fixtures.php` beheert gemarkeerde demo-only fixtures en ondersteunt `seed`, `reset`, `verify` en `cleanup`. Zie `tests/load/README.md` voor de operationele stappen en stopcriteria.

### Resultaten van 12 juli 2026

Alle runs gebruikten honderd synthetische vrijwilligers en 21 synthetische diensten op demo. Memcached was tijdens iedere run actief. Productie bleef tijdens de tests bereikbaar en het demo-errorlog kreeg geen nieuwe fatals.

| Gelijktijdige vrijwilligers | HTTP-fouten | REST p95 | Iteratie p95 | Uitkomst |
| --- | ---: | ---: | ---: | --- |
| 1 | 0% | 142-161 ms | 1,98 s | Groen |
| 5 | 0% | 134-151 ms | 2,11 s | Groen |
| 25 | 0% | 374-795 ms | 3,73 s | Groen |
| 50 | 0% | 960-1.560 ms | 6,31 s | Functioneel goed, maar boven de latencydoelen |

De conditionele run met honderd gebruikers is niet uitgevoerd, omdat vijftig gebruikers het vooraf bepaalde stopcriterium al overschreden. Het huidige veilige testniveau is 25 gelijktijdige vrijwilligers. Dat is nog geen capaciteitsgarantie: demo en productie delen serverresources en de test duurde per gebruiker één reis.

De eerste test met twintig vrijwel gelijktijdige inschrijvingen ontdekte een race condition in de geserialiseerde `assigned_persons`-postmeta: alle requests konden succes melden terwijl WordPress inschrijvingen verloor. Versie 33.48.6 serialiseert mutaties per dienst en forceert verse Memcached-reads binnen de lock. De definitieve hertest gaf 20/20 succesvolle requests, 20/20 uniek opgeslagen vrijwilligers, 0% requestfouten en een signup-p95 van 397 ms.

Deze meting bewijst dat Memcached actief is en dat Rondo ermee correct functioneert onder de geteste belasting. De afzonderlijke snelheidswinst van Memcached is niet gekwantificeerd, omdat er geen vergelijkbare run met uitgeschakelde objectcache is gedaan.

Leg per endpoint vast:

- requests per seconde;
- p50-, p95- en p99-responstijd;
- HTTP-fouten en time-outs;
- PHP-workerbezetting en CPU;
- databaseverbindingen en trage queries;
- object-cache-hits en -misses;
- responspayload en aantal queries.

Stop een test zodra het foutpercentage boven 1% komt of de responstijd blijft oplopen. Het veilige capaciteitsgetal ligt onder het eerste verzadigingspunt, met minimaal 30% marge.

## Fase 4: volgende code-optimalisaties op basis van metingen

Voer deze verbeteringen alleen uit als traces of loadtests aantonen dat ze de bottleneck raken:

- splits het dashboard in gedeelde, rolgebonden en gebruikersgebonden cacheonderdelen;
- voeg stampede-bescherming toe wanneer veel requests tegelijk een verlopen cache opnieuw opbouwen;
- profileer zoek- en filterqueries en voeg alleen bewezen ontbrekende indexen toe;
- beperk REST-responses verder met `_fields`, paginering en kleinere afbeeldingen;
- laad zware routechunks alvast bij hover of focus op een navigatielink;
- voeg `Server-Timing` toe voor WordPress-bootstrap, databasewerk en endpointlogica;
- verplaats langlopende exports, bulkfacturen en mailtaken volledig naar achtergrondjobs;
- archiveer of aggregeer snel groeiende logdata en verlopen transients.

## Monitoring en operationele voorbereiding

Zet vóór de aankondiging een dashboard en waarschuwingen klaar voor HTTP 5xx, p95 REST-responstijd, PHP-workerbezetting, CPU, databasebelasting en cronachterstand. Bewaar per release een korte baseline, zodat een regressie direct zichtbaar wordt.

Gebruik daarnaast een eenvoudige aankondigingsprocedure:

1. deploy minimaal één dag voor de communicatie;
2. warm de anonieme pagina's en belangrijkste gebruikersroutes op;
3. controleer objectcache, cron en errorlog;
4. verstuur de aankondiging eventueel in groepen als de eerste loadtest weinig marge toont;
5. monitor het eerste uur live en houd een rollbackbesluit klaar.

## Besluitvolgorde

De aanbevolen volgorde is: eerst Memcached activeren en meten, daarna een staging-loadtest draaien, en pas dan hostingcapaciteit verhogen of diepere queryrefactors uitvoeren. Zo kopen we geen servercapaciteit zonder bewijs en veranderen we geen toegangsgevoelige cachelogica zonder noodzaak.

## Referenties

- [SiteGround: SuperCacher en Memcached voor WordPress](https://world.siteground.com/tutorials/wordpress/speed-optimizer/supercacher/)
- [SiteGround: werking en uitsluitingen van Dynamic Caching](https://world.siteground.com/kb/siteground-dynamic-caching-configuration/)
- [WordPress: persistente objectcache](https://developer.wordpress.org/reference/classes/wp_object_cache/)
