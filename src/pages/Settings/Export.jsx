import { Link } from 'react-router-dom';
import { ArrowLeft, Download, FileCode, FileSpreadsheet } from 'lucide-react';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { prmApi } from '@/api/client';

export default function Export() {
  useDocumentTitle('Export Data - Settings');
  
  const handleExport = (format) => {
    // The API endpoints return files directly, so we use window.location
    if (format === 'vcard') {
      window.location.href = '/wp-json/prm/v1/export/vcard';
    } else if (format === 'google-csv') {
      window.location.href = '/wp-json/prm/v1/export/google-csv';
    }
  };
  
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
        <h1 className="text-2xl font-semibold">Export Data</h1>
      </div>
      
      <div className="card p-6">
        <p className="text-sm text-gray-600 mb-6">
          Export all your contacts in a format compatible with other contact management systems.
        </p>
        
        <div className="space-y-4">
          <button
            onClick={() => handleExport('vcard')}
            className="w-full p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4"
          >
            <div className="p-3 bg-primary-50 rounded-lg">
              <FileCode className="w-6 h-6 text-primary-600" />
            </div>
            <div className="flex-1">
              <p className="font-medium">Export as vCard (.vcf)</p>
              <p className="text-sm text-gray-500">
                Compatible with Apple Contacts, Outlook, Android, and most contact apps
              </p>
            </div>
            <Download className="w-5 h-5 text-gray-400" />
          </button>
          
          <button
            onClick={() => handleExport('google-csv')}
            className="w-full p-4 rounded-lg border border-gray-200 hover:bg-gray-50 text-left flex items-center gap-4"
          >
            <div className="p-3 bg-primary-50 rounded-lg">
              <FileSpreadsheet className="w-6 h-6 text-primary-600" />
            </div>
            <div className="flex-1">
              <p className="font-medium">Export as Google Contacts CSV</p>
              <p className="text-sm text-gray-500">
                Import directly into Google Contacts or other CSV-compatible systems
              </p>
            </div>
            <Download className="w-5 h-5 text-gray-400" />
          </button>
        </div>
      </div>
    </div>
  );
}

