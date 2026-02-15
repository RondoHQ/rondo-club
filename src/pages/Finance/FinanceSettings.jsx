import { useState, useEffect } from 'react';
import { Loader2, AlertCircle, CheckCircle } from 'lucide-react';
import { useFinanceSettings, useUpdateFinanceSettings } from '@/hooks/useFinanceSettings';

export default function FinanceSettings() {
  const { data: settings, isLoading, error } = useFinanceSettings();
  const updateMutation = useUpdateFinanceSettings();

  // Form state
  const [formData, setFormData] = useState({
    org_name: '',
    org_address: '',
    org_email: '',
    iban: '',
    payment_term_days: 14,
    payment_clause: '',
    email_template: '',
    rabobank_environment: 'sandbox',
    rabobank_client_id: '',
    rabobank_client_secret: '',
  });

  const [showSuccess, setShowSuccess] = useState(false);
  const [saveError, setSaveError] = useState(null);

  // Load settings into form state
  useEffect(() => {
    if (settings) {
      setFormData({
        org_name: settings.org_name || '',
        org_address: settings.org_address || '',
        org_email: settings.org_email || '',
        iban: settings.iban || '',
        payment_term_days: settings.payment_term_days || 14,
        payment_clause: settings.payment_clause || '',
        email_template: settings.email_template || '',
        rabobank_environment: settings.rabobank_environment || 'sandbox',
        // Don't populate credentials from API for security
        rabobank_client_id: '',
        rabobank_client_secret: '',
      });
    }
  }, [settings]);

  // Format IBAN on blur
  const handleIbanBlur = () => {
    const formatted = formData.iban.toUpperCase().replace(/\s+/g, '');
    setFormData(prev => ({ ...prev, iban: formatted }));
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
        org_email: formData.org_email,
        iban: formData.iban,
        payment_term_days: parseInt(formData.payment_term_days, 10),
        payment_clause: formData.payment_clause,
        email_template: formData.email_template,
        rabobank_environment: formData.rabobank_environment,
      };

      // Only include credentials if user entered new values
      if (formData.rabobank_client_id.trim()) {
        payload.rabobank_client_id = formData.rabobank_client_id;
      }
      if (formData.rabobank_client_secret.trim()) {
        payload.rabobank_client_secret = formData.rabobank_client_secret;
      }

      await updateMutation.mutateAsync(payload);

      // Show success message
      setShowSuccess(true);
      setTimeout(() => setShowSuccess(false), 3000);

      // Clear credential fields after save
      setFormData(prev => ({
        ...prev,
        rabobank_client_id: '',
        rabobank_client_secret: '',
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
      {/* Section 1: Organization Details */}
      <div className="card p-6">
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
            <label htmlFor="org_email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              E-mailadres
            </label>
            <input
              type="email"
              id="org_email"
              value={formData.org_email}
              onChange={(e) => setFormData(prev => ({ ...prev, org_email: e.target.value }))}
              placeholder="financien@vereniging.nl"
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent"
            />
          </div>
        </div>
      </div>

      {/* Section 2: Payment Details */}
      <div className="card p-6">
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
            <label htmlFor="payment_clause" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Betalingsclausule
            </label>
            <textarea
              id="payment_clause"
              value={formData.payment_clause}
              onChange={(e) => setFormData(prev => ({ ...prev, payment_clause: e.target.value }))}
              placeholder="Tekst die onderaan de factuur wordt getoond over de betalingsvoorwaarden"
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent resize-none"
            />
          </div>
        </div>
      </div>

      {/* Section 3: Email Template */}
      <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">E-mailsjabloon</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Sjabloon voor de e-mail waarmee facturen worden verstuurd.
          </p>
        </div>
        <div className="space-y-4">
          <div>
            <label htmlFor="email_template" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              E-mailtekst
            </label>
            <textarea
              id="email_template"
              value={formData.email_template}
              onChange={(e) => setFormData(prev => ({ ...prev, email_template: e.target.value }))}
              rows={8}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent resize-none"
            />
          </div>

          {/* Variable documentation */}
          <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
            <p className="font-semibold mb-2">Beschikbare variabelen:</p>
            <div className="space-y-1 font-mono">
              <div><code>{'{naam}'}</code> - Naam van het lid</div>
              <div><code>{'{factuur_nummer}'}</code> - Factuurnummer</div>
              <div><code>{'{tuchtzaken_lijst}'}</code> - Overzicht van tuchtzaken</div>
              <div><code>{'{totaal_bedrag}'}</code> - Totaalbedrag</div>
              <div><code>{'{betaallink}'}</code> - Link naar betaalverzoek</div>
              <div><code>{'{organisatie_naam}'}</code> - Naam van de organisatie</div>
            </div>
          </div>
        </div>
      </div>

      {/* Section 4: Rabobank Integration */}
      <div className="card p-6">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Rabobank Koppeling</h2>
          <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
            API-gegevens voor het aanmaken van betaalverzoeken via Rabobank.
          </p>
        </div>
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

      {/* Save button */}
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
    </form>
  );
}
