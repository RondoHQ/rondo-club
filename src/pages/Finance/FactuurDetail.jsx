import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, Send, CheckCircle, RefreshCw, Download, FileText, Receipt, User, Calendar, CreditCard, ExternalLink, QrCode } from 'lucide-react';
import { useInvoice, useSendInvoice, useUpdateInvoiceStatus, useResendInvoice, useGenerateInvoicePdf, useRegeneratePaymentLink, useResetPaymentState } from '@/hooks/useInvoices';
import { useCreatePaymentLink, useFinanceSettings } from '@/hooks/useFinanceSettings';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format, parseYmd } from '@/utils/dateFormat';
import { formatCurrency } from '@/utils/formatters';
import { prmApi } from '@/api/client';

// Status badge colors (same as Facturen.jsx)
const statusColors = {
  draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  paid: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

// Status display labels
const statusLabels = {
  draft: 'Concept',
  sent: 'Verstuurd',
  paid: 'Betaald',
  overdue: 'Verlopen',
};

/**
 * Status badge component
 */
function StatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center px-2 py-1 rounded-full text-sm font-medium ${statusColors[status] || statusColors.draft}`}>
      {statusLabels[status] || status}
    </span>
  );
}

export default function FactuurDetail() {
  const { id } = useParams();
  const { data: invoice, isLoading, error } = useInvoice(id);
  const sendInvoice = useSendInvoice();
  const updateInvoiceStatus = useUpdateInvoiceStatus();
  const resendInvoice = useResendInvoice();
  const generatePdf = useGenerateInvoicePdf();
  const createPaymentLink = useCreatePaymentLink();
  const regeneratePaymentLink = useRegeneratePaymentLink();
  const resetPaymentState = useResetPaymentState();
  const { data: financeSettings } = useFinanceSettings();
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  const isTestMode = (() => {
    if (!financeSettings) return false;
    const provider = financeSettings.active_payment_provider;
    if (provider === 'mollie') return financeSettings.mollie_environment === 'test';
    if (provider === 'rabobank') return financeSettings.rabobank_environment === 'sandbox';
    return false;
  })();

  useDocumentTitle(invoice?.invoice_number || 'Factuur');

  // Auto-hide success message after 3 seconds
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(''), 3000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  // Clear error on invoice change
  useEffect(() => {
    setErrorMessage('');
  }, [invoice]);

  const handleSend = async () => {
    if (!window.confirm('Weet je zeker dat je deze factuur wilt versturen?')) {
      return;
    }
    setErrorMessage('');
    try {
      await sendInvoice.mutateAsync(id);
      setSuccessMessage('Factuur succesvol verstuurd!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het versturen.');
    }
  };

  const handleMarkPaid = async () => {
    if (!window.confirm('Weet je zeker dat je deze factuur als betaald wilt markeren?')) {
      return;
    }
    setErrorMessage('');
    try {
      await updateInvoiceStatus.mutateAsync({ id, status: 'paid' });
      setSuccessMessage('Factuur gemarkeerd als betaald!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het markeren als betaald.');
    }
  };

  const handleResend = async () => {
    if (!window.confirm('Weet je zeker dat je deze factuur opnieuw wilt versturen?')) {
      return;
    }
    setErrorMessage('');
    try {
      await resendInvoice.mutateAsync(id);
      setSuccessMessage('Factuur opnieuw verstuurd!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het opnieuw versturen.');
    }
  };

  const handleDownloadPdf = () => {
    if (invoice?.pdf_path) {
      window.open(prmApi.getInvoicePdfUrl(id), '_blank');
    }
  };

  const handleGeneratePdf = async () => {
    setErrorMessage('');
    try {
      await generatePdf.mutateAsync(id);
      setSuccessMessage('PDF succesvol gegenereerd!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het genereren van de PDF.');
    }
  };

  const handleRegeneratePdf = async () => {
    if (!window.confirm('Weet je zeker dat je de PDF opnieuw wilt genereren? Dit overschrijft de bestaande PDF.')) {
      return;
    }
    setErrorMessage('');
    try {
      await generatePdf.mutateAsync(id);
      setSuccessMessage('PDF opnieuw gegenereerd!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het opnieuw genereren van de PDF.');
    }
  };

  const handleCreatePaymentLink = async () => {
    setErrorMessage('');
    try {
      await createPaymentLink.mutateAsync(id);
      setSuccessMessage('Betaallink succesvol aangemaakt!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het aanmaken van de betaallink.');
    }
  };

  const handleRegeneratePaymentLink = async () => {
    if (!window.confirm('Weet je zeker dat je een nieuwe betaallink wilt aanmaken? De bestaande link wordt vervangen.')) {
      return;
    }
    setErrorMessage('');
    try {
      await regeneratePaymentLink.mutateAsync(id);
      setSuccessMessage('Betaallink opnieuw aangemaakt!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het opnieuw aanmaken van de betaallink.');
    }
  };

  const handleResetPaymentState = async () => {
    if (!window.confirm('Weet je zeker dat je de factuur wilt resetten? Dit wist de betaallink, factuur-PDF, en betaalstatus (alleen in testmodus).')) {
      return;
    }
    setErrorMessage('');
    try {
      await resetPaymentState.mutateAsync(id);
      setSuccessMessage('Betaalstatus gereset!');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het resetten van de betaalstatus.');
    }
  };

  const isPending = sendInvoice.isPending || updateInvoiceStatus.isPending || resendInvoice.isPending || generatePdf.isPending || createPaymentLink.isPending || regeneratePaymentLink.isPending || resetPaymentState.isPending;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="card p-8 text-center">
        <p className="text-red-600 dark:text-red-400">
          Failed to load invoice: {error.message}
        </p>
        <Link to="/financien/facturen" className="text-electric-cyan dark:text-electric-cyan hover:underline mt-4 inline-block">
          Terug naar facturen
        </Link>
      </div>
    );
  }

  if (!invoice) {
    return (
      <div className="card p-8 text-center">
        <p className="text-gray-500 dark:text-gray-400">Factuur niet gevonden.</p>
        <Link to="/financien/facturen" className="text-electric-cyan dark:text-electric-cyan hover:underline mt-4 inline-block">
          Terug naar facturen
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Back link */}
      <Link
        to="/financien/facturen"
        className="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
      >
        <ArrowLeft className="w-4 h-4 mr-1" />
        Terug naar facturen
      </Link>

      {/* Success message */}
      {successMessage && (
        <div className="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4">
          <p className="text-green-700 dark:text-green-400">{successMessage}</p>
        </div>
      )}

      {/* Error message */}
      {errorMessage && (
        <div className="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4">
          <p className="text-red-700 dark:text-red-400">{errorMessage}</p>
        </div>
      )}

      {/* Header */}
      <div className="card p-6">
        <div className="flex items-start justify-between mb-4">
          <div>
            <h1 className="text-2xl font-bold text-brand-gradient mb-2">
              {invoice.invoice_number}
            </h1>
          </div>
          <StatusBadge status={invoice.status} />
        </div>
      </div>

      {/* Info cards - two column layout */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Left card: Factuurgegevens */}
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
            <Receipt className="w-5 h-5" />
            Factuurgegevens
          </h2>
          <div className="space-y-3">
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Factuurnummer</h3>
              <p className="text-gray-700 dark:text-gray-300">{invoice.invoice_number}</p>
            </div>
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Status</h3>
              <div className="mt-1">
                <StatusBadge status={invoice.status} />
              </div>
            </div>
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Aangemaakt</h3>
              <p className="text-gray-700 dark:text-gray-300">
                {format(new Date(invoice.created), 'd MMM yyyy')}
              </p>
            </div>
            {invoice.sent_date && (
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Verstuurd</h3>
                <p className="text-gray-700 dark:text-gray-300">
                  {format(parseYmd(invoice.sent_date), 'd MMM yyyy')}
                </p>
              </div>
            )}
            {invoice.due_date && (
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Vervaldatum</h3>
                <p className="text-gray-700 dark:text-gray-300">
                  {format(parseYmd(invoice.due_date), 'd MMM yyyy')}
                </p>
              </div>
            )}
            {invoice.payment_link && (
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Betaallink</h3>
                <a
                  href={invoice.payment_link}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-electric-cyan dark:text-electric-cyan hover:underline text-sm flex items-center gap-1"
                >
                  <ExternalLink className="w-3 h-3" />
                  Open betaallink
                </a>
              </div>
            )}
            {invoice.qr_code_path && (
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1">
                  <QrCode className="w-3.5 h-3.5" />
                  QR Code
                </h3>
                <img
                  src={prmApi.getInvoiceQrUrl(id)}
                  alt="Betaal QR code"
                  className="max-w-48 rounded border border-gray-200 dark:border-gray-700"
                />
              </div>
            )}
          </div>
        </div>

        {/* Right card: Lid */}
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
            <User className="w-5 h-5" />
            Lid
          </h2>
          <div className="space-y-3">
            <div>
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Naam</h3>
              {invoice.person?.id ? (
                <Link
                  to={`/people/${invoice.person.id}`}
                  className="text-electric-cyan dark:text-electric-cyan hover:underline"
                >
                  {invoice.person.name}
                </Link>
              ) : (
                <p className="text-gray-700 dark:text-gray-300">{invoice.person?.name || '-'}</p>
              )}
            </div>
            {invoice.person?.email && (
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Email</h3>
                <p className="text-gray-700 dark:text-gray-300">{invoice.person.email}</p>
              </div>
            )}
            <div className="pt-3 mt-3 border-t border-gray-100 dark:border-gray-700">
              <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Totaalbedrag</h3>
              <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                {formatCurrency(invoice.total_amount, 2)}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Line items table */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Regels</h2>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="border-b border-gray-200 dark:border-gray-700">
              <tr>
                <th className="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                  Omschrijving
                </th>
                <th className="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">
                  Bedrag
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {invoice.line_items?.map((item, index) => (
                <tr key={index}>
                  <td className="px-4 py-3">
                    <div className="text-gray-900 dark:text-gray-100">{item.description}</div>
                    {item.discipline_case && (
                      <div className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Dossier: {item.discipline_case.dossier_id}
                        {item.discipline_case.match_description && (
                          <> • {item.discipline_case.match_description}</>
                        )}
                        {item.discipline_case.sanction && (
                          <> • {item.discipline_case.sanction}</>
                        )}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                    {formatCurrency(item.amount, 2)}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t-2 border-gray-300 dark:border-gray-600">
              <tr>
                <td className="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100">
                  Totaal
                </td>
                <td className="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100">
                  {formatCurrency(invoice.total_amount, 2)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      {/* Action buttons */}
      <div className="card p-6">
        <div className="flex flex-wrap gap-3">
          {/* Draft status actions */}
          {invoice.status === 'draft' && (
            <>
              <button
                onClick={handleSend}
                disabled={isPending}
                className="btn-primary flex items-center gap-2"
              >
                {sendInvoice.isPending ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                ) : (
                  <Send className="w-4 h-4" />
                )}
                {isTestMode ? 'Verstuur factuur (test)' : 'Verstuur factuur'}
              </button>
              {invoice.pdf_path ? (
                <button
                  onClick={handleDownloadPdf}
                  disabled={isPending}
                  className="btn-secondary flex items-center gap-2"
                >
                  <Download className="w-4 h-4" />
                  Download PDF
                </button>
              ) : (
                <button
                  onClick={handleGeneratePdf}
                  disabled={isPending}
                  className="btn-secondary flex items-center gap-2"
                >
                  {generatePdf.isPending ? (
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
                  ) : (
                    <FileText className="w-4 h-4" />
                  )}
                  Genereer PDF
                </button>
              )}
              {invoice.pdf_path && (
                <button
                  onClick={handleRegeneratePdf}
                  disabled={isPending}
                  className="btn-secondary flex items-center gap-2"
                >
                  {generatePdf.isPending ? (
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
                  ) : (
                    <RefreshCw className="w-4 h-4" />
                  )}
                  PDF opnieuw genereren
                </button>
              )}
            </>
          )}

          {/* Sent/Overdue status actions */}
          {(invoice.status === 'sent' || invoice.status === 'overdue') && (
            <>
              <button
                onClick={handleMarkPaid}
                disabled={isPending}
                className="btn-primary flex items-center gap-2 bg-green-600 hover:bg-green-700"
              >
                {updateInvoiceStatus.isPending ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                ) : (
                  <CheckCircle className="w-4 h-4" />
                )}
                Markeer als betaald
              </button>
              <button
                onClick={handleResend}
                disabled={isPending}
                className="btn-secondary flex items-center gap-2"
              >
                {resendInvoice.isPending ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
                ) : (
                  <RefreshCw className="w-4 h-4" />
                )}
                {isTestMode ? 'Opnieuw versturen (test)' : 'Opnieuw versturen'}
              </button>
              <button
                onClick={handleDownloadPdf}
                disabled={!invoice.pdf_path || isPending}
                className="btn-secondary flex items-center gap-2"
              >
                <Download className="w-4 h-4" />
                Download PDF
              </button>
              {invoice.pdf_path && (
                <button
                  onClick={handleRegeneratePdf}
                  disabled={isPending}
                  className="btn-secondary flex items-center gap-2"
                >
                  {generatePdf.isPending ? (
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
                  ) : (
                    <RefreshCw className="w-4 h-4" />
                  )}
                  PDF opnieuw genereren
                </button>
              )}
            </>
          )}

          {/* Payment link button (for any unpaid invoice without a link) */}
          {invoice.status !== 'paid' && !invoice.payment_link && (
            <button
              onClick={handleCreatePaymentLink}
              disabled={isPending}
              className="btn-secondary flex items-center gap-2"
            >
              {createPaymentLink.isPending ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
              ) : (
                <CreditCard className="w-4 h-4" />
              )}
              Betaallink aanmaken
            </button>
          )}

          {/* Regenerate payment link button (for any unpaid invoice WITH an existing link) */}
          {invoice.status !== 'paid' && invoice.payment_link && (
            <button
              onClick={handleRegeneratePaymentLink}
              disabled={isPending}
              className="btn-secondary flex items-center gap-2"
            >
              {regeneratePaymentLink.isPending ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
              ) : (
                <RefreshCw className="w-4 h-4" />
              )}
              Betaallink opnieuw aanmaken
            </button>
          )}

          {/* Paid status actions */}
          {invoice.status === 'paid' && (
            <>
              <button
                onClick={handleDownloadPdf}
                disabled={!invoice.pdf_path || isPending}
                className="btn-secondary flex items-center gap-2"
              >
                <Download className="w-4 h-4" />
                Download PDF
              </button>
              {invoice.pdf_path && (
                <button
                  onClick={handleRegeneratePdf}
                  disabled={isPending}
                  className="btn-secondary flex items-center gap-2"
                >
                  {generatePdf.isPending ? (
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
                  ) : (
                    <RefreshCw className="w-4 h-4" />
                  )}
                  PDF opnieuw genereren
                </button>
              )}
            </>
          )}

          {/* Reset payment state button (test mode only) */}
          {isTestMode && (invoice.payment_link || invoice.qr_code_path || invoice.status === 'paid') && (
            <button
              onClick={handleResetPaymentState}
              disabled={isPending}
              className="btn-secondary flex items-center gap-2 border-orange-300 dark:border-orange-700 text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20"
            >
              {resetPaymentState.isPending ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-orange-600 dark:border-orange-400"></div>
              ) : (
                <RefreshCw className="w-4 h-4" />
              )}
              Reset factuur (test)
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
