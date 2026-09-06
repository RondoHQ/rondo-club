import AuthenticationServices

// Only compiled into the separate pilot. Requires the real webcredentials domain association.
@objc(RondoAuthSession)
public class RondoAuthSession: CAPPlugin, CAPBridgedPlugin, ASWebAuthenticationPresentationContextProviding {
    public let identifier = "RondoAuthSession"
    public let jsName = "RondoAuthSession"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "open", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "close", returnType: CAPPluginReturnPromise)
    ]
    private var authentication: ASWebAuthenticationSession?
    private var pendingCall: CAPPluginCall?
    private var anchor: UIWindow?

    public func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        return anchor ?? ASPresentationAnchor()
    }

    @objc public func open(_ call: CAPPluginCall) {
        guard let value = call.getString("url"), value.utf8.count <= 8192,
              let parts = URLComponents(string: value), let url = parts.url,
              parts.scheme == "https", parts.host == "rondo.svawc.nl", parts.port == nil,
              parts.user == nil, parts.password == nil, parts.fragment == nil,
              parts.path == "/wp-admin/admin-post.php",
              parts.queryItems?.filter({ $0.name == "action" }).map({ $0.value }) == ["rondo_mobile_pilot_authorize"] else {
            call.reject("Ongeldige clubaanmelding."); return
        }
        DispatchQueue.main.async {
            guard self.pendingCall == nil, let window = self.bridge?.viewController?.view.window else {
                call.reject("Er is al een aanmelding bezig."); return
            }
            guard #available(iOS 17.4, *) else {
                call.reject("Deze pilot vereist iOS 17.4 of nieuwer."); return
            }
            self.anchor = window
            self.pendingCall = call
            let session = ASWebAuthenticationSession(url: url, callback: .https(host: "rondo.svawc.nl", path: "/rondo-app/callback")) { [weak self] callback, error in
                DispatchQueue.main.async {
                    guard let self = self, let pending = self.pendingCall, pending === call else { return }
                    self.pendingCall = nil; self.authentication = nil; self.anchor = nil
                    if let callback = callback { pending.resolve(["callbackUrl": callback.absoluteString]) }
                    else { pending.reject(error is ASWebAuthenticationSessionError && (error as? ASWebAuthenticationSessionError)?.code == .canceledLogin ? "Aanmelding geannuleerd." : "De aanmelding kon niet worden afgerond.") }
                }
            }
            session.presentationContextProvider = self
            session.prefersEphemeralWebBrowserSession = false
            self.authentication = session
            if !session.start() {
                self.pendingCall = nil; self.authentication = nil; self.anchor = nil
                call.reject("Het aanmeldvenster kon niet worden geopend.")
            }
        }
    }

    // A Mail universal link may complete the login outside the still-open auth session.
    @objc public func close(_ call: CAPPluginCall) {
        DispatchQueue.main.async {
            let pending = self.pendingCall
            self.pendingCall = nil
            self.authentication?.cancel(); self.authentication = nil; self.anchor = nil
            pending?.resolve(["callbackUrl": NSNull()])
            call.resolve()
        }
    }
}
