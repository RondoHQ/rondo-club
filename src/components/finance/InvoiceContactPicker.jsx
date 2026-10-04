import { useId, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BookUser } from 'lucide-react';
import { fetchAllFilteredPeople, peopleKeys } from '@/hooks/usePeople';
import { filterInvoiceContacts, invoiceCustomerFromContact } from '@/utils/invoiceContact';

export default function InvoiceContactPicker({ onSelect }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedName, setSelectedName] = useState('');
  const panelId = useId();
  const buttonRef = useRef(null);
  const { data: contacts = [], isPending, isError, refetch } = useQuery({
    queryKey: [...peopleKeys.lists(), 'invoice-contacts'],
    queryFn: () => fetchAllFilteredPeople({ personType: 'contact' }),
    enabled: open,
    refetchOnMount: 'always',
  });
  const matches = filterInvoiceContacts(contacts, search);

  const close = () => {
    setOpen(false);
    buttonRef.current?.focus();
  };

  return (
    <div className="md:col-span-2 space-y-3">
      <button
        ref={buttonRef}
        type="button"
        className="btn-secondary gap-2"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => { setOpen(!open); setSearch(''); }}
      >
        <BookUser className="w-4 h-4" aria-hidden="true" />
        {open ? 'Adresboek sluiten' : 'Kies uit adresboek'}
      </button>
      {open && (
        <div id={panelId} className="space-y-3" onKeyDown={(event) => {
          if (event.key === 'Escape') { event.stopPropagation(); close(); }
        }}>
          <p className="text-sm text-gray-600 dark:text-gray-300">
            Kies een extern contact om de klantgegevens hieronder te vervangen. Je kunt ze daarna aanpassen.
          </p>
          <label className="block text-sm">
            Zoek in adresboek
            <input
              autoFocus
              type="search"
              className="input mt-1"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              onKeyDown={(event) => { if (event.key === 'Enter') event.preventDefault(); }}
              placeholder="Naam, bedrijf of e-mailadres"
            />
          </label>
          {isPending ? (
            <p role="status" className="text-sm text-gray-600 dark:text-gray-300">Adresboek laden…</p>
          ) : isError ? (
            <div role="alert" className="space-y-2">
              <p className="text-sm text-red-700 dark:text-red-300">Het adresboek kon niet worden geladen. Probeer opnieuw of vul de klantgegevens zelf in.</p>
              <button type="button" className="btn-secondary" onClick={() => refetch()}>Opnieuw proberen</button>
            </div>
          ) : matches.length === 0 ? (
            <p role="status" className="text-sm text-gray-600 dark:text-gray-300">
              {contacts.length ? 'Geen contacten gevonden. Pas je zoekopdracht aan.' : 'Er zijn geen externe contacten beschikbaar in het adresboek.'}
            </p>
          ) : (
            <>
              <ul aria-label="Externe contacten" className="max-h-64 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700">
                {matches.slice(0, 20).map((contact) => {
                  const customer = invoiceCustomerFromContact(contact);
                  return (
                    <li key={contact.id}>
                      <button type="button" className="w-full text-left py-3 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus-visible:outline-2 focus-visible:outline-electric-cyan break-words" onClick={() => {
                        onSelect(customer);
                        setSelectedName(customer.customerName);
                        close();
                      }}>
                        <span className="block text-sm font-medium">{customer.customerName}</span>
                        <span className="block text-sm text-gray-600 dark:text-gray-300">
                          {[customer.customerAttention, customer.customerEmail].filter(Boolean).join(' · ') || 'Geen e-mailadres bekend'}
                        </span>
                        <span className="block text-sm text-gray-600 dark:text-gray-300">
                          {customer.customerAddress.replaceAll('\n', ', ') || 'Geen adres bekend'}
                        </span>
                      </button>
                    </li>
                  );
                })}
              </ul>
              {matches.length > 20 && <p role="status" className="text-sm text-gray-600 dark:text-gray-300">20 van {matches.length} contacten getoond. Zoek gerichter om een ander contact te vinden.</p>}
            </>
          )}
        </div>
      )}
      {!open && selectedName && <p role="status" className="text-sm text-gray-600 dark:text-gray-300">Gegevens van {selectedName} overgenomen. Controleer de klantgegevens hieronder.</p>}
    </div>
  );
}
