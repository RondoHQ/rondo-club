# Twelve dagrapportage → Rondo

Elke ochtend mailt Twelve (`noreply@twelve.eu`) een PDF met de kassaomzet van
de vorige dag naar het AWC-mailadres. Deze integratie haalt die mail op,
parst de PDF en slaat de cijfers op als `rondo_twelve_report` posts, zodat
Rondo er overzichten en de maandelijkse businessclub-factuur uit kan maken.

Alles draait op de eigen EU-VPS; er komt geen Cloudflare of andere
Amerikaanse cloud aan te pas. De bronmail staat bij Gmail, dat verandert
hier niet.

## Componenten

| Onderdeel | Bestand |
|---|---|
| PDF-parser (pure PHP, zonder WordPress) | `includes/class-twelve-report-parser.php` |
| Gmail-client (google/apiclient) | `includes/class-twelve-gmail-client.php` |
| Opslag (`rondo_twelve_report` posts) | `includes/class-twelve-report-repository.php` |
| Aggregaties (dag/maand, categorie, product, btw) | `includes/class-twelve-report-aggregator.php` |
| Businessclub-facturatie | `includes/class-twelve-businessclub-invoicing.php` |
| REST API (`rondo/v1/twelve/...`) | `includes/class-rest-twelve-reports.php` |
| WP-CLI (`wp rondo twelve ...`) | `includes/class-wp-cli.php` |

REST-endpoints (alle achter `financieel_read`):

- `GET /twelve/reports` — recent geïmporteerde rapporten
- `GET /twelve/summary?from=&to=&group=day|month` — omzet en aantallen per dag/maand
- `GET /twelve/categories?from=&to=` — omzet per categorie
- `GET /twelve/products?from=&to=` — verkoop per product
- `GET /twelve/vat?from=&to=` — btw per tariefgroep
- `GET /twelve/businessclub?month=JJJJ-MM` — businessclub-omzet per dag

## Eenmalige setup

### 1. Google OAuth-client aanmaken

1. Maak in [Google Cloud Console](https://console.cloud.google.com/) een project
   aan (of hergebruik een bestaand AWC-project).
2. Schakel de **Gmail API** in.
3. Maak onder *APIs & Services → Credentials* een **OAuth client ID** aan van
   het type *Desktop app*.
4. Voeg onder *OAuth consent screen* de scope
   `https://www.googleapis.com/auth/gmail.readonly` toe.
5. Haal een **refresh token** op door één keer het OAuth-flow te doorlopen als
   het mailbox-account dat de Twelve-mails ontvangt (alleen-lezen toegang is
   genoeg). Dit kan met elk OAuth-hulpprogramma; bewaar het refresh token.

### 2. Credentials opslaan op de server

Op de VPS, in de WordPress-installatie:

```bash
wp rondo twelve auth \
  --client-id=JE_CLIENT_ID \
  --client-secret=JE_CLIENT_SECRET \
  --refresh-token=JE_REFRESH_TOKEN
```

De waarden worden versleuteld opgeslagen in de `rondo_twelve_gmail_credentials`
optie (via `Rondo\Data\CredentialEncryption`, sleutel uit `AUTH_KEY`). Er
staan **nooit** secrets in deze repo. Controleer met:

```bash
wp rondo twelve auth --status
```

### 3. Dagelijkse import inplannen

systemd-timer op de VPS (pas het pad naar WordPress aan):

```ini
# /etc/systemd/system/rondo-twelve-import.service
[Unit]
Description=Rondo Twelve dagrapportage import
After=network-online.target

[Service]
Type=oneshot
User=www-data
ExecStart=/usr/local/bin/wp rondo twelve import --path=/var/www/rondo
```

```ini
# /etc/systemd/system/rondo-twelve-import.timer
[Unit]
Description=Dagelijks Twelve-rapport importeren

[Timer]
OnCalendar=07:05
Persistent=true

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl enable --now rondo-twelve-import.timer
```

Liever cron? Dit is equivalent:

```cron
5 7 * * * www-data /usr/local/bin/wp rondo twelve import --path=/var/www/rondo >> /var/log/rondo-twelve.log 2>&1
```

Handmatig testen:

```bash
wp rondo twelve import --dry-run   # parst zonder op te slaan
wp rondo twelve import             # importeert de nieuwste mail
```

De import is idempotent: een bericht of periode die al bestaat wordt
overgeslagen. De ruwe PDF wordt als bijlage bij het rapport bewaard, zodat
opnieuw parsen mogelijk blijft als Twelve de layout wijzigt.

## Businessclub-factuur

Eén keer per maand, na controle van de cijfers:

```bash
wp rondo twelve businessclub-invoice --month=2026-09
```

Dit maakt een **conceptfactuur** (`rondo_invoice`, type `manual`) met één regel
per dag (netto) plus een btw-regel (9%). Vul in Rondo de ontvanger aan en
verstuur via de normale factuurflow. Een tweede run voor dezelfde maand wordt
geweigerd.

## Monitoring

- De import logt naar stdout (systemd-journal of het cron-log). Geen nieuwe
  mail in 3 dagen → waarschuwing, geen fout.
- Mislukt parsen → WP-CLI error, exit code niet-nul; er wordt geen leeg
  rapport opgeslagen.
