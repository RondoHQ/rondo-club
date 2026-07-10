import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, ChevronDown, Plus, Search, Trash2, X } from 'lucide-react';
import { useSearch } from '@/hooks/useDashboard';
import { useFinanceSettings } from '@/hooks/useFinanceSettings';
import RichTextEditor from '@/components/RichTextEditor';

const emptyLine = { description: '', amount: '', discipline_case_id: null };
const DEFAULT_SUBJECT_TEMPLATE = 'Factuur {factuur_nummer} - {organisatie_naam}';
const DEFAULT_BODY_TEMPLATE = `Beste {naam},

Bijgevoegd vindt u factuur {factuur_nummer}.

Het totaalbedrag is {totaal_bedrag}.
U kunt betalen via: {betaallink}

Met vriendelijke groet,
{organisatie_naam}`;

// Compare an editor value against a default template loosely. The rich text
// editor re-serializes HTML when a value is loaded, so an exact `===` check
// spuriously reports an untouched default as "customized". Stripping tags and
// collapsing whitespace lets an unedited default still match its source.
const emailBodyMatchesDefault = (a, b) => {
  const norm = (value) => (value || '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return norm(a) === norm(b);
};

const formatDateForInput = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

function getInitialDueDate(value) {
  if (value && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }
  if (value && /^\d{8}$/.test(value)) {
    return `${value.slice(0, 4)}-${value.slice(4, 6)}-${value.slice(6, 8)}`;
  }
  const defaultDueDate = new Date();
  defaultDueDate.setDate(defaultDueDate.getDate() + 14);
  return formatDateForInput(defaultDueDate);
}

function normalizeLineItems(lineItems = []) {
  if (!Array.isArray(lineItems) || lineItems.length === 0) {
    return [{ ...emptyLine }];
  }

  return lineItems.map((item) => ({
    description: item?.description || '',
    amount: item?.amount === 0 || item?.amount ? String(item.amount) : '',
    discipline_case_id: item?.discipline_case_id || item?.discipline_case?.id || null,
  }));
}

function normalizeCustomFields(customFields = []) {
  if (!Array.isArray(customFields) || customFields.length === 0) {
    return [{ label: '', text: '' }, { label: '', text: '' }];
  }

  const normalized = customFields.slice(0, 2).map((field) => ({
    label: field?.label || '',
    text: field?.text || '',
  }));

  while (normalized.length < 2) {
    normalized.push({ label: '', text: '' });
  }

  return normalized;
}

function getDefaultPaymentAccountId(financeSettings, invoiceType) {
  if (invoiceType === 'membership') {
    return financeSettings?.mollie_default_membership_account_id || '';
  }

  if (invoiceType === 'discipline') {
    return financeSettings?.mollie_default_discipline_account_id || '';
  }

  return financeSettings?.mollie_default_manual_account_id || '';
}

export default function InvoiceDraftForm({
  invoiceType = 'manual',
  initialValues,
  formKey,
  onSubmit,
  submitLabel,
  isSubmitting = false,
  error = '',
  onCancel,
  cancelLabel = 'Annuleren',
  backLink,
  backLabel,
  heading,
  description,
  previewInvoiceNumber,
}) {
  const { data: financeSettings } = useFinanceSettings();
  const [invoiceKind, setInvoiceKind] = useState('normal');
  const [invoiceTarget, setInvoiceTarget] = useState('member');
  const [customerName, setCustomerName] = useState('');
  const [customerAttention, setCustomerAttention] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');
  const [customerCcEmail, setCustomerCcEmail] = useState('');
  const [customerAddress, setCustomerAddress] = useState('');
  const [personId, setPersonId] = useState('');
  const [personSearch, setPersonSearch] = useState('');
  const [isPersonOpen, setIsPersonOpen] = useState(false);
  const [dueDate, setDueDate] = useState('');
  const [paymentAccountId, setPaymentAccountId] = useState('');
  const [emailSubject, setEmailSubject] = useState('');
  const [emailBody, setEmailBody] = useState('');
  const appliedDefaultSubjectRef = useRef('');
  const appliedDefaultBodyRef = useRef('');
  const [customFields, setCustomFields] = useState([{ label: '', text: '' }, { label: '', text: '' }]);
  const [showCustomFields, setShowCustomFields] = useState(false);
  const [lineItems, setLineItems] = useState([{ ...emptyLine }]);
  const [localError, setLocalError] = useState('');
  const personInputRef = useRef(null);
  const personDropdownRef = useRef(null);

  const searchTerm = personSearch.trim();
  const { data: searchResults } = useSearch(searchTerm);
  const memberOptions = (searchResults?.people || []).slice(0, 20);
  // Credit invoices carry their own configured email template; every other
  // kind uses the regular-invoice defaults. Picking the wrong default here is
  // what caused credit invoices to go out with regular-invoice wording.
  const defaultEmailSubject = useMemo(() => (
    invoiceKind === 'credit'
      ? (financeSettings?.credit_email_subject || DEFAULT_SUBJECT_TEMPLATE)
      : (financeSettings?.regular_invoice_email_subject || DEFAULT_SUBJECT_TEMPLATE)
  ), [financeSettings, invoiceKind]);
  const defaultEmailBody = useMemo(() => (
    invoiceKind === 'credit'
      ? (financeSettings?.credit_email_template || DEFAULT_BODY_TEMPLATE)
      : (financeSettings?.regular_invoice_email_body || DEFAULT_BODY_TEMPLATE)
  ), [financeSettings, invoiceKind]);
  const usableMollieAccounts = Array.isArray(financeSettings?.mollie_accounts)
    ? financeSettings.mollie_accounts.filter((account) => account?.has_api_key)
    : [];
  const showManualMollieAccountSelector = financeSettings?.active_payment_provider === 'mollie'
    && invoiceType === 'manual'
    && usableMollieAccounts.length > 1;

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
    setInvoiceKind(initialValues?.invoiceKind || 'normal');
    setInvoiceTarget(initialValues?.invoiceTarget || 'member');
    setCustomerName(initialValues?.customerName || '');
    setCustomerAttention(initialValues?.customerAttention || '');
    setCustomerEmail(initialValues?.customerEmail || '');
    setCustomerCcEmail(initialValues?.customerCcEmail || '');
    setCustomerAddress(initialValues?.customerAddress || '');
    setPersonId(initialValues?.personId ? String(initialValues.personId) : '');
    setPersonSearch(initialValues?.personLabel || '');
    setIsPersonOpen(false);
    setDueDate(getInitialDueDate(initialValues?.dueDate));
    setPaymentAccountId(initialValues?.paymentAccountId || '');
    setEmailSubject(initialValues?.emailSubject || '');
    setEmailBody(initialValues?.emailBody || '');
    appliedDefaultSubjectRef.current = '';
    appliedDefaultBodyRef.current = '';
    setCustomFields(normalizeCustomFields(initialValues?.customFields));
    setShowCustomFields((initialValues?.customFields || []).some((field) => field?.label || field?.text));
    setLineItems(normalizeLineItems(initialValues?.lineItems));
    setLocalError('');
  }, [formKey, initialValues]);

  useEffect(() => {
    setPaymentAccountId((current) => current || initialValues?.paymentAccountId || getDefaultPaymentAccountId(financeSettings, invoiceType));
  }, [financeSettings, initialValues?.paymentAccountId, invoiceType]);

  // Hydrate the email fields with the kind-appropriate default, and swap them
  // over when the invoice kind changes — but only while the field still holds a
  // default value, so a user's own edits are never overwritten.
  useEffect(() => {
    if (!financeSettings) {
      return;
    }

    setEmailSubject((current) => (
      current === '' || current === appliedDefaultSubjectRef.current ? defaultEmailSubject : current
    ));
    setEmailBody((current) => (
      current === '' || emailBodyMatchesDefault(current, appliedDefaultBodyRef.current) ? defaultEmailBody : current
    ));

    appliedDefaultSubjectRef.current = defaultEmailSubject;
    appliedDefaultBodyRef.current = defaultEmailBody;
  }, [financeSettings, defaultEmailSubject, defaultEmailBody]);

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

  const handleTargetChange = (nextTarget) => {
    setInvoiceTarget(nextTarget);
    setLocalError('');

    if (nextTarget === 'member') {
      setCustomerName('');
      setCustomerAttention('');
      setCustomerEmail('');
      setCustomerCcEmail('');
      setCustomerAddress('');
      return;
    }

    setPersonId('');
    setPersonSearch('');
    setIsPersonOpen(false);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLocalError('');

    const rows = lineItems
      .map((row) => ({
        description: row.description.trim(),
        amount: parseFloat(row.amount) || 0,
        discipline_case_id: row.discipline_case_id || null,
      }))
      .filter((row) => row.description || row.amount !== 0 || row.discipline_case_id);

    if (rows.length === 0) {
      setLocalError('Voeg minimaal één regel toe.');
      return;
    }

    if (invoiceTarget === 'member' && !personId) {
      setLocalError('Selecteer een lid voor deze factuur.');
      return;
    }

    if (invoiceTarget === 'external' && (!customerName.trim() || !customerAddress.trim())) {
      setLocalError('Vul klantnaam en adres in voor een externe factuur.');
      return;
    }

    await onSubmit({
      person_id: invoiceTarget === 'member' && personId ? parseInt(personId, 10) : null,
      invoice_kind: invoiceKind,
      customer_name: invoiceTarget === 'external' ? customerName : '',
      customer_attention: invoiceTarget === 'external' ? customerAttention : '',
      customer_email: invoiceTarget === 'external' ? customerEmail : '',
      customer_cc_email: invoiceTarget === 'external' ? customerCcEmail : '',
      customer_address: invoiceTarget === 'external' ? customerAddress : '',
      payment_terms_due_date: dueDate ? dueDate.replaceAll('-', '') : '',
      payment_account_id: paymentAccountId,
      email_subject: emailSubject === defaultEmailSubject ? '' : emailSubject,
      email_body_override: emailBodyMatchesDefault(emailBody, defaultEmailBody) ? '' : emailBody,
      custom_fields: customFields,
      line_items: rows,
    });
  };

  const visibleError = localError || error;

  return (
    <div className="space-y-6">
      {backLink && backLabel && (
        <Link to={backLink} className="text-electric-cyan hover:underline inline-flex items-center gap-1">
          <ArrowLeft className="w-4 h-4" /> {backLabel}
        </Link>
      )}

      {(heading || description || previewInvoiceNumber) && (
        <div className="flex items-center justify-between">
          <div>
            {heading && <h1 className="text-2xl font-semibold mt-2">{heading}</h1>}
            {description && <p className="text-sm text-gray-500 mt-1">{description}</p>}
            {previewInvoiceNumber && <p className="text-sm text-gray-500">Nummer preview: <strong>{previewInvoiceNumber}</strong></p>}
          </div>
        </div>
      )}

      <form onSubmit={handleSubmit} className="card p-6 space-y-6">
        {visibleError && <p className="text-red-600 text-sm">{visibleError}</p>}

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
                  onChange={() => handleTargetChange('member')}
                />
                <span>Lid</span>
              </label>
              <label className="inline-flex items-center gap-2 cursor-pointer">
                <input
                  type="radio"
                  name="invoice-target"
                  value="external"
                  checked={invoiceTarget === 'external'}
                  onChange={() => handleTargetChange('external')}
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
            <label className="text-sm">E-mail
              <input className="input mt-1" type="email" value={customerEmail} onChange={(e) => setCustomerEmail(e.target.value)} />
            </label>
            <label className="text-sm">CC
              <input className="input mt-1" type="email" value={customerCcEmail} onChange={(e) => setCustomerCcEmail(e.target.value)} />
            </label>
            <label className="text-sm md:col-span-2">Adres
              <textarea className="input mt-1 min-h-20" value={customerAddress} onChange={(e) => setCustomerAddress(e.target.value)} />
            </label>
          </div>
        )}

        <label className="text-sm block max-w-sm">Vervaldatum
          <input type="date" className="input mt-1" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
        </label>

        {showManualMollieAccountSelector && (
          <label className="text-sm block max-w-sm">Bankrekening
            <select className="input mt-1" value={paymentAccountId} onChange={(e) => setPaymentAccountId(e.target.value)}>
              {usableMollieAccounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.internal_name} · {account.iban}
                </option>
              ))}
            </select>
          </label>
        )}

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
              <button type="button" onClick={() => setLineItems((prev) => prev.filter((_, i) => i !== index))} className="btn-danger col-span-1" disabled={lineItems.length === 1}>
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
          <button type="button" className="btn-tertiary gap-2" onClick={() => setLineItems((prev) => [...prev, { ...emptyLine }])}>
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
            <div className="mt-1">
              <RichTextEditor value={emailBody} onChange={setEmailBody} />
            </div>
          </label>
          <p className="text-xs text-gray-500">
            Deze velden starten met de standaard uit <Link to="/settings/financieel" className="text-electric-cyan hover:underline">Financieel instellingen</Link>. Variabelen zoals <code>{'{factuur_nummer}'}</code> en <code>{'{organisatie_naam}'}</code> blijven werken.
          </p>
        </div>

        <div className="flex gap-2">
          <button type="submit" className="btn-primary" disabled={isSubmitting}>{submitLabel}</button>
          {onCancel && (
            <button type="button" className="btn-secondary" onClick={onCancel} disabled={isSubmitting}>
              {cancelLabel}
            </button>
          )}
        </div>
      </form>
    </div>
  );
}
