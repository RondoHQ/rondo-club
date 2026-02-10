import { useState, useEffect } from 'react';
import { Loader2 } from 'lucide-react';
import { prmApi, wpApi } from '@/api/client';

export default function VOGSettings() {
  const [vogSettings, setVogSettings] = useState({
    from_email: '',
    from_name: '',
    template_new: '',
    template_renewal: '',
    exempt_commissies: [],
  });
  const [vogLoading, setVogLoading] = useState(true);
  const [vogSaving, setVogSaving] = useState(false);
  const [vogMessage, setVogMessage] = useState('');
  const [commissies, setCommissies] = useState([]);

  // Fetch VOG settings and commissies on mount
  useEffect(() => {
    const fetchVogSettings = async () => {
      try {
        const [settingsResponse, commissiesResponse] = await Promise.all([
          prmApi.getVOGSettings(),
          wpApi.getCommissies({ per_page: 100, _fields: 'id,title' }),
        ]);
        setVogSettings(settingsResponse.data);
        setCommissies(commissiesResponse.data || []);
      } catch {
        // VOG settings fetch failed silently
      } finally {
        setVogLoading(false);
      }
    };
    fetchVogSettings();
  }, []);

  // Handle save
  const handleVogSave = async () => {
    setVogSaving(true);
    setVogMessage('');
    try {
      const response = await prmApi.updateVOGSettings(vogSettings);
      // Extract people_recalculated before setting state (it's not part of persistent settings)
      const { people_recalculated, ...settingsData } = response.data;
      setVogSettings(settingsData);
      setVogMessage(
        people_recalculated !== undefined && people_recalculated !== null
          ? `VOG-instellingen opgeslagen. ${people_recalculated} personen herberekend.`
          : 'VOG-instellingen opgeslagen'
      );
    } catch (error) {
      setVogMessage('Fout bij opslaan: ' + (error.response?.data?.message || 'Onbekende fout'));
    } finally {
      setVogSaving(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
          VOG E-mail Instellingen
        </h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Configureer de e-mails die verstuurd worden voor VOG-aanvragen.
        </p>
      </div>

      {vogLoading ? (
        <div className="flex items-center justify-center py-8">
          <Loader2 className="w-6 h-6 animate-spin text-electric-cyan" />
        </div>
      ) : (
        <div className="space-y-6">
          {/* From Email */}
          <div>
            <label htmlFor="vog-from-email" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Afzender e-mailadres
            </label>
            <input
              type="email"
              id="vog-from-email"
              value={vogSettings.from_email}
              onChange={(e) => setVogSettings(prev => ({ ...prev, from_email: e.target.value }))}
              placeholder="vog@vereniging.nl"
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-electric-cyan focus:ring-electric-cyan sm:text-sm"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Het e-mailadres dat als afzender wordt gebruikt voor VOG e-mails.
            </p>
          </div>

          {/* From Name */}
          <div>
            <label htmlFor="vog-from-name" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Afzender naam
            </label>
            <input
              type="text"
              id="vog-from-name"
              value={vogSettings.from_name}
              onChange={(e) => setVogSettings(prev => ({ ...prev, from_name: e.target.value }))}
              placeholder="Vereniging VOG"
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-electric-cyan focus:ring-electric-cyan sm:text-sm"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              De naam die als afzender wordt weergegeven voor VOG e-mails.
            </p>
          </div>

          {/* Template for new volunteers */}
          <div>
            <label htmlFor="vog-template-new" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Template nieuwe vrijwilliger
            </label>
            <textarea
              id="vog-template-new"
              rows={8}
              value={vogSettings.template_new}
              onChange={(e) => setVogSettings(prev => ({ ...prev, template_new: e.target.value }))}
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-electric-cyan focus:ring-electric-cyan sm:text-sm font-mono"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Beschikbare variabelen: {'{first_name}'}
            </p>
          </div>

          {/* Template for renewals */}
          <div>
            <label htmlFor="vog-template-renewal" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Template verlenging
            </label>
            <textarea
              id="vog-template-renewal"
              rows={8}
              value={vogSettings.template_renewal}
              onChange={(e) => setVogSettings(prev => ({ ...prev, template_renewal: e.target.value }))}
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-electric-cyan focus:ring-electric-cyan sm:text-sm font-mono"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Beschikbare variabelen: {'{first_name}'}, {'{previous_vog_date}'}
            </p>
          </div>

          {/* Exempt commissies */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Vrijgestelde commissies
            </label>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
              Selecteer commissies die vrijgesteld zijn van de VOG-verplichting. Leden van deze commissies verschijnen niet in de VOG-lijst.
            </p>
            <div className="mt-2 border rounded-md border-gray-300 dark:border-gray-600 max-h-48 overflow-y-auto">
              {commissies.length > 0 ? (
                commissies.map(commissie => (
                  <label key={commissie.id} className="flex items-center px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={vogSettings.exempt_commissies?.includes(commissie.id)}
                      onChange={(e) => {
                        const id = commissie.id;
                        setVogSettings(prev => ({
                          ...prev,
                          exempt_commissies: e.target.checked
                            ? [...(prev.exempt_commissies || []), id]
                            : (prev.exempt_commissies || []).filter(i => i !== id)
                        }));
                      }}
                      className="h-4 w-4 text-electric-cyan focus:ring-electric-cyan border-gray-300 rounded"
                    />
                    <span className="ml-3 text-sm text-gray-700 dark:text-gray-300">
                      {commissie.title?.rendered || commissie.title}
                    </span>
                  </label>
                ))
              ) : (
                <p className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                  Geen commissies gevonden
                </p>
              )}
            </div>
          </div>

          {/* Save button and message */}
          <div className="flex items-center gap-4">
            <button
              onClick={handleVogSave}
              disabled={vogSaving}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-electric-cyan hover:bg-bright-cobalt focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-electric-cyan disabled:opacity-50"
            >
              {vogSaving ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Opslaan...
                </>
              ) : (
                'Opslaan'
              )}
            </button>
            {vogMessage && (
              <span className={`text-sm ${vogMessage.includes('Fout') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                {vogMessage}
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
