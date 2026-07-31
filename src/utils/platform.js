/**
 * Platform detection for install affordances.
 *
 * Installing a web app works differently on every platform and none of it is
 * feature-detectable: Safari has no beforeinstallprompt event and no API to ask
 * "can this be installed", so the only way to help a member is to recognise
 * their browser and describe the right menu.
 */

/**
 * True on iPhone/iPad, including iPadOS 13+ which reports itself as a Mac.
 *
 * @returns {boolean}
 */
export function isIOS() {
  if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
    return true;
  }

  // iPadOS 13+ sends a desktop Safari user agent; touch points give it away.
  return navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
}

/**
 * True on iOS Safari specifically. Chrome, Firefox and Edge on iOS all render
 * with WebKit but only Safari can add to the home screen.
 *
 * @returns {boolean}
 */
export function isIOSSafari() {
  return isIOS() && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(navigator.userAgent);
}

/**
 * True when the app is already running as an installed app.
 *
 * @returns {boolean}
 */
export function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: minimal-ui)').matches
    || window.navigator.standalone === true;
}

/**
 * Which install instructions apply to this browser.
 *
 * @returns {'ios-safari'|'ios-other'|'android'|'desktop'} Platform key.
 */
export function getInstallPlatform() {
  if (isIOSSafari()) {
    return 'ios-safari';
  }

  if (isIOS()) {
    return 'ios-other';
  }

  if (/Android/.test(navigator.userAgent)) {
    return 'android';
  }

  return 'desktop';
}
