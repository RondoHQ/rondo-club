# Demo object cache

Applied on `srv1.rondo` on 2026-11-10 using the same Redis Object Cache 3.0.0
plugin and PhpRedis 6.2.0 driver as production. The plugin checksums were verified.

- `redis.conf` → `/etc/rondo/redis-demo.conf` (root:rondo-demo, 0640).
- `rondo-redis-demo.service` → `/etc/systemd/system/rondo-redis-demo.service`.
- Enable `rondo-redis-demo.service`; reload `rondo-php@demo` after installing
  the shared `php8.5-redis` extension.
- Demo has a 128 MiB Redis dataset budget with allkeys-lru eviction and a
  256 MiB service memory cap. Its existing two PHP workers remain in place.
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
