// A pilot build has reviewed origins and a separate protocol/storage identity.
export const PILOT = import.meta.env?.MODE === 'pilot';
export const PROTOCOL = PILOT ? 'rondo-mobile-pilot-v1' : 'rondo-mobile-spike-v1';
export const AUTHORIZE_ACTION = PILOT ? 'rondo_mobile_pilot_authorize' : 'rondo_mobile_spike_authorize';
export const PILOT_CLUBS = [{ id: 'awc', name: 'AWC', url: 'https://rondo.svawc.nl', logoUrl: 'https://www.svawc.nl/wp-content/uploads/2024/02/awc-logo.svg' }, { id: 'demo', name: 'Rondo Demo', url: 'https://demo.rondo.club' }];
