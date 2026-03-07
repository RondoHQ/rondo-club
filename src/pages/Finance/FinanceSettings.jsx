import { useState, useEffect } from 'react';
import { Loader2, AlertCircle, CheckCircle, Link2, Unlink, ExternalLink, Copy, Check, ShieldCheck, Send } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { useFinanceSettings, useUpdateFinanceSettings, useRabobankStatus, useDisconnectRabobank } from '@/hooks/useFinanceSettings';
import api, { prmApi, wpApi } from '@/api/client';
import TabButton from '@/components/TabButton';
import RichTextEditor from '@/components/RichTextEditor';
import FeeCategorySettings from '@/pages/Settings/FeeCategorySettings';
import SearchableMultiSelect from '@/components/SearchableMultiSelect';

const TABS = [
  { id: 'organization', label: 'Factuur' },
  { id: 'payment', label: 'Betaling' },
  { id: 'discipline', label: 'Tuchtzaken' },
  { id: 'contributie', label: 'Contributie' },
  { id: 'email', label: 'E-mail' },
  { id: 'membership_passes', label: 'Wallets' },
  { id: 'mollie', label: 'Mollie' },
  { id: 'rabobank', label: 'Rabobank' },
];

const EMAIL_SUB_TABS = [
  { id: 'gewone_facturen', label: 'Gewone facturen' },
  { id: 'boetes', label: 'Boetes' },
  { id: 'contributie', label: 'Contributie' },
  { id: 'termijnen', label: 'Termijnen' },
  { id: 'herinneringen', label: 'Termijnherinneringen' },
  { id: 'factuur_herinneringen', label: 'Contributieherinneringen' },
];

function createEmptyBankAccount() {
  return {
    id: `bank-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    internal_name: '',
    account_holder: '',
    iban: '',
    linked_provider: '',
  };
}

function TestEmailBlock({ templateType }) {
  const [recipient, setRecipient] = useState('');
  const [sending, setSending] = useState(false);
  const [result, setResult] = useState(null);

  const handleSend = async () => {
    if (!recipient.trim()) return;
    setSending(true);
    setResult(null);
    try {
      await api.post('/rondo/v1/finance/test-email', { template_type: templateType, recipient: recipient.trim() });
      setResult({ type: 'success', message: `Testmail verstuurd naar ${recipient.trim()}.` });
    } catch (err) {
      setResult({ type: 'error', message: err.response?.data?.message || 'Versturen mislukt.' });
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="mt-4 rounded-lg border border-cyan-200 bg-cyan-50/70 p-4 dark:border-cyan-800 dark:bg-cyan-900/20">
      <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Testmail versturen</h3>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <input
          type="email"
          className="input min-w-0 flex-1 sm:max-w-xs"
          placeholder="test@voorbeeld.nl"
          value={recipient}
          onChange={(e) => { setRecipient(e.target.value); setResult(null); }}
          disabled={sending}
        />
        <button
          type="button"
          onClick={handleSend}
          disabled={sending || !recipient.trim()}
          className="btn-secondary flex items-center justify-center gap-2 whitespace-nowrap"
        >
          {sending ? (
            <Loader2 className="w-4 h-4 animate-spin" />
          ) : (
            <Send className="w-4 h-4" />
          )}
          Verstuur
        </button>
      </div>
      {result && (
        <p className={`mt-2 text-sm ${result.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
          {result.message}
        </p>
      )}
    </div>
  );
}

function normalizeBankAccounts(accounts = []) {
  if (!Array.isArray(accounts)) {
    return [];
  }

  return accounts.map((account, index) => ({
    id: account?.id || `bank-${index}-${Math.random().toString(36).slice(2, 8)}`,
    internal_name: account?.internal_name || '',
    account_holder: account?.account_holder || '',
    iban: account?.iban || '',
    linked_provider: account?.linked_provider || '',
  }));
}

/**
 * Certificate display section for Rabobank mTLS
 */
function CertificateSection() {
  const [certificate, setCertificate] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [copied, setCopied] = useState(false);

  const loadCertificate = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await prmApi.getRabobankCertificate();
      setCertificate(response.data.certificate);
    } catch (err) {
      setError(err.response?.data?.message || 'Kon certificaat niet laden.');
    } finally {
      setLoading(false);
    }
  };

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(certificate);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Fallback for older browsers
      const textarea = document.createElement('textarea');
      textarea.value = certificate;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
      <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        mTLS Certificaat
      </h3>
      <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">
        Dit certificaat moet je toevoegen in het{' '}
        <a href="https://developer.rabobank.nl" target="_blank" rel="noopener noreferrer" className="text-electric-cyan hover:underline">
          Rabobank Developer Portal
        </a>
        {' '}onder je app &rarr; Configurations &rarr; MTLS.
      </p>

      {!certificate && !loading && (
        <button
          type="button"
          onClick={loadCertificate}
          className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium transition-colors"
        >
          <ShieldCheck className="w-4 h-4" />
          Certificaat tonen
        </button>
      )}

      {loading && (
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
          <Loader2 className="w-4 h-4 animate-spin" />
          <span>Certificaat laden...</span>
        </div>
      )}

      {error && (
        <div className="flex items-start gap-2 text-sm text-red-600 dark:text-red-400">
          <AlertCircle className="w-4 h-4 flex-shrink-0 mt-0.5" />
          <span>{error}</span>
        </div>
      )}

      {certificate && (
        <div className="space-y-2">
          <div className="relative">
            <pre className="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-xs font-mono text-gray-700 dark:text-gray-300 overflow-x-auto whitespace-pre-wrap break-all max-h-48 overflow-y-auto">
              {certificate}
            </pre>
            <button
              type="button"
              onClick={handleCopy}
              className="absolute top-2 right-2 inline-flex items-center gap-1 px-2 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
            >
              {copied ? (
                <>
                  <Check className="w-3 h-3 text-green-500" />
                  Gekopieerd
                </>
              ) : (
                <>
                  <Copy className="w-3 h-3" />
                  Kopieer
                </>
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function FinanceSettings({ initialTab = 'organization', allowedTabs = null }) {
  const { data: settings, isLoading, error } = useFinanceSettings();
  const updateMutation = useUpdateFinanceSettings();
  const { data: rabobankStatus, isLoading: rabobankLoading } = useRabobankStatus();
  const disconnectMutation = useDisconnectRabobank();
  const [searchParams, setSearchParams] = useSearchParams();
  const isAdmin = window.rondoConfig?.isAdmin || false;
  const [teams, setTeams] = useState([]);

  // Form state
  const [formData, setFormData] = useState({
    org_name: '',
    org_address: '',
    contact_email: '',
    bank_accounts: [],
    payment_term_days: 14,
    payment_clause: '',
    membership_payment_clause: '',
    email_template: '',
    membership_email_template: '',
    installment_email_template: '',
    reminder_1_email_template: '',
    reminder_2_email_template: '',
    invoice_reminder_1_email_template: '',
    invoice_reminder_2_email_template: '',
    regular_invoice_email_subject: '',
    regular_invoice_email_body: '',
    bcc_email: '',
    admin_fee: 0,
    exempt_discipline_teams: [],
    rabobank_environment: 'sandbox',
    rabobank_client_id: '',
    rabobank_client_secret: '',
    active_payment_provider: 'rabobank',
    mollie_api_key: '',
    mollie_redirect_url: '',
    membership_pass_apple_cert_attachment_id: 0,
    membership_pass_apple_cert_url: '',
    membership_pass_apple_cert_password: '',
    membership_pass_apple_pass_type_identifier: '',
    membership_pass_apple_team_identifier: '',
    membership_pass_apple_organization_name: '',
    membership_pass_google_service_account_attachment_id: 0,
    membership_pass_google_service_account_url: '',
    membership_pass_google_issuer_id: '',
    membership_pass_google_class_suffix: 'rondo_membership',
  });

  const availableTabs = Array.isArray(allowedTabs) && allowedTabs.length > 0
    ? TABS.filter((tab) => allowedTabs.includes(tab.id))
    : TABS;
  const [activeTab, setActiveTab] = useState(
    availableTabs.some((tab) => tab.id === initialTab) ? initialTab : availableTabs[0]?.id || 'organization'
  );
  const [emailSubTab, setEmailSubTab] = useState('gewone_facturen');
  const [showSuccess, setShowSuccess] = useState(false);
  const [saveError, setSaveError] = useState(null);
  const [showMembershipPassHelp, setShowMembershipPassHelp] = useState(false);

  useEffect(() => {
    if (!availableTabs.some((tab) => tab.id === activeTab)) {
      setActiveTab(availableTabs[0]?.id || 'organization');
    }
  }, [activeTab, availableTabs]);

  // Load settings into form state
  useEffect(() => {
    if (settings) {
      setFormData({
        org_name: settings.org_name || '',
        org_address: settings.org_address || '',
        contact_email: settings.contact_email || '',
        bank_accounts: normalizeBankAccounts(settings.bank_accounts || []),
        payment_term_days: settings.payment_term_days || 14,
        payment_clause: settings.payment_clause || '',
        membership_payment_clause: settings.membership_payment_clause || '',
        email_template: settings.email_template || '',
        membership_email_template: settings.membership_email_template || '',
        installment_email_template: settings.installment_email_template || '',
        reminder_1_email_template: settings.reminder_1_email_template || '',
        reminder_2_email_template: settings.reminder_2_email_template || '',
        invoice_reminder_1_email_template: settings.invoice_reminder_1_email_template || '',
        invoice_reminder_2_email_template: settings.invoice_reminder_2_email_template || '',
        regular_invoice_email_subject: settings.regular_invoice_email_subject || '',
        regular_invoice_email_body: settings.regular_invoice_email_body || '',
        bcc_email: settings.bcc_email || '',
        admin_fee: settings.admin_fee || 0,
        exempt_discipline_teams: [],
        rabobank_environment: settings.rabobank_environment || 'sandbox',
        // Don't populate credentials from API for security
        rabobank_client_id: '',
        rabobank_client_secret: '',
        active_payment_provider: settings.active_payment_provider || 'rabobank',
        // Do NOT add mollie_api_key — key is never returned by API
        mollie_api_key: '',
        mollie_redirect_url: settings.mollie_redirect_url || '',
        membership_pass_apple_cert_attachment_id: settings.membership_pass_apple_cert_attachment_id || 0,
        membership_pass_apple_cert_url: settings.membership_pass_apple_cert_url || '',
        membership_pass_apple_cert_password: '',
        membership_pass_apple_pass_type_identifier: settings.membership_pass_apple_pass_type_identifier || '',
        membership_pass_apple_team_identifier: settings.membership_pass_apple_team_identifier || '',
        membership_pass_apple_organization_name: settings.membership_pass_apple_organization_name || '',
        membership_pass_google_service_account_attachment_id: settings.membership_pass_google_service_account_attachment_id || 0,
        membership_pass_google_service_account_url: settings.membership_pass_google_service_account_url || '',
        membership_pass_google_issuer_id: settings.membership_pass_google_issuer_id || '',
        membership_pass_google_class_suffix: settings.membership_pass_google_class_suffix || 'rondo_membership',
      });
    }
  }, [settings]);

  // Load discipline exception settings + teams (admin only)
  useEffect(() => {
    if (!isAdmin) return;
    const loadDisciplineSettings = async () => {
      try {
        const [vogSettingsResponse, teamsResponse] = await Promise.all([
          prmApi.getVOGSettings(),
          wpApi.getTeams({ per_page: 100, _fields: 'id,title' }),
        ]);
        setFormData((prev) => ({
          ...prev,
          exempt_discipline_teams: vogSettingsResponse.data?.exempt_discipline_teams || [],
        }));
        setTeams(teamsResponse.data || []);
      } catch {
        // Failed silently; finance settings remain usable.
      }
    };
    loadDisciplineSettings();
  }, [isAdmin]);

  // Handle OAuth callback notification
  useEffect(() => {
    const rabobankParam = searchParams.get('rabobank');
    if (rabobankParam === 'connected') {
      // Show success banner, clear param
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 5000);
      searchParams.delete('rabobank');
      setSearchParams(searchParams, { replace: true });
    } else if (rabobankParam === 'error') {
      const errorMessage = searchParams.get('message') || 'Rabobank koppeling mislukt. Probeer het opnieuw.';
      setSaveError(errorMessage);
      searchParams.delete('rabobank');
      searchParams.delete('message');
      setSearchParams(searchParams, { replace: true });
    }
  }, [searchParams, setSearchParams]);

  const handleBankAccountChange = (accountId, key, value) => {
    setFormData((prev) => {
      const nextAccounts = (prev.bank_accounts || []).map((account) => {
        if (account.id !== accountId) {
          if (key === 'linked_provider' && value && account.linked_provider === value) {
            return { ...account, linked_provider: '' };
          }
          return account;
        }

        const nextValue = key === 'iban' ? value.toUpperCase().replace(/\s+/g, '') : value;
        return { ...account, [key]: nextValue };
      });

      return { ...prev, bank_accounts: nextAccounts };
    });
  };

  const handleAddBankAccount = () => {
    setFormData((prev) => ({
      ...prev,
      bank_accounts: [...(prev.bank_accounts || []), createEmptyBankAccount()],
    }));
  };

  const handleRemoveBankAccount = (accountId) => {
    setFormData((prev) => ({
      ...prev,
      bank_accounts: (prev.bank_accounts || []).filter((account) => account.id !== accountId),
    }));
  };

  const handleMembershipFileUpload = async (event, type) => {
    const file = event.target.files?.[0];
    if (!file) return;

    try {
      const uploadFormData = new FormData();
      uploadFormData.append('file', file);

      const response = await api.post('/wp/v2/media', uploadFormData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      if (type === 'apple') {
        setFormData(prev => ({
          ...prev,
          membership_pass_apple_cert_attachment_id: response.data.id,
          membership_pass_apple_cert_url: response.data.source_url,
        }));
      } else {
        setFormData(prev => ({
          ...prev,
          membership_pass_google_service_account_attachment_id: response.data.id,
          membership_pass_google_service_account_url: response.data.source_url,
        }));
      }
    } catch (err) {
      setSaveError(err.response?.data?.message || 'Fout bij uploaden bestand');
    } finally {
      event.target.value = '';
    }
  };

  // Handle form submission
  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaveError(null);
    setShowSuccess(false);

    try {
      // Build payload - only include credentials if they're non-empty
      const payload = {
        org_name: formData.org_name,
        org_address: formData.org_address,
        contact_email: formData.contact_email,
        bank_accounts: (formData.bank_accounts || []).map((account) => ({
          id: account.id,
          internal_name: account.internal_name,
          account_holder: account.account_holder,
          iban: account.iban,
          linked_provider: account.linked_provider,
        })),
        payment_term_days: parseInt(formData.payment_term_days, 10),
        payment_clause: formData.payment_clause,
        membership_payment_clause: formData.membership_payment_clause,
        email_template: formData.email_template,
        membership_email_template: formData.membership_email_template,
        installment_email_template: formData.installment_email_template,
        reminder_1_email_template: formData.reminder_1_email_template,
        reminder_2_email_template: formData.reminder_2_email_template,
        invoice_reminder_1_email_template: formData.invoice_reminder_1_email_template,
        invoice_reminder_2_email_template: formData.invoice_reminder_2_email_template,
        regular_invoice_email_subject: formData.regular_invoice_email_subject,
        regular_invoice_email_body: formData.regular_invoice_email_body,
        bcc_email: formData.bcc_email,
        admin_fee: parseFloat(formData.admin_fee) || 0,
        rabobank_environment: formData.rabobank_environment,
        active_payment_provider: formData.active_payment_provider,
        membership_pass_apple_cert_attachment_id: formData.membership_pass_apple_cert_attachment_id,
        membership_pass_apple_pass_type_identifier: formData.membership_pass_apple_pass_type_identifier,
        membership_pass_apple_team_identifier: formData.membership_pass_apple_team_identifier,
        membership_pass_apple_organization_name: formData.membership_pass_apple_organization_name,
        membership_pass_google_service_account_attachment_id: formData.membership_pass_google_service_account_attachment_id,
        membership_pass_google_issuer_id: formData.membership_pass_google_issuer_id,
        membership_pass_google_class_suffix: formData.membership_pass_google_class_suffix,
      };

      // Only include credentials if user entered new values
      if (formData.rabobank_client_id.trim()) {
        payload.rabobank_client_id = formData.rabobank_client_id;
      }
      if (formData.rabobank_client_secret.trim()) {
        payload.rabobank_client_secret = formData.rabobank_client_secret;
      }
      if (formData.mollie_api_key.trim()) {
        payload.mollie_api_key = formData.mollie_api_key;
      }
      if (formData.membership_pass_apple_cert_password.trim()) {
        payload.membership_pass_apple_cert_password = formData.membership_pass_apple_cert_password;
      }
      payload.mollie_redirect_url = formData.mollie_redirect_url;

      await updateMutation.mutateAsync(payload);

      // Discipline charging exceptions are stored in VOG settings (admin only).
      if (isAdmin) {
        await prmApi.updateVOGSettings({
          exempt_discipline_teams: formData.exempt_discipline_teams || [],
        });
      }

      // Show success message
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 3000);

      // Clear credential fields after save
      setFormData(prev => ({
        ...prev,
        rabobank_client_id: '',
        rabobank_client_secret: '',
        mollie_api_key: '',
        membership_pass_apple_cert_password: '',
      }));
    } catch (err) {
      setSaveError(err.response?.data?.message || 'Er is een fout opgetreden bij het opslaan.');
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="w-8 h-8 animate-spin text-electric-cyan" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="card p-6">
        <div className="flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-gray-900 dark:text-gray-100">Fout bij laden instellingen</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              {error.response?.data?.message || 'Kon instellingen niet laden.'}
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Tab Navigation */}
      <div className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
        {availableTabs.map(tab => {
          let label = tab.label;
          if (tab.id === 'mollie' && formData.active_payment_provider !== 'mollie') {
            label += ' (niet in gebruik)';
          } else if (tab.id === 'rabobank' && formData.active_payment_provider !== 'rabobank') {
            label += ' (niet in gebruik)';
          }
          return (
            <TabButton
              key={tab.id}
              label={label}
              isActive={activeTab === tab.id}
              onClick={() => setActiveTab(tab.id)}
            />
          );
        })}
      </div>

      {/* Section 1: Organization Details */}
      {activeTab === 'organization' && <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Factuurgegevens</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Deze gegevens worden op facturen vermeld.
          </p>
        </div>
        <div className="space-y-4">
          <div>
            <label htmlFor="org_name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Officiële organisatienaam
            </label>
            <input
              type="text"
              id="org_name"
              value={formData.org_name}
              onChange={(e) => setFormData(prev => ({ ...prev, org_name: e.target.value }))}
              placeholder="Naam van de vereniging"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
          </div>
          <div>
            <label htmlFor="org_address" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Adres
            </label>
            <textarea
              id="org_address"
              value={formData.org_address}
              onChange={(e) => setFormData(prev => ({ ...prev, org_address: e.target.value }))}
              placeholder="Straat, postcode, plaats"
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent resize-none"
            />
          </div>
          <div>
            <label htmlFor="contact_email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              E-mailadres - Afzender voor facturen
            </label>
            <input
              type="email"
              id="contact_email"
              value={formData.contact_email}
              onChange={(e) => setFormData(prev => ({ ...prev, contact_email: e.target.value }))}
              placeholder="financien@vereniging.nl"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
          </div>
          <div>
            <label htmlFor="bcc_email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              BCC E-mailadres
            </label>
            <input
              type="email"
              id="bcc_email"
              value={formData.bcc_email}
              onChange={(e) => setFormData(prev => ({ ...prev, bcc_email: e.target.value }))}
              placeholder="penningmeester@vereniging.nl"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Alle factuurmails worden ook naar dit adres gestuurd (bv. voor de boekhouding of penningmeester)
            </p>
          </div>
        </div>
      </div>}

      {/* Section 2: Payment Details */}
      {activeTab === 'payment' && <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Betaalgegevens</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Bankrekeningen, providerkoppeling en betalingsvoorwaarden voor facturen.
          </p>
        </div>
        <div className="space-y-4">
          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300">Bankrekeningen</h3>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Per rekening vul je een interne naam, tenaamstelling en IBAN in. Koppel maximaal één rekening aan Rabobank en maximaal één aan Mollie.
                </p>
              </div>
              <button
                type="button"
                onClick={handleAddBankAccount}
                className="inline-flex items-center gap-2 px-3 py-2 bg-electric-cyan text-white rounded-lg text-sm font-medium hover:opacity-90 transition-opacity"
              >
                Bankrekening toevoegen
              </button>
            </div>

            {(formData.bank_accounts || []).length === 0 && (
              <div className="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-4 text-sm text-gray-500 dark:text-gray-400">
                Er zijn nog geen bankrekeningen toegevoegd.
              </div>
            )}

            {(formData.bank_accounts || []).map((account, index) => {
              const isActiveProviderAccount = account.linked_provider && account.linked_provider === formData.active_payment_provider;

              return (
                <div
                  key={account.id}
                  className={`rounded-lg border p-4 space-y-4 ${isActiveProviderAccount ? 'border-electric-cyan bg-cyan-50/40 dark:bg-cyan-900/10 dark:border-cyan-700' : 'border-gray-200 dark:border-gray-700'}`}
                >
                  <div className="flex items-center justify-between gap-3">
                    <h4 className="text-sm font-medium text-gray-900 dark:text-gray-100">Rekening {index + 1}</h4>
                    <button
                      type="button"
                      onClick={() => handleRemoveBankAccount(account.id)}
                      className="text-sm text-red-600 hover:underline"
                    >
                      Verwijderen
                    </button>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Interne naam
                      </label>
                      <input
                        type="text"
                        value={account.internal_name}
                        onChange={(e) => handleBankAccountChange(account.id, 'internal_name', e.target.value)}
                        placeholder="Bijv. Hoofdrekening"
                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Tenaamstelling
                      </label>
                      <input
                        type="text"
                        value={account.account_holder}
                        onChange={(e) => handleBankAccountChange(account.id, 'account_holder', e.target.value)}
                        placeholder="Naam op de factuur"
                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        IBAN
                      </label>
                      <input
                        type="text"
                        value={account.iban}
                        onChange={(e) => handleBankAccountChange(account.id, 'iban', e.target.value)}
                        placeholder="NL00RABO0000000000"
                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Gekoppelde provider
                      </label>
                      <select
                        value={account.linked_provider}
                        onChange={(e) => handleBankAccountChange(account.id, 'linked_provider', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                      >
                        <option value="">Geen</option>
                        <option value="rabobank">Rabobank</option>
                        <option value="mollie">Mollie</option>
                      </select>
                      {isActiveProviderAccount && (
                        <p className="mt-1 text-xs text-electric-cyan dark:text-cyan-300">
                          Dit is nu de standaardrekening voor de actieve betalingsprovider.
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
          <div>
            <label htmlFor="payment_term_days" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Betalingstermijn (dagen)
            </label>
            <input
              type="number"
              id="payment_term_days"
              value={formData.payment_term_days}
              onChange={(e) => setFormData(prev => ({ ...prev, payment_term_days: e.target.value }))}
              min="1"
              max="365"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Betalingsprovider
            </label>
            <div className="space-y-2">
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="active_payment_provider"
                  value="rabobank"
                  checked={formData.active_payment_provider === 'rabobank'}
                  onChange={(e) => setFormData(prev => ({ ...prev, active_payment_provider: e.target.value }))}
                  className="text-electric-cyan focus:ring-electric-cyan"
                />
                <span className="text-sm text-gray-700 dark:text-gray-300">Rabobank</span>
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="radio"
                  name="active_payment_provider"
                  value="mollie"
                  checked={formData.active_payment_provider === 'mollie'}
                  onChange={(e) => setFormData(prev => ({ ...prev, active_payment_provider: e.target.value }))}
                  className="text-electric-cyan focus:ring-electric-cyan"
                />
                <span className="text-sm text-gray-700 dark:text-gray-300">Mollie</span>
              </label>
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Kies welke provider wordt gebruikt voor betaallinks bij het versturen van facturen.
            </p>
          </div>
        </div>
      </div>}

      {/* Section: Discipline / Tuchtzaken */}
      {activeTab === 'discipline' && (
        <div className="card p-6">
          <div className="mb-4">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Tuchtzaken</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Instellingen voor het doorbelasten van tuchtzaken.
            </p>
          </div>
          <div className="space-y-4">
            <div>
              <label htmlFor="admin_fee" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Administratiekosten tuchtzaken
              </label>
              <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm pointer-events-none">&euro;</span>
                <input
                  type="number"
                  id="admin_fee"
                  value={formData.admin_fee}
                  onChange={(e) => setFormData(prev => ({ ...prev, admin_fee: e.target.value }))}
                  min="0"
                  step="0.01"
                  className="w-full pl-7 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                />
              </div>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Vaste administratiekosten per factuur. Wordt automatisch als aparte regelpost toegevoegd. Gebruik 0 om uit te schakelen.
              </p>
            </div>
            <div>
              <label htmlFor="payment_clause" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Betalingsclausule tuchtzaken
              </label>
              <textarea
                id="payment_clause"
                value={formData.payment_clause}
                onChange={(e) => setFormData(prev => ({ ...prev, payment_clause: e.target.value }))}
                placeholder="Tekst die onderaan de tuchtzaakfactuur wordt getoond over de betalingsvoorwaarden"
                rows={3}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent resize-none"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Teams met doorbelasting-uitzondering
              </label>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
                Voor deze teams krijgen tuchtzaken de status &quot;Uitzondering&quot; en worden ze niet doorberekend.
              </p>
              {isAdmin ? (
                <SearchableMultiSelect
                  options={teams.map(t => ({ id: t.id, label: t.title?.rendered || t.title }))}
                  selectedIds={formData.exempt_discipline_teams || []}
                  onChange={(newIds) => setFormData(prev => ({ ...prev, exempt_discipline_teams: newIds }))}
                  placeholder="Team zoeken..."
                  emptyMessage="Geen teams gevonden"
                />
              ) : (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  Alleen beheerders kunnen deze uitzondering aanpassen.
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Section 3: Email Templates */}
      {activeTab === 'email' && (
        <div className="space-y-4">
          {/* Sub-tab navigation */}
          <div className="flex gap-2 flex-wrap">
            {EMAIL_SUB_TABS.map(sub => (
              <button
                key={sub.id}
                type="button"
                onClick={() => setEmailSubTab(sub.id)}
                className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                  emailSubTab === sub.id
                    ? 'bg-electric-cyan text-white'
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                }`}
              >
                {sub.label}
              </button>
            ))}
          </div>

          {/* Boetes template */}
          {emailSubTab === 'gewone_facturen' && (
            <div className="card p-6">
              <div className="mb-4">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Standaard e-mail voor gewone facturen</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Wordt gebruikt voor handmatige facturen (niet boetes, niet contributie), tenzij je op de factuur zelf een override invult.
                </p>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Onderwerp
                  </label>
                  <input
                    type="text"
                    value={formData.regular_invoice_email_subject}
                    onChange={(e) => setFormData(prev => ({ ...prev, regular_invoice_email_subject: e.target.value }))}
                    placeholder="Factuur {factuur_nummer} - {organisatie_naam}"
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Tekst body
                  </label>
                  <RichTextEditor
                    value={formData.regular_invoice_email_body}
                    onChange={(html) => setFormData(prev => ({ ...prev, regular_invoice_email_body: html }))}
                    placeholder="Beste {naam},"
                  />
                </div>
                <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                  <p className="font-semibold mb-2">Beschikbare variabelen:</p>
                  <div className="space-y-1 font-mono">
                    <div><code>{'{naam}'}</code> - Volledige naam van de ontvanger</div>
                    <div><code>{'{voornaam}'}</code> - Voornaam van de ontvanger</div>
                    <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
                    <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
                    <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
                    <div><code>{'{betaalknop}'}</code> - Betaalknop</div>
                    <div><code>{'{qr_code}'}</code> - QR code voor de betaallink</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <TestEmailBlock templateType="regular_invoice" />
              </div>
            </div>
          )}

          {/* Boetes template */}
          {emailSubTab === 'boetes' && (
            <div className="card p-6">
              <div className="mb-4">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Template e-mail voor boetes</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Template voor de e-mail waarmee boete-facturen worden verstuurd.
                </p>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    E-mailtekst
                  </label>
                  <RichTextEditor
                    value={formData.email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, email_template: html }))}
                    placeholder="Schrijf hier het e-mail template..."
                    minHeight="200px"
                  />
                </div>
                <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                  <p className="font-semibold mb-2">Beschikbare variabelen:</p>
                  <div className="space-y-1 font-mono">
                    <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
                    <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
                    <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
                    <div><code>{'{tuchtzaken_lijst}'}</code> - Overzicht van tuchtzaken</div>
                    <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
                    <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
                    <div><code>{'{betaalknop}'}</code> - Betaalknop</div>
                    <div><code>{'{qr_code}'}</code> - QR-code afbeelding (betaallink)</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
                </p>
                <TestEmailBlock templateType="discipline" />
              </div>
            </div>
          )}

          {/* Contributie template */}
          {emailSubTab === 'contributie' && (
            <div className="card p-6">
              <div className="mb-4">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Template e-mail voor contributie</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Template voor de e-mail waarmee contributiefacturen worden verstuurd.
                </p>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    E-mailtekst
                  </label>
                  <RichTextEditor
                    value={formData.membership_email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, membership_email_template: html }))}
                    placeholder="Schrijf hier het e-mail template voor contributie..."
                    minHeight="200px"
                  />
                </div>
                <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                  <p className="font-semibold mb-2">Beschikbare variabelen:</p>
                  <div className="space-y-1 font-mono">
                    <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
                    <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
                    <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
                    <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
                    <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
                    <div><code>{'{betaalknop}'}</code> - Betaalknop</div>
                    <div><code>{'{qr_code}'}</code> - QR-code afbeelding (betaallink)</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
                </p>
                <TestEmailBlock templateType="membership" />
              </div>
            </div>
          )}

          {/* Termijnen template */}
          {emailSubTab === 'termijnen' && (
            <div className="card p-6">
              <div className="mb-4">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Template termijnbetaling</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Dit template wordt verstuurd bij elke nieuwe termijnbetaling.
                </p>
              </div>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    E-mailtekst
                  </label>
                  <RichTextEditor
                    value={formData.installment_email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, installment_email_template: html }))}
                    placeholder="Schrijf hier het template voor termijnbetalingen..."
                    minHeight="200px"
                  />
                </div>
                <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                  <p className="font-semibold mb-2">Beschikbare variabelen:</p>
                  <div className="space-y-1 font-mono">
                    <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
                    <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
                    <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
                    <div><code>{'{termijn_nummer}'}</code> - Huidig termijnnummer</div>
                    <div><code>{'{totaal_termijnen}'}</code> - Totaal aantal termijnen</div>
                    <div><code>{'{termijn_bedrag}'}</code> - Bedrag van deze termijn</div>
                    <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
                    <div><code>{'{betaalknop}'}</code> - Betaalknop</div>
                    <div><code>{'{vervaldatum}'}</code> - Vervaldatum van de termijn</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
                </p>
                <TestEmailBlock templateType="installment" />
              </div>
            </div>
          )}

          {/* Termijnherinneringen templates */}
          {emailSubTab === 'herinneringen' && (
            <div className="card p-6">
              <div className="mb-6">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Template e-mail voor herinneringen</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Templates voor herinneringen bij achterstallige termijnen.
                </p>
              </div>

              {/* Reminder 1 */}
              <div className="space-y-4 mb-8">
                <div>
                  <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Template eerste herinnering</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Dit template wordt verstuurd 14 dagen na de vervaldatum.
                  </p>
                  <RichTextEditor
                    value={formData.reminder_1_email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, reminder_1_email_template: html }))}
                    placeholder="Schrijf hier het template voor de eerste herinnering..."
                    minHeight="200px"
                  />
                  <TestEmailBlock templateType="reminder_1" />
                </div>
              </div>

              {/* Reminder 2 */}
              <div className="space-y-4 mb-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div>
                  <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Template tweede herinnering</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Dit template wordt verstuurd 21 dagen na de vervaldatum. De penningmeester ontvangt een BCC.
                  </p>
                  <RichTextEditor
                    value={formData.reminder_2_email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, reminder_2_email_template: html }))}
                    placeholder="Schrijf hier het template voor de tweede herinnering..."
                    minHeight="200px"
                  />
                  <TestEmailBlock templateType="reminder_2" />
                </div>
              </div>

              <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                <p className="font-semibold mb-2">Beschikbare variabelen:</p>
                <div className="space-y-1 font-mono">
                  <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
                  <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
                  <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
                  <div><code>{'{termijn_nummer}'}</code> - Huidig termijnnummer</div>
                  <div><code>{'{totaal_termijnen}'}</code> - Totaal aantal termijnen</div>
                  <div><code>{'{termijn_bedrag}'}</code> - Bedrag van deze termijn</div>
                  <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
                  <div><code>{'{betaalknop}'}</code> - Betaalknop</div>
                  <div><code>{'{vervaldatum}'}</code> - Vervaldatum van de termijn</div>
                  <div><code>{'{dagen_te_laat}'}</code> - Aantal dagen te laat</div>
                  <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                </div>
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
              </p>
            </div>
          )}

          {/* Factuurherinneringen templates */}
          {emailSubTab === 'factuur_herinneringen' && (
            <div className="card p-6">
              <div className="mb-6">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Herinneringen voor facturen zonder betaalplan</h2>
                <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Deze herinneringen worden automatisch verstuurd aan leden die hun contributiefactuur hebben ontvangen maar nog geen betaalwijze hebben gekozen.
                </p>
              </div>

              {/* Reminder 1 — 14 days */}
              <div className="space-y-4 mb-8">
                <div>
                  <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Eerste herinnering</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Wordt verstuurd 2 weken na de factuurdatum.
                  </p>
                  <RichTextEditor
                    value={formData.invoice_reminder_1_email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, invoice_reminder_1_email_template: html }))}
                    placeholder="Schrijf hier het template voor de eerste factuurherinnering..."
                    minHeight="200px"
                  />
                  <TestEmailBlock templateType="invoice_reminder_1" />
                </div>
              </div>

              {/* Reminder 2 — 28 days */}
              <div className="space-y-4 mb-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div>
                  <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Tweede herinnering</h3>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Wordt verstuurd 4 weken na de factuurdatum. De penningmeester ontvangt een BCC.
                  </p>
                  <RichTextEditor
                    value={formData.invoice_reminder_2_email_template}
                    onChange={(html) => setFormData(prev => ({ ...prev, invoice_reminder_2_email_template: html }))}
                    placeholder="Schrijf hier het template voor de tweede factuurherinnering..."
                    minHeight="200px"
                  />
                  <TestEmailBlock templateType="invoice_reminder_2" />
                </div>
              </div>

              {/* Available variables */}
              <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                <p className="font-semibold mb-2">Beschikbare variabelen:</p>
                <div className="space-y-1 font-mono">
                  <div><code>{'{naam}'}</code> - Volledige naam van het lid</div>
                  <div><code>{'{voornaam}'}</code> - Voornaam van het lid</div>
                  <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
                  <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
                  <div><code>{'{betaallink}'}</code> - Link naar betaalpagina</div>
                  <div><code>{'{betaalknop}'}</code> - Betaalknop</div>
                  <div><code>{'{qr_code}'}</code> - QR code voor de betaallink</div>
                  <div><code>{'{factuurdatum}'}</code> - Datum van de originele factuur</div>
                  <div><code>{'{dagen_sinds_factuur}'}</code> - Aantal dagen sinds factuurdatum</div>
                  <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                </div>
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
              </p>
            </div>
          )}
        </div>
      )}

      {/* Section 4: Rabobank Integration */}
      {activeTab === 'rabobank' && <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Rabobank Koppeling</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            API-gegevens voor het aanmaken van betaalverzoeken via Rabobank.
          </p>
        </div>

        <div className="space-y-6">
          {/* Part A: Connection Status & Action */}
          <div>
            <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Koppeling Status</h3>

            {rabobankLoading ? (
              <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <Loader2 className="w-4 h-4 animate-spin" />
                <span>Status wordt geladen...</span>
              </div>
            ) : rabobankStatus?.connected ? (
              <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-3">
                    <Link2 className="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                    <div>
                      <div className="flex items-center gap-2">
                        <div className="flex items-center gap-2">
                          <div className="w-2 h-2 rounded-full bg-green-500"></div>
                          <span className="font-medium text-green-900 dark:text-green-100">Gekoppeld</span>
                        </div>
                        <span className="text-sm text-green-700 dark:text-green-300">
                          ({rabobankStatus.environment === 'sandbox' ? 'Sandbox' : 'Productie'})
                        </span>
                      </div>
                      <p className="text-sm text-green-700 dark:text-green-300 mt-1">
                        Rabobank betaalverzoeken kunnen worden aangemaakt
                      </p>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={async () => {
                      if (window.confirm('Weet je zeker dat je Rabobank wilt ontkoppelen?')) {
                        try {
                          await disconnectMutation.mutateAsync();
                          setShowSuccess(true);
                          setTimeout(() => setShowSuccess(false), 3000);
                        } catch (err) {
                          setSaveError(err.response?.data?.message || 'Fout bij ontkoppelen');
                        }
                      }
                    }}
                    disabled={disconnectMutation.isPending}
                    className="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                  >
                    {disconnectMutation.isPending ? (
                      <Loader2 className="w-4 h-4 animate-spin" />
                    ) : (
                      <Unlink className="w-4 h-4" />
                    )}
                    Ontkoppelen
                  </button>
                </div>
              </div>
            ) : (
              <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-3">
                    <AlertCircle className="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                    <div>
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 rounded-full bg-yellow-500"></div>
                        <span className="font-medium text-yellow-900 dark:text-yellow-100">Niet gekoppeld</span>
                      </div>
                      <p className="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                        Koppel Rabobank om betaalverzoeken te kunnen versturen
                      </p>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        const response = await prmApi.getRabobankAuthorizeUrl();
                        window.location.href = response.data.authorize_url;
                      } catch (err) {
                        setSaveError(err.response?.data?.message || 'Fout bij ophalen autorisatie URL');
                      }
                    }}
                    disabled={!settings?.rabobank_has_credentials}
                    title={!settings?.rabobank_has_credentials ? 'Sla eerst je Client ID en Client Secret op' : ''}
                    className="inline-flex items-center gap-2 px-4 py-2 bg-electric-cyan hover:bg-electric-cyan/90 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <ExternalLink className="w-4 h-4" />
                    Koppelen met Rabobank
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Part B: mTLS Certificate */}
          <CertificateSection />

          {/* Part C: Credentials Form */}
          <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">API Credentials</h3>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Omgeving
                </label>
                <div className="space-y-2">
                  <label className="flex items-center gap-2">
                    <input
                      type="radio"
                      name="rabobank_environment"
                      value="sandbox"
                      checked={formData.rabobank_environment === 'sandbox'}
                      onChange={(e) => setFormData(prev => ({ ...prev, rabobank_environment: e.target.value }))}
                      className="text-electric-cyan focus:ring-electric-cyan"
                    />
                    <span className="text-sm text-gray-700 dark:text-gray-300">Sandbox (test)</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <input
                      type="radio"
                      name="rabobank_environment"
                      value="production"
                      checked={formData.rabobank_environment === 'production'}
                      onChange={(e) => setFormData(prev => ({ ...prev, rabobank_environment: e.target.value }))}
                      className="text-electric-cyan focus:ring-electric-cyan"
                    />
                    <span className="text-sm text-gray-700 dark:text-gray-300">Productie</span>
                  </label>
                </div>
                {formData.rabobank_environment === 'production' && (
                  <div className="mt-2 flex items-start gap-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                    <AlertCircle className="w-4 h-4 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-yellow-700 dark:text-yellow-300">
                      Let op: productieomgeving gebruikt echte betalingen
                    </p>
                  </div>
                )}
              </div>

              {settings?.rabobank_has_credentials && (
                <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                  <p className="text-sm text-green-700 dark:text-green-300">
                    Opgeslagen credentials gevonden. Laat velden leeg om huidige waarden te behouden.
                  </p>
                </div>
              )}

              <div>
                <label htmlFor="rabobank_client_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Client ID
                </label>
                <input
                  type="text"
                  id="rabobank_client_id"
                  value={formData.rabobank_client_id}
                  onChange={(e) => setFormData(prev => ({ ...prev, rabobank_client_id: e.target.value }))}
                  placeholder={settings?.rabobank_has_credentials ? '••••••••' : 'Rabobank Client ID'}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                />
              </div>
              <div>
                <label htmlFor="rabobank_client_secret" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Client Secret
                </label>
                <input
                  type="password"
                  id="rabobank_client_secret"
                  value={formData.rabobank_client_secret}
                  onChange={(e) => setFormData(prev => ({ ...prev, rabobank_client_secret: e.target.value }))}
                  placeholder={settings?.rabobank_has_credentials ? '••••••••' : 'Rabobank Client Secret'}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
                />
              </div>
            </div>
          </div>
        </div>
      </div>}

      {/* Section 5: Mollie Integration */}
      {activeTab === 'mollie' && <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Mollie Koppeling</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            API-sleutel voor betalingen via Mollie.
          </p>
        </div>

        <div className="space-y-4">
          {settings?.mollie_has_api_key && (
            <div className="flex items-center gap-2">
              <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Omgeving:</span>
              {settings.mollie_environment === 'live' ? (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                  Live
                </span>
              ) : (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                  Test
                </span>
              )}
            </div>
          )}

          {settings?.mollie_has_api_key && (
            <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
              <p className="text-sm text-green-700 dark:text-green-300">
                API-sleutel opgeslagen. Laat het veld leeg om de huidige waarde te behouden.
              </p>
            </div>
          )}

          <div>
            <label htmlFor="mollie_api_key" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              API-sleutel
            </label>
            <input
              type="password"
              id="mollie_api_key"
              value={formData.mollie_api_key}
              onChange={(e) => setFormData(prev => ({ ...prev, mollie_api_key: e.target.value }))}
              placeholder={settings?.mollie_has_api_key ? '••••••••' : 'live_... of test_...'}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Gebruik een <code>live_</code> sleutel voor productie of <code>test_</code> voor sandbox. De omgeving wordt automatisch afgeleid.
            </p>
          </div>

          <div>
            <label htmlFor="mollie_redirect_url" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Redirect URL na betaling
            </label>
            <input
              type="url"
              id="mollie_redirect_url"
              value={formData.mollie_redirect_url}
              onChange={(e) => setFormData(prev => ({ ...prev, mollie_redirect_url: e.target.value }))}
              placeholder="https://www.svawc.nl/bedankt"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              De pagina waar de betaler naartoe wordt gestuurd na de betaling. Laat leeg om naar de homepagina te verwijzen.
            </p>
          </div>
        </div>
      </div>}

      {activeTab === 'membership_passes' && <div className="card p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Wallets</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Upload certificaten en service-account bestanden voor Apple Wallet en Google Wallet.
            </p>
          </div>
          <button
            type="button"
            onClick={() => setShowMembershipPassHelp(true)}
            className="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            Hulp bij instellen
          </button>
        </div>

        <div className="space-y-8">
          <div className="space-y-4">
            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Apple Wallet</h3>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                P12 certificaat
              </label>
              {formData.membership_pass_apple_cert_url && (
                <div className="mb-2 text-sm text-gray-600 dark:text-gray-400">
                  Huidig bestand: {formData.membership_pass_apple_cert_url.split('/').pop()}
                </div>
              )}
              <input
                type="file"
                accept=".p12,application/x-pkcs12"
                onChange={(e) => handleMembershipFileUpload(e, 'apple')}
                className="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-electric-cyan file:text-white hover:file:bg-electric-cyan/90 file:cursor-pointer"
              />
            </div>
            <div>
              <label htmlFor="membership_pass_apple_cert_password" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Certificaat wachtwoord
              </label>
              <input
                type="password"
                id="membership_pass_apple_cert_password"
                value={formData.membership_pass_apple_cert_password}
                onChange={(e) => setFormData(prev => ({ ...prev, membership_pass_apple_cert_password: e.target.value }))}
                placeholder={settings?.membership_pass_apple_has_cert_password ? '••••••••' : 'Voer wachtwoord in'}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
            </div>
            <div>
              <label htmlFor="membership_pass_apple_pass_type_identifier" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Pass Type Identifier
              </label>
              <input
                type="text"
                id="membership_pass_apple_pass_type_identifier"
                value={formData.membership_pass_apple_pass_type_identifier}
                onChange={(e) => setFormData(prev => ({ ...prev, membership_pass_apple_pass_type_identifier: e.target.value }))}
                placeholder="pass.nl.voorbeeld.rondo.membership"
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
            </div>
            <div>
              <label htmlFor="membership_pass_apple_team_identifier" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Apple Team Identifier
              </label>
              <input
                type="text"
                id="membership_pass_apple_team_identifier"
                value={formData.membership_pass_apple_team_identifier}
                onChange={(e) => setFormData(prev => ({ ...prev, membership_pass_apple_team_identifier: e.target.value }))}
                placeholder="XXXXXXXXXX"
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
            </div>
            <div>
              <label htmlFor="membership_pass_apple_organization_name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Organisatienaam op pas
              </label>
              <input
                type="text"
                id="membership_pass_apple_organization_name"
                value={formData.membership_pass_apple_organization_name}
                onChange={(e) => setFormData(prev => ({ ...prev, membership_pass_apple_organization_name: e.target.value }))}
                placeholder="Rondo"
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
            </div>
          </div>

          <div className="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">Google Wallet</h3>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Service Account JSON
              </label>
              {formData.membership_pass_google_service_account_url && (
                <div className="mb-2 text-sm text-gray-600 dark:text-gray-400">
                  Huidig bestand: {formData.membership_pass_google_service_account_url.split('/').pop()}
                </div>
              )}
              <input
                type="file"
                accept=".json,application/json"
                onChange={(e) => handleMembershipFileUpload(e, 'google')}
                className="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-electric-cyan file:text-white hover:file:bg-electric-cyan/90 file:cursor-pointer"
              />
            </div>
            <div>
              <label htmlFor="membership_pass_google_issuer_id" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Google Wallet Issuer ID
              </label>
              <input
                type="text"
                id="membership_pass_google_issuer_id"
                value={formData.membership_pass_google_issuer_id}
                onChange={(e) => setFormData(prev => ({ ...prev, membership_pass_google_issuer_id: e.target.value }))}
                placeholder="3388000000022222222"
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
            </div>
            <div>
              <label htmlFor="membership_pass_google_class_suffix" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Class suffix
              </label>
              <input
                type="text"
                id="membership_pass_google_class_suffix"
                value={formData.membership_pass_google_class_suffix}
                onChange={(e) => setFormData(prev => ({ ...prev, membership_pass_google_class_suffix: e.target.value }))}
                placeholder="rondo_membership"
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Alleen kleine letters, cijfers en underscores.
              </p>
            </div>
          </div>
        </div>
      </div>}

      {showMembershipPassHelp && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
          <div className="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Wallets instellen</h3>
              <button
                type="button"
                onClick={() => setShowMembershipPassHelp(false)}
                className="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white"
              >
                Sluiten
              </button>
            </div>

            <div className="px-6 py-5 space-y-6 text-sm text-gray-700 dark:text-gray-300">
              <div>
                <h4 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">Google Wallet</h4>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>Open Google Cloud Console en kies/projecteer je project.</li>
                  <li>Activeer de Google Wallet API.</li>
                  <li>Ga naar IAM &amp; Admin → Service Accounts en maak een service account.</li>
                  <li>Open dat service account → Keys → Add key → Create new key → JSON.</li>
                  <li>Upload dit JSON-bestand bij &quot;Service Account JSON&quot;.</li>
                  <li>Open Google Pay &amp; Wallet Console en activeer je issuer-account.</li>
                  <li>Geef het service account toegang in de Wallet Console.</li>
                  <li>Kopieer daar je numerieke Issuer ID en vul die hier in.</li>
                </ol>
                <div className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  Class suffix mag alleen kleine letters, cijfers en underscores bevatten.
                </div>
              </div>

              <div>
                <h4 className="font-semibold text-gray-900 dark:text-gray-100 mb-2">Apple Wallet</h4>
                <ol className="list-decimal pl-5 space-y-1">
                  <li>Log in op Apple Developer (Certificates, Identifiers &amp; Profiles).</li>
                  <li>Maak een Pass Type ID aan (bijv. <code>pass.nl.jouwdomein.rondo.membership</code>).</li>
                  <li>Maak daarna een Pass Type Certificate voor die Pass Type ID.</li>
                  <li>Download het certificaat en importeer het in Keychain Access (macOS).</li>
                  <li>Exporteer in Keychain het certificaat mét private key als <code>.p12</code>.</li>
                  <li>Gebruik bij export een wachtwoord en vul dat hier in bij &quot;Certificaat wachtwoord&quot;.</li>
                  <li>Upload het <code>.p12</code>-bestand bij &quot;P12 certificaat&quot;.</li>
                  <li>Vul Team Identifier in (10 tekens, te vinden in Apple Developer account).</li>
                  <li>Vul exact dezelfde Pass Type Identifier in als bij stap 2.</li>
                  <li>Organisatienaam is de naam die op de Wallet-pass zichtbaar wordt.</li>
                </ol>
              </div>

              <div className="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-3 text-xs text-gray-600 dark:text-gray-400">
                Tip: sla eerst op, open daarna een ledenpas-link (<code>/lidpas/{'{token}'}</code>) en test beide knoppen.
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Section 6: Fee Category Settings */}
      {activeTab === 'contributie' && (
        <FeeCategorySettings
          membershipPaymentClause={formData.membership_payment_clause}
          onMembershipPaymentClauseChange={(value) => setFormData(prev => ({ ...prev, membership_payment_clause: value }))}
          onSaveMembershipPaymentClause={async () => {
            setSaveError(null);
            setShowSuccess(false);
            try {
              await updateMutation.mutateAsync({
                membership_payment_clause: formData.membership_payment_clause,
              });
              setShowSuccess(true);
              setTimeout(() => setShowSuccess(false), 3000);
            } catch (err) {
              setSaveError(err.response?.data?.message || 'Er is een fout opgetreden bij het opslaan.');
            }
          }}
          savingMembershipPaymentClause={updateMutation.isPending}
        />
      )}

      {/* Error message */}
      {saveError && (
        <div className="card p-4 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
          <div className="flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="font-semibold text-red-900 dark:text-red-100">Fout bij opslaan</h3>
              <p className="text-sm text-red-700 dark:text-red-300 mt-1">{saveError}</p>
            </div>
          </div>
        </div>
      )}

      {/* Success message */}
      {showSuccess && (
        <div className="card p-4 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
          <div className="flex items-center gap-3">
            <CheckCircle className="w-5 h-5 text-green-500 flex-shrink-0" />
            <p className="text-sm font-medium text-green-700 dark:text-green-300">
              Instellingen opgeslagen
            </p>
          </div>
        </div>
      )}

      {/* Save button — hidden on contributie tab (FeeCategorySettings manages its own saves) */}
      {activeTab !== 'contributie' && (
        <div className="flex justify-end">
          <button
            type="submit"
            disabled={updateMutation.isPending}
            className="inline-flex items-center gap-2 px-6 py-2.5 bg-electric-cyan hover:bg-electric-cyan/90 text-white font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {updateMutation.isPending && <Loader2 className="w-4 h-4 animate-spin" />}
            Opslaan
          </button>
        </div>
      )}
    </form>
  );
}
