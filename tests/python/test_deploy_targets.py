"""Keep demo deployments off production and preserve cache failures on each host."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class DeployTargetsTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name)
        self.env = dict(os.environ)
        for name in list(self.env):
            if name.startswith('DEPLOY_'):
                del self.env[name]

    def wrapper(self, overrides):
        shutil.copy(ROOT / 'bin/deploy-demo.sh', self.base / 'deploy-demo.sh')
        stub = self.base / 'deploy.sh'
        stub.write_text('#!/usr/bin/env python3\nimport json, os, sys\nprint(json.dumps({"env": dict(os.environ), "args": sys.argv[1:]}))\n')
        stub.chmod(0o755)
        self.env.update(overrides)
        return json.loads(subprocess.check_output(
            ['bash', str(self.base / 'deploy-demo.sh'), '--skip-build'],
            env=self.env, text=True,
        ))

    def test_production_environment_cannot_redirect_demo(self):
        result = self.wrapper({
            'DEPLOY_SSH_HOST': 'production.invalid', 'DEPLOY_SSH_USER': 'prod',
            'DEPLOY_SSH_PORT': '18765', 'DEPLOY_REMOTE_WP_PATH': '/production',
            'DEPLOY_REMOTE_THEME_PATH': '/production/theme',
            'DEPLOY_SSH_IDENTITY_FILE': '/production/key',
            'DEPLOY_SSH_KNOWN_HOSTS_FILE': '/production/hosts',
        })
        env = result['env']
        self.assertEqual('81.24.6.19', env['DEPLOY_SSH_HOST'])
        self.assertEqual('22', env['DEPLOY_SSH_PORT'])
        self.assertEqual('rondo-demo', env['DEPLOY_SSH_USER'])
        self.assertEqual('/srv/rondo-demo/public_html', env['DEPLOY_REMOTE_WP_PATH'])
        self.assertEqual('/srv/rondo-demo/public_html/wp-content/themes/rondo-club', env['DEPLOY_REMOTE_THEME_PATH'])
        self.assertEqual('', env['DEPLOY_SSH_IDENTITY_FILE'])
        self.assertEqual('', env['DEPLOY_SSH_KNOWN_HOSTS_FILE'])
        self.assertEqual(['--skip-build'], result['args'])

    def test_explicit_demo_target_and_key_overrides_are_preserved(self):
        env = self.wrapper({
            'DEPLOY_DEMO_SSH_HOST': 'rehearsal.invalid',
            'DEPLOY_DEMO_SSH_PORT': '2222', 'DEPLOY_DEMO_SSH_USER': 'rehearsal',
            'DEPLOY_DEMO_REMOTE_WP_PATH': '/rehearsal',
            'DEPLOY_DEMO_SSH_IDENTITY_FILE': '/rehearsal/key',
            'DEPLOY_DEMO_SSH_KNOWN_HOSTS_FILE': '/rehearsal/hosts',
            'DEPLOY_DEMO_URL': 'https://rehearsal.invalid/',
        })['env']
        self.assertEqual('rehearsal.invalid', env['DEPLOY_SSH_HOST'])
        self.assertEqual('2222', env['DEPLOY_SSH_PORT'])
        self.assertEqual('rehearsal', env['DEPLOY_SSH_USER'])
        self.assertEqual('/rehearsal/wp-content/themes/rondo-club', env['DEPLOY_REMOTE_THEME_PATH'])
        self.assertEqual('/rehearsal/key', env['DEPLOY_SSH_IDENTITY_FILE'])
        self.assertEqual('/rehearsal/hosts', env['DEPLOY_SSH_KNOWN_HOSTS_FILE'])
        self.assertEqual('https://rehearsal.invalid/', env['DEPLOY_PRODUCTION_URL'])

    def caches(self, has_sg=False, cache_exit=0, sg_exit=0):
        fake = self.base / 'wp'
        fake.write_text('''#!/bin/bash
echo "$*" >> "$CALLS"
case "$*" in
  'cache flush') exit "$CACHE_EXIT" ;;
  'cli has-command sg purge') test "$HAS_SG" = true ;;
  'sg purge') exit "$SG_EXIT" ;;
  *) exit 99 ;;
esac
''')
        fake.chmod(0o755)
        self.env.update(PATH=str(self.base) + ':' + self.env['PATH'],
                        CALLS=str(self.base / 'calls'), HAS_SG=str(has_sg).lower(),
                        CACHE_EXIT=str(cache_exit), SG_EXIT=str(sg_exit))
        source = (ROOT / 'bin/deploy.sh').read_text()
        function = source.split('clear_remote_caches() {', 1)[1].split('\n}', 1)[0]
        script = '''retry_remote() { bash -c "${@: -1}"; }
SSH_CMD=(ssh)
REMOTE_TARGET=unused
DEPLOY_REMOTE_WP_PATH="$1"
clear_remote_caches() {''' + function + '\n}\nclear_remote_caches\n'
        result = subprocess.run(['bash', '-c', script, 'test', str(self.base)], env=self.env)
        return result.returncode, (self.base / 'calls').read_text().splitlines()

    def test_generic_host_flushes_wordpress_without_siteground(self):
        code, calls = self.caches()
        self.assertEqual(0, code)
        self.assertEqual(['cache flush', 'cli has-command sg purge'], calls)

    def test_siteground_cache_is_still_flushed(self):
        code, calls = self.caches(has_sg=True)
        self.assertEqual(0, code)
        self.assertEqual('sg purge', calls[-1])

    def test_host_cache_errors_are_not_hidden(self):
        code, _ = self.caches(has_sg=True, sg_exit=7)
        self.assertEqual(7, code)

    def test_wordpress_cache_error_stops_host_purge(self):
        code, calls = self.caches(has_sg=True, cache_exit=6)
        self.assertEqual(6, code)
        self.assertEqual(['cache flush'], calls)

    def php_reload(self, user, failure=0):
        source = (ROOT / 'bin/deploy.sh').read_text()
        function = source.split('reload_remote_php() {', 1)[1].split('\n}', 1)[0]
        self.env.update(DEPLOY_SSH_USER=user, RELOAD_EXIT=str(failure),
                        CALLS=str(self.base / 'reload-calls'))
        script = '''retry_remote() { printf '%s\\n' "${@: -1}" >> "$CALLS"; return "$RELOAD_EXIT"; }
SSH_CMD=(ssh)
REMOTE_TARGET=unused
reload_remote_php() {''' + function + '\n}\nreload_remote_php\n'
        result = subprocess.run(['bash', '-c', script], env=self.env)
        calls = Path(self.env['CALLS'])
        return result.returncode, calls.read_text().splitlines() if calls.exists() else []

    def test_each_site_reloads_only_its_own_fpm_service(self):
        for site in ('prod', 'demo'):
            with self.subTest(site=site):
                (self.base / 'reload-calls').unlink(missing_ok=True)
                code, calls = self.php_reload('rondo-' + site)
                self.assertEqual(0, code)
                self.assertEqual(['sudo -n /usr/bin/systemctl reload rondo-php@' + site + '.service'], calls)

    def test_other_hosts_do_not_receive_a_privileged_reload(self):
        code, calls = self.php_reload('legacy-host-account')
        self.assertEqual(0, code)
        self.assertEqual([], calls)

    def test_reload_failure_is_propagated_to_deployment(self):
        code, calls = self.php_reload('rondo-demo', failure=7)
        self.assertEqual(7, code)
        self.assertEqual(1, len(calls))


if __name__ == '__main__':
    unittest.main()
