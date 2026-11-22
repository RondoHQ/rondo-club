import { useEffect, useRef } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { RefreshCw } from 'lucide-react';
import { prmApi } from '@/api/client';

export default function TwelveSyncButton() {
  const queryClient = useQueryClient();
  const wasRunning = useRef(false);
  const previousRunId = useRef(null);
  const status = useQuery({
    queryKey: ['twelve-sync-status'],
    queryFn: async () => (await prmApi.getTwelve('sync')).data,
    refetchInterval: 5000,
    retry: false,
  });
  const sync = useMutation({
    mutationFn: () => {
      previousRunId.current = status.data?.last_run?.id ?? null;
      return prmApi.syncTwelve();
    },
    onSuccess: () => {
      queryClient.setQueryData(['twelve-sync-status'], current => ({ ...current, running: true }));
      return queryClient.invalidateQueries({ queryKey: ['twelve-sync-status'] });
    },
    onError: () => queryClient.invalidateQueries({ queryKey: ['twelve-sync-status'] }),
  });
  const running = Boolean(status.data?.running);
  useEffect(() => {
    if (wasRunning.current && !running) queryClient.invalidateQueries({ queryKey: ['twelve'] });
    wasRunning.current = running;
  }, [running, queryClient]);
  const busy = sync.isPending || running;
  const completed = sync.isSuccess && !busy && !status.isError;
  return <div className="space-y-2">
    <button type="button" className="btn-secondary inline-flex items-center gap-2" disabled={busy || status.isPending || status.isError} onClick={() => sync.mutate()}>
      <RefreshCw aria-hidden="true" className={`w-4 h-4 ${busy ? 'animate-spin' : ''}`} />
      {busy ? 'Synchroniseren…' : 'Nu synchroniseren'}
    </button>
    {busy && <p role="status" className="text-sm text-gray-500 dark:text-gray-400">De recente kassaomzet wordt opgehaald. Dit kan enkele minuten duren.</p>}
    {completed && <p role="status" className="text-sm">{status.data?.last_run?.id !== previousRunId.current && status.data?.last_run?.outcome === 'success' ? 'Synchronisatie voltooid. De rapportages zijn bijgewerkt.' : 'Synchronisatie is gestopt zonder succes. Probeer opnieuw.'}</p>}
    {(sync.isError || status.isError) && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{sync.error?.response?.data?.message || status.error?.response?.data?.message || 'Synchronisatie kon niet worden gecontroleerd. Probeer opnieuw.'}</p>}
    {status.isError && <button type="button" className="text-sm underline" onClick={() => status.refetch()}>Opnieuw controleren</button>}
  </div>;
}
