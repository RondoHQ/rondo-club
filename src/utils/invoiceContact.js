const clean = (value) => String(value ?? '').trim();
const join = (values, separator = ' ') => values.map(clean).filter(Boolean).join(separator);

// Copy contact data into an invoice snapshot; never link an external invoice
// to a member or change the source contact when invoice fields are edited.
export function invoiceCustomerFromContact(contact) {
  const fields = contact?.fields || {};
  const company = clean(fields.company_name);
  const name = join([fields.first_name, fields.infix, fields.last_name]);
  const emails = [...new Map([fields.email_1, fields.email_2]
    .map(clean).filter(Boolean).map((email) => [email.toLowerCase(), email])).values()];
  const addresses = Array.isArray(fields.addresses) ? fields.addresses.filter(Boolean) : [];
  const address = addresses.find((item) => clean(item.address_label).toLowerCase() === 'factuur')
    || addresses[0] || {};

  return {
    customerName: company || name || clean(contact?.name),
    customerAttention: company ? name : '',
    customerEmail: emails[0] || '',
    customerCcEmail: emails[1] || '',
    customerAddress: join([
      join([address.street_name, address.house_number, address.house_number_addition]),
      join([address.postal_code, address.city]),
      address.state,
      address.country,
    ], '\n'),
  };
}

export function filterInvoiceContacts(contacts, search) {
  const terms = clean(search).toLocaleLowerCase('nl').split(/\s+/).filter(Boolean);
  return contacts.filter((contact) => {
    const customer = invoiceCustomerFromContact(contact);
    const text = join([contact.name, ...Object.values(customer)]).toLocaleLowerCase('nl');
    return terms.every((term) => text.includes(term));
  });
}
