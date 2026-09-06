export const walletProvider = (platform) => ({ ios: 'apple', android: 'google' })[platform] || null;

// Only the signed Google save endpoint may leave the app; never a server-supplied redirect.
export function walletPayload(data, provider) {
  if (data?.provider !== provider) throw new Error('De club heeft een ongeldige Wallet-pas teruggestuurd.');
  if (provider === 'google' && typeof data.url === 'string' && data.url.length <= 65536 && /^https:\/\/pay\.google\.com\/gp\/v\/save\/[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+(?![\s\S])/.test(data.url)) return data.url;
  if (provider === 'apple' && typeof data.content === 'string' && data.content.length > 0 && data.content.length <= 5592408 && data.content.length % 4 === 0 && /^[A-Za-z0-9+/]*={0,2}(?![\s\S])/.test(data.content)) return data.content;
  throw new Error('De club heeft een ongeldige Wallet-pas teruggestuurd.');
}

export async function openWallet({ request, provider, active, apple, google }) {
  const payload = walletPayload(await request(), provider);
  if (!active()) return false;
  if (provider === 'apple') await apple(payload);
  else await google(payload);
  return active();
}
