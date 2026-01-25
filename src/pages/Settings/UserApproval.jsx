import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { CheckCircle2, XCircle, Loader2, ArrowLeft, ShieldAlert, Trash2 } from 'lucide-react';

export default function UserApproval() {
  useDocumentTitle('Gebruikersgoedkeuring - Instellingen');
  const queryClient = useQueryClient();
  const config = window.stadionConfig || {};
  const isAdmin = config.isAdmin || false;

  // Check if user is admin
  if (!isAdmin) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="card p-8 text-center">
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Toegang geweigerd</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            Je hebt geen toestemming om gebruikersgoedkeuringen te beheren. Deze functie is alleen beschikbaar voor beheerders.
          </p>
          <Link to="/settings" className="btn-primary">
            Terug naar Instellingen
          </Link>
        </div>
      </div>
    );
  }

  const { data: users = [], isLoading } = useQuery({
    queryKey: ['users'],
    queryFn: async () => {
      const response = await prmApi.getUsers();
      return response.data;
    },
  });

  const approveMutation = useMutation({
    mutationFn: (userId) => prmApi.approveUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });

  const denyMutation = useMutation({
    mutationFn: (userId) => prmApi.denyUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (userId) => prmApi.deleteUser(userId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
    },
  });

  const handleApprove = (userId) => {
    if (window.confirm('Weet je zeker dat je deze gebruiker wilt goedkeuren?')) {
      approveMutation.mutate(userId);
    }
  };

  const handleDeny = (userId) => {
    if (window.confirm('Weet je zeker dat je deze gebruiker wilt weigeren? Ze kunnen geen toegang krijgen tot het systeem.')) {
      denyMutation.mutate(userId);
    }
  };

  const handleDelete = (userId, userName) => {
    if (window.confirm(`Weet je zeker dat je ${userName} wilt verwijderen? Dit zal hun account en alle gerelateerde gegevens (leden, organisaties, datums) permanent verwijderen. Dit kan niet ongedaan worden gemaakt.`)) {
      deleteMutation.mutate(userId);
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-accent-600 dark:text-accent-400" />
      </div>
    );
  }

  const unapprovedUsers = users.filter(u => !u.is_approved);
  const approvedUsers = users.filter(u => u.is_approved);

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-4">
        <Link
          to="/settings"
          className="btn-secondary flex items-center gap-2"
        >
          <ArrowLeft className="w-4 h-4" />
          <span className="hidden md:inline">Terug naar Instellingen</span>
        </Link>
        <h1 className="text-2xl font-semibold dark:text-gray-50">Gebruikersgoedkeuring</h1>
      </div>

      <div className="space-y-6">
      {unapprovedUsers.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold dark:text-gray-50 mb-4">Wacht op goedkeuring</h2>
          <div className="space-y-3">
            {unapprovedUsers.map((user) => (
              <div
                key={user.id}
                className="card p-4 flex items-center justify-between"
              >
                <div>
                  <p className="font-medium dark:text-gray-50">{user.name}</p>
                  <p className="text-sm text-gray-500 dark:text-gray-400">{user.email}</p>
                  <p className="text-xs text-gray-400 dark:text-gray-500">
                    Geregistreerd: {new Date(user.registered).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleApprove(user.id)}
                    disabled={approveMutation.isPending || deleteMutation.isPending}
                    className="btn-primary flex items-center gap-2"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    Goedkeuren
                  </button>
                  <button
                    onClick={() => handleDeny(user.id)}
                    disabled={denyMutation.isPending || deleteMutation.isPending}
                    className="btn-secondary flex items-center gap-2"
                  >
                    <XCircle className="w-4 h-4" />
                    Weigeren
                  </button>
                  <button
                    onClick={() => handleDelete(user.id, user.name)}
                    disabled={approveMutation.isPending || denyMutation.isPending || deleteMutation.isPending}
                    className="btn-danger flex items-center gap-2"
                  >
                    <Trash2 className="w-4 h-4" />
                    Verwijderen
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {approvedUsers.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold dark:text-gray-50 mb-4">Goedgekeurde gebruikers</h2>
          <div className="space-y-3">
            {approvedUsers.map((user) => (
              <div
                key={user.id}
                className="card p-4 flex items-center justify-between"
              >
                <div>
                  <p className="font-medium dark:text-gray-50 flex items-center gap-2">
                    {user.name}
                    <CheckCircle2 className="w-4 h-4 text-green-600 dark:text-green-400" />
                  </p>
                  <p className="text-sm text-gray-500 dark:text-gray-400">{user.email}</p>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleDeny(user.id)}
                    disabled={denyMutation.isPending || deleteMutation.isPending}
                    className="btn-secondary flex items-center gap-2"
                  >
                    <XCircle className="w-4 h-4" />
                    Toegang intrekken
                  </button>
                  <button
                    onClick={() => handleDelete(user.id, user.name)}
                    disabled={denyMutation.isPending || deleteMutation.isPending}
                    className="btn-danger flex items-center gap-2"
                  >
                    <Trash2 className="w-4 h-4" />
                    Verwijderen
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {users.length === 0 && (
        <div className="card p-8 text-center">
          <p className="text-gray-600 dark:text-gray-300">Geen Stadion-gebruikers gevonden.</p>
        </div>
      )}
      </div>
    </div>
  );
}
