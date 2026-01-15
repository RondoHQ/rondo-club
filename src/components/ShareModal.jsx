import { useState, useEffect, useRef } from 'react';
import { X, Search, UserPlus, Trash2 } from 'lucide-react';
import { useShares, useAddShare, useRemoveShare, useUserSearch } from '@/hooks/useSharing';

export default function ShareModal({
  isOpen,
  onClose,
  postType,  // 'people' or 'companies'
  postId,
  postTitle, // For display purposes
}) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedPermission, setSelectedPermission] = useState('view');
  const searchInputRef = useRef(null);

  const { data: shares = [], isLoading: sharesLoading } = useShares(postType, postId);
  const { data: searchResults = [], isLoading: searchLoading } = useUserSearch(searchQuery);
  const addShareMutation = useAddShare();
  const removeShareMutation = useRemoveShare();

  // Focus search input when modal opens
  useEffect(() => {
    if (isOpen) {
      setSearchQuery('');
      setTimeout(() => searchInputRef.current?.focus(), 100);
    }
  }, [isOpen]);

  // Filter out users already shared with
  const availableUsers = searchResults.filter(
    user => !shares.some(share => share.user_id === user.id)
  );

  const handleAddShare = async (user) => {
    await addShareMutation.mutateAsync({
      postType,
      postId,
      userId: user.id,
      permission: selectedPermission,
    });
    setSearchQuery('');
  };

  const handleRemoveShare = async (userId) => {
    await removeShareMutation.mutateAsync({
      postType,
      postId,
      userId,
    });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Share</h2>
              <p className="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">{postTitle}</p>
            </div>
            <button
              onClick={onClose}
              className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Content */}
          <div className="px-6 py-4">
            {/* Search input */}
            <div className="relative mb-4">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                ref={searchInputRef}
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search users by name or email..."
                className="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-2 focus:ring-accent-500 focus:border-accent-500"
              />
            </div>

            {/* Permission selector */}
            <div className="flex items-center gap-2 mb-4">
              <span className="text-sm text-gray-600 dark:text-gray-400">Permission:</span>
              <select
                value={selectedPermission}
                onChange={(e) => setSelectedPermission(e.target.value)}
                className="text-sm border border-gray-300 dark:border-gray-600 rounded-md px-2 py-1 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-50 focus:ring-accent-500 focus:border-accent-500"
              >
                <option value="view">Can view</option>
                <option value="edit">Can edit</option>
              </select>
            </div>

            {/* Search results */}
            {searchQuery.length >= 2 && (
              <div className="mb-4">
                {searchLoading ? (
                  <div className="py-3 text-center text-sm text-gray-500 dark:text-gray-400">Searching...</div>
                ) : availableUsers.length === 0 ? (
                  <div className="py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                    {searchResults.length === 0 ? 'No users found' : 'All matching users already have access'}
                  </div>
                ) : (
                  <div className="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                    {availableUsers.map((user) => (
                      <div key={user.id} className="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <img
                          src={user.avatar_url}
                          alt={user.display_name}
                          className="w-8 h-8 rounded-full"
                        />
                        <div className="flex-1 min-w-0">
                          <div className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">
                            {user.display_name}
                          </div>
                          <div className="text-xs text-gray-500 dark:text-gray-400 truncate">{user.email}</div>
                        </div>
                        <button
                          onClick={() => handleAddShare(user)}
                          disabled={addShareMutation.isPending}
                          className="p-2 text-accent-600 dark:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded-full"
                        >
                          <UserPlus className="w-4 h-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Current shares */}
            <div>
              <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Shared with ({shares.length})
              </h3>
              {sharesLoading ? (
                <div className="py-3 text-center text-sm text-gray-500 dark:text-gray-400">Loading...</div>
              ) : shares.length === 0 ? (
                <div className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                  Not shared with anyone yet
                </div>
              ) : (
                <div className="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                  {shares.map((share) => (
                    <div key={share.user_id} className="flex items-center gap-3 p-3">
                      <img
                        src={share.avatar_url}
                        alt={share.display_name}
                        className="w-8 h-8 rounded-full"
                      />
                      <div className="flex-1 min-w-0">
                        <div className="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">
                          {share.display_name}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                          Can {share.permission}
                        </div>
                      </div>
                      <button
                        onClick={() => handleRemoveShare(share.user_id)}
                        disabled={removeShareMutation.isPending}
                        className="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-full"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Footer */}
          <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button
              onClick={onClose}
              className="btn-secondary"
            >
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
