import { useParams, useNavigate, Link } from 'react-router-dom';
import { Users, CheckCircle, XCircle, Clock, Loader2 } from 'lucide-react';
import { useValidateInvite, useAcceptInvite } from '@/hooks/useWorkspaces';

export default function WorkspaceInviteAccept() {
  const { token } = useParams();
  const navigate = useNavigate();

  const { data: invite, isLoading, error } = useValidateInvite(token);
  const acceptMutation = useAcceptInvite();

  const handleAccept = async () => {
    try {
      const result = await acceptMutation.mutateAsync(token);
      // Redirect to the workspace after accepting
      navigate(`/workspaces/${result.workspace_id}`);
    } catch {
      // Error will be displayed via acceptMutation.error
    }
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="card p-8 max-w-md w-full mx-4 text-center">
          <Loader2 className="w-8 h-8 text-primary-600 animate-spin mx-auto mb-4" />
          <p className="text-gray-600">Validating invitation...</p>
        </div>
      </div>
    );
  }

  // Error state (invalid or expired token)
  if (error || !invite) {
    const errorMessage = error?.response?.data?.message || 'This invitation is invalid or has expired.';

    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="card p-8 max-w-md w-full mx-4 text-center">
          <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <XCircle className="w-8 h-8 text-red-600" />
          </div>
          <h1 className="text-xl font-semibold text-gray-900 mb-2">
            Invalid Invitation
          </h1>
          <p className="text-gray-600 mb-6">{errorMessage}</p>
          <Link to="/workspaces" className="btn-primary inline-block">
            Go to Workspaces
          </Link>
        </div>
      </div>
    );
  }

  // Already accepted (shouldn't normally happen, but handle gracefully)
  if (invite.status === 'accepted') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="card p-8 max-w-md w-full mx-4 text-center">
          <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <CheckCircle className="w-8 h-8 text-green-600" />
          </div>
          <h1 className="text-xl font-semibold text-gray-900 mb-2">
            Already Accepted
          </h1>
          <p className="text-gray-600 mb-6">
            This invitation has already been accepted.
          </p>
          <Link to={`/workspaces/${invite.workspace_id}`} className="btn-primary inline-block">
            Go to Workspace
          </Link>
        </div>
      </div>
    );
  }

  // Valid invitation - show accept UI
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="card p-8 max-w-md w-full mx-4">
        {/* Header */}
        <div className="text-center mb-6">
          <div className="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Users className="w-8 h-8 text-primary-600" />
          </div>
          <h1 className="text-xl font-semibold text-gray-900">
            Workspace Invitation
          </h1>
        </div>

        {/* Invitation details */}
        <div className="bg-gray-50 rounded-lg p-4 mb-6">
          <div className="text-center">
            <p className="text-sm text-gray-500 mb-1">You have been invited to join</p>
            <p className="text-lg font-semibold text-gray-900">{invite.workspace_name}</p>
          </div>

          <hr className="my-4 border-gray-200" />

          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-gray-500">Invited by</p>
              <p className="font-medium text-gray-900">{invite.invited_by}</p>
            </div>
            <div>
              <p className="text-gray-500">Your role</p>
              <p className="font-medium text-gray-900 capitalize">{invite.role}</p>
            </div>
          </div>

          <div className="mt-4 flex items-center justify-center gap-2 text-sm text-gray-500">
            <Clock className="w-4 h-4" />
            Expires {new Date(invite.expires_at).toLocaleDateString()}
          </div>
        </div>

        {/* Error message */}
        {acceptMutation.error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p className="text-sm text-red-600">
              {acceptMutation.error?.response?.data?.message || 'Failed to accept invitation'}
            </p>
          </div>
        )}

        {/* Actions */}
        <div className="flex flex-col gap-3">
          <button
            onClick={handleAccept}
            disabled={acceptMutation.isPending}
            className="btn-primary w-full justify-center"
          >
            {acceptMutation.isPending ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin mr-2" />
                Joining...
              </>
            ) : (
              'Accept Invitation'
            )}
          </button>

          <Link
            to="/workspaces"
            className="btn-secondary w-full justify-center text-center"
          >
            Decline
          </Link>
        </div>

        {/* Note about email */}
        <p className="text-xs text-gray-500 text-center mt-4">
          This invitation was sent to {invite.email}
        </p>
      </div>
    </div>
  );
}
