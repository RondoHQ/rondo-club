import { useAuth } from '@/hooks/useAuth';
import MonicaImport from '@/components/import/MonicaImport';
import { APP_NAME } from '@/constants/app';

export default function Settings() {
  const { logoutUrl } = useAuth();
  const config = window.prmConfig || {};

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="card p-6">
        <MonicaImport />
      </div>

      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Account</h2>
        <div className="space-y-4">
          <div>
            <label className="label">User ID</label>
            <p className="text-gray-600">{config.userId || 'N/A'}</p>
          </div>
          <div>
            <a
              href={config.adminUrl + 'profile.php'}
              className="btn-secondary"
              target="_blank"
              rel="noopener noreferrer"
            >
              Edit Profile in WordPress
            </a>
          </div>
        </div>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Administration</h2>
        <div className="space-y-3">
          <a
            href={config.adminUrl}
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            target="_blank"
            rel="noopener noreferrer"
          >
            <p className="font-medium">WordPress Admin</p>
            <p className="text-sm text-gray-500">Manage all settings, users, and content</p>
          </a>
          <a
            href={config.adminUrl + 'edit.php?post_type=person'}
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            target="_blank"
            rel="noopener noreferrer"
          >
            <p className="font-medium">Manage People</p>
            <p className="text-sm text-gray-500">Edit people with all ACF fields</p>
          </a>
          <a
            href={config.adminUrl + 'edit.php?post_type=company'}
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            target="_blank"
            rel="noopener noreferrer"
          >
            <p className="font-medium">Manage Companies</p>
            <p className="text-sm text-gray-500">Edit companies with all ACF fields</p>
          </a>
          <a
            href={config.adminUrl + 'edit.php?post_type=important_date'}
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            target="_blank"
            rel="noopener noreferrer"
          >
            <p className="font-medium">Manage Important Dates</p>
            <p className="text-sm text-gray-500">Edit dates and link people</p>
          </a>
        </div>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Session</h2>
        <a href={logoutUrl} className="btn-danger">
          Log Out
        </a>
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">About</h2>
        <p className="text-sm text-gray-600">
          {APP_NAME} v1.0.0<br />
          Built with WordPress, React, and Tailwind CSS.
        </p>
      </div>
    </div>
  );
}
