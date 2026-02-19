import { useState, useEffect } from 'react';
import { Loader2, AlertCircle, CheckCircle, Link2, Unlink, ExternalLink, Copy, Check, ShieldCheck } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { useFinanceSettings, useUpdateFinanceSettings, useRabobankStatus, useDisconnectRabobank } from '@/hooks/useFinanceSettings';
import api, { prmApi } from '@/api/client';
import TabButton from '@/components/TabButton';
import RichTextEditor from '@/components/RichTextEditor';
import FeeCategorySettings from '@/pages/Settings/FeeCategorySettings';

const TABS = [
  { id: 'organization', label: 'Organisatie' },
  { id: 'payment', label: 'Betaling' },
  { id: 'contributie', label: 'Contributie' },
  { id: 'email', label: 'E-mail' },
  { id: 'mollie', label: 'Mollie' },
  { id: 'rabobank', label: 'Rabobank' },
];

const EMAIL_SUB_TABS = [
  { id: 'boetes', label: 'Boetes' },
  { id: 'contributie', label: 'Contributie' },
  { id: 'termijnen', label: 'Termijnen' },
  { id: 'herinneringen', label: 'Herinneringen' },
];

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

export default function FinanceSettings() {
  const { data: settings, isLoading, error } = useFinanceSettings();
  const updateMutation = useUpdateFinanceSettings();
  const { data: rabobankStatus, isLoading: rabobankLoading } = useRabobankStatus();
  const disconnectMutation = useDisconnectRabobank();
  const [searchParams, setSearchParams] = useSearchParams();

  // Form state
  const [formData, setFormData] = useState({
    org_name: '',
    org_address: '',
    contact_email: '',
    iban: '',
    payment_term_days: 14,
    payment_clause: '',
    membership_payment_clause: '',
    email_template: '',
    membership_email_template: '',
    installment_email_template: '',
    reminder_1_email_template: '',
    reminder_2_email_template: '',
    club_logo_id: 0,
    club_logo_url: '',
    accent_color: '',
    bcc_email: '',
    admin_fee: 0,
    installment_admin_fee: 0,
    rabobank_environment: 'sandbox',
    rabobank_client_id: '',
    rabobank_client_secret: '',
    active_payment_provider: 'rabobank',
    mollie_api_key: '',
    mollie_redirect_url: '',
  });

  const [activeTab, setActiveTab] = useState('organization');
  const [emailSubTab, setEmailSubTab] = useState('boetes');
  const [showSuccess, setShowSuccess] = useState(false);
  const [saveError, setSaveError] = useState(null);

  // Load settings into form state
  useEffect(() => {
    if (settings) {
      setFormData({
        org_name: settings.org_name || '',
        org_address: settings.org_address || '',
        contact_email: settings.contact_email || '',
        iban: settings.iban || '',
        payment_term_days: settings.payment_term_days || 14,
        payment_clause: settings.payment_clause || '',
        membership_payment_clause: settings.membership_payment_clause || '',
        email_template: settings.email_template || '',
        membership_email_template: settings.membership_email_template || '',
        installment_email_template: settings.installment_email_template || '',
        reminder_1_email_template: settings.reminder_1_email_template || '',
        reminder_2_email_template: settings.reminder_2_email_template || '',
        club_logo_id: settings.club_logo_id || 0,
        club_logo_url: settings.club_logo_url || '',
        accent_color: settings.accent_color || '',
        bcc_email: settings.bcc_email || '',
        admin_fee: settings.admin_fee || 0,
        installment_admin_fee: settings.installment_admin_fee || 0,
        rabobank_environment: settings.rabobank_environment || 'sandbox',
        // Don't populate credentials from API for security
        rabobank_client_id: '',
        rabobank_client_secret: '',
        active_payment_provider: settings.active_payment_provider || 'rabobank',
        // Do NOT add mollie_api_key — key is never returned by API
        mollie_api_key: '',
        mollie_redirect_url: settings.mollie_redirect_url || '',
      });
    }
  }, [settings]);

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

  // Format IBAN on blur
  const handleIbanBlur = () => {
    const formatted = formData.iban.toUpperCase().replace(/\s+/g, '');
    setFormData(prev => ({ ...prev, iban: formatted }));
  };

  // Handle logo upload
  const handleLogoUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    try {
      const uploadFormData = new FormData();
      uploadFormData.append('file', file);

      const response = await api.post('/wp/v2/media', uploadFormData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setFormData(prev => ({
        ...prev,
        club_logo_id: response.data.id,
        club_logo_url: response.data.source_url,
      }));
    } catch (err) {
      setSaveError(err.response?.data?.message || 'Fout bij uploaden logo');
    }
  };

  // Remove logo
  const handleLogoRemove = () => {
    setFormData(prev => ({
      ...prev,
      club_logo_id: 0,
      club_logo_url: '',
    }));
  };

  // Reset accent color to default
  const handleResetAccentColor = () => {
    setFormData(prev => ({ ...prev, accent_color: '' }));
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
        iban: formData.iban,
        payment_term_days: parseInt(formData.payment_term_days, 10),
        payment_clause: formData.payment_clause,
        membership_payment_clause: formData.membership_payment_clause,
        email_template: formData.email_template,
        membership_email_template: formData.membership_email_template,
        installment_email_template: formData.installment_email_template,
        reminder_1_email_template: formData.reminder_1_email_template,
        reminder_2_email_template: formData.reminder_2_email_template,
        club_logo_id: formData.club_logo_id,
        accent_color: formData.accent_color,
        bcc_email: formData.bcc_email,
        admin_fee: parseFloat(formData.admin_fee) || 0,
        installment_admin_fee: parseFloat(formData.installment_admin_fee) || 0,
        rabobank_environment: formData.rabobank_environment,
        active_payment_provider: formData.active_payment_provider,
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
      payload.mollie_redirect_url = formData.mollie_redirect_url;

      await updateMutation.mutateAsync(payload);

      // Show success message
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 3000);

      // Clear credential fields after save
      setFormData(prev => ({
        ...prev,
        rabobank_client_id: '',
        rabobank_client_secret: '',
        mollie_api_key: '',
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
        {TABS.map(tab => {
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
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Organisatiegegevens</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Deze gegevens worden op facturen vermeld.
          </p>
        </div>
        <div className="space-y-4">
          <div>
            <label htmlFor="org_name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Organisatienaam
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
              E-mailadres
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
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Clublogo
            </label>
            {formData.club_logo_url ? (
              <div className="flex items-center gap-3">
                <img
                  src={formData.club_logo_url}
                  alt="Club logo"
                  className="max-h-[60px] object-contain"
                />
                <button
                  type="button"
                  onClick={handleLogoRemove}
                  className="text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                >
                  Verwijderen
                </button>
              </div>
            ) : null}
            <input
              type="file"
              accept="image/*"
              onChange={handleLogoUpload}
              className="mt-2 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-electric-cyan file:text-white hover:file:bg-electric-cyan/90 file:cursor-pointer"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Logo wordt getoond op facturen. Kies een afbeelding met transparante achtergrond voor het beste resultaat.
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Accentkleur
            </label>
            <div className="flex items-center gap-3">
              <input
                type="color"
                value={formData.accent_color || '#0891b2'}
                onChange={(e) => setFormData(prev => ({ ...prev, accent_color: e.target.value }))}
                className="h-10 w-20 cursor-pointer border border-gray-300 dark:border-gray-600 rounded-lg"
              />
              <input
                type="text"
                value={formData.accent_color || '#0891b2'}
                onChange={(e) => setFormData(prev => ({ ...prev, accent_color: e.target.value }))}
                placeholder="#0891b2"
                className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent font-mono text-sm"
              />
              <button
                type="button"
                onClick={handleResetAccentColor}
                className="text-sm text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap"
              >
                Reset naar standaard
              </button>
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Deze kleur wordt gebruikt voor koppen en lijnen op facturen. Standaard: #0891b2 (electric cyan).
            </p>
          </div>
        </div>
      </div>}

      {/* Section 2: Payment Details */}
      {activeTab === 'payment' && <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Betaalgegevens</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Bankrekening en betalingsvoorwaarden voor facturen.
          </p>
        </div>
        <div className="space-y-4">
          <div>
            <label htmlFor="iban" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              IBAN
            </label>
            <input
              type="text"
              id="iban"
              value={formData.iban}
              onChange={(e) => setFormData(prev => ({ ...prev, iban: e.target.value }))}
              onBlur={handleIbanBlur}
              placeholder="NL00 RABO 0000 0000 00"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
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
            <label htmlFor="installment_admin_fee" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Administratiekosten termijnbetaling
            </label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm pointer-events-none">&euro;</span>
              <input
                type="number"
                id="installment_admin_fee"
                value={formData.installment_admin_fee}
                onChange={(e) => setFormData(prev => ({ ...prev, installment_admin_fee: e.target.value }))}
                min="0"
                step="0.01"
                className="w-full pl-7 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
              />
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Extra administratiekosten per termijn bij betaling in meerdere termijnen. Gebruik 0 om uit te schakelen.
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
            <label htmlFor="membership_payment_clause" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Betalingsclausule contributie
            </label>
            <textarea
              id="membership_payment_clause"
              value={formData.membership_payment_clause}
              onChange={(e) => setFormData(prev => ({ ...prev, membership_payment_clause: e.target.value }))}
              placeholder="Tekst die onderaan de contributiefactuur wordt getoond"
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent resize-none"
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
                    <div><code>{'{qr_code}'}</code> - QR-code afbeelding (betaallink)</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
                </p>
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
                    <div><code>{'{qr_code}'}</code> - QR-code afbeelding (betaallink)</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
                </p>
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
                    <div><code>{'{vervaldatum}'}</code> - Vervaldatum van de termijn</div>
                    <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
                  </div>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                  Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
                </p>
              </div>
            </div>
          )}

          {/* Herinneringen templates */}
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

      {/* Section 6: Fee Category Settings */}
      {activeTab === 'contributie' && <FeeCategorySettings />}

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
