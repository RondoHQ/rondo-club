import assert from 'node:assert/strict';
import test from 'node:test';

import {
  buildDigitalPassPath,
  getMembershipPassPresentation,
} from '../../src/pages/Household/membershipPassUtils.js';

test('builds a personal digital pass route with an encoded optional choice', () => {
  assert.equal(buildDigitalPassPath(123), '/mijn-gegevens/pas/123');
  assert.equal(
    buildDigitalPassPath(123, 'AWC 1 / Trainer'),
    '/mijn-gegevens/pas/123?role=AWC+1+%2F+Trainer',
  );
});

test('selects the correct presentation for every scanner pass type', () => {
  globalThis.window = { rondoConfig: { clubName: 'AWC' } };
  assert.deepEqual(getMembershipPassPresentation('bondslid'), {
    eyebrow: 'Bondslid',
    title: 'AWC Ledenpas',
    sponsor: false,
    businessclub: false,
  });
  assert.equal(getMembershipPassPresentation('businessclub').title, 'Businessclub AWC');
  assert.equal(getMembershipPassPresentation('businessclub').businessclub, true);
  assert.equal(getMembershipPassPresentation('awc_sponsor').title, 'Sponsor AWC');
});


test('uses current club branding for all pass types without changing their stored keys', () => {
  for (const clubName of ['SV Voorbeeld', 'FC Andere Club']) {
    globalThis.window = { rondoConfig: { clubName } };
    assert.equal(getMembershipPassPresentation('awc_sponsor').title, `Sponsor ${clubName}`);
    assert.equal(getMembershipPassPresentation('businessclub').title, `Businessclub ${clubName}`);
    for (const type of ['bondslid', 'verenigingslid', 'unknown']) {
      assert.equal(getMembershipPassPresentation(type).title, `${clubName} Ledenpas`);
    }
  }
  globalThis.window.rondoConfig.clubName = '  ';
  assert.equal(getMembershipPassPresentation('awc_sponsor').title, 'Sponsor');
  assert.equal(getMembershipPassPresentation('businessclub').title, 'Businessclub');
  assert.equal(getMembershipPassPresentation('unknown').title, 'Ledenpas');
  delete globalThis.window;
});
