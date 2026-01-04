import { useState, useCallback } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Upload, CheckCircle, AlertCircle, Loader2, FileSpreadsheet, Users, Building2, Cake, StickyNote } from 'lucide-react';
import api from '@/api/client';

export default function GoogleContactsImport() {
  const [file, setFile] = useState(null);
  const [dragActive, setDragActive] = useState(false);
  const [validationResult, setValidationResult] = useState(null);
  const queryClient = useQueryClient();

  const validateMutation = useMutation({
    mutationFn: async (file) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await api.post('/prm/v1/import/google-contacts/validate', formData, {
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
        error: error.response?.data?.message || 'Failed to validate file',
      });
    },
  });

  const importMutation = useMutation({
    mutationFn: async (file) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await api.post('/prm/v1/import/google-contacts', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return response.data;
    },
    onSuccess: () => {
      // Invalidate all relevant queries to refresh data
      queryClient.invalidateQueries({ queryKey: ['people'] });
      queryClient.invalidateQueries({ queryKey: ['companies'] });
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
    if (!selectedFile.name.endsWith('.csv')) {
      setValidationResult({
        valid: false,
        error: 'Please select a CSV file',
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
      <h2 className="text-lg font-semibold">Import from Google Contacts</h2>
      <p className="text-sm text-gray-600">
        Import contacts from a Google Contacts CSV export. To export from Google:
      </p>
      <ol className="text-sm text-gray-600 list-decimal list-inside space-y-1 ml-2">
        <li>Go to <a href="https://contacts.google.com" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:underline">contacts.google.com</a></li>
        <li>Click "Export" in the left sidebar</li>
        <li>Select "Google CSV" format and click "Export"</li>
      </ol>

      {importMutation.isSuccess ? (
        <div className="rounded-lg border border-green-200 bg-green-50 p-4">
          <div className="flex items-start gap-3">
            <CheckCircle className="h-5 w-5 text-green-600 mt-0.5" />
            <div className="flex-1">
              <h3 className="font-medium text-green-900">Import Complete</h3>
              <div className="mt-2 text-sm text-green-800 space-y-1">
                <p>Contacts imported: {importMutation.data.stats.contacts_imported}</p>
                <p>Contacts updated: {importMutation.data.stats.contacts_updated}</p>
                <p>Contacts skipped: {importMutation.data.stats.contacts_skipped}</p>
                <p>Companies created: {importMutation.data.stats.companies_created}</p>
                <p>Birthdays created: {importMutation.data.stats.dates_created}</p>
                <p>Notes created: {importMutation.data.stats.notes_created}</p>
                {importMutation.data.stats.errors?.length > 0 && (
                  <div className="mt-2 pt-2 border-t border-green-200">
                    <p className="font-medium">Errors:</p>
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
                className="mt-3 text-sm text-green-700 hover:text-green-800 font-medium"
              >
                Import another file
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
                ? 'border-primary-500 bg-primary-50'
                : file
                ? 'border-green-300 bg-green-50'
                : 'border-gray-300 hover:border-gray-400'
            }`}
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
          >
            <input
              type="file"
              accept=".csv"
              onChange={handleFileInput}
              className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
            />

            {validateMutation.isPending ? (
              <div className="flex flex-col items-center gap-2">
                <Loader2 className="h-8 w-8 text-primary-600 animate-spin" />
                <p className="text-gray-600">Validating file...</p>
              </div>
            ) : file ? (
              <div className="flex flex-col items-center gap-2">
                <FileSpreadsheet className="h-8 w-8 text-green-600" />
                <p className="font-medium text-gray-900">{file.name}</p>
                <p className="text-sm text-gray-500">
                  {(file.size / 1024).toFixed(2)} KB
                </p>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2">
                <Upload className="h-8 w-8 text-gray-400" />
                <p className="text-gray-600">
                  Drag and drop your Google Contacts CSV here, or click to browse
                </p>
                <p className="text-sm text-gray-500">Supports Google CSV format</p>
              </div>
            )}
          </div>

          {/* Validation result */}
          {validationResult && (
            <div
              className={`rounded-lg border p-4 ${
                validationResult.valid
                  ? 'border-green-200 bg-green-50'
                  : 'border-red-200 bg-red-50'
              }`}
            >
              {validationResult.valid ? (
                <div className="flex items-start gap-3">
                  <CheckCircle className="h-5 w-5 text-green-600 mt-0.5" />
                  <div className="flex-1">
                    <h3 className="font-medium text-green-900">File validated successfully</h3>
                    <div className="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                      <div className="flex items-center gap-2 text-sm text-green-800">
                        <Users className="h-4 w-4" />
                        <span>{validationResult.summary.contacts} contacts</span>
                      </div>
                      {validationResult.summary.companies_count > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800">
                          <Building2 className="h-4 w-4" />
                          <span>{validationResult.summary.companies_count} companies</span>
                        </div>
                      )}
                      {validationResult.summary.birthdays > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800">
                          <Cake className="h-4 w-4" />
                          <span>{validationResult.summary.birthdays} birthdays</span>
                        </div>
                      )}
                      {validationResult.summary.notes > 0 && (
                        <div className="flex items-center gap-2 text-sm text-green-800">
                          <StickyNote className="h-4 w-4" />
                          <span>{validationResult.summary.notes} notes</span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex items-start gap-3">
                  <AlertCircle className="h-5 w-5 text-red-600 mt-0.5" />
                  <div>
                    <h3 className="font-medium text-red-900">Validation failed</h3>
                    <p className="text-sm text-red-700 mt-1">{validationResult.error}</p>
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
                    Importing...
                  </>
                ) : (
                  <>
                    <Upload className="h-4 w-4" />
                    Start Import
                  </>
                )}
              </button>
              <button
                onClick={reset}
                disabled={importMutation.isPending}
                className="btn-secondary"
              >
                Cancel
              </button>
            </div>
          )}

          {/* Import error */}
          {importMutation.isError && (
            <div className="rounded-lg border border-red-200 bg-red-50 p-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-red-600 mt-0.5" />
                <div>
                  <h3 className="font-medium text-red-900">Import failed</h3>
                  <p className="text-sm text-red-700 mt-1">
                    {importMutation.error.response?.data?.message || 'An error occurred during import'}
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

