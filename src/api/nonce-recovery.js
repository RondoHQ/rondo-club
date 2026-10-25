// A second login changes the browser's shared cookie, while existing tabs keep
// the REST nonce from their original session. Only WordPress's nonce rejection
// is safe to retry: it happens before the endpoint performs any work.
export function installNonceRecovery(api, config, { fetchSession = async () => {
  const response = await fetch(config.sessionRefreshUrl, {
    credentials: 'same-origin',
    cache: 'no-store',
  });
  if (!response.ok) throw new Error('Session refresh failed');
  const result = await response.json();
  if (!result.success) throw new Error('Session refresh failed');
  return result.data;
}, reload = () => window.location.reload() } = {}) {
  let refreshing;

  api.interceptors.response.use(response => response, async error => {
    const request = error.config;
    if (error.response?.status !== 403
      || error.response?.data?.code !== 'rest_cookie_invalid_nonce'
      || !request || request._rondoNonceRetried || !config.sessionRefreshUrl) {
      throw error;
    }

    request._rondoNonceRetried = true;
    // A concurrent request may already have refreshed this tab's nonce.
    if (request.headers['X-WP-Nonce'] === config.nonce) {
      if (!refreshing) {
        refreshing = fetchSession().then(session => {
          // Never replay an old account's request under a newly logged-in user.
          if (Number(session.userId) !== Number(config.userId)) {
            reload();
            throw new Error('Session user changed');
          }
          if (!session.nonce) throw new Error('Missing REST nonce');
          config.nonce = session.nonce;
        }).finally(() => { refreshing = undefined; });
      }
      try {
        await refreshing;
      } catch {
        throw error;
      }
    }
    request.headers['X-WP-Nonce'] = config.nonce;
    return api.request(request);
  });
}
