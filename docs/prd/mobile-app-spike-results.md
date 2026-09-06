# Capacitor-proef: resultaten en vervolg

Datum: 6 september 2026. Status: eerste ontwikkelmijlpaal 0.1.0; native toestelproef nog open.
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
| iOS-compilatie | Geblokkeerd vóór compilatie: Xcode 26.2 aanwezig, maar iOS 26.2-platformcomponent ontbreekt |
| iPhone-simulator | Geen geïnstalleerde simulatorapparaten/runtimes gevonden |
| Android-compilatie | Niet uitgevoerd: Android SDK ontbreekt; aanwezige Java 8 is niet de benodigde moderne ontwikkelomgeving |

De Capacitor CLI heeft drie samenhangende moderate auditmeldingen via `xcode → uuid`, alleen in
ontwikkelafhankelijkheden. De CLI gebruikt daar UUID v4, terwijl de melding v3/v5/v6 met een
meegegeven buffer betreft. Geen geforceerde major override of terugzetting toegepast; opnieuw
beoordelen bij de volgende dependency-update. De CLI zit niet in de verpakte runtime.

## Wat nog niet bewezen is

Deze eerste mijlpaal valideert de basisverbinding en serverrechten. Er is nog geen geslaagde
native build of fysieke iPhone/Android-login. Ook de terugkeer uit een echte e-mailapp is niet
getest; de HTTP-proef gebruikte een fictief account met wachtwoord.

Sessies en de PKCE-verifier staan uitsluitend in werkgeheugen. Er zijn geen refresh tokens,
Keychain/Keystore-opslag, refreshrotatie, hergebruikdetectie, achtergrondprivacy of geverifieerde
Universal Links/App Links. Een koude appstart vraagt opnieuw inloggen. Dit zijn expliciete
vervolgwerkzaamheden, geen eigenschappen die de bovenstaande tests aantonen.

Het clubregister is voor deze proef een buildconfiguratie. Meerdere clubs kunnen geselecteerd
worden, maar sessies worden niet tegelijk bewaard: wisselen logt eerst uit. Een operationele
centrale clublijst en stabiele installatie-identiteit volgen in het productieontwerp.

## Eerstvolgende stappen

1. iOS-platform/simulatorcomponenten en Android Studio met SDK/JDK beschikbaar maken; twee
   geïsoleerde HTTPS-testclubs instellen. Daarna compileren en de login op beide platformen testen.
2. De proef uitbreiden met veilige blijvende sessies en geverifieerde terugkeerlinks, inclusief
   annulering, koude start, e-maillink, offline herstel en gelijktijdige verversing.
3. Na die technische controle de afgesproken eerste-release-schermen aansluiten: Start, Passen,
   Vrijwillig met maandkalender en Meer. Clubwissel blijft uitsluitend onder Meer.

De totale technische proef uit het releaseplan is dus **nog niet afgerond**. Deze branch is een
reviewbaar begin en mag niet als store- of productierijpe app worden gepresenteerd.
