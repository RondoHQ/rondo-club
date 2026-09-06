# Echte Apple Wallet-test vanuit de Rondo-proefapp

Datum: 6 september 2026. Native app: 0.7.0.

## Opzet

De bestaande Apple Wallet-configuratie van AWC is op de clubserver gecontroleerd: het certificaat
is geldig tot 24 maart 2027. De server heeft met dat certificaat een zelfstandige synthetische
pas ondertekend. De privésleutel en het certificaatbestand zijn op de clubserver gebleven;
alleen het ondertekende `.pkpass`-bestand is naar de lokale proefomgeving overgebracht.

De pas toont `Proeflid Rondo`, `TESTPAS` en `Geen toegang tot de club`, met AWC-logo en clubkleur.
Het serienummer begint met `rondo-mobile-test-` en gebruikt een UUID, zodat het geen bestaande
ledenpas vervangt. De barcode is een herkenbare testwaarde; de productiecontrole weigert die
met `membership_pass_invalid_token`. Er zijn geen ledenrecords aangemaakt of gewijzigd.

Het lokale bestand staat buiten de webroot. Een lokale fixture biedt het uitsluitend aan voor
proefpersoon 13, nadat de echte mobiele adapter zijn sessie-, huishoud-, zichtbaarheid- en
paskeuzecontroles heeft uitgevoerd. Een aanvraag zonder aanmelding geeft HTTP 401. De fixture
controleert bovendien de bestandshash en werkt alleen op de lokale HTTPS-proefclub.

Dit bewijst de native overdracht van een echt ondertekende pas. Het is nog geen mobiele login
op de productieclub, geen dynamische uitgifte van echte ledenpassen vanuit de app en geen
praktijktest op een fysieke iPhone. De ontwikkelplugin blijft uitgeschakeld op productie.

## Getest

- De knop opent het echte Apple Wallet-toevoegscherm met de juiste testpas.
- Annuleren sluit het scherm en maakt de Rondo-knop opnieuw bruikbaar.
- Toevoegen sluit het scherm en keert terug naar de proefapp; de testpas is vervolgens zichtbaar in de afzonderlijke Wallet-app.
- De opgeslagen testpas blijft zichtbaar na het volledig afsluiten en opnieuw openen van de Wallet-app.
- ZIP-inhoud, bestandshashes en de cryptografische handtekening zijn afzonderlijk gecontroleerd.
- De ondertekeningssleutel is niet aanwezig in het pasbestand.

De knop blijft beschikbaar in de lokale proefclub, zodat Joost dezelfde test zelf kan uitvoeren
via Passen → Proeflid Rondo → Toevoegen aan Apple Wallet. Alleen de automatische testsimulator
wordt door de testbediening aangestuurd.

## Android daarna

Google Wallet kan in demo mode passen uitgeven aan testaccounts. De huidige Rondo-generator
gebruikt objectnamen zoals `issuer.member_<person_id>`; gebruik daarvan op een tweede clubsite
met hetzelfde uitgevers-ID kan een bestaande pas bijwerken. Een andere class suffix voorkomt
zo'n botsing niet. De Android-proef vereist daarom een afzonderlijke testuitgever, of een expliciet
voorbereide, unieke testobjectnaam op de server. Google-credentials hoeven daarvoor evenmin naar
de lokale app te worden gekopieerd. Dit is nog niet uitgevoerd.

## Bronnen

- [Apple: een pas bouwen en testen](https://developer.apple.com/documentation/walletpasses/building-a-pass)
- [Google: uitgeversaccount en demo mode](https://developers.google.com/wallet/generic/getting-started/issuer-onboarding)

Relevante code: `mobile/src/WalletAction.jsx`, de native `RondoWallet`-plugin,
`mobile/spike-plugin/rondo-mobile-spike.php` en de bestaande Apple/Google-passgeneratoren.
