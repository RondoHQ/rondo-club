import { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useCreateInvoice, useNextInvoiceNumber } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const emptyLine = { description: '', amount: '' };

export default function FactuurNieuw() {
  useDocumentTitle('Nieuwe factuur');
  const navigate = useNavigate();
  const createInvoice = useCreateInvoice();
  const { data: preview } = useNextInvoiceNumber();

  const [invoiceKind, setInvoiceKind] = useState('normal');
  const [customerName, setCustomerName] = useState('');
  const [customerAddress, setCustomerAddress] = useState('');
  const [personId, setPersonId] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [emailSubject, setEmailSubject] = useState('');
  const [emailBody, setEmailBody] = useState('');
  const [customFields, setCustomFields] = useState([{ label: '', text: '' }, { label: '', text: '' }]);
  const [lineItems, setLineItems] = useState([{ ...emptyLine }]);
  const [error, setError] = useState('');

  const total = useMemo(() => lineItems.reduce((sum, item) => {
    const amount = parseFloat(item.amount) || 0;
    return sum + (invoiceKind === 'credit' ? -Math.abs(amount) : amount);
  }, 0), [lineItems, invoiceKind]);

  const updateLine = (index, key, value) => {
    setLineItems((prev) => prev.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
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

    if (!personId && (!customerName.trim() || !customerAddress.trim())) {
      setError('Vul klantnaam en adres in of koppel een lid.');
      return;
    }

    try {
      const created = await createInvoice.mutateAsync({
        person_id: personId ? parseInt(personId, 10) : null,
        invoice_type: 'manual',
        invoice_kind: invoiceKind,
        customer_name: customerName,
        customer_address: customerAddress,
        payment_terms_due_date: dueDate ? dueDate.replaceAll('-', '') : '',
        email_subject: emailSubject,
        email_body_override: emailBody,
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
          <label className="text-sm">Lid-ID (optioneel)
            <input className="input mt-1" value={personId} onChange={(e) => setPersonId(e.target.value)} placeholder="Bijv. 123" />
          </label>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <label className="text-sm">Klantnaam
            <input className="input mt-1" value={customerName} onChange={(e) => setCustomerName(e.target.value)} />
          </label>
          <label className="text-sm">Vervaldatum override (optioneel)
            <input type="date" className="input mt-1" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
          </label>
          <label className="text-sm md:col-span-2">Adres
            <textarea className="input mt-1 min-h-20" value={customerAddress} onChange={(e) => setCustomerAddress(e.target.value)} />
          </label>
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

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {customFields.map((field, index) => (
            <div key={index} className="space-y-1">
              <input className="input" placeholder={`Extra veld ${index + 1} label`} value={field.label} onChange={(e) => setCustomFields((prev) => prev.map((f, i) => i === index ? { ...f, label: e.target.value } : f))} />
              <textarea className="input min-h-16" placeholder="Tekst" value={field.text} onChange={(e) => setCustomFields((prev) => prev.map((f, i) => i === index ? { ...f, text: e.target.value } : f))} />
            </div>
          ))}
        </div>

        <div className="grid grid-cols-1 gap-3">
          <input className="input" placeholder="E-mail onderwerp override (optioneel)" value={emailSubject} onChange={(e) => setEmailSubject(e.target.value)} />
          <textarea className="input min-h-24" placeholder="E-mail body override (optioneel)" value={emailBody} onChange={(e) => setEmailBody(e.target.value)} />
        </div>

        <div className="flex gap-2">
          <button type="submit" className="btn btn-primary" disabled={createInvoice.isPending}>Opslaan als concept</button>
        </div>
      </form>
    </div>
  );
}
