# Running the test suite

The PHP suite is Codeception + wp-browser, running the theme inside a real
WordPress install. 389 tests, green.

```bash
composer test                                   # everything
vendor/bin/codecept run Wpunit AgeGroupAccessTest   # one file
vendor/bin/codecept run Wpunit AgeGroupAccessTest:returns_null_for_admin_user
```

## What it needs

Three things, none of them in the repository, all pointed at by `tests/.env`
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

### 2. ACF Pro

The suite loads `advanced-custom-fields-pro/acf.php` explicitly, and the theme
refuses to boot without it. It is a paid plugin, so it cannot live in the repo.
Copy the licensed build from production:

```bash
cd ~/Code/rondo/wp-test/wp-content/plugins
source ~/Code/rondo/rondo-club/.env
scp -P "$DEPLOY_SSH_PORT" -r \
  "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST:$DEPLOY_REMOTE_WP_PATH/wp-content/plugins/advanced-custom-fields-pro" .
```

### 3. MySQL

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

### Known `_doing_it_wrong()` noise

ACF Pro emits a REST schema whose `type` keyword WordPress 7.0 rejects, on every
person and shift select field. It is upstream, and silent in production because
`_doing_it_wrong()` only speaks up under `WP_DEBUG` — but `WP_UnitTestCase` fails
any test that triggers an unexpected one. Declare it per test class:

```php
$this->ignoreIncorrectUsage( 'rest_handle_multi_type_schema' );
```

Not `setExpectedIncorrectUsage()`: that *requires* the notice to fire, and it
only fires for payloads that happen to carry such a field, so the test ends up
coupled to which fields are in the request. `ignoreIncorrectUsage()` drops that
one notice and leaves every other one fatal.

### Fixtures follow the current model, not the old one

Two shapes changed and still catch people out:

- **Todos** carry state in `post_status` (`rondo_open` / `rondo_awaiting` /
  `rondo_completed`) and relate through a `related_persons` **array**. The older
  `is_completed` + singular `related_person` shape is not what the endpoints
  query.
- **Person writes** round-trip the whole ACF object, and ACF marks several
  fields required. A partial `acf` payload is rejected with `400` before any of
  our guards run, so a test asserting `403` needs to send what the UI sends.

### Person access has two axes

Which people a user may see, and which fields they may read, are decided
separately — see `features/access-control.md` in the developer docs. A test that
creates a person as a plain member and expects to read it back is asserting the
old ownership model; use a role entitled to the record.

## CI

`.github/workflows/ci.yml` runs the suite on every push and pull request, with a
MySQL service container and the same pinned WordPress version.

It needs the repository secret **`ACF_PRO_LICENSE_KEY`** (ACF → Updates in
wp-admin, or the ACF account page). Without it the tests step is skipped with a
warning annotation rather than failing, so the absence of the secret is visible
but does not block deploys.
