import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { APP_NAME } from '@/constants/app';
import apiClient from '@/api/client';

export default function Settings() {
  const config = window.prmConfig || {};
  const isAdmin = config.isAdmin || false;
  
  const [icalUrl, setIcalUrl] = useState('');
  const [webcalUrl, setWebcalUrl] = useState('');
  const [icalLoading, setIcalLoading] = useState(true);
  const [icalCopied, setIcalCopied] = useState(false);
  const [regenerating, setRegenerating] = useState(false);
  
  // Fetch iCal URL on mount
  useEffect(() => {
    const fetchIcalUrl = async () => {
      try {
        const response = await apiClient.get('/prm/v1/user/ical-url');
        setIcalUrl(response.data.url);
        setWebcalUrl(response.data.webcal_url);
      } catch (error) {
        console.error('Failed to fetch iCal URL:', error);
      } finally {
        setIcalLoading(false);
      }
    };
    fetchIcalUrl();
  }, []);
  
  const copyIcalUrl = async () => {
    try {
      await navigator.clipboard.writeText(icalUrl);
      setIcalCopied(true);
      setTimeout(() => setIcalCopied(false), 2000);
    } catch (error) {
      console.error('Failed to copy:', error);
    }
  };
  
  const regenerateIcalToken = async () => {
    if (!confirm('Are you sure you want to regenerate your calendar URL? Any existing calendar subscriptions will stop working until you update them with the new URL.')) {
      return;
    }
    
    setRegenerating(true);
    try {
      const response = await apiClient.post('/prm/v1/user/regenerate-ical-token');
      setIcalUrl(response.data.url);
      setWebcalUrl(response.data.webcal_url);
    } catch (error) {
      console.error('Failed to regenerate token:', error);
    } finally {
      setRegenerating(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Calendar Subscription</h2>
        <p className="text-sm text-gray-600 mb-4">
          Subscribe to your important dates in any calendar app (Apple Calendar, Google Calendar, Outlook, etc.)
        </p>
        
        {icalLoading ? (
          <div className="animate-pulse">
            <div className="h-10 bg-gray-200 rounded mb-3"></div>
            <div className="h-9 bg-gray-200 rounded w-24"></div>
          </div>
        ) : (
          <div className="space-y-4">
            <div>
              <label className="label mb-1">Your Calendar Feed URL</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  readOnly
                  value={icalUrl}
                  className="input flex-1 text-sm font-mono bg-gray-50"
                  onClick={(e) => e.target.select()}
                />
                <button
                  onClick={copyIcalUrl}
                  className="btn-secondary whitespace-nowrap"
                  title="Copy URL"
                >
                  {icalCopied ? (
                    <span className="flex items-center gap-1">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                      </svg>
                      Copied
                    </span>
                  ) : (
                    <span className="flex items-center gap-1">
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                      </svg>
                      Copy
                    </span>
                  )}
                </button>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-2">
              <a
                href={webcalUrl}
                className="btn-primary"
              >
                <span className="flex items-center gap-1">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Subscribe in Calendar App
                </span>
              </a>
              
              <button
                onClick={regenerateIcalToken}
                disabled={regenerating}
                className="btn-secondary"
              >
                {regenerating ? 'Regenerating...' : 'Regenerate URL'}
              </button>
            </div>
            
            <p className="text-xs text-gray-500">
              Keep this URL private. Anyone with access to it can see your important dates.
              If you think it has been compromised, click "Regenerate URL" to get a new one.
            </p>
          </div>
        )}
      </div>
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">Data</h2>
        <div className="space-y-3">
          <Link
            to="/settings/import"
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
          >
            <p className="font-medium">Import Data</p>
            <p className="text-sm text-gray-500">Import contacts from Monica CRM or other sources</p>
          </Link>
          <Link
            to="/settings/export"
            className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
          >
            <p className="font-medium">Export Data</p>
            <p className="text-sm text-gray-500">Export all contacts as vCard or Google Contacts CSV</p>
          </Link>
        </div>
      </div>
      
      {isAdmin && (
        <div className="card p-6">
          <h2 className="text-lg font-semibold mb-4">Administration</h2>
          <div className="space-y-3">
            <Link
              to="/settings/relationship-types"
              className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            >
              <p className="font-medium">Relationship Types</p>
              <p className="text-sm text-gray-500">Manage relationship types and their inverse mappings</p>
            </Link>
            <Link
              to="/settings/user-approval"
              className="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
            >
              <p className="font-medium">User Approval</p>
              <p className="text-sm text-gray-500">Approve or deny access for new users</p>
            </Link>
          </div>
        </div>
      )}
      
      <div className="card p-6">
        <h2 className="text-lg font-semibold mb-4">About</h2>
        <p className="text-sm text-gray-600">
          {APP_NAME} v{config.version || '1.0.0'}<br />
          Built with WordPress, React, and Tailwind CSS.
        </p>
      </div>
    </div>
  );
}
