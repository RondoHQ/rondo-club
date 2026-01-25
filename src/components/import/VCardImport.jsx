import { useState, useCallback } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Upload, CheckCircle, AlertCircle, Loader2, FileCode, Users, Building2, Cake, StickyNote, Image } from 'lucide-react';
import api from '@/api/client';

export default function VCardImport() {
  const [file, setFile] = useState(null);
  const [dragActive, setDragActive] = useState(false);
  const [validationResult, setValidationResult] = useState(null);
  const queryClient = useQueryClient();

  const validateMutation = useMutation({
    mutationFn: async (file) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await api.post('/stadion/v1/import/vcard/validate', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return response.data;
    },
    onSuccess: (data) => {
      setValidationResult(data);
    },
    onError: (error) => {
      setValidationResult({
        valid: false,
        error: error.response?.data?.message || 'Bestand valideren mislukt',
      });
    },
  });

  const importMutation = useMutation({
    mutationFn: async (file) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await api.post('/stadion/v1/import/vcard', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return response.data;
    },
    onSuccess: () => {
      // Invalidate all relevant queries to refresh data
      queryClient.invalidateQueries({ queryKey: ['people'] });
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      queryClient.invalidateQueries({ queryKey: ['dates'] });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });

  const handleDrag = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  }, []);

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFile(e.dataTransfer.files[0]);
    }
  }, []);

  const handleFileInput = (e) => {
    if (e.target.files && e.target.files[0]) {
      handleFile(e.target.files[0]);
    }
  };

  const handleFile = (selectedFile) => {
    const ext = selectedFile.name.split('.').pop().toLowerCase();
    if (ext !== 'vcf' && ext !== 'vcard') {
      setValidationResult({
        valid: false,
        error: 'Selecteer een vCard-bestand (.vcf)',
      });
      return;
    }

    setFile(selectedFile);
    setValidationResult(null);
    importMutation.reset();
    validateMutation.mutate(selectedFile);
  };

  const handleImport = () => {
    if (file && validationResult?.valid) {
      importMutation.mutate(file);
    }
  };

  const reset = () => {
    setFile(null);
    setValidationResult(null);
    validateMutation.reset();
    importMutation.reset();
  };

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold dark:text-gray-50">Importeren van vCard</h2>
      <p className="text-sm text-gray-600 dark:text-gray-300">
        Importeer contacten vanuit een vCard (.vcf) bestand. vCard is een standaard formaat dat wordt ondersteund door de meeste contact-apps, waaronder Apple Contacten, Outlook en Android.
        Bestanden kunnen één of meerdere contacten bevatten.
      </p>

      {importMutation.isSuccess ? (
        <div className="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 p-4">
          <div className="flex items-start gap-3">
            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5" />
            <div className="flex-1">
              <h3 className="font-medium text-green-900 dark:text-green-300">Import voltooid</h3>
              <div className="mt-2 text-sm text-green-800 dark:text-green-200 space-y-1">
                <p>Contacten geïmporteerd: {importMutation.data.stats.contacts_imported}</p>
                <p>Contacten bijgewerkt: {importMutation.data.stats.contacts_updated}</p>
                <p>Contacten overgeslagen: {importMutation.data.stats.contacts_skipped}</p>
                <p>Foto's geïmporteerd: {importMutation.data.stats.photos_imported}</p>
                <p>Organisaties aangemaakt: {importMutation.data.stats.teams_created}</p>
                <p>Verjaardagen aangemaakt: {importMutation.data.stats.dates_created}</p>
                <p>Notities aangemaakt: {importMutation.data.stats.notes_created}</p>
                {importMutation.data.stats.errors?.length > 0 && (
                  <div className="mt-2 pt-2 border-t border-green-200 dark:border-green-800">
                    <p className="font-medium">Fouten:</p>
                    <ul className="list-disc list-inside">
                      {importMutation.data.stats.errors.map((error, i) => (
                        <li key={i}>{error}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
              <button
                onClick={reset}
                className="mt-3 text-sm text-green-700 dark:text-green-300 hover:text-green-800 dark:hover:text-green-200 font-medium"
              >
                Een ander bestand importeren
              </button>
            </div>
          </div>
        </div>
      ) : (
        <>
          {/* File drop zone */}
          <div
            className={`relative rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
              dragActive
                ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                : file
                ? 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/30'
                : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
            }`}
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
          >
            <input
              type="file"
              accept=".vcf,.vcard"
              onChange={handleFileInput}
              className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
            />

            {validateMutation.isPending ? (
              <div className="flex flex-col items-center gap-2">
                <Loader2 className="h-8 w-8 text-accent-600 dark:text-accent-400 animate-spin" />
                <p className="text-gray-600 dark:text-gray-300">Bestand valideren...</p>
              </div>
            ) : file ? (
              <div className="flex flex-col items-center gap-2">
                <FileCode className="h-8 w-8 text-green-600 dark:text-green-400" />
                <p className="font-medium text-gray-900 dark:text-gray-50">{file.name}</p>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {(file.size / 1024).toFixed(2)} KB
                </p>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2">
                <Upload className="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p className="text-gray-600 dark:text-gray-300">
                  Sleep je vCard-bestand hierheen of klik om te bladeren
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400">Ondersteunt .vcf en .vcard bestanden</p>
              </div>
            )}
          </div>

          {/* Validation result */}
          {validationResult && (
            <div
              className={`rounded-lg border p-4 ${
                validationResult.valid
                  ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30'
                  : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/30'
              }`}
            >
              {validationResult.valid ? (
                <div className="flex items-start gap-3">
                  <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5" />
                  <div className="flex-1">
                    <h3 className="font-medium text-green-900 dark:text-green-300">Bestand succesvol gevalideerd</h3>
                    <div className="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                      <div className="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                        <Users className="h-4 w-4" />
                        <span>{validationResult.summary.contacts} contacten</span>
                      </div>
                      {validationResult.summary.teams_count > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                          <Building2 className="h-4 w-4" />
                          <span>{validationResult.summary.teams_count} organisaties</span>
                        </div>
                      )}
                      {validationResult.summary.birthdays > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                          <Cake className="h-4 w-4" />
                          <span>{validationResult.summary.birthdays} verjaardagen</span>
                        </div>
                      )}
                      {validationResult.summary.photos > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                          <Image className="h-4 w-4" />
                          <span>{validationResult.summary.photos} foto's</span>
                        </div>
                      )}
                      {validationResult.summary.notes > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                          <StickyNote className="h-4 w-4" />
                          <span>{validationResult.summary.notes} notities</span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex items-start gap-3">
                  <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
                  <div>
                    <h3 className="font-medium text-red-900 dark:text-red-300">Validatie mislukt</h3>
                    <p className="text-sm text-red-700 dark:text-red-200 mt-1">{validationResult.error}</p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Import button */}
          {validationResult?.valid && (
            <div className="flex gap-3">
              <button
                onClick={handleImport}
                disabled={importMutation.isPending}
                className="btn-primary flex items-center gap-2"
              >
                {importMutation.isPending ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span className="hidden md:inline">Importeren...</span>
                  </>
                ) : (
                  <>
                    <Upload className="h-4 w-4" />
                    <span className="hidden md:inline">Start import</span>
                  </>
                )}
              </button>
              <button
                onClick={reset}
                disabled={importMutation.isPending}
                className="btn-secondary"
              >
                Annuleren
              </button>
            </div>
          )}

          {/* Import error */}
          {importMutation.isError && (
            <div className="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/30 p-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
                <div>
                  <h3 className="font-medium text-red-900 dark:text-red-300">Import mislukt</h3>
                  <p className="text-sm text-red-700 dark:text-red-200 mt-1">
                    {importMutation.error.response?.data?.message || 'Er is een fout opgetreden tijdens het importeren'}
                  </p>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

