import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { CheckCircle2, XCircle, Loader2, ArrowLeft, ShieldAlert } from 'lucide-react';

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
          <ShieldAlert className="w-16 h-16 mx-auto text-amber-500 mb-4" />
          <h1 className="text-2xl font-bold mb-2">Access Denied</h1>
          <p className="text-gray-600 mb-6">
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
  
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-primary-600" />
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
        <h1 className="text-2xl font-semibold">User Approval</h1>
      </div>
      
      <div className="space-y-6">
      {unapprovedUsers.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold mb-4">Pending Approval</h2>
          <div className="space-y-3">
            {unapprovedUsers.map((user) => (
              <div
                key={user.id}
                className="card p-4 flex items-center justify-between"
              >
                <div>
                  <p className="font-medium">{user.name}</p>
                  <p className="text-sm text-gray-500">{user.email}</p>
                  <p className="text-xs text-gray-400">
                    Registered: {new Date(user.registered).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleApprove(user.id)}
                    disabled={approveMutation.isPending}
                    className="btn-primary flex items-center gap-2"
                  >
                    <CheckCircle2 className="w-4 h-4" />
                    Approve
                  </button>
                  <button
                    onClick={() => handleDeny(user.id)}
                    disabled={denyMutation.isPending}
                    className="btn-secondary flex items-center gap-2"
                  >
                    <XCircle className="w-4 h-4" />
                    Deny
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
      
      {approvedUsers.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold mb-4">Approved Users</h2>
          <div className="space-y-3">
            {approvedUsers.map((user) => (
              <div
                key={user.id}
                className="card p-4 flex items-center justify-between"
              >
                <div>
                  <p className="font-medium flex items-center gap-2">
                    {user.name}
                    <CheckCircle2 className="w-4 h-4 text-green-600" />
                  </p>
                  <p className="text-sm text-gray-500">{user.email}</p>
                </div>
                <button
                  onClick={() => handleDeny(user.id)}
                  disabled={denyMutation.isPending}
                  className="btn-secondary flex items-center gap-2"
                >
                  <XCircle className="w-4 h-4" />
                  Revoke Access
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
      
      {users.length === 0 && (
        <div className="card p-8 text-center">
          <p className="text-gray-600">No Caelis users found.</p>
        </div>
      )}
      </div>
    </div>
  );
}

