import { Gavel } from 'lucide-react';

export default function DisciplineCasesList() {
  return (
    <div className="max-w-7xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Tuchtzaken</h1>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Beheer tuchtrechtelijke procedures en dossiers
        </p>
      </div>

      <div className="card p-12 text-center">
        <div className="flex justify-center mb-4">
          <div className="p-4 bg-gray-100 rounded-full dark:bg-gray-700">
            <Gavel className="w-12 h-12 text-gray-400 dark:text-gray-500" />
          </div>
        </div>
        <h2 className="text-xl font-semibold text-gray-900 mb-2 dark:text-gray-100">
          Binnenkort beschikbaar
        </h2>
        <p className="text-gray-600 dark:text-gray-400">
          De tuchtzaken functionaliteit wordt in Phase 134 toegevoegd.
        </p>
      </div>
    </div>
  );
}
