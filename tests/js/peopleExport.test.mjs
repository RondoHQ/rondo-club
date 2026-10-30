import test from 'node:test';
import assert from 'node:assert/strict';
import { buildPeopleExportRows } from '../../src/pages/People/peopleExport.js';
import { fetchAllPeoplePages } from '../../src/api/fetchAllPeoplePages.js';
import { buildCsv } from '../../src/utils/csvExport.js';

const person = {
  id: 1, first_name: 'Zoë', infix: 'van', last_name: 'Dijk', team_id: 12, modified: '2026-10-30T12:30:00',
  fields: { email_1: 'hidden@example.test', birthdate: '2000-01-02', knvb_id: 'ABCD123', active: false, count: 0,
    addresses: [{ street_name: 'Sportlaan', house_number: 12, house_number_addition: 'A', postal_code: '1234 AB', city: 'Wijchen', country: 'Nederland' }],
  },
};
const column = (id, label = id, type = 'core') => ({ id, label, type });

test('exports exactly the visible columns in their selected order, including address parts', () => {
  const columns = [column('city', 'Plaats'), column('last_name', 'Achternaam'), column('address', 'Adres'), column('first_name', 'Voornaam')];
  assert.deepEqual(buildPeopleExportRows([person], columns), [
    ['Plaats', 'Achternaam', 'Adres', 'Voornaam'], ['Wijchen', 'van Dijk', 'Sportlaan 12A', 'Zoë'],
  ]);
  assert.ok(!buildCsv(buildPeopleExportRows([person], columns)).includes('hidden@example.test'));
  assert.deepEqual(buildPeopleExportRows([person], [column('first_name')]), [['first_name'], ['Zoë']]);
});

test('exports canonical custom fields, dates, false and zero without dropping values', () => {
  const columns = [column('knvb_id'), column('birthdate'), column('modified'), column('active', 'Actief', 'true_false'), column('count', 'Aantal', 'number'), column('missing')];
  assert.deepEqual(buildPeopleExportRows([person], columns)[1], ['ABCD123', '2000-01-02', '2026-10-30', 'Nee', 0, '']);
  assert.equal(buildPeopleExportRows([person], [column('alias')], { alias: { canonical_name: 'knvb_id', type: 'text' } })[1][0], 'ABCD123');
});

test('formats team names, characteristics, membership labels and selected shift columns only', () => {
  const row = { ...person, characteristics: { playing_member: true, knvb_known: true, parent: true, volunteer: true },
    shift_progress: { status: 'planned', planned: 2, required: 3, family: true } };
  const columns = [column('team'), column('characteristics'), column('type_lid'), column('shift_status'), column('shift_planned')];
  assert.deepEqual(buildPeopleExportRows([row], columns, {}, { 12: 'JO19-1' })[1], [
    'JO19-1', 'Spelend lid, KNVB bekend, Ouder/verzorger, Vrijwilliger', 'Bondslid', 'Voldoende ingepland (Inclusief gezin)', 2,
  ]);
  assert.equal(buildPeopleExportRows([row], [column('first_name')])[0].length, 1);
});

test('serializes multi-values and structured custom fields without leaking object internals', () => {
  const row = { fields: { choices: ['A', 'B'], relation: [{ ID: 5, post_title: 'Club', secret: 'private' }, 9], image: { url: 'https://example.test/image.png' }, link: { title: 'Example', url: 'https://example.test/' } } };
  const columns = [column('choices', 'Keuzes', 'checkbox'), column('relation', 'Relatie', 'relationship'), column('image', 'Foto', 'image'), column('link', 'Link', 'link')];
  assert.deepEqual(buildPeopleExportRows([row], columns)[1], ['A, B', 'Club, #9', 'https://example.test/image.png', 'https://example.test/']);
});

test('keeps CSV quoting and blocks spreadsheet formulas in newly selectable text fields', () => {
  const row = { ...person, first_name: '=1+1', fields: { note: 'Hallo; "Zoë"\nTweede regel', unsafe: '\t@SUM(1)', count: -2 } };
  const columns = [column('first_name'), column('note'), column('unsafe'), column('count', 'Aantal', 'number')];
  const rows = buildPeopleExportRows([row], columns);
  assert.equal(rows[1][0], "'=1+1"); assert.equal(rows[1][2], "'\t@SUM(1)"); assert.equal(rows[1][3], -2);
  assert.match(buildCsv(rows), /"Hallo; ""Zoë""\nTweede regel"/);
});

test('export includes every filtered page beyond 100 in order and forwards filters', async () => {
  const calls = [];
  const people = await fetchAllPeoplePages(async params => {
    calls.push(params);
    return { data: { total_pages: 3, people: Array.from({ length: params.page === 3 ? 5 : 100 }, (_, i) => ({ ...person, first_name: `Lid ${(params.page - 1) * 100 + i + 1}` })) } };
  }, { page: 9, per_page: 20, type_lid: 'Bondslid', order: 'asc' });
  assert.equal(people.length, 205);
  assert.deepEqual(calls.map(call => call.page), [1, 2, 3]);
  assert.ok(calls.every(call => call.per_page === 100 && call.type_lid === 'Bondslid' && call.order === 'asc'));
  const rows = buildPeopleExportRows(people, [column('first_name', 'Voornaam')]);
  assert.equal(rows.length, 206); assert.equal(rows[205][0], 'Lid 205');
});

test('a failed subsequent page rejects the complete export', async () => {
  await assert.rejects(fetchAllPeoplePages(async ({ page }) => {
    if (page === 2) throw new Error('Unavailable');
    return { data: { total_pages: 2, people: [person] } };
  }), /Unavailable/);
});
