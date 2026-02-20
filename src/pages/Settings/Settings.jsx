import { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { FileCode, FileSpreadsheet, Download, Sun, Moon, Monitor, Check, Users, Search, Link as LinkIcon, Loader2, Key, Copy, Database } from 'lucide-react';
import { APP_NAME } from '@/constants/app';
import { prmApi } from '@/api/client';
import { useTheme } from '@/hooks/useTheme';
import { useSearch } from '@/hooks/useDashboard';
import PersonAvatar from '@/components/PersonAvatar';
import TabButton from '@/components/TabButton';


// Tab configuration (no icons - using TabButton component)
const TABS = [
  { id: 'appearance', label: 'Weergave' },
  { id: 'connections', label: 'Koppelingen' },
  { id: 'notifications', label: 'Meldingen' },
  { id: 'data', label: 'Gegevens' },
  { id: 'admin', label: 'Beheer', adminOnly: true },
  { id: 'about', label: 'Info' },
];

// Connections subtabs configuration
const CONNECTION_SUBTABS = [
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'api-access', label: 'API-toegang', icon: Key },
];

// Admin subtabs configuration
const ADMIN_SUBTABS = [
  { id: 'users', label: 'Gebruikers', icon: Users },
  { id: 'rollen', label: 'Rollen' },
];

export default function Settings() {
  const { tab: urlTab, subtab: urlSubtab } = useParams();
  const navigate = useNavigate();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;
  const userId = config.userId;

  // Get active tab from URL or default to 'appearance'
  const activeTab = urlTab || 'appearance';
  // Get active subtab for connections tab
  const activeSubtab = urlSubtab || 'carddav';

  const setActiveTab = (tab, subtab = null) => {
    if (subtab) {
      navigate(`/settings/${tab}/${subtab}`);
    } else if (tab === 'connections') {
      // Default to carddav subtab when switching to connections
      navigate(`/settings/${tab}/carddav`);
    } else if (tab === 'admin') {
      // Default to users subtab when switching to admin
      navigate(`/settings/${tab}/users`);
    } else {
      navigate(`/settings/${tab}`);
    }
  };

  const setActiveSubtab = (subtab) => {
    navigate(`/settings/connections/${subtab}`);
  };
  
  // Filter tabs based on admin status
  const visibleTabs = TABS.filter(tab => !tab.adminOnly || isAdmin);

  // App Passwords state
  const [appPasswords, setAppPasswords] = useState([]);
  const [appPasswordsLoading, setAppPasswordsLoading] = useState(true);
  const [newPasswordName, setNewPasswordName] = useState('');
  const [creatingPassword, setCreatingPassword] = useState(false);
  const [newPassword, setNewPassword] = useState(null);
  const [passwordCopied, setPasswordCopied] = useState(false);
  const [carddavUrls, setCarddavUrls] = useState(null);
  
  // Notification channels state
  const [notificationChannels, setNotificationChannels] = useState([]);
  const [notificationTime, setNotificationTime] = useState('09:00');
  const [mentionNotifications, setMentionNotifications] = useState('digest');
  const [notificationsLoading, setNotificationsLoading] = useState(true);
  const [savingChannels, setSavingChannels] = useState(false);
  const [savingTime, setSavingTime] = useState(false);
  const [savingMentionPref, setSavingMentionPref] = useState(false);
  
  // Manual trigger state (admin only)
  const [triggeringReminders, setTriggeringReminders] = useState(false);
  const [reminderMessage, setReminderMessage] = useState('');
  const [reschedulingCron, setReschedulingCron] = useState(false);
  const [cronMessage, setCronMessage] = useState('');

  // Volunteer role classification state
  const [availableRoles, setAvailableRoles] = useState([]);
  const [roleSettings, setRoleSettings] = useState({ player_roles: [], excluded_roles: [] });
  const [roleDefaults, setRoleDefaults] = useState({ default_player_roles: [], default_excluded_roles: [] });
  const [rolesLoading, setRolesLoading] = useState(true);
  const [rolesSaving, setRolesSaving] = useState(false);
  const [rolesMessage, setRolesMessage] = useState('');

  // Fetch Applicatiewachtwoorden and CardDAV URLs on mount
  useEffect(() => {
    const fetchAppPasswords = async () => {
      try {
        const [passwordsResponse, urlsResponse] = await Promise.all([
          prmApi.getAppPasswords(userId),
          prmApi.getCardDAVUrls(),
        ]);
        setAppPasswords(passwordsResponse.data || []);
        setCarddavUrls(urlsResponse.data);
      } catch {
        // App passwords fetch failed silently
      } finally {
        setAppPasswordsLoading(false);
      }
    };
    if (userId) {
      fetchAppPasswords();
    }
  }, [userId]);
  
  // Fetch notification channels on mount
  useEffect(() => {
    const fetchNotificationChannels = async () => {
      try {
        const response = await prmApi.getNotificationChannels();
        setNotificationChannels(response.data.channels || ['email']);
        setNotificationTime(response.data.notification_time || '09:00');
        setMentionNotifications(response.data.mention_notifications || 'digest');
      } catch {
        // Notification channels fetch failed silently
      } finally {
        setNotificationsLoading(false);
      }
    };
    fetchNotificationChannels();
  }, []);
  
  // Fetch volunteer role settings on mount (admin only)
  useEffect(() => {
    const fetchRoleSettings = async () => {
      if (!isAdmin) {
        setRolesLoading(false);
        return;
      }
      try {
        const [availableResponse, settingsResponse] = await Promise.all([
          prmApi.getAvailableRoles(),
          prmApi.getVolunteerRoleSettings(),
        ]);
        setAvailableRoles(availableResponse.data || []);
        const { player_roles, excluded_roles, default_player_roles, default_excluded_roles } = settingsResponse.data;
        setRoleSettings({ player_roles, excluded_roles });
        setRoleDefaults({ default_player_roles, default_excluded_roles });
      } catch {
        // Role settings fetch failed silently
      } finally {
        setRolesLoading(false);
      }
    };
    fetchRoleSettings();
  }, [isAdmin]);

  // Handle volunteer role settings save
  const handleRolesSave = async () => {
    setRolesSaving(true);
    setRolesMessage('');
    try {
      const response = await prmApi.updateVolunteerRoleSettings(roleSettings);
      const { player_roles, excluded_roles, people_recalculated } = response.data;
      setRoleSettings({ player_roles, excluded_roles });
      setRolesMessage(
        people_recalculated !== undefined && people_recalculated !== null
          ? `Rolclassificatie opgeslagen. ${people_recalculated} personen herberekend.`
          : 'Rolclassificatie opgeslagen'
      );
    } catch (error) {
      setRolesMessage('Fout bij opslaan: ' + (error.response?.data?.message || 'Onbekende fout'));
    } finally {
      setRolesSaving(false);
    }
  };

  const handleCreateAppPassword = async (e) => {
    e.preventDefault();
    if (!newPasswordName.trim()) return;
    
    setCreatingPassword(true);
    try {
      const response = await prmApi.createAppPassword(userId, newPasswordName.trim());
      setNewPassword(response.data.password);
      setAppPasswords([...appPasswords, response.data]);
      setNewPasswordName('');
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan applicatiewachtwoord niet aanmaken');
    } finally {
      setCreatingPassword(false);
    }
  };
  
  const handleDeleteAppPassword = async (uuid, name) => {
    if (!confirm(`Weet je zeker dat je het applicatiewachtwoord "${name}" wilt intrekken? Apparaten die dit wachtwoord gebruiken kunnen niet meer synchroniseren.`)) {
      return;
    }
    
    try {
      await prmApi.deleteAppPassword(userId, uuid);
      setAppPasswords(appPasswords.filter(p => p.uuid !== uuid));
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan applicatiewachtwoord niet intrekken');
    }
  };
  
  const copyNewPassword = async () => {
    try {
      await navigator.clipboard.writeText(newPassword);
      setPasswordCopied(true);
      setTimeout(() => setPasswordCopied(false), 2000);
    } catch {
      // Copy failed silently
    }
  };
  
  const copyCarddavUrl = async (url) => {
    try {
      await navigator.clipboard.writeText(url);
    } catch {
      // Copy failed silently
    }
  };
  
  const formatDate = (dateString) => {
    if (!dateString) return 'Nooit';
    const date = new Date(dateString);
    return date.toLocaleDateString('nl-NL', {
      day: 'numeric',
      month: 'short',
      year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
    });
  };

  const toggleChannel = async (channelId) => {
    const newChannels = notificationChannels.includes(channelId)
      ? notificationChannels.filter(c => c !== channelId)
      : [...notificationChannels, channelId];
    
    setSavingChannels(true);
    try {
      await prmApi.updateNotificationChannels(newChannels);
      setNotificationChannels(newChannels);
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan meldingskanalen niet bijwerken');
    } finally {
      setSavingChannels(false);
    }
  };
  
  const handleNotificationTimeChange = async (time) => {
    const [hours, minutes] = time.split(':').map(Number);
    const roundedMinutes = Math.round(minutes / 5) * 5;
    const adjustedHours = roundedMinutes === 60 ? (hours + 1) % 24 : hours;
    const adjustedMinutes = roundedMinutes === 60 ? 0 : roundedMinutes;
    const roundedTime = `${String(adjustedHours).padStart(2, '0')}:${String(adjustedMinutes).padStart(2, '0')}`;

    setNotificationTime(roundedTime);
    setSavingTime(true);

    try {
      await prmApi.updateNotificationTime(roundedTime);
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan meldingstijd niet bijwerken');
      const response = await prmApi.getNotificationChannels();
      setNotificationTime(response.data.notification_time || '09:00');
    } finally {
      setSavingTime(false);
    }
  };

  const handleMentionNotificationsChange = async (preference) => {
    setSavingMentionPref(true);
    const previousValue = mentionNotifications;
    setMentionNotifications(preference);

    try {
      await prmApi.updateMentionNotifications(preference);
    } catch (fout) {
      alert(fout.response?.data?.message || 'Kan vermeldingsvoorkeur niet bijwerken');
      setMentionNotifications(previousValue);
    } finally {
      setSavingMentionPref(false);
    }
  };
  
  const handleTriggerReminders = async () => {
    if (!confirm('Dit verstuurt herinneringsmails voor alle herinneringen van vandaag. Doorgaan?')) {
      return;
    }
    
    setTriggeringReminders(true);
    setReminderMessage('');
    
    try {
      const response = await prmApi.triggerReminders();
      setReminderMessage(response.data.message || 'Herinneringen succesvol verstuurd.');
    } catch (fout) {
      setReminderMessage(fout.response?.data?.message || 'Kan herinneringen niet versturen. Controleer de serverlogboeken.');
    } finally {
      setTriggeringReminders(false);
    }
  };
  
  const handleRescheduleCron = async () => {
    if (!confirm('Dit herplant alle cron-taken op basis van de meldingsvoorkeuren van gebruikers. Doorgaan?')) {
      return;
    }
    
    setReschedulingCron(true);
    setCronMessage('');
    
    try {
      const response = await prmApi.rescheduleCronJobs();
      setCronMessage(response.data.message || 'Cron-taken succesvol herplant.');
    } catch (fout) {
      setCronMessage(fout.response?.data?.message || 'Kan cron-taken niet herplannen. Controleer de serverlogboeken.');
    } finally {
      setReschedulingCron(false);
    }
  };

  // Render tab content
  const renderTabContent = () => {
    switch (activeTab) {
      case 'appearance':
        return <AppearanceTab />;
      case 'connections':
        return <ConnectionsTab
          activeSubtab={activeSubtab}
          setActiveSubtab={setActiveSubtab}
          setActiveTab={setActiveTab}
          // CardDAV props
          carddavUrls={carddavUrls}
          config={config}
          copyCarddavUrl={copyCarddavUrl}
          // API Access props
          appPasswords={appPasswords}
          appPasswordsLoading={appPasswordsLoading}
          newPasswordName={newPasswordName}
          setNewPasswordName={setNewPasswordName}
          handleCreateAppPassword={handleCreateAppPassword}
          creatingPassword={creatingPassword}
          newPassword={newPassword}
          setNewPassword={setNewPassword}
          copyNewPassword={copyNewPassword}
          passwordCopied={passwordCopied}
          handleDeleteAppPassword={handleDeleteAppPassword}
          formatDate={formatDate}
        />;
      case 'notifications':
        return <NotificationsTab
          notificationsLoading={notificationsLoading}
          notificationChannels={notificationChannels}
          toggleChannel={toggleChannel}
          savingChannels={savingChannels}
          notificationTime={notificationTime}
          handleNotificationTimeChange={handleNotificationTimeChange}
          savingTime={savingTime}
          mentionNotifications={mentionNotifications}
          handleMentionNotificationsChange={handleMentionNotificationsChange}
          savingMentionPref={savingMentionPref}
        />;
      case 'data':
        return <DataTab />;
      case 'admin':
        return isAdmin ? (
          <AdminTabWithSubtabs
            activeSubtab={activeSubtab}
            setActiveSubtab={(subtab) => navigate(`/settings/admin/${subtab}`)}
            handleTriggerReminders={handleTriggerReminders}
            triggeringReminders={triggeringReminders}
            reminderMessage={reminderMessage}
            handleRescheduleCron={handleRescheduleCron}
            reschedulingCron={reschedulingCron}
            cronMessage={cronMessage}
            availableRoles={availableRoles}
            roleSettings={roleSettings}
            setRoleSettings={setRoleSettings}
            roleDefaults={roleDefaults}
            rolesLoading={rolesLoading}
            rolesSaving={rolesSaving}
            rolesMessage={rolesMessage}
            handleRolesSave={handleRolesSave}
          />
        ) : null;
      case 'about':
        return <AboutTab config={config} />;
      default:
        return null;
    }
  };

  return (
    <div className="max-w-3xl mx-auto">
      {/* Tab Navigation */}
      <div className="border-b border-gray-200 mb-6 dark:border-gray-700">
        <nav className="-mb-px flex space-x-8" aria-label="Tabs">
          {visibleTabs.map((tab) => (
            <TabButton
              key={tab.id}
              label={tab.label}
              isActive={activeTab === tab.id}
              onClick={() => setActiveTab(tab.id)}
            />
          ))}
        </nav>
      </div>
      
      {/* Tab Content */}
      <div className="space-y-6">
        {renderTabContent()}
      </div>
    </div>
  );
}

// Appearance Tab Component
function AppearanceTab() {
  const { colorScheme, setColorScheme, effectiveColorScheme } = useTheme();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  // Club Configuration state (admin only)
  const [clubName, setClubName] = useState(config.clubName || '');
  const [freescoutUrl, setFreescoutUrl] = useState(config.freescoutUrl || '');
  const [savingClubConfig, setSavingClubConfig] = useState(false);
  const [clubConfigSaved, setClubConfigSaved] = useState(false);

  // Profile link state
  const [linkedPerson, setLinkedPerson] = useState(null);
  const [loadingLinkedPerson, setLoadingLinkedPerson] = useState(true);
  const [personSearchQuery, setPersonSearchQuery] = useState('');
  const [showPersonSearch, setShowPersonSearch] = useState(false);
  const [savingLinkedPerson, setSavingLinkedPerson] = useState(false);

  // Search for people
  const trimmedQuery = personSearchQuery.trim();
  const { data: searchResults, isLoading: isSearching } = useSearch(trimmedQuery);
  const peopleResults = searchResults?.people || [];
  const showSearchResults = trimmedQuery.length >= 2;

  // Fetch linked person on mount
  useEffect(() => {
    const fetchLinkedPerson = async () => {
      try {
        const response = await prmApi.getLinkedPerson();
        setLinkedPerson(response.data.person || null);
      } catch {
        // No linked person or fout
        setLinkedPerson(null);
      } finally {
        setLoadingLinkedPerson(false);
      }
    };
    fetchLinkedPerson();
  }, []);

  const handleSelectPerson = async (person) => {
    setSavingLinkedPerson(true);
    try {
      const response = await prmApi.updateLinkedPerson(person.id);
      setLinkedPerson(response.data.person);
      setShowPersonSearch(false);
      setPersonSearchQuery('');
      // Update the global config so filtering works immediately
      window.rondoConfig.currentUserPersonId = person.id;
    } catch {
      alert('Kan persoon niet koppelen. Probeer het opnieuw.');
    } finally {
      setSavingLinkedPerson(false);
    }
  };

  const handleUnlinkPerson = async () => {
    setSavingLinkedPerson(true);
    try {
      await prmApi.updateLinkedPerson(null);
      setLinkedPerson(null);
      // Update the global config
      window.rondoConfig.currentUserPersonId = null;
    } catch {
      alert('Kan persoon niet ontkoppelen. Probeer het opnieuw.');
    } finally {
      setSavingLinkedPerson(false);
    }
  };


  const handleSaveClubConfig = async () => {
    setSavingClubConfig(true);
    setClubConfigSaved(false);
    try {
      const response = await prmApi.updateClubConfig({
        club_name: clubName,
        freescout_url: freescoutUrl,
      });
      // Update window.rondoConfig with saved values
      window.rondoConfig.clubName = response.data.club_name;
      window.rondoConfig.freescoutUrl = response.data.freescout_url;

      setClubConfigSaved(true);
      setTimeout(() => setClubConfigSaved(false), 3000);
    } catch (error) {
      alert('Kan clubconfiguratie niet opslaan. Probeer het opnieuw.');
    } finally {
      setSavingClubConfig(false);
    }
  };

  const colorSchemeOptions = [
    { id: 'light', label: 'Licht', icon: Sun },
    { id: 'dark', label: 'Donker', icon: Moon },
    { id: 'system', label: 'Systeem', icon: Monitor },
  ];


  return (
    <div className="space-y-6">
      {/* Club Configuration card (admin only) */}
      {isAdmin && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-brand-gradient mb-2">Clubconfiguratie</h2>
          <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
            Configureer clubinstellingen. Wijzigingen gelden voor alle gebruikers.
          </p>

          <div className="space-y-5">
            {/* Club Name */}
            <div>
              <label className="label">Clubnaam</label>
              <input
                type="text"
                value={clubName}
                onChange={(e) => setClubName(e.target.value)}
                className="input max-w-md"
                placeholder="bijv. Hockey Club Utrecht"
              />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Wordt getoond op het inlogscherm en in de paginatitel.
              </p>
            </div>

            {/* FreeScout URL */}
            <div>
              <label className="label">FreeScout URL</label>
              <input
                type="url"
                value={freescoutUrl}
                onChange={(e) => setFreescoutUrl(e.target.value)}
                className="input max-w-md"
                placeholder="https://support.example.com"
              />
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Basis-URL voor FreeScout klantkoppelingen. Laat leeg om te verbergen.
              </p>
            </div>

            {/* Save Button */}
            <div className="flex items-center gap-3">
              <button
                onClick={handleSaveClubConfig}
                disabled={savingClubConfig}
                className="btn-primary"
              >
                {savingClubConfig ? 'Opslaan...' : 'Opslaan'}
              </button>
              {clubConfigSaved && (
                <span className="text-sm text-green-600 dark:text-green-400 flex items-center gap-1">
                  <Check className="w-4 h-4" />
                  Opgeslagen
                </span>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Color scheme card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Kleurenschema</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Kies hoe {APP_NAME} eruitziet. Selecteer een thema of synchroniseer met je systeeminstellingen.
        </p>

        {/* Color scheme selector */}
        <div className="flex gap-2">
          {colorSchemeOptions.map((option) => {
            const Icon = option.icon;
            const isSelected = colorScheme === option.id;
            return (
              <button
                key={option.id}
                onClick={() => setColorScheme(option.id)}
                className={`
                  flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-lg font-medium transition-colors
                  ${isSelected
                    ? 'bg-electric-cyan text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}
                `}
              >
                <Icon className="w-5 h-5" />
                <span>{option.label}</span>
              </button>
            );
          })}
        </div>

        {/* Current modus indicator */}
        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
          Momenteel <span className="font-medium">{effectiveColorScheme}</span> modus
          {colorScheme === 'system' && ' (op basis van je systeeminstelling)'}
        </p>
      </div>

      {/* Profile link card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4 flex items-center gap-2">
          <LinkIcon className="w-5 h-5" />
          Profielkoppeling
        </h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Koppel je account aan je persoonsrecord. Wanneer gekoppeld word je verborgen uit de deelnemerslijst van afspraken, omdat je al weet dat je aanwezig bent.
        </p>

        {loadingLinkedPerson ? (
          <div className="text-sm text-gray-500 dark:text-gray-400">Laden...</div>
        ) : linkedPerson ? (
          <div className="space-y-3">
            {/* Currently linked person */}
            <div className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <div className="flex items-center gap-3">
                <PersonAvatar
                  thumbnail={linkedPerson.thumbnail}
                  name={linkedPerson.name}
                  size="lg"
                />
                <div>
                  <p className="font-medium text-gray-900 dark:text-gray-100">{linkedPerson.name}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Gekoppeld aan je account</p>
                </div>
              </div>
              <button
                onClick={handleUnlinkPerson}
                disabled={savingLinkedPerson}
                className="px-3 py-1.5 text-sm text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/10 rounded transition-colors disabled:opacity-50"
              >
                {savingLinkedPerson ? 'Ontkoppelen...' : 'Ontkoppelen'}
              </button>
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {showPersonSearch ? (
              <div className="relative">
                {/* Search input */}
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <input
                    type="text"
                    value={personSearchQuery}
                    onChange={(e) => setPersonSearchQuery(e.target.value)}
                    placeholder="Zoek je persoonsrecord..."
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm placeholder:text-gray-400 bg-white dark:bg-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-electric-cyan focus:border-electric-cyan outline-none"
                    autoFocus
                  />
                </div>

                {/* Search results dropdown */}
                {showSearchResults && (
                  <div className="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-64 overflow-y-auto z-10">
                    {isSearching ? (
                      <div className="p-3 text-sm text-gray-500 dark:text-gray-400 text-center">Zoeken...</div>
                    ) : peopleResults.length === 0 ? (
                      <div className="p-3 text-sm text-gray-500 dark:text-gray-400 text-center">Geen personen gevonden</div>
                    ) : (
                      peopleResults.map((person) => (
                        <button
                          key={person.id}
                          onClick={() => handleSelectPerson(person)}
                          disabled={savingLinkedPerson}
                          className="w-full flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 text-left"
                        >
                          <PersonAvatar
                            thumbnail={person.thumbnail}
                            name={person.name}
                            size="md"
                          />
                          <span className="text-sm text-gray-900 dark:text-gray-100">{person.name}</span>
                        </button>
                      ))
                    )}
                  </div>
                )}

                {/* Cancel button */}
                <button
                  onClick={() => {
                    setShowPersonSearch(false);
                    setPersonSearchQuery('');
                  }}
                  className="mt-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                >
                  Annuleren
                </button>
              </div>
            ) : (
              <button
                onClick={() => setShowPersonSearch(true)}
                className="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
              >
                <Search className="w-4 h-4" />
                <span>Koppel aan je persoonsrecord</span>
              </button>
            )}
            <p className="text-xs text-gray-500 dark:text-gray-400">
              Nog niet gekoppeld. Zoek je persoonsrecord om het aan je account te koppelen.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}

// ConnectionsTab Component - Container for CardDAV and API subtabs
function ConnectionsTab({
  activeSubtab, setActiveSubtab, setActiveTab,
  // CardDAV props
  carddavUrls, config, copyCarddavUrl,
  // API Access props
  appPasswords, appPasswordsLoading, newPasswordName, setNewPasswordName,
  handleCreateAppPassword, creatingPassword, newPassword, setNewPassword,
  copyNewPassword, passwordCopied, handleDeleteAppPassword, formatDate,
}) {
  return (
    <div className="space-y-6">
      {/* Subtab navigation */}
      <div className="flex gap-2">
        {CONNECTION_SUBTABS.map((subtab) => {
          const Icon = subtab.icon;
          const isActive = activeSubtab === subtab.id;
          return (
            <button
              key={subtab.id}
              onClick={() => setActiveSubtab(subtab.id)}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
                ${isActive
                  ? 'bg-cyan-100 text-bright-cobalt dark:bg-deep-midnight dark:text-cyan-100'
                  : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                }`}
            >
              <Icon className="w-4 h-4" />
              {subtab.label}
            </button>
          );
        })}
      </div>

      {/* Subtab content */}
      {activeSubtab === 'carddav' && (
        <ConnectionsCardDAVSubtab
          carddavUrls={carddavUrls}
          config={config}
          copyCarddavUrl={copyCarddavUrl}
          setActiveTab={setActiveTab}
        />
      )}
      {activeSubtab === 'api-access' && (
        <APIAccessTab
          appPasswords={appPasswords}
          appPasswordsLoading={appPasswordsLoading}
          config={config}
          newPasswordName={newPasswordName}
          setNewPasswordName={setNewPasswordName}
          handleCreateAppPassword={handleCreateAppPassword}
          creatingPassword={creatingPassword}
          newPassword={newPassword}
          setNewPassword={setNewPassword}
          copyNewPassword={copyNewPassword}
          passwordCopied={passwordCopied}
          handleDeleteAppPassword={handleDeleteAppPassword}
          formatDate={formatDate}
        />
      )}
    </div>
  );
}

// ConnectionsCardDAVSubtab - CardDAV-synchronisatie configuration (URLs only, passwords managed in API Access tab)
function ConnectionsCardDAVSubtab({
  carddavUrls, config, copyCarddavUrl, setActiveTab,
}) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold text-brand-gradient mb-4">CardDAV-synchronisatie</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Synchroniseer je contacten met apps zoals Apple Contacten, Android Contacten of Thunderbird via CardDAV.
      </p>

      {carddavUrls ? (
        <div className="space-y-4">
          <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-3">
            <h3 className="font-medium text-sm dark:text-gray-200">Verbindingsdetails</h3>
            <div>
              <label className="text-xs text-gray-500 dark:text-gray-400">Server-URL (voor de meeste apps)</label>
              <div className="flex gap-2 mt-1">
                <input
                  type="text"
                  readOnly
                  value={carddavUrls.addressbook}
                  className="input flex-1 text-xs font-mono bg-white dark:bg-gray-700"
                  onClick={(e) => e.target.select()}
                />
                <button
                  onClick={() => copyCarddavUrl(carddavUrls.addressbook)}
                  className="btn-secondary text-xs px-2"
                  title="URL kopiëren"
                >
                  Kopiëren
                </button>
              </div>
            </div>
            <div>
              <label className="text-xs text-gray-500 dark:text-gray-400">Gebruikersnaam</label>
              <div className="flex gap-2 mt-1">
                <input
                  type="text"
                  readOnly
                  value={config.userLogin || ''}
                  className="input flex-1 text-xs font-mono bg-white dark:bg-gray-700"
                  onClick={(e) => e.target.select()}
                />
                <button
                  onClick={() => copyCarddavUrl(config.userLogin)}
                  className="btn-secondary text-xs px-2"
                  title="Gebruikersnaam kopiëren"
                >
                  Kopiëren
                </button>
              </div>
            </div>
          </div>

          <p className="text-sm text-gray-500 dark:text-gray-400">
            Applicatiewachtwoorden voor CardDAV worden beheerd in{' '}
            <button
              onClick={() => setActiveTab('api-access')}
              className="text-electric-cyan dark:text-electric-cyan hover:underline"
            >
              Instellingen &gt; API-toegang
            </button>
            .
          </p>
        </div>
      ) : (
        <div className="animate-pulse">
          <div className="h-24 bg-gray-200 rounded dark:bg-gray-700"></div>
        </div>
      )}
    </div>
  );
}

// Notifications Tab Component - Channel toggles and preferences only
function NotificationsTab({
  notificationsLoading, notificationChannels, toggleChannel, savingChannels,
  notificationTime, handleNotificationTimeChange, savingTime,
  mentionNotifications, handleMentionNotificationsChange, savingMentionPref
}) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold text-brand-gradient mb-4">Meldingen</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Kies hoe je dagelijkse herinneringen wilt ontvangen over je belangrijke datums.
      </p>

      {notificationsLoading ? (
        <div className="animate-pulse">
          <div className="h-10 bg-gray-200 rounded mb-3 dark:bg-gray-700"></div>
          <div className="h-10 bg-gray-200 rounded dark:bg-gray-700"></div>
        </div>
      ) : (
        <div className="space-y-4">
          {/* Email channel */}
          <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <div>
              <p className="font-medium dark:text-gray-200">E-mail</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">Ontvang dagelijkse samenvattingen per e-mail</p>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={notificationChannels.includes('email')}
                onChange={() => toggleChannel('email')}
                disabled={savingChannels}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-electric-cyan-light rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-electric-cyan dark:bg-gray-600 dark:peer-checked:bg-electric-cyan"></div>
            </label>
          </div>

          {/* Notification time */}
          <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <label className="label mb-1">Meldingstijd (UTC)</label>
            <input
              type="time"
              value={notificationTime}
              onChange={(e) => handleNotificationTimeChange(e.target.value)}
              className="input"
              disabled={savingTime}
              step="300"
            />
            {notificationTime && (
              <div className="mt-2 p-2 bg-gray-50 rounded text-sm dark:bg-gray-800">
                <p className="text-gray-700 dark:text-gray-300">
                  <span className="font-medium">UTC:</span> {notificationTime}
                </p>
                <p className="text-gray-700 mt-1 dark:text-gray-300">
                  <span className="font-medium">Your time ({Intl.DateTimeFormat().resolvedOptions().timeZone}):</span>{' '}
                  {(() => {
                    try {
                      const [hours, minutes] = notificationTime.split(':');
                      const utcDate = new Date();
                      utcDate.setUTCHours(parseInt(hours), parseInt(minutes), 0, 0);
                      const localTime = utcDate.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                      });
                      return localTime;
                    } catch (e) {
                      return notificationTime;
                    }
                  })()}
                </p>
              </div>
            )}
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Choose the UTC time when you want to receive your daily reminder digest. Reminders are sent within a 1-hour window of your geselecteerd time.
            </p>
          </div>

          {/* Mention notifications preference */}
          <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <label className="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300">Vermeldingsmeldingen</label>
            <select
              value={mentionNotifications}
              onChange={(e) => handleMentionNotificationsChange(e.target.value)}
              className="input"
              disabled={savingMentionPref}
            >
              <option value="digest">Opnemen in dagelijkse samenvatting (standaard)</option>
              <option value="immediate">Direct verzenden</option>
              <option value="never">Niet melden</option>
            </select>
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Kies wanneer je meldingen wilt ontvangen als iemand je @vermeldt in een notitie.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}

// API Access Tab Component - Application password management
function APIAccessTab({
  appPasswords, appPasswordsLoading, config,
  newPasswordName, setNewPasswordName, handleCreateAppPassword, creatingPassword,
  newPassword, setNewPassword, copyNewPassword, passwordCopied,
  handleDeleteAppPassword, formatDate,
}) {
  return (
    <div className="space-y-6">
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Applicatiewachtwoorden</h2>
        <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
          Maak applicatiewachtwoorden aan voor API-toegang vanuit externe tools en scripts.
          Deze wachtwoorden werken met de REST API en CardDAV-synchronisatie.
        </p>

        {appPasswordsLoading ? (
          <div className="animate-pulse">
            <div className="h-10 bg-gray-200 rounded mb-3 dark:bg-gray-700"></div>
            <div className="h-24 bg-gray-200 rounded dark:bg-gray-700"></div>
          </div>
        ) : (
          <div className="space-y-4">
            {/* Create password form */}
            <form onSubmit={handleCreateAppPassword} className="flex gap-2">
              <input
                type="text"
                value={newPasswordName}
                onChange={(e) => setNewPasswordName(e.target.value)}
                placeholder="Wachtwoordnaam (bijv. Mijn Script, Mobiele App)"
                className="input flex-1"
                disabled={creatingPassword}
              />
              <button
                type="submit"
                disabled={creatingPassword || !newPasswordName.trim()}
                className="btn-primary whitespace-nowrap"
              >
                {creatingPassword ? 'Aanmaken...' : 'Aanmaken'}
              </button>
            </form>

            {/* New password display modal */}
            {newPassword && (
              <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div className="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
                  <h3 className="text-lg font-semibold mb-2 dark:text-gray-100">Applicatiewachtwoord aangemaakt</h3>
                  <p className="text-sm text-amber-600 dark:text-amber-400 mb-4">
                    Kopieer dit wachtwoord nu. Het zal niet&apos;opnieuw worden getoond.
                  </p>
                  <div className="flex gap-2 mb-4">
                    <input
                      type="text"
                      readOnly
                      value={newPassword}
                      className="input flex-1 font-mono text-sm bg-gray-50 dark:bg-gray-700"
                      onClick={(e) => e.target.select()}
                    />
                    <button
                      onClick={copyNewPassword}
                      className="btn-primary flex items-center gap-2"
                    >
                      {passwordCopied ? (
                        <>
                          <Check className="w-4 h-4" />
                          Gekopieerd
                        </>
                      ) : (
                        <>
                          <Copy className="w-4 h-4" />
                          Kopiëren
                        </>
                      )}
                    </button>
                  </div>
                  <button
                    onClick={() => setNewPassword(null)}
                    className="btn-secondary w-full"
                  >
                    Klaar
                  </button>
                </div>
              </div>
            )}

            {/* Existing passwords list */}
            {appPasswords.length > 0 ? (
              <div>
                <h3 className="text-sm font-medium mb-2 dark:text-gray-200">Jouw applicatiewachtwoorden</h3>
                <div className="space-y-2">
                  {appPasswords.map((password) => (
                    <div
                      key={password.uuid}
                      className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                      <div>
                        <p className="font-medium text-sm dark:text-gray-200">{password.name}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                          Aangemaakt {formatDate(password.created)} · Laatst gebruikt {formatDate(password.last_used)}
                        </p>
                      </div>
                      <button
                        onClick={() => handleDeleteAppPassword(password.uuid, password.name)}
                        className="text-red-600 hover:text-red-700 text-sm font-medium dark:text-red-400 dark:hover:text-red-300"
                      >
                        Intrekken
                      </button>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Nog geen applicatiewachtwoorden. Maak er een aan om de API te gebruiken.
              </p>
            )}
          </div>
        )}
      </div>

      {/* API information card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">API-informatie</h2>
        <div className="space-y-3">
          <div>
            <label className="text-xs text-gray-500 dark:text-gray-400">Gebruikersnaam</label>
            <p className="font-mono text-sm dark:text-gray-200">{config.userLogin || 'N/A'}</p>
          </div>
          <div>
            <label className="text-xs text-gray-500 dark:text-gray-400">REST API Basis-URL</label>
            <p className="font-mono text-sm dark:text-gray-200">{window.location.origin}/wp-json/rondo/v1/</p>
          </div>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-4">
            Gebruik HTTP Basic Authentication met je gebruikersnaam en een applicatiewachtwoord.
          </p>
        </div>
      </div>
    </div>
  );
}

function DataTab() {
  const handleExport = (format) => {
    if (format === 'vcard') {
      window.location.href = '/wp-json/rondo/v1/export/vcard';
    } else if (format === 'google-csv') {
      window.location.href = '/wp-json/rondo/v1/export/google-csv';
    }
  };

  return (
    <div className="space-y-6">
      {/* Export Section */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Gegevens exporteren</h2>
        <p className="text-sm text-gray-600 mb-6">
          Exporteer al je contacten in een formaat dat compatibel is met andere contactbeheersystemen.
        </p>
        
        <div className="space-y-4">
          <button
            onClick={() => handleExport('vcard')}
            className="w-full p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4"
          >
            <div className="p-3 bg-cyan-50 rounded-lg">
              <FileCode className="w-6 h-6 text-electric-cyan" />
            </div>
            <div className="flex-1">
              <p className="font-medium">Exporteren als vCard (.vcf)</p>
              <p className="text-sm text-gray-500">
                Compatibel met Apple Contacten, Outlook, Android en de meeste contact-apps
              </p>
            </div>
            <Download className="w-5 h-5 text-gray-400" />
          </button>
          
          <button
            onClick={() => handleExport('google-csv')}
            className="w-full p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4"
          >
            <div className="p-3 bg-cyan-50 rounded-lg">
              <FileSpreadsheet className="w-6 h-6 text-electric-cyan" />
            </div>
            <div className="flex-1">
              <p className="font-medium">Exporteren als Google Contacten CSV</p>
              <p className="text-sm text-gray-500">
                Direct importeren in Google Contacten of andere CSV-compatibele systemen
              </p>
            </div>
            <Download className="w-5 h-5 text-gray-400" />
          </button>
        </div>
      </div>
    </div>
  );
}

// Admin Tab with Subtabs
function AdminTabWithSubtabs({
  activeSubtab,
  setActiveSubtab,
  handleTriggerReminders,
  triggeringReminders,
  reminderMessage,
  handleRescheduleCron,
  reschedulingCron,
  cronMessage,
  availableRoles,
  roleSettings,
  setRoleSettings,
  roleDefaults,
  rolesLoading,
  rolesSaving,
  rolesMessage,
  handleRolesSave,
}) {
  return (
    <div className="space-y-6">
      {/* Subtab Navigation */}
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="-mb-px flex space-x-6" aria-label="Admin subtabs">
          {ADMIN_SUBTABS.map((subtab) => (
            <TabButton
              key={subtab.id}
              label={subtab.label}
              isActive={activeSubtab === subtab.id}
              onClick={() => setActiveSubtab(subtab.id)}
            />
          ))}
        </nav>
      </div>

      {/* Subtab Content */}
      {activeSubtab === 'users' || !activeSubtab ? (
        <AdminTab
          handleTriggerReminders={handleTriggerReminders}
          triggeringReminders={triggeringReminders}
          reminderMessage={reminderMessage}
          handleRescheduleCron={handleRescheduleCron}
          reschedulingCron={reschedulingCron}
          cronMessage={cronMessage}
        />
      ) : activeSubtab === 'rollen' ? (
        <RollenTab
          availableRoles={availableRoles}
          roleSettings={roleSettings}
          setRoleSettings={setRoleSettings}
          roleDefaults={roleDefaults}
          rolesLoading={rolesLoading}
          rolesSaving={rolesSaving}
          rolesMessage={rolesMessage}
          handleRolesSave={handleRolesSave}
        />
      ) : null}
    </div>
  );
}

// Admin Tab Component
function AdminTab({
  handleTriggerReminders, triggeringReminders, reminderMessage,
  handleRescheduleCron, reschedulingCron, cronMessage
}) {
  return (
    <div className="space-y-6">
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Configuratie</h2>
        <div className="space-y-3">
          <Link
            to="/settings/relationship-types"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Relatietypes</p>
            <p className="text-sm text-gray-500">Beheer relatietypes en hun omgekeerde koppelingen</p>
          </Link>
          <Link
            to="/settings/custom-fields"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Aangepaste velden</p>
            <p className="text-sm text-gray-500">Definieer aangepaste gegevensvelden voor personen en organisaties</p>
          </Link>
          <Link
            to="/settings/feedback"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors dark:border-gray-700 dark:hover:bg-gray-700 dark:hover:border-gray-600"
          >
            <p className="font-medium dark:text-gray-100">Feedbackbeheer</p>
            <p className="text-sm text-gray-500 dark:text-gray-400">Bekijk en beheer alle gebruikersfeedback</p>
          </Link>
        </div>
      </div>

      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Systeemacties</h2>
        <div className="space-y-3">
          <button
            onClick={handleTriggerReminders}
            disabled={triggeringReminders}
            className="w-full text-left p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <p className="font-medium">Herinneringen versturen</p>
            <p className="text-sm text-gray-500">
              {triggeringReminders ? 'Verzenden...' : 'Handmatig herinneringen voor vandaag versturen'}
            </p>
            {reminderMessage && (
              <p className="text-sm text-green-600 mt-1">{reminderMessage}</p>
            )}
          </button>
          <button
            onClick={handleRescheduleCron}
            disabled={reschedulingCron}
            className="w-full text-left p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <p className="font-medium">Cron-taken herplannen</p>
            <p className="text-sm text-gray-500">
              {reschedulingCron ? 'Herplannen...' : 'Herplan alle gebruikersherinneringen'}
            </p>
            {cronMessage && (
              <p className="text-sm text-green-600 mt-1">{cronMessage}</p>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

// Rollen Tab Component - Volunteer role classification
function RollenTab({
  availableRoles,
  roleSettings,
  setRoleSettings,
  roleDefaults,
  rolesLoading,
  rolesSaving,
  rolesMessage,
  handleRolesSave,
}) {
  // Combine all roles: available from DB + configured in settings (deduplicated, sorted)
  const allRoles = [...new Set([
    ...availableRoles,
    ...roleSettings.player_roles,
    ...roleSettings.excluded_roles,
  ])].sort((a, b) => a.localeCompare(b, 'nl'));

  const getClassification = (role) => {
    if (roleSettings.player_roles.includes(role)) return 'player';
    if (roleSettings.excluded_roles.includes(role)) return 'excluded';
    return 'volunteer';
  };

  const handleClassificationChange = (role, classification) => {
    setRoleSettings(prev => {
      const newPlayerRoles = prev.player_roles.filter(r => r !== role);
      const newExcludedRoles = prev.excluded_roles.filter(r => r !== role);

      if (classification === 'player') {
        newPlayerRoles.push(role);
      } else if (classification === 'excluded') {
        newExcludedRoles.push(role);
      }

      return {
        player_roles: newPlayerRoles,
        excluded_roles: newExcludedRoles,
      };
    });
  };

  const isDefault = (role) => {
    return roleDefaults.default_player_roles.includes(role) || roleDefaults.default_excluded_roles.includes(role);
  };

  const getDefaultClassification = (role) => {
    if (roleDefaults.default_player_roles.includes(role)) return 'player';
    if (roleDefaults.default_excluded_roles.includes(role)) return 'excluded';
    return 'volunteer';
  };

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
          Rolclassificatie
        </h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Bepaal hoe functies uit Sportlink worden geclassificeerd voor de vrijwilligersstatus.
        </p>
        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
          Functies die als &lsquo;Speler&rsquo; zijn gemarkeerd tellen niet mee als vrijwilligersposities bij teams. Functies die als &lsquo;Uitgesloten&rsquo; zijn gemarkeerd worden nergens als vrijwilliger geteld. Alle andere functies tellen als vrijwilligersrol.
        </p>
      </div>

      {rolesLoading ? (
        <div className="flex items-center justify-center py-8">
          <Loader2 className="w-6 h-6 animate-spin text-electric-cyan" />
        </div>
      ) : allRoles.length === 0 ? (
        <div className="text-sm text-gray-500 dark:text-gray-400 py-4">
          Geen functies gevonden. Functies worden automatisch geladen vanuit Sportlink-sync.
        </div>
      ) : (
        <div className="space-y-6">
          {/* Role classification table */}
          <div className="border rounded-md border-gray-300 dark:border-gray-600 overflow-hidden">
            {/* Header */}
            <div className="grid grid-cols-[1fr,auto] gap-4 px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-600">
              <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Functie</span>
              <div className="grid grid-cols-3 gap-6 text-center">
                <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Vrijwilliger</span>
                <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Speler</span>
                <span className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-24">Uitgesloten</span>
              </div>
            </div>
            {/* Rows */}
            <div className="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
              {allRoles.map(role => {
                const classification = getClassification(role);
                const hasDefault = isDefault(role);
                const defaultClass = getDefaultClassification(role);
                const isModified = hasDefault && classification !== defaultClass;
                return (
                  <div key={role} className="grid grid-cols-[1fr,auto] gap-4 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/50 items-center">
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm text-gray-900 dark:text-gray-100 truncate">{role}</span>
                      {isModified && (
                        <span className="text-xs text-amber-600 dark:text-amber-400 whitespace-nowrap">(gewijzigd)</span>
                      )}
                    </div>
                    <div className="grid grid-cols-3 gap-6">
                      {['volunteer', 'player', 'excluded'].map(type => (
                        <label key={type} className="flex items-center justify-center w-24 cursor-pointer">
                          <input
                            type="radio"
                            name={`role-${role}`}
                            checked={classification === type}
                            onChange={() => handleClassificationChange(role, type)}
                            className="h-4 w-4 text-electric-cyan focus:ring-electric-cyan border-gray-300"
                          />
                        </label>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Save button and message */}
          <div className="flex items-center gap-4">
            <button
              onClick={handleRolesSave}
              disabled={rolesSaving}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-electric-cyan hover:bg-bright-cobalt focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-electric-cyan disabled:opacity-50"
            >
              {rolesSaving ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Opslaan...
                </>
              ) : (
                'Opslaan'
              )}
            </button>
            {rolesMessage && (
              <span className={`text-sm ${rolesMessage.includes('Fout') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                {rolesMessage}
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

// About Tab Component
function AboutTab({ config }) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold text-brand-gradient mb-4">Over {APP_NAME}</h2>
      <div className="space-y-4">
        <div>
          <p className="text-sm text-gray-600">
            Versie {config.version || '1.0.0'}
          </p>
        </div>
        <div className="pt-4 border-t border-gray-200">
          <p className="text-sm text-gray-600">
            {APP_NAME} is een persoonlijk CRM-systeem dat je helpt bij het beheren van je contacten,
            het bijhouden van belangrijke datums en het onderhouden van betekenisvolle relaties.
          </p>
        </div>
        <div className="pt-4 border-t border-gray-200">
          <p className="text-sm text-gray-500">
            Gebouwd met WordPress, React en Tailwind CSS.
          </p>
        </div>
      </div>
    </div>
  );
}
