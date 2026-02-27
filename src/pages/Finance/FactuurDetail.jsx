import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { ArrowLeft, Send, CheckCircle, RefreshCw, Download, FileText, Receipt, User, CreditCard, ExternalLink, QrCode, Trash2 } from 'lucide-react';
import { useInvoice, useSendInvoice, useUpdateInvoiceStatus, useResendInvoice, useGenerateInvoicePdf, useRegeneratePaymentLink, useResetPaymentState, useDeleteInvoice, useToggleInstallments, useUpdateMembershipInvoiceDiscount, useAddDraftInvoiceLineItem } from '@/hooks/useInvoices';
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

// Installment status badge colors
const installmentStatusColors = {
  pending: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
  sent: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  betaald: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const installmentStatusLabels = {
  pending: 'Openstaand',
  sent: 'Verstuurd',
  betaald: 'Betaald',
  overdue: 'Verlopen',
};

/**
 * Get a human-readable label for a payment plan.
 * Uses actual installment_count when available (dynamic plans may have fewer than 8).
 */
function getPlanLabel(plan, installmentCount) {
  if (!plan || plan === 'full') return 'Volledig';
  if (installmentCount) return `${installmentCount} termijnen`;
  if (plan === 'quarterly_3') return '3 termijnen';
  if (plan === 'monthly_8') return '8 termijnen';
  return plan;
}

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

/**
 * Installment status badge component
 */
function InstallmentStatusBadge({ status }) {
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${installmentStatusColors[status] || installmentStatusColors.pending}`}>
      {installmentStatusLabels[status] || status}
    </span>
  );
}

function getCurrentFamilyDiscountPercent(invoice) {
  const discountLine = invoice?.line_items?.find((item) => /^Gezinskorting\s*\(([\d.,]+)%\)/i.test(item?.description || ''));
  if (!discountLine?.description) return 0;
  const match = discountLine.description.match(/^Gezinskorting\s*\(([\d.,]+)%\)/i);
  if (!match) return 0;
  const parsed = Number.parseFloat(match[1].replace(',', '.'));
  if (!Number.isFinite(parsed)) return 0;
  return Math.max(0, Math.min(100, parsed));
}

function getCurrentEntryDiscountPercent(invoice) {
  const discountLine = invoice?.line_items?.find((item) => /^Instapkorting\s*\(([\d.,]+)%\)/i.test(item?.description || ''));
  if (!discountLine?.description) return 0;
  const match = discountLine.description.match(/^Instapkorting\s*\(([\d.,]+)%\)/i);
  if (!match) return 0;
  const parsed = Number.parseFloat(match[1].replace(',', '.'));
  if (!Number.isFinite(parsed)) return 0;
  return Math.max(0, Math.min(100, parsed));
}

export default function FactuurDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { data: invoice, isLoading, error } = useInvoice(id);
  const sendInvoice = useSendInvoice();
  const updateInvoiceStatus = useUpdateInvoiceStatus();
  const resendInvoice = useResendInvoice();
  const generatePdf = useGenerateInvoicePdf();
  const createPaymentLink = useCreatePaymentLink();
  const regeneratePaymentLink = useRegeneratePaymentLink();
  const resetPaymentState = useResetPaymentState();
  const deleteInvoice = useDeleteInvoice();
  const toggleInstallments = useToggleInstallments();
  const updateMembershipDiscount = useUpdateMembershipInvoiceDiscount();
  const addDraftLineItem = useAddDraftInvoiceLineItem();
  const { data: financeSettings } = useFinanceSettings();
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [familyDiscountPercentInput, setFamilyDiscountPercentInput] = useState('');
  const [entryDiscountPercentInput, setEntryDiscountPercentInput] = useState('');
  const [draftAdjustmentDescription, setDraftAdjustmentDescription] = useState('Correctie');
  const [draftAdjustmentAmount, setDraftAdjustmentAmount] = useState('');

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

  useEffect(() => {
    if (!invoice) return;
    setFamilyDiscountPercentInput(String(getCurrentFamilyDiscountPercent(invoice)));
    setEntryDiscountPercentInput(String(getCurrentEntryDiscountPercent(invoice)));
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

  const handleDelete = async () => {
    if (!window.confirm('Weet je zeker dat je deze conceptfactuur wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) {
      return;
    }
    setErrorMessage('');
    try {
      await deleteInvoice.mutateAsync(id);
      navigate('/financien/facturen');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het verwijderen van de factuur.');
    }
  };

  const handleAddDraftLineItem = async () => {
    const description = draftAdjustmentDescription.trim();
    const parsedAmount = Number.parseFloat(String(draftAdjustmentAmount).replace(',', '.'));

    if (!description) {
      setErrorMessage('Vul een omschrijving in voor de correctieregel.');
      return;
    }
    if (!Number.isFinite(parsedAmount) || Math.abs(parsedAmount) < 0.01) {
      setErrorMessage('Vul een geldig bedrag in (groter dan 0, positief of negatief).');
      return;
    }

    setErrorMessage('');
    try {
      await addDraftLineItem.mutateAsync({
        id,
        description,
        amount: parsedAmount,
      });
      setDraftAdjustmentAmount('');
      setSuccessMessage('Correctieregel toegevoegd. Totaalbedrag is bijgewerkt.');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het toevoegen van de correctieregel.');
    }
  };

  const handleUpdateMembershipDiscount = async () => {
    const parsedFamily = Number.parseFloat(String(familyDiscountPercentInput).replace(',', '.'));
    const parsedEntry = Number.parseFloat(String(entryDiscountPercentInput).replace(',', '.'));
    if (!Number.isFinite(parsedFamily) || parsedFamily < 0 || parsedFamily > 100 || !Number.isFinite(parsedEntry) || parsedEntry < 0 || parsedEntry > 100) {
      setErrorMessage('Vul geldige kortingspercentages in tussen 0 en 100.');
      return;
    }
    if (!window.confirm(`Weet je zeker dat je de gezinskorting (${parsedFamily}%) en instapkorting (${parsedEntry}%) wilt aanpassen?`)) {
      return;
    }
    setErrorMessage('');
    try {
      const updated = await updateMembershipDiscount.mutateAsync({
        id,
        familyDiscountPercent: parsedFamily,
        entryDiscountPercent: parsedEntry,
      });
      setFamilyDiscountPercentInput(String(getCurrentFamilyDiscountPercent(updated)));
      setEntryDiscountPercentInput(String(getCurrentEntryDiscountPercent(updated)));
      setSuccessMessage('Kortingen aangepast. Genereer de PDF opnieuw als je die wilt delen.');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Er is een fout opgetreden bij het aanpassen van de kortingen.');
    }
  };

  const isPending = sendInvoice.isPending || updateInvoiceStatus.isPending || resendInvoice.isPending || generatePdf.isPending || createPaymentLink.isPending || regeneratePaymentLink.isPending || resetPaymentState.isPending || deleteInvoice.isPending || updateMembershipDiscount.isPending || addDraftLineItem.isPending;

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
              {invoice.invoice_number}{invoice.invoice_kind === 'credit' ? ' · Credit' : ''}
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
          <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div className="space-y-3 sm:flex-1">
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
              {invoice.installment_plan && (
                <div>
                  <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Betaalplan</h3>
                  <p className="text-gray-700 dark:text-gray-300">
                    {getPlanLabel(invoice.installment_plan, invoice.installment_count)}
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
                    className="text-electric-cyan dark:text-electric-cyan hover:underline text-sm inline-flex items-center gap-1"
                  >
                    <ExternalLink className="w-3 h-3" />
                    Open betaallink
                  </a>
                </div>
              )}
            </div>
            {invoice.qr_code_path && (
              <div className="sm:shrink-0">
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1">
                  <QrCode className="w-3.5 h-3.5" />
                  QR Code
                </h3>
                <img
                  src={prmApi.getInvoiceQrUrl(id)}
                  alt="Betaal QR code"
                  className="w-40 h-40 rounded border border-gray-200 dark:border-gray-700"
                />
              </div>
            )}
          </div>
        </div>

        {/* Right card: Lid */}
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
            <User className="w-5 h-5" />
            Klant
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
                <p className="text-gray-700 dark:text-gray-300">{invoice.person?.name || invoice.customer_name || '-'}</p>
              )}
            </div>
            {invoice.customer_address && (
              <div>
                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">Adres</h3>
                <p className="text-gray-700 dark:text-gray-300 whitespace-pre-line">{invoice.customer_address}</p>
              </div>
            )}
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
        <div
          className="overflow-x-auto"
          style={{ WebkitOverflowScrolling: 'touch', touchAction: 'pan-x' }}
          data-horizontal-scroll="true"
        >
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
        {invoice.status === 'draft' && (
          <div className="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700">
            <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Extra regel toevoegen</h3>
            <div className="grid grid-cols-1 md:grid-cols-12 gap-3">
              <input
                type="text"
                className="input md:col-span-8"
                placeholder="Omschrijving (bijv. Correctie of Korting)"
                value={draftAdjustmentDescription}
                onChange={(e) => setDraftAdjustmentDescription(e.target.value)}
                disabled={addDraftLineItem.isPending}
              />
              <input
                type="number"
                step="0.01"
                className="input md:col-span-2"
                placeholder="+/- bedrag"
                value={draftAdjustmentAmount}
                onChange={(e) => setDraftAdjustmentAmount(e.target.value)}
                disabled={addDraftLineItem.isPending}
              />
              <button
                type="button"
                onClick={handleAddDraftLineItem}
                className="btn btn-secondary md:col-span-2"
                disabled={addDraftLineItem.isPending}
              >
                {addDraftLineItem.isPending ? 'Toevoegen...' : 'Voeg regel toe'}
              </button>
            </div>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
              Gebruik een positief bedrag voor opslag en een negatief bedrag voor korting.
            </p>
          </div>
        )}
      </div>

      {/* Installment timeline */}
      {invoice.installments && invoice.installments.length > 0 && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Termijnen ({getPlanLabel(invoice.installment_plan, invoice.installment_count)})
          </h2>
          <div
            className="overflow-x-auto"
            style={{ WebkitOverflowScrolling: 'touch', touchAction: 'pan-x' }}
            data-horizontal-scroll="true"
          >
            <table className="w-full">
              <thead className="border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Termijn</th>
                  <th className="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Vervaldatum</th>
                  <th className="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">Bedrag</th>
                  <th className="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Status</th>
                  <th className="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">Betaald op</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {invoice.installments.map((inst) => (
                  <tr key={inst.number}>
                    <td className="px-4 py-3 text-gray-900 dark:text-gray-100">{inst.number}</td>
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                      {inst.due_date ? format(parseYmd(inst.due_date), 'd MMM yyyy') : '-'}
                    </td>
                    <td className="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                      {formatCurrency(inst.amount, 2)}
                    </td>
                    <td className="px-4 py-3">
                      <InstallmentStatusBadge status={inst.status} />
                    </td>
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                      {inst.paid_at ? format(new Date(inst.paid_at), 'd MMM yyyy') : '-'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Action buttons */}
      <div className="card p-6">
        <div className="flex flex-wrap gap-3">
          {/* Draft status actions */}
          {invoice.status === 'draft' && (
            <>
              {invoice.invoice_type === 'membership' && (
                <div className="w-full mb-2">
                  <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={!!invoice.disable_installments}
                      disabled={toggleInstallments.isPending}
                      onChange={(e) => toggleInstallments.mutate({ id: invoice.id, disabled: e.target.checked })}
                      className="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500"
                    />
                    Termijnbetaling uitschakelen
                  </label>
                </div>
              )}
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
              <button
                onClick={handleDelete}
                disabled={isPending}
                className="btn-secondary flex items-center gap-2 border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
              >
                {deleteInvoice.isPending ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-red-600 dark:border-red-400"></div>
                ) : (
                  <Trash2 className="w-4 h-4" />
                )}
                Verwijder factuur
              </button>
            </>
          )}

          {/* Sent/Overdue status actions */}
          {(invoice.status === 'sent' || invoice.status === 'overdue') && (
            <>
              {invoice.invoice_type === 'membership' && (
                <div className="w-full mb-2 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3">
                  <div className="flex flex-col sm:flex-row sm:items-end gap-2">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        Gezinskorting (%)
                      </label>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={familyDiscountPercentInput}
                        onChange={(e) => setFamilyDiscountPercentInput(e.target.value)}
                        className="input w-40"
                        disabled={isPending}
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        Instapkorting (%)
                      </label>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        value={entryDiscountPercentInput}
                        onChange={(e) => setEntryDiscountPercentInput(e.target.value)}
                        className="input w-40"
                        disabled={isPending}
                      />
                    </div>
                    <button
                      onClick={handleUpdateMembershipDiscount}
                      disabled={isPending}
                      className="btn-secondary flex items-center gap-2"
                    >
                      {updateMembershipDiscount.isPending ? (
                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600 dark:border-gray-400"></div>
                      ) : (
                        <RefreshCw className="w-4 h-4" />
                      )}
                      Kortingen aanpassen
                    </button>
                  </div>
                  <p className="text-xs text-gray-600 dark:text-gray-400 mt-2">
                    Alleen beschikbaar voor verstuurde/onbetaalde contributiefacturen zonder actieve termijnbetaallinks.
                  </p>
                </div>
              )}
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
