"""Exercise real files, archives, PHP requests and deployment failure paths."""
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import tempfile
import time
import unittest

REPO = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('release', REPO / 'bin/atomic-release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


def theme(path, version):
    (path / 'dist/assets').mkdir(parents=True)
    (path / 'vendor/composer').mkdir(parents=True)
    (path / 'includes').mkdir()
    (path / 'functions.php').write_text('<?php // ' + version)
    (path / 'index.php').write_text('<?php // index')
    (path / 'style.css').write_text('Version: ' + version + '\n')
    (path / 'vendor/autoload.php').write_text('<?php // autoload')
    (path / 'vendor/composer/autoload_classmap.php').write_text("<?php return ['Example' => __DIR__ . '/../../includes/class-example.php'];")
    (path / 'includes/class-example.php').write_text('<?php // class ' + version)
    (path / f'dist/assets/{version}.js').write_text('// ' + version)
    (path / 'dist/manifest.json').write_text(json.dumps({'main': {'file': f'assets/{version}.js'}}))
    (path / 'dist/.asset-inventory.json').write_text(json.dumps([f'{version}.js']))
    (path / 'dist/sw.js').write_text(version)


class AtomicReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='rondo-release-test-')
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name).resolve()
        self.wp = self.base / 'public_html'
        self.live = self.wp / 'wp-content/themes/rondo-club'
        self.live.parent.mkdir(parents=True)
        self.root = self.base / '.rondo-releases/rondo-club'
        self.root.mkdir(parents=True)
        self.old = self.root / 'baseline/rondo-club'
        theme(self.old, '1.0.0')
        (self.old / 'certs').mkdir()
        (self.old / 'certs/synthetic.pem').write_text('fixture only')
        self.live.symlink_to(self.old, target_is_directory=True)
        self.source = self.base / 'source'
        theme(self.source, '1.0.1')
        self.stage('candidate')

    def stage(self, identifier, source=None):
        archive = self.root / ('upload-' + identifier + '.tar.gz')
        with tarfile.open(archive, 'w:gz') as stream:
            stream.add(source or self.source, arcname='.')
        for name, directory in [('retain-assets.php', 'bin'), ('rondo-00-release.php', 'mu-plugins'), ('rondo-error-monitoring.php', 'mu-plugins')]:
            shutil.copy2(REPO / directory / name, self.root / (identifier + '-' + name))
        return archive, hashlib.sha256(archive.read_bytes()).hexdigest()

    def action(self, action, identifier='candidate', *args):
        if action == 'prepare' and not args:
            archive = self.root / ('upload-' + identifier + '.tar.gz')
            args = (str(archive), hashlib.sha256(archive.read_bytes()).hexdigest(), 'true')
        return release.run(action, self.live, self.root, identifier, args)

    def candidate(self, identifier='candidate'):
        return self.root / identifier / 'rondo-club'

    def test_prepare_never_changes_live_code_or_manifest_and_preserves_old_assets(self):
        (self.old / 'includes/deleted.php').write_text('<?php // retired')
        self.action('prepare')
        self.assertEqual(self.live.resolve(), self.old)
        self.assertEqual((self.live / 'dist/sw.js').read_text(), '1.0.0')
        self.assertFalse((self.candidate() / 'includes/deleted.php').exists())
        self.assertTrue((self.candidate() / 'dist/assets/1.0.0.js').exists())
        self.assertTrue((self.candidate() / 'dist/assets/1.0.1.js').exists())
        self.assertEqual((self.candidate() / 'certs').resolve(), self.old / 'certs')
        self.action('activate')
        self.action('activate')  # Lost SSH reply is safe to retry.
        self.assertEqual(self.live.resolve(), self.candidate())
        self.action('finish')
        self.assertTrue(self.old.exists())

    def test_failed_upload_missing_class_or_asset_keeps_previous_release(self):
        archive = self.root / 'upload-candidate.tar.gz'
        with self.assertRaisesRegex(ValueError, 'checksum'):
            self.action('prepare', 'candidate', str(archive), 'bad', 'true')
        (self.source / 'includes/class-example.php').unlink()
        self.stage('missing-class')
        with self.assertRaises(subprocess.CalledProcessError):
            self.action('prepare', 'missing-class')
        self.assertEqual(self.live.resolve(), self.old)
        (self.source / 'dist/assets/1.0.1.js').unlink()
        self.stage('missing-asset')
        with self.assertRaisesRegex(ValueError, 'Vite asset'):
            self.action('prepare', 'missing-asset')
        self.assertEqual(self.live.resolve(), self.old)
        self.action('rollback', 'missing-asset')

    def test_failed_health_check_can_restore_previous_release_without_breaking_new_tabs(self):
        self.action('prepare')
        self.action('activate')
        self.action('rollback')
        self.action('rollback')
        self.assertEqual(self.live.resolve(), self.old)
        self.assertTrue((self.live / 'dist/assets/1.0.1.js').exists())
        self.assertTrue((self.candidate() / 'includes/class-example.php').exists())

    def test_stale_deployment_cannot_overwrite_another_release(self):
        self.action('prepare')
        self.stage('other')
        self.action('prepare', 'other')
        self.action('activate', 'other')
        with self.assertRaisesRegex(RuntimeError, 'changed'):
            self.action('activate')
        with self.assertRaisesRegex(RuntimeError, 'different deployment'):
            self.action('rollback')
        self.assertEqual(self.live.resolve(), self.candidate('other'))

    def test_rollback_after_lost_activation_reply(self):
        self.action('prepare')
        self.action('activate')
        state = self.root / 'candidate.json'
        data = json.loads(state.read_text())
        data['status'] = 'activating'
        state.write_text(json.dumps(data))
        self.action('rollback')
        self.assertEqual(self.live.resolve(), self.old)

    def test_unsafe_archive_is_rejected_without_touching_live(self):
        archive = self.root / 'upload-candidate.tar.gz'
        with tarfile.open(archive, 'w:gz') as stream:
            info = tarfile.TarInfo('../escape')
            info.size = 3
            stream.addfile(info, io.BytesIO(b'bad'))
        with self.assertRaisesRegex(ValueError, 'Unsafe'):
            self.action('prepare')
        self.assertEqual(self.live.resolve(), self.old)
        self.assertFalse((self.root / 'candidate/escape').exists())

    @unittest.skipUnless(sys.platform == 'linux', 'First directory exchange uses Linux renameat2')
    def test_bootstrap_is_atomic_and_preserves_original_runtime_data(self):
        self.live.unlink()
        shutil.move(self.old, self.live)
        self.action('prepare')
        self.action('activate')
        legacy = self.root / 'legacy-candidate/rondo-club'
        self.assertTrue(self.live.is_symlink())
        self.assertTrue(legacy.is_dir())
        self.assertEqual((self.live / 'certs').resolve(), legacy / 'certs')
        self.action('rollback')
        self.assertEqual(self.live.resolve(), legacy)
        self.assertEqual((self.live / 'certs/synthetic.pem').read_text(), 'fixture only')

    def test_cleanup_keeps_active_recently_retired_and_legacy_runtime_paths(self):
        self.action('prepare')
        self.action('activate')
        now = time.time()
        for index in range(6):
            identifier = 'old-' + str(index)
            (self.root / identifier).mkdir()
            state = {'status': 'finished', 'created': now - (index + 2) * 7200}
            if index == 5:
                state['retired_at'] = now  # Old release was just rolled back from.
            (self.root / (identifier + '.json')).write_text(json.dumps(state))
        self.action('finish')
        self.assertFalse((self.root / 'old-4').exists())
        self.assertTrue((self.root / 'old-5').exists())
        self.assertTrue(self.live.resolve().is_dir())
        self.assertEqual((self.live / 'certs/synthetic.pem').read_text(), 'fixture only')

    def test_php_request_and_monitoring_stay_on_pinned_release_after_switch(self):
        monitoring = self.old / 'includes/class-error-monitoring.php'
        monitoring.write_text('<?php namespace Rondo; class ErrorMonitoring { static function boot() { echo "monitor-old\\n"; } }')
        self.action('prepare')
        (self.candidate() / 'includes/class-error-monitoring.php').write_text('<?php namespace Rondo; class ErrorMonitoring { static function boot() { echo "monitor-new\\n"; } }')
        php = self.base / 'request.php'
        php.write_text('''<?php
        define('ABSPATH', __DIR__); define('RONDO_GLITCHTIP_DSN', 'fixture');
        define('WP_CONTENT_DIR', $argv[1]);
        function add_filter($name, $callback, $priority, $args) { $GLOBALS['filters'][$name] = $callback; }
        require $argv[2];
        echo "ready\\n"; flush(); fgets(STDIN);
        clearstatcache(true);
        foreach (['template_directory', 'stylesheet_directory'] as $filter) {
            echo $GLOBALS['filters'][$filter]('unused', 'rondo-club') . "\\n";
            echo $GLOBALS['filters'][$filter]('other', 'other-theme') . "\\n";
        }
        require $argv[3];
        ''')
        with subprocess.Popen(['php', str(php), str(self.wp / 'wp-content'), str(REPO / 'mu-plugins/rondo-00-release.php'), str(REPO / 'mu-plugins/rondo-error-monitoring.php')], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) as process:
            self.assertEqual(process.stdout.readline(), 'ready\n')
            self.action('activate')
            stdout, stderr = process.communicate('\n', timeout=10)
            self.assertEqual(process.returncode, 0, stderr)
            self.assertEqual(stdout.splitlines(), [str(self.old), 'other', str(self.old), 'other', 'monitor-old'])

    def test_real_deploy_script_rolls_back_when_public_health_check_fails(self):
        # git archives include this documentation symlink; it is not runtime code.
        (self.source / 'AGENTS.md').write_text('Fixture instructions')
        (self.source / 'CLAUDE.md').symlink_to('AGENTS.md')
        transport = self.base / 'transport'
        transport.mkdir()
        scripts = {
            'ssh': '#!/bin/bash\nexec bash -c "${@: -1}"\n',
            'scp': '#!/bin/bash\nargs=("$@"); n=${#args[@]}; dest="${args[$((n-1))]}"; exec cp "${args[$((n-2))]}" "${dest#*:}"\n',
            'wp': '#!/bin/bash\nif [ "$1" = theme ]; then awk -F": " \'/^Version:/ {print $2}\' wp-content/themes/rondo-club/style.css; fi\n',
            'curl': '#!/bin/bash\nif [ "$TEST_HTTP" = success ]; then echo \'{"version":"1.0.1"}\'; elif [ "$TEST_HTTP" = stale ]; then echo \'{"version":"1.0.0"}\'; else exit 22; fi\n',
        }
        for name, content in scripts.items():
            path = transport / name
            path.write_text(content)
            path.chmod(0o755)
        env = {**os.environ, 'PATH': str(transport) + ':' + os.environ['PATH'], 'DEPLOY_SSH_HOST': 'fixture', 'DEPLOY_SSH_PORT': '22', 'DEPLOY_SSH_USER': 'fixture', 'DEPLOY_REMOTE_WP_PATH': str(self.wp), 'DEPLOY_REMOTE_THEME_PATH': str(self.live), 'DEPLOY_PRODUCTION_URL': 'https://fixture.invalid', 'DEPLOY_RETRY_DELAY': '0'}
        for http in ('failure', 'stale', 'success'):
            with self.subTest(http=http):
                result = subprocess.run(['bash', str(REPO / 'bin/deploy.sh'), '--source-dir', str(self.source), '--skip-build', '--prune'], env={**env, 'TEST_HTTP': http}, capture_output=True, text=True)
                self.assertIn('Activating the complete release', result.stdout, result.stderr)
                if http == 'success':
                    self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
                    self.assertNotEqual(self.live.resolve(), self.old)
                else:
                    self.assertNotEqual(result.returncode, 0, result.stdout)
                    self.assertIn('restoring the previous release', result.stderr)
                    self.assertEqual(self.live.resolve(), self.old)
                self.assertTrue((self.live / 'dist/assets/1.0.1.js').exists())


if __name__ == '__main__':
    unittest.main()
