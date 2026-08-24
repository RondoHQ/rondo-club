export const FEATURE_TOGGLE_STATES = {
  ON: 'on',
  OFF: 'off',
  ADMIN_ONLY: 'admin_only',
};

export function getFeatureToggleState(feature) {
  return window.rondoConfig?.featureToggles?.[feature] ?? FEATURE_TOGGLE_STATES.OFF;
}

export function canAccessFeature(feature, isAdmin = false) {
  const state = getFeatureToggleState(feature);
  return state === FEATURE_TOGGLE_STATES.ON
    || (state === FEATURE_TOGGLE_STATES.ADMIN_ONLY && isAdmin);
}
