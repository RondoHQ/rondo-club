# Running the test suite

The PHP suite is Codeception + wp-browser, running the theme inside a real
WordPress install. The suite boots without external field plugins.

```bash
composer test                                   # everything
vendor/bin/codecept run Wpunit AgeGroupAccessTest   # one file
vendor/bin/codecept run Wpunit AgeGroupAccessTest:returns_null_for_admin_user
```

## What it needs

Two things, neither in the repository, both pointed at by `tests/.env`
(gitignored, because the paths are machine-specific).

### 1. A throwaway WordPress install

```bash
mkdir -p ~/Code/rondo/wp-test
cd ~/Code/rondo/wp-test
wp core download --version=7.0.2 --skip-content
mkdir -p wp-content/plugins wp-content/themes
ln -sfn ~/Code/rondo/rondo-club wp-content/themes/rondo-club
```

The theme is **symlinked**, so the suite runs your working tree rather than a
copy — edit code, rerun, no sync step. Keep the WordPress version in step with
production (`wp core version` over SSH tells you what that is); the CI workflow
pins the same one.

### 2. MySQL

```bash
docker run -d --name rondo-test-db \
  -e MYSQL_ROOT_PASSWORD=rondo -e MYSQL_DATABASE=rondo_tests \
  -p 3307:3306 mysql:8.4
```

Afterwards just `docker start rondo-test-db`.

**Not SQLite**, even though wp-browser bundles it and it needs no container.
Several queries compare meta values with `'type' => 'DATETIME'`, which SQLite
cannot evaluate: the shift calendar then silently returns nothing and its tests
fail for reasons that have nothing to do with the code under test. The failure
looks like a product bug, which is worse than no test at all.

### `tests/.env`

```ini
WP_ROOT_FOLDER=/Users/you/Code/rondo/wp-test
TEST_DB_URL=mysql://root:rondo@127.0.0.1:3307/rondo_tests
WP_DOMAIN=rondo.test
```

Comments must use `#`; the parser rejects `;`.

## Writing tests

### REST routes have to be booted

The theme only instantiates its REST controllers on real REST requests, so in a
test their routes do not exist and **every dispatch answers 404** — which is
indistinguishable from a permission check working. Every REST test must boot its
controllers first:

```php
$server = $this->bootRestControllers( [ \Rondo\REST\MemberShifts::class ] );
```

Order matters: `rest_get_server()` fires `rest_api_init` once, and a controller
constructed after that has already missed it. `bootRestControllers()` handles
this — do not hand-roll it.

### Native field contracts

REST tests should write partial canonical payloads under `fields` and assert that
responses never contain the retired legacy attribute. Storage tests should also
assert the exact meta keys, repeater count, numbered child rows, reference rows,
and stale-row cleanup. `SmokeTest` verifies that the old helper functions are not
present, which prevents a plugin from masking an incomplete native code path.

### Fixtures follow the current model, not the old one

Two shapes changed and still catch people out:

- **Todos** carry state in `post_status` (`rondo_open` / `rondo_awaiting` /
  `rondo_completed`) and relate through a `related_persons` **array**. The older
  `is_completed` + singular `related_person` shape is not what the endpoints
  query.
- **Person writes** use partial `fields` objects. Dates and nested relationship
  keys must use their canonical wire formats and names.

### Person access has two axes

Which people a user may see, and which fields they may read, are decided
separately — see `features/access-control.md` in the developer docs. A test that
creates a person as a plain member and expects to read it back is asserting the
old ownership model; use a role entitled to the record.

## CI

`.github/workflows/ci.yml` runs the suite on every push and pull request, with a
MySQL service container and the same pinned WordPress version.

No paid-plugin download or license secret is required. The CI test job always
runs and therefore blocks deployment on a PHP regression.
