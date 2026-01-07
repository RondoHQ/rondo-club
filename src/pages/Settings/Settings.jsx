import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { APP_NAME } from '@/constants/app';
import apiClient from '@/api/client';
import { prmApi } from '@/api/client';

export default function Settings() {
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;
  
  const [icalUrl, setIcalUrl] = useState('');
  const [webcalUrl, setWebcalUrl] = useState('');
  const [icalLoading, setIcalLoading] = useState(true);
  const [icalCopied, setIcalCopied] = useState(false);
  const [regenerating, setRegenerating] = useState(false);
  
  // Notification channels state
  const [notificationChannels, setNotificationChannels] = useState([]);
  const [slackWebhook, setSlackWebhook] = useState('');
  const [slackConnected, setSlackConnected] = useState(false);
  const [slackWorkspaceName, setSlackWorkspaceName] = useState('');
  const [notificationTime, setNotificationTime] = useState('09:00');
  const [notificationsLoading, setNotificationsLoading] = useState(true);
  const [savingChannels, setSavingChannels] = useState(false);
  const [savingWebhook, setSavingWebhook] = useState(false);
  const [savingTime, setSavingTime] = useState(false);
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
      } catch (error) {
        console.error('Failed to fetch iCal URL:', error);
      } finally {
        setIcalLoading(false);
      }
    };
    fetchIcalUrl();
  }, []);
  
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
    } catch (error) {
      console.error('Failed to fetch Slack data:', error);
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
        
        // Check Slack OAuth status
        const slackStatus = await prmApi.getSlackStatus();
        const isConnected = slackStatus.data.connected || false;
        setSlackConnected(isConnected);
        setSlackWorkspaceName(slackStatus.data.workspace_name || '');
        
        // If connected, fetch channels/users and targets
        if (isConnected) {
          await fetchSlackData();
        }
      } catch (error) {
        console.error('Failed to fetch notification channels:', error);
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
    const slackConnected = params.get('slack_connected');
    const slackError = params.get('slack_error');
    
    if (slackConnected === '1') {
      setWebhookTestMessage('Slack connected successfully!');
      // Refresh Slack status - fetchSlackData will be called by the useEffect when slackConnected changes
      prmApi.getSlackStatus().then(response => {
        setSlackConnected(response.data.connected || false);
        setSlackWorkspaceName(response.data.workspace_name || '');
      });
      // Clean URL
      window.history.replaceState({}, '', window.location.pathname);
    } else if (slackError) {
      setWebhookTestMessage(`Slack connection failed: ${slackError}`);
      // Clean URL
      window.history.replaceState({}, '', window.location.pathname);
    }
  }, []);
  
  const handleConnectSlack = async () => {
    try {
      // Get OAuth URL from backend
      const response = await apiClient.get('/prm/v1/slack/oauth/authorize');
      // Redirect to Slack OAuth URL
      window.location.href = response.data.oauth_url;
    } catch (error) {
      console.error('Failed to get Slack OAuth URL:', error);
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
      // Also disable Slack channel if enabled
      if (notificationChannels.includes('slack')) {
        await toggleChannel('slack');
      }
    } catch (error) {
      console.error('Failed to disconnect Slack:', error);
      alert(error.response?.data?.message || 'Failed to disconnect Slack');
    } finally {
      setDisconnectingSlack(false);
    }
  };
  
  // Handle Slack target selection
  const handleToggleSlackTarget = (targetId) => {
    const newTargets = slackTargets.includes(targetId)
      ? slackTargets.filter(id => id !== targetId)
      : [...slackTargets, targetId];
    setSlackTargets(newTargets);
  };
  
  // Save Slack targets
  const handleSaveSlackTargets = async () => {
    setSavingSlackTargets(true);
    try {
      await prmApi.updateSlackTargets(slackTargets);
      setWebhookTestMessage('Notification targets saved successfully');
      setTimeout(() => setWebhookTestMessage(''), 3000);
    } catch (error) {
      console.error('Failed to save Slack targets:', error);
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
    } catch (error) {
      console.error('Failed to copy:', error);
    }
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
    } catch (error) {
      console.error('Failed to regenerate token:', error);
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
      console.error('Failed to update notification channels:', error);
      alert(error.response?.data?.message || 'Failed to update notification channels');
    } finally {
      setSavingChannels(false);
    }
  };
  
  const handleNotificationTimeChange = async (time) => {
    setNotificationTime(time);
    setSavingTime(true);
    
    try {
      await prmApi.updateNotificationTime(time);
    } catch (error) {
      console.error('Failed to update notification time:', error);
      alert(error.response?.data?.message || 'Failed to update notification time');
      // Revert on error
      const response = await prmApi.getNotificationChannels();
      setNotificationTime(response.data.notification_time || '09:00');
    } finally {
      setSavingTime(false);
    }
  };
  
  const handleSlackWebhookChange = async (webhook) => {
    setSlackWebhook(webhook);
    setWebhookTestMessage('');
    
    if (!webhook) {
      // Remove webhook if empty
      setSavingWebhook(true);
      try {
        await prmApi.updateSlackWebhook('');
        setWebhookTestMessage('Webhook removed');
        // Also disable Slack channel if enabled
        if (notificationChannels.includes('slack')) {
          await toggleChannel('slack');
        }
      } catch (error) {
        console.error('Failed to remove webhook:', error);
        alert(error.response?.data?.message || 'Failed to remove webhook');
      } finally {
        setSavingWebhook(false);
      }
      return;
    }
    
    // Validate and save webhook
    setSavingWebhook(true);
    try {
      const response = await prmApi.updateSlackWebhook(webhook);
      setWebhookTestMessage(response.data.message || 'Webhook configured successfully');
    } catch (error) {
      console.error('Failed to update webhook:', error);
      setWebhookTestMessage(error.response?.data?.message || 'Failed to configure webhook');
    } finally {
      setSavingWebhook(false);
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
      console.error('Failed to trigger reminders:', error);
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
      console.error('Failed to reschedule cron jobs:', error);
      setCronMessage(error.response?.data?.message || 'Failed to reschedule cron jobs. Please check server logs.');
    } finally {
      setReschedulingCron(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Calendar Subscription</h2>
        <p className="text-sm text-gray-600 mb-4">
          Subscribe to your important dates in any calendar app (Apple Calendar, Google Calendar, Outlook, etc.)
        </p>
        
        {icalLoading ? (
          <div className="animate-pulse">
            <div className="h-10 bg-gray-200 rounded mb-3"></div>
            <div className="h-9 bg-gray-200 rounded w-24"></div>
          </div>
        ) : (
          <div className="space-y-4">
            <div>
              <label className="label mb-1">Your Calendar Feed URL</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  readOnly
                  value={icalUrl}
                  className="input flex-1 text-sm font-mono bg-gray-50"
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
              <a
                href={webcalUrl}
                className="btn-primary"
              >
                <span className="flex items-center gap-1">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Subscribe in Calendar App
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
            
            <p className="text-xs text-gray-500">
              Keep this URL private. Anyone with access to it can see your important dates.
              If you think it has been compromised, click "Regenerate URL" to get a new one.
            </p>
          </div>
        )}
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Data</h2>
        <div className="space-y-3">
          <Link
            to="/settings/import"
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
          >
            <p className="font-medium">Import Data</p>
            <p className="text-sm text-gray-500">Import contacts from Monica CRM or other sources</p>
          </Link>
          <Link
            to="/settings/export"
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
          >
            <p className="font-medium">Export Data</p>
            <p className="text-sm text-gray-500">Export all contacts as vCard or Google Contacts CSV</p>
          </Link>
        </div>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Notifications</h2>
        <p className="text-sm text-gray-600 mb-4">
          Choose how you want to receive daily reminders about your important dates.
        </p>
        
        {notificationsLoading ? (
          <div className="animate-pulse">
            <div className="h-10 bg-gray-200 rounded mb-3"></div>
            <div className="h-10 bg-gray-200 rounded"></div>
          </div>
        ) : (
          <div className="space-y-4">
            {/* Email channel */}
            <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200">
              <div>
                <p className="font-medium">Email</p>
                <p className="text-sm text-gray-500">Receive daily digest emails</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={notificationChannels.includes('email')}
                  onChange={() => toggleChannel('email')}
                  disabled={savingChannels}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>
            
            {/* Slack channel */}
            <div className="flex items-center justify-between p-3 rounded-lg border border-gray-200">
              <div>
                <p className="font-medium">Slack</p>
                <p className="text-sm text-gray-500">Receive notifications in Slack</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={notificationChannels.includes('slack')}
                  onChange={() => toggleChannel('slack')}
                  disabled={savingChannels || !slackConnected}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>
            
            {/* Slack OAuth connection */}
            <div>
              {slackConnected ? (
                <div className="space-y-3">
                  <div className="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium text-green-900">Connected to Slack</p>
                        {slackWorkspaceName && (
                          <p className="text-sm text-green-700">Workspace: {slackWorkspaceName}</p>
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
                    <p className={`text-sm ${webhookTestMessage.includes('successfully') || webhookTestMessage.includes('disconnected') ? 'text-green-600' : 'text-red-600'}`}>
                      {webhookTestMessage}
                    </p>
                  )}
                  
                  {/* Notification targets configuration */}
                  {notificationChannels.includes('slack') && (
                    <div className="mt-4 p-4 border border-gray-200 rounded-lg">
                      <h3 className="font-medium mb-3">Notification targets</h3>
                      <p className="text-sm text-gray-600 mb-4">
                        Choose where to send Slack notifications. You can select multiple channels and users.
                      </p>
                      
                      {loadingSlackData ? (
                        <p className="text-sm text-gray-500">Loading channels and users...</p>
                      ) : (
                        <>
                          {/* Channels */}
                          {slackChannels.length > 0 && (
                            <div className="mb-4">
                              <h4 className="text-sm font-medium mb-2">Channels</h4>
                              <div className="space-y-2 max-h-48 overflow-y-auto">
                                {slackChannels.map((channel) => (
                                  <label
                                    key={channel.id}
                                    className="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer"
                                  >
                                    <input
                                      type="checkbox"
                                      checked={slackTargets.includes(channel.id)}
                                      onChange={() => handleToggleSlackTarget(channel.id)}
                                      className="mr-2 cursor-pointer"
                                    />
                                    <span className="text-sm">{channel.name}</span>
                                  </label>
                                ))}
                              </div>
                            </div>
                          )}
                          
                          {/* Users */}
                          {slackUsers.length > 0 && (
                            <div className="mb-4">
                              <h4 className="text-sm font-medium mb-2">Direct messages</h4>
                              <div className="space-y-2 max-h-48 overflow-y-auto">
                                {slackUsers.map((user) => (
                                  <label
                                    key={user.id}
                                    className="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer"
                                  >
                                    <input
                                      type="checkbox"
                                      checked={slackTargets.includes(user.id)}
                                      onChange={() => handleToggleSlackTarget(user.id)}
                                      className="mr-2 cursor-pointer"
                                    />
                                    <span className="text-sm">
                                      {user.name}
                                      {user.is_me && <span className="text-gray-500 ml-1">(you)</span>}
                                    </span>
                                  </label>
                                ))}
                              </div>
                            </div>
                          )}
                          
                          {slackChannels.length === 0 && slackUsers.length === 0 && !loadingSlackData && (
                            <p className="text-sm text-gray-500 mb-4">
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
                  )}
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
                    <p className={`text-sm ${webhookTestMessage.includes('successfully') ? 'text-green-600' : 'text-red-600'}`}>
                      {webhookTestMessage}
                    </p>
                  )}
                  <p className="text-xs text-gray-500">
                    Connect your Slack workspace to receive daily reminder notifications. You'll be able to message channels or receive direct messages.
                  </p>
                </div>
              )}
            </div>
            
            {/* Legacy webhook support (hidden but kept for backward compatibility) */}
            {slackWebhook && !slackConnected && (
              <div className="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p className="text-sm text-yellow-800">
                  <strong>Legacy webhook detected.</strong> Please connect via OAuth for better functionality. Your webhook will continue to work until you connect via OAuth.
                </p>
              </div>
            )}
            
            {/* Notification time */}
            <div>
              <label className="label mb-1">Notification Time (UTC)</label>
              <input
                type="time"
                value={notificationTime}
                onChange={(e) => handleNotificationTimeChange(e.target.value)}
                className="input"
                disabled={savingTime}
              />
              {notificationTime && (
                <div className="mt-2 p-2 bg-gray-50 rounded text-sm">
                  <p className="text-gray-700">
                    <span className="font-medium">UTC:</span> {notificationTime}
                  </p>
                  <p className="text-gray-700 mt-1">
                    <span className="font-medium">Your time ({Intl.DateTimeFormat().resolvedOptions().timeZone}):</span>{' '}
                    {(() => {
                      try {
                        // Parse UTC time and convert to local timezone
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
              <p className="text-xs text-gray-500 mt-1">
                Choose the UTC time when you want to receive your daily reminder digest. Reminders are sent within a 1-hour window of your selected time.
              </p>
            </div>
          </div>
        )}
      </div>
      
      {isAdmin && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold mb-4">Administration</h2>
          <div className="space-y-3">
            <Link
              to="/settings/relationship-types"
              className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            >
              <p className="font-medium">Relationship Types</p>
              <p className="text-sm text-gray-500">Manage relationship types and their inverse mappings</p>
            </Link>
            <Link
              to="/settings/user-approval"
              className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            >
              <p className="font-medium">User Approval</p>
              <p className="text-sm text-gray-500">Approve or deny access for new users</p>
            </Link>
            <button
              onClick={handleTriggerReminders}
              disabled={triggeringReminders}
              className="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
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
              className="w-full text-left p-3 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
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
      )}
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">About</h2>
        <p className="text-sm text-gray-600">
          {APP_NAME} v{config.version || '1.0.0'}<br />
          Built with WordPress, React, and Tailwind CSS.
        </p>
      </div>
    </div>
  );
}
