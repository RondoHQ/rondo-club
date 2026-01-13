import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Trash2, AlertTriangle } from 'lucide-react';
import { useWorkspace, useUpdateWorkspace, useDeleteWorkspace } from '@/hooks/useWorkspaces';

export default function WorkspaceSettings() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deleteConfirmText, setDeleteConfirmText] = useState('');

  const { data: workspace, isLoading, error } = useWorkspace(id);

  const updateMutation = useUpdateWorkspace();
  const deleteMutation = useDeleteWorkspace();

  const { register, handleSubmit, reset, formState: { errors, isDirty } } = useForm();

  // Populate form when workspace loads
  useEffect(() => {
    if (workspace) {
      reset({
        title: workspace.title,
        description: workspace.description || '',
      });
    }
  }, [workspace, reset]);

  const onSubmit = async (data) => {
    await updateMutation.mutateAsync({
      id: parseInt(id),
      data: {
        title: data.title,
        content: data.description,
      },
    });
  };

  const handleDelete = async () => {
    if (deleteConfirmText !== workspace.title) return;

    await deleteMutation.mutateAsync(parseInt(id));
    navigate('/workspaces');
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (error || !workspace) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load workspace.</p>
        <Link to="/workspaces" className="text-primary-600 hover:underline mt-2 inline-block">
          Back to Workspaces
        </Link>
      </div>
    );
  }

  const isOwner = workspace.current_user?.is_owner;

  // Non-owners can't access settings (should be caught by route, but just in case)
  if (!isOwner) {
    return (
      <div className="card p-6 text-center">
        <p className="text-gray-600">Only workspace owners can access settings.</p>
        <Link to={`/workspaces/${id}`} className="text-primary-600 hover:underline mt-2 inline-block">
          Back to Workspace
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <button
          onClick={() => navigate(`/workspaces/${id}`)}
          className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-2"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Workspace
        </button>
        <h1 className="text-2xl font-semibold text-gray-900">Workspace Settings</h1>
        <p className="text-gray-500 mt-1">{workspace.title}</p>
      </div>

      {/* General Settings */}
      <div className="card">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-900">General</h2>
        </div>
        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Workspace Name *
            </label>
            <input
              type="text"
              {...register('title', { required: 'Name is required' })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            {errors.title && (
              <p className="mt-1 text-sm text-red-600">{errors.title.message}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              {...register('description')}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>

          <div className="flex justify-end">
            <button
              type="submit"
              disabled={!isDirty || updateMutation.isPending}
              className="btn-primary disabled:opacity-50"
            >
              {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
            </button>
          </div>
        </form>
      </div>

      {/* Danger Zone */}
      <div className="card border-red-200">
        <div className="px-6 py-4 border-b border-red-200 bg-red-50">
          <h2 className="text-lg font-medium text-red-900 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" />
            Danger Zone
          </h2>
        </div>
        <div className="p-6">
          <div className="flex items-start gap-4">
            <Trash2 className="w-6 h-6 text-red-600 flex-shrink-0" />
            <div className="flex-1">
              <h3 className="text-base font-medium text-gray-900">Delete Workspace</h3>
              <p className="text-sm text-gray-600 mt-1">
                This will permanently delete the workspace and remove all member access.
                Contacts will remain but will no longer be shared via this workspace.
              </p>

              {!showDeleteConfirm ? (
                <button
                  onClick={() => setShowDeleteConfirm(true)}
                  className="mt-4 px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50"
                >
                  Delete Workspace
                </button>
              ) : (
                <div className="mt-4 p-4 bg-red-50 rounded-lg">
                  <p className="text-sm text-red-800 mb-3">
                    Type <strong>{workspace.title}</strong> to confirm deletion:
                  </p>
                  <input
                    type="text"
                    value={deleteConfirmText}
                    onChange={(e) => setDeleteConfirmText(e.target.value)}
                    className="w-full px-3 py-2 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 mb-3"
                    placeholder="Type workspace name..."
                  />
                  <div className="flex gap-2">
                    <button
                      onClick={() => { setShowDeleteConfirm(false); setDeleteConfirmText(''); }}
                      className="btn-secondary"
                    >
                      Cancel
                    </button>
                    <button
                      onClick={handleDelete}
                      disabled={deleteConfirmText !== workspace.title || deleteMutation.isPending}
                      className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {deleteMutation.isPending ? 'Deleting...' : 'Delete Forever'}
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
