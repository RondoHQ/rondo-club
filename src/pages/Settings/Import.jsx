import { useState } from 'react';
import { FileCode, FileSpreadsheet, Database } from 'lucide-react';
import MonicaImport from '@/components/import/MonicaImport';
import VCardImport from '@/components/import/VCardImport';
import GoogleContactsImport from '@/components/import/GoogleContactsImport';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const importTypes = [
  {
    id: 'vcard',
    name: 'vCard',
    description: 'Apple Contacts, Outlook, Android',
    icon: FileCode,
    component: VCardImport,
  },
  {
    id: 'google',
    name: 'Google Contacts',
    description: 'CSV export from Google',
    icon: FileSpreadsheet,
    component: GoogleContactsImport,
  },
  {
    id: 'monica',
    name: 'Monica CRM',
    description: 'SQL export from Monica',
    icon: Database,
    component: MonicaImport,
  },
];

export default function Import() {
  useDocumentTitle('Import - Settings');
  const [activeTab, setActiveTab] = useState('vcard');

  const ActiveComponent = importTypes.find(t => t.id === activeTab)?.component;

  return (
    <div className="max-w-2xl mx-auto">
      <div className="card p-6">
        <h1 className="text-xl font-semibold mb-4">Import Contacts</h1>
        <p className="text-sm text-gray-600 mb-6">
          Import your contacts from various sources. Existing contacts with matching names will be updated instead of duplicated.
        </p>

        {/* Import type tabs */}
        <div className="border-b border-gray-200 mb-6">
          <nav className="-mb-px flex space-x-6" aria-label="Import types">
            {importTypes.map((type) => {
              const Icon = type.icon;
              const isActive = activeTab === type.id;
              return (
                <button
                  key={type.id}
                  onClick={() => setActiveTab(type.id)}
                  className={`group inline-flex items-center py-3 px-1 border-b-2 font-medium text-sm transition-colors ${
                    isActive
                      ? 'border-primary-500 text-primary-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <Icon
                    className={`mr-2 h-5 w-5 ${
                      isActive ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'
                    }`}
                  />
                  <div className="text-left">
                    <span className="block">{type.name}</span>
                    <span className={`block text-xs font-normal ${
                      isActive ? 'text-primary-400' : 'text-gray-400'
                    }`}>
                      {type.description}
                    </span>
                  </div>
                </button>
              );
            })}
          </nav>
        </div>

        {/* Active import component */}
        {ActiveComponent && <ActiveComponent />}
      </div>
    </div>
  );
}
