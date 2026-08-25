# PRD: Membership passes

> Digitale ledenpassen in Apple Wallet en Google Wallet, met een PWA-scanoplossing voor toegangscontrole en identificatie.

**Status:** Implemented (phase 1 and 2)
**Components:** club
**Date:** 2026-02-20

---

## 1. Concept

Elk lid krijgt een digitale pas op hun telefoon. Bruikbaar voor:

- Toegang tot het sportpark / clubhuis
- Identificatie bij wedstrijden
- Kortingen bij sponsors
- Kleding ophalen

## 2. Concurrentie

- Sportlink heeft geen wallet-integratie
- De meeste clubs gebruiken fysieke pasjes of helemaal niets
- Enkele clubs experimenteren met QR-codes via e-mail (niet in wallet)
- Dit zou een echte differentiator zijn

---

## 3. Apple Wallet (PassKit)

- Format: `.pkpass` (signed ZIP met JSON + images)
- Vereist: Apple Developer account via Emilia Projects
- Pass type: "Generic"
- Velden: naam, foto, lidnummer, team en barcode/QR
- **Push updates**: passen kunnen remote geüpdatet worden (seizoenswissel, teamwijziging)
- Barcode formaten: QR, PDF417, Aztec, Code 128
- Distributie: via link, e-mail, of vanuit ledenprofiel

### Certificate setup

1. **[Apple Developer Account](https://developer.apple.com/account/)** → [Certificates, Identifiers & Profiles](https://developer.apple.com/account/resources/)
2. Create a **Pass Type ID**: `pass.nl.emiliaprojects.rondo.membership`
3. Create a certificate for that Pass Type ID (generates a CSR, download .cer)
4. Export from Keychain as `.p12` (includes private key)
5. Upload `.p12` to server (keep password in environment variable, NOT in code)
6. **WWDR Certificate**: Download from Apple (library handles this automatically in v2+)

> ⚠️ Certificates expire yearly. Set a calendar reminder to renew.

### PHP library: [pkpass/pkpass (PHP-PKPass)](https://github.com/includable/php-pkpass)

```bash
composer require pkpass/pkpass
```

```php
use PKPass\PKPass;

$pass = new PKPass();
$pass->setCertificate('/path/to/certificate.p12');
$pass->setCertificatePassword('your-password');

$data = [
    'formatVersion'      => 1,
    'passTypeIdentifier' => 'pass.nl.emiliaprojects.rondo.membership',
    'serialNumber'       => 'member-' . $memberId,
    'teamIdentifier'     => 'XXXXXXXXXX', // Apple Team ID
    'organizationName'   => 'Rondo',
    'description'        => 'Rondo Lidmaatschapspas',
    'logoText'           => 'Rondo',
    'foregroundColor'    => 'rgb(255, 255, 255)',
    'backgroundColor'    => 'rgb(0, 100, 60)',  // Club colors (configurable)
    'generic' => [
        'primaryFields' => [
            ['key' => 'member_name', 'label' => 'LID', 'value' => $memberName],
        ],
        'secondaryFields' => [
            ['key' => 'team', 'label' => 'TEAM', 'value' => $teamName],
            ['key' => 'season', 'label' => 'SEIZOEN', 'value' => '2025-2026'],
        ],
        'auxiliaryFields' => [
            ['key' => 'member_id', 'label' => 'LIDNUMMER', 'value' => $memberId],
        ],
    ],
    'barcode' => [
        'format'          => 'PKBarcodeFormatQR',
        'message'         => $qrPayload,
        'messageEncoding' => 'iso-8859-1',
    ],
    'webServiceURL'      => 'https://rondo.example.com/wp-json/rondo/v1/passes',
    'authenticationToken' => bin2hex(random_bytes(16)),
];

$pass->setJSON(json_encode($data));

// Add images
$pass->addFile('icon.png', file_get_contents('/path/to/icon.png'));
$pass->addFile('icon@2x.png', file_get_contents('/path/to/icon@2x.png'));
$pass->addFile('logo.png', file_get_contents('/path/to/club-logo.png'));
$pass->addFile('logo@2x.png', file_get_contents('/path/to/club-logo@2x.png'));
$pass->addFile('thumbnail.png', file_get_contents($memberPhotoPath));
$pass->addFile('thumbnail@2x.png', file_get_contents($memberPhoto2xPath));

if ($pass->create(true)) {
    // Pass is sent to browser with Content-Type: application/vnd.apple.pkpass
}
```

### Push updates

PHP-PKPass includes a `Push` class for APNs notifications:

```php
use PKPass\Push;

$push = new Push('/path/to/certificate.p12', 'certificate-password');
$push->sendPush($pushToken, 'pass.nl.emiliaprojects.rondo.membership');
```

**Update flow:**
1. Pass includes `webServiceURL` and `authenticationToken`
2. When user adds pass, Wallet calls `POST /v1/devices/{deviceId}/registrations/{passTypeId}/{serialNumber}`
3. Store the device token + serial mapping in the DB
4. When pass data changes, send a silent push via APNs → Wallet calls `GET /v1/passes/{passTypeId}/{serialNumber}` → server returns the updated `.pkpass`
5. Requires ~5 REST endpoints (register, unregister, list serials, get latest pass, log errors)

Maps to a WordPress REST API namespace: `rondo/v1/passes/`.

---

## 4. Google Wallet

- Format: JWT-based via [Google Wallet API](https://developers.google.com/wallet)
- Vereist: Google Cloud project + Wallet API access
- Pass type: "Generic pass"
- Zelfde velden mogelijk als Apple
- **Push updates**: via REST API PATCH (much simpler than Apple)
- Distributie: "Add to Google Wallet" link/button

### Setup

1. **[Google Cloud Console](https://console.cloud.google.com/)**: Enable Google Wallet API
2. Create a **Service Account** + download JSON key file
3. **[Google Pay & Wallet Console](https://pay.google.com/business/console/)**: Sign up as Issuer, grant service account access
4. Use [`google/apiclient`](https://github.com/googleapis/google-api-php-client) PHP SDK

```bash
composer require google/apiclient
composer require firebase/php-jwt
```

### Creating a pass

```php
use Google\Client as GoogleClient;
use Google\Service\Walletobjects;
use Google\Service\Walletobjects\GenericClass;
use Google\Service\Walletobjects\GenericObject;
use Google\Service\Walletobjects\Barcode;
use Google\Service\Walletobjects\ImageModuleData;
use Google\Service\Walletobjects\Image;
use Google\Service\Walletobjects\ImageUri;
use Firebase\JWT\JWT;

$issuerId = '3388000000022222222'; // Your issuer ID
$classId = "{$issuerId}.rondo_membership_2025";
$objectId = "{$issuerId}.member_{$memberId}";

// Auth
$client = new GoogleClient();
$client->setAuthConfig('/path/to/service-account-key.json');
$client->addScope('https://www.googleapis.com/auth/wallet_object.issuer');
$service = new Walletobjects($client);

// 1. Create class (once)
$class = new GenericClass([
    'id' => $classId,
    'issuerName' => 'Rondo',
    'reviewStatus' => 'UNDER_REVIEW',
]);
$service->genericclass->insert($class);

// 2. Create object (per member)
$object = new GenericObject([
    'id' => $objectId,
    'classId' => $classId,
    'state' => 'ACTIVE',
    'barcode' => new Barcode([
        'type' => 'QR_CODE',
        'value' => $qrPayload,
    ]),
    'heroImage' => new Image([
        'sourceUri' => new ImageUri(['uri' => $clubBannerUrl]),
    ]),
    'imageModulesData' => [
        new ImageModuleData([
            'mainImage' => new Image([
                'sourceUri' => new ImageUri(['uri' => $memberPhotoUrl]),
            ]),
        ]),
    ],
    'textModulesData' => [
        new Walletobjects\TextModuleData([
            'header' => 'Team',
            'body' => $teamName,
        ]),
        new Walletobjects\TextModuleData([
            'header' => 'Seizoen',
            'body' => '2025-2026',
        ]),
    ],
]);

// 3. Generate "Add to Google Wallet" link
$serviceAccountKey = json_decode(
    file_get_contents('/path/to/service-account-key.json'), true
);

$claims = [
    'iss' => $serviceAccountKey['client_email'],
    'aud' => 'google',
    'origins' => ['https://rondo.example.com'],
    'typ' => 'savetowallet',
    'payload' => [
        'genericObjects' => [$object->toSimpleObject()],
    ],
];

$jwt = JWT::encode($claims, $serviceAccountKey['private_key'], 'RS256');
$addToWalletUrl = "https://pay.google.com/gp/v/save/{$jwt}";
```

### Push updates

Much simpler than Apple. Just PATCH the object:

```php
$updatedObject = new GenericObject([
    'textModulesData' => [
        new Walletobjects\TextModuleData([
            'header' => 'Seizoen',
            'body' => '2026-2027',
        ]),
    ],
]);

$service->genericobject->patch($objectId, $updatedObject);
// Google Wallet automatically reflects the change on user's device
```

No push tokens, no APNs. Google handles the sync.

---

## 5. QR code strategie

Elke pas bevat een unieke QR-code die een lid-ID of token encodeert.

**Opties:**
- **Simpel**: lid-ID in QR → scanner slaat op tegen Rondo API
- **Signed token**: JWT met lid-ID + verloopdatum → offline validatie mogelijk
- **Roterende codes**: hogere beveiliging, maar overkill voor sportclubs

**Beslissing: signed JWT in QR, altijd online gecontroleerd.** De token verloopt
niet standaard. De server controleert bij iedere scan het actuele pasrecht en
de persoonlijke `pass_version`. Een wijziging in lidstatus, lidtype of
sponsorrelatie verhoogt die versie en trekt alle eerder uitgegeven QR-codes
definitief in.

```
eyJhbGciOiJIUzI1NiJ9.eyJtZW1iZXIiOjEyMzQsInNlYXNvbiI6IjIwMjUtMjAyNiIsImV4cCI6MTcyNTAwMH0.xxx
```

De PWA scanner decodeert de QR en valideert deze via de API. Zonder verbinding
wordt geen uitspraak over de geldigheid gedaan.

---

## 6. Scanoplossing: PWA

**Beslissing: PWA met camera-based QR-scanning.**

### QR scanning library

| Library | Bundle size | Camera | iOS Safari | Status | Notes |
|---------|------------|--------|------------|--------|-------|
| **[qr-scanner](https://github.com/nimiq/qr-scanner)** (nimiq) | ~25 KB | ✅ Built-in | ✅ | Stable (2022) | ⭐ **Best pick** |
| **[html5-qrcode](https://github.com/mebjas/html5-qrcode)** | ~280 KB | ✅ Built-in UI | ✅ | Active | Fallback optie |
| **[jsQR](https://github.com/cozmo/jsQR)** | ~45 KB | ❌ Manual | ✅ | Archived | Image-only |
| **[@zxing/library](https://github.com/nicimiq/zxing-js)** | ~400 KB+ | Via browser | ✅ | Maintenance mode | Te zwaar |
| **[Dynamsoft Barcode Reader](https://www.dynamsoft.com/barcode-reader/overview/)** | Large | ✅ | ✅ | Active | Betaald, overkill |
| **BarcodeDetector API** | 0 KB | ✅ | ❌ Chrome only | N/A | Niet bruikbaar voor iOS |

**Aanbeveling: `qr-scanner` (nimiq)** — 25 KB, Web Worker-based decoding, ingebouwde camera-handling.

```typescript
import QrScanner from 'qr-scanner';

const videoEl = document.getElementById('scanner-video') as HTMLVideoElement;

const scanner = new QrScanner(
  videoEl,
  (result) => {
    console.log('Scanned:', result.data);
    scanner.stop();
    // Validate membership, show result
  },
  {
    preferredCamera: 'environment',
    highlightScanRegion: true,
    highlightCodeOutline: true,
  }
);

scanner.start();
```

### iOS PWA camera gotchas

Dit is het **grootste risico** van de hele feature:

1. **iOS standalone PWA + `getUserMedia`**: Was broken, **fixed in iOS 16.4+** (maart 2023). WebKit bug [#185448](https://bugs.webkit.org/show_bug.cgi?id=185448). Oudere devices kunnen problemen geven.

2. **Permission prompts herhalen**: WebKit bug [#215884](https://bugs.webkit.org/show_bug.cgi?id=215884). In standalone PWAs kan de camera-permissie prompt herhalen bij navigatie. Workaround: scanner op een single page, geen route changes tijdens scannen.

3. **`playsinline` attribuut is verplicht**: Zonder dit probeert iOS de video fullscreen te maken. Altijd `playsinline` en `muted` toevoegen.

4. **HTTPS verplicht**: Camera access vereist secure context. Localhost werkt voor dev.

5. **iOS 17.4+**: Betere PWA support in EU, camera permissions in standalone mode zijn betrouwbaarder.

**Mitigatie:**
- Minimum supported iOS: 16.4+
- Test op echte iPhones (simulator heeft geen camera)
- Fallback: als camera faalt, sta foto-upload toe → decode from image
- Detect standalone mode en toon "scan in Safari" fallback voor oude iOS

### Online validatie

De camera en QR-detectie draaien lokaal, maar de geldigheid wordt altijd online
gecontroleerd. Daardoor is een opgezegd of ingetrokken pas direct ongeldig. Bij
een netwerkfout toont de scanner **Geldigheid kan niet worden gecontroleerd**.

### Scanner UX

De PWA toont na een succesvolle scan:
- Naam en foto van het lid
- Lidstatus (actief / verlopen)
- Team
- Toegangsrecht

Elke vrijwilliger met een telefoon kan scannen. Geen speciale hardware nodig.

---

## 7. Rondo Club integratie

Nieuwe classes in de bestaande `includes/` directory:

```
includes/
├── class-membership-pass-apple.php    # .pkpass generatie
├── class-membership-pass-google.php   # Google Wallet API
├── class-membership-pass-api.php      # REST endpoints voor Apple push updates
└── class-membership-pass-qr.php       # QR code content/signing
```

Pass images (icons, logos op 1x/2x/3x) in de theme `assets/` directory. Certificaat-upload en configuratie in Finance Settings of een nieuw Passes-tabblad.

### Data uit Sportlink sync

- Lidnummer
- Naam
- Geboortedatum
- Pasfoto
- Team

### Pass design

- Clublogo en kleuren per club configureerbaar
- Geldig zolang het actuele lid- of sponsorpasrecht actief blijft
- Foto van het lid op de pas

---

## 8. Fasering

### Fase 1 — Pasrecht en intrekking

- Eén centrale bepaling van actief pasrecht
- Permanente signed JWT met persoonlijke `pass_version`
- Directe intrekking bij wijzigingen in lidstatus, lidtype en sponsorrelaties
- Scanner-API alleen voor beheerders en toegangscontrole

### Fase 2 — Productierijp maken

- Duidelijke scannerstatus bij ingetrokken passen en netwerkfouten
- Apple-certificaatstatus en verloopwaarschuwing in Wallet-instellingen
- Geen seizoensveld meer op Apple- en Google-passen
- Geautomatiseerde lifecycle-, autorisatie- en regressietests

### Fase 3 — Uitbreidingen

- NFC tap support
- Toegangsregels (alleen op wedstrijddagen, alleen eigen sportpark)
- Integratie met narrowcasting (welkomstbericht bij scan)
- Aanwezigheidsregistratie voor trainingen
- Koppeling met sponsors (kortingspas bij partnerbedrijven)
- Dedicated tablet-scanner (kiosk-modus)
- Tourniquet/poortje-integratie

---

## 9. Beslissingen

- ✅ **Scanner**: PWA met QR-scanning (camera-based, `qr-scanner`)
- ✅ **Foto's**: beschikbaar of te verkrijgen voor alle leden
- ✅ **Apple Developer account**: via Emilia Projects (centraal)
- ✅ **QR payload**: signed JWT met permanente online validatie en intrekbare pasversie
- ✅ **Apple Wallet library**: `pkpass/pkpass` via Composer
- ✅ **Google Wallet**: `google/apiclient` + `firebase/php-jwt`

## 10. Open vragen

- [ ] Pass style: `generic` vs `eventTicket` vs `storeCard` (generic is meest flexibel)
- [ ] Member photo: resize/crop pipeline voor embedding in pass
- [ ] Willen clubs toegangscontrole of is identificatie voldoende?
- [ ] NFC: hoe betrouwbaar is NFC tap met Wallet passes bij sportparken?

---

## 11. Samenvatting

| Component | Aanpak |
|-----------|--------|
| QR scanner (PWA) | [`qr-scanner`](https://github.com/nimiq/qr-scanner) (nimiq) — 25 KB, iOS 16.4+ |
| Apple Wallet | [`pkpass/pkpass`](https://github.com/includable/php-pkpass) via Composer |
| Google Wallet | [`google/apiclient`](https://github.com/googleapis/google-api-php-client) + [`firebase/php-jwt`](https://github.com/firebase/php-jwt) |
| QR content | Signed JWT met permanente online verificatie en intrekbare pasversie |
| Pass updates | Apple: APNs + web service endpoints · Google: REST API patch |
