import test from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { QueryClient } from '@tanstack/react-query';
import { getInvalidResponseDiagnostics, readDashboardResponse, retryDashboardRequest } from '../../src/api/dashboard-response.js';
import { sanitizeErrorEvent } from '../../src/utils/error-event.js';

const workspace = () => ({
  layout: { order: ['birthdays'], hidden: [], birthday_days: 3 },
  context: { board: true }, teams: [], birthdays: [], training: [],
  today: '2026-10-28', end_date: '2026-11-03',
});
const response = data => ({ data, status: 200, headers: { 'content-type': 'application/json' } });

test('valid empty lists, populated counters and the default birthday period retain their data', () => {
  for (const [kind, data] of [
    ['workspace', workspace()], ['invoices', []], ['invoices', [{ status: 'sent' }]],
    ['fees', { members: [] }], ['fees', { members: [{ final_fee: '12.50' }] }],
  ]) assert.equal(readDashboardResponse(response(data), kind), data);
  const data = workspace();
  delete data.layout.birthday_days;
  assert.equal(readDashboardResponse(response(data), 'workspace'), data);
});

test('invalid success bodies and missing nested collections are rejected before rendering', () => {
  for (const kind of ['workspace', 'invoices', 'fees']) {
    for (const body of ['<!doctype html>PRIVATE', '{"broken":', null, undefined, {}, { success: false }, 42]) {
      assert.throws(() => readDashboardResponse(response(body), kind), /Invalid dashboard API response/);
    }
  }
  for (const data of [
    { ...workspace(), layout: null }, { ...workspace(), layout: { order: [], hidden: null } },
    { ...workspace(), layout: { order: [], hidden: [], birthday_days: 'PRIVATE' } },
    { ...workspace(), birthdays: [null] }, { ...workspace(), birthdays: [{ team_ids: null }] },
    { ...workspace(), training: [{ start: null }] }, { ...workspace(), teams: {} },
  ]) assert.throws(() => readDashboardResponse(response(data), 'workspace'));
  assert.throws(() => readDashboardResponse(response([null]), 'invoices'));
  assert.throws(() => readDashboardResponse(response([{}]), 'invoices'));
  assert.throws(() => readDashboardResponse(response({ members: null }), 'fees'));
  assert.throws(() => readDashboardResponse(response({ members: [null] }), 'fees'));
});

test('real Axios/QueryClient reject HTML once, retain no bad data, and recover on explicit retry', async () => {
  for (const [kind, validData] of [['workspace', workspace()], ['invoices', []], ['fees', { members: [] }]]) {
    let requests = 0;
    const reports = [];
    const api = axios.create({ adapter: async config => ({
      config, status: 200, statusText: 'OK',
      headers: { 'content-type': requests === 0 ? 'text/html' : 'application/json' },
      data: requests++ === 0 ? '<html>PRIVATE</html>' : JSON.stringify(validData),
    }) });
    const queryClient = new QueryClient();
    const options = {
      queryKey: [kind],
      retry: retryDashboardRequest,
      queryFn: async () => readDashboardResponse(await api.get('/local-fixture'), kind, error => reports.push(error)),
    };
    try {
      await assert.rejects(queryClient.fetchQuery(options));
      assert.equal(requests, 1);
      assert.equal(reports.length, 1);
      assert.equal(queryClient.getQueryState([kind]).status, 'error');
      assert.equal(queryClient.getQueryData([kind]), undefined);
      assert.deepEqual(await queryClient.fetchQuery(options), validData);
      assert.equal(queryClient.getQueryState([kind]).status, 'success');
    } finally { queryClient.clear(); }
  }
});

test('failed refresh keeps the last valid value without storing the invalid response', async () => {
  const client = new QueryClient();
  const data = [{ status: 'sent' }];
  client.setQueryData(['invoices'], data);
  try {
    await assert.rejects(client.fetchQuery({
      queryKey: ['invoices'], retry: retryDashboardRequest,
      queryFn: async () => readDashboardResponse(response('<html>PRIVATE</html>'), 'invoices'),
    }));
    assert.deepEqual(client.getQueryData(['invoices']), data);
    assert.equal(client.getQueryState(['invoices']).status, 'error');
  } finally { client.clear(); }
});

test('diagnostics contain only bounded categories and cannot leak payloads or forged properties', () => {
  const reports = [];
  assert.throws(() => readDashboardResponse({
    data: '<html>PRIVATE MEMBER SECRET</html>', status: 200,
    headers: { 'content-type': 'text/html; private=SECRET', authorization: 'SECRET' },
    config: { url: '/people/123?token=SECRET' },
  }, 'workspace', error => reports.push(error)));
  const error = reports[0];
  assert.deepEqual(getInvalidResponseDiagnostics(error), {
    api_contract: 'workspace', response_shape: 'string', response_content_type: 'html', response_status: '200',
  });
  assert.equal(error.response, undefined);
  assert.equal(error.config, undefined);
  const clean = sanitizeErrorEvent({ exception: { values: [{ type: 'Error', value: error.message }] } }, { originalException: error });
  assert.equal(clean.tags.error_code, 'invalid_api_response');
  assert.equal(clean.tags.api_contract, 'workspace');
  assert.doesNotMatch(JSON.stringify(clean), /PRIVATE|SECRET|123/);
  for (const forged of [null, 'PRIVATE', { api_contract: 'workspace', response_shape: 'PRIVATE' }]) {
    assert.equal(getInvalidResponseDiagnostics(forged), undefined);
    assert.equal(sanitizeErrorEvent({}, { originalException: forged }).tags.api_contract, undefined);
  }
  assert.equal(retryDashboardRequest(0, error), false);
  assert.equal(retryDashboardRequest(2, new Error('Network error')), true);
  assert.equal(retryDashboardRequest(3, new Error('Network error')), false);
});
