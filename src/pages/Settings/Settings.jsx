import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Share2, Bell, Database, Shield, Info, FileCode, FileSpreadsheet, Download, Palette, Sun, Moon, Monitor, Calendar, RefreshCw, Trash2, Edit2, ExternalLink, AlertCircle, Check, X, Users, MessageSquare } from 'lucide-react';
import { format, formatDistanceToNow } from 'date-fns';
import { APP_NAME } from '@/constants/app';
import apiClient from '@/api/client';
import { prmApi } from '@/api/client';
import { useTheme, COLOR_SCHEMES, ACCENT_COLORS } from '@/hooks/useTheme';
import MonicaImport from '@/components/import/MonicaImport';
import VCardImport from '@/components/import/VCardImport';
import GoogleContactsImport from '@/components/import/GoogleContactsImport';

// Tab configuration
const TABS = [
  { id: 'appearance', label: 'Appearance', icon: Palette },
  { id: 'connections', label: 'Connections', icon: Share2 },
  { id: 'sync', label: 'Sync', icon: Calendar },
  { id: 'notifications', label: 'Notifications', icon: Bell },
  { id: 'data', label: 'Data', icon: Database },
  { id: 'admin', label: 'Admin', icon: Shield, adminOnly: true },
  { id: 'about', label: 'About', icon: Info },
];

// Connections subtabs configuration
const CONNECTION_SUBTABS = [
  { id: 'calendars', label: 'Calendars', icon: Calendar },
  { id: 'carddav', label: 'CardDAV', icon: Users },
  { id: 'slack', label: 'Slack', icon: MessageSquare },
];

export default function Settings() {
  const [searchParams, setSearchParams] = useSearchParams();
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;
  const userId = config.userId;

  // Get active tab from URL or default to 'appearance'
  const activeTab = searchParams.get('tab') || 'appearance';
  // Get active subtab for connections tab
  const activeSubtab = searchParams.get('subtab') || 'calendars';

  const setActiveTab = (tab, subtab = null) => {
    if (subtab) {
      setSearchParams({ tab, subtab });
    } else if (tab === 'connections') {
      // Default to calendars subtab when switching to connections
      setSearchParams({ tab, subtab: 'calendars' });
    } else {
      setSearchParams({ tab });
    }
  };

  const setActiveSubtab = (subtab) => {
    setSearchParams({ tab: 'connections', subtab });
  };
  
  // Filter tabs based on admin status
  const visibleTabs = TABS.filter(tab => !tab.adminOnly || isAdmin);
  
  const [icalUrl, setIcalUrl] = useState('');
  const [webcalUrl, setWebcalUrl] = useState('');
  const [icalLoading, setIcalLoading] = useState(true);
  const [icalCopied, setIcalCopied] = useState(false);
  const [regenerating, setRegenerating] = useState(false);
  
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
  const [slackWebhook, setSlackWebhook] = useState('');
  const [slackConnected, setSlackConnected] = useState(false);
  const [slackWorkspaceName, setSlackWorkspaceName] = useState('');
  const [notificationTime, setNotificationTime] = useState('09:00');
  const [mentionNotifications, setMentionNotifications] = useState('digest');
  const [notificationsLoading, setNotificationsLoading] = useState(true);
  const [savingChannels, setSavingChannels] = useState(false);
  const [savingTime, setSavingTime] = useState(false);
  const [savingMentionPref, setSavingMentionPref] = useState(false);
  const [webhookTestMessage, setWebhookTestMessage] = useState('');
  const [disconnectingSlack, setDisconnectingSlack] = useState(false);
  
  // Slack notification targets state
  const [slackChannels, setSlackChannels] = useState([]);
  const [slackUsers, setSlackUsers] = useState([]);
  const [slackTargets, setSlackTargets] = useState([]);
  const [loadingSlackData, setLoadingSlackData] = useState(false);
  const [savingSlackTargets, setSavingSlackTargets] = useState(false);
  
  // Manual trigger state (admin only)
  const [triggeringReminders, setTriggeringReminders] = useState(false);
  const [reminderMessage, setReminderMessage] = useState('');
  const [reschedulingCron, setReschedulingCron] = useState(false);
  const [cronMessage, setCronMessage] = useState('');
  
  // Fetch iCal URL on mount
  useEffect(() => {
    const fetchIcalUrl = async () => {
      try {
        const response = await apiClient.get('/prm/v1/user/ical-url');
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
  
  // Fetch Application Passwords and CardDAV URLs on mount
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
  
  // Fetch Slack channels, users, and targets
  const fetchSlackData = async () => {
    setLoadingSlackData(true);
    try {
      const [channelsResponse, targetsResponse] = await Promise.all([
        prmApi.getSlackChannels(),
        prmApi.getSlackTargets(),
      ]);
      
      setSlackChannels(channelsResponse.data.channels || []);
      setSlackUsers(channelsResponse.data.users || []);
      setSlackTargets(targetsResponse.data.targets || []);
    } catch {
      setWebhookTestMessage('Failed to load Slack channels and users. Please try refreshing the page.');
    } finally {
      setLoadingSlackData(false);
    }
  };
  
  // Fetch notification channels on mount
  useEffect(() => {
    const fetchNotificationChannels = async () => {
      try {
        const response = await prmApi.getNotificationChannels();
        setNotificationChannels(response.data.channels || ['email']);
        setSlackWebhook(response.data.slack_webhook || '');
        setNotificationTime(response.data.notification_time || '09:00');
        setMentionNotifications(response.data.mention_notifications || 'digest');
        
        // Check Slack OAuth status
        const slackStatus = await prmApi.getSlackStatus();
        const isConnected = slackStatus.data.connected || false;
        setSlackConnected(isConnected);
        setSlackWorkspaceName(slackStatus.data.workspace_name || '');
        
        // If connected, fetch channels/users and targets
        if (isConnected) {
          await fetchSlackData();
        }
      } catch {
        // Notification channels fetch failed silently
      } finally {
        setNotificationsLoading(false);
      }
    };
    fetchNotificationChannels();
  }, []);
  
  // Fetch Slack data when connection status changes
  useEffect(() => {
    if (slackConnected) {
      fetchSlackData();
    }
  }, [slackConnected]);
  
  // Handle OAuth callback messages from URL params
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const slackConnectedParam = params.get('slack_connected');
    const slackError = params.get('slack_error');
    const googleConnected = params.get('connected');
    const googleError = params.get('error');

    // Handle Slack OAuth callbacks
    if (slackConnectedParam === '1') {
      setWebhookTestMessage('Slack connected successfully!');
      // Refresh Slack status
      prmApi.getSlackStatus().then(response => {
        setSlackConnected(response.data.connected || false);
        setSlackWorkspaceName(response.data.workspace_name || '');
      });
      // Clean URL but keep tab and subtab for connections/slack
      setSearchParams({ tab: 'connections', subtab: 'slack' });
    } else if (slackError) {
      setWebhookTestMessage(`Slack connection failed: ${slackError}`);
      setSearchParams({ tab: 'connections', subtab: 'slack' });
    }

    // Handle Google Calendar OAuth callbacks (redirected from PHP)
    if (googleConnected === 'google') {
      // Clean URL but keep tab and subtab for connections/calendars
      setSearchParams({ tab: 'connections', subtab: 'calendars' });
    } else if (googleError && params.get('tab') === 'connections') {
      // Keep on connections/calendars to show error
      setSearchParams({ tab: 'connections', subtab: 'calendars' });
    }
  }, []);
  
  const handleConnectSlack = async () => {
    try {
      const response = await apiClient.get('/prm/v1/slack/oauth/authorize');
      window.location.href = response.data.oauth_url;
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to connect Slack');
    }
  };
  
  const handleDisconnectSlack = async () => {
    if (!confirm('Are you sure you want to disconnect Slack? You will stop receiving Slack notifications.')) {
      return;
    }
    
    setDisconnectingSlack(true);
    try {
      await prmApi.disconnectSlack();
      setSlackConnected(false);
      setSlackWorkspaceName('');
      setSlackChannels([]);
      setSlackUsers([]);
      setSlackTargets([]);
      setWebhookTestMessage('Slack disconnected successfully');
      if (notificationChannels.includes('slack')) {
        await toggleChannel('slack');
      }
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to disconnect Slack');
    } finally {
      setDisconnectingSlack(false);
    }
  };
  
  const handleToggleSlackTarget = (targetId) => {
    const newTargets = slackTargets.includes(targetId)
      ? slackTargets.filter(id => id !== targetId)
      : [...slackTargets, targetId];
    setSlackTargets(newTargets);
  };
  
  const handleSaveSlackTargets = async () => {
    setSavingSlackTargets(true);
    try {
      await prmApi.updateSlackTargets(slackTargets);
      setWebhookTestMessage('Notification targets saved successfully');
      setTimeout(() => setWebhookTestMessage(''), 3000);
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to save notification targets');
    } finally {
      setSavingSlackTargets(false);
    }
  };
  
  const copyIcalUrl = async () => {
    try {
      await navigator.clipboard.writeText(icalUrl);
      setIcalCopied(true);
      setTimeout(() => setIcalCopied(false), 2000);
    } catch {
      // Copy failed silently
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
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to create app password');
    } finally {
      setCreatingPassword(false);
    }
  };
  
  const handleDeleteAppPassword = async (uuid, name) => {
    if (!confirm(`Are you sure you want to revoke the app password "${name}"? Any devices using this password will no longer be able to sync.`)) {
      return;
    }
    
    try {
      await prmApi.deleteAppPassword(userId, uuid);
      setAppPasswords(appPasswords.filter(p => p.uuid !== uuid));
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to revoke app password');
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
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric',
      year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
    });
  };
  
  const regenerateIcalToken = async () => {
    if (!confirm('Are you sure you want to regenerate your calendar URL? Any existing calendar subscriptions will stop working until you update them with the new URL.')) {
      return;
    }
    
    setRegenerating(true);
    try {
      const response = await apiClient.post('/prm/v1/user/regenerate-ical-token');
      setIcalUrl(response.data.url);
      setWebcalUrl(response.data.webcal_url);
    } catch {
      // Token regeneration failed silently
    } finally {
      setRegenerating(false);
    }
  };
  
  const toggleChannel = async (channelId) => {
    const newChannels = notificationChannels.includes(channelId)
      ? notificationChannels.filter(c => c !== channelId)
      : [...notificationChannels, channelId];
    
    setSavingChannels(true);
    try {
      await prmApi.updateNotificationChannels(newChannels);
      setNotificationChannels(newChannels);
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to update notification channels');
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
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to update notification time');
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
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to update mention notification preference');
      setMentionNotifications(previousValue);
    } finally {
      setSavingMentionPref(false);
    }
  };
  
  const handleTriggerReminders = async () => {
    if (!confirm('This will send reminder emails for all reminders due today. Continue?')) {
      return;
    }
    
    setTriggeringReminders(true);
    setReminderMessage('');
    
    try {
      const response = await prmApi.triggerReminders();
      setReminderMessage(response.data.message || 'Reminders triggered successfully.');
    } catch (error) {
      setReminderMessage(error.response?.data?.message || 'Failed to trigger reminders. Please check server logs.');
    } finally {
      setTriggeringReminders(false);
    }
  };
  
  const handleRescheduleCron = async () => {
    if (!confirm('This will reschedule all user reminder cron jobs based on their notification time preferences. Continue?')) {
      return;
    }
    
    setReschedulingCron(true);
    setCronMessage('');
    
    try {
      const response = await prmApi.rescheduleCronJobs();
      setCronMessage(response.data.message || 'Cron jobs rescheduled successfully.');
    } catch (error) {
      setCronMessage(error.response?.data?.message || 'Failed to reschedule cron jobs. Please check server logs.');
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
          // CardDAV props
          appPasswords={appPasswords}
          appPasswordsLoading={appPasswordsLoading}
          carddavUrls={carddavUrls}
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
          copyCarddavUrl={copyCarddavUrl}
          // Slack props
          slackConnected={slackConnected}
          slackWorkspaceName={slackWorkspaceName}
          handleConnectSlack={handleConnectSlack}
          handleDisconnectSlack={handleDisconnectSlack}
          disconnectingSlack={disconnectingSlack}
          webhookTestMessage={webhookTestMessage}
          slackChannels={slackChannels}
          slackUsers={slackUsers}
          slackTargets={slackTargets}
          loadingSlackData={loadingSlackData}
          handleToggleSlackTarget={handleToggleSlackTarget}
          handleSaveSlackTargets={handleSaveSlackTargets}
          savingSlackTargets={savingSlackTargets}
        />;
      case 'sync':
        return <SyncTab
          icalUrl={icalUrl}
          webcalUrl={webcalUrl}
          icalLoading={icalLoading}
          icalCopied={icalCopied}
          copyIcalUrl={copyIcalUrl}
          regenerateIcalToken={regenerateIcalToken}
          regenerating={regenerating}
        />;
      case 'notifications':
        return <NotificationsTab
          notificationsLoading={notificationsLoading}
          notificationChannels={notificationChannels}
          toggleChannel={toggleChannel}
          savingChannels={savingChannels}
          slackConnected={slackConnected}
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
        return isAdmin ? <AdminTab
          handleTriggerReminders={handleTriggerReminders}
          triggeringReminders={triggeringReminders}
          reminderMessage={reminderMessage}
          handleRescheduleCron={handleRescheduleCron}
          reschedulingCron={reschedulingCron}
          cronMessage={cronMessage}
        /> : null;
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
          {visibleTabs.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`
                  flex items-center gap-2 py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap
                  ${isActive
                    ? 'border-accent-500 text-accent-600 dark:border-accent-400 dark:text-accent-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600'}
                `}
              >
                <Icon className="w-4 h-4" />
                {tab.label}
              </button>
            );
          })}
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

  const colorSchemeOptions = [
    { id: 'light', label: 'Light', icon: Sun },
    { id: 'dark', label: 'Dark', icon: Moon },
    { id: 'system', label: 'System', icon: Monitor },
  ];

  // Map accent color names to Tailwind color classes
  const accentColorClasses = {
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
      {/* Color scheme card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Color scheme</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Choose how {APP_NAME} looks to you. Select a single theme or sync with your system settings.
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

        {/* Current mode indicator */}
        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
          Currently using <span className="font-medium">{effectiveColorScheme}</span> mode
          {colorScheme === 'system' && ' (based on your system preference)'}
        </p>
      </div>

      {/* Accent color card */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Accent color</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Choose the accent color used for buttons, links, and other interactive elements.
        </p>

        {/* Accent color picker */}
        <div className="flex flex-wrap gap-3">
          {ACCENT_COLORS.map((color) => {
            const isSelected = accentColor === color;
            return (
              <button
                key={color}
                onClick={() => setAccentColor(color)}
                className={`
                  w-10 h-10 rounded-full transition-transform hover:scale-110
                  ${accentColorClasses[color]}
                  ${isSelected ? `ring-2 ring-offset-2 ${accentRingClasses[color]} dark:ring-offset-gray-800` : ''}
                `}
                title={color.charAt(0).toUpperCase() + color.slice(1)}
                aria-label={`Select ${color} accent color`}
              />
            );
          })}
        </div>

        {/* Current accent indicator */}
        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
          Selected: <span className="font-medium capitalize">{accentColor}</span>
        </p>
      </div>
    </div>
  );
}

// Calendars Tab Component
function CalendarsTab() {
  const [connections, setConnections] = useState([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState({});
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [showAddModal, setShowAddModal] = useState(null); // 'google' | 'caldav' | null
  const [searchParams, setSearchParams] = useSearchParams();

  // Fetch connections on mount
  useEffect(() => {
    fetchConnections();
  }, []);

  // Handle OAuth callback params from URL
  useEffect(() => {
    const connected = searchParams.get('connected');
    const errorParam = searchParams.get('error');

    if (connected === 'google') {
      setSuccessMessage('Google Calendar connected successfully!');
      // Refresh connections list
      fetchConnections();
      // Clean URL
      setSearchParams({ tab: 'calendars' });
      setTimeout(() => setSuccessMessage(''), 5000);
    } else if (errorParam) {
      setError(errorParam);
      setSearchParams({ tab: 'calendars' });
      setTimeout(() => setError(''), 8000);
    }
  }, [searchParams, setSearchParams]);

  const fetchConnections = async () => {
    try {
      const response = await prmApi.getCalendarConnections();
      setConnections(response.data || []);
    } catch (err) {
      setError('Failed to load calendar connections.');
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
        setError('Failed to get authorization URL.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to connect Google Calendar.');
    }
  };

  const handleSync = async (connectionId) => {
    setSyncing(prev => ({ ...prev, [connectionId]: true }));
    try {
      const response = await prmApi.triggerCalendarSync(connectionId);
      setSuccessMessage(`Sync completed: ${response.data.created} new, ${response.data.updated} updated events.`);
      setTimeout(() => setSuccessMessage(''), 5000);
      // Refresh connections to update last_sync
      fetchConnections();
    } catch (err) {
      setError(err.response?.data?.message || 'Sync failed.');
      setTimeout(() => setError(''), 8000);
    } finally {
      setSyncing(prev => ({ ...prev, [connectionId]: false }));
    }
  };

  const handleDelete = async (connectionId, connectionName) => {
    if (!confirm(`Are you sure you want to delete "${connectionName}"? All synced events from this calendar will also be deleted.`)) {
      return;
    }

    try {
      await prmApi.deleteCalendarConnection(connectionId);
      setConnections(prev => prev.filter(c => c.id !== connectionId));
      setSuccessMessage('Connection deleted successfully.');
      setTimeout(() => setSuccessMessage(''), 5000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to delete connection.');
      setTimeout(() => setError(''), 8000);
    }
  };

  const handleCalDAVSave = async (data) => {
    try {
      await prmApi.createCalendarConnection(data);
      setShowAddModal(null);
      setSuccessMessage('CalDAV calendar connected successfully!');
      fetchConnections();
      setTimeout(() => setSuccessMessage(''), 5000);
    } catch (err) {
      throw err; // Let CalDAVModal handle the error
    }
  };

  const formatLastSync = (lastSync) => {
    if (!lastSync) return 'Never synced';
    try {
      const date = new Date(lastSync);
      return formatDistanceToNow(date, { addSuffix: true });
    } catch {
      return 'Never synced';
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
      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2 dark:bg-red-900/20 dark:border-red-800">
          <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" />
          <p className="text-red-800 dark:text-red-300">{error}</p>
        </div>
      )}

      {/* Connections list */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Calendar Connections</h2>
        <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
          Connect your calendars to automatically sync meetings and find contacts you meet with.
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
                    <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                      <span>{formatLastSync(connection.last_sync)}</span>
                      {connection.last_error && (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs dark:bg-red-900/30 dark:text-red-400">
                          <AlertCircle className="w-3 h-3" />
                          Error
                        </span>
                      )}
                      {!connection.sync_enabled && (
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs dark:bg-gray-700 dark:text-gray-400">
                          Paused
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
                    title="Sync now"
                  >
                    <RefreshCw className={`w-4 h-4 ${syncing[connection.id] ? 'animate-spin' : ''}`} />
                    {syncing[connection.id] ? 'Syncing...' : 'Sync'}
                  </button>
                  <button
                    onClick={() => setShowAddModal(connection)}
                    className="btn-secondary text-sm p-2"
                    title="Edit"
                  >
                    <Edit2 className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => handleDelete(connection.id, connection.name)}
                    className="btn-secondary text-sm p-2 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                    title="Delete"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-500 dark:text-gray-400">
            No calendar connections yet. Add one below to start syncing your meetings.
          </p>
        )}
      </div>

      {/* Add connection section */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Add Connection</h2>
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
              <p className="font-medium dark:text-gray-100">Connect Google Calendar</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Sign in with your Google account
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
              <p className="font-medium dark:text-gray-100">Add CalDAV Calendar</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                iCloud, Fastmail, Nextcloud, etc.
              </p>
            </div>
          </button>
        </div>
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
            setSuccessMessage('Connection updated successfully.');
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
      setError('Please fill in server URL, username, and password.');
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
        setError(response.data?.message || 'Connection test failed.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Connection test failed.');
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    if (!name) {
      setError('Please enter a name for this connection.');
      return;
    }
    if (!tested || calendars.length === 0) {
      setError('Please test the connection and select a calendar first.');
      return;
    }
    if (!selectedCalendar) {
      setError('Please select a calendar.');
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
      setError(err.response?.data?.message || 'Failed to save connection.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 dark:bg-gray-800">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold dark:text-gray-100">Add CalDAV Calendar</h3>
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

          <div>
            <label className="label mb-1">Connection name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="input"
              placeholder="e.g., iCloud Calendar"
            />
          </div>

          <div>
            <label className="label mb-1">Server URL</label>
            <input
              type="url"
              value={url}
              onChange={(e) => { setUrl(e.target.value); setTested(false); }}
              className="input"
              placeholder="https://caldav.example.com"
            />
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              For iCloud: caldav.icloud.com, Fastmail: caldav.fastmail.com/dav
            </p>
          </div>

          <div>
            <label className="label mb-1">Username</label>
            <input
              type="text"
              value={username}
              onChange={(e) => { setUsername(e.target.value); setTested(false); }}
              className="input"
              placeholder="your-email@example.com"
            />
          </div>

          <div>
            <label className="label mb-1">Password / App password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => { setPassword(e.target.value); setTested(false); }}
              className="input"
              placeholder="App-specific password"
            />
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              For iCloud, use an app-specific password from appleid.apple.com
            </p>
          </div>

          <button
            onClick={handleTest}
            disabled={testing || !url || !username || !password}
            className="btn-secondary w-full"
          >
            {testing ? 'Testing...' : tested ? 'Re-test Connection' : 'Test Connection'}
          </button>

          {tested && calendars.length > 0 && (
            <div>
              <label className="label mb-1">Select calendar</label>
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

          {tested && calendars.length === 0 && (
            <p className="text-sm text-yellow-600 dark:text-yellow-400">
              No calendars found. Please check your credentials and server URL.
            </p>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
          <button onClick={onClose} className="btn-secondary">
            Cancel
          </button>
          <button
            onClick={handleSave}
            disabled={saving || !tested || calendars.length === 0}
            className="btn-primary"
          >
            {saving ? 'Saving...' : 'Save'}
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
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  // CalDAV-specific fields
  const [url, setUrl] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [testing, setTesting] = useState(false);
  const [tested, setTested] = useState(true); // Assume tested on edit

  const isCalDAV = connection.provider === 'caldav';
  const isGoogle = connection.provider === 'google';

  const handleTestCalDAV = async () => {
    if (!url || !username || !password) {
      setError('Please fill in server URL, username, and password to test.');
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
        setError(response.data?.message || 'Connection test failed.');
      }
    } catch (err) {
      setTested(false);
      setError(err.response?.data?.message || 'Connection test failed.');
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    if (!name.trim()) {
      setError('Please enter a name for this connection.');
      return;
    }

    // If CalDAV credentials changed, must be tested
    if (isCalDAV && (url || username || password) && !tested) {
      setError('Please test the updated credentials before saving.');
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
      };

      // Include CalDAV credentials if any were changed
      if (isCalDAV && url && username && password) {
        data.credentials = { url, username, password };
      }

      await onSave(connection.id, data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to save connection.');
      setSaving(false);
    }
  };

  const syncFromOptions = [
    { value: 30, label: '30 days' },
    { value: 60, label: '60 days' },
    { value: 90, label: '90 days' },
    { value: 180, label: '180 days' },
  ];

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 dark:bg-gray-800">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 className="text-lg font-semibold dark:text-gray-100">Edit Connection</h3>
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
                (credentials managed via Google OAuth)
              </span>
            )}
          </div>

          {/* Connection name */}
          <div>
            <label className="label mb-1">Connection name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="input"
              placeholder="e.g., Work Calendar"
            />
          </div>

          {/* Sync enabled toggle */}
          <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <div>
              <p className="font-medium dark:text-gray-100">Sync enabled</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">Automatically sync calendar events</p>
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
              <p className="font-medium dark:text-gray-100">Auto-log meetings</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">Automatically create activities from meetings</p>
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

          {/* Sync from dropdown */}
          <div>
            <label className="label mb-1">Sync events from</label>
            <select
              value={syncFromDays}
              onChange={(e) => setSyncFromDays(Number(e.target.value))}
              className="input"
            >
              {syncFromOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  Past {option.label}
                </option>
              ))}
            </select>
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Events older than this will not be synced
            </p>
          </div>

          {/* CalDAV credential update section */}
          {isCalDAV && (
            <div className="pt-4 border-t border-gray-200 dark:border-gray-700">
              <h4 className="font-medium mb-3 dark:text-gray-100">Update credentials (optional)</h4>
              <p className="text-sm text-gray-500 mb-3 dark:text-gray-400">
                Leave blank to keep current credentials. Fill in all fields to update.
              </p>

              <div className="space-y-3">
                <div>
                  <label className="label mb-1">Server URL</label>
                  <input
                    type="url"
                    value={url}
                    onChange={(e) => { setUrl(e.target.value); setTested(false); }}
                    className="input"
                    placeholder="https://caldav.example.com"
                  />
                </div>

                <div>
                  <label className="label mb-1">Username</label>
                  <input
                    type="text"
                    value={username}
                    onChange={(e) => { setUsername(e.target.value); setTested(false); }}
                    className="input"
                    placeholder="your-email@example.com"
                  />
                </div>

                <div>
                  <label className="label mb-1">Password / App password</label>
                  <input
                    type="password"
                    value={password}
                    onChange={(e) => { setPassword(e.target.value); setTested(false); }}
                    className="input"
                    placeholder="New app-specific password"
                  />
                </div>

                {(url || username || password) && (
                  <button
                    onClick={handleTestCalDAV}
                    disabled={testing || !url || !username || !password}
                    className="btn-secondary w-full"
                  >
                    {testing ? 'Testing...' : tested ? 'Credentials verified' : 'Test new credentials'}
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Info for Google */}
          {isGoogle && (
            <div className="p-3 bg-gray-50 border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600">
              <p className="text-sm text-gray-600 dark:text-gray-300">
                To change Google Calendar credentials, delete this connection and reconnect with Google OAuth.
              </p>
            </div>
          )}
        </div>

        <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700">
          <button onClick={onClose} className="btn-secondary">
            Cancel
          </button>
          <button
            onClick={handleSave}
            disabled={saving}
            className="btn-primary"
          >
            {saving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ConnectionsTab Component - Container for Calendars, CardDAV, and Slack subtabs
function ConnectionsTab({
  activeSubtab, setActiveSubtab,
  // CardDAV props
  appPasswords, appPasswordsLoading, carddavUrls, config,
  newPasswordName, setNewPasswordName, handleCreateAppPassword, creatingPassword,
  newPassword, setNewPassword, copyNewPassword, passwordCopied,
  handleDeleteAppPassword, formatDate, copyCarddavUrl,
  // Slack props
  slackConnected, slackWorkspaceName, handleConnectSlack, handleDisconnectSlack,
  disconnectingSlack, webhookTestMessage, slackChannels, slackUsers, slackTargets,
  loadingSlackData, handleToggleSlackTarget, handleSaveSlackTargets, savingSlackTargets,
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
                  ? 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-400'
                  : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'
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
      {activeSubtab === 'carddav' && (
        <ConnectionsCardDAVSubtab
          appPasswords={appPasswords}
          appPasswordsLoading={appPasswordsLoading}
          carddavUrls={carddavUrls}
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
          copyCarddavUrl={copyCarddavUrl}
        />
      )}
      {activeSubtab === 'slack' && (
        <ConnectionsSlackSubtab
          slackConnected={slackConnected}
          slackWorkspaceName={slackWorkspaceName}
          handleConnectSlack={handleConnectSlack}
          handleDisconnectSlack={handleDisconnectSlack}
          disconnectingSlack={disconnectingSlack}
          webhookTestMessage={webhookTestMessage}
          slackChannels={slackChannels}
          slackUsers={slackUsers}
          slackTargets={slackTargets}
          loadingSlackData={loadingSlackData}
          handleToggleSlackTarget={handleToggleSlackTarget}
          handleSaveSlackTargets={handleSaveSlackTargets}
          savingSlackTargets={savingSlackTargets}
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

// ConnectionsCardDAVSubtab - CardDAV sync configuration
function ConnectionsCardDAVSubtab({
  appPasswords, appPasswordsLoading, carddavUrls, config,
  newPasswordName, setNewPasswordName, handleCreateAppPassword, creatingPassword,
  newPassword, setNewPassword, copyNewPassword, passwordCopied,
  handleDeleteAppPassword, formatDate, copyCarddavUrl,
}) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">CardDAV sync</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Sync your contacts with apps like Apple Contacts, Android Contacts, or Thunderbird using CardDAV.
      </p>

      {appPasswordsLoading ? (
        <div className="animate-pulse">
          <div className="h-10 bg-gray-200 rounded mb-3 dark:bg-gray-700"></div>
          <div className="h-24 bg-gray-200 rounded dark:bg-gray-700"></div>
        </div>
      ) : (
        <div className="space-y-4">
          {carddavUrls && (
            <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-3">
              <h3 className="font-medium text-sm dark:text-gray-200">Connection details</h3>
              <div>
                <label className="text-xs text-gray-500 dark:text-gray-400">Server URL (for most apps)</label>
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
                    title="Copy URL"
                  >
                    Copy
                  </button>
                </div>
              </div>
              <div>
                <label className="text-xs text-gray-500 dark:text-gray-400">Username</label>
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
                    title="Copy username"
                  >
                    Copy
                  </button>
                </div>
              </div>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Use one of the app passwords below instead of your regular password.
              </p>
            </div>
          )}

          <form onSubmit={handleCreateAppPassword} className="flex gap-2">
            <input
              type="text"
              value={newPasswordName}
              onChange={(e) => setNewPasswordName(e.target.value)}
              placeholder="Password name (e.g., iPhone Contacts)"
              className="input flex-1"
              disabled={creatingPassword}
            />
            <button
              type="submit"
              disabled={creatingPassword || !newPasswordName.trim()}
              className="btn-primary whitespace-nowrap"
            >
              {creatingPassword ? 'Creating...' : 'Create password'}
            </button>
          </form>

          {newPassword && (
            <div className="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
              <p className="text-sm text-green-800 font-medium mb-2 dark:text-green-300">
                Your new app password (copy it now, it won&apos;t be shown again):
              </p>
              <div className="flex gap-2">
                <input
                  type="text"
                  readOnly
                  value={newPassword}
                  className="input flex-1 font-mono text-sm bg-white dark:bg-gray-700"
                  onClick={(e) => e.target.select()}
                />
                <button onClick={copyNewPassword} className="btn-primary whitespace-nowrap">
                  {passwordCopied ? 'Copied!' : 'Copy'}
                </button>
              </div>
              <button
                onClick={() => setNewPassword(null)}
                className="text-xs text-green-700 mt-2 hover:underline dark:text-green-400"
              >
                I&apos;ve saved this password, hide it
              </button>
            </div>
          )}

          {appPasswords.length > 0 && (
            <div>
              <h3 className="text-sm font-medium mb-2 dark:text-gray-200">Your app passwords</h3>
              <div className="space-y-2">
                {appPasswords.map((password) => (
                  <div
                    key={password.uuid}
                    className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700"
                  >
                    <div>
                      <p className="font-medium text-sm dark:text-gray-200">{password.name}</p>
                      <p className="text-xs text-gray-500 dark:text-gray-400">
                        Created {formatDate(password.created)} · Last used {formatDate(password.last_used)}
                      </p>
                    </div>
                    <button
                      onClick={() => handleDeleteAppPassword(password.uuid, password.name)}
                      className="text-red-600 hover:text-red-700 text-sm font-medium dark:text-red-400 dark:hover:text-red-300"
                    >
                      Revoke
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {appPasswords.length === 0 && !newPassword && (
            <p className="text-sm text-gray-500 dark:text-gray-400">
              No app passwords yet. Create one to start syncing your contacts.
            </p>
          )}
        </div>
      )}
    </div>
  );
}

// ConnectionsSlackSubtab - Slack connection management
function ConnectionsSlackSubtab({
  slackConnected, slackWorkspaceName, handleConnectSlack, handleDisconnectSlack,
  disconnectingSlack, webhookTestMessage, slackChannels, slackUsers, slackTargets,
  loadingSlackData, handleToggleSlackTarget, handleSaveSlackTargets, savingSlackTargets,
}) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Slack connection</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Connect your Slack workspace to receive notifications and use Slack commands.
      </p>

      {slackConnected ? (
        <div className="space-y-4">
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-green-900 dark:text-green-300">Connected to Slack</p>
                {slackWorkspaceName && (
                  <p className="text-sm text-green-700 dark:text-green-400">Workspace: {slackWorkspaceName}</p>
                )}
              </div>
              <button
                onClick={handleDisconnectSlack}
                disabled={disconnectingSlack}
                className="btn-secondary text-sm"
              >
                {disconnectingSlack ? 'Disconnecting...' : 'Disconnect'}
              </button>
            </div>
          </div>

          {webhookTestMessage && (
            <p className={`text-sm ${webhookTestMessage.includes('successfully') || webhookTestMessage.includes('disconnected') ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
              {webhookTestMessage}
            </p>
          )}

          {/* Notification targets configuration */}
          <div className="mt-4 p-4 border border-gray-200 rounded-lg dark:border-gray-700">
            <h3 className="font-medium mb-3 dark:text-gray-200">Notification targets</h3>
            <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
              Choose where to send Slack notifications. You can select multiple channels and users.
            </p>

            {loadingSlackData ? (
              <p className="text-sm text-gray-500 dark:text-gray-400">Loading channels and users...</p>
            ) : (
              <>
                {slackChannels.length > 0 && (
                  <div className="mb-4">
                    <h4 className="text-sm font-medium mb-2 dark:text-gray-300">Channels</h4>
                    <div className="space-y-2 max-h-48 overflow-y-auto">
                      {slackChannels.map((channel) => (
                        <label
                          key={channel.id}
                          className="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer dark:hover:bg-gray-800"
                        >
                          <input
                            type="checkbox"
                            checked={slackTargets.includes(channel.id)}
                            onChange={() => handleToggleSlackTarget(channel.id)}
                            className="mr-2 cursor-pointer"
                          />
                          <span className="text-sm dark:text-gray-300">{channel.name}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                )}

                {slackUsers.length > 0 && (
                  <div className="mb-4">
                    <h4 className="text-sm font-medium mb-2 dark:text-gray-300">Direct messages</h4>
                    <div className="space-y-2 max-h-48 overflow-y-auto">
                      {slackUsers.map((user) => (
                        <label
                          key={user.id}
                          className="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer dark:hover:bg-gray-800"
                        >
                          <input
                            type="checkbox"
                            checked={slackTargets.includes(user.id)}
                            onChange={() => handleToggleSlackTarget(user.id)}
                            className="mr-2 cursor-pointer"
                          />
                          <span className="text-sm dark:text-gray-300">
                            {user.name}
                            {user.is_me && <span className="text-gray-500 ml-1 dark:text-gray-500">(you)</span>}
                          </span>
                        </label>
                      ))}
                    </div>
                  </div>
                )}

                {slackChannels.length === 0 && slackUsers.length === 0 && !loadingSlackData && (
                  <p className="text-sm text-gray-500 mb-4 dark:text-gray-400">
                    No channels or users found. Make sure the Slack app has the necessary permissions.
                  </p>
                )}

                <button
                  onClick={handleSaveSlackTargets}
                  disabled={savingSlackTargets}
                  className="btn-primary text-sm mt-4"
                >
                  {savingSlackTargets ? 'Saving...' : 'Save targets'}
                </button>
              </>
            )}
          </div>
        </div>
      ) : (
        <div className="space-y-3">
          <button
            onClick={handleConnectSlack}
            className="btn-primary w-full"
            disabled={disconnectingSlack}
          >
            Connect Slack
          </button>
          {webhookTestMessage && (
            <p className={`text-sm ${webhookTestMessage.includes('successfully') ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
              {webhookTestMessage}
            </p>
          )}
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Connect your Slack workspace to receive daily reminder notifications. You'll be able to message channels or receive direct messages.
          </p>
        </div>
      )}
    </div>
  );
}

// Sync Tab Component - Calendar feed subscription only
function SyncTab({
  icalUrl, webcalUrl, icalLoading, icalCopied, copyIcalUrl,
  regenerateIcalToken, regenerating,
}) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Calendar subscription</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Subscribe to your important dates in any calendar app (Apple Calendar, Google Calendar, Outlook, etc.)
      </p>

      {icalLoading ? (
        <div className="animate-pulse">
          <div className="h-10 bg-gray-200 rounded mb-3 dark:bg-gray-700"></div>
          <div className="h-9 bg-gray-200 rounded w-24 dark:bg-gray-700"></div>
        </div>
      ) : (
        <div className="space-y-4">
          <div>
            <label className="label mb-1">Your calendar feed URL</label>
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
                title="Copy URL"
              >
                {icalCopied ? (
                  <span className="flex items-center gap-1">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    Copied
                  </span>
                ) : (
                  <span className="flex items-center gap-1">
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                    </svg>
                    Copy
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
                Subscribe in calendar app
              </span>
            </a>

            <button
              onClick={regenerateIcalToken}
              disabled={regenerating}
              className="btn-secondary"
            >
              {regenerating ? 'Regenerating...' : 'Regenerate URL'}
            </button>
          </div>

          <p className="text-xs text-gray-500 dark:text-gray-400">
            Keep this URL private. Anyone with access to it can see your important dates.
            If you think it has been compromised, click "Regenerate URL" to get a new one.
          </p>
        </div>
      )}
    </div>
  );
}

// Notifications Tab Component - Channel toggles and preferences only
function NotificationsTab({
  notificationsLoading, notificationChannels, toggleChannel, savingChannels,
  slackConnected, notificationTime, handleNotificationTimeChange, savingTime,
  mentionNotifications, handleMentionNotificationsChange, savingMentionPref
}) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4 dark:text-gray-100">Notifications</h2>
      <p className="text-sm text-gray-600 mb-4 dark:text-gray-400">
        Choose how you want to receive daily reminders about your important dates.
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
              <p className="font-medium dark:text-gray-200">Email</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">Receive daily digest emails</p>
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

          {/* Slack channel */}
          <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
            <div>
              <p className="font-medium dark:text-gray-200">Slack</p>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {slackConnected ? 'Receive notifications in Slack' : 'Connect Slack in Connections to enable'}
              </p>
            </div>
            {slackConnected ? (
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={notificationChannels.includes('slack')}
                  onChange={() => toggleChannel('slack')}
                  disabled={savingChannels}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent-600 dark:bg-gray-600 dark:peer-checked:bg-accent-500"></div>
              </label>
            ) : (
              <Link
                to="/settings?tab=connections&subtab=slack"
                className="text-sm text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300"
              >
                Connect Slack
              </Link>
            )}
          </div>

          {/* Notification time */}
          <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <label className="label mb-1">Notification time (UTC)</label>
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
              Choose the UTC time when you want to receive your daily reminder digest. Reminders are sent within a 1-hour window of your selected time.
            </p>
          </div>

          {/* Mention notifications preference */}
          <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <label className="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300">Mention notifications</label>
            <select
              value={mentionNotifications}
              onChange={(e) => handleMentionNotificationsChange(e.target.value)}
              className="input"
              disabled={savingMentionPref}
            >
              <option value="digest">Include in daily digest (default)</option>
              <option value="immediate">Send immediately</option>
              <option value="never">Don&apos;t notify me</option>
            </select>
            <p className="text-xs text-gray-500 mt-1 dark:text-gray-400">
              Choose when to receive notifications when someone @mentions you in a note.
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
    description: 'Apple Contacts, Outlook, Android',
    icon: FileCode,
    component: VCardImport,
  },
  {
    id: 'google',
    name: 'Google Contacts',
    description: 'CSV export from Google',
    icon: FileSpreadsheet,
    component: GoogleContactsImport,
  },
  {
    id: 'monica',
    name: 'Monica CRM',
    description: 'SQL export from Monica',
    icon: Database,
    component: MonicaImport,
  },
];

function DataTab() {
  const [activeImportType, setActiveImportType] = useState('vcard');
  
  const handleExport = (format) => {
    if (format === 'vcard') {
      window.location.href = '/wp-json/prm/v1/export/vcard';
    } else if (format === 'google-csv') {
      window.location.href = '/wp-json/prm/v1/export/google-csv';
    }
  };
  
  const ActiveImportComponent = importTypes.find(t => t.id === activeImportType)?.component;

  return (
    <div className="space-y-6">
      {/* Import Section */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Import data</h2>
        <p className="text-sm text-gray-600 mb-6">
          Import your contacts from various sources. Existing contacts with matching names will be updated instead of duplicated.
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
        <h2 className="text-lg font-semibold mb-4">Export data</h2>
        <p className="text-sm text-gray-600 mb-6">
          Export all your contacts in a format compatible with other contact management systems.
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
              <p className="font-medium">Export as vCard (.vcf)</p>
              <p className="text-sm text-gray-500">
                Compatible with Apple Contacts, Outlook, Android, and most contact apps
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
              <p className="font-medium">Export as Google Contacts CSV</p>
              <p className="text-sm text-gray-500">
                Import directly into Google Contacts or other CSV-compatible systems
              </p>
            </div>
            <Download className="w-5 h-5 text-gray-400" />
          </button>
        </div>
      </div>
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
        <h2 className="text-lg font-semibold mb-4">User management</h2>
        <div className="space-y-3">
          <Link
            to="/settings/user-approval"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">User approval</p>
            <p className="text-sm text-gray-500">Approve or deny access for new users</p>
          </Link>
        </div>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Configuration</h2>
        <div className="space-y-3">
          <Link
            to="/settings/relationship-types"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Relationship types</p>
            <p className="text-sm text-gray-500">Manage relationship types and their inverse mappings</p>
          </Link>
          <Link
            to="/settings/labels"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Labels</p>
            <p className="text-sm text-gray-500">Manage labels for people and organizations</p>
          </Link>
        </div>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">System actions</h2>
        <div className="space-y-3">
          <button
            onClick={handleTriggerReminders}
            disabled={triggeringReminders}
            className="w-full text-left p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <p className="font-medium">Trigger reminders</p>
            <p className="text-sm text-gray-500">
              {triggeringReminders ? 'Sending...' : 'Manually send reminders for today'}
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
            <p className="font-medium">Reschedule cron jobs</p>
            <p className="text-sm text-gray-500">
              {reschedulingCron ? 'Rescheduling...' : 'Reschedule all user reminder cron jobs'}
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

// About Tab Component
function AboutTab({ config }) {
  return (
    <div className="card p-6">
      <h2 className="text-lg font-semibold mb-4">About {APP_NAME}</h2>
      <div className="space-y-4">
        <div>
          <p className="text-sm text-gray-600">
            Version {config.version || '1.0.0'}
          </p>
        </div>
        <div className="pt-4 border-t border-gray-200">
          <p className="text-sm text-gray-600">
            {APP_NAME} is a personal CRM system that helps you manage your contacts, 
            track important dates, and maintain meaningful relationships.
          </p>
        </div>
        <div className="pt-4 border-t border-gray-200">
          <p className="text-sm text-gray-500">
            Built with WordPress, React, and Tailwind CSS.
          </p>
        </div>
      </div>
    </div>
  );
}
