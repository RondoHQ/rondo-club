import { useState, useCallback } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Upload, CheckCircle, AlertCircle, Loader2, FileCode, Link as LinkIcon } from 'lucide-react';
import api from '@/api/client';

export default function MonicaImport() {
  const [file, setFile] = useState(null);
  const [monicaUrl, setMonicaUrl] = useState('');
  const [dragActive, setDragActive] = useState(false);
  const [validationResult, setValidationResult] = useState(null);
  const queryClient = useQueryClient();

  const validateMutation = useMutation({
    mutationFn: async (file) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await api.post('/stadion/v1/import/monica/validate', formData, {
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
    mutationFn: async ({ file, monicaUrl }) => {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('monica_url', monicaUrl);
      const response = await api.post('/stadion/v1/import/monica', formData, {
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
    if (!selectedFile.name.endsWith('.sql')) {
      setValidationResult({
        valid: false,
        error: 'Please select a SQL file',
      });
      return;
    }

    setFile(selectedFile);
    setValidationResult(null);
    importMutation.reset();
    validateMutation.mutate(selectedFile);
  };

  const handleImport = () => {
    if (!monicaUrl.trim()) {
      setValidationResult(prev => ({
        ...prev,
        urlError: 'Please enter your Monica instance URL',
      }));
      return;
    }

    if (file && validationResult?.valid) {
      importMutation.mutate({ file, monicaUrl: monicaUrl.trim() });
    }
  };

  const reset = () => {
    setFile(null);
    setMonicaUrl('');
    setValidationResult(null);
    validateMutation.reset();
    importMutation.reset();
  };

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold dark:text-gray-50">Import from Monica</h2>
      <p className="text-sm text-gray-600 dark:text-gray-300">
        Import contacts, relationships, notes, and photos from a Monica CRM SQL export file.
        Reimporting the same file will update existing contacts instead of creating duplicates.
      </p>

      {importMutation.isSuccess ? (
        <div className="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/30 p-4">
          <div className="flex items-start gap-3">
            <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5" />
            <div className="flex-1">
              <h3 className="font-medium text-green-900 dark:text-green-300">Import Complete</h3>
              <div className="mt-2 text-sm text-green-800 dark:text-green-200 space-y-1">
                <p>Contacts imported: {importMutation.data.stats.contacts_imported}</p>
                <p>Contacts updated: {importMutation.data.stats.contacts_updated}</p>
                <p>Contacts skipped (partial): {importMutation.data.stats.contacts_skipped}</p>
                <p>Photos imported: {importMutation.data.stats.photos_imported}</p>
                <p>Organizations created: {importMutation.data.stats.companies_created}</p>
                <p>Relationships created: {importMutation.data.stats.relationships_created}</p>
                <p>Important dates created: {importMutation.data.stats.dates_created}</p>
                <p>Notes created: {importMutation.data.stats.notes_created}</p>
                {importMutation.data.stats.errors?.length > 0 && (
                  <div className="mt-2 pt-2 border-t border-green-200 dark:border-green-800">
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
                className="mt-3 text-sm text-green-700 dark:text-green-300 hover:text-green-800 dark:hover:text-green-200 font-medium"
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
              accept=".sql"
              onChange={handleFileInput}
              className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
            />

            {validateMutation.isPending ? (
              <div className="flex flex-col items-center gap-2">
                <Loader2 className="h-8 w-8 text-accent-600 dark:text-accent-400 animate-spin" />
                <p className="text-gray-600 dark:text-gray-300">Validating file...</p>
              </div>
            ) : file ? (
              <div className="flex flex-col items-center gap-2">
                <FileCode className="h-8 w-8 text-green-600 dark:text-green-400" />
                <p className="font-medium text-gray-900 dark:text-gray-50">{file.name}</p>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {(file.size / 1024 / 1024).toFixed(2)} MB
                </p>
              </div>
            ) : (
              <div className="flex flex-col items-center gap-2">
                <Upload className="h-8 w-8 text-gray-400 dark:text-gray-500" />
                <p className="text-gray-600 dark:text-gray-300">
                  Drag and drop your Monica SQL export file here, or click to browse
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400">Supports SQL export files</p>
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
                    <h3 className="font-medium text-green-900 dark:text-green-300">File validated successfully</h3>
                    <div className="mt-2 text-sm text-green-800 dark:text-green-200">
                      <p>Export format: {validationResult.version}</p>
                      <p>Contacts to import: {validationResult.summary.contacts}</p>
                      <p>Relationships: {validationResult.summary.relationships}</p>
                      <p>Notes: {validationResult.summary.notes}</p>
                      <p>Dates: {validationResult.summary.reminders}</p>
                      <p>Photos: {validationResult.summary.photos}</p>
                      <p>Organizations: {validationResult.summary.companies_count}</p>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex items-start gap-3">
                  <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
                  <div>
                    <h3 className="font-medium text-red-900 dark:text-red-300">Validation failed</h3>
                    <p className="text-sm text-red-700 dark:text-red-200 mt-1">{validationResult.error}</p>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Monica URL input */}
          {validationResult?.valid && (
            <div className="space-y-2">
              <label htmlFor="monica-url" className="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Monica Instance URL
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <LinkIcon className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                </div>
                <input
                  type="url"
                  id="monica-url"
                  value={monicaUrl}
                  onChange={(e) => setMonicaUrl(e.target.value)}
                  placeholder="https://your-monica-instance.com"
                  className={`block w-full pl-10 pr-3 py-2 border rounded-lg focus:ring-2 focus:ring-accent-500 focus:border-accent-500 dark:bg-gray-700 dark:text-gray-50 ${
                    validationResult.urlError ? 'border-red-300 dark:border-red-600' : 'border-gray-300 dark:border-gray-600'
                  }`}
                />
              </div>
              {validationResult.urlError && (
                <p className="text-sm text-red-600 dark:text-red-400">{validationResult.urlError}</p>
              )}
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Enter the URL of your Monica instance to import photos. Photos will be downloaded from this URL.
              </p>
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
                    <span className="hidden md:inline">Importing...</span>
                  </>
                ) : (
                  <>
                    <Upload className="h-4 w-4" />
                    <span className="hidden md:inline">Start Import</span>
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
            <div className="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/30 p-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" />
                <div>
                  <h3 className="font-medium text-red-900 dark:text-red-300">Import failed</h3>
                  <p className="text-sm text-red-700 dark:text-red-200 mt-1">
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
