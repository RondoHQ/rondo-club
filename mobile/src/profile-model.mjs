import { countryForAddress } from './countries.mjs';

export const PHONE_LABELS = { mobile_1: 'Mobiel', mobile_2: 'Mobiel 2', telephone_1: 'Telefoon', telephone_2: 'Telefoon 2' };
export const ADDRESS_LABELS = { street_name: 'Straat', house_number: 'Huisnummer', house_number_addition: 'Toevoeging', postal_code: 'Postcode', city: 'Plaats' };

export function phoneValues(fields = {}) {
  return Object.fromEntries(Object.keys(PHONE_LABELS).map((field) => [field, fields[field] || '']));
}

export function homeAddress(fields = {}) {
  const home = fields.addresses?.find((address) => String(address.address_label || '').trim().toLowerCase() === 'home') || {};
  // Preserve existing province data for the complete address payload without showing an editor.
  const values = { state: home.state || '', ...Object.fromEntries(Object.keys(ADDRESS_LABELS).map((field) => [field, home[field] || ''])) };
  return { ...values, ...countryForAddress(home.country || '', home.country_code || '') };
}

export function addressLabel(fields) {
  const a = homeAddress(fields);
  return [`${a.street_name} ${a.house_number}${a.house_number_addition}`.trim(), `${a.postal_code} ${a.city}`.trim(), a.street_name && a.country].filter(Boolean).join(', ') || 'Niet ingesteld';
}
