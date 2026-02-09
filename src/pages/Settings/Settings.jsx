import { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { FileCode, FileSpreadsheet, Download, Sun, Moon, Monitor, Calendar, RefreshCw, Trash2, Edit2, ExternalLink, AlertCircle, Check, Coins, X, Users, Search, Link as LinkIcon, Loader2, CheckCircle, Key, Copy, Database } from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { HexColorPicker, HexColorInput } from 'react-colorful';
import { format, formatDistanceToNow } from '@/utils/dateFormat';
import { APP_NAME } from '@/constants/app';
import apiClient from '@/api/client';
import { prmApi, wpApi } from '@/api/client';
import { useTheme, COLOR_SCHEMES, ACCENT_COLORS } from '@/hooks/useTheme';
import { useSearch } from '@/hooks/useDashboard';
import VCardImport from '@/components/import/VCardImport';
import GoogleContactsImport from '@/components/import/GoogleContactsImport';
import PersonAvatar from '@/components/PersonAvatar';
import TabButton from '@/components/TabButton';
import FeeCategorySettings from './FeeCategorySettings';

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
  { id: 'calendars', label: 'Google Agenda', icon: Calendar },
  { id: 'contacts', label: 'Google Contacten', icon: Users },
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'api-access', label: 'API-toegang', icon: Key },
];

// Admin subtabs configuration (VOG moved here from main tabs)
const ADMIN_SUBTABS = [
  { id: 'users', label: 'Gebruikers', icon: Users },
  { id: 'fees', label: 'Contributie', icon: Coins },
  { id: 'vog', label: 'VOG' },
  { id: 'rollen', label: 'Rollen' },
];

export default function Settings() {
  const { tab: urlTab, subtab: urlSubtab } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;
  const userId = config.userId;

  // Get active tab from URL or default to 'appearance'
  const activeTab = urlTab || 'appearance';
  // Get active subtab for connections tab
  const activeSubtab = urlSubtab || 'calendars';

  const setActiveTab = (tab, subtab = null) => {
    if (subtab) {
      navigate(`/settings/${tab}/${subtab}`);
    } else if (tab === 'connections') {
      // Default to agendas subtab when switching to connections
      navigate(`/settings/${tab}/calendars`);
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

  // Google Contacts connection state
  const [googleContactsStatus, setGoogleContactsStatus] = useState(null);
  const [googleContactsLoading, setGoogleContactsLoading] = useState(true);
  const [connectingGoogleContacts, setConnectingGoogleContacts] = useState(false);
  const [disconnectingGoogleContacts, setDisconnectingGoogleContacts] = useState(false);
  const [googleContactsMessage, setGoogleContactsMessage] = useState('');
  const [googleContactsImporting, setGoogleContactsImporting] = useState(false);
  const [googleContactsImportResult, setGoogleContactsImportResult] = useState(null);
  const [unlinkedCount, setUnlinkedCount] = useState(null);
  const [isBulkExporting, setIsBulkExporting] = useState(false);
  const [bulkExportResult, setBulkExportResult] = useState(null);

  // Google Contacts sync state
  const [isSyncing, setIsSyncing] = useState(false);
  const [syncError, setSyncError] = useState(null);
  const [syncSuccess, setSyncSuccess] = useState(null);

  // VOG settings state
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
  const [vogCommissies, setVogCommissies] = useState([]);

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
  
  // Fetch Google Contacts status on mount
  useEffect(() => {
    const fetchGoogleContactsStatus = async () => {
      try {
        const response = await prmApi.getGoogleContactsStatus();
        setGoogleContactsStatus(response.data);
      } catch {
        // Status fetch failed silently
      } finally {
        setGoogleContactsLoading(false);
      }
    };
    fetchGoogleContactsStatus();
  }, []);

  // Fetch unlinked count when connected with readwrite
  useEffect(() => {
    if (googleContactsStatus?.connected && googleContactsStatus?.access_mode === 'readwrite') {
      prmApi.getGoogleContactsUnlinkedCount()
        .then(response => {
          setUnlinkedCount(response.data.unlinked_count);
        })
        .catch(err => {
          console.error('Kan ontkoppeld aantal niet ophalen:', err);
        });
    }
  }, [googleContactsStatus?.connected, googleContactsStatus?.access_mode]);

  // Handle OAuth callback messages from URL params
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const googleConnected = params.get('connected');
    const googleError = params.get('fout');

    // Handle Google Calendar OAuth callbacks (redirected from PHP)
    if (googleConnected === 'google') {
      // Clean URL but keep tab and subtab for connections/calendars
      navigate('/settings/connections/calendars', { replace: true });
    } else if (googleConnected === 'google-contacts') {
      // Handle Google Contacts OAuth callback
      setGoogleContactsMessage('Google Contacten succesvol verbonden!');
      // Refresh status
      prmApi.getGoogleContactsStatus().then(response => {
        setGoogleContactsStatus(response.data);
      });
      navigate('/settings/connections/contacts', { replace: true });
    } else if (googleError && params.get('subtab') === 'contacts') {
      // Show fout on contacts subtab
      setGoogleContactsMessage(`Verbinding mislukt: ${googleError}`);
      navigate('/settings/connections/contacts', { replace: true });
    } else if (googleError && params.get('tab') === 'connections') {
      // Keep on connections/calendars to show fout
      navigate('/settings/connections/calendars', { replace: true });
    }
  }, [navigate]);

  // Auto-import when pending flag is set (after OAuth connection)
  useEffect(() => {
    if (googleContactsStatus?.has_pending_import && !googleContactsImporting && !googleContactsImportResult) {
      handleImportGoogleContacts();
    }
  }, [googleContactsStatus?.has_pending_import]);

  // Fetch VOG settings on mount (admin only)
  useEffect(() => {
    const fetchVogSettings = async () => {
      if (!isAdmin) {
        setVogLoading(false);
        return;
      }
      try {
        const [settingsResponse, commissiesResponse] = await Promise.all([
          prmApi.getVOGSettings(),
          wpApi.getCommissies({ per_page: 100, _fields: 'id,title' }),
        ]);
        setVogSettings(settingsResponse.data);
        setVogCommissies(commissiesResponse.data || []);
      } catch {
        // VOG settings fetch failed silently
      } finally {
        setVogLoading(false);
      }
    };
    fetchVogSettings();
  }, [isAdmin]);

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

  // Handle VOG settings save
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

  const handleConnectGoogleContacts = async (readonly = true) => {
    setConnectingGoogleContacts(true);
    setGoogleContactsMessage('');
    try {
      const response = await prmApi.initiateGoogleContactsAuth(readonly);
      if (response.data.auth_url) {
        window.location.href = response.data.auth_url;
      }
    } catch (fout) {
      setGoogleContactsMessage(fout.response?.data?.message || 'Kan verbinding niet starten');
      setConnectingGoogleContacts(false);
    }
  };

  const handleDisconnectGoogleContacts = async () => {
    if (!confirm('Google Contacten ontkoppelen? Hiermee stopt de synchronisatie.')) return;
    setDisconnectingGoogleContacts(true);
    try {
      await prmApi.disconnectGoogleContacts();
      setGoogleContactsStatus({ ...googleContactsStatus, connected: false });
      setGoogleContactsMessage('Google Contacten ontkoppeld.');
      setGoogleContactsImportResult(null);
    } catch (fout) {
      setGoogleContactsMessage(fout.response?.data?.message || 'Kan niet ontkoppelen');
    } finally {
      setDisconnectingGoogleContacts(false);
    }
  };

  const handleImportGoogleContacts = async () => {
    setGoogleContactsImporting(true);
    setGoogleContactsImportResult(null);
    setGoogleContactsMessage('Contacten importeren van Google...');

    try {
      const response = await prmApi.triggerGoogleContactsImport();
      setGoogleContactsImportResult(response.data);
      setGoogleContactsMessage('');

      // Refresh status to get updated contact count
      const statusResponse = await prmApi.getGoogleContactsStatus();
      setGoogleContactsStatus(statusResponse.data);

      // Invalidate queries to refresh contact lists
      queryClient.invalidateQueries({ queryKey: ['people'] });
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      queryClient.invalidateQueries({ queryKey: ['dates'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    } catch (fout) {
      setGoogleContactsMessage(`Import mislukt: ${fout.response?.data?.message || fout.message}`);
      setGoogleContactsImportResult(null);
    } finally {
      setGoogleContactsImporting(false);
    }
  };

  const handleBulkExportGoogleContacts = async () => {
    setIsBulkExporting(true);
    setBulkExportResult(null);

    try {
      const response = await prmApi.bulkExportGoogleContacts();
      setBulkExportResult(response.data);
      // Refresh unlinked count after export
      setUnlinkedCount(0);
    } catch (fout) {
      setBulkExportResult({
        success: false,
        message: fout.response?.data?.message || 'Bulk export failed'
      });
    } finally {
      setIsBulkExporting(false);
    }
  };

  const handleContactsSync = async () => {
    setIsSyncing(true);
    setSyncError(null);
    setSyncSuccess(null);
    try {
      const response = await prmApi.triggerContactsSync();
      const stats = response.data.stats;
      const pullCount = stats?.pull?.contacts_imported || 0;
      const verzondenCount = stats?.push?.pushed || 0;
      setSyncSuccess(`Synchronisatie voltooid: ${pullCount} geimporteerd, ${verzondenCount} verzonden`);
      // Invalidate contacts status query to refresh last_sync display
      queryClient.invalidateQueries({ queryKey: ['contacts-status'] });
      // Refresh status
      const statusResponse = await prmApi.getGoogleContactsStatus();
      setGoogleContactsStatus(statusResponse.data);
    } catch (fout) {
      setSyncError(fout.response?.data?.message || 'Synchronisatie mislukt');
    } finally {
      setIsSyncing(false);
    }
  };

  const handleFrequencyChange = async (e) => {
    const frequency = parseInt(e.target.value, 10);
    try {
      await prmApi.updateContactsSyncFrequency(frequency);
      // Update local state
      setGoogleContactsStatus(prev => ({
        ...prev,
        sync_frequency: frequency,
      }));
    } catch (fout) {
      console.error('Kan synchronisatiefrequentie niet bijwerken:', fout);
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
          // Google Contacts props
          googleContactsStatus={googleContactsStatus}
          googleContactsLoading={googleContactsLoading}
          connectingGoogleContacts={connectingGoogleContacts}
          disconnectingGoogleContacts={disconnectingGoogleContacts}
          googleContactsMessage={googleContactsMessage}
          handleConnectGoogleContacts={handleConnectGoogleContacts}
          handleDisconnectGoogleContacts={handleDisconnectGoogleContacts}
          googleContactsImporting={googleContactsImporting}
          googleContactsImportResult={googleContactsImportResult}
          handleImportGoogleContacts={handleImportGoogleContacts}
          // Google Contacts bulk export props
          unlinkedCount={unlinkedCount}
          isBulkExporting={isBulkExporting}
          bulkExportResult={bulkExportResult}
          handleBulkExportGoogleContacts={handleBulkExportGoogleContacts}
          setBulkExportResult={setBulkExportResult}
          // Google Contacts sync props
          isSyncing={isSyncing}
          syncError={syncError}
          syncSuccess={syncSuccess}
          handleContactsSync={handleContactsSync}
          handleFrequencyChange={handleFrequencyChange}
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
            vogSettings={vogSettings}
          setVogSettings={setVogSettings}
          vogLoading={vogLoading}
          vogSaving={vogSaving}
          vogMessage={vogMessage}
          handleVogSave={handleVogSave}
          vogCommissies={vogCommissies}
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
  const { colorScheme, setColorScheme, effectiveColorScheme, accentColor, setAccentColor } = useTheme();
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  // Club Configuration state (admin only)
  const [clubName, setClubName] = useState(config.clubName || '');
  const [clubColor, setClubColor] = useState(config.accentColor || '#006935');
  const [originalClubColor] = useState(config.accentColor || '#006935');
  const [freescoutUrl, setFreescoutUrl] = useState(config.freescoutUrl || '');
  const [savingClubConfig, setSavingClubConfig] = useState(false);
  const [clubConfigSaved, setClubConfigSaved] = useState(false);
  const [showColorPicker, setShowColorPicker] = useState(false);

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

  const handleClubColorChange = (color) => {
    setClubColor(color);
    // Live preview: if user's current accent is 'club', update CSS vars immediately
    if (accentColor === 'club') {
      const root = document.documentElement;
      // Set key shades for live preview (500, 600, 700 are most visible in UI)
      root.style.setProperty('--color-accent-500', color);
      root.style.setProperty('--color-accent-600', color);
      root.style.setProperty('--color-accent-700', color);
    }
  };

  // Cleanup effect: revert preview if navigating away without saving
  useEffect(() => {
    return () => {
      // Revert preview if color was changed but not saved
      if (accentColor === 'club') {
        // Clear inline CSS overrides - useTheme will re-apply from rondoConfig
        const root = document.documentElement;
        for (const shade of ['500', '600', '700']) {
          root.style.removeProperty(`--color-accent-${shade}`);
        }
      }
    };
  }, [accentColor]);

  const handleSaveClubConfig = async () => {
    setSavingClubConfig(true);
    setClubConfigSaved(false);
    try {
      const response = await prmApi.updateClubConfig({
        club_name: clubName,
        accent_color: clubColor,
        freescout_url: freescoutUrl,
      });
      // Update window.rondoConfig with saved values
      window.rondoConfig.clubName = response.data.club_name;
      window.rondoConfig.accentColor = response.data.accent_color;
      window.rondoConfig.freescoutUrl = response.data.freescout_url;

      // Clear preview overrides so useTheme can apply full scale
      if (accentColor === 'club') {
        const root = document.documentElement;
        for (const shade of ['500', '600', '700']) {
          root.style.removeProperty(`--color-accent-${shade}`);
        }
        // useTheme's applyTheme will now generate the full scale from the new rondoConfig value
        setAccentColor('club');
      }

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

  // Map accent color names to Tailwind color classes
  const accentColorClasses = {
    club: 'bg-club-600',
    orange: 'bg-orange-500',
    teal: 'bg-teal-500',
    indigo: 'bg-indigo-500',
    emerald: 'bg-emerald-500',
    violet: 'bg-violet-500',
    pink: 'bg-pink-500',
    fuchsia: 'bg-fuchsia-500',
    rose: 'bg-rose-500',
  };

  const accentRingClasses = {
    club: 'ring-club-600',
    orange: 'ring-orange-500',
    teal: 'ring-teal-500',
    indigo: 'ring-indigo-500',
    emerald: 'ring-emerald-500',
    violet: 'ring-violet-500',
    pink: 'ring-pink-500',
    fuchsia: 'ring-fuchsia-500',
    rose: 'ring-rose-500',
  };

  return (
    <div className="space-y-6">
      {/* Club Configuration card (admin only) */}
      {isAdmin && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold mb-2 dark:text-gray-100">Clubconfiguratie</h2>
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

            {/* Club Color */}
            <div>
              <label className="label">Clubkleur</label>
              <div className="flex gap-4 items-start">
                <div>
                  <button
                    type="button"
                    onClick={() => setShowColorPicker(!showColorPicker)}
                    className="w-12 h-12 rounded-lg border-2 border-gray-300 dark:border-gray-600 cursor-pointer hover:scale-105 transition-transform"
                    style={{ backgroundColor: clubColor }}
                    title="Klik om kleurkiezer te openen"
                  />
                  {showColorPicker && (
                    <div className="mt-2">
                      <HexColorPicker color={clubColor} onChange={handleClubColorChange} />
                    </div>
                  )}
                </div>
                <div className="flex-1 max-w-[200px]">
                  <HexColorInput
                    color={clubColor}
                    onChange={handleClubColorChange}
                    className="input"
                    prefixed
                    placeholder="#006935"
                  />
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Live voorbeeld zichtbaar als je accentkleur op "Club" staat.
                  </p>
                </div>
              </div>
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
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Kleurenschema</h2>
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
                    ? 'bg-accent-500 text-white'
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

      {/* Accent color card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Accentkleur</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Kies de accentkleur voor knoppen, links en andere interactieve elementen.
        </p>

        {/* Accent color picker */}
        <div className="flex flex-wrap gap-3">
          {ACCENT_COLORS.map((color) => {
            const isSelected = accentColor === color;
            return (
              <button
                key={color}
                onClick={() => setAccentColor(color)}
                style={color === 'club' ? { backgroundColor: window.rondoConfig?.accentColor || '#006935' } : undefined}
                className={`
                  w-10 h-10 rounded-full transition-transform hover:scale-110
                  ${color !== 'club' ? accentColorClasses[color] : ''}
                  ${isSelected ? `ring-2 ring-offset-2 ${color !== 'club' ? accentRingClasses[color] : 'ring-current'} dark:ring-offset-gray-800` : ''}
                `}
                title={color === 'club' ? 'Club' : color.charAt(0).toUpperCase() + color.slice(1)}
                aria-label={`Selecteer ${color === 'club' ? 'Club' : color} accentkleur`}
              />
            );
          })}
        </div>

        {/* Current accent indicator */}
        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
          Geselecteerd: <span className="font-medium">{accentColor === 'club' ? 'Club' : accentColor.charAt(0).toUpperCase() + accentColor.slice(1)}</span>
          {accentColor === 'club' && (
            <span className="text-gray-400 dark:text-gray-500"> (past zich aan wanneer de beheerder de clubkleur wijzigt)</span>
          )}
        </p>
      </div>

      {/* Profile link card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100 flex items-center gap-2">
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
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm placeholder:text-gray-400 bg-white dark:bg-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-accent-500 focus:border-accent-500 outline-none"
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

// Calendars Tab Component
function CalendarsTab() {
  const [connections, setConnections] = useState([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState({});
  const [fout, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [showAddModal, setShowAddModal] = useState(null); // 'google' | 'caldav' | null
  const navigate = useNavigate();

  // iCal subscription state
  const [icalUrl, setIcalUrl] = useState('');
  const [webcalUrl, setWebcalUrl] = useState('');
  const [icalLoading, setIcalLoading] = useState(true);
  const [icalCopied, setIcalCopied] = useState(false);
  const [regenerating, setRegenerating] = useState(false);

  // Fetch connections on mount
  useEffect(() => {
    fetchConnections();
  }, []);

  // Handle OAuth callback params from URL
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const connected = params.get('connected');
    const foutParam = params.get('fout');

    if (connected === 'google') {
      setSuccessMessage('Google Agenda succesvol verbonden!');
      // Refresh connections list
      fetchConnections();
      // Clean URL
      navigate('/settings/connections/calendars', { replace: true });
      setTimeout(() => setSuccessMessage(''), 5000);
    } else if (foutParam) {
      setError(foutParam);
      navigate('/settings/connections/calendars', { replace: true });
      setTimeout(() => setError(''), 8000);
    }
  }, [navigate]);

  // Fetch iCal URL on mount
  useEffect(() => {
    const fetchIcalUrl = async () => {
      try {
        const response = await apiClient.get('/rondo/v1/user/ical-url');
        setIcalUrl(response.data.url);
        setWebcalUrl(response.data.webcal_url);
      } catch {
        // iCal URL fetch failed silently
      } finally {
        setIcalLoading(false);
      }
    };
    fetchIcalUrl();
  }, []);

  const fetchConnections = async () => {
    try {
      const response = await prmApi.getCalendarConnections();
      setConnections(response.data || []);
    } catch (err) {
      setError('Kan agendakoppelingen niet laden.');
    } finally {
      setLoading(false);
    }
  };

  const handleConnectGoogle = async () => {
    try {
      const response = await prmApi.getGoogleAuthUrl();
      if (response.data?.auth_url) {
        window.location.href = response.data.auth_url;
      } else {
        setError('Kan autorisatie-URL niet ophalen.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Kan Google Agenda niet verbinden.');
    }
  };

  const handleSync = async (connectionId) => {
    setSyncing(prev => ({ ...prev, [connectionId]: true }));
    try {
      const response = await prmApi.triggerCalendarSync(connectionId);
      setSuccessMessage(`Synchronisatie voltooid: ${response.data.created} nieuw, ${response.data.updated} bijgewerkt.`);
      setTimeout(() => setSuccessMessage(''), 5000);
      // Refresh connections to update last_sync
      fetchConnections();
    } catch (err) {
      setError(err.response?.data?.message || 'Synchronisatie mislukt.');
      setTimeout(() => setError(''), 8000);
    } finally {
      setSyncing(prev => ({ ...prev, [connectionId]: false }));
    }
  };

  const handleDelete = async (connectionId, connectionName) => {
    if (!confirm(`Weet je zeker dat je "${connectionName}" wilt verwijderen? Alle gesynchroniseerde afspraken worden ook verwijderd.`)) {
      return;
    }

    try {
      await prmApi.deleteCalendarConnection(connectionId);
      setConnections(prev => prev.filter(c => c.id !== connectionId));
      setSuccessMessage('Koppeling succesvol verwijderd.');
      setTimeout(() => setSuccessMessage(''), 5000);
    } catch (err) {
      setError(err.response?.data?.message || 'Kan koppeling niet verwijderen.');
      setTimeout(() => setError(''), 8000);
    }
  };

  const handleCalDAVSave = async (data) => {
    try {
      await prmApi.createCalendarConnection(data);
      setShowAddModal(null);
      setSuccessMessage('CalDAV-agenda succesvol verbonden!');
      fetchConnections();
      setTimeout(() => setSuccessMessage(''), 5000);
    } catch (err) {
      throw err; // Let CalDAVModal handle the fout
    }
  };

  const copyIcalUrl = async () => {
    try {
      await navigator.clipboard.writeText(icalUrl);
      setIcalCopied(true);
      setTimeout(() => setIcalCopied(false), 2000);
    } catch {
      // Clipboard access denied
    }
  };

  const regenerateIcalToken = async () => {
    if (!confirm('Weet je zeker dat je de agenda-URL opnieuw wilt genereren? Bestaande abonnementen werken niet meer totdat je ze bijwerkt met de nieuwe URL.')) {
      return;
    }

    setRegenerating(true);
    try {
      const response = await apiClient.post('/rondo/v1/user/regenerate-ical-token');
      setIcalUrl(response.data.url);
      setWebcalUrl(response.data.webcal_url);
    } catch {
      // Token regeneration failed silently
    } finally {
      setRegenerating(false);
    }
  };

  const formatLastSync = (lastSync) => {
    if (!lastSync) return 'Nog nooit gesynchroniseerd';
    try {
      const date = new Date(lastSync);
      return formatDistanceToNow(date, { addSuffix: true });
    } catch {
      return 'Nog nooit gesynchroniseerd';
    }
  };

  const getProviderIcon = (provider) => {
    if (provider === 'google') {
      return (
        <svg className="w-5 h-5" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
      );
    }
    // CalDAV icon
    return (
      <Calendar className="w-5 h-5 text-accent-600 dark:text-accent-400" />
    );
  };

  return (
    <div className="space-y-6">
      {/* Success message */}
      {successMessage && (
        <div className="p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2 dark:bg-green-900/20 dark:border-green-800">
          <Check className="w-5 h-5 text-green-600 dark:text-green-400" />
          <p className="text-green-800 dark:text-green-300">{successMessage}</p>
        </div>
      )}

      {/* Error message */}
      {fout && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2 dark:bg-red-900/20 dark:border-red-800">
          <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" />
          <p className="text-red-800 dark:text-red-300">{fout}</p>
        </div>
      )}

      {/* Connections list */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Agendakoppelingen</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Koppel je agenda's om automatisch afspraken te synchroniseren en contacten te vinden.
        </p>

        {loading ? (
          <div className="animate-pulse space-y-3">
            <div className="h-16 bg-gray-200 rounded dark:bg-gray-700"></div>
            <div className="h-16 bg-gray-200 rounded dark:bg-gray-700"></div>
          </div>
        ) : connections.length > 0 ? (
          <div className="space-y-3">
            {connections.map((connection) => (
              <div
                key={connection.id}
                className="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800"
              >
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-gray-100 rounded-lg dark:bg-gray-700">
                    {getProviderIcon(connection.provider)}
                  </div>
                  <div>
                    <p className="font-medium dark:text-gray-100">{connection.name}</p>
                    {/* Multi-calendar display */}
                    {connection.calendar_ids?.length > 0 ? (
                      <p className="text-xs text-gray-400 dark:text-gray-500">
                        {connection.calendar_ids.length} agenda{connection.calendar_ids.length !== 1 ? 's' : ''} geselecteerd
                      </p>
                    ) : connection.calendar_id && connection.calendar_id !== 'primary' ? (
                      <p className="text-xs text-gray-400 dark:text-gray-500 truncate max-w-xs">{connection.calendar_name || connection.calendar_id}</p>
                    ) : null}
                    <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                      <span>{formatLastSync(connection.last_sync)}</span>
                      {connection.last_error && (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs dark:bg-red-900/30 dark:text-red-400">
                          <AlertCircle className="w-3 h-3" />
                          Fout
                        </span>
                      )}
                      {!connection.sync_enabled && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs dark:bg-gray-700 dark:text-gray-400">
                          Gepauzeerd
                        </span>
                      )}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handleSync(connection.id)}
                    disabled={syncing[connection.id]}
                    className="btn-secondary text-sm flex items-center gap-1"
                    title="Nu synchroniseren"
                  >
                    <RefreshCw className={`w-4 h-4 ${syncing[connection.id] ? 'animate-spin' : ''}`} />
                    {syncing[connection.id] ? 'Synchroniseren...' : 'Sync'}
                  </button>
                  <button
                    onClick={() => setShowAddModal(connection)}
                    className="btn-secondary text-sm p-2"
                    title="Bewerken"
                  >
                    <Edit2 className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(connection.id, connection.name)}
                    className="btn-secondary text-sm p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                    title="Verwijderen"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Nog geen agendakoppelingen. Voeg er hieronder een toe om je afspraken te synchroniseren.
          </p>
        )}
      </div>

      {/* Add connection section */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Koppeling toevoegen</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button
            onClick={handleConnectGoogle}
            className="p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4 transition-colors dark:border-gray-700 dark:hover:bg-gray-800"
          >
            <div className="p-3 bg-gray-100 rounded-lg dark:bg-gray-700">
              <svg className="w-6 h-6" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
            </div>
            <div className="flex-1">
              <p className="font-medium dark:text-gray-100">Google Agenda koppelen</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Inloggen met je Google-account
              </p>
            </div>
            <ExternalLink className="w-5 h-5 text-gray-400" />
          </button>

          <button
            onClick={() => setShowAddModal('caldav')}
            className="p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4 transition-colors dark:border-gray-700 dark:hover:bg-gray-800"
          >
            <div className="p-3 bg-gray-100 rounded-lg dark:bg-gray-700">
              <Calendar className="w-6 h-6 text-accent-600 dark:text-accent-400" />
            </div>
            <div className="flex-1">
              <p className="font-medium dark:text-gray-100">CalDAV-agenda toevoegen</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                iCloud, Fastmail, Nextcloud, etc.
              </p>
            </div>
          </button>
        </div>
      </div>

      {/* Important Dates Subscription */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Abonneer op belangrijke datums in je agenda</h2>
        <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
          Abonneer op je belangrijke datums in elke agenda-app (Apple Agenda, Google Agenda, Outlook, etc.)
        </p>

        {icalLoading ? (
          <div className="animate-pulse">
            <div className="h-10 bg-gray-200 rounded mb-3 dark:bg-gray-700"></div>
            <div className="h-9 bg-gray-200 rounded w-24 dark:bg-gray-700"></div>
          </div>
        ) : (
          <div className="space-y-4">
            <div>
              <label className="label mb-1">Je agenda-feed-URL</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  readOnly
                  value={icalUrl}
                  className="input flex-1 text-sm font-mono bg-gray-50 dark:bg-gray-800"
                  onClick={(e) => e.target.select()}
                />
                <button
                  onClick={copyIcalUrl}
                  className="btn-secondary whitespace-nowrap"
                  title="URL kopiëren"
                >
                  {icalCopied ? (
                    <span className="flex items-center gap-1">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                      </svg>
                      Gekopieerd
                    </span>
                  ) : (
                    <span className="flex items-center gap-1">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                      </svg>
                      Kopiëren
                    </span>
                  )}
                </button>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <a href={webcalUrl} className="btn-primary">
                <span className="flex items-center gap-1">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Abonneren in agenda-app
                </span>
              </a>

              <button
                onClick={regenerateIcalToken}
                disabled={regenerating}
                className="btn-secondary"
              >
                {regenerating ? 'Opnieuw genereren...' : 'URL opnieuw genereren'}
              </button>
            </div>

            <p className="text-xs text-gray-500 dark:text-gray-400">
              Houd deze URL privé. Iedereen met toegang kan je belangrijke datums zien. Als je denkt dat de URL is uitgelekt, klik dan op "URL opnieuw genereren" voor een nieuwe.
            </p>
          </div>
        )}
      </div>

      {/* CalDAV Modal */}
      {showAddModal === 'caldav' && (
        <CalDAVModal
          onSave={handleCalDAVSave}
          onClose={() => setShowAddModal(null)}
        />
      )}

      {/* Edit Connection Modal */}
      {showAddModal && typeof showAddModal === 'object' && (
        <EditConnectionModal
          connection={showAddModal}
          onSave={async (id, data) => {
            await prmApi.updateCalendarConnection(id, data);
            setShowAddModal(null);
            setSuccessMessage('Koppeling succesvol bijgewerkt.');
            fetchConnections();
            setTimeout(() => setSuccessMessage(''), 5000);
          }}
          onClose={() => setShowAddModal(null)}
        />
      )}
    </div>
  );
}

// CalDAV Modal Component
function CalDAVModal({ onSave, onClose }) {
  const [name, setName] = useState('');
  const [url, setUrl] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [calendars, setCalendars] = useState([]);
  const [selectedCalendar, setSelectedCalendar] = useState('');
  const [testing, setTesting] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [tested, setTested] = useState(false);

  const handleTest = async () => {
    if (!url || !username || !password) {
      setError('Vul server-URL, gebruikersnaam en wachtwoord in.');
      return;
    }

    setTesting(true);
    setError('');

    try {
      const response = await prmApi.testCalDAVConnection({ url, username, password });
      if (response.data?.success) {
        setCalendars(response.data.calendars || []);
        setTested(true);
        if (response.data.calendars?.length > 0) {
          setSelectedCalendar(response.data.calendars[0].id);
        }
      } else {
        setError(response.data?.message || 'Verbindingstest mislukt.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Verbindingstest mislukt.');
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    if (!name) {
      setError('Voer een naam in voor deze koppeling.');
      return;
    }
    if (!tested || calendars.length === 0) {
      setError('Test eerst de verbinding en selecteer een agenda.');
      return;
    }
    if (!selectedCalendar) {
      setError('Selecteer een agenda.');
      return;
    }

    setSaving(true);
    setError('');

    try {
      await onSave({
        provider: 'caldav',
        name,
        calendar_id: selectedCalendar,
        credentials: { url, username, password },
      });
    } catch (err) {
      setError(err.response?.data?.message || 'Kan koppeling niet opslaan.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 dark:bg-gray-800">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold dark:text-gray-100">CalDAV-agenda toevoegen</h3>
          <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded dark:hover:bg-gray-700">
            <X className="w-5 h-5 text-gray-500 dark:text-gray-400" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          {fout && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
              {error}
            </div>
          )}

          <div>
            <label className="label mb-1">Verbindingsnaam</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="input"
              placeholder="bijv. iCloud Agenda"
            />
          </div>

          <div>
            <label className="label mb-1">Server-URL</label>
            <input
              type="url"
              value={url}
              onChange={(e) => { setUrl(e.target.value); setTested(false); }}
              className="input"
              placeholder="https://caldav.example.com"
            />
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Voor iCloud: caldav.icloud.com, Fastmail: caldav.fastmail.com/dav
            </p>
          </div>

          <div>
            <label className="label mb-1">Gebruikersnaam</label>
            <input
              type="text"
              value={username}
              onChange={(e) => { setUsername(e.target.value); setTested(false); }}
              className="input"
              placeholder="your-email@example.com"
            />
          </div>

          <div>
            <label className="label mb-1">Wachtwoord / App-wachtwoord</label>
            <input
              type="password"
              value={password}
              onChange={(e) => { setPassword(e.target.value); setTested(false); }}
              className="input"
              placeholder="App-specifiek wachtwoord"
            />
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Voor iCloud, gebruik een app-specifiek wachtwoord van appleid.apple.com
            </p>
          </div>

          <button
            onClick={handleTest}
            disabled={testing || !url || !username || !password}
            className="btn-secondary w-full"
          >
            {testing ? 'Testen...' : tested ? 'Opnieuw testen' : 'Verbinding testen'}
          </button>

          {tested && agendas.length > 0 && (
            <div>
              <label className="label mb-1">Selecteer agenda</label>
              <select
                value={selectedCalendar}
                onChange={(e) => setSelectedCalendar(e.target.value)}
                className="input"
              >
                {calendars.map((cal) => (
                  <option key={cal.id} value={cal.id}>
                    {cal.name}
                  </option>
                ))}
              </select>
            </div>
          )}

          {tested && agendas.length === 0 && (
            <p className="text-sm text-yellow-600 dark:text-yellow-400">
              Geen agenda's gevonden. Controleer je inloggegevens en server-URL.
            </p>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
          <button onClick={onClose} className="btn-secondary">
            Annuleren
          </button>
          <button
            onClick={handleSave}
            disabled={saving || !tested || calendars.length === 0}
            className="btn-primary"
          >
            {saving ? 'Opslaan...' : 'Opslaan'}
          </button>
        </div>
      </div>
    </div>
  );
}

// Edit Connection Modal Component
function EditConnectionModal({ connection, onSave, onClose }) {
  const [name, setName] = useState(connection.name || '');
  const [syncEnabled, setSyncEnabled] = useState(connection.sync_enabled !== false);
  const [autoLog, setAutoLog] = useState(connection.auto_log !== false);
  const [syncFromDays, setSyncFromDays] = useState(connection.sync_from_days || 90);
  const [syncToDays, setSyncToDays] = useState(connection.sync_to_days || 30);
  const [syncFrequency, setSyncFrequency] = useState(connection.sync_frequency || 15);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  // Multi-calendar selection state
  const [calendars, setCalendars] = useState([]);
  const [loadingCalendars, setLoadingCalendars] = useState(false);
  // Initialize from calendar_ids array or fall back to single calendar_id
  const [selectedCalendarIds, setSelectedCalendarIds] = useState(
    connection.calendar_ids?.length > 0
      ? connection.calendar_ids
      : (connection.calendar_id ? [connection.calendar_id] : [])
  );

  // CalDAV-specific fields
  const [url, setUrl] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [testing, setTesting] = useState(false);
  const [tested, setTested] = useState(true); // Assume tested on edit

  const isCalDAV = connection.provider === 'caldav';
  const isGoogle = connection.provider === 'google';

  // Fetch beschikbare agenda's when modal opens
  useEffect(() => {
    const fetchCalendars = async () => {
      setLoadingCalendars(true);
      try {
        const response = await prmApi.getConnectionCalendars(connection.id);
        setCalendars(response.data.calendars || []);
        // Set initial selection from current_ids array (new format) or fall back to single current
        if (selectedCalendarIds.length === 0) {
          if (response.data.current_ids?.length > 0) {
            setSelectedCalendarIds(response.data.current_ids);
          } else if (response.data.current) {
            setSelectedCalendarIds([response.data.current]);
          }
        }
      } catch (err) {
        // Silently fail - agenda-lijst is optional enhancement
        console.error('Kan agenda\'s niet ophalen:', err);
      } finally {
        setLoadingCalendars(false);
      }
    };
    fetchCalendars();
  }, [connection.id]);

  const handleTestCalDAV = async () => {
    if (!url || !username || !password) {
      setError('Vul server-URL, gebruikersnaam en wachtwoord in om te testen.');
      return;
    }

    setTesting(true);
    setError('');

    try {
      const response = await prmApi.testCalDAVConnection({ url, username, password });
      if (response.data?.success) {
        setTested(true);
        setError('');
      } else {
        setTested(false);
        setError(response.data?.message || 'Verbindingstest mislukt.');
      }
    } catch (err) {
      setTested(false);
      setError(err.response?.data?.message || 'Verbindingstest mislukt.');
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    if (!name.trim()) {
      setError('Voer een naam in voor deze koppeling.');
      return;
    }

    // If CalDAV credentials changed, must be tested
    if (isCalDAV && (url || username || password) && !tested) {
      setError('Test de bijgewerkte inloggegevens voor het opslaan.');
      return;
    }

    setSaving(true);
    setError('');

    try {
      const data = {
        name: name.trim(),
        sync_enabled: syncEnabled,
        auto_log: autoLog,
        sync_from_days: syncFromDays,
        sync_to_days: syncToDays,
        sync_frequency: syncFrequency,
      };

      // Include agenda_ids array if we have selections (for Google connections)
      if (isGoogle && selectedCalendarIds.length > 0) {
        data.calendar_ids = selectedCalendarIds;
      }

      // Include CalDAV credentials if any were changed
      if (isCalDAV && url && username && password) {
        data.credentials = { url, username, password };
      }

      await onSave(connection.id, data);
    } catch (err) {
      setError(err.response?.data?.message || 'Kan koppeling niet opslaan.');
      setSaving(false);
    }
  };

  const syncFromOptions = [
    { value: 30, label: '30 dagen' },
    { value: 60, label: '60 dagen' },
    { value: 90, label: '90 dagen' },
    { value: 180, label: '180 dagen' },
  ];

  const syncToOptions = [
    { value: 7, label: '1 week' },
    { value: 14, label: '2 weken' },
    { value: 30, label: '30 dagen' },
    { value: 60, label: '60 dagen' },
    { value: 90, label: '90 dagen' },
  ];

  const syncFrequencyOptions = [
    { value: 15, label: 'Elke 15 minuten' },
    { value: 30, label: 'Elke 30 minuten' },
    { value: 60, label: 'Elk uur' },
    { value: 240, label: 'Elke 4 uur' },
    { value: 1440, label: 'Eenmaal per dag' },
  ];

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 dark:bg-gray-800">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold dark:text-gray-100">Koppeling bewerken</h3>
          <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded dark:hover:bg-gray-700">
            <X className="w-5 h-5 text-gray-500 dark:text-gray-400" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          {error && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
              {error}
            </div>
          )}

          {/* Provider badge */}
          <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span className="capitalize font-medium">{connection.provider}</span>
            {isGoogle && (
              <span className="text-xs text-gray-400">
                (inloggegevens beheerd via Google OAuth)
              </span>
            )}
          </div>

          {/* Connection name */}
          <div>
            <label className="label mb-1">Verbindingsnaam</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="input"
              placeholder="bijv. Werkagenda"
            />
          </div>

          {/* Two-column layout for Google connections with calendars */}
          {isGoogle && calendars.length > 0 && (
            <div className="grid md:grid-cols-2 gap-4">
              {/* Left column: Calendar selection */}
              <div>
                <label className="label mb-1">Agenda&apos;s om te synchroniseren</label>
                <div className="space-y-2 max-h-48 overflow-y-auto border rounded-lg p-3 bg-gray-50 dark:bg-gray-900 dark:border-gray-700">
                  {calendars.map((cal) => (
                    <label key={cal.id} className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={selectedCalendarIds.includes(cal.id)}
                        onChange={() => {
                          setSelectedCalendarIds(prev =>
                            prev.includes(cal.id)
                              ? prev.filter(id => id !== cal.id)
                              : [...prev, cal.id]
                          );
                        }}
                        className="rounded border-gray-300 text-accent-600 focus:ring-accent-500 dark:border-gray-600 dark:bg-gray-800"
                      />
                      <span className="text-sm text-gray-700 dark:text-gray-300">
                        {cal.name}{cal.primary ? ' (Primair)' : ''}
                      </span>
                    </label>
                  ))}
                </div>
                {selectedCalendarIds.length === 0 && (
                  <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    Selecteer ten minste één agenda om te synchroniseren
                  </p>
                )}
              </div>

              {/* Right column: Sync settings */}
              <div className="space-y-3">
                {/* Synchronisatiefrequentie dropdown */}
                <div>
                  <label className="label mb-1">Synchronisatiefrequentie</label>
                  <select
                    value={syncFrequency}
                    onChange={(e) => setSyncFrequency(Number(e.target.value))}
                    className="input"
                  >
                    {syncFrequencyOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Sync from dropdown */}
                <div>
                  <label className="label mb-1">Afspraken synchroniseren vanaf</label>
                  <select
                    value={syncFromDays}
                    onChange={(e) => setSyncFromDays(Number(e.target.value))}
                    className="input"
                  >
                    {syncFromOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        Afgelopen {option.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Sync to dropdown */}
                <div>
                  <label className="label mb-1">Afspraken synchroniseren tot</label>
                  <select
                    value={syncToDays}
                    onChange={(e) => setSyncToDays(Number(e.target.value))}
                    className="input"
                  >
                    {syncToOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        Komende {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>
          )}

          {/* Loading state for agenda's */}
          {loadingCalendars && (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Beschikbare agenda&apos;s laden...
            </p>
          )}

          {/* Sync enabled toggle */}
          <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <div>
              <p className="font-medium dark:text-gray-100">Synchronisatie ingeschakeld</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">Automatisch agenda-afspraken synchroniseren</p>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={syncEnabled}
                onChange={(e) => setSyncEnabled(e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent-600 dark:peer-focus:ring-accent-800"></div>
            </label>
          </div>

          {/* Auto-log meetings toggle */}
          <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <div>
              <p className="font-medium dark:text-gray-100">Automatisch afspraken loggen</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">Automatisch activiteiten aanmaken van afspraken</p>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={autoLog}
                onChange={(e) => setAutoLog(e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent-600 dark:peer-focus:ring-accent-800"></div>
            </label>
          </div>

          {/* Sync settings for non-Google or Google without calendars loaded */}
          {(!isGoogle || calendars.length === 0) && !loadingCalendars && (
            <>
              {/* Sync from dropdown */}
              <div>
                <label className="label mb-1">Synchroniseer vanaf</label>
                <select
                  value={syncFromDays}
                  onChange={(e) => setSyncFromDays(Number(e.target.value))}
                  className="input"
                >
                  {syncFromOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      Afgelopen {option.label}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
                  Oudere afspraken worden niet gesynchroniseerd
                </p>
              </div>

              {/* Sync to dropdown */}
              <div>
                <label className="label mb-1">Synchroniseer tot</label>
                <select
                  value={syncToDays}
                  onChange={(e) => setSyncToDays(Number(e.target.value))}
                  className="input"
                >
                  {syncToOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      Komende {option.label}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
                  Toekomstige afspraken hierna worden niet gesynchroniseerd
                </p>
              </div>

              {/* Synchronisatiefrequentie dropdown */}
              <div>
                <label className="label mb-1">Synchronisatiefrequentie</label>
                <select
                  value={syncFrequency}
                  onChange={(e) => setSyncFrequency(Number(e.target.value))}
                  className="input"
                >
                  {syncFrequencyOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
                <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
                  Hoe vaak te controleren op agenda-updates
                </p>
              </div>
            </>
          )}

          {/* CalDAV credential update section */}
          {isCalDAV && (
            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
              <h4 className="font-medium mb-3 dark:text-gray-100">Inloggegevens bijwerken (optioneel)</h4>
              <p className="text-sm text-gray-500 mb-3 dark:text-gray-400">
                Laat leeg om huidige inloggegevens te behouden. Vul alle velden in om bij te werken.
              </p>

              <div className="space-y-3">
                <div>
                  <label className="label mb-1">Server-URL</label>
                  <input
                    type="url"
                    value={url}
                    onChange={(e) => { setUrl(e.target.value); setTested(false); }}
                    className="input"
                    placeholder="https://caldav.example.com"
                  />
                </div>

                <div>
                  <label className="label mb-1">Gebruikersnaam</label>
                  <input
                    type="text"
                    value={username}
                    onChange={(e) => { setUsername(e.target.value); setTested(false); }}
                    className="input"
                    placeholder="your-email@example.com"
                  />
                </div>

                <div>
                  <label className="label mb-1">Wachtwoord / App-wachtwoord</label>
                  <input
                    type="password"
                    value={password}
                    onChange={(e) => { setPassword(e.target.value); setTested(false); }}
                    className="input"
                    placeholder="Nieuw app-specifiek wachtwoord"
                  />
                </div>

                {(url || username || password) && (
                  <button
                    onClick={handleTestCalDAV}
                    disabled={testing || !url || !username || !password}
                    className="btn-secondary w-full"
                  >
                    {testing ? 'Testen...' : tested ? 'Inloggegevens geverifieerd' : 'Nieuwe inloggegevens testen'}
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Info for Google */}
          {isGoogle && (
            <div className="p-3 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <p className="text-sm text-gray-600 dark:text-gray-300">
                Om Google Agenda-inloggegevens te wijzigen, verwijder deze koppeling en maak opnieuw verbinding via Google OAuth.
              </p>
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
          <button onClick={onClose} className="btn-secondary">
            Annuleren
          </button>
          <button
            onClick={handleSave}
            disabled={saving || (isGoogle && calendars.length > 0 && selectedCalendarIds.length === 0)}
            className="btn-primary"
          >
            {saving ? 'Opslaan...' : 'Opslaan'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ConnectionsTab Component - Container for Calendars, Contacts, CardDAV, and API subtabs
function ConnectionsTab({
  activeSubtab, setActiveSubtab, setActiveTab,
  // CardDAV props
  carddavUrls, config, copyCarddavUrl,
  // Google Contacts props
  googleContactsStatus, googleContactsLoading, connectingGoogleContacts,
  disconnectingGoogleContacts, googleContactsMessage,
  handleConnectGoogleContacts, handleDisconnectGoogleContacts,
  googleContactsImporting, googleContactsImportResult, handleImportGoogleContacts,
  // Google Contacts bulk export props
  unlinkedCount, isBulkExporting, bulkExportResult,
  handleBulkExportGoogleContacts, setBulkExportResult,
  // Google Contacts sync props
  isSyncing, syncError, syncSuccess, handleContactsSync, handleFrequencyChange,
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
                  ? 'bg-accent-100 text-accent-700 dark:bg-accent-800 dark:text-accent-100'
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
      {activeSubtab === 'calendars' && <ConnectionsCalendarsSubtab />}
      {activeSubtab === 'contacts' && (
        <ConnectionsContactsSubtab
          googleContactsStatus={googleContactsStatus}
          googleContactsLoading={googleContactsLoading}
          connectingGoogleContacts={connectingGoogleContacts}
          disconnectingGoogleContacts={disconnectingGoogleContacts}
          googleContactsMessage={googleContactsMessage}
          handleConnectGoogleContacts={handleConnectGoogleContacts}
          handleDisconnectGoogleContacts={handleDisconnectGoogleContacts}
          googleContactsImporting={googleContactsImporting}
          googleContactsImportResult={googleContactsImportResult}
          handleImportGoogleContacts={handleImportGoogleContacts}
          unlinkedCount={unlinkedCount}
          isBulkExporting={isBulkExporting}
          bulkExportResult={bulkExportResult}
          handleBulkExportGoogleContacts={handleBulkExportGoogleContacts}
          setBulkExportResult={setBulkExportResult}
          isSyncing={isSyncing}
          syncError={syncError}
          syncSuccess={syncSuccess}
          handleContactsSync={handleContactsSync}
          handleFrequencyChange={handleFrequencyChange}
        />
      )}
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

// ConnectionsCalendarsSubtab - Wraps existing CalendarsTab content
function ConnectionsCalendarsSubtab() {
  // Reuse CalendarsTab directly since it's self-contained with its own state
  return <CalendarsTab />;
}

// Synchronisatiefrequentie options for UI
const SYNC_FREQUENCY_OPTIONS = [
  { value: 15, label: 'Elke 15 minuten' },
  { value: 60, label: 'Elk uur' },
  { value: 360, label: 'Every 6 hours' },
  { value: 1440, label: 'Daily' },
];

// ConnectionsContactsSubtab - Google Contacts connection management
function ConnectionsContactsSubtab({
  googleContactsStatus, googleContactsLoading, connectingGoogleContacts,
  disconnectingGoogleContacts, googleContactsMessage,
  handleConnectGoogleContacts, handleDisconnectGoogleContacts,
  googleContactsImporting, googleContactsImportResult, handleImportGoogleContacts,
  unlinkedCount, isBulkExporting, bulkExportResult,
  handleBulkExportGoogleContacts, setBulkExportResult,
  isSyncing, syncError, syncSuccess, handleContactsSync, handleFrequencyChange,
}) {
  const isConnected = googleContactsStatus?.connected;
  const isConfigured = googleContactsStatus?.google_configured;

  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Google Contacten</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Synchroniseer je contacten met Google Contacten voor naadloze toegang op al je apparaten.
      </p>

      {googleContactsLoading ? (
        <div className="animate-pulse">
          <div className="h-10 bg-gray-200 rounded dark:bg-gray-700"></div>
        </div>
      ) : !isConfigured ? (
        <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/20 dark:border-yellow-800">
          <p className="text-sm text-yellow-800 dark:text-yellow-300">
            Google integration is not configured. Contact your administrator to set up GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET.
          </p>
        </div>
      ) : isConnected ? (
        <div className="space-y-4">
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-green-900 dark:text-green-300">Verbonden met Google Contacten</p>
                {googleContactsStatus.email && (
                  <p className="text-sm text-green-700 dark:text-green-400">{googleContactsStatus.email}</p>
                )}
                {googleContactsStatus.last_sync && (
                  <p className="text-xs text-green-600 dark:text-green-500 mt-1">
                    Laatst gesynchroniseerd: {formatDistanceToNow(new Date(googleContactsStatus.last_sync), { addSuffix: true })}
                  </p>
                )}
                {googleContactsStatus.contact_count > 0 && (
                  <p className="text-xs text-green-600 dark:text-green-500">
                    {googleContactsStatus.contact_count} contacten gesynchroniseerd
                  </p>
                )}
                {googleContactsStatus.access_mode && (
                  <p className="text-xs text-green-600 dark:text-green-500">
                    Toegang: {googleContactsStatus.access_mode === 'readwrite' ? 'Lezen & Schrijven' : 'Alleen Lezen'}
                  </p>
                )}
                {/* Error indicator - show if last sync had fouts */}
                {googleContactsStatus.sync_history?.[0]?.fouts > 0 ? (
                  <details className="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    <summary className="cursor-pointer hover:underline">
                      {googleContactsStatus.sync_history[0].fouts} fout{googleContactsStatus.sync_history[0].fouts !== 1 ? 's' : ''} in laatste synchronisatie
                    </summary>
                    {googleContactsStatus.last_error && (
                      <p className="mt-1 pl-2 text-amber-700 dark:text-amber-300 text-xs">
                        {googleContactsStatus.last_error}
                      </p>
                    )}
                  </details>
                ) : googleContactsStatus.last_error && !googleContactsImportResult ? (
                  <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">
                    Waarschuwing: {googleContactsStatus.last_error}
                  </p>
                ) : null}
              </div>
              <div className="flex items-center gap-2">
                {!googleContactsImporting && (
                  <button
                    onClick={handleImportGoogleContacts}
                    className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-accent-700 dark:text-accent-300 hover:text-accent-800 dark:hover:text-accent-200"
                  >
                    <RefreshCw className="h-4 w-4" />
                    Opnieuw importeren
                  </button>
                )}
                <button
                  onClick={handleDisconnectGoogleContacts}
                  disabled={disconnectingGoogleContacts || googleContactsImporting}
                  className="btn-secondary text-sm"
                >
                  {disconnectingGoogleContacts ? 'Ontkoppelen...' : 'Ontkoppelen'}
                </button>
              </div>
            </div>
          </div>

          {/* Import Progress */}
          {googleContactsImporting && (
            <div className="flex items-center gap-2 text-accent-600 dark:text-accent-400">
              <Loader2 className="h-4 w-4 animate-spin" />
              <span>Contacten importeren van Google...</span>
            </div>
          )}

          {/* Import Results */}
          {googleContactsImportResult && (
            <div className="p-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
              <div className="flex items-start gap-2">
                <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-green-800 dark:text-green-200">
                  <p className="font-medium">Import voltooid!</p>
                  <ul className="mt-1 space-y-0.5">
                    <li>{googleContactsImportResult.stats.contacts_imported} contacten geïmporteerd</li>
                    <li>{googleContactsImportResult.stats.contacts_updated} contacten bijgewerkt</li>
                    {googleContactsImportResult.stats.contacts_skipped > 0 && (
                      <li>{googleContactsImportResult.stats.contacts_skipped} contacten overgeslagen</li>
                    )}
                    {googleContactsImportResult.stats.contacts_no_email > 0 && (
                      <li className="text-green-600 dark:text-green-300">
                        {googleContactsImportResult.stats.contacts_no_email} overgeslagen (geen e-mail)
                      </li>
                    )}
                    {googleContactsImportResult.stats.teams_created > 0 && (
                      <li>{googleContactsImportResult.stats.teams_created} organisaties aangemaakt</li>
                    )}
                    {googleContactsImportResult.stats.dates_created > 0 && (
                      <li>{googleContactsImportResult.stats.dates_created} verjaardagen toegevoegd</li>
                    )}
                    {googleContactsImportResult.stats.photos_imported > 0 && (
                      <li>{googleContactsImportResult.stats.photos_imported} foto&apos;s geïmporteerd</li>
                    )}
                  </ul>
                  {googleContactsImportResult.stats.fouts?.length > 0 && (
                    <div className="mt-2 pt-2 border-t border-green-200 dark:border-green-700">
                      <p className="font-medium text-amber-700 dark:text-amber-300">Waarschuwingen:</p>
                      <ul className="list-disc list-inside">
                        {googleContactsImportResult.stats.fouts.slice(0, 5).map((fout, i) => (
                          <li key={i} className="text-amber-600 dark:text-amber-400">{fout}</li>
                        ))}
                        {googleContactsImportResult.stats.fouts.length > 5 && (
                          <li className="text-amber-600 dark:text-amber-400">
                            ...and {googleContactsImportResult.stats.fouts.length - 5} more
                          </li>
                        )}
                      </ul>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Bulk Export Section - only show when connected with readwrite */}
          {googleContactsStatus.access_mode === 'readwrite' && (
            <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-sm font-medium text-gray-900 dark:text-white">
                    Bulk exporteren naar Google
                  </h4>
                  {unlinkedCount !== null && unlinkedCount > 0 && !isBulkExporting && !bulkExportResult && (
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                      {unlinkedCount} contact{unlinkedCount !== 1 ? 's' : ''} not yet in Google
                    </p>
                  )}
                  {unlinkedCount === 0 && !isBulkExporting && !bulkExportResult && (
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                      Alle contacten zijn gesynchroniseerd met Google
                    </p>
                  )}
                </div>
                {unlinkedCount > 0 && !isBulkExporting && !bulkExportResult && (
                  <button
                    onClick={handleBulkExportGoogleContacts}
                    className="px-3 py-1.5 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                  >
                    Export All
                  </button>
                )}
              </div>

              {/* Progress indicator */}
              {isBulkExporting && (
                <div className="mt-3">
                  <div className="flex items-center gap-2">
                    <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                    <span className="text-sm text-gray-600 dark:text-gray-400">
                      Contacten exporteren naar Google...
                    </span>
                  </div>
                </div>
              )}

              {/* Results display */}
              {bulkExportResult && (
                <div className={`mt-3 p-3 rounded-md ${bulkExportResult.success ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'}`}>
                  <p className={`text-sm ${bulkExportResult.success ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200'}`}>
                    {bulkExportResult.message}
                  </p>
                  {bulkExportResult.stats && (
                    <div className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                      <span className="inline-block mr-3">Geëxporteerd: {bulkExportResult.stats.exported}</span>
                      {bulkExportResult.stats.skipped > 0 && (
                        <span className="inline-block mr-3">Overgeslagen: {bulkExportResult.stats.skipped}</span>
                      )}
                      {bulkExportResult.stats.failed > 0 && (
                        <span className="inline-block text-red-600 dark:text-red-400">Mislukt: {bulkExportResult.stats.failed}</span>
                      )}
                    </div>
                  )}
                  <button
                    onClick={() => setBulkExportResult(null)}
                    className="mt-2 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                  >
                    Dismiss
                  </button>
                </div>
              )}
            </div>
          )}

          {/* Achtergrond synchronisatie Section */}
          <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-medium text-gray-900 dark:text-white">
                  Achtergrond synchronisatie
                </h4>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  Synchroniseer contacten automatisch op de achtergrond.
                </p>
              </div>
              <button
                onClick={handleContactsSync}
                disabled={isSyncing || googleContactsImporting}
                className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md bg-accent-600 text-white hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-accent-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50"
              >
                {isSyncing ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Synchroniseren...
                  </>
                ) : (
                  <>
                    <RefreshCw className="h-4 w-4" />
                    Nu synchroniseren
                  </>
                )}
              </button>
            </div>

            {/* Synchronisatiefrequentie dropdown */}
            <div className="mt-3">
              <label htmlFor="sync-frequency" className="block text-xs text-gray-500 dark:text-gray-400 mb-1">
                Synchronisatiefrequentie
              </label>
              <select
                id="sync-frequency"
                value={googleContactsStatus?.sync_frequency || 60}
                onChange={handleFrequencyChange}
                className="input text-sm w-48"
              >
                {SYNC_FREQUENCY_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            {/* Sync success message */}
            {syncSuccess && (
              <div className="mt-3 p-2 rounded-md bg-green-50 dark:bg-green-900/20 text-sm text-green-800 dark:text-green-200">
                {syncSuccess}
              </div>
            )}

            {/* Sync fout message */}
            {syncError && (
              <div className="mt-3 p-2 rounded-md bg-red-50 dark:bg-red-900/20 text-sm text-red-800 dark:text-red-200">
                {syncError}
              </div>
            )}
          </div>

          {/* Synchronisatiegeschiedenis */}
          {googleContactsStatus.sync_history?.length > 0 && (
            <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <details className="group">
                <summary className="text-sm font-medium text-gray-900 dark:text-white cursor-pointer hover:text-accent-600 dark:hover:text-accent-400 flex items-center gap-1">
                  <span>Synchronisatiegeschiedenis</span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    ({googleContactsStatus.sync_history.length} recente)
                  </span>
                </summary>
                <div className="mt-3 space-y-2">
                  {googleContactsStatus.sync_history.map((entry, index) => (
                    <div
                      key={index}
                      className="text-xs text-gray-600 dark:text-gray-400 flex items-center justify-between py-1 border-b border-gray-100 dark:border-gray-800 last:border-0"
                    >
                      <span>
                        {formatDistanceToNow(new Date(entry.timestamp), { addSuffix: true })}
                      </span>
                      <span className="flex items-center gap-3">
                        {entry.pulled > 0 && <span>{entry.pulled} opgehaald</span>}
                        {entry.pushed > 0 && <span>{entry.pushed} verzonden</span>}
                        {entry.fouts > 0 && (
                          <span className="text-amber-600 dark:text-amber-400">
                            {entry.fouts} fout{entry.fouts !== 1 ? 's' : ''}
                          </span>
                        )}
                        {entry.pulled === 0 && entry.pushed === 0 && entry.fouts === 0 && (
                          <span className="text-gray-400 dark:text-gray-500">Geen wijzigingen</span>
                        )}
                      </span>
                    </div>
                  ))}
                </div>
              </details>
            </div>
          )}
        </div>
      ) : (
        <div className="space-y-4">
          <button
            onClick={() => handleConnectGoogleContacts(false)}
            disabled={connectingGoogleContacts}
            className="btn-primary"
          >
            {connectingGoogleContacts ? 'Verbinden...' : 'Google Contacten koppelen'}
          </button>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Verleent lees- en schrijftoegang om contacten bidirectioneel te synchroniseren.
          </p>
        </div>
      )}

      {googleContactsMessage && (
        <p className={`mt-4 text-sm ${googleContactsMessage.includes('succesvol') || googleContactsMessage.includes('ontkoppeld') || googleContactsMessage.includes('importeren') ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
          {googleContactsMessage.includes('importeren') ? (
            <span className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" />
              {googleContactsMessage}
            </span>
          ) : (
            googleContactsMessage
          )}
        </p>
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
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">CardDAV-synchronisatie</h2>
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
              className="text-accent-600 dark:text-accent-400 hover:underline"
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
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Meldingen</h2>
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
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent-600 dark:bg-gray-600 dark:peer-checked:bg-accent-500"></div>
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

// Data Tab Component - Import types configuration
const importTypes = [
  {
    id: 'vcard',
    name: 'vCard',
    description: 'Apple Contacten, Outlook, Android',
    icon: FileCode,
    component: VCardImport,
  },
  {
    id: 'google',
    name: 'Google Contacten',
    description: 'CSV-export van Google',
    icon: FileSpreadsheet,
    component: GoogleContactsImport,
  },
];

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
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Applicatiewachtwoorden</h2>
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
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">API-informatie</h2>
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
  const [activeImportType, setActiveImportType] = useState('vcard');

  const handleExport = (format) => {
    if (format === 'vcard') {
      window.location.href = '/wp-json/rondo/v1/export/vcard';
    } else if (format === 'google-csv') {
      window.location.href = '/wp-json/rondo/v1/export/google-csv';
    }
  };
  
  const ActiveImportComponent = importTypes.find(t => t.id === activeImportType)?.component;

  return (
    <div className="space-y-6">
      {/* Import Section */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Gegevens importeren</h2>
        <p className="text-sm text-gray-600 mb-6">
          Importeer je contacten vanuit verschillende bronnen. Bestaande contacten met dezelfde naam worden bijgewerkt in plaats van gedupliceerd.
        </p>

        {/* Import type tabs */}
        <div className="border-b border-gray-200 mb-6">
          <nav className="-mb-px flex space-x-6" aria-label="Import types">
            {importTypes.map((type) => {
              const Icon = type.icon;
              const isActive = activeImportType === type.id;
              return (
                <button
                  key={type.id}
                  onClick={() => setActiveImportType(type.id)}
                  className={`group inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm transition-colors ${
                    isActive
                      ? 'border-accent-500 text-accent-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <Icon
                    className={`mr-2 h-5 w-5 ${
                      isActive ? 'text-accent-500' : 'text-gray-400 group-hover:text-gray-500'
                    }`}
                  />
                  <div className="text-left">
                    <span className="block">{type.name}</span>
                    <span className={`block text-xs font-normal ${
                      isActive ? 'text-accent-400' : 'text-gray-400'
                    }`}>
                      {type.description}
                    </span>
                  </div>
                </button>
              );
            })}
          </nav>
        </div>

        {/* Active import component */}
        {ActiveImportComponent && <ActiveImportComponent />}
      </div>
      
      {/* Export Section */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Gegevens exporteren</h2>
        <p className="text-sm text-gray-600 mb-6">
          Exporteer al je contacten in een formaat dat compatibel is met andere contactbeheersystemen.
        </p>
        
        <div className="space-y-4">
          <button
            onClick={() => handleExport('vcard')}
            className="w-full p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4"
          >
            <div className="p-3 bg-accent-50 rounded-lg">
              <FileCode className="w-6 h-6 text-accent-600" />
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
            <div className="p-3 bg-accent-50 rounded-lg">
              <FileSpreadsheet className="w-6 h-6 text-accent-600" />
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
  vogSettings,
  setVogSettings,
  vogLoading,
  vogSaving,
  vogMessage,
  handleVogSave,
  vogCommissies,
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
      ) : activeSubtab === 'fees' ? (
        <FeeCategorySettings />
      ) : activeSubtab === 'vog' ? (
        <VOGTab
          vogSettings={vogSettings}
          setVogSettings={setVogSettings}
          vogLoading={vogLoading}
          vogSaving={vogSaving}
          vogMessage={vogMessage}
          handleVogSave={handleVogSave}
          commissies={vogCommissies}
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
        <h2 className="text-lg font-semibold mb-4">Gebruikersbeheer</h2>
        <div className="space-y-3">
          <Link
            to="/settings/user-approval"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Gebruikersgoedkeuring</p>
            <p className="text-sm text-gray-500">Keur toegang goed of weiger voor nieuwe gebruikers</p>
          </Link>
        </div>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Configuratie</h2>
        <div className="space-y-3">
          <Link
            to="/settings/relationship-types"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Relatietypes</p>
            <p className="text-sm text-gray-500">Beheer relatietypes en hun omgekeerde koppelingen</p>
          </Link>
          <Link
            to="/settings/labels"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Labels</p>
            <p className="text-sm text-gray-500">Beheer labels voor personen en organisaties</p>
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
        <h2 className="text-lg font-semibold mb-4">Systeemacties</h2>
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

// VOG Tab Component
function VOGTab({
  vogSettings,
  setVogSettings,
  vogLoading,
  vogSaving,
  vogMessage,
  handleVogSave,
  commissies,
}) {
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
          <Loader2 className="w-6 h-6 animate-spin text-accent-500" />
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
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-accent-500 focus:ring-accent-500 sm:text-sm"
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
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-accent-500 focus:ring-accent-500 sm:text-sm"
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
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-accent-500 focus:ring-accent-500 sm:text-sm font-mono"
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
              className="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-accent-500 focus:ring-accent-500 sm:text-sm font-mono"
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
                      className="h-4 w-4 text-accent-600 focus:ring-accent-500 border-gray-300 rounded"
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
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-accent-600 hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-500 disabled:opacity-50"
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
          <Loader2 className="w-6 h-6 animate-spin text-accent-500" />
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
                            className="h-4 w-4 text-accent-600 focus:ring-accent-500 border-gray-300"
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
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-accent-600 hover:bg-accent-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent-500 disabled:opacity-50"
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
      <h2 className="text-lg font-semibold mb-4">Over {APP_NAME}</h2>
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
