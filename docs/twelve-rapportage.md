# Twelve dagrapportage → Rondo

Elke ochtend mailt Twelve (`noreply@twelve.eu`) een PDF met de kassaomzet van
de vorige dag naar het AWC-mailadres. Deze integratie haalt die mail op,
parst de PDF en slaat de cijfers op als `rondo_twelve_report` posts, zodat
Rondo er overzichten en de maandelijkse businessclub-factuur uit kan maken.

De import draait op de WordPress-server en leest de bestaande AgentMail inbox.

## Componenten

| Onderdeel | Bestand |
|---|---|
| PDF-parser (pure PHP, zonder WordPress) | `includes/class-twelve-report-parser.php` |
| AgentMail-client (WordPress HTTP API) | `includes/class-twelve-agentmail-client.php` |
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

### 1. AgentMail inbox instellen

Laat Twelve de rapportages sturen naar de bestaande AgentMail inbox.
Er is geen Google OAuth nodig. De integratie gebruikt de AgentMail REST API
met een API key en inbox ID (het e-mailadres).

### 2. Credentials opslaan op de server

Voer dit uit in de WordPress-installatie. De API key wordt verborgen ingelezen,
waardoor deze niet in shell history of command-line argumenten verschijnt:

```bash
read -rs AGENTMAIL_API_KEY
export AGENTMAIL_API_KEY
wp rondo twelve auth --inbox-id=rondo-twelve@agentmail.to
unset AGENTMAIL_API_KEY
wp rondo twelve auth --status
```

Bij gebruik van een lokale `.env`:

```bash
set -a
source .env
set +a
wp rondo twelve auth
```

`AGENTMAIL_EMAIL_ADDRESS` levert het inbox ID; `--inbox-id` kan dit overschrijven.
WP-CLI leest `.env` niet automatisch.

De credentials staan versleuteld in `rondo_twelve_agentmail_credentials`.
`--status` controleert alleen de opgeslagen configuratie; test toegang met
`wp rondo twelve import --dry-run`.

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
overgeslagen. De ruwe PDF wordt als base64 in beschermde postmetadata bij het rapport bewaard, zodat
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

- De import logt naar stdout (systemd-journal of het cron-log). Alle ontvangen berichten worden gepagineerd gescand; reeds geïmporteerde berichten worden overgeslagen.
- Mislukt parsen → WP-CLI error, exit code niet-nul; er wordt geen leeg
  rapport opgeslagen.

## Import en opslag

Elke run scant alle ontvangen berichten, van oud naar nieuw, en controleert
het exacte afzenderadres `noreply@twelve.eu`. Gemiste dagen blijven daardoor
beschikbaar voor een latere run. `--message-id` selecteert één bericht.
PDF-bestanden (maximaal 10 MB) staan in `_twelve_pdf_base64` en de bestandsnaam
in `_twelve_pdf_filename`; er wordt geen openbaar uploadbestand aangemaakt.
Als PDF-opslag mislukt wordt het rapport verwijderd en kan dezelfde mail
opnieuw worden geïmporteerd. De API biedt deze interne metadata niet aan.

API-contract: https://docs.agentmail.to/api-reference/inboxes/messages/list
