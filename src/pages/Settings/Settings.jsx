import { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { Check, Users, Search, Link as LinkIcon, Loader2, Key, Copy, Database, UserPlus, Wrench, AlertCircle, Wallet, Award } from 'lucide-react';
import { APP_NAME } from '@/constants/app';
import api, { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import PersonAvatar from '@/components/PersonAvatar';
import TabButton from '@/components/TabButton';
import FinanceSettings from '@/pages/Finance/FinanceSettings';
import VOGSettings from '@/pages/VOG/VOGSettings';
import { useClothingSettings, useUpdateClothingSettings } from '@/hooks/useClothing';


// Tab configuration (no icons - using TabButton component)
const TABS = [
  { id: 'appearance', label: 'Club' },
  { id: 'connections', label: 'Koppelingen' },
  { id: 'clothing', label: 'Kleding', requiresClothing: true },
  { id: 'financieel', label: 'Financieel', requiresFinancieel: true },
  { id: 'vog', label: 'VOG', adminOnly: true, requiresVOG: true },
  { id: 'admin', label: 'Beheer', adminOnly: true },
  { id: 'about', label: 'Info' },
];

// Connections subtabs configuration
const CONNECTION_SUBTABS = [
  { id: 'carddav', label: 'CardDAV', icon: Database },
  { id: 'api-access', label: 'API-toegang', icon: Key },
  { id: 'freescout', label: 'FreeScout', icon: LinkIcon },
  { id: 'payment-providers', label: 'Betaalproviders', icon: Wrench },
  { id: 'wallets', label: 'Wallets', icon: Wallet },
];

// Admin subtabs configuration
const ADMIN_SUBTABS = [
  { id: 'users', label: 'Gebruikers', icon: Users },
  { id: 'rollen', label: 'Rollen' },
  { id: 'functies', label: 'Functies' },
  { id: 'welkomstmail', label: 'Welkomstmail' },
  { id: 'anniversaries', label: 'Jubilarissen', icon: Award },
  { id: 'systeem', label: 'Systeem', icon: Wrench },
];

export default function Settings() {
  const { tab: urlTab, subtab: urlSubtab } = useParams();
  const navigate = useNavigate();
  const config = window.rondoConfig || {};
  const { data: currentUser, isLoading: currentUserLoading } = useCurrentUser();
  const isAdmin = (currentUser?.is_admin ?? config.isAdmin) || false;
  const canAccessFinancieel = currentUser?.can_access_financieel ?? false;
  const canAccessVOG = currentUser?.can_access_vog ?? false;
  const canAccessClothing = currentUser?.can_access_clothing ?? false;
  const userId = config.userId;

  // Get active tab from URL or default to 'appearance'
  const activeTab = urlTab || 'appearance';
  // Get active subtab for tabs that support subtabs.
  const activeSubtab = urlSubtab || (activeTab === 'admin' ? 'users' : activeTab === 'connections' ? 'carddav' : null);

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
    if (activeTab === 'admin') {
      navigate(`/settings/admin/${subtab}`);
      return;
    }
    navigate(`/settings/connections/${subtab}`);
  };
  
  // Filter tabs based on capabilities.
  const visibleTabs = TABS.filter((tab) => {
    if (tab.adminOnly && !isAdmin) return false;
    if (tab.requiresClothing && !canAccessClothing) return false;
    if (tab.requiresFinancieel && !canAccessFinancieel) return false;
    if (tab.requiresVOG && !canAccessVOG) return false;
    return true;
  });

  useEffect(() => {
    if (currentUserLoading) {
      return;
    }
    if (!visibleTabs.some((tab) => tab.id === activeTab)) {
      const fallbackTab = visibleTabs[0]?.id || 'appearance';
      navigate(`/settings/${fallbackTab}`, { replace: true });
    }
  }, [activeTab, currentUserLoading, navigate, visibleTabs]);

  // App Passwords state
  const [appPasswords, setAppPasswords] = useState([]);
  const [appPasswordsLoading, setAppPasswordsLoading] = useState(true);
  const [newPasswordName, setNewPasswordName] = useState('');
  const [creatingPassword, setCreatingPassword] = useState(false);
  const [newPassword, setNewPassword] = useState(null);
  const [passwordCopied, setPasswordCopied] = useState(false);
  const [carddavUrls, setCarddavUrls] = useState(null);
  const [clubConfig, setClubConfig] = useState(null);
  const [clubConfigLoading, setClubConfigLoading] = useState(true);
  
  // Manual trigger state (admin only)
  const [reschedulingCron, setReschedulingCron] = useState(false);
  const [cronMessage, setCronMessage] = useState('');

  // Volunteer role classification state
  const [availableRoles, setAvailableRoles] = useState([]);
  const [roleSettings, setRoleSettings] = useState({ player_roles: [], excluded_roles: [] });
  const [roleDefaults, setRoleDefaults] = useState({ default_player_roles: [], default_excluded_roles: [] });
  const [rolesLoading, setRolesLoading] = useState(true);
  const [rolesSaving, setRolesSaving] = useState(false);
  const [rolesMessage, setRolesMessage] = useState('');

  // Welcome email settings state (admin only)
  const [welcomeSettings, setWelcomeSettings] = useState(null);
  const [welcomeSettingsLoading, setWelcomeSettingsLoading] = useState(false);
  const [welcomeSaving, setWelcomeSaving] = useState(false);
  const [welcomeSaved, setWelcomeSaved] = useState(false);

  // Anniversary settings state (admin only)
  const [anniversarySettings, setAnniversarySettings] = useState(null);
  const [anniversarySettingsLoading, setAnniversarySettingsLoading] = useState(false);
  const [anniversarySaving, setAnniversarySaving] = useState(false);
  const [anniversaryMessage, setAnniversaryMessage] = useState('');

  // Capability sync state (admin only)
  const [syncingCapabilities, setSyncingCapabilities] = useState(false);
  const [capabilitySyncMessage, setCapabilitySyncMessage] = useState('');

  // Functie-to-role mapping state (admin only)
  const [availableFuncties, setAvailableFuncties] = useState([]);
  const [functieMapState, setFunctieMapState] = useState({});
  const [functieRoles, setFunctieRoles] = useState([]);
  const [functiesLoading, setFunctiesLoading] = useState(true);
  const [functiesSaving, setFunctiesSaving] = useState(false);
  const [functiesMessage, setFunctiesMessage] = useState('');
  const [commissieMapState, setCommissieMapState] = useState({});
  const [commissieList, setCommissieList] = useState([]);
  const [commissieSaving, setCommissieSaving] = useState(false);
  const [commissieMessage, setCommissieMessage] = useState('');

  // Fetch Applicatiewachtwoorden and CardDAV URLs on mount
  useEffect(() => {
    const fetchAppPasswords = async () => {
      try {
        const [passwordsResponse, urlsResponse, configResponse] = await Promise.all([
          prmApi.getAppPasswords(userId),
          prmApi.getCardDAVUrls(),
          prmApi.getClubConfig(),
        ]);
        setAppPasswords(passwordsResponse.data || []);
        setCarddavUrls(urlsResponse.data);
        setClubConfig(configResponse.data || null);
      } catch {
        // App passwords fetch failed silently
      } finally {
        setAppPasswordsLoading(false);
        setClubConfigLoading(false);
      }
    };
    if (userId) {
      fetchAppPasswords();
    }
  }, [userId]);
  
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

  // Fetch welcome email settings when welkomstmail subtab is active
  useEffect(() => {
    if (activeTab === 'admin' && activeSubtab === 'welkomstmail' && isAdmin && !welcomeSettings) {
      setWelcomeSettingsLoading(true);
      prmApi.getProvisioningSettings()
        .then(res => setWelcomeSettings(res.data))
        .catch(() => {})
        .finally(() => setWelcomeSettingsLoading(false));
    }
  }, [activeTab, activeSubtab, isAdmin, welcomeSettings]);

  // Fetch anniversary settings when jubilees subtab is active
  useEffect(() => {
    if (activeTab === 'admin' && activeSubtab === 'anniversaries' && isAdmin && !anniversarySettings) {
      setAnniversarySettingsLoading(true);
      setAnniversaryMessage('');
      prmApi.getAnniversarySettings()
        .then((res) => setAnniversarySettings(res.data?.milestones || { member: [], volunteer: [] }))
        .catch((error) => setAnniversaryMessage(error.response?.data?.message || 'Kon jubileuminstellingen niet laden.'))
        .finally(() => setAnniversarySettingsLoading(false));
    }
  }, [activeTab, activeSubtab, isAdmin, anniversarySettings]);

  // Fetch functie-to-role mapping on mount (admin only)
  useEffect(() => {
    const fetchFunctieMapping = async () => {
      if (!isAdmin) {
        setFunctiesLoading(false);
        return;
      }
      try {
        const [werkfunctiesResponse, mapResponse, commissieMapResponse] = await Promise.all([
          prmApi.getAvailableWerkfuncties(),
          prmApi.getFunctieCapabilityMap(),
          prmApi.getCommissieCapabilityMap(),
        ]);
        setAvailableFuncties(werkfunctiesResponse.data || []);
        setFunctieMapState(mapResponse.data.map || {});
        setFunctieRoles(mapResponse.data.roles || []);
        setCommissieMapState(commissieMapResponse.data.map || {});
        setCommissieList(commissieMapResponse.data.commissies || []);
      } catch {
        // Mapping fetch failed silently
      } finally {
        setFunctiesLoading(false);
      }
    };
    fetchFunctieMapping();
  }, [isAdmin]);

  // Handle welcome email settings save
  const handleWelcomeSave = async () => {
    setWelcomeSaving(true);
    setWelcomeSaved(false);
    try {
      const res = await prmApi.updateProvisioningSettings(welcomeSettings);
      setWelcomeSettings(res.data);
      setWelcomeSaved(true);
      setTimeout(() => setWelcomeSaved(false), 2000);
    } catch {
      // Handle error silently
    } finally {
      setWelcomeSaving(false);
    }
  };

  const handleAnniversarySave = async () => {
    if (!anniversarySettings) {
      return;
    }
    setAnniversarySaving(true);
    setAnniversaryMessage('');
    try {
      const response = await prmApi.updateAnniversarySettings(anniversarySettings);
      setAnniversarySettings(response.data?.milestones || anniversarySettings);
      setAnniversaryMessage('Jubileuminstellingen opgeslagen.');
    } catch (error) {
      setAnniversaryMessage(error.response?.data?.message || 'Kon jubileuminstellingen niet opslaan.');
    } finally {
      setAnniversarySaving(false);
    }
  };

  // Handle functie-to-role mapping save
  const handleFunctiesSave = async () => {
    setFunctiesSaving(true);
    setFunctiesMessage('');
    try {
      const response = await prmApi.updateFunctieCapabilityMap({ map: functieMapState });
      setFunctieMapState(response.data.map || {});
      setFunctieRoles(response.data.roles || []);
      setFunctiesMessage('Functietoewijzing opgeslagen.');
    } catch (error) {
      setFunctiesMessage('Fout bij opslaan: ' + (error.response?.data?.message || 'Onbekende fout'));
    } finally {
      setFunctiesSaving(false);
    }
  };

  // Handle commissie-to-role mapping save
  const handleCommissieSave = async () => {
    setCommissieSaving(true);
    setCommissieMessage('');
    try {
      const response = await prmApi.updateCommissieCapabilityMap({ map: commissieMapState });
      setCommissieMapState(response.data.map || {});
      setCommissieMessage('Commissietoewijzing opgeslagen.');
    } catch (error) {
      setCommissieMessage('Fout bij opslaan: ' + (error.response?.data?.message || 'Onbekende fout'));
    } finally {
      setCommissieSaving(false);
    }
  };

  // Handle capability sync (on-demand re-apply mapping to all users)
  const handleSyncCapabilities = async () => {
    if (!confirm('Dit synchroniseert rollen van alle gebruikers op basis van hun functies. Doorgaan?')) {
      return;
    }
    setSyncingCapabilities(true);
    setCapabilitySyncMessage('');
    try {
      const response = await prmApi.syncAllCapabilities();
      const data = response.data;
      setCapabilitySyncMessage(
        `Synchronisatie voltooid: ${data.synced || 0} bijgewerkt, ${data.skipped || 0} overgeslagen.`
      );
    } catch (fout) {
      setCapabilitySyncMessage(
        fout.response?.data?.message || 'Kan rollen niet synchroniseren. Controleer de serverlogboeken.'
      );
    } finally {
      setSyncingCapabilities(false);
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
          isAdmin={isAdmin}
          canAccessFinancieel={canAccessFinancieel}
          // CardDAV props
          carddavUrls={carddavUrls}
          config={config}
          copyCarddavUrl={copyCarddavUrl}
          // Club config props
          clubConfig={clubConfig}
          setClubConfig={setClubConfig}
          clubConfigLoading={clubConfigLoading}
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
      case 'financieel':
        return canAccessFinancieel ? (
          <FinanceSettings allowedTabs={['organization', 'payment', 'discipline', 'contributie', 'email']} />
        ) : null;
      case 'clothing':
        return canAccessClothing ? <ClothingSettingsTab /> : null;
      case 'vog':
        return isAdmin && canAccessVOG ? (
          <div className="card p-6">
            <VOGSettings />
          </div>
        ) : null;
      case 'admin':
        return isAdmin ? (
          <AdminTabWithSubtabs
            activeSubtab={activeSubtab}
            setActiveSubtab={setActiveSubtab}
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
            availableFuncties={availableFuncties}
            functieMapState={functieMapState}
            setFunctieMapState={setFunctieMapState}
            functieRoles={functieRoles}
            functiesLoading={functiesLoading}
            functiesSaving={functiesSaving}
            functiesMessage={functiesMessage}
            handleFunctiesSave={handleFunctiesSave}
            commissieList={commissieList}
            commissieMapState={commissieMapState}
            setCommissieMapState={setCommissieMapState}
            commissieSaving={commissieSaving}
            commissieMessage={commissieMessage}
            handleCommissieSave={handleCommissieSave}
            handleSyncCapabilities={handleSyncCapabilities}
            syncingCapabilities={syncingCapabilities}
            capabilitySyncMessage={capabilitySyncMessage}
            welcomeSettings={welcomeSettings}
            setWelcomeSettings={setWelcomeSettings}
            welcomeSettingsLoading={welcomeSettingsLoading}
            welcomeSaving={welcomeSaving}
            welcomeSaved={welcomeSaved}
            handleWelcomeSave={handleWelcomeSave}
            anniversarySettings={anniversarySettings}
            setAnniversarySettings={setAnniversarySettings}
            anniversarySettingsLoading={anniversarySettingsLoading}
            anniversarySaving={anniversarySaving}
            anniversaryMessage={anniversaryMessage}
            handleAnniversarySave={handleAnniversarySave}
          />
        ) : null;
      case 'about':
        return <AboutTab config={config} />;
      default:
        return null;
    }
  };

  return (
    <div className="w-full">
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

function ClothingSettingsTab() {
  const { data: settings, isLoading } = useClothingSettings();
  const updateSettings = useUpdateClothingSettings();

  const [eligibilityEnabled, setEligibilityEnabled] = useState(true);
  const [cooldownSeasons, setCooldownSeasons] = useState(3);
  const [currentSeason, setCurrentSeason] = useState('');

  useEffect(() => {
    if (!settings) return;
    setEligibilityEnabled(Boolean(settings.eligibility_enabled));
    setCooldownSeasons(Number(settings.cooldown_seasons || 3));
    setCurrentSeason(settings.current_season || '');
  }, [settings]);

  const handleSave = async () => {
    try {
      await updateSettings.mutateAsync({
        eligibility_enabled: eligibilityEnabled,
        ...(eligibilityEnabled ? { cooldown_seasons: cooldownSeasons, current_season: currentSeason } : {}),
      });
    } catch (error) {
      alert(error.response?.data?.message || 'Kon kledinginstellingen niet opslaan.');
    }
  };

  if (isLoading) {
    return (
      <div className="card p-6">
        <p className="text-sm text-gray-500 dark:text-gray-400">Instellingen laden...</p>
      </div>
    );
  }

  return (
    <div className="card p-6 space-y-6">
      <div>
        <h2 className="text-lg font-semibold text-brand-gradient mb-2">Kledingregels</h2>
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Beheer hier of eligibility-regels actief zijn voor uitgifte.
        </p>
      </div>

      <div className="flex items-center gap-3">
        <input
          id="eligibility-enabled"
          type="checkbox"
          className="h-4 w-4"
          checked={eligibilityEnabled}
          onChange={(e) => setEligibilityEnabled(e.target.checked)}
        />
        <label htmlFor="eligibility-enabled" className="text-sm font-medium">
          Eligibility-regels inschakelen
        </label>
      </div>

      {eligibilityEnabled && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="label">Wachttijd (seizoenen)</label>
            <input
              type="number"
              min={1}
              className="input"
              value={cooldownSeasons}
              onChange={(e) => setCooldownSeasons(Number(e.target.value || 1))}
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Aantal seizoenen wachten voordat hetzelfde item opnieuw mag worden uitgegeven.
            </p>
          </div>
          <div>
            <label className="label">Huidig seizoen</label>
            <input
              className="input"
              value={currentSeason}
              onChange={(e) => setCurrentSeason(e.target.value)}
              placeholder="bijv. 2025-2026"
            />
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Formaat: YYYY-YYYY.
            </p>
          </div>
        </div>
      )}

      <div>
        <button
          type="button"
          onClick={handleSave}
          disabled={updateSettings.isPending}
          className="btn-primary"
        >
          {updateSettings.isPending ? 'Opslaan...' : 'Opslaan'}
        </button>
      </div>
    </div>
  );
}

// Appearance Tab Component
function AppearanceTab() {
  const config = window.rondoConfig || {};
  const isAdmin = config.isAdmin || false;

  // Club Configuration state (admin only)
  const [clubName, setClubName] = useState(config.clubName || '');
  const [savingClubConfig, setSavingClubConfig] = useState(false);
  const [clubConfigSaved, setClubConfigSaved] = useState(false);
  const [clubLogoId, setClubLogoId] = useState(0);
  const [clubLogoUrl, setClubLogoUrl] = useState('');
  const [primaryColor, setPrimaryColor] = useState('');
  const [accentColor, setAccentColor] = useState('');
  const [loadingBranding, setLoadingBranding] = useState(false);
  const [savingBranding, setSavingBranding] = useState(false);
  const [brandingSaved, setBrandingSaved] = useState(false);
  const [brandingError, setBrandingError] = useState('');

  useEffect(() => {
    if (!isAdmin) return;

    const loadBranding = async () => {
      setLoadingBranding(true);
      setBrandingError('');
      try {
        const response = await prmApi.getFinanceBranding();
        setClubLogoId(response.data?.club_logo_id || 0);
        setClubLogoUrl(response.data?.club_logo_url || '');
        setPrimaryColor(response.data?.accent_color || '');
        setAccentColor(response.data?.accent_background_color || '');
      } catch (error) {
        setBrandingError(error.response?.data?.message || 'Kon huisstijlinstellingen niet laden.');
      } finally {
        setLoadingBranding(false);
      }
    };

    loadBranding();
  }, [isAdmin]);


  const handleSaveClubConfig = async () => {
    setSavingClubConfig(true);
    setClubConfigSaved(false);
    try {
      const response = await prmApi.updateClubConfig({
        club_name: clubName,
      });
      // Update window.rondoConfig with saved values
      window.rondoConfig.clubName = response.data.club_name;

      setClubConfigSaved(true);
      setTimeout(() => setClubConfigSaved(false), 3000);
    } catch (error) {
      alert('Kan clubconfiguratie niet opslaan. Probeer het opnieuw.');
    } finally {
      setSavingClubConfig(false);
    }
  };

  const handleLogoUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setBrandingError('');
    setBrandingSaved(false);

    try {
      const uploadFormData = new FormData();
      uploadFormData.append('file', file);

      const response = await api.post('/wp/v2/media', uploadFormData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setClubLogoId(response.data.id);
      setClubLogoUrl(response.data.source_url);
    } catch (error) {
      setBrandingError(error.response?.data?.message || 'Fout bij uploaden logo');
    } finally {
      event.target.value = '';
    }
  };

  const handleSaveBranding = async () => {
    setSavingBranding(true);
    setBrandingError('');
    setBrandingSaved(false);
    try {
      await prmApi.updateFinanceBranding({
        club_logo_id: clubLogoId,
        accent_color: primaryColor,
        accent_background_color: accentColor,
      });
      setBrandingSaved(true);
      setTimeout(() => setBrandingSaved(false), 3000);
    } catch (error) {
      setBrandingError(error.response?.data?.message || 'Kon huisstijlinstellingen niet opslaan.');
    } finally {
      setSavingBranding(false);
    }
  };


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

      {isAdmin && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold text-brand-gradient mb-2">Huisstijl</h2>
          <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
            Beheer het clublogo, de primaire kleur en de accentkleur voor publieke pagina&apos;s.
          </p>

          {loadingBranding ? (
            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              <Loader2 className="w-4 h-4 animate-spin" />
              Instellingen laden...
            </div>
          ) : (
            <div className="space-y-5">
              <div>
                <label className="label">Clublogo</label>
                {clubLogoUrl ? (
                  <div className="flex items-center gap-3">
                    <img
                      src={clubLogoUrl}
                      alt="Club logo"
                      className="max-h-[60px] object-contain"
                    />
                    <button
                      type="button"
                      onClick={() => {
                        setClubLogoId(0);
                        setClubLogoUrl('');
                        setBrandingSaved(false);
                      }}
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
                <label className="label">Primaire kleur</label>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={primaryColor || '#0891b2'}
                    onChange={(e) => {
                      setPrimaryColor(e.target.value);
                      setBrandingSaved(false);
                    }}
                    className="h-10 w-20 cursor-pointer border border-gray-300 dark:border-gray-600 rounded-lg"
                  />
                  <input
                    type="text"
                    value={primaryColor || '#0891b2'}
                    onChange={(e) => {
                      setPrimaryColor(e.target.value);
                      setBrandingSaved(false);
                    }}
                    placeholder="#0891b2"
                    className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent font-mono text-sm"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      setPrimaryColor('');
                      setBrandingSaved(false);
                    }}
                    className="text-sm text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap"
                  >
                    Reset naar standaard
                  </button>
                </div>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Deze kleur wordt gebruikt voor koppen en lijnen op facturen. Standaard: #0891b2 (electric cyan).
                </p>
              </div>

              <div>
                <label className="label">Accentkleur</label>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={accentColor || '#f8fafc'}
                    onChange={(e) => {
                      setAccentColor(e.target.value);
                      setBrandingSaved(false);
                    }}
                    className="h-10 w-20 cursor-pointer border border-gray-300 dark:border-gray-600 rounded-lg"
                  />
                  <input
                    type="text"
                    value={accentColor || '#f8fafc'}
                    onChange={(e) => {
                      setAccentColor(e.target.value);
                      setBrandingSaved(false);
                    }}
                    placeholder="#f8fafc"
                    className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-electric-cyan dark:focus:ring-electric-cyan focus:border-transparent font-mono text-sm"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      setAccentColor('');
                      setBrandingSaved(false);
                    }}
                    className="text-sm text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 whitespace-nowrap"
                  >
                    Reset naar standaard
                  </button>
                </div>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Deze kleur wordt gebruikt als achtergrond op de publieke pagina&apos;s /betaling en /lidpas.
                </p>
              </div>

              {brandingError ? (
                <div className="flex items-start gap-2 text-sm text-red-600 dark:text-red-400">
                  <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                  <span>{brandingError}</span>
                </div>
              ) : null}

              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={handleSaveBranding}
                  disabled={savingBranding}
                  className="btn-primary"
                >
                  {savingBranding ? 'Opslaan...' : 'Opslaan'}
                </button>
                {brandingSaved ? (
                  <span className="text-sm text-green-600 dark:text-green-400 flex items-center gap-1">
                    <Check className="w-4 h-4" />
                    Opgeslagen
                  </span>
                ) : null}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// ConnectionsTab Component - Container for CardDAV and API subtabs
function ConnectionsTab({
  activeSubtab, setActiveSubtab, setActiveTab,
  isAdmin, canAccessFinancieel,
  // CardDAV props
  carddavUrls, config, copyCarddavUrl,
  // Club config props
  clubConfig, setClubConfig, clubConfigLoading,
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
      {activeSubtab === 'freescout' && (
        <FreeScoutConnectionSubtab
          isAdmin={isAdmin}
          clubConfig={clubConfig}
          setClubConfig={setClubConfig}
          loading={clubConfigLoading}
        />
      )}
      {activeSubtab === 'payment-providers' && (
        <PaymentProvidersSubtab canAccessFinancieel={canAccessFinancieel} />
      )}
      {activeSubtab === 'wallets' && (
        <WalletsSubtab canAccessFinancieel={canAccessFinancieel} />
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
              onClick={() => setActiveTab('connections', 'api-access')}
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

function FreeScoutConnectionSubtab({ isAdmin, clubConfig, setClubConfig, loading }) {
  const [formData, setFormData] = useState({
    freescout_url: '',
    freescout_api_key: '',
  });
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    setFormData({
      freescout_url: clubConfig?.freescout_url || '',
      freescout_api_key: '',
    });
  }, [clubConfig]);

  const handleSave = async (e) => {
    e.preventDefault();
    if (!isAdmin) {
      return;
    }

    setSaving(true);
    setMessage('');
    try {
      const payload = {
        freescout_url: formData.freescout_url,
      };
      if (formData.freescout_api_key.trim()) {
        payload.freescout_api_key = formData.freescout_api_key.trim();
      }
      const response = await prmApi.updateClubConfig(payload);
      setClubConfig(response.data || null);
      window.rondoConfig.freescoutUrl = response.data?.freescout_url || '';
      setFormData((prev) => ({ ...prev, freescout_api_key: '' }));
      setMessage('FreeScout-instellingen opgeslagen.');
    } catch (error) {
      setMessage(error.response?.data?.message || 'Kon FreeScout-instellingen niet opslaan.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="card p-6">
        <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
          <Loader2 className="w-4 h-4 animate-spin" />
          <span>FreeScout-instellingen laden...</span>
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSave} className="card p-6 space-y-4">
      <div>
        <h2 className="text-lg font-semibold text-brand-gradient">FreeScout</h2>
        <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Configureer de koppeling met FreeScout voor klantlinks en API-gebruik.
        </p>
      </div>

      <div>
        <label className="label">FreeScout URL</label>
        <input
          type="url"
          value={formData.freescout_url}
          onChange={(e) => setFormData((prev) => ({ ...prev, freescout_url: e.target.value }))}
          className="input"
          placeholder="https://support.example.com"
          disabled={!isAdmin}
        />
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
          Basis-URL voor FreeScout klantkoppelingen.
        </p>
      </div>

      <div>
        <label className="label">FreeScout API key</label>
        <input
          type="password"
          value={formData.freescout_api_key}
          onChange={(e) => setFormData((prev) => ({ ...prev, freescout_api_key: e.target.value }))}
          className="input"
          placeholder={clubConfig?.freescout_has_api_key ? '••••••••' : 'FreeScout API key'}
          disabled={!isAdmin}
        />
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
          {clubConfig?.freescout_has_api_key
            ? 'Er is al een API key opgeslagen. Laat leeg om de huidige key te behouden.'
            : 'Wordt gebruikt voor toekomstige FreeScout API-acties.'}
        </p>
      </div>

      {!isAdmin && (
        <p className="text-sm text-amber-600 dark:text-amber-400">
          Alleen beheerders kunnen FreeScout-instellingen wijzigen.
        </p>
      )}

      {message && (
        <p className={`text-sm ${message.includes('niet') || message.includes('Kon') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
          {message}
        </p>
      )}

      <div className="flex justify-end">
        <button type="submit" disabled={!isAdmin || saving} className="btn-primary">
          {saving ? 'Opslaan...' : 'Opslaan'}
        </button>
      </div>
    </form>
  );
}

function PaymentProvidersSubtab({ canAccessFinancieel }) {
  if (!canAccessFinancieel) {
    return (
      <div className="card p-6">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Je hebt geen toegang tot betaalprovider-instellingen.
        </p>
      </div>
    );
  }

  return <FinanceSettings initialTab="mollie" allowedTabs={['mollie', 'rabobank']} />;
}

function WalletsSubtab({ canAccessFinancieel }) {
  if (!canAccessFinancieel) {
    return (
      <div className="card p-6">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Je hebt geen toegang tot wallet-instellingen.
        </p>
      </div>
    );
  }

  return <FinanceSettings initialTab="membership_passes" allowedTabs={['membership_passes']} />;
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

// Admin Tab with Subtabs
function AdminTabWithSubtabs({
  activeSubtab,
  setActiveSubtab,
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
  availableFuncties,
  functieMapState,
  setFunctieMapState,
  functieRoles,
  functiesLoading,
  functiesSaving,
  functiesMessage,
  handleFunctiesSave,
  commissieList,
  commissieMapState,
  setCommissieMapState,
  commissieSaving,
  commissieMessage,
  handleCommissieSave,
  handleSyncCapabilities,
  syncingCapabilities,
  capabilitySyncMessage,
  welcomeSettings,
  setWelcomeSettings,
  welcomeSettingsLoading,
  welcomeSaving,
  welcomeSaved,
  handleWelcomeSave,
  anniversarySettings,
  setAnniversarySettings,
  anniversarySettingsLoading,
  anniversarySaving,
  anniversaryMessage,
  handleAnniversarySave,
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
        <GebruikersTab />
      ) : activeSubtab === 'rollen' ? (
        <div className="card p-6">
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
        </div>
      ) : activeSubtab === 'functies' ? (
        <div className="card p-6">
          <FunctiesTab
            availableFuncties={availableFuncties}
            functieMapState={functieMapState}
            setFunctieMapState={setFunctieMapState}
            roles={functieRoles}
            loading={functiesLoading}
            saving={functiesSaving}
            message={functiesMessage}
            handleSave={handleFunctiesSave}
            commissieList={commissieList}
            commissieMapState={commissieMapState}
            setCommissieMapState={setCommissieMapState}
            commissieSaving={commissieSaving}
            commissieMessage={commissieMessage}
            handleCommissieSave={handleCommissieSave}
            handleSyncCapabilities={handleSyncCapabilities}
            syncingCapabilities={syncingCapabilities}
            capabilitySyncMessage={capabilitySyncMessage}
          />
        </div>
      ) : activeSubtab === 'welkomstmail' ? (
        <div className="card p-6">
          <WelkomstmailTab
            settings={welcomeSettings}
            setSettings={setWelcomeSettings}
            loading={welcomeSettingsLoading}
            saving={welcomeSaving}
            saved={welcomeSaved}
            handleSave={handleWelcomeSave}
          />
        </div>
      ) : activeSubtab === 'anniversaries' ? (
        <div className="card p-6">
          <AnniversariesTab
            settings={anniversarySettings}
            setSettings={setAnniversarySettings}
            loading={anniversarySettingsLoading}
            saving={anniversarySaving}
            message={anniversaryMessage}
            handleSave={handleAnniversarySave}
          />
        </div>
      ) : activeSubtab === 'systeem' ? (
        <AdminTab
          handleRescheduleCron={handleRescheduleCron}
          reschedulingCron={reschedulingCron}
          cronMessage={cronMessage}
        />
      ) : null}
    </div>
  );
}

// Gebruikers Tab Component - User management (active users + invite)
function GebruikersTab() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [provisioning, setProvisioning] = useState(null);
  const [provisionMessage, setProvisionMessage] = useState(null);
  const [inviteSearch, setInviteSearch] = useState('');
  const [inviteResults, setInviteResults] = useState([]);
  const [inviteSearching, setInviteSearching] = useState(false);

  const fetchUsers = async () => {
    setLoading(true);
    try {
      const usersRes = await prmApi.getUsers();
      setUsers(usersRes.data || []);
    } catch {
      // Fetch failed silently
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchUsers(); }, []);

  // Debounced search for provisionable people
  useEffect(() => {
    if (inviteSearch.length < 2) {
      setInviteResults([]);
      return;
    }
    const timer = setTimeout(async () => {
      setInviteSearching(true);
      try {
        const res = await prmApi.searchProvisionableUsers(inviteSearch);
        setInviteResults(res.data || []);
      } catch {
        setInviteResults([]);
      } finally {
        setInviteSearching(false);
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [inviteSearch]);

  const handleProvision = async (personId, personName) => {
    setProvisioning(personId);
    setProvisionMessage(null);
    try {
      await prmApi.provisionUser(personId);
      setProvisionMessage({ type: 'success', text: `Account aangemaakt voor ${personName}.` });
      setInviteSearch('');
      setInviteResults([]);
      await fetchUsers();
    } catch (error) {
      setProvisionMessage({
        type: 'error',
        text: error.response?.data?.message || `Kan geen account aanmaken voor ${personName}.`,
      });
    } finally {
      setProvisioning(null);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('nl-NL', {
      day: 'numeric', month: 'short', year: 'numeric',
    });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="w-6 h-6 animate-spin text-electric-cyan" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Feedback message */}
      {provisionMessage && (
        <div className={`p-3 rounded-lg text-sm flex items-center gap-2 ${
          provisionMessage.type === 'success'
            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'
        }`}>
          {provisionMessage.type === 'success'
            ? <Check className="w-4 h-4 shrink-0" />
            : <AlertCircle className="w-4 h-4 shrink-0" />}
          {provisionMessage.text}
        </div>
      )}

      {/* Active users */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-1">Actieve gebruikers</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
          {users.length} {users.length === 1 ? 'gebruiker' : 'gebruikers'} met een account.
        </p>

        {users.length === 0 ? (
          <p className="text-sm text-gray-500 dark:text-gray-400">Geen actieve gebruikers gevonden.</p>
        ) : (
          <div className="border rounded-md border-gray-300 dark:border-gray-600 overflow-hidden">
            <div className="max-h-96 overflow-y-auto">
              <table className="w-full border-collapse">
                <thead className="sticky top-0 bg-gray-50 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-600">
                  <tr>
                    <th className="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2">Naam</th>
                    <th className="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2">E-mail</th>
                    <th className="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2">Geregistreerd</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {users.map(user => (
                    <tr key={user.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-2.5 text-sm">
                        {user.linked_person_id ? (
                          <Link to={`/people/${user.linked_person_id}`} className="text-electric-cyan hover:underline">
                            {user.linked_person_name || user.name}
                          </Link>
                        ) : (
                          <span className="text-gray-900 dark:text-gray-100">{user.name}</span>
                        )}
                      </td>
                      <td className="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">{user.email}</td>
                      <td className="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">{formatDate(user.registered)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Invite by search */}
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-1">Uitnodigen</h2>
        <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">
          Zoek een lid op naam om een account aan te maken.
        </p>

        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            type="text"
            value={inviteSearch}
            onChange={(e) => setInviteSearch(e.target.value)}
            placeholder="Zoek op naam..."
            className="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:border-transparent"
          />
          {inviteSearching && (
            <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin text-gray-400" />
          )}
        </div>

        {inviteSearch.length >= 2 && !inviteSearching && inviteResults.length === 0 && (
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-3">Geen leden gevonden zonder account.</p>
        )}

        {inviteResults.length > 0 && (
          <div className="border rounded-md border-gray-300 dark:border-gray-600 overflow-hidden mt-3">
            <div className="max-h-64 overflow-y-auto">
              <table className="w-full border-collapse">
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {inviteResults.map(person => (
                    <tr key={person.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-2.5 text-sm">
                        <div className="flex items-center gap-2">
                          <PersonAvatar thumbnail={person.thumbnail} name={person.name} size="sm" />
                          <Link to={`/people/${person.id}`} className="text-electric-cyan hover:underline">
                            {person.name}
                          </Link>
                        </div>
                      </td>
                      <td className="px-4 py-2.5 text-sm text-gray-600 dark:text-gray-400">{person.email}</td>
                      <td className="px-4 py-2.5 text-right">
                        <button
                          onClick={() => handleProvision(person.id, person.name)}
                          disabled={provisioning === person.id}
                          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md text-white bg-electric-cyan hover:bg-bright-cobalt disabled:opacity-50 transition-colors"
                        >
                          {provisioning === person.id ? (
                            <Loader2 className="w-3.5 h-3.5 animate-spin" />
                          ) : (
                            <UserPlus className="w-3.5 h-3.5" />
                          )}
                          Uitnodigen
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// Admin Tab Component (now used as Systeem subtab)
function AdminTab({
  handleRescheduleCron, reschedulingCron, cronMessage
}) {
  return (
    <div className="space-y-6">
      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Configuratie</h2>
        <div className="space-y-3">
          <Link
            to="/settings/custom-fields"
            className="block p-4 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-colors"
          >
            <p className="font-medium">Aangepaste velden</p>
            <p className="text-sm text-gray-500">Definieer aangepaste gegevensvelden voor personen en organisaties</p>
          </Link>
        </div>
      </div>

      <div className="card p-6">
        <h2 className="text-lg font-semibold text-brand-gradient mb-4">Systeemacties</h2>
        <div className="space-y-3">
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

function AnniversariesTab({
  settings,
  setSettings,
  loading,
  saving,
  message,
  handleSave,
}) {
  const MEMBER_PRESETS = [5, 10, 15, 20, 25, 40, 50, 60, 75];
  const VOLUNTEER_PRESETS = [12.5, 25, 40];
  const [newMemberMilestone, setNewMemberMilestone] = useState('');
  const [newVolunteerMilestone, setNewVolunteerMilestone] = useState('');

  const normalizeMilestones = (values) => {
    const normalized = values
      .map((value) => Number(value))
      .filter((value) => Number.isFinite(value) && value > 0 && value <= 120)
      .filter((value) => {
        const fraction = Number((value - Math.floor(value)).toFixed(2));
        return fraction === 0 || fraction === 0.5;
      })
      .map((value) => Number(value.toFixed(1)));

    return [...new Set(normalized)].sort((a, b) => a - b);
  };

  const updateCategory = (category, values) => {
    setSettings((prev) => ({
      ...(prev || {}),
      [category]: normalizeMilestones(values),
    }));
  };

  const toggleMilestone = (category, milestone, checked) => {
    const existing = settings?.[category] || [];
    const next = checked
      ? [...existing, milestone]
      : existing.filter((value) => value !== milestone);
    updateCategory(category, next);
  };

  const addCustomMilestone = (category) => {
    const raw = category === 'member' ? newMemberMilestone : newVolunteerMilestone;
    const value = Number(raw.replace(',', '.'));
    if (!Number.isFinite(value) || value <= 0 || value > 120) {
      return;
    }
    const fraction = Number((value - Math.floor(value)).toFixed(2));
    if (!(fraction === 0 || fraction === 0.5)) {
      return;
    }

    const existing = settings?.[category] || [];
    updateCategory(category, [...existing, value]);

    if (category === 'member') {
      setNewMemberMilestone('');
    } else {
      setNewVolunteerMilestone('');
    }
  };

  const formatMilestone = (value) => (Number.isInteger(value) ? String(value) : String(value).replace('.', ','));

  const renderCategory = (category, title, description, presets, customValue, setCustomValue) => {
    const selected = settings?.[category] || [];
    const customMilestones = selected.filter((value) => !presets.includes(value));

    return (
      <div className="space-y-4">
        <div>
          <h4 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</h4>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{description}</p>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
          {presets.map((milestone) => (
            <label
              key={`${category}-${milestone}`}
              className="flex items-center gap-2 rounded-md border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm"
            >
              <input
                type="checkbox"
                checked={selected.includes(milestone)}
                onChange={(e) => toggleMilestone(category, milestone, e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-electric-cyan focus:ring-electric-cyan"
              />
              <span>{formatMilestone(milestone)} jaar</span>
            </label>
          ))}
        </div>

        <div className="flex flex-col md:flex-row md:items-center gap-2">
          <input
            type="text"
            value={customValue}
            onChange={(e) => setCustomValue(e.target.value)}
            placeholder="Bijv. 30 of 12,5"
            className="input md:max-w-xs"
          />
          <button
            type="button"
            onClick={() => addCustomMilestone(category)}
            className="btn-secondary md:w-auto"
          >
            Mijlpaal toevoegen
          </button>
          <p className="text-xs text-gray-500 dark:text-gray-400">Alleen hele of halve jaren (bijv. 12,5).</p>
        </div>

        {customMilestones.length > 0 && (
          <div className="flex flex-wrap gap-2">
            {customMilestones.map((milestone) => (
              <button
                key={`${category}-custom-${milestone}`}
                type="button"
                onClick={() => toggleMilestone(category, milestone, false)}
                className="inline-flex items-center gap-1 rounded-full bg-cyan-50 dark:bg-gray-700 text-cyan-700 dark:text-cyan-300 px-3 py-1 text-xs"
              >
                {formatMilestone(milestone)} jaar
                <span aria-hidden>×</span>
              </button>
            ))}
          </div>
        )}
      </div>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
        <Loader2 className="w-4 h-4 animate-spin" />
        Jubileuminstellingen laden...
      </div>
    );
  }

  if (!settings) {
    return (
      <div className="text-sm text-gray-600 dark:text-gray-400">
        Kon jubileuminstellingen niet laden.
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Jubilarissen</h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Stel in welke lid- en vrijwilligersmijlpalen je club wil volgen in het jubileumoverzicht.
        </p>
      </div>

      {renderCategory(
        'member',
        'Lidmaatschap',
        'Mijlpalen op basis van lid sinds datum.',
        MEMBER_PRESETS,
        newMemberMilestone,
        setNewMemberMilestone
      )}

      {renderCategory(
        'volunteer',
        'Vrijwilligers',
        'Mijlpalen voor vrijwilligersjubilea.',
        VOLUNTEER_PRESETS,
        newVolunteerMilestone,
        setNewVolunteerMilestone
      )}

      <div className="flex items-center gap-4">
        <button
          type="button"
          onClick={handleSave}
          disabled={saving}
          className="btn-primary"
        >
          {saving ? 'Opslaan...' : 'Opslaan'}
        </button>
        {message && (
          <span className={`text-sm ${message.includes('niet') || message.includes('Kon') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
            {message}
          </span>
        )}
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

// Functies Tab Component - Functie-to-Role mapping
function FunctiesTab({
  availableFuncties,
  functieMapState,
  setFunctieMapState,
  roles,
  loading,
  saving,
  message,
  handleSave,
  commissieList,
  commissieMapState,
  setCommissieMapState,
  commissieSaving,
  commissieMessage,
  handleCommissieSave,
  handleSyncCapabilities,
  syncingCapabilities,
  capabilitySyncMessage,
}) {
  // Union of available functies and keys already in the saved map, deduplicated and sorted
  const allFuncties = [...new Set([
    ...availableFuncties,
    ...Object.keys(functieMapState),
  ])].sort((a, b) => a.localeCompare(b, 'nl'));

  const handleCheckboxChange = (functie, roleSlug, checked) => {
    setFunctieMapState(prev => ({
      ...prev,
      [functie]: {
        ...(prev[functie] || {}),
        [roleSlug]: checked,
      },
    }));
  };

  const handleCommissieCheckboxChange = (commissieId, roleSlug, checked) => {
    setCommissieMapState(prev => ({
      ...prev,
      [commissieId]: {
        ...(prev[commissieId] || {}),
        [roleSlug]: checked,
      },
    }));
  };

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
          Functie-toewijzing
        </h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Bepaal welke Sportlink-functies welke Rondo-rollen toekennen. Een functie kan meerdere rollen toekennen.
        </p>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-8">
          <Loader2 className="w-6 h-6 animate-spin text-electric-cyan" />
        </div>
      ) : allFuncties.length === 0 ? (
        <div className="text-sm text-gray-500 dark:text-gray-400 py-4">
          Geen functies gevonden. Functies worden automatisch geladen vanuit Sportlink-sync.
        </div>
      ) : (
        <div className="space-y-6">
          {/* Functie-to-role mapping table */}
          <div className="border rounded-md border-gray-300 dark:border-gray-600 overflow-hidden">
            <div className="max-h-96 overflow-y-auto">
              <table className="w-full border-collapse">
                <thead className="sticky top-0 bg-gray-50 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-600">
                  <tr>
                    <th className="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2">
                      Functie
                    </th>
                    {roles.map(role => (
                      <th key={role.slug} className="text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2 w-24">
                        {role.label}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {allFuncties.map(functie => {
                    const isStale = !availableFuncties.includes(functie);
                    return (
                      <tr key={functie} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td className="px-4 py-2.5 align-middle">
                          <div className="flex items-center gap-2 min-w-0">
                            <span className="text-sm text-gray-900 dark:text-gray-100 truncate">{functie}</span>
                            {isStale && (
                              <span className="text-xs text-gray-400 dark:text-gray-500 italic whitespace-nowrap">(niet meer actief)</span>
                            )}
                          </div>
                        </td>
                        {roles.map(role => (
                          <td key={role.slug} className="px-4 py-2.5 w-24 text-center align-middle">
                            <label className="flex items-center justify-center cursor-pointer">
                              <input
                                type="checkbox"
                                checked={!!(functieMapState[functie]?.[role.slug])}
                                onChange={(e) => handleCheckboxChange(functie, role.slug, e.target.checked)}
                                className="h-4 w-4 rounded text-electric-cyan focus:ring-electric-cyan border-gray-300"
                              />
                            </label>
                          </td>
                        ))}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>

          {/* Save button and message */}
          <div className="flex items-center gap-4">
            <button
              onClick={handleSave}
              disabled={saving}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-electric-cyan hover:bg-bright-cobalt focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-electric-cyan disabled:opacity-50"
            >
              {saving ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Opslaan...
                </>
              ) : (
                'Opslaan'
              )}
            </button>
            {message && (
              <span className={`text-sm ${message.includes('Fout') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                {message}
              </span>
            )}
          </div>

          {/* Commissie-to-role mapping table */}
          <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
              Commissie-toewijzing
            </h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400 mb-4">
              Bepaal welke commissies welke Rondo-rollen toekennen aan al hun leden.
            </p>

            {commissieList.length > 0 && (
              <>
                <div className="border rounded-md border-gray-300 dark:border-gray-600 overflow-hidden">
                  <div className="max-h-96 overflow-y-auto">
                    <table className="w-full border-collapse">
                      <thead className="sticky top-0 bg-gray-50 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-600">
                        <tr>
                          <th className="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2">
                            Commissie
                          </th>
                          {roles.map(role => (
                            <th key={role.slug} className="text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider px-4 py-2 w-24">
                              {role.label}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                        {commissieList.map(c => (
                          <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td className="px-4 py-2.5 align-middle">
                              <span className="text-sm text-gray-900 dark:text-gray-100">{c.name}</span>
                            </td>
                            {roles.map(role => (
                              <td key={role.slug} className="px-4 py-2.5 w-24 text-center align-middle">
                                <label className="flex items-center justify-center cursor-pointer">
                                  <input
                                    type="checkbox"
                                    checked={!!(commissieMapState[c.id]?.[role.slug])}
                                    onChange={(e) => handleCommissieCheckboxChange(c.id, role.slug, e.target.checked)}
                                    className="h-4 w-4 rounded text-electric-cyan focus:ring-electric-cyan border-gray-300"
                                  />
                                </label>
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="flex items-center gap-4 mt-4">
                  <button
                    onClick={handleCommissieSave}
                    disabled={commissieSaving}
                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-electric-cyan hover:bg-bright-cobalt focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-electric-cyan disabled:opacity-50"
                  >
                    {commissieSaving ? (
                      <>
                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                        Opslaan...
                      </>
                    ) : (
                      'Opslaan'
                    )}
                  </button>
                  {commissieMessage && (
                    <span className={`text-sm ${commissieMessage.includes('Fout') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                      {commissieMessage}
                    </span>
                  )}
                </div>
              </>
            )}
          </div>

          {/* Capability sync section */}
          <div className="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h4 className="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Rollen synchroniseren</h4>
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-3">
              Pas de huidige functie-toewijzing toe op alle gebruikers met een account.
            </p>
            <div className="flex items-center gap-4">
              <button
                onClick={handleSyncCapabilities}
                disabled={syncingCapabilities}
                className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md shadow-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-electric-cyan disabled:opacity-50"
              >
                {syncingCapabilities ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Synchroniseren...
                  </>
                ) : (
                  'Sync nu uitvoeren'
                )}
              </button>
              {capabilitySyncMessage && (
                <span className={`text-sm ${capabilitySyncMessage.includes('niet') ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                  {capabilitySyncMessage}
                </span>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// Welkomstmail Tab Component - Welcome email template configuration
function WelkomstmailTab({ settings, setSettings, loading, saving, saved, handleSave }) {
  const inputClass = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-electric-cyan focus:border-electric-cyan';

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="w-6 h-6 animate-spin text-electric-cyan" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Welkomstmail</h3>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Stel het sjabloon in voor de welkomstmail die verstuurd wordt als een account wordt aangemaakt.
        </p>
      </div>

      <div className="space-y-5">
        {/* From email */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Van e-mailadres
          </label>
          <input
            type="text"
            value={settings?.from_email || ''}
            onChange={(e) => setSettings(prev => ({ ...prev, from_email: e.target.value }))}
            className={inputClass}
            placeholder="noreply@example.com"
          />
        </div>

        {/* From name */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Van naam
          </label>
          <input
            type="text"
            value={settings?.from_name || ''}
            onChange={(e) => setSettings(prev => ({ ...prev, from_name: e.target.value }))}
            className={inputClass}
            placeholder="Rondo Club"
          />
        </div>

        {/* Subject */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Onderwerp
          </label>
          <input
            type="text"
            value={settings?.subject || ''}
            onChange={(e) => setSettings(prev => ({ ...prev, subject: e.target.value }))}
            className={inputClass}
            placeholder="Welkom bij {club_naam}"
          />
        </div>

        {/* Body */}
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Bericht
          </label>
          <textarea
            rows={12}
            value={settings?.body || ''}
            onChange={(e) => setSettings(prev => ({ ...prev, body: e.target.value }))}
            className={`${inputClass} font-mono text-sm`}
            placeholder="Hallo {first_name},..."
          />
          <p className="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
            Beschikbare variabelen: <code className="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">{'{first_name}'}</code>, <code className="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">{'{login}'}</code>, <code className="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">{'{email}'}</code>, <code className="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">{'{set_password_url}'}</code>, <code className="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">{'{club_naam}'}</code>
          </p>
        </div>

        {/* Save button */}
        <div className="flex items-center gap-3">
          <button
            onClick={handleSave}
            disabled={saving || !settings}
            className="btn-primary flex items-center gap-2 disabled:opacity-50"
          >
            {saving ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Opslaan...
              </>
            ) : (
              'Opslaan'
            )}
          </button>
          {saved && (
            <span className="text-sm text-green-600 dark:text-green-400 flex items-center gap-1">
              <Check className="w-4 h-4" />
              Opgeslagen
            </span>
          )}
        </div>
      </div>
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
            {APP_NAME} is een clubmanagementplatform voor verenigingen: beheer leden, teams en commissies,
            organiseer contributie en facturen, en regel dagelijkse clubprocessen in een centrale omgeving.
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
