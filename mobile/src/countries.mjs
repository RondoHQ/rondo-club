import countries from 'i18n-iso-countries/index.js';
import nl from 'i18n-iso-countries/langs/nl.json' with { type: 'json' };
import en from 'i18n-iso-countries/langs/en.json' with { type: 'json' };

// Only the two locales needed for display and existing Dutch/English records are bundled.
countries.registerLocale(nl);
countries.registerLocale(en);
const names = countries.getNames('nl', { select: 'alias' });
const common = ['NL', 'BE', 'DE'];
export const COUNTRY_OPTIONS = Object.entries(names).map(([code, name]) => ({ code, name })).sort((a, b) => {
  const priority = (code) => common.includes(code) ? common.indexOf(code) : common.length;
  return priority(a.code) - priority(b.code) || a.name.localeCompare(b.name, 'nl');
});

export function countryForAddress(country = '', countryCode = '') {
  const name = country.trim();
  const storedCode = countryCode.trim().toUpperCase();
  const code = (names[storedCode] && storedCode) || countries.alpha3ToAlpha2(storedCode) || countries.getAlpha2Code(name, 'nl') || countries.getAlpha2Code(name, 'en') || (!name && !storedCode ? 'NL' : '');
  // Unknown legacy values require a selection; never silently turn them into the Netherlands.
  return { country: names[code] || name, country_code: names[code] ? code : '' };
}

export function withCountry(address, code) {
  if (!names[code]) throw new Error('Kies een land uit de lijst.');
  return { ...address, country: names[code], country_code: code };
}
