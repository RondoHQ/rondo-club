# Capacitor-proef: resultaten en vervolg

Datum: 6 september 2026. Status: ontwikkelproef 0.1.0; native simulatorlogin geslaagd, fysieke toestelproef nog open.
Branch: `codex/capacitor-login-spike`. Geen productie-installatie of store-upload uitgevoerd.

## Gebouwd

- Apart React/Capacitor-appstartpunt met lokaal verpakte assets en native iOS/Android-projecten.
- Clubzoeken en bevestigen uit een vooraf ingestelde HTTPS-clublijst; standaardlijst leeg.
- Browserlogin via bestaande WordPress-login, expliciete toestemming, S256 PKCE en state.
- Vijf minuten geldige sessie in appgeheugen, intrekking en clubwissel onder Meer.
- Losse ontwikkelplugin die alleen eigen profiel en toegestane huishoudgegevens via bestaande
  REST-controllers leest; geen nieuwe productie-authenticatie of uitbreiding van FreeScout OIDC.
- Automatische controles in CI en instructies in `mobile/README.md`.

De proef heeft een eigen vereenvoudigd verbindingsscherm; de goedgekeurde releasevoorstellen voor
passen en de vrijwilligerskalender blijven de basis voor de volgende schermimplementatie.

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
nog niet getest; de lokale proef gebruikt een fictief account met wachtwoord.

Sessies en de PKCE-verifier staan uitsluitend in werkgeheugen. Er zijn geen refresh tokens,
Keychain/Keystore-opslag, refreshrotatie, hergebruikdetectie, achtergrondprivacy of geverifieerde
Universal Links/App Links. Een koude appstart vraagt opnieuw inloggen. Dit zijn expliciete
vervolgwerkzaamheden, geen eigenschappen die de bovenstaande tests aantonen.

Het clubregister is voor deze proef een buildconfiguratie. Meerdere clubs kunnen geselecteerd
worden, maar sessies worden niet tegelijk bewaard: wisselen logt eerst uit. Een operationele
centrale clublijst en stabiele installatie-identiteit volgen in het productieontwerp.

## Eerstvolgende stappen

1. Twee onafhankelijke HTTPS-testclubs instellen en op fysieke iPhone/Android controleren;
   annuleren, koude terugkeer, verlopen sessies, offline herstel en echte e-mailterugkeer testen.
2. De proef uitbreiden met veilige blijvende sessies en geverifieerde terugkeerlinks, inclusief
   annulering, koude start, e-maillink, offline herstel en gelijktijdige verversing.
3. Na die technische controle de afgesproken eerste-release-schermen aansluiten: Start, Passen,
   Vrijwillig met maandkalender en Meer. Clubwissel blijft uitsluitend onder Meer.

De totale technische proef uit het releaseplan is dus **nog niet afgerond**. Deze branch is een
reviewbaar begin en mag niet als store- of productierijpe app worden gepresenteerd.
