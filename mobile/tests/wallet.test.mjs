import test from 'node:test';
import assert from 'node:assert/strict';
import { openWallet, walletPayload, walletProvider } from '../src/wallet-model.mjs';

test('Wallet matches the native platform and only permits the exact signed Google save URL', () => {
  assert.equal(walletProvider('ios'), 'apple');
  assert.equal(walletProvider('android'), 'google');
  assert.equal(walletProvider('web'), null);
  const url = 'https://pay.google.com/gp/v/save/abc.def.ghi';
  assert.equal(walletPayload({ provider: 'google', url }, 'google'), url);
  for (const invalid of [url + '?token=secret', url + '#x', url + '\n', url.replace('https:', 'http:'), url.replace('pay.google.com', 'pay.google.com.evil.test'), url.replace('pay.google.com', 'user@pay.google.com'), 'javascript:alert(1)', 'https://club.example.test/pass', 'https://pay.google.com/gp/v/save/unsigned']) {
    assert.throws(() => walletPayload({ provider: 'google', url: invalid }, 'google'));
  }
  assert.throws(() => walletPayload({ provider: 'apple', url }, 'google'));
});

test('Apple pass transport is bounded base64; native PassKit owns signature validation', () => {
  assert.equal(walletPayload({ provider: 'apple', content: 'YWJj' }, 'apple'), 'YWJj');
  for (const content of ['', {}, 'x', '<html>', 'YWJj\n', 'x'.repeat(5592412)]) {
    assert.throws(() => walletPayload({ provider: 'apple', content }, 'apple'));
  }
});

test('a result received after leaving the pass screen never opens Wallet', async () => {
  let active = true;
  const result = await openWallet({
    provider: 'apple', active: () => active,
    request: async () => { active = false; return { provider: 'apple', content: 'YWJj' }; },
    apple: () => assert.fail('Late handoff'), google: () => assert.fail('Wrong provider'),
  });
  assert.equal(result, false);
});

test('handoff opens only the selected provider and does not retry failures', async () => {
  let attempts = 0;
  const url = 'https://pay.google.com/gp/v/save/abc.def.ghi';
  await assert.rejects(openWallet({
    provider: 'google', active: () => true,
    request: async () => ({ provider: 'google', url }),
    apple: () => assert.fail('Wrong provider'),
    google: (received) => { assert.equal(received, url); attempts++; throw new Error('Unavailable'); },
  }), /Unavailable/);
  assert.equal(attempts, 1);
});
