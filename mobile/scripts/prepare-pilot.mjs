import { cp, mkdir, mkdtemp, readFile, writeFile, symlink } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { resolve, join, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

// Builds an isolated native project. Never rewrites the working simulator project or copies .env.
const source = resolve(fileURLToPath(new URL('..', import.meta.url)));
const root = await mkdtemp(join(tmpdir(), 'rondo-awc-pilot-'));
const target = join(root, 'mobile');
const excluded = new Set(['node_modules', 'dist', 'build', '.gradle', '.idea', 'local.properties', 'public']);
await cp(source, target, { recursive: true, filter: path => {
  const name = basename(path);
  if (name.startsWith('.env') || excluded.has(name) && path !== join(source, 'public')) return false;
  // Temporary debug CA overrides are never part of a live pilot build.
  return !path.includes('/app/src/debug');
} });
await symlink(join(source, 'node_modules'), join(target, 'node_modules'), 'dir');
await symlink(resolve(source, '../src'), join(root, 'src'), 'dir');

async function edit(path, fn) {
  const file = join(target, path);
  await writeFile(file, fn(await readFile(file, 'utf8')));
}
await edit('capacitor.config.json', text => {
  const config = JSON.parse(text); config.appId = 'club.rondo.pilot'; config.appName = 'Rondo Pilot';
  if (config.server?.url) throw new Error('A pilot must use bundled assets.');
  return JSON.stringify(config, null, 2) + '\n';
});
await edit('ios/App/App/Info.plist', text => text.replace(/\t<key>CFBundleURLTypes<\/key>[\s\S]*?(?=<\/dict>\s*<\/plist>)/, '').replace('Rondo Proef', 'Rondo Pilot'));
await edit('ios/App/App.xcodeproj/project.pbxproj', text => text.replaceAll('club.rondo.spike', 'club.rondo.pilot').replaceAll('CURRENT_PROJECT_VERSION = ', 'CODE_SIGN_ENTITLEMENTS = App/Pilot.entitlements;\n\t\t\t\tCURRENT_PROJECT_VERSION = '));
await edit('ios/App/App/Simulator.entitlements', text => text.replaceAll('club.rondo.spike', 'club.rondo.pilot'));
await cp(join(source, 'pilot-native/PrivacyInfo.xcprivacy'), join(target, 'ios/App/App/PrivacyInfo.xcprivacy'));
await edit('ios/App/App.xcodeproj/project.pbxproj', text => text
  .replace(/IPHONEOS_DEPLOYMENT_TARGET = [0-9.]+;/g, 'IPHONEOS_DEPLOYMENT_TARGET = 17.4;')
  .replace('/* Begin PBXBuildFile section */', '/* Begin PBXBuildFile section */\n\t\tA7C000000000000000000001 /* PrivacyInfo.xcprivacy in Resources */ = {isa = PBXBuildFile; fileRef = A7C000000000000000000002; };')
  .replace('/* Begin PBXFileReference section */', '/* Begin PBXFileReference section */\n\t\tA7C000000000000000000002 /* PrivacyInfo.xcprivacy */ = {isa = PBXFileReference; lastKnownFileType = text.xml; path = PrivacyInfo.xcprivacy; sourceTree = "<group>"; };')
  .replace('504EC3131FED79650016851F /* Info.plist */,', '504EC3131FED79650016851F /* Info.plist */,\n\t\t\t\tA7C000000000000000000002 /* PrivacyInfo.xcprivacy */,')
  .replace('504EC3121FED79650016851F /* LaunchScreen.storyboard in Resources */,', '504EC3121FED79650016851F /* LaunchScreen.storyboard in Resources */,\n\t\t\t\tA7C000000000000000000001 /* PrivacyInfo.xcprivacy in Resources */,'));
const authSessionSource = await readFile(join(source, 'pilot-native/RondoAuthSession.swift'), 'utf8');
await edit('ios/App/App/SceneDelegate.swift', text => text.replaceAll('club.rondo.spike.session', 'club.rondo.pilot.session').replace('bridge?.registerPluginInstance(RondoWallet())', 'bridge?.registerPluginInstance(RondoWallet())\n        bridge?.registerPluginInstance(RondoAuthSession())') + '\n' + authSessionSource);
await writeFile(join(target, 'ios/App/App/Pilot.entitlements'), '<?xml version="1.0" encoding="UTF-8"?>\n<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">\n<plist version="1.0"><dict><key>com.apple.developer.associated-domains</key><array><string>applinks:rondo.svawc.nl</string><string>webcredentials:rondo.svawc.nl</string></array></dict></plist>\n');
await edit('ios/App/App/SceneDelegate.swift', text => text.replace('    var window: UIWindow?', `    var window: UIWindow?
    private var privacyCover: UIView?

    func sceneWillResignActive(_ scene: UIScene) {
        guard let window = window, privacyCover == nil else { return }
        let cover = UIView(frame: window.bounds)
        cover.backgroundColor = .systemBackground
        cover.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        window.addSubview(cover)
        privacyCover = cover
    }

    func sceneDidBecomeActive(_ scene: UIScene) {
        privacyCover?.removeFromSuperview()
        privacyCover = nil
    }`));
await edit('android/app/src/main/java/club/rondo/spike/MainActivity.java', text => text.replace('super.onCreate(savedInstanceState);', 'super.onCreate(savedInstanceState);\n        getWindow().setFlags(android.view.WindowManager.LayoutParams.FLAG_SECURE, android.view.WindowManager.LayoutParams.FLAG_SECURE);'));
await edit('android/app/build.gradle', text => text.replace('applicationId "club.rondo.spike"', 'applicationId "club.rondo.pilot"'));
await edit('android/app/src/main/res/values/strings.xml', text => text.replaceAll('Rondo Proef', 'Rondo Pilot').replaceAll('club.rondo.spike', 'club.rondo.pilot'));
await edit('android/app/src/main/java/club/rondo/spike/RondoSessionVault.java', text => text.replace('"club.rondo.spike.session"', '"club.rondo.pilot.session"'));
await edit('android/app/src/main/AndroidManifest.xml', text => text.replace('<intent-filter>\n                <action android:name="android.intent.action.VIEW"', '<intent-filter android:autoVerify="true">\n                <action android:name="android.intent.action.VIEW"').replace('<data android:scheme="club.rondo.spike" android:host="oauth" android:path="/callback" />', '<data android:scheme="https" android:host="rondo.svawc.nl" android:path="/rondo-app/callback" />'));
execFileSync(join(source, 'node_modules/.bin/vite'), ['build', '--mode', 'pilot'], { cwd: target, stdio: 'inherit' });
execFileSync(join(source, 'node_modules/.bin/cap'), ['sync'], { cwd: target, stdio: 'inherit' });

const plugin = join(root, 'rondo-awc-pilot');
await mkdir(plugin);
await cp(join(source, 'shared'), join(plugin, 'shared'), { recursive: true });
await cp(join(source, 'pilot-plugin'), join(plugin, 'pilot-plugin'), { recursive: true });
await writeFile(join(plugin, 'rondo-awc-pilot.php'), "<?php\n/**\n * Plugin Name: Rondo AWC Pilot\n * Description: Explicitly enabled native AWC pilot.\n * Version: 0.8.0\n */\nrequire_once __DIR__ . '/pilot-plugin/rondo-mobile-pilot.php';\n");
// Non-secret association files can be generated only from supplied real signing identities.
const team = process.env.RONDO_APPLE_TEAM_ID;
const fingerprint = process.env.RONDO_ANDROID_CERT_SHA256;
const wellKnown = join(root, 'well-known'); await mkdir(wellKnown);
if (team) {
  if (!/^[A-Z0-9]{10}$/.test(team)) throw new Error('Invalid Apple Team ID');
  await writeFile(join(wellKnown, 'apple-app-site-association'), JSON.stringify({ webcredentials: { apps: [`${team}.club.rondo.pilot`] }, applinks: { details: [{ appIDs: [`${team}.club.rondo.pilot`], components: [{ '/': '/rondo-app/callback' }] }] } }, null, 2));
}
if (fingerprint) {
  if (!/^([A-F0-9]{2}:){31}[A-F0-9]{2}$/.test(fingerprint)) throw new Error('Invalid Android certificate fingerprint');
  await writeFile(join(wellKnown, 'assetlinks.json'), JSON.stringify([{ relation: ['delegate_permission/common.handle_all_urls'], target: { namespace: 'android_app', package_name: 'club.rondo.pilot', sha256_cert_fingerprints: [fingerprint] } }], null, 2));
}
await writeFile(join(root, 'READINESS.json'), JSON.stringify({ appId: 'club.rondo.pilot', club: 'https://rondo.svawc.nl', scope: 'rondo:pilot:read', appleAssociationPrepared: Boolean(team), androidAssociationPrepared: Boolean(fingerprint), signingConfigured: false, uploaded: false, deployed: false }, null, 2));
console.log(`\nPilot project prepared: ${root}`);
console.log('No signing, upload, deployment or tester invitations performed.');
