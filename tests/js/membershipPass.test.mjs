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
  assert.deepEqual(getMembershipPassPresentation('bondslid'), {
    eyebrow: 'Bondslid',
    title: 'AWC Ledenpas',
    sponsor: false,
    businessclub: false,
  });
  assert.equal(getMembershipPassPresentation('businessclub').title, 'Businessclub AWC');
  assert.equal(getMembershipPassPresentation('businessclub').businessclub, true);
  assert.equal(getMembershipPassPresentation('awc_sponsor').title, 'AWC Sponsor');
});
