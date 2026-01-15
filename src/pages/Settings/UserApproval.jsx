import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { CheckCircle2, XCircle, Loader2, ArrowLeft, ShieldAlert, Trash2 } from 'lucide-react';

export default function UserApproval() {
  useDocumentTitle('User Approval - Settings');
  const queryClient = useQueryClient();
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;
  
  // Check if user is admin
  if (!isAdmin) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="card p-8 text-center">
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 dark:text-amber-400 mb-4" />
          <h1 className="text-2xl font-bold dark:text-gray-50 mb-2">Access Denied</h1>
          <p className="text-gray-600 dark:text-gray-300 mb-6">
            You don't have permission to manage user approvals. This feature is only available to administrators.
          </p>
          <Link to="/settings" className="btn-primary">
            Back to Settings
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
    if (window.confirm('Are you sure you want to approve this user?')) {
      approveMutation.mutate(userId);
    }
  };
  
  const handleDeny = (userId) => {
    if (window.confirm('Are you sure you want to deny this user? They will not be able to access the system.')) {
      denyMutation.mutate(userId);
    }
  };
  
  const handleDelete = (userId, userName) => {
    if (window.confirm(`Are you sure you want to delete ${userName}? This will permanently delete their account and all their related data (people, organizations, dates). This action cannot be undone.`)) {
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
          <span className="hidden md:inline">Back to Settings</span>
        </Link>
        <h1 className="text-2xl font-semibold dark:text-gray-50">User Approval</h1>
      </div>

      <div className="space-y-6">
      {unapprovedUsers.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold dark:text-gray-50 mb-4">Pending Approval</h2>
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
                    Registered: {new Date(user.registered).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleApprove(user.id)}
                    disabled={approveMutation.isPending || deleteMutation.isPending}
                    className="btn-primary flex items-center gap-2"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    Approve
                  </button>
                  <button
                    onClick={() => handleDeny(user.id)}
                    disabled={denyMutation.isPending || deleteMutation.isPending}
                    className="btn-secondary flex items-center gap-2"
                  >
                    <XCircle className="w-4 h-4" />
                    Deny
                  </button>
                  <button
                    onClick={() => handleDelete(user.id, user.name)}
                    disabled={approveMutation.isPending || denyMutation.isPending || deleteMutation.isPending}
                    className="btn-danger flex items-center gap-2"
                  >
                    <Trash2 className="w-4 h-4" />
                    Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {approvedUsers.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold dark:text-gray-50 mb-4">Approved Users</h2>
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
                    Revoke Access
                  </button>
                  <button
                    onClick={() => handleDelete(user.id, user.name)}
                    disabled={denyMutation.isPending || deleteMutation.isPending}
                    className="btn-danger flex items-center gap-2"
                  >
                    <Trash2 className="w-4 h-4" />
                    Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {users.length === 0 && (
        <div className="card p-8 text-center">
          <p className="text-gray-600 dark:text-gray-300">No Caelis users found.</p>
        </div>
      )}
      </div>
    </div>
  );
}

