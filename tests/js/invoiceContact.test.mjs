import test from 'node:test';
import assert from 'node:assert/strict';
import { filterInvoiceContacts, invoiceContactPayload, invoiceContactSignature, invoiceCustomerFromContact } from '../../src/utils/invoiceContact.js';

const business = {
  id: 12,
  name: 'Jan de Boer',
  fields: {
    company_name: 'Voorbeeld BV', first_name: 'Jan', infix: 'de', last_name: 'Boer',
    email_1: 'facturen@example.org', email_2: 'administratie@example.org',
    addresses: [
      { address_label: 'Werk', street_name: 'Werkstraat', house_number: '1' },
      { address_label: ' factuur ', street_name: 'Poststraat', house_number: '42', house_number_addition: 'A', postal_code: '1234 AB', city: 'Nijmegen', country: 'Nederland' },
    ],
  },
};

test('external invoice snapshot uses company, attention, both emails and preferred invoice address', () => {
  const before = structuredClone(business);
  const customer = invoiceCustomerFromContact(business);
  assert.deepEqual(customer, {
    customerName: 'Voorbeeld BV', customerAttention: 'Jan de Boer',
    customerEmail: 'facturen@example.org', customerCcEmail: 'administratie@example.org',
    customerAddress: 'Poststraat 42 A\n1234 AB Nijmegen\nNederland',
  });
  customer.customerName = 'Aangepaste factuurnaam';
  assert.deepEqual(business, before);
  assert.equal('person_id' in customer, false);
});

test('person-only contacts and secondary-email-only contacts do not retain a previous recipient', () => {
  assert.deepEqual(invoiceCustomerFromContact({ fields: {
    first_name: 'Eva', last_name: 'Jansen', email_2: 'eva@example.org',
  } }), {
    customerName: 'Eva Jansen', customerAttention: '', customerEmail: 'eva@example.org',
    customerCcEmail: '', customerAddress: '',
  });
});

test('company-only contacts, absent fields and duplicate emails are supported', () => {
  const customer = invoiceCustomerFromContact({ fields: {
    company_name: 'Businessclub', email_1: 'info@example.org', email_2: 'INFO@example.org',
    addresses: [{ street_name: 'Main Street', house_number: '5', postal_code: '10001', city: 'New York', state: 'NY', country: 'United States' }],
  } });
  assert.equal(customer.customerName, 'Businessclub');
  assert.equal(customer.customerAttention, '');
  assert.equal(customer.customerCcEmail, '');
  assert.equal(customer.customerAddress, 'Main Street 5\n10001 New York\nNY\nUnited States');
  assert.deepEqual(invoiceCustomerFromContact({ name: 'Alleen zichtbare naam' }), {
    customerName: 'Alleen zichtbare naam', customerAttention: '', customerEmail: '',
    customerCcEmail: '', customerAddress: '',
  });
});

test('search matches company, contact person, secondary email and contacts beyond the first 20', () => {
  const contacts = [...Array.from({ length: 25 }, (_, id) => ({ id, name: `Ander ${id}` })), business];
  for (const query of ['VOORBEELD', 'Jan Boer', 'administratie@example.org', 'Nijmegen']) {
    assert.deepEqual(filterInvoiceContacts(contacts, query), [business]);
  }
  assert.equal(filterInvoiceContacts(contacts, '').length, 26);
  assert.deepEqual(filterInvoiceContacts(contacts, 'onbekend'), []);
});

test('saved invoice contacts round-trip multiline addresses, attention and both recipients without guessing name or address parts', () => {
  const customer = {
    customerName: 'AWC Businessclub', customerAttention: 'Bestuur, t.a.v. Jan van der Meer',
    customerEmail: 'facturen@example.org', customerCcEmail: 'bestuur@example.org',
    customerAddress: 'Postbus 42\n6600 AA Wijchen\nNederland',
  };
  const before = structuredClone(customer);
  const payload = invoiceContactPayload(customer);
  assert.equal(payload.status, 'publish');
  assert.equal(payload.fields.person_type, 'contact');
  assert.equal(payload.fields.company_name, 'AWC Businessclub');
  assert.equal('person_id' in payload, false);
  assert.equal('first_name' in payload.fields, false);
  assert.deepEqual(invoiceCustomerFromContact(payload), customer);
  assert.deepEqual(customer, before);
  assert.equal(filterInvoiceContacts([payload], 'Meer Wijchen').length, 1);
});

test('saved invoice addresses take precedence over postal addresses while normal contacts retain their existing mapping', () => {
  const saved = structuredClone(business);
  saved.fields.invoice_address = 'PO Box 123\nLondon SW1A 1AA\nUnited Kingdom';
  saved.fields.invoice_attention = 'Finance department';
  const customer = invoiceCustomerFromContact(saved);
  assert.equal(customer.customerAddress, saved.fields.invoice_address);
  assert.equal(customer.customerAttention, 'Finance department');
  assert.deepEqual(invoiceCustomerFromContact(invoiceContactPayload(customer)), customer);
});

test('identical invoice contacts have a stable signature after trimming and newline normalization', () => {
  const customer = {
    customerName: '  Club  ', customerAddress: 'Postbus 42\r\nWijchen  ',
    customerEmail: 'info@example.org', customerCcEmail: 'INFO@example.org',
  };
  const saved = invoiceCustomerFromContact(invoiceContactPayload(customer));
  assert.equal(invoiceContactSignature(customer), invoiceContactSignature(saved));
  assert.notEqual(invoiceContactSignature(customer), invoiceContactSignature({ ...customer, customerAddress: 'Ander adres' }));
});

test('saved invoice contacts preserve an empty primary recipient and a CC recipient in their original slots', () => {
  const customer = {
    customerName: 'AWC Businessclub', customerAttention: '', customerAddress: 'Postbus 42',
    customerEmail: '', customerCcEmail: 'bestuur@example.org',
  };
  assert.deepEqual(invoiceCustomerFromContact(invoiceContactPayload(customer)), customer);
});
