import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useTournaments() {
  return useQuery({
    queryKey: ['tournaments'],
    queryFn: async () => (await prmApi.getTournaments()).data,
  });
}

export function useTournament(id) {
  return useQuery({
    queryKey: ['tournaments', Number(id)],
    queryFn: async () => (await prmApi.getTournament(id)).data,
    enabled: Boolean(id),
  });
}

export function useTournamentEntries(id) {
  return useQuery({
    queryKey: ['tournaments', Number(id), 'entries'],
    queryFn: async () => (await prmApi.getTournamentEntries(id)).data,
    enabled: Boolean(id),
  });
}

export function useTournamentAssignmentOptions(enabled = true) {
  return useQuery({
    queryKey: ['tournaments', 'assignment-options'],
    queryFn: async () => (await prmApi.getTournamentAssignmentOptions()).data,
    enabled,
    staleTime: 60_000,
  });
}

export function useSaveTournament() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }) => (
      id
        ? (await prmApi.updateTournament(id, data)).data
        : (await prmApi.createTournament(data)).data
    ),
    onSuccess: (tournament) => {
      queryClient.setQueryData(['tournaments', Number(tournament.id)], tournament);
      queryClient.invalidateQueries({ queryKey: ['tournaments'] });
    },
  });
}

export function useDeleteTournament() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id) => (await prmApi.deleteTournament(id)).data,
    onSuccess: (_, id) => {
      queryClient.removeQueries({ queryKey: ['tournaments', Number(id)] });
      queryClient.invalidateQueries({ queryKey: ['tournaments'] });
      queryClient.invalidateQueries({ queryKey: ['tournament-entries'] });
    },
  });
}

export function usePublishTournament() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, assignments }) => (await prmApi.publishTournament(id, assignments)).data,
    onSuccess: (result) => {
      const id = Number(result.tournament.id);
      queryClient.setQueryData(['tournaments', id], result.tournament);
      queryClient.setQueryData(['tournaments', id, 'entries'], result.entries);
      queryClient.invalidateQueries({ queryKey: ['tournaments'] });
    },
  });
}

export function useExtendTournamentDeadline() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, internalDeadline }) => (await prmApi.extendTournamentDeadline(id, internalDeadline)).data,
    onSuccess: (tournament) => {
      const id = Number(tournament.id);
      queryClient.setQueryData(['tournaments', id], tournament);
      queryClient.invalidateQueries({ queryKey: ['tournaments'] });
      queryClient.invalidateQueries({ queryKey: ['tournament-entries'] });
    },
  });
}

export function useMyTournamentEntries() {
  return useQuery({
    queryKey: ['tournament-entries', 'mine'],
    queryFn: async () => (await prmApi.getMyTournamentEntries()).data,
  });
}

export function useTournamentEntry(id) {
  return useQuery({
    queryKey: ['tournament-entries', Number(id)],
    queryFn: async () => (await prmApi.getTournamentEntry(id)).data,
    enabled: Boolean(id),
  });
}

export function useSaveTournamentEntryDraft() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }) => (await prmApi.saveTournamentEntryDraft(id, data)).data,
    onSuccess: (entry) => {
      queryClient.setQueryData(['tournament-entries', Number(entry.id)], entry);
      queryClient.invalidateQueries({ queryKey: ['tournament-entries', 'mine'] });
    },
  });
}

export function useSubmitTournamentEntry() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }) => (await prmApi.submitTournamentEntry(id, data)).data,
    onSuccess: (entry) => {
      queryClient.setQueryData(['tournament-entries', Number(entry.id)], entry);
      queryClient.invalidateQueries({ queryKey: ['tournament-entries', 'mine'] });
    },
  });
}
