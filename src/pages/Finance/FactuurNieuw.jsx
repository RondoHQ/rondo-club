import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCreateInvoice, useNextInvoiceNumber } from '@/hooks/useInvoices';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import InvoiceDraftForm from '@/components/finance/InvoiceDraftForm';

export default function FactuurNieuw() {
  useDocumentTitle('Nieuwe factuur');
  const navigate = useNavigate();
  const createInvoice = useCreateInvoice();
  const { data: preview } = useNextInvoiceNumber();
  const [error, setError] = useState('');

  const handleSubmit = async (payload) => {
    setError('');
    try {
      const created = await createInvoice.mutateAsync({
        invoice_type: 'manual',
        ...payload,
      });
      navigate(`/financien/facturen/${created.id}`);
    } catch (err) {
      setError(err?.response?.data?.message || 'Factuur kon niet worden opgeslagen.');
    }
  };

  return (
    <div className="max-w-4xl">
      <InvoiceDraftForm
        invoiceType="manual"
        formKey="new"
        initialValues={{ invoiceTarget: 'member' }}
        onSubmit={handleSubmit}
        submitLabel="Opslaan als concept"
        isSubmitting={createInvoice.isPending}
        error={error}
        backLink="/financien/facturen"
        backLabel="Terug naar facturen"
        heading="Nieuwe factuur"
        previewInvoiceNumber={preview?.invoice_number || '...'}
      />
    </div>
  );
}
