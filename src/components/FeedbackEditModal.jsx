import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { X, Upload, Trash2 } from 'lucide-react';
import { wpApi } from '@/api/client';

// Status options
const statusOptions = [
  { value: 'new', label: 'Nieuw' },
  { value: 'approved', label: 'Goedgekeurd' },
  { value: 'in_progress', label: 'In behandeling' },
  { value: 'in_review', label: 'In review' },
  { value: 'needs_info', label: 'Info nodig' },
  { value: 'resolved', label: 'Opgelost' },
  { value: 'declined', label: 'Afgewezen' },
];

// Priority options
const priorityOptions = [
  { value: 'low', label: 'Laag' },
  { value: 'medium', label: 'Gemiddeld' },
  { value: 'high', label: 'Hoog' },
  { value: 'critical', label: 'Kritiek' },
];

export default function FeedbackEditModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  feedback, // The feedback item to edit
}) {
  const { register, handleSubmit, watch, reset, formState: { errors } } = useForm();
  const feedbackType = watch('feedback_type');

  // Attachment state
  const [attachments, setAttachments] = useState([]);
  const [isUploading, setIsUploading] = useState(false);
  const [dragActive, setDragActive] = useState(false);

  // Reset form with feedback data when opening
  useEffect(() => {
    if (isOpen && feedback) {
      reset({
        title: feedback.title || '',
        content: feedback.content || '',
        feedback_type: feedback.meta?.feedback_type || feedback.feedback_type || 'bug',
        project: feedback.meta?.project || 'rondo-club',
        status: feedback.meta?.status || feedback.status || 'new',
        priority: feedback.meta?.priority || feedback.priority || 'medium',
        steps_to_reproduce: feedback.meta?.steps_to_reproduce || feedback.steps_to_reproduce || '',
        expected_behavior: feedback.meta?.expected_behavior || feedback.expected_behavior || '',
        actual_behavior: feedback.meta?.actual_behavior || feedback.actual_behavior || '',
        use_case: feedback.meta?.use_case || feedback.use_case || '',
      });
      setAttachments(feedback.attachments || []);
    }
  }, [isOpen, feedback, reset]);

  // File upload handler
  const handleFileUpload = async (files) => {
    setIsUploading(true);
    try {
      const newAttachments = [];
      for (const file of files) {
        const response = await wpApi.uploadMedia(file);
        newAttachments.push({
          id: response.data.id,
          url: response.data.source_url,
          thumbnail: response.data.media_details?.sizes?.thumbnail?.source_url || response.data.source_url,
          title: file.name,
        });
      }
      setAttachments(prev => [...prev, ...newAttachments]);
    } catch (error) {
      console.error('Upload failed:', error);
    } finally {
      setIsUploading(false);
    }
  };

  // Remove attachment
  const handleRemoveAttachment = (id) => {
    setAttachments(prev => prev.filter(a => a.id !== id));
  };

  // Drag and drop handlers
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

    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      handleFileUpload(Array.from(e.dataTransfer.files));
    }
  }, []);

  // Form submit handler
  const handleFormSubmit = (data) => {
    const submitData = {
      title: data.title,
      content: data.content,
      feedback_type: data.feedback_type,
      project: data.project,
      status: data.status,
      priority: data.priority,
      attachments: attachments.map(a => a.id),
    };

    // Add type-specific fields
    if (data.feedback_type === 'bug') {
      submitData.steps_to_reproduce = data.steps_to_reproduce;
      submitData.expected_behavior = data.expected_behavior;
      submitData.actual_behavior = data.actual_behavior;
    } else if (data.feedback_type === 'feature_request') {
      submitData.use_case = data.use_case;
    }

    onSubmit(submitData);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Feedback bewerken</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Type */}
            <div>
              <label className="label">Type *</label>
              <div className="flex gap-4">
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('feedback_type')}
                    type="radio"
                    value="bug"
                    className="w-4 h-4 text-electric-cyan border-gray-300 dark:border-gray-600 focus:ring-electric-cyan dark:bg-gray-700"
                    disabled={isLoading}
                    autoFocus
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Bugmelding</span>
                </label>
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('feedback_type')}
                    type="radio"
                    value="feature_request"
                    className="w-4 h-4 text-electric-cyan border-gray-300 dark:border-gray-600 focus:ring-electric-cyan dark:bg-gray-700"
                    disabled={isLoading}
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Functieverzoek</span>
                </label>
              </div>
            </div>

            {/* Project */}
            <div>
              <label className="label">Project *</label>
              <div className="flex gap-4">
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('project')}
                    type="radio"
                    value="rondo-club"
                    className="w-4 h-4 text-electric-cyan border-gray-300 dark:border-gray-600 focus:ring-electric-cyan dark:bg-gray-700"
                    disabled={isLoading}
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Rondo Club</span>
                </label>
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('project')}
                    type="radio"
                    value="rondo-sync"
                    className="w-4 h-4 text-electric-cyan border-gray-300 dark:border-gray-600 focus:ring-electric-cyan dark:bg-gray-700"
                    disabled={isLoading}
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Rondo Sync</span>
                </label>
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('project')}
                    type="radio"
                    value="website"
                    className="w-4 h-4 text-electric-cyan border-gray-300 dark:border-gray-600 focus:ring-electric-cyan dark:bg-gray-700"
                    disabled={isLoading}
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Website</span>
                </label>
              </div>
            </div>

            {/* Title */}
            <div>
              <label className="label">Titel *</label>
              <input
                {...register('title', { required: 'Titel is verplicht' })}
                className="input"
                placeholder="Korte samenvatting van je feedback"
                disabled={isLoading}
              />
              {errors.title && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.title.message}</p>
              )}
            </div>

            {/* Status and Priority row */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Status</label>
                <select
                  {...register('status')}
                  className="input"
                  disabled={isLoading}
                >
                  {statusOptions.map(opt => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="label">Prioriteit</label>
                <select
                  {...register('priority')}
                  className="input"
                  disabled={isLoading}
                >
                  {priorityOptions.map(opt => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Description */}
            <div>
              <label className="label">Beschrijving *</label>
              <textarea
                {...register('content', { required: 'Beschrijving is verplicht' })}
                className="input"
                rows={3}
                placeholder="Beschrijf je feedback in detail"
                disabled={isLoading}
              />
              {errors.content && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.content.message}</p>
              )}
            </div>

            {/* Bug-specific fields */}
            {feedbackType === 'bug' && (
              <>
                <div>
                  <label className="label">Stappen om te reproduceren</label>
                  <textarea
                    {...register('steps_to_reproduce')}
                    className="input"
                    rows={3}
                    placeholder="1. Ga naar...&#10;2. Klik op...&#10;3. Zie fout"
                    disabled={isLoading}
                  />
                </div>
                <div>
                  <label className="label">Verwacht gedrag</label>
                  <textarea
                    {...register('expected_behavior')}
                    className="input"
                    rows={2}
                    placeholder="Wat had moeten gebeuren"
                    disabled={isLoading}
                  />
                </div>
                <div>
                  <label className="label">Werkelijk gedrag</label>
                  <textarea
                    {...register('actual_behavior')}
                    className="input"
                    rows={2}
                    placeholder="Wat er werkelijk gebeurde"
                    disabled={isLoading}
                  />
                </div>
              </>
            )}

            {/* Feature request field */}
            {feedbackType === 'feature_request' && (
              <div>
                <label className="label">Gebruikssituatie</label>
                <textarea
                  {...register('use_case')}
                  className="input"
                  rows={3}
                  placeholder="Beschrijf hoe je deze functie zou gebruiken"
                  disabled={isLoading}
                />
              </div>
            )}

            {/* Attachments */}
            <div>
              <label className="label">Bijlagen</label>
              <div
                className={`relative rounded-lg border-2 border-dashed p-4 text-center transition-colors ${
                  dragActive
                    ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight'
                    : 'border-gray-300 hover:border-gray-400 dark:border-gray-600'
                }`}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
              >
                <input
                  type="file"
                  accept="image/*"
                  multiple
                  onChange={(e) => handleFileUpload(Array.from(e.target.files))}
                  className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                  disabled={isLoading || isUploading}
                />
                {isUploading ? (
                  <div className="flex items-center justify-center gap-2">
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-electric-cyan dark:border-electric-cyan"></div>
                    <span className="text-sm text-gray-600 dark:text-gray-300">Uploaden...</span>
                  </div>
                ) : (
                  <>
                    <Upload className="w-6 h-6 mx-auto text-gray-400 mb-2" />
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                      Sleep screenshots of <span className="text-electric-cyan dark:text-electric-cyan">blader</span>
                    </p>
                  </>
                )}
              </div>

              {/* Attachment previews */}
              {attachments.length > 0 && (
                <div className="flex flex-wrap gap-2 mt-3">
                  {attachments.map((attachment) => (
                    <div
                      key={attachment.id}
                      className="relative group"
                    >
                      <img
                        src={attachment.thumbnail || attachment.url}
                        alt={attachment.title}
                        className="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                      />
                      <button
                        type="button"
                        onClick={() => handleRemoveAttachment(attachment.id)}
                        className="absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                        disabled={isLoading}
                      >
                        <Trash2 className="w-3 h-3" />
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <button
              type="button"
              onClick={onClose}
              className="btn-secondary"
              disabled={isLoading}
            >
              Annuleren
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading || isUploading}
            >
              {isLoading ? 'Opslaan...' : 'Wijzigingen opslaan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
