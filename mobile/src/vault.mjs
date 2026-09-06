import { registerPlugin } from '@capacitor/core';

// Deliberately no web/localStorage fallback for credentials.
export const vault = registerPlugin('RondoSessionVault');
