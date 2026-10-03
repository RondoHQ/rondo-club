import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { useInvoice } from '@/hooks/useInvoices';
import { useCreditDraft } from '@/hooks/useCreditDraft';
import CreditPreview from './CreditPreview';
import { formatCurrency } from '@/utils/formatters';

export default function CreditNoteForm({ sourceInvoiceId }) {
  const { data: invoice, isLoading, error: loadError } = useInvoice(sourceInvoiceId);
  const [amount, setAmount] = useState('');
  const [reason, setReason] = useState('');
  const payload = { source_invoice_id: Number(sourceInvoiceId), amount: Number(amount), reason };
  const { preview, pending, error, reset, submit } = useCreditDraft(payload);
  const change = (setter, value) => { setter(value); reset(); };
  const available = invoice ? Math.max(0, invoice.total_amount - (invoice.linked_credits || []).filter(credit => credit.status !== 'cancelled').reduce((sum, credit) => sum + Math.abs(credit.total_amount), 0)) : 0;
  return <div className="max-w-3xl space-y-5">
    <Link to={`/financien/facturen/${sourceInvoiceId}`} className="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300"><ArrowLeft className="h-4 w-4" />Terug naar factuur</Link>
    <div className="card p-5 sm:p-6">
      <h1 className="text-2xl font-semibold mb-3">Creditnota maken</h1>
      {isLoading ? <p role="status">Factuur laden…</p> : loadError || !invoice ? <p role="alert">De oorspronkelijke factuur kon niet worden geladen.</p> : <form onSubmit={submit} className="space-y-5">
        <p className="text-sm text-gray-600 dark:text-gray-300">Bij factuur <strong>{invoice.invoice_number}</strong> voor {invoice.person?.name || invoice.customer_name}. De klantgegevens worden overgenomen.</p>
        <p className="text-sm">Nog te crediteren: <strong className="tabular-nums">{formatCurrency(available, 2)}</strong></p>
        <fieldset disabled={pending} className="space-y-4 disabled:opacity-70">
          <label className="block text-sm font-medium">Te crediteren bedrag (€)<input className="input mt-1 w-full" type="number" min="0.01" max={available} step="0.01" required value={amount} onChange={event => change(setAmount, event.target.value)} /></label>
          <label className="block text-sm font-medium">Reden<textarea className="input mt-1 w-full" required maxLength={500} rows={3} value={reason} onChange={event => change(setReason, event.target.value)} /></label>
        </fieldset>
        <CreditPreview preview={preview} />
        {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
        <button className="btn-primary" disabled={pending || available <= 0}>{pending ? 'Bezig…' : preview ? 'Opslaan als concept' : 'Controleer creditnota'}</button>
      </form>}
    </div>
  </div>;
}
