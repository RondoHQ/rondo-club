import { Capacitor, registerPlugin } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { PILOT } from './deployment.mjs';

const nativeAuth = registerPlugin('RondoAuthSession');
export async function openLoginBrowser(url) {
  if (PILOT && Capacitor.getPlatform() === 'ios') return (await nativeAuth.open({ url })).callbackUrl;
  await Browser.open({ url });
  return null;
}
export async function closeLoginBrowser() {
  if (Capacitor.getPlatform() !== 'ios') return;
  if (PILOT) await nativeAuth.close();
  else await Browser.close();
}
