# Vrijwilligers-loadtest

Deze k6-test simuleert uitsluitend de eenvoudige vrijwilligerservaring op `demo.rondo.club`. De test weigert iedere andere host.

## Fixtures

Maak honderd synthetische vrijwilligers, gekoppelde WordPress-gebruikers en 21 toekomstige diensten:

```bash
RONDO_LOAD_TEST_PASSWORD='minimaal-16-tekens' wp eval-file \
  wp-content/themes/rondo-club/bin/load-test-fixtures.php seed 100
```

Beschikbare fixturecommando's:

```bash
wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php status
wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php reset
wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php verify 20
wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php cleanup
```

Alle fixturedata heeft `_rondo_load_test_fixture=1`. `cleanup` verwijdert uitsluitend records met die marker.

## Leesreis

```bash
k6 run \
  -e BASE_URL=https://demo.rondo.club \
  -e LOADTEST_PASSWORD='minimaal-16-tekens' \
  -e VUS=5 \
  -e MODE=browse \
  tests/load/volunteer-journey.js
```

Voer afzonderlijke runs uit met 5, 25, 50 en alleen na een stabiele tussenmeting 100 virtuele gebruikers. Iedere gebruiker logt echt in, opent `/vrijwillig` en vraagt `user/me`, `my-shifts` en `shifts/available` op.

## Gelijktijdige inschrijving

Reset eerst alle inschrijvingen. Gebruik daarna het `contention_shift_id` uit `status`:

```bash
wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php reset

k6 run \
  -e BASE_URL=https://demo.rondo.club \
  -e LOADTEST_PASSWORD='minimaal-16-tekens' \
  -e VUS=20 \
  -e MODE=signup \
  -e CONTENTION_SHIFT_ID=123 \
  -e START_AT_MS=1700000000000 \
  tests/load/volunteer-journey.js

wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php verify 20
```

Zet `START_AT_MS` ongeveer vijftien seconden in de toekomst. Zo kunnen alle gebruikers eerst inloggen en vrijwel tegelijk inschrijven. Een geslaagde HTTP-respons is niet genoeg: `verify` moet bevestigen dat WordPress alle unieke inschrijvingen heeft behouden.

## Stopcriteria

- meer dan 1% mislukte requests;
- p95 boven 750 ms voor `user/me`;
- p95 boven 1,5 seconde voor vrijwilligerslijsten of inschrijven;
- HTTP 5xx-responses;
- verloren of dubbele inschrijvingen;
- zichtbare impact op productie, omdat demo dezelfde server gebruikt.
