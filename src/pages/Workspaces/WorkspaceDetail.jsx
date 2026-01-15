import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Settings, UserPlus, Crown, Shield, Users, Eye, MoreVertical, Trash2, UserMinus, Calendar, Copy, Check } from 'lucide-react';
import { useWorkspace, useWorkspaceInvites, useRemoveWorkspaceMember, useUpdateWorkspaceMember, useRevokeWorkspaceInvite } from '@/hooks/useWorkspaces';
import WorkspaceInviteModal from '@/components/WorkspaceInviteModal';
import { prmApi } from '@/api/client';

function RoleBadge({ role, isOwner, small = false }) {
  if (isOwner) {
    return (
      <span className={`inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300 rounded-full ${small ? 'text-xs' : 'text-sm'}`}>
        <Crown className={small ? 'w-3 h-3' : 'w-4 h-4'} />
        Owner
      </span>
    );
  }

  const roleConfig = {
    admin: { icon: Shield, bg: 'bg-purple-100 dark:bg-purple-900/50', text: 'text-purple-800 dark:text-purple-300', label: 'Admin' },
    member: { icon: Users, bg: 'bg-blue-100 dark:bg-blue-900/50', text: 'text-blue-800 dark:text-blue-300', label: 'Member' },
    viewer: { icon: Eye, bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-600 dark:text-gray-300', label: 'Viewer' },
  };

  const config = roleConfig[role] || roleConfig.member;
  const Icon = config.icon;

  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 ${config.bg} ${config.text} rounded-full ${small ? 'text-xs' : 'text-sm'}`}>
      <Icon className={small ? 'w-3 h-3' : 'w-4 h-4'} />
      {config.label}
    </span>
  );
}

function MemberRow({ member, currentUserRole, onRemove, onChangeRole }) {
  const [showMenu, setShowMenu] = useState(false);
  const canManage = currentUserRole === 'admin' || currentUserRole === 'owner';
  const isOwnerMember = member.is_owner;

  return (
    <div className="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700">
      <img
        src={member.avatar_url}
        alt={member.display_name}
        className="w-10 h-10 rounded-full"
      />
      <div className="flex-1 min-w-0">
        <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{member.display_name}</div>
        <div className="text-xs text-gray-500 dark:text-gray-400">{member.email}</div>
      </div>
      <RoleBadge role={member.role} isOwner={member.is_owner} small />

      {canManage && !isOwnerMember && (
        <div className="relative">
          <button
            onClick={() => setShowMenu(!showMenu)}
            className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <MoreVertical className="w-4 h-4" />
          </button>

          {showMenu && (
            <>
              <div className="fixed inset-0" onClick={() => setShowMenu(false)} />
              <div className="absolute right-0 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-10">
                <div className="py-1">
                  <button
                    onClick={() => { onChangeRole(member.user_id, 'admin'); setShowMenu(false); }}
                    className="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    Make Admin
                  </button>
                  <button
                    onClick={() => { onChangeRole(member.user_id, 'member'); setShowMenu(false); }}
                    className="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    Make Member
                  </button>
                  <button
                    onClick={() => { onChangeRole(member.user_id, 'viewer'); setShowMenu(false); }}
                    className="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    Make Viewer
                  </button>
                  <hr className="my-1 dark:border-gray-700" />
                  <button
                    onClick={() => { onRemove(member.user_id); setShowMenu(false); }}
                    className="w-full px-4 py-2 text-left text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2"
                  >
                    <UserMinus className="w-4 h-4" />
                    Remove
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}

function InviteRow({ invite, onRevoke }) {
  return (
    <div className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50">
      <div className="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
        <UserPlus className="w-5 h-5 text-gray-400 dark:text-gray-500" />
      </div>
      <div className="flex-1 min-w-0">
        <div className="text-sm font-medium text-gray-900 dark:text-gray-50">{invite.email}</div>
        <div className="text-xs text-gray-500 dark:text-gray-400">
          Invited as {invite.role} · Expires {new Date(invite.expires_at).toLocaleDateString()}
        </div>
      </div>
      <span className="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300 text-xs rounded-full">Pending</span>
      <button
        onClick={() => onRevoke(invite.id)}
        className="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded-full hover:bg-red-50 dark:hover:bg-red-900/30"
        title="Revoke invite"
      >
        <Trash2 className="w-4 h-4" />
      </button>
    </div>
  );
}

export default function WorkspaceDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [icalToken, setIcalToken] = useState(null);
  const [calendarCopied, setCalendarCopied] = useState(false);

  const { data: workspace, isLoading, error } = useWorkspace(id);
  const { data: invites = [] } = useWorkspaceInvites(id);
  const removeMemberMutation = useRemoveWorkspaceMember();
  const updateMemberMutation = useUpdateWorkspaceMember();
  const revokeInviteMutation = useRevokeWorkspaceInvite();

  // Fetch the user's iCal token on mount
  useEffect(() => {
    const fetchIcalToken = async () => {
      try {
        const response = await prmApi.getIcalUrl();
        setIcalToken(response.data.token);
      } catch {
        // Silently fail - calendar URL just won't be available
      }
    };
    fetchIcalToken();
  }, []);

  // Construct workspace calendar URL
  const workspaceCalendarUrl = icalToken && workspace
    ? `${window.location.origin}/workspace/${workspace.id}/calendar/${icalToken}.ics`
    : null;

  // Copy handler for calendar URL
  const handleCopyCalendarUrl = () => {
    if (workspaceCalendarUrl) {
      navigator.clipboard.writeText(workspaceCalendarUrl);
      setCalendarCopied(true);
      setTimeout(() => setCalendarCopied(false), 2000);
    }
  };

  const handleRemoveMember = async (userId) => {
    if (confirm('Remove this member from the workspace?')) {
      await removeMemberMutation.mutateAsync({ workspaceId: parseInt(id), userId });
    }
  };

  const handleChangeRole = async (userId, newRole) => {
    await updateMemberMutation.mutateAsync({ workspaceId: parseInt(id), userId, role: newRole });
  };

  const handleRevokeInvite = async (inviteId) => {
    await revokeInviteMutation.mutateAsync({ workspaceId: parseInt(id), inviteId });
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 dark:border-primary-400"></div>
      </div>
    );
  }

  if (error || !workspace) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600 dark:text-red-400">Failed to load workspace.</p>
        <Link to="/workspaces" className="text-primary-600 dark:text-primary-400 hover:underline mt-2 inline-block">
          Back to Workspaces
        </Link>
      </div>
    );
  }

  const currentUserRole = workspace.current_user?.is_owner ? 'owner' : workspace.current_user?.role;
  const canInvite = currentUserRole === 'admin' || currentUserRole === 'owner';
  const canAccessSettings = workspace.current_user?.is_owner;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <button
            onClick={() => navigate('/workspaces')}
            className="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 mb-2"
          >
            <ArrowLeft className="w-4 h-4" />
            Back to Workspaces
          </button>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-50">{workspace.title}</h1>
          {workspace.description && (
            <p className="text-gray-500 dark:text-gray-400 mt-1">{workspace.description}</p>
          )}
        </div>

        <div className="flex items-center gap-2">
          {canInvite && (
            <button
              onClick={() => setShowInviteModal(true)}
              className="btn-primary"
            >
              <UserPlus className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Invite</span>
            </button>
          )}
          {canAccessSettings && (
            <Link
              to={`/workspaces/${id}/settings`}
              className="btn-secondary"
            >
              <Settings className="w-4 h-4 md:mr-2" />
              <span className="hidden md:inline">Settings</span>
            </Link>
          )}
        </div>
      </div>

      {/* Members */}
      <div className="card">
        <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-medium text-gray-900 dark:text-gray-50">
            Members ({workspace.member_count})
          </h2>
        </div>
        <div className="divide-y divide-gray-100 dark:divide-gray-700">
          {workspace.members?.map((member) => (
            <MemberRow
              key={member.user_id}
              member={member}
              currentUserRole={currentUserRole}
              onRemove={handleRemoveMember}
              onChangeRole={handleChangeRole}
            />
          ))}
        </div>
      </div>

      {/* Pending Invites */}
      {canInvite && invites.length > 0 && (
        <div className="card">
          <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 className="text-lg font-medium text-gray-900 dark:text-gray-50">
              Pending Invites ({invites.length})
            </h2>
          </div>
          <div className="divide-y divide-gray-100 dark:divide-gray-700">
            {invites.map((invite) => (
              <InviteRow
                key={invite.id}
                invite={invite}
                onRevoke={handleRevokeInvite}
              />
            ))}
          </div>
        </div>
      )}

      {/* Calendar Subscription */}
      <div className="card p-4">
        <h3 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2 flex items-center gap-2">
          <Calendar className="w-4 h-4" />
          Calendar Subscription
        </h3>
        <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
          Subscribe to important dates for all contacts in this workspace.
        </p>
        {icalToken ? (
          <div className="flex items-center gap-2">
            <input
              type="text"
              value={workspaceCalendarUrl || ''}
              readOnly
              className="flex-1 text-xs px-2 py-1.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded font-mono dark:text-gray-200"
              onClick={(e) => e.target.select()}
            />
            <button
              onClick={handleCopyCalendarUrl}
              className="px-3 py-1.5 text-xs bg-primary-600 text-white rounded hover:bg-primary-700 flex items-center gap-1"
            >
              {calendarCopied ? <Check className="w-3 h-3" /> : <Copy className="w-3 h-3" />}
              {calendarCopied ? 'Copied!' : 'Copy'}
            </button>
          </div>
        ) : (
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Enable iCal feed in Settings to subscribe to workspace calendars.
          </p>
        )}
      </div>

      {/* Invite Modal */}
      <WorkspaceInviteModal
        isOpen={showInviteModal}
        onClose={() => setShowInviteModal(false)}
        workspaceId={parseInt(id)}
        workspaceName={workspace.title}
      />
    </div>
  );
}
