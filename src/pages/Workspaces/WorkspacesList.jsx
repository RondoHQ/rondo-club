import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Users, Crown, Shield, Eye } from 'lucide-react';
import { useWorkspaces } from '@/hooks/useWorkspaces';
import WorkspaceCreateModal from '@/components/WorkspaceCreateModal';

// Role badge component
function RoleBadge({ role, isOwner }) {
  if (isOwner) {
    return (
      <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-300 text-xs rounded-full">
        <Crown className="w-3 h-3" />
        Owner
      </span>
    );
  }

  const roleConfig = {
    admin: { icon: Shield, bg: 'bg-purple-100 dark:bg-purple-900/50', text: 'text-purple-800 dark:text-purple-300', label: 'Admin' },
    member: { icon: Users, bg: 'bg-blue-100 dark:bg-blue-900/50', text: 'text-blue-800 dark:text-blue-300', label: 'Member' },
    viewer: { icon: Eye, bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-800 dark:text-gray-300', label: 'Viewer' },
  };

  const config = roleConfig[role] || roleConfig.member;
  const Icon = config.icon;

  return (
    <span className={`inline-flex items-center gap-1 px-2 py-0.5 ${config.bg} ${config.text} text-xs rounded-full`}>
      <Icon className="w-3 h-3" />
      {config.label}
    </span>
  );
}

function WorkspaceCard({ workspace }) {
  return (
    <Link
      to={`/workspaces/${workspace.id}`}
      className="card p-4 hover:shadow-md transition-shadow"
    >
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <h3 className="text-base font-medium text-gray-900 dark:text-gray-50 truncate">
            {workspace.title}
          </h3>
          {workspace.description && (
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
              {workspace.description}
            </p>
          )}
          <div className="flex items-center gap-3 mt-3">
            <RoleBadge role={workspace.role} isOwner={workspace.is_owner} />
            <span className="text-sm text-gray-500 dark:text-gray-400">
              {workspace.member_count} member{workspace.member_count !== 1 ? 's' : ''}
            </span>
          </div>
        </div>
      </div>
    </Link>
  );
}

export default function WorkspacesList() {
  const [showCreateModal, setShowCreateModal] = useState(false);
  const { data: workspaces, isLoading, error } = useWorkspaces();

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-50">Workspaces</h1>
        <button
          onClick={() => setShowCreateModal(true)}
          className="btn-primary"
        >
          <Plus className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Create Workspace</span>
        </button>
      </div>

      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 dark:border-primary-400"></div>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="card p-6 text-center">
          <p className="text-red-600 dark:text-red-400">Failed to load workspaces.</p>
        </div>
      )}

      {/* Empty state */}
      {!isLoading && !error && workspaces?.length === 0 && (
        <div className="card p-12 text-center">
          <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
            <Users className="w-6 h-6 text-gray-400 dark:text-gray-500" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">No workspaces yet</h3>
          <p className="text-gray-500 dark:text-gray-400 mb-4">
            Create a workspace to start collaborating with others.
          </p>
          <button
            onClick={() => setShowCreateModal(true)}
            className="btn-primary"
          >
            <Plus className="w-4 h-4 mr-2" />
            Create Workspace
          </button>
        </div>
      )}

      {/* Workspaces grid */}
      {!isLoading && !error && workspaces?.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {workspaces.map((workspace) => (
            <WorkspaceCard key={workspace.id} workspace={workspace} />
          ))}
        </div>
      )}

      {/* Create Modal */}
      <WorkspaceCreateModal
        isOpen={showCreateModal}
        onClose={() => setShowCreateModal(false)}
      />
    </div>
  );
}
