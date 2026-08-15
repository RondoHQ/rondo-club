import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  CircleAlert,
  CircleCheck,
  ExternalLink,
  MonitorPlay,
  Moon,
  Power,
  RefreshCw,
  RotateCcw,
  Sun,
  Unplug,
} from 'lucide-react';
import { prmApi } from '@/api/client';
import { useRouteTitle } from '@/hooks/useDocumentTitle';

const defaultForm = {
  code: '',
  title: '',
  location: '',
  wake_time: '08:00',
  sleep_time: '23:00',
  timezone: 'Europe/Amsterdam',
};

const commandLabels = {
  wake_tv: 'Tv aan',
  sleep_tv: 'Tv uit',
  reload: 'Beeld vernieuwen',
  restart_browser: 'Browser herstarten',
  reboot: 'Player herstarten',
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

export default function Narrowcasting() {
  useRouteTitle('Club TV');
  const queryClient = useQueryClient();
  const [form, setForm] = useState(defaultForm);
  const [notice, setNotice] = useState('');

  const displaysQuery = useQuery({
    queryKey: ['narrowcasting', 'displays'],
    queryFn: async () => (await prmApi.getNarrowcastingDisplays()).data,
    refetchInterval: 30000,
  });

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

  const submitClaim = (event) => {
    event.preventDefault();
    setNotice('');
    claimMutation.mutate(form);
  };

  const queueCommand = (displayId, command) => {
    setNotice('');
    commandMutation.mutate({ displayId, command });
  };

  const revoke = (display) => {
    if (!window.confirm(`Toegang voor “${display.name}” intrekken? De player moet daarna opnieuw gekoppeld worden.`)) return;
    setNotice('');
    revokeMutation.mutate(display.id);
  };

  const displays = displaysQuery.data || [];
  const mutationError = claimMutation.error || commandMutation.error || revokeMutation.error;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Club TV</h1>
          <p className="mt-1 text-gray-600 dark:text-gray-400">
            Koppel en beheer de Raspberry Pi-players achter de schermen in het clubhuis.
          </p>
        </div>
        <a href="/display?preview=1" target="_blank" rel="noreferrer" className="btn-primary">
          <ExternalLink className="mr-2 h-4 w-4" />
          Voorbeeld openen
        </a>
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
                <MonitorPlay className="h-6 w-6 shrink-0 text-gray-400" />
              </div>

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
              </dl>

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
                <button type="button" className="btn-tertiary text-sm text-red-700 dark:text-red-300" onClick={() => revoke(display)} disabled={display.pairing_status === 'revoked' || revokeMutation.isPending}>
                  <Unplug className="mr-2 h-4 w-4" />Intrekken
                </button>
              </div>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
