import { useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useCreateInvoice, useNextInvoiceNumber, useInvoice } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import InvoiceDraftForm from '@/components/finance/InvoiceDraftForm';
import { mapInvoiceToInitialValues } from '@/utils/invoiceFormValues';

export default function FactuurNieuw() {
  const [searchParams] = useSearchParams();
  const copyFromId = searchParams.get('copyFrom');
  const isCopy = !!copyFromId;

  const { data: source, isLoading: isLoadingSource } = useInvoice(copyFromId);
  useDocumentTitle(isCopy ? 'Factuur kopiëren' : 'Nieuwe factuur');
  const navigate = useNavigate();
  const createInvoice = useCreateInvoice();
  const { data: preview } = useNextInvoiceNumber();
  const [error, setError] = useState('');

  // Contributiefacturen are managed automatically (payment page + termijnen), so
  // a hand-made copy is created as a manual invoice. Manual/discipline keep their type.
  const invoiceType = isCopy
    ? (['membership', 'tournament'].includes(source?.invoice_type) ? 'manual' : (source?.invoice_type || 'manual'))
    : 'manual';

  const initialValues = useMemo(() => (
    isCopy && source
      ? mapInvoiceToInitialValues(source, { copy: true })
      : { invoiceTarget: 'member' }
  ), [isCopy, source]);

  const handleSubmit = async (payload) => {
    setError('');
    try {
      const created = await createInvoice.mutateAsync({
        invoice_type: invoiceType,
        ...payload,
      });
      navigate(`/financien/facturen/${created.id}`);
    } catch (err) {
      setError(err?.response?.data?.message || 'Factuur kon niet worden opgeslagen.');
    }
  };

  if (isCopy && isLoadingSource) {
    return (
      <div className="max-w-4xl">
        <div className="card p-8 text-center text-gray-500 dark:text-gray-400">Factuur laden…</div>
      </div>
    );
  }

  if (isCopy && !source) {
    return (
      <div className="max-w-4xl">
        <div className="card p-8 text-center">
          <p className="text-gray-500 dark:text-gray-400">De te kopiëren factuur kon niet worden geladen.</p>
          <Link to="/financien/facturen" className="text-electric-cyan hover:underline mt-4 inline-block">
            Terug naar facturen
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl">
      {isCopy && source && (
        <div className="mb-4 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 text-sm text-blue-700 dark:text-blue-300">
          Deze conceptfactuur is een kopie van{' '}
          <Link to={`/financien/facturen/${source.id}`} className="underline font-medium">
            {source.invoice_number}
          </Link>
          . Controleer de gegevens en sla op als concept — er wordt een nieuw factuurnummer toegekend bij versturen.
        </div>
      )}
      <InvoiceDraftForm
        invoiceType={invoiceType}
        formKey={isCopy ? `copy-${copyFromId}` : 'new'}
        initialValues={initialValues}
        onSubmit={handleSubmit}
        submitLabel="Opslaan als concept"
        isSubmitting={createInvoice.isPending}
        error={error}
        backLink="/financien/facturen"
        backLabel="Terug naar facturen"
        heading={isCopy ? 'Nieuwe factuur (kopie)' : 'Nieuwe factuur'}
        previewInvoiceNumber={preview?.invoice_number || '...'}
      />
    </div>
  );
}
