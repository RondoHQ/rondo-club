# Demo PHP and object cache

Applied on `srv1.rondo` on 2026-11-10 using the same Redis Object Cache 3.0.0
plugin and PhpRedis 6.2.0 driver as production. The plugin checksums were verified.

- `redis.conf` → `/etc/rondo/redis-demo.conf` (root:rondo-demo, 0640).
- `rondo-redis-demo.service` → `/etc/systemd/system/rondo-redis-demo.service`.
- Enable `rondo-redis-demo.service`; reload `rondo-php@demo` after installing
  the shared `php8.5-redis` extension.
- Demo has a 128 MiB Redis dataset budget with allkeys-lru eviction and a
  256 MiB service memory cap.
- WordPress uses the same constants documented for production, substituting
  `/run/rondo-redis-demo/redis.sock` for `WP_REDIS_PATH` and `rondo-demo:` for
  `WP_REDIS_PREFIX`.
- There is no TCP listener or shared Redis database. Unix socket permissions
  prevent either site's service account from accessing the other instance.

The transition preserved nine live database transients with their remaining
TTLs; one other transient already existed when importing. Backups of the initial
WordPress configuration are root-only in
`/etc/rondo/demo-performance-backup-20261110/`.

Verify both `wp redis status` (as `rondo-demo`) and a short-lived cache key shared
between WP-CLI and the demo FPM socket. Test both cross-site socket connections
are denied. Recovery follows the production instructions, using demo paths and
service names. Never point demo at the production socket.

## PHP and release parity

- `php-performance.conf` → `/etc/php/8.5/fpm/rondo/demo-performance.conf`, included
  at the end of `/etc/php/8.5/fpm/rondo/demo.conf`.
- `php-performance.service.conf` →
  `/etc/systemd/system/rondo-php@demo.service.d/zz-performance.conf`. The filename
  is important: it overrides the memory limits in the earlier `resources.conf`.
- Remove the old pool-level `opcache.memory_consumption` override. Startup flags
  allocate 256 MiB of OPcache and 32 MiB for interned strings.
- Three dynamic workers, one warm worker, and 256 MiB per request fit within a
  1 GiB soft / 1.25 GiB hard service limit. Keep the existing lower CPU weight
  and one-core quota so production remains the priority.
- Install the narrowly scoped [reload sudoers rules](../production/php-deploy-reload.sudoers)
  as `/etc/sudoers.d/rondo-php-deploy-reload` (root:root, 0440), validating with
  `visudo -cf` first. Demo deployments reload only the demo FPM service.

Deploy the same verified GitHub Actions release artifact as production with
`bin/deploy-demo.sh --source-dir /path/to/release --skip-build --prune`.
Take a protected database backup first; atomic deployment retains the previous
theme release. Verify demo mode, record counts, dashboard and people APIs,
Redis connectivity, the metadata index and the real FPM OPcache after deployment.

Configuration and database backups for this rollout are retained under
`/etc/rondo/demo-performance-rollout-20261110/` and
`/srv/rondo-demo/backups/performance-20261110/`, respectively.
