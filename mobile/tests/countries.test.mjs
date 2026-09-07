import test from 'node:test';
import assert from 'node:assert/strict';
import { countryForAddress, withCountry, COUNTRY_OPTIONS } from '../src/countries.mjs';
import { homeAddress, ADDRESS_LABELS } from '../src/profile-model.mjs';

test('legacy country names and ISO codes resolve to one Dutch selection', () => {
  assert.deepEqual(countryForAddress('Belgium', ''), { country: 'België', country_code: 'BE' });
  assert.deepEqual(countryForAddress('Germany', 'DEU'), { country: 'Duitsland', country_code: 'DE' });
  assert.deepEqual(countryForAddress('', ''), { country: 'Nederland', country_code: 'NL' });
  assert.equal(countryForAddress('Unknown country', 'ZZ').country_code, '');
});

test('country changes keep the code and name together and preserve unedited province data', () => {
  const address = homeAddress({ addresses: [{ address_label: 'Home', country: 'Nederland', country_code: 'NL', state: 'Gelderland', city: 'Wijchen' }] });
  const changed = withCountry(address, 'BE');
  assert.equal(changed.country, 'België');
  assert.equal(changed.country_code, 'BE');
  assert.equal(changed.state, 'Gelderland');
  assert.equal(changed.city, 'Wijchen');
  assert.equal(ADDRESS_LABELS.state, undefined);
  assert.equal(ADDRESS_LABELS.country_code, undefined);
  assert.throws(() => withCountry(address, 'ZZ'));
  assert.ok(COUNTRY_OPTIONS.length >= 249);
  assert.equal(new Set(COUNTRY_OPTIONS.map(({ code }) => code)).size, COUNTRY_OPTIONS.length);
});
