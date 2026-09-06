import UIKit
import Capacitor
import Security
import PassKit

class SceneDelegate: UIResponder, UIWindowSceneDelegate {
    var window: UIWindow?

    func scene(_ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions) {
        guard let windowScene = scene as? UIWindowScene else { return }

        window = UIWindow(windowScene: windowScene)
        window?.rootViewController = RondoBridgeController()
        window?.makeKeyAndVisible()

        SceneDelegateProxy.shared.scene(scene, willConnectTo: session, options: connectionOptions)
    }

    func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
        SceneDelegateProxy.shared.scene(scene, openURLContexts: URLContexts)
    }

    func scene(_ scene: UIScene, continue userActivity: NSUserActivity) {
        SceneDelegateProxy.shared.scene(scene, continue: userActivity)
    }
}

// One narrowly scoped native store; credentials never enter Preferences, files or iCloud.

@objc(RondoSessionVault)
public class RondoSessionVault: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "RondoSessionVault"
    public let jsName = "RondoSessionVault"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "read", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "write", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "clear", returnType: CAPPluginReturnPromise)
    ]
    private var query: [String: Any] {
        [kSecClass as String: kSecClassGenericPassword,
         kSecAttrService as String: "club.rondo.spike.session",
         kSecAttrAccount as String: "active",
         kSecAttrSynchronizable as String: false]
    }
    public override func load() {
        // Keychain may survive uninstall; an app reinstall must start signed out.
        if !UserDefaults.standard.bool(forKey: "rondoVaultInstalled") {
            let status = SecItemDelete(query as CFDictionary)
            if status == errSecSuccess || status == errSecItemNotFound {
                UserDefaults.standard.set(true, forKey: "rondoVaultInstalled")
            }
        }
    }
    @objc public func read(_ call: CAPPluginCall) {
        guard UserDefaults.standard.bool(forKey: "rondoVaultInstalled") else {
            call.reject("Beveiligde opslag is niet beschikbaar."); return
        }
        var request = query
        request[kSecReturnData as String] = true
        request[kSecMatchLimit as String] = kSecMatchLimitOne
        var result: CFTypeRef?
        let status = SecItemCopyMatching(request as CFDictionary, &result)
        if status == errSecItemNotFound { call.resolve(["value": NSNull()]); return }
        guard status == errSecSuccess, let data = result as? Data,
              let value = String(data: data, encoding: .utf8) else {
            call.reject("Beveiligde opslag is niet beschikbaar."); return
        }
        call.resolve(["value": value])
    }
    @objc public func write(_ call: CAPPluginCall) {
        guard let value = call.getString("value"), let data = value.data(using: .utf8), data.count <= 8192 else {
            call.reject("Ongeldige sessie."); return
        }
        let attributes: [String: Any] = [
            kSecValueData as String: data,
            kSecAttrAccessible as String: kSecAttrAccessibleWhenUnlockedThisDeviceOnly
        ]
        var status = SecItemUpdate(query as CFDictionary, attributes as CFDictionary)
        if status == errSecItemNotFound {
            status = SecItemAdd(query.merging(attributes) { _, new in new } as CFDictionary, nil)
        }
        if status == errSecSuccess { call.resolve() }
        else { call.reject("De sessie kon niet veilig worden opgeslagen.") }
    }
    @objc public func clear(_ call: CAPPluginCall) {
        let status = SecItemDelete(query as CFDictionary)
        if status == errSecSuccess || status == errSecItemNotFound { call.resolve() }
        else { call.reject("De opgeslagen sessie kon niet worden verwijderd.") }
    }
}

class RondoBridgeController: CAPBridgeViewController {
    override func capacitorDidLoad() {
        bridge?.registerPluginInstance(RondoSessionVault())
        bridge?.registerPluginInstance(RondoWallet())
    }
}

// Signed pass bytes stay in memory and are validated by PassKit before presentation.
@objc(RondoWallet)
public class RondoWallet: CAPPlugin, CAPBridgedPlugin, PKAddPassesViewControllerDelegate {
    public let identifier = "RondoWallet"
    public let jsName = "RondoWallet"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "available", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "add", returnType: CAPPluginReturnPromise)
    ]
    private var pending: CAPPluginCall?

    @objc public func available(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            call.resolve(["available": PKAddPassesViewController.canAddPasses()])
        }
    }

    @objc public func add(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            guard self.pending == nil,
                  PKAddPassesViewController.canAddPasses(),
                  let presenter = self.bridge?.viewController,
                  presenter.viewIfLoaded?.window != nil,
                  presenter.presentedViewController == nil else {
                call.reject("Apple Wallet kan nu niet worden geopend."); return
            }
            guard let content = call.getString("content"), content.utf8.count <= 5592408,
                  let data = Data(base64Encoded: content), !data.isEmpty, data.count <= 4 * 1024 * 1024,
                  let pass = try? PKPass(data: data),
                  let controller = PKAddPassesViewController(pass: pass) else {
                call.reject("De club heeft geen geldige Apple Wallet-pas teruggestuurd."); return
            }
            self.pending = call
            controller.delegate = self
            presenter.present(controller, animated: true)
        }
    }

    public func addPassesViewControllerDidFinish(_ controller: PKAddPassesViewController) {
        controller.dismiss(animated: true) {
            // Closing the sheet can mean cancellation; never claim that a pass was saved.
            self.pending?.resolve()
            self.pending = nil
        }
    }
}
