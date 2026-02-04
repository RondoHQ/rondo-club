import { Link } from 'react-router-dom';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ArrowLeft, Info } from 'lucide-react';

export default function UserApproval() {
  useDocumentTitle('Gebruikersbeheer - Instellingen');

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-4">
        <Link
          to="/settings"
          className="btn-secondary flex items-center gap-2"
        >
          <ArrowLeft className="w-4 h-4" />
          <span className="hidden md:inline">Terug naar Instellingen</span>
        </Link>
        <h1 className="text-2xl font-semibold dark:text-gray-50">Gebruikersbeheer</h1>
      </div>

      <div className="card p-8">
        <div className="flex items-start gap-4">
          <div className="p-3 bg-blue-100 rounded-full dark:bg-blue-900">
            <Info className="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <h2 className="text-lg font-semibold dark:text-gray-50 mb-2">
              Gebruikersgoedkeuring is uitgeschakeld
            </h2>
            <p className="text-gray-600 dark:text-gray-300 mb-4">
              Registratie op deze site is uitgeschakeld. Nieuwe gebruikers worden handmatig aangemaakt door een beheerder in WordPress.
            </p>
            <p className="text-gray-600 dark:text-gray-300">
              Om gebruikers te beheren, ga naar het{' '}
              <a
                href="/wp-admin/users.php"
                className="text-accent-600 dark:text-accent-400 hover:underline"
              >
                WordPress gebruikersbeheer
              </a>.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
