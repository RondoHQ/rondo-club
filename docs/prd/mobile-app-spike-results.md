# Capacitor-proef: resultaten en vervolg

Datum: 6 september 2026. Status: ontwikkelproef 0.4.1; native simulatorlogin geslaagd, fysieke toestelproef nog open.
Branch: `codex/capacitor-login-spike`. Geen productie-installatie of store-upload uitgevoerd.

## Gebouwd

- Apart React/Capacitor-appstartpunt met lokaal verpakte assets en native iOS/Android-projecten.
- Clubzoeken en bevestigen uit een vooraf ingestelde HTTPS-clublijst; standaardlijst leeg.
- Browserlogin via bestaande WordPress-login, expliciete toestemming, S256 PKCE en state.
- Vijf minuten geldige toegangstokens in appgeheugen; veilige sessieopslag sinds 0.3.0,
  intrekking en clubwissel onder Meer.
- Losse ontwikkelplugin die alleen eigen profiel en toegestane huishoudgegevens via bestaande
  REST-controllers leest; geen nieuwe productie-authenticatie of uitbreiding van FreeScout OIDC.
- Automatische controles in CI en instructies in `mobile/README.md`.

Bouwstap 0.2.0 voegt Start, Passen met QR-detail en bestaande paskeuzes, Mijn gegevens,
Vrijwillig met maandkalender, Mijn diensten, dienstdetail en Meer/Mijn clubs toe. Na feedback is de
kop één compacte regel met clublogo en Rondo. Clubwissel staat alleen onder Meer. De leesroutes
gebruiken de bestaande serverrechten; wijzigen, aanmelden/afmelden en Wallet toevoegen openen
voorlopig de vaste clubpagina in de systeembrowser, zonder mobiele tokens of nonces in die URL.

## Gecontroleerd op deze Mac

| Controle | Resultaat |
|---|---|
| Mobiele JavaScript-contracttests | 8 tests geslaagd: PKCE, callback/state, clublijst, dubbele callbacks en races bij uitloggen/wisselen |
| WordPress/MySQL-tests | 847 tests en 3.548 assertions geslaagd, inclusief 8 nieuwe mobiele integratietests |
| Echte lokale HTTP-login met fictief lid | Geslaagd: loginredirect, WordPress-login, toestemmingspagina, weigering zonder nonce, codewissel, profiel zonder browsercookies, replayweigering en intrekking |
| Productieblokkade | Dezelfde lokale installatie met productie-instelling retourneert 404 voor de proefconfigroute, ondanks expliciete opt-in-constant |
| Mobiele build en beide native asset-syncs | Geslaagd; dit is geen native compilatie |
| Bestaande webbuild | Geslaagd |
| Mobiele ESLint en PHP-codingstandaarden | Geslaagd |
| Visuele controle | Clubkeuze en clubbevestiging in Chrome op 390 × 844; geen toestelbewijs |
| Runtime-afhankelijkheden | `npm audit --omit=dev`: geen meldingen |
| iOS-compilatie | Geslaagd met Xcode 26.2, iOS 26.2-simulatorruntime en ongetekende Debug-build |
| iPhone-simulator | iPhone 17 / iOS 26.2: clubkeuze, browsertoestemming, callback, profiel van Proeflid en uitloggen via Meer geslaagd |
| Android-compilatie | Geslaagd met SDK 36, build-tools 36.0.0, Java 21 en Gradle 8.14.3 |
| Android-emulator | Pixel 8 / API 36: verse browserlogin met fictief wachtwoord, toestemming, callback, profiel van Proeflid en uitloggen via Meer geslaagd |
| Regressietest eerste login | 9 mobiele PHP-tests, 65 assertions geslaagd na herstel van de redirect na het loginformulier |
| Native visuele controle | Screenshots van het ingelogde scherm op beide platformen bekeken; knoppen en tekst zichtbaar |

De Capacitor CLI heeft drie samenhangende moderate auditmeldingen via `xcode → uuid`, alleen in
ontwikkelafhankelijkheden. De CLI gebruikt daar UUID v4, terwijl de melding v3/v5/v6 met een
meegegeven buffer betreft. Geen geforceerde major override of terugzetting toegepast; opnieuw
beoordelen bij de volgende dependency-update. De CLI zit niet in de verpakte runtime.

## Native vervolgcontrole en zelf testen

Op deze Mac zijn iOS 26.2-simulatorcomponenten, Android Studio, SDK 36, een Pixel-emulator en
Java 21 geïnstalleerd. Android Studio levert zelf Java 25 mee; de Gradle-proef gebruikt expliciet
Java 21. SDK-locatie en tijdelijke certificaatinstellingen zijn niet ingecheckt.

De eerste native login bracht een integratieprobleem aan het licht: de bestaande theme-login
controleert alleen de GET-redirect, terwijl het WordPress-loginformulier de bestemming in POST
meestuurt. De uitsluitend lokaal ingeschakelde proefplugin herstelt nu alleen zijn eigen exact
gevalideerde autorisatiebestemming. Een regressietest controleert ook afwijkende hosts, paden,
actions, callbacks, PKCE en mislukte gebruikersauthenticatie. De theme-login zelf is niet gewijzigd.

De afzonderlijke simulator **Rondo iPhone 17** blijft beschikbaar om zelf te testen. Kies
**Proefclub Alpha** en gebruik het fictieve lokale account `spike-member`, wachtwoord
`Local-Member-Only-2026`. Na toestemming verschijnt **Hallo, Proeflid**. Onder **Meer** kun je
uitloggen en opnieuw een club kiezen. Dit account bevat geen productiegegevens. Deze tijdelijke
proef werkt zolang de lokale WordPress-server en HTTPS-proxy op deze Mac draaien.

De app bevat ook **Proefclub Beta** als keuzetest; die heeft nog geen werkende tweede backend.
Alle succesvolle logins zijn tegen Alpha uitgevoerd. Cross-club tokenisolatie is alleen in de
servercontracttests gecontroleerd, niet met twee onafhankelijke native clubinstallaties.

De HTTPS-proef gebruikt een kort geldig lokaal certificaat. Alleen wegwerp-testsimulators
vertrouwen deze test-CA; de Mac-sleutelhanger en persoonlijke toestellen zijn niet aangepast.
Het Android-testpakket gebruikte daarvoor een tijdelijke debug-only trust override. Die is na
de test uit de broncode verwijderd en hoort niet bij een releasepakket. Automatische controles
liepen in een andere iPhone-simulator dan de simulator die voor zelf testen is geopend.

Maestro bevestigde op beide platformen het ingelogde scherm en terugkeer naar clubkeuze na
uitloggen. Serverintrekking is afzonderlijk via de PHP/HTTP-contracttests bewezen; de schermtest
alleen bewijst geen serverintrekking. Een eerste Android-flow strandde uitsluitend op een ongeldig
screenshot-uitvoerpad nadat alle loginasserties waren geslaagd; de vervolgflow met relatief
uitvoerpad slaagde inclusief screenshot en uitloggen.

## Wat nog niet bewezen is

Deze proef valideert de basisverbinding en serverrechten, inclusief native compilatie en login
in beide simulators. Een fysieke iPhone/Android-login en terugkeer uit een echte e-mailapp zijn
nog niet getest; de lokale proef gebruikt fictieve accounts en lokaal opgevangen e-mail.

Bouwstap 0.3.0 voegt blijvende sessies toe; 0.4.0 bewaart ook een lopende browseraanmelding
maximaal tien minuten in de beveiligde toestelopslag. Achtergrondprivacy en geverifieerde Universal Links/App Links zijn
nog niet gebouwd. Fysieke toestelcontrole, backups en herinstallatie zijn nog open.

Het clubregister is voor deze proef een buildconfiguratie. Meerdere clubs kunnen geselecteerd
worden, maar sessies worden niet tegelijk bewaard: wisselen logt eerst uit. Een operationele
centrale clublijst en stabiele installatie-identiteit volgen in het productieontwerp.

## Eerstvolgende stappen

1. Twee onafhankelijke HTTPS-testclubs instellen en op fysieke iPhone/Android controleren;
   annuleren, koude terugkeer, verlopen sessies, offline herstel en echte e-mailterugkeer testen.
2. Blijvende sessies onafhankelijk beoordelen en geverifieerde terugkeerlinks bouwen, inclusief
   annulering, koude start, e-maillink, offline herstel en gelijktijdige verversing.
3. De gebouwde leesschermen aanvullen met native schrijfhandelingen, directe Wallet-overdracht,
   gastpassen, volledige contributiebediening en capabilitygestuurde navigatie.

De totale technische proef uit het releaseplan is dus **nog niet afgerond**. Deze branch is een
reviewbaar begin en mag niet als store- of productierijpe app worden gepresenteerd.

## Bouwstap 0.2.0: ledenoverzicht en kalender

De lokale proefclub bevat één fictief gekoppeld lid en 36 testdiensten over meerdere dagen,
waarvan één eigen inschrijving. Dit zijn opgeslagen WordPress-testrecords; de app bevat geen
hardgecodeerde voorbeeldpassen of diensten. Proefclub Beta heeft nog steeds geen eigen backend.

De kalender telt `can_signup`-diensten, niet het aantal open plekken. Eigen inschrijvingen worden
apart gemarkeerd en tellen niet mee als beschikbare dienst. De datumweergave gebruikt de
clubtijdzone; diensttijden volgen de bestaande lokale WordPress-datetimevelden.

De adapter accepteert uitsluitend een valide maand en forceert `view=signup`. QR-passen zijn
extra begrensd tot de persoonlijke huishoudrespons, ook voor beheerders. De bestaande QR-route
blijft beslissen over pasrecht en rolkeuze. Er worden geen willekeurige routes of schrijfacties
via de bearer-sessie doorgestuurd. De QR-rendering wordt gedeeld met de webpas.

Gecontroleerd: 13 mobiele JavaScript-tests, 11 mobiele PHP-tests met 91 assertions, mobiele lint,
PHP-codingstandaarden en web/mobiele builds. Native simulatorcontrole omvat Start, Passen,
pasdetail met QR en de kalender op beide platformen. Native screenshots zijn bekeken. Datumselectie, dienstdetail, maandwissel, Mijn diensten en
uitloggen via Meer/Mijn clubs zijn daarna op beide simulators doorlopen. De eerste vervolgflow
vereiste correctie van de tests (expliciete tabkeuze en scrollen naar de dienstkaart); de volledige
herhaling is op beide platformen geslaagd.
Runtime dependency-audit bevat geen meldingen; de React Router-dependency is voor dit afzonderlijke
mobiele pakket bijgewerkt naar 7.18.3. De bestaande web-router is ongewijzigd.

De proefsessie blijft maximaal vijf minuten geldig en wordt niet duurzaam opgeslagen. Deze
bouwstap is geen bewijs voor fysieke toestellen, offline gebruik, productieauthenticatie, een
store-release of native schrijf-/Walletacties.

## Bouwstap 0.3.0: blijvend aangemeld en websitebranding

De app gebruikt het exacte woordmerk, de Figtree-lettertypen en de kleuren uit de Rondo-website.
Het clublogo staat links in de compacte header. Native appiconen en opstartschermen gebruiken
het bestaande Rondo-logo, zonder wijziging van het bronlogo.

Een aanmelding blijft maximaal 30 dagen geldig vanaf login. Alleen de roterende refreshcode,
clubidentiteit en vervaltijd staan in de native beveiligde opslag. Toegangstokens blijven maximaal
vijf minuten in geheugen; persoonsgegevens en QR-codes worden niet bewust opgeslagen. iOS gebruikt
Keychain zonder synchronisatie/toesteloverdracht; Android gebruikt AES-GCM met een Keystore-sleutel
en een bestand dat van backups is uitgesloten.

Uitloggen verwijdert eerst de actieve aanmelding. Zonder netwerk blijft het intrekkingsverzoek
versleuteld bewaard tot een volgende start. Tot die bevestiging of de absolute vervaltijd kan de
serverfamilie nog geldig zijn. Opslagfouten tonen expliciet dat uitloggen niet is afgerond.
Refreshverzoeken lopen één voor één; hergebruik van een oude code trekt ook nieuwere tokens in.

Gecontroleerd: 21 mobiele JavaScript-tests en 14 mobiele PHP-tests met 107 assertions, inclusief
parallelle verversing, uitloggen tijdens verversing, offline herstel/uitloggen, opslagfouten,
hergebruikdetectie, wachtwoordwijziging, ingetrokken rechten en absolute vervaltijd.
Beide native builds slagen. Op iPhone én Android: login, twee volledige procesherstarts zonder
opnieuw inloggen, passen en kalender, uitloggen en herstart zonder sessie geslaagd. Screenshots
van de nieuwe branding zijn bekeken. Op Android is daarna de lokale testverbinding onderbroken:
herstart toont herstel zonder persoonsgegevens, opnieuw proberen na herstel van de verbinding
herstelt de login, en offline uitloggen blijft na herstart uitgelogd. De versleutelde Android-opslag
is apart gecontroleerd op afwezigheid van leesbare refreshvelden en club-URL; dit vervangt geen
onafhankelijke cryptografische audit.

De eerste iPhone-controle vond ontbrekende Keychain-rechten in de ongetekende simulatorbuild.
Simulator-only entitlements en lokale signing herstellen dit. De volledige herhaling is geslaagd;
fysieke toestellen moeten hun echte rechten uit Apple-provisioning krijgen.

## Bouwstap 0.3.1: vrijstaand AWC-logo

Het clublogo heeft geen achtergrond, kader, afgeronde hoeken of binnenruimte meer. Voor AWC
is expliciet `https://www.svawc.nl/wp-content/uploads/2024/02/awc-logo.svg` gekozen. De proefclub
gebruikt deze URL via het vooraf gecontroleerde clubregister; API-metadata blijft beperkt tot
logo's op de eigen clubhost. De gekozen afbeelding blijft behouden na sessieherstel.

Gecontroleerd in beide native simulators: kalender met het opgegeven vrijstaande SVG-logo en
hetzelfde logo na herstart. Mobiele tests/lint en mobiele, web- en documentatiebuilds slagen.

## Bouwstap 0.4.0: e-mailaanmelding en terugkeer na afsluiten

De bestaande Magic Login 2.8.1-provider is in de lokale fixture geactiveerd. Het fictieve bestaande
lid vraagt via het echte loginformulier een e-maillink aan. Rondo's bestaande activatieservice
maakte die link met de homepage als bestemming; de ontwikkelplugin behoudt nu uitsluitend de
exact gevalideerde mobiele autorisatiebestemming. Providercontroles en andere webbestemmingen
blijven intact. Alle testmail is lokaal opgevangen, zonder uitgaande bezorging.

De app bewaart clubidentiteit, PKCE-verifier, state en starttijd maximaal tien minuten in de
bestaande beveiligde toestelopslag. Een koude start herstelt die gegevens voordat een terugkeerlink
wordt verwerkt. Annuleren verwijdert ze definitief; een geldige callback verbruikt ze vóór de
codewissel. Dubbele OS-meldingen delen één wissel. Bij een verloren codewisselantwoord is opnieuw
inloggen nodig.

Gecontroleerd: 28 mobiele JavaScript-tests, 15 mobiele WordPress/MySQL-tests met 122 assertions,
44 bestaande activatieservicetests met 138 assertions, mobiele lint en PHP-codingstandaarden.
iOS- en Android-native builds slagen. Op beide simulators is via het formulier een testmail
opgevangen, de app volledig afgesloten en de opgevangen link in de systeembrowser geopend.
Na Verbinden start de app met het juiste fictieve lid; een volgende procesherstart behoudt de
sessie en toont de kalender. Ook een onafgemaakte login herstellen, annuleren en opnieuw starten
is op beide platformen geslaagd. Screenshots van beide platformen zijn bekeken.

De testautomatisering moest eerst Safari- en Chrome-introductiemeldingen sluiten en een Android-
link correct quoten; daarna slaagden de volledige terugkeerflows. Dit is bewijs voor opgevangen
e-maillinks in de systeembrowser, niet voor aflevering of terugkeer uit een echte Mail/Gmail-app.
Nieuwe-accountactivatie, huishoudkeuze tijdens activatie, fysieke toestellen en geverifieerde
HTTPS-terugkeerlinks blijven open. De proefplugin blijft geblokkeerd buiten local/development.

## Bouwstap 0.4.1: clubstyling op de ledenpas

De ledenpas gebruikt nu de door de bestaande pasroute geleverde achtergrondkleur, met dezelfde
fallback en pasvariantbepaling als de webpas. Het algemene Rondo-kleurverloop is verwijderd.
Het vrijstaande clublogo vervangt het tijdelijke pictogram. Sponsorvarianten krijgen donkere
tekst op hun lichte achtergrond; businessclubpassen behouden hun eigen serverlogo. Gewone
passen gebruiken bij voorkeur het vooraf gekozen clublogo, waaronder de opgegeven AWC-SVG.
Functie/team of bedrijf volgen dezelfde variantkeuze als de webpas; KNVB-ID verschijnt voor
gewone leden wanneer beschikbaar.

De 28 bestaande mobiele tests, lint en web/mobiele builds slagen. De ledenpas met clubgroen,
AWC-logo en geladen QR is op beide native simulators gecontroleerd. De fixture bevat een gewone
ledenpas; sponsor- en businessclubpassen zijn in deze stap niet met echte accounts doorlopen.
Er zijn geen productiegegevens of productie-instellingen aangepast.
