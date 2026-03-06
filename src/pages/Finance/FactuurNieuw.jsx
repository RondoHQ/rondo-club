import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft, ChevronDown, Plus, Search, Trash2, X } from 'lucide-react';
import { useCreateInvoice, useNextInvoiceNumber } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useSearch } from '@/hooks/useDashboard';
import { useFinanceSettings } from '@/hooks/useFinanceSettings';

const emptyLine = { description: '', amount: '' };
const DEFAULT_SUBJECT_TEMPLATE = 'Factuur {factuur_nummer} - {organisatie_naam}';
const DEFAULT_BODY_TEMPLATE = `Beste {naam},

Bijgevoegd vindt u factuur {factuur_nummer}.

Het totaalbedrag is {totaal_bedrag}.
U kunt betalen via: {betaallink}

Met vriendelijke groet,
{organisatie_naam}`;

const formatDateForInput = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

export default function FactuurNieuw() {
  useDocumentTitle('Nieuwe factuur');
  const navigate = useNavigate();
  const createInvoice = useCreateInvoice();
  const { data: preview } = useNextInvoiceNumber();
  const { data: financeSettings } = useFinanceSettings();

  const [invoiceKind, setInvoiceKind] = useState('normal');
  const [invoiceTarget, setInvoiceTarget] = useState('member');
  const [customerName, setCustomerName] = useState('');
  const [customerAttention, setCustomerAttention] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');
  const [customerAddress, setCustomerAddress] = useState('');
  const [personId, setPersonId] = useState('');
  const [personSearch, setPersonSearch] = useState('');
  const [isPersonOpen, setIsPersonOpen] = useState(false);
  const [dueDate, setDueDate] = useState(() => {
    const defaultDueDate = new Date();
    defaultDueDate.setDate(defaultDueDate.getDate() + 14);
    return formatDateForInput(defaultDueDate);
  });
  const [emailSubject, setEmailSubject] = useState('');
  const [emailBody, setEmailBody] = useState('');
  const [emailDefaultsHydrated, setEmailDefaultsHydrated] = useState(false);
  const [customFields, setCustomFields] = useState([{ label: '', text: '' }, { label: '', text: '' }]);
  const [showCustomFields, setShowCustomFields] = useState(false);
  const [lineItems, setLineItems] = useState([{ ...emptyLine }]);
  const [error, setError] = useState('');
  const personInputRef = useRef(null);
  const personDropdownRef = useRef(null);

  const searchTerm = personSearch.trim();
  const { data: searchResults } = useSearch(searchTerm);
  const memberOptions = (searchResults?.people || []).slice(0, 20);
  const defaultEmailSubject = financeSettings?.regular_invoice_email_subject || DEFAULT_SUBJECT_TEMPLATE;
  const defaultEmailBody = financeSettings?.regular_invoice_email_body || DEFAULT_BODY_TEMPLATE;

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        personDropdownRef.current &&
        !personDropdownRef.current.contains(event.target) &&
        personInputRef.current &&
        !personInputRef.current.contains(event.target)
      ) {
        setIsPersonOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (invoiceTarget === 'member') {
      setCustomerName('');
      setCustomerAttention('');
      setCustomerEmail('');
      setCustomerAddress('');
      return;
    }
    setPersonId('');
    setPersonSearch('');
    setIsPersonOpen(false);
  }, [invoiceTarget]);

  useEffect(() => {
    if (emailDefaultsHydrated || !financeSettings) {
      return;
    }

    setEmailSubject(defaultEmailSubject);
    setEmailBody(defaultEmailBody);
    setEmailDefaultsHydrated(true);
  }, [defaultEmailBody, defaultEmailSubject, emailDefaultsHydrated, financeSettings]);

  const total = useMemo(() => lineItems.reduce((sum, item) => {
    const amount = parseFloat(item.amount) || 0;
    return sum + amount;
  }, 0), [lineItems]);

  const updateLine = (index, key, value) => {
    setLineItems((prev) => prev.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
  };

  const handlePersonSearchChange = (value) => {
    setPersonSearch(value);
    setIsPersonOpen(true);
    setPersonId('');
  };

  const handleSelectPerson = (person) => {
    setPersonId(String(person.id));
    setPersonSearch(person.name || person.title || `Lid ${person.id}`);
    setIsPersonOpen(false);
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');

    const rows = lineItems
      .map((row) => ({ description: row.description.trim(), amount: parseFloat(row.amount) || 0 }))
      .filter((row) => row.description || row.amount !== 0);

    if (rows.length === 0) {
      setError('Voeg minimaal één regel toe.');
      return;
    }

    if (invoiceTarget === 'member' && !personId) {
      setError('Selecteer een lid voor deze factuur.');
      return;
    }

    if (invoiceTarget === 'external' && (!customerName.trim() || !customerAddress.trim())) {
      setError('Vul klantnaam en adres in voor een externe factuur.');
      return;
    }

    try {
      const created = await createInvoice.mutateAsync({
        person_id: invoiceTarget === 'member' && personId ? parseInt(personId, 10) : null,
        invoice_type: 'manual',
        invoice_kind: invoiceKind,
        customer_name: invoiceTarget === 'external' ? customerName : '',
        customer_attention: invoiceTarget === 'external' ? customerAttention : '',
        customer_email: invoiceTarget === 'external' ? customerEmail : '',
        customer_address: invoiceTarget === 'external' ? customerAddress : '',
        payment_terms_due_date: dueDate ? dueDate.replaceAll('-', '') : '',
        email_subject: emailSubject === defaultEmailSubject ? '' : emailSubject,
        email_body_override: emailBody === defaultEmailBody ? '' : emailBody,
        custom_fields: customFields,
        line_items: rows,
      });
      navigate(`/financien/facturen/${created.id}`);
    } catch (err) {
      setError(err?.response?.data?.message || 'Factuur kon niet worden opgeslagen.');
    }
  };

  return (
    <div className="space-y-6 max-w-4xl">
      <div className="flex items-center justify-between">
        <div>
          <Link to="/financien/facturen" className="text-electric-cyan hover:underline inline-flex items-center gap-1">
            <ArrowLeft className="w-4 h-4" /> Terug naar facturen
          </Link>
          <h1 className="text-2xl font-semibold mt-2">Nieuwe factuur</h1>
          <p className="text-sm text-gray-500">Nummer preview: <strong>{preview?.invoice_number || '...'}</strong></p>
        </div>
      </div>

      <form onSubmit={onSubmit} className="card p-6 space-y-6">
        {error && <p className="text-red-600 text-sm">{error}</p>}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label className="text-sm">Type
            <select className="input mt-1" value={invoiceKind} onChange={(e) => setInvoiceKind(e.target.value)}>
              <option value="normal">Normale factuur</option>
              <option value="credit">Creditfactuur</option>
            </select>
          </label>
          <div className="text-sm">
            <span>Factuur voor</span>
            <div className="mt-1 flex items-center gap-4">
              <label className="inline-flex items-center gap-2 cursor-pointer">
                <input
                  type="radio"
                  name="invoice-target"
                  value="member"
                  checked={invoiceTarget === 'member'}
                  onChange={() => setInvoiceTarget('member')}
                />
                <span>Lid</span>
              </label>
              <label className="inline-flex items-center gap-2 cursor-pointer">
                <input
                  type="radio"
                  name="invoice-target"
                  value="external"
                  checked={invoiceTarget === 'external'}
                  onChange={() => setInvoiceTarget('external')}
                />
                <span>Extern</span>
              </label>
            </div>
          </div>
        </div>

        {invoiceTarget === 'member' && (
          <label className="text-sm block">Lid
            <div className="relative mt-1">
              <input
                ref={personInputRef}
                className="input pr-16"
                value={personSearch}
                onChange={(e) => handlePersonSearchChange(e.target.value)}
                onFocus={() => setIsPersonOpen(true)}
                placeholder="Zoek lid op naam..."
              />
              <Search className="absolute right-9 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
              {(personSearch || personId) && (
                <button
                  type="button"
                  className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  onClick={() => {
                    setPersonSearch('');
                    setPersonId('');
                    setIsPersonOpen(false);
                  }}
                  aria-label="Lid wissen"
                >
                  <X className="w-4 h-4" />
                </button>
              )}
              {isPersonOpen && (
                <div
                  ref={personDropdownRef}
                  className="absolute z-20 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-56 overflow-y-auto"
                >
                  {searchTerm.length < 2 ? (
                    <p className="px-3 py-2 text-sm text-gray-500">Typ minimaal 2 tekens om te zoeken</p>
                  ) : memberOptions.length > 0 ? (
                    memberOptions.map((person) => (
                      <button
                        key={person.id}
                        type="button"
                        onClick={() => handleSelectPerson(person)}
                        className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                      >
                        {person.name || person.title || `Lid ${person.id}`}
                      </button>
                    ))
                  ) : (
                    <p className="px-3 py-2 text-sm text-gray-500">Geen leden gevonden</p>
                  )}
                </div>
              )}
            </div>
            {personId && (
              <p className="mt-1 text-xs text-gray-500">Geselecteerde lid-ID: {personId}</p>
            )}
          </label>
        )}

        {invoiceTarget === 'external' && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label className="text-sm">Klantnaam
              <input className="input mt-1" value={customerName} onChange={(e) => setCustomerName(e.target.value)} />
            </label>
            <label className="text-sm">Ter attentie van
              <input className="input mt-1" value={customerAttention} onChange={(e) => setCustomerAttention(e.target.value)} />
            </label>
            <label className="text-sm md:col-span-2">E-mail
              <input className="input mt-1" type="email" value={customerEmail} onChange={(e) => setCustomerEmail(e.target.value)} />
            </label>
            <label className="text-sm md:col-span-2">Adres
              <textarea className="input mt-1 min-h-20" value={customerAddress} onChange={(e) => setCustomerAddress(e.target.value)} />
            </label>
          </div>
        )}

        <label className="text-sm block max-w-sm">Vervaldatum
          <input type="date" className="input mt-1" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
        </label>

        <div className="space-y-2">
          {!showCustomFields ? (
            <button
              type="button"
              className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 hover:underline"
              onClick={() => setShowCustomFields(true)}
            >
              PO nummer of andere gegevens toevoegen?
              <ChevronDown className="w-4 h-4" />
            </button>
          ) : (
            <div className="space-y-2">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {customFields.map((field, index) => (
                  <div key={index} className="space-y-1">
                    <input className="input" placeholder={`Extra veld ${index + 1} label`} value={field.label} onChange={(e) => setCustomFields((prev) => prev.map((f, i) => i === index ? { ...f, label: e.target.value } : f))} />
                    <textarea className="input min-h-16" placeholder="Tekst" value={field.text} onChange={(e) => setCustomFields((prev) => prev.map((f, i) => i === index ? { ...f, text: e.target.value } : f))} />
                  </div>
                ))}
              </div>
              <button
                type="button"
                className="text-sm text-gray-500 hover:text-gray-700 hover:underline"
                onClick={() => setShowCustomFields(false)}
              >
                Verberg extra gegevens
              </button>
            </div>
          )}
        </div>

        <div className="space-y-2">
          <h2 className="font-medium">Regels</h2>
          {lineItems.map((item, index) => (
            <div key={index} className="grid grid-cols-12 gap-2 items-center">
              <input className="input col-span-8" placeholder="Omschrijving" value={item.description} onChange={(e) => updateLine(index, 'description', e.target.value)} />
              <input className="input col-span-3" type="number" step="0.01" placeholder="Bedrag" value={item.amount} onChange={(e) => updateLine(index, 'amount', e.target.value)} />
              <button type="button" onClick={() => setLineItems((prev) => prev.filter((_, i) => i !== index))} className="btn btn-secondary col-span-1" disabled={lineItems.length === 1}>
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
          <button type="button" className="btn btn-secondary" onClick={() => setLineItems((prev) => [...prev, { ...emptyLine }])}>
            <Plus className="w-4 h-4" /> Regel toevoegen
          </button>
          <p className="text-sm text-gray-600">Totaal: <strong>{total.toFixed(2)}</strong></p>
        </div>

        <div className="grid grid-cols-1 gap-3">
          <label className="text-sm">
            E-mail onderwerp (optioneel)
            <input className="input mt-1" value={emailSubject} onChange={(e) => setEmailSubject(e.target.value)} />
          </label>
          <label className="text-sm">
            E-mail body (optioneel)
            <textarea className="input min-h-24 mt-1" value={emailBody} onChange={(e) => setEmailBody(e.target.value)} />
          </label>
          <p className="text-xs text-gray-500">
            Deze velden starten met de standaard uit <Link to="/settings/financieel" className="text-electric-cyan hover:underline">Financieel instellingen</Link>. Variabelen zoals <code>{'{factuur_nummer}'}</code> en <code>{'{organisatie_naam}'}</code> blijven werken.
          </p>
        </div>

        <div className="flex gap-2">
          <button type="submit" className="btn btn-primary" disabled={createInvoice.isPending}>Opslaan als concept</button>
        </div>
      </form>
    </div>
  );
}
