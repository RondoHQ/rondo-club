import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  CircleAlert,
  CircleCheck,
  ExternalLink,
  MonitorPlay,
  Moon,
  Pencil,
  Power,
  PowerOff,
  RefreshCw,
  RotateCcw,
  Sun,
  Unplug,
  X,
} from 'lucide-react';
import { prmApi } from '@/api/client';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import NarrowcastingContent from './NarrowcastingContent';
import NarrowcastingPlaylists from './NarrowcastingPlaylists';

const defaultForm = {
  code: '',
  title: '',
  location: '',
  wake_time: '08:00',
  sleep_time: '23:00',
  timezone: 'Europe/Amsterdam',
  update_channel: 'stable',
};

const updateChannelLabels = {
  stable: 'Stabiel',
  beta: 'Beta',
  off: 'Uit',
};

const commandLabels = {
  wake_tv: 'Tv aan',
  sleep_tv: 'Tv uit',
  reload: 'Beeld vernieuwen',
  restart_browser: 'Browser herstarten',
  reboot: 'Player herstarten',
  shutdown: 'Player uitzetten',
  cec_detect: 'HDMI-CEC testen',
};

function formatDateTime(value) {
  if (!value) return 'Nog niet';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat('nl-NL', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(parsed);
}

function errorMessage(error) {
  return error?.response?.data?.message || 'Er ging iets mis. Probeer het opnieuw.';
}

function versionNeedsUpdate(current, target) {
  if (!current || !target) return false;
  const parse = (value) => value.split('.').map(Number);
  const left = parse(current);
  const right = parse(target);
  return right.some((part, index) => part > left[index] && right.slice(0, index).every((previous, previousIndex) => previous === left[previousIndex]));
}

function DisplayEditForm({ display, isPending, onCancel, onSave }) {
  const [values, setValues] = useState(() => ({
    title: display.name,
    location: display.location || '',
    wake_time: display.wake_time,
    sleep_time: display.sleep_time,
    timezone: display.display_timezone || 'Europe/Amsterdam',
    update_channel: display.update_channel || 'stable',
    presentation_enabled: Boolean(display.presentation_enabled),
  }));
  const updateValue = (name, value) => setValues((current) => ({ ...current, [name]: value }));

  return (
    <form
      className="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"
      onSubmit={(event) => {
        event.preventDefault();
        onSave(display.id, values);
      }}
    >
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Naam</span>
          <input className="input w-full" value={values.title} onChange={(event) => updateValue('title', event.target.value)} required />
        </label>
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Locatie</span>
          <input className="input w-full" value={values.location} onChange={(event) => updateValue('location', event.target.value)} />
        </label>
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Tv aan</span>
          <input type="time" className="input w-full" value={values.wake_time} onChange={(event) => updateValue('wake_time', event.target.value)} required />
        </label>
        <label className="block text-sm">
          <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Tv uit</span>
          <input type="time" className="input w-full" value={values.sleep_time} onChange={(event) => updateValue('sleep_time', event.target.value)} required />
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Tijdzone</span>
          <input className="input w-full" value={values.timezone} onChange={(event) => updateValue('timezone', event.target.value)} required />
        </label>
        <label className="block text-sm sm:col-span-2">
          <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Automatische updates</span>
          <select className="input w-full" value={values.update_channel} onChange={(event) => updateValue('update_channel', event.target.value)}>
            <option value="stable">Stabiel — aanbevolen</option>
            <option value="beta">Beta — nieuwe versies eerder testen</option>
            <option value="off">Uit</option>
          </select>
        </label>
        <label className="flex items-start gap-3 text-sm sm:col-span-2">
          <input
            type="checkbox"
            className="mt-1"
            checked={values.presentation_enabled}
            onChange={(event) => updateValue('presentation_enabled', event.target.checked)}
          />
          <span>
            <span className="block font-medium text-gray-700 dark:text-gray-300">Browserpresentaties testen</span>
            <span className="block text-gray-500 dark:text-gray-400">Toon een tijdelijke code waarmee ingelogde gebruikers hun scherm kunnen delen.</span>
          </span>
        </label>
      </div>
      <div className="mt-4 flex flex-wrap gap-2">
        <button type="submit" className="btn-primary text-sm" disabled={isPending}>{isPending ? 'Opslaan…' : 'Wijzigingen opslaan'}</button>
        <button type="button" className="btn-tertiary text-sm" onClick={onCancel} disabled={isPending}>Annuleren</button>
      </div>
    </form>
  );
}

export default function Narrowcasting() {
  useRouteTitle('Club TV');
  const queryClient = useQueryClient();
  const { data: currentUser } = useCurrentUser();
  const isAdmin = currentUser?.is_admin ?? false;
  const canManage = currentUser?.can_manage_narrowcasting ?? false;
  const [activeTab, setActiveTab] = useState('content');
  const [form, setForm] = useState(defaultForm);
  const [sportlinkForm, setSportlinkForm] = useState({ client_id: '', club_relation_code: '' });
  const [updateForm, setUpdateForm] = useState({ stable_version: '0.3.0', beta_version: '' });
  const [notice, setNotice] = useState('');
  const [editingDisplayId, setEditingDisplayId] = useState(null);

  const displaysQuery = useQuery({
    queryKey: ['narrowcasting', 'displays'],
    queryFn: async () => (await prmApi.getNarrowcastingDisplays()).data,
    refetchInterval: 30000,
    enabled: isAdmin,
  });

  const settingsQuery = useQuery({
    queryKey: ['narrowcasting', 'settings'],
    queryFn: async () => (await prmApi.getNarrowcastingSettings()).data,
    enabled: isAdmin,
  });

  const playlistsQuery = useQuery({
    queryKey: ['narrowcasting', 'playlists'],
    queryFn: async () => (await prmApi.getNarrowcastingPlaylists()).data,
    enabled: isAdmin,
  });

  useEffect(() => {
    if (!settingsQuery.data) return;
    setSportlinkForm((current) => ({
      ...current,
      club_relation_code: current.club_relation_code || settingsQuery.data.club_relation_code || '',
    }));
    setUpdateForm({
      stable_version: settingsQuery.data.player_updates?.stable_version || '0.3.0',
      beta_version: settingsQuery.data.player_updates?.beta_version || '',
    });
  }, [settingsQuery.data]);

  const refreshDisplays = () => queryClient.invalidateQueries({ queryKey: ['narrowcasting', 'displays'] });

  const claimMutation = useMutation({
    mutationFn: (data) => prmApi.claimNarrowcastingDisplay(data),
    onSuccess: () => {
      setNotice('De player is goedgekeurd. Hij rondt de koppeling nu zelf af.');
      setForm(defaultForm);
      refreshDisplays();
    },
  });

  const commandMutation = useMutation({
    mutationFn: ({ displayId, command }) => prmApi.queueNarrowcastingCommand(displayId, command),
    onSuccess: (_, variables) => {
      setNotice(`Commando “${commandLabels[variables.command]}” staat klaar.`);
      refreshDisplays();
    },
  });

  const revokeMutation = useMutation({
    mutationFn: (displayId) => prmApi.revokeNarrowcastingDisplay(displayId),
    onSuccess: () => {
      setNotice('De toegang van de player is ingetrokken.');
      refreshDisplays();
    },
  });

  const assignPlaylistMutation = useMutation({
    mutationFn: ({ displayId, playlistId }) => prmApi.assignNarrowcastingPlaylist(displayId, playlistId),
    onSuccess: () => {
      setNotice('De afspeellijst voor het scherm is opgeslagen.');
      refreshDisplays();
    },
  });

  const updateDisplayMutation = useMutation({
    mutationFn: ({ displayId, values }) => prmApi.updateNarrowcastingDisplay(displayId, values),
    onSuccess: () => {
      setNotice('De playerinstellingen zijn opgeslagen.');
      setEditingDisplayId(null);
      refreshDisplays();
    },
  });

  const settingsMutation = useMutation({
    mutationFn: (data) => prmApi.updateNarrowcastingSettings(data),
    onSuccess: () => {
      setNotice('De Sportlink-koppeling is opgeslagen.');
      setSportlinkForm((current) => ({ ...current, client_id: '' }));
      queryClient.invalidateQueries({ queryKey: ['narrowcasting', 'settings'] });
    },
  });

  const updateSettingsMutation = useMutation({
    mutationFn: (data) => prmApi.updateNarrowcastingSettings(data),
    onSuccess: () => {
      setNotice('De goedgekeurde player-versies zijn opgeslagen. Online players nemen de update vanzelf over.');
      queryClient.invalidateQueries({ queryKey: ['narrowcasting', 'settings'] });
      refreshDisplays();
    },
  });

  const matchdayRefreshMutation = useMutation({
    mutationFn: () => prmApi.refreshNarrowcastingMatchday(),
    onSuccess: () => {
      setNotice('De wedstrijdinformatie is bijgewerkt.');
      queryClient.invalidateQueries({ queryKey: ['narrowcasting', 'settings'] });
    },
  });

  const submitClaim = (event) => {
    event.preventDefault();
    setNotice('');
    claimMutation.mutate(form);
  };

  const queueCommand = (displayId, command) => {
    setNotice('');
    commandMutation.mutate({ displayId, command });
  };

  const shutdown = (display) => {
    if (!window.confirm(`Player “${display.name}” uitzetten? Daarna moet je de stroom kort onderbreken om hem weer aan te zetten.`)) return;
    queueCommand(display.id, 'shutdown');
  };

  const revoke = (display) => {
    if (!window.confirm(`Toegang voor “${display.name}” intrekken? De player moet daarna opnieuw gekoppeld worden.`)) return;
    setNotice('');
    revokeMutation.mutate(display.id);
  };

  const submitSportlink = (event) => {
    event.preventDefault();
    setNotice('');
    settingsMutation.mutate(sportlinkForm);
  };

  const submitUpdates = (event) => {
    event.preventDefault();
    setNotice('');
    updateSettingsMutation.mutate(updateForm);
  };

  const displays = displaysQuery.data || [];
  const mutationError = claimMutation.error
    || commandMutation.error
    || revokeMutation.error
    || assignPlaylistMutation.error
    || updateDisplayMutation.error
    || settingsMutation.error
    || updateSettingsMutation.error
    || matchdayRefreshMutation.error;
  const sportlink = settingsQuery.data;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Club TV</h1>
          <p className="mt-1 text-gray-600 dark:text-gray-400">
            Koppel en beheer de Raspberry Pi-players achter de schermen in het clubhuis.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <a href="/presenteren" className="btn-tertiary">
            <MonitorPlay className="mr-2 h-4 w-4" />
            Scherm delen
          </a>
          <a href="/display?preview=1" target="_blank" rel="noreferrer" className="btn-primary">
            <ExternalLink className="mr-2 h-4 w-4" />
            Voorbeeld openen
          </a>
        </div>
      </div>

      {notice && (
        <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
          <CircleCheck className="mt-0.5 h-5 w-5 shrink-0" />
          <span>{notice}</span>
        </div>
      )}

      {mutationError && (
        <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
          <CircleAlert className="mt-0.5 h-5 w-5 shrink-0" />
          <span>{errorMessage(mutationError)}</span>
        </div>
      )}

      <nav className="flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-gray-700" aria-label="Club TV-onderdelen">
        <button type="button" className={activeTab === 'content' ? 'btn-primary' : 'btn-tertiary'} onClick={() => setActiveTab('content')}>Content</button>
        {canManage && <button type="button" className={activeTab === 'playlists' ? 'btn-primary' : 'btn-tertiary'} onClick={() => setActiveTab('playlists')}>Afspeellijsten</button>}
        {isAdmin && <button type="button" className={activeTab === 'technical' ? 'btn-primary' : 'btn-tertiary'} onClick={() => setActiveTab('technical')}>Players & koppelingen</button>}
      </nav>

      {activeTab === 'content' && <NarrowcastingContent sponsorOnly={!canManage} />}
      {activeTab === 'playlists' && canManage && <NarrowcastingPlaylists />}

      {activeTab === 'technical' && isAdmin && (
        <>

      <section className="card p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Wedstrijdinformatie</h2>
              {sportlink && (
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${sportlink.client_id_configured ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200'}`}>
                  {sportlink.client_id_configured ? 'Sportlink gekoppeld' : 'Nog niet gekoppeld'}
                </span>
              )}
            </div>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
              Rondo haalt programma, kleedkamers, velden, afgelastingen en uitslagen server-side op.
            </p>
          </div>
          <button
            type="button"
            className="btn-tertiary text-sm"
            onClick={() => matchdayRefreshMutation.mutate()}
            disabled={!sportlink?.client_id_configured || matchdayRefreshMutation.isPending}
          >
            <RefreshCw className={`mr-2 h-4 w-4 ${matchdayRefreshMutation.isPending ? 'animate-spin' : ''}`} />
            Nu verversen
          </button>
        </div>

        <form onSubmit={submitSportlink} className="mt-5 grid gap-4 md:grid-cols-2">
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Sportlink client-ID</span>
            <input
              type="password"
              className="input w-full"
              value={sportlinkForm.client_id}
              onChange={(event) => setSportlinkForm({ ...sportlinkForm, client_id: event.target.value })}
              placeholder={sportlink?.client_id_configured ? '•••••••• (ongewijzigd)' : 'Client-ID'}
              autoComplete="new-password"
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Clubrelatiecode</span>
            <input
              className="input w-full uppercase"
              value={sportlinkForm.club_relation_code}
              onChange={(event) => setSportlinkForm({ ...sportlinkForm, club_relation_code: event.target.value.toUpperCase() })}
              placeholder="Bijvoorbeeld BBKX38Z"
              required
            />
          </label>
          <div className="md:col-span-2 flex flex-wrap items-center gap-4">
            <button type="submit" className="btn-primary" disabled={settingsMutation.isPending}>
              {settingsMutation.isPending ? 'Opslaan…' : 'Koppeling opslaan'}
            </button>
            {sportlink?.status?.last_success_at && (
              <p className={`text-sm ${sportlink.status.stale ? 'text-amber-700 dark:text-amber-300' : 'text-gray-600 dark:text-gray-400'}`}>
                Laatst bijgewerkt: {formatDateTime(sportlink.status.last_success_at)}
                {' · '}{sportlink.counts.matches} wedstrijden, {sportlink.counts.cancellations} afgelastingen, {sportlink.counts.results} uitslagen
              </p>
            )}
          </div>
        </form>

        {sportlink?.status?.last_error && (
          <p className="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
            Laatste Sportlink-fout: {sportlink.status.last_error} Eerder opgehaalde gegevens blijven beschikbaar.
          </p>
        )}
      </section>

      <section className="card p-6">
        <div className="flex items-start gap-3">
          <div className="rounded-lg bg-cyan-50 p-2 text-bright-cobalt dark:bg-gray-800 dark:text-electric-cyan">
            <RefreshCw className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Automatische player-updates</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Players installeren alleen ondertekende releases. Een update die niet gezond start wordt binnen twee minuten teruggedraaid.
            </p>
          </div>
        </div>
        <form onSubmit={submitUpdates} className="mt-5 grid gap-4 md:grid-cols-2">
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Stabiele versie</span>
            <input
              className="input w-full"
              value={updateForm.stable_version}
              onChange={(event) => setUpdateForm({ ...updateForm, stable_version: event.target.value })}
              placeholder="0.3.0"
              pattern="[0-9]+\\.[0-9]+\\.[0-9]+"
              required
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Betaversie</span>
            <input
              className="input w-full"
              value={updateForm.beta_version}
              onChange={(event) => setUpdateForm({ ...updateForm, beta_version: event.target.value })}
              placeholder="Leeg: geen beta-update"
              pattern="[0-9]+\\.[0-9]+\\.[0-9]+"
            />
          </label>
          <div className="md:col-span-2">
            <button type="submit" className="btn-primary" disabled={updateSettingsMutation.isPending}>
              {updateSettingsMutation.isPending ? 'Opslaan…' : 'Updateversies opslaan'}
            </button>
          </div>
        </form>
      </section>

      <section className="card p-6">
        <div className="flex items-start gap-3">
          <div className="rounded-lg bg-cyan-50 p-2 text-bright-cobalt dark:bg-gray-800 dark:text-electric-cyan">
            <MonitorPlay className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Nieuwe player koppelen</h2>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              Neem de code over die op de tv staat. De code blijft 15 minuten geldig.
            </p>
          </div>
        </div>

        <form onSubmit={submitClaim} className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Activatiecode</span>
            <input
              className="input w-full uppercase tracking-widest"
              value={form.code}
              onChange={(event) => setForm({ ...form, code: event.target.value.toUpperCase() })}
              placeholder="ABCD-EFGH"
              required
              autoComplete="off"
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Automatische updates</span>
            <select className="input w-full" value={form.update_channel} onChange={(event) => setForm({ ...form, update_channel: event.target.value })}>
              <option value="stable">Stabiel — aanbevolen</option>
              <option value="beta">Beta</option>
              <option value="off">Uit</option>
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Naam</span>
            <input
              className="input w-full"
              value={form.title}
              onChange={(event) => setForm({ ...form, title: event.target.value })}
              placeholder="Scherm kantine"
              required
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Locatie</span>
            <input
              className="input w-full"
              value={form.location}
              onChange={(event) => setForm({ ...form, location: event.target.value })}
              placeholder="Kantine"
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tv aan</span>
            <input
              type="time"
              className="input w-full"
              value={form.wake_time}
              onChange={(event) => setForm({ ...form, wake_time: event.target.value })}
              required
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tv uit</span>
            <input
              type="time"
              className="input w-full"
              value={form.sleep_time}
              onChange={(event) => setForm({ ...form, sleep_time: event.target.value })}
              required
            />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tijdzone</span>
            <input
              className="input w-full"
              value={form.timezone}
              onChange={(event) => setForm({ ...form, timezone: event.target.value })}
              required
            />
          </label>
          <div className="md:col-span-2 xl:col-span-3">
            <button type="submit" className="btn-primary" disabled={claimMutation.isPending}>
              {claimMutation.isPending ? 'Koppelen…' : 'Player goedkeuren'}
            </button>
          </div>
        </form>
      </section>

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Schermen</h2>
          <button type="button" className="btn-tertiary text-sm" onClick={() => displaysQuery.refetch()} disabled={displaysQuery.isFetching}>
            <RefreshCw className={`mr-2 h-4 w-4 ${displaysQuery.isFetching ? 'animate-spin' : ''}`} />
            Vernieuwen
          </button>
        </div>

        {displaysQuery.isError && (
          <div className="card p-6 text-red-700 dark:text-red-300">{errorMessage(displaysQuery.error)}</div>
        )}

        {!displaysQuery.isLoading && displays.length === 0 && (
          <div className="card p-8 text-center text-gray-600 dark:text-gray-400">Er zijn nog geen players gekoppeld.</div>
        )}

        <div className="grid gap-4 xl:grid-cols-2">
          {displays.map((display) => (
            <article key={display.id} className="card p-5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{display.name}</h3>
                    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${display.online ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'}`}>
                      <span className={`h-2 w-2 rounded-full ${display.online ? 'bg-green-500' : 'bg-gray-400'}`} />
                      {display.online ? 'Online' : 'Offline'}
                    </span>
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                      {display.pairing_status === 'paired' ? 'Gekoppeld' : display.pairing_status === 'approved' ? 'Goedgekeurd' : 'Ingetrokken'}
                    </span>
                  </div>
                  <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{display.location || 'Geen locatie'} · {display.device_id}</p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  {editingDisplayId === display.id ? (
                    <button type="button" className="btn-tertiary p-2" onClick={() => setEditingDisplayId(null)} aria-label="Bewerken sluiten">
                      <X className="h-4 w-4" />
                    </button>
                  ) : (
                    <button type="button" className="btn-tertiary p-2" onClick={() => setEditingDisplayId(display.id)} disabled={display.pairing_status === 'revoked'} aria-label={`${display.name} bewerken`}>
                      <Pencil className="h-4 w-4" />
                    </button>
                  )}
                  <MonitorPlay className="h-6 w-6 text-gray-400" />
                </div>
              </div>

              {editingDisplayId === display.id && (
                <DisplayEditForm
                  key={display.id}
                  display={display}
                  isPending={updateDisplayMutation.isPending}
                  onCancel={() => setEditingDisplayId(null)}
                  onSave={(displayId, values) => {
                    setNotice('');
                    updateDisplayMutation.mutate({ displayId, values });
                  }}
                />
              )}

              <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-gray-500 dark:text-gray-400">Laatst gezien</dt>
                  <dd className="font-medium text-gray-900 dark:text-gray-100">{formatDateTime(display.last_seen_at)}</dd>
                </div>
                <div>
                  <dt className="text-gray-500 dark:text-gray-400">Versie</dt>
                  <dd className="font-medium text-gray-900 dark:text-gray-100">{display.player_version || 'Onbekend'}</dd>
                </div>
                <div>
                  <dt className="text-gray-500 dark:text-gray-400">Schema</dt>
                  <dd className="font-medium text-gray-900 dark:text-gray-100">{display.wake_time}–{display.sleep_time}</dd>
                </div>
                <div>
                  <dt className="text-gray-500 dark:text-gray-400">Status</dt>
                  <dd className="font-medium text-gray-900 dark:text-gray-100">{display.last_playback_state || 'Nog niet gemeld'}</dd>
                </div>
                <div>
                  <dt className="text-gray-500 dark:text-gray-400">Updates</dt>
                  <dd className="font-medium text-gray-900 dark:text-gray-100">{updateChannelLabels[display.update_channel] || 'Stabiel'}</dd>
                </div>
              </dl>

              {versionNeedsUpdate(display.player_version, display.update_target_version) && (
                <p className="mt-4 rounded-md bg-cyan-50 p-3 text-sm text-cyan-900 dark:bg-cyan-950 dark:text-cyan-100">
                  Update naar {display.update_target_version} staat klaar en wordt automatisch geïnstalleerd.
                </p>
              )}

              <label className="mt-4 block text-sm">
                <span className="mb-1 block font-medium text-gray-700 dark:text-gray-300">Afspeellijst</span>
                <select
                  className="input w-full"
                  value={display.assigned_playlist_id || ''}
                  onChange={(event) => assignPlaylistMutation.mutate({ displayId: display.id, playlistId: Number(event.target.value) || 0 })}
                  disabled={assignPlaylistMutation.isPending}
                >
                  <option value="">Standaard afspeellijst</option>
                  {(playlistsQuery.data || []).map((playlist) => <option key={playlist.id} value={playlist.id}>{playlist.title}</option>)}
                </select>
              </label>

              {display.last_error && (
                <p className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{display.last_error}</p>
              )}
              {display.command && (
                <p className="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                  Wachtend commando: {commandLabels[display.command.name] || display.command.name}
                </p>
              )}

              <div className="mt-5 flex flex-wrap gap-2">
                <button type="button" className="btn-tertiary text-sm" onClick={() => queueCommand(display.id, 'wake_tv')} disabled={display.pairing_status !== 'paired' || commandMutation.isPending}>
                  <Sun className="mr-2 h-4 w-4" />Tv aan
                </button>
                <button type="button" className="btn-tertiary text-sm" onClick={() => queueCommand(display.id, 'sleep_tv')} disabled={display.pairing_status !== 'paired' || commandMutation.isPending}>
                  <Moon className="mr-2 h-4 w-4" />Tv uit
                </button>
                <button type="button" className="btn-tertiary text-sm" onClick={() => queueCommand(display.id, 'reload')} disabled={display.pairing_status !== 'paired' || commandMutation.isPending}>
                  <RotateCcw className="mr-2 h-4 w-4" />Beeld vernieuwen
                </button>
                <button type="button" className="btn-tertiary text-sm" onClick={() => queueCommand(display.id, 'reboot')} disabled={display.pairing_status !== 'paired' || commandMutation.isPending}>
                  <Power className="mr-2 h-4 w-4" />Player herstarten
                </button>
                <button type="button" className="btn-tertiary text-sm text-red-700 dark:text-red-300" onClick={() => shutdown(display)} disabled={display.pairing_status !== 'paired' || commandMutation.isPending}>
                  <PowerOff className="mr-2 h-4 w-4" />Player uitzetten
                </button>
                <button type="button" className="btn-tertiary text-sm text-red-700 dark:text-red-300" onClick={() => revoke(display)} disabled={display.pairing_status === 'revoked' || revokeMutation.isPending}>
                  <Unplug className="mr-2 h-4 w-4" />Intrekken
                </button>
              </div>
            </article>
          ))}
        </div>
      </section>
        </>
      )}
    </div>
  );
}
