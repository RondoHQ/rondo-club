export const PHONE_LABELS = { mobile_1: 'Mobiel', mobile_2: 'Mobiel 2', telephone_1: 'Telefoon', telephone_2: 'Telefoon 2' };
export const ADDRESS_LABELS = { street_name: 'Straat', house_number: 'Huisnummer', house_number_addition: 'Toevoeging', postal_code: 'Postcode', city: 'Plaats', state: 'Provincie', country: 'Land', country_code: 'Landcode' };

export function phoneValues(fields = {}) {
  return Object.fromEntries(Object.keys(PHONE_LABELS).map((field) => [field, fields[field] || '']));
}

export function homeAddress(fields = {}) {
  const home = fields.addresses?.find((address) => String(address.address_label || '').trim().toLowerCase() === 'home') || {};
  const values = Object.fromEntries(Object.keys(ADDRESS_LABELS).map((field) => [field, home[field] || '']));
  if (!values.country.trim()) values.country = 'Nederland';
  if (!values.country_code.trim() && ['nederland', 'netherlands'].includes(values.country.toLowerCase())) values.country_code = 'NL';
  return values;
}

export function addressLabel(fields) {
  const a = homeAddress(fields);
  return [`${a.street_name} ${a.house_number}${a.house_number_addition}`.trim(), `${a.postal_code} ${a.city}`.trim(), a.street_name && a.country].filter(Boolean).join(', ') || 'Niet ingesteld';
}
