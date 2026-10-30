#!/usr/bin/env python3
"""Stage immutable theme releases and atomically switch/restore the live symlink.

The existing directory is retained on first deployment using Linux renameat2
(RENAME_EXCHANGE); there is never a missing theme path. No WordPress data is
changed here. The durable transaction makes retries after lost SSH replies safe.
"""
import ctypes
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import time


def atomic_json(path, value):
    temporary = path.with_suffix('.tmp')
    temporary.write_text(json.dumps(value))
    os.replace(temporary, path)


def exchange(left, right):
    """Linux first-deploy directory-to-symlink conversion, without a rename gap."""
    libc = ctypes.CDLL(None, use_errno=True)
    rename = getattr(libc, 'renameat2', None)
    if rename is None:
        raise RuntimeError('Atomic bootstrap requires Linux renameat2; live theme unchanged')
    rename.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint]
    if rename(-100, os.fsencode(left), -100, os.fsencode(right), 2) != 0:
        raise OSError(ctypes.get_errno(), 'Atomic bootstrap failed; live theme unchanged')


def unpack(archive, destination):
    """Reject links, traversal and special files before writing any archive member."""
    with tarfile.open(archive, 'r:gz') as stream:
        members = stream.getmembers()
        for member in members:
            path = Path(member.name)
            if path.is_absolute() or '..' in path.parts or not (member.isdir() or member.isfile()):
                raise ValueError('Unsafe release archive member')
            if path.parts and path.parts[0] in ('.env', 'certs'):
                raise ValueError('Release archive must not contain runtime secrets')
        for member in members:
            path = destination / member.name
            # Existing runtime symlinks must never redirect extraction elsewhere.
            if not path.resolve().is_relative_to(destination.resolve()):
                raise ValueError('Release archive crosses an existing symlink')
            if member.isdir():
                path.mkdir(parents=True, exist_ok=True)
            else:
                path.parent.mkdir(parents=True, exist_ok=True)
                if path.is_symlink():
                    path.unlink()
                with stream.extractfile(member) as source, path.open('wb') as target:
                    shutil.copyfileobj(source, target)
                path.chmod(member.mode & 0o777)


def carry_assets(source, target):
    """Add missing hashed assets, also on rollback, without replacing live files."""
    for path in source.rglob('*'):
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in retained assets')
        if path.is_file():
            dest = target / path.relative_to(source)
            if not dest.exists():
                dest.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(path, dest)


def check_release(candidate):
    for name in ('functions.php', 'index.php', 'style.css', 'vendor/autoload.php'):
        if not (candidate / name).is_file():
            raise ValueError('Incomplete release: ' + name)
    manifests = [candidate / 'dist/.vite/manifest.json', candidate / 'dist/manifest.json']
    manifest = next((path for path in manifests if path.is_file()), None)
    if not manifest:
        raise ValueError('Missing Vite manifest')
    entries = json.loads(manifest.read_text())
    if not entries:
        raise ValueError('Empty Vite manifest')
    for entry in entries.values():
        for name in [entry['file'], *entry.get('css', []), *entry.get('assets', [])]:
            path = candidate / 'dist' / name
            if not path.resolve().is_relative_to(candidate / 'dist') or not path.is_file():
                raise ValueError('Missing or unsafe Vite asset')
    # Check all Composer class paths before serving the release, without loading WP.
    subprocess.run(['php', '-r', 'foreach (require $argv[1] as $file) { if (!is_file($file)) { fwrite(STDERR, "Missing Composer class file\n"); exit(1); } }', str(candidate / 'vendor/composer/autoload_classmap.php')], check=True)


def run(action, live, root, release_id, args):
    candidate = root / release_id / live.name
    legacy = root / ('legacy-' + release_id) / live.name
    state_path = root / (release_id + '.json')
    state = json.loads(state_path.read_text()) if state_path.exists() else None
    current = live.resolve(strict=True)

    if action == 'prepare':
        if state:
            if state['status'] in ('prepared', 'activating', 'active', 'finished'):
                return
            raise RuntimeError('This transaction was rolled back; use a fresh release ID')
        archive, digest, prune = args
        checksum = hashlib.sha256()
        with Path(archive).open('rb') as uploaded:
            for chunk in iter(lambda: uploaded.read(1024 * 1024), b''):
                checksum.update(chunk)
        if checksum.hexdigest() != digest:
            raise ValueError('Upload checksum mismatch; live theme unchanged')
        if live.is_symlink() and not current.is_relative_to(root):
            raise ValueError('Live theme points outside the managed release root')
        if candidate.exists():
            shutil.rmtree(candidate.parent)
        # Preserve existing host-only files; controlled code is replaced in staging.
        excluded = {'.git', 'node_modules', 'tests', '.artifacts', '.bg-shell', 'certs', '.env'}
        if prune == 'true':
            excluded.update(('includes', 'bin', 'src', 'vendor'))
        shutil.copytree(current, candidate, symlinks=True,
                        ignore=lambda path, names: excluded.intersection(names) if Path(path) == current else [])
        unpack(archive, candidate)
        check_release(candidate)
        subprocess.run(['php', str(root / (release_id + '-retain-assets.php')), 'prune', str(candidate / 'dist')], check=True)
        # The original directory is retained permanently. Certificate paths keep
        # pointing to it across releases and rollbacks, rather than stale copies.
        for name in ('certs', '.env'):
            existing = current / name
            if existing.exists():
                target = existing.resolve() if live.is_symlink() else legacy / name
                (candidate / name).symlink_to(target, target_is_directory=existing.is_dir())
        info = live.stat()
        state = {'status': 'prepared', 'previous': str(current), 'bootstrap': not live.is_symlink(),
                 'device': info.st_dev, 'inode': info.st_ino, 'created': time.time()}
        atomic_json(state_path, state)
        return

    if action == 'rollback' and not state:
        # A failed extraction may have left an inactive directory without state.
        if candidate.parent.exists() and current != candidate:
            shutil.rmtree(candidate.parent)
        (root / ('upload-' + release_id + '.tar.gz')).unlink(missing_ok=True)
        return  # Failed upload/prepare: live files were never touched.
    if not state:
        raise RuntimeError('No prepared release transaction')
    previous = legacy if state['bootstrap'] else Path(state['previous'])

    def retire_current():
        history = root / (current.parent.name + '.json')
        if current.is_relative_to(root) and history.exists() and history != state_path:
            data = json.loads(history.read_text())
            data['retired_at'] = time.time()
            atomic_json(history, data)

    if action == 'activate':
        if current == candidate and state['status'] in ('activating', 'active'):
            return
        if state['status'] != 'prepared' or str(current) != state['previous']:
            raise RuntimeError('Live release changed since preparation; refusing stale deployment')
        info = live.stat()
        if (info.st_dev, info.st_ino) != (state['device'], state['inode']):
            raise RuntimeError('Live directory changed since preparation')
        # Install early request pinning and monitoring atomically, before switching.
        mu = live.parent.parent / 'mu-plugins'
        mu.mkdir(exist_ok=True)
        for name in ('rondo-00-release.php', 'rondo-error-monitoring.php'):
            temporary = mu / ('.' + release_id + '-' + name)
            shutil.copy2(root / (release_id + '-' + name), temporary)
            os.replace(temporary, mu / name)
        retire_current()
        state['status'] = 'activating'
        atomic_json(state_path, state)
        if state['bootstrap']:
            legacy.parent.mkdir(exist_ok=True)
            if legacy.is_symlink():
                legacy.unlink()  # Retry after failure before the exchange.
            legacy.symlink_to(candidate, target_is_directory=True)
            exchange(live, legacy)
        else:
            link = root / ('.next-' + release_id)
            if link.is_symlink():
                link.unlink()
            link.symlink_to(candidate, target_is_directory=True)
            os.replace(link, live)
        state['status'] = 'active'
        atomic_json(state_path, state)
    elif action == 'rollback':
        if state['status'] in ('finished', 'rolled_back'):
            return
        if current == candidate:
            # A tab may already have loaded the failed release before health checks.
            carry_assets(candidate / 'dist/assets', previous / 'dist/assets')
            state['retired_at'] = time.time()
            link = root / ('.rollback-' + release_id)
            if link.is_symlink():
                link.unlink()
            link.symlink_to(previous, target_is_directory=True)
            os.replace(link, live)
        elif str(current) != state['previous']:
            raise RuntimeError('A different deployment is live; refusing to overwrite it')
        state['status'] = 'rolled_back'
        atomic_json(state_path, state)
        (root / ('upload-' + release_id + '.tar.gz')).unlink(missing_ok=True)
    elif action == 'finish':
        if current != candidate or state['status'] not in ('active', 'finished'):
            raise RuntimeError('Cannot confirm a release that is not active')
        state['status'] = 'finished'
        atomic_json(state_path, state)
        # Keep at least three releases and every release used in the past hour.
        # Legacy/runtime data is never pruned. Only completed managed releases qualify.
        completed = []
        for path in root.glob('*.json'):
            data = json.loads(path.read_text())
            if data.get('status') in ('finished', 'rolled_back'):
                completed.append((data.get('retired_at', data['created']), path.stem))
        completed.sort(reverse=True)
        for created, identifier in completed[3:]:
            directory = root / identifier
            if created < time.time() - 3600 and directory != candidate.parent and directory != previous.parent:
                if directory.is_dir() and not directory.is_symlink():
                    shutil.rmtree(directory)
        for suffix in ('.tar.gz',):
            (root / ('upload-' + release_id + suffix)).unlink(missing_ok=True)
    else:
        raise ValueError('Unknown release action')


if __name__ == '__main__':
    action, live_arg, root_arg, release_id, *args = sys.argv[1:]
    if not re.fullmatch(r'[A-Za-z0-9_-]+', release_id):
        raise ValueError('Invalid release ID')
    live, root = Path(live_arg).absolute(), Path(root_arg).resolve()
    if live.name != 'rondo-club' or root.is_relative_to(live) or live.is_relative_to(root):
        raise ValueError('Invalid live theme or release root')
    root.mkdir(parents=True, exist_ok=True)
    with (root / '.deploy.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        run(action, live, root, release_id, args)
