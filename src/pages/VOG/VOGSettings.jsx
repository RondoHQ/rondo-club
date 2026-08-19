import { useState, useEffect } from 'react';
import { Loader2 } from 'lucide-react';
import { prmApi, wpApi } from '@/api/client';
import SearchableMultiSelect from '@/components/SearchableMultiSelect';
import RichTextEditor from '@/components/RichTextEditor';

export default function VOGSettings() {
  const [vogSettings, setVogSettings] = useState({
    from_email: '',
    from_name: '',
    template_new: '',
    template_renewal: '',
    reminder_template_new: '',
    reminder_template_renewal: '',
    exempt_commissies: [],
    exempt_roles: [],
  });
  const [vogLoading, setVogLoading] = useState(true);
  const [vogSaving, setVogSaving] = useState(false);
  const [vogMessage, setVogMessage] = useState('');
  const [commissies, setCommissies] = useState([]);
  const [availableRoles, setAvailableRoles] = useState([]);

  // Fetch VOG settings and commissies on mount
  useEffect(() => {
    const fetchVogSettings = async () => {
      try {
        const [settingsResponse, commissiesResponse, rolesResponse] = await Promise.all([
          prmApi.getVOGSettings(),
          wpApi.getCommissies({ per_page: 100, _fields: 'id,title' }),
          prmApi.getAvailableRoles(),
        ]);
        setVogSettings(prev => ({ ...prev, ...settingsResponse.data }));
        setCommissies(commissiesResponse.data || []);
        setAvailableRoles(rolesResponse.data || []);
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
          VOG instellingen
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
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Template nieuwe vrijwilliger
            </label>
            <RichTextEditor
              value={vogSettings.template_new}
              onChange={(html) => setVogSettings(prev => ({ ...prev, template_new: html }))}
              placeholder="Schrijf de e-mail template voor nieuwe vrijwilligers..."
              minHeight="160px"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Beschikbare variabelen: {'{first_name}'}
            </p>
          </div>

          {/* Template for renewals */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Template verlenging
            </label>
            <RichTextEditor
              value={vogSettings.template_renewal}
              onChange={(html) => setVogSettings(prev => ({ ...prev, template_renewal: html }))}
              placeholder="Schrijf de e-mail template voor verlengingen..."
              minHeight="160px"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Beschikbare variabelen: {'{first_name}'}, {'{previous_vog_date}'}
            </p>
          </div>

          {/* Section separator */}
          <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h4 className="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">
              Herinnering templates
            </h4>
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
              Deze e-mails worden automatisch verstuurd 7 dagen nadat de VOG bij Justis is ingediend.
            </p>
          </div>

          {/* Reminder template for new volunteers */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Herinnering template nieuwe vrijwilliger
            </label>
            <RichTextEditor
              value={vogSettings.reminder_template_new}
              onChange={(html) => setVogSettings(prev => ({ ...prev, reminder_template_new: html }))}
              placeholder="Schrijf de herinnering template voor nieuwe vrijwilligers..."
              minHeight="160px"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Beschikbare variabelen: {'{first_name}'}, {'{email_sent_date}'}, {'{justis_date}'}
            </p>
          </div>

          {/* Reminder template for renewals */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Herinnering template verlenging
            </label>
            <RichTextEditor
              value={vogSettings.reminder_template_renewal}
              onChange={(html) => setVogSettings(prev => ({ ...prev, reminder_template_renewal: html }))}
              placeholder="Schrijf de herinnering template voor verlengingen..."
              minHeight="160px"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Beschikbare variabelen: {'{first_name}'}, {'{email_sent_date}'}, {'{justis_date}'}, {'{previous_vog_date}'}
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
            <SearchableMultiSelect
              options={commissies.map(c => ({ id: c.id, label: c.title?.rendered || c.title }))}
              selectedIds={vogSettings.exempt_commissies || []}
              onChange={(newIds) => setVogSettings(prev => ({ ...prev, exempt_commissies: newIds }))}
              placeholder="Commissie zoeken..."
              emptyMessage="Geen commissies gevonden"
            />
          </div>

          {/* Exempt roles */}
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Vrijgestelde functies
            </label>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 mb-2">
              Selecteer vrijwilligersfuncties waarvoor geen VOG nodig is. De functie blijft meetellen als vrijwilligerswerk; iemand met daarnaast een andere VOG-plichtige functie blijft in de VOG-lijst staan.
            </p>
            <SearchableMultiSelect
              options={availableRoles.map(role => ({ id: role, label: role }))}
              selectedIds={vogSettings.exempt_roles || []}
              onChange={(newRoles) => setVogSettings(prev => ({ ...prev, exempt_roles: newRoles }))}
              placeholder="Functie zoeken..."
              emptyMessage="Geen functies gevonden"
            />
          </div>

          {/* Save button and message */}
          <div className="flex items-center gap-4">
            <button
              onClick={handleVogSave}
              disabled={vogSaving}
              className="btn-primary disabled:opacity-50"
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
