# Production PHP and object cache

These files record the configuration applied to `srv1.rondo` on 2026-11-10.
They are host configuration, not files automatically installed by theme deployment.
Production alone uses this instance; demo cannot access its Unix socket.
[Demo has a separate Redis instance](../demo/README.md).

## Choice

Use the free [Redis Object Cache](https://wordpress.org/plugins/redis-cache/)
plugin (installed version 3.0.0), the distribution's PhpRedis extension (6.2.0),
and Redis Server (8.0.5). The cache is shared by PHP-FPM, scheduled WordPress jobs,
and WP-CLI, including deploy cache invalidation. A process-local cache would not
provide that shared invalidation. A separate Redis instance avoids cross-site
cache flushes and access through a shared daemon. Relay and Object Cache Pro add
another dependency or license without a demonstrated need on this single host.

## Installed configuration

- `redis.conf` → `/etc/rondo/redis-prod.conf` (root:rondo-prod, 0640).
- `rondo-redis-prod.service` → `/etc/systemd/system/rondo-redis-prod.service`.
- `php-performance.conf` → `/etc/php/8.5/fpm/rondo/prod-performance.conf`, included
  at the end of `/etc/php/8.5/fpm/rondo/prod.conf`.
- `php-performance.service.conf` →
  `/etc/systemd/system/rondo-php@prod.service.d/performance.conf`.
- Packages: `redis-server`, `php8.5-redis` (also installs igbinary).
- The distribution's unused `redis-server.service` is masked. Only
  `rondo-redis-prod.service` is enabled.

Redis has no TCP listener, a 512 MiB dataset budget and allkeys-lru eviction.
Snapshots and AOF are disabled: WordPress is the source of truth. The Redis
service is capped at 768 MiB, allowing overhead beyond its dataset budget.
PHP has six dynamic workers, two warm workers, and the existing 1024 MiB
per-request / 4 GiB total service limits. Watch service memory and worker-limit
warnings before raising concurrency further; six requests simultaneously using
the full per-request allowance would exceed the service budget.

OPcache gets 256 MiB with 32 MiB for interned strings. These are startup flags,
not pool-level settings: the shared memory is allocated before pool overrides.
Remove the old pool `php_admin_value[opcache.memory_consumption]` override.
The changed ExecStart needs `systemctl daemon-reload` and a graceful service
restart, not merely a PHP reload. Timestamp validation stays enabled.

WordPress configuration (before `wp-settings.php`):

```php
define( 'WP_REDIS_SCHEME', 'unix' );
define( 'WP_REDIS_PATH', '/run/rondo-redis-prod/redis.sock' );
define( 'WP_REDIS_PREFIX', 'rondo-prod:' );
define( 'WP_REDIS_CLIENT', 'phpredis' );
define( 'WP_REDIS_DATABASE', 0 );
define( 'WP_REDIS_TIMEOUT', 1 );
define( 'WP_REDIS_READ_TIMEOUT', 1 );
```

Install/activate `redis-cache`, verify its WordPress.org checksums, then run
`wp redis enable` as `rondo-prod`. Preserve live database transients (including
login/OAuth state) and their remaining TTLs when transitioning to object caching;
never print their values or leave exports in the web root. The initial migration
preserved 44 live transients; another already existed when importing.
Do not enable the unsupported `WP_REDIS_GRACEFUL` option.

## Verification and recovery

- Check `wp redis status` and both service states.
- Check the real FPM runtime with `opcache_get_status(false)`; CLI settings alone
  do not prove the web runtime's cache state.
- Verify a short-lived WP-CLI cache value can be read from FPM, then remove it.
- Verify `rondo-demo` cannot connect to the production socket.
- Monitor Redis `INFO memory`, `INFO stats`, PHP service memory and the FPM log.
- Theme deploys already run `wp cache flush`; the independent Redis service,
  plugin and drop-in survive atomic theme releases.

## OPcache across releases

Atomic release paths are unique. On 2026-11-10, nine old releases filled the
256 MiB OPcache and 32 MiB string buffer, preventing the current release from
being cached. A graceful FPM reload clears that shared cache while allowing
in-flight requests to finish.

`bin/deploy.sh` now reloads the site's isolated FPM service after activation and
after rollback. Install `php-deploy-reload.sudoers` as
`/etc/sudoers.d/rondo-php-deploy-reload` (root:root, 0440), first checking it with
`visudo -cf`. Each deployment account may reload only its own service. Other
hosting accounts retain their existing deployment behavior. A failed reload
fails deployment and triggers rollback; it must not be silently skipped.

Configuration backups from initial rollout are root-only in
`/etc/rondo/performance-backup-20261110/`. To disable Redis, run `wp redis disable`
while Redis is healthy, then stop its service. Treat cache disable/flush as a
loss of transient state: temporary login links or OAuth exchanges may need a
retry. Do not blindly restore an old `wp-config.php` after unrelated changes.
