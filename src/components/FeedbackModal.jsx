import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { X, Upload, Trash2 } from 'lucide-react';
import { wpApi } from '@/api/client';

export default function FeedbackModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
}) {
  const { register, handleSubmit, watch, reset, formState: { errors } } = useForm({
    defaultValues: {
      title: '',
      content: '',
      feedback_type: 'bug',
      steps_to_reproduce: '',
      expected_behavior: '',
      actual_behavior: '',
      use_case: '',
      include_system_info: false,
    },
  });

  const feedbackType = watch('feedback_type');

  // Attachment state
  const [attachments, setAttachments] = useState([]);
  const [isUploading, setIsUploading] = useState(false);
  const [dragActive, setDragActive] = useState(false);

  // Reset form on open
  useEffect(() => {
    if (isOpen) {
      reset({
        title: '',
        content: '',
        feedback_type: 'bug',
        steps_to_reproduce: '',
        expected_behavior: '',
        actual_behavior: '',
        use_case: '',
        include_system_info: false,
      });
      setAttachments([]);
    }
  }, [isOpen, reset]);

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

    // Capture system info if opted in
    if (data.include_system_info) {
      submitData.browser_info = navigator.userAgent;
      submitData.app_version = window.stadionConfig?.version || 'unknown';
      submitData.url_context = window.location.href;
    }

    onSubmit(submitData);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Submit feedback</h2>
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
            {/* Title */}
            <div>
              <label className="label">Title *</label>
              <input
                {...register('title', { required: 'Title is required' })}
                className="input"
                placeholder="Brief summary of your feedback"
                disabled={isLoading}
                autoFocus
              />
              {errors.title && (
                <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.title.message}</p>
              )}
            </div>

            {/* Type */}
            <div>
              <label className="label">Type *</label>
              <div className="flex gap-4">
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('feedback_type')}
                    type="radio"
                    value="bug"
                    className="w-4 h-4 text-accent-600 border-gray-300 dark:border-gray-600 focus:ring-accent-500 dark:bg-gray-700"
                    disabled={isLoading}
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Bug Report</span>
                </label>
                <label className="flex items-center cursor-pointer">
                  <input
                    {...register('feedback_type')}
                    type="radio"
                    value="feature_request"
                    className="w-4 h-4 text-accent-600 border-gray-300 dark:border-gray-600 focus:ring-accent-500 dark:bg-gray-700"
                    disabled={isLoading}
                  />
                  <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Feature Request</span>
                </label>
              </div>
            </div>

            {/* Description */}
            <div>
              <label className="label">Description *</label>
              <textarea
                {...register('content', { required: 'Description is required' })}
                className="input"
                rows={3}
                placeholder="Describe your feedback in detail"
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
                  <label className="label">Steps to reproduce</label>
                  <textarea
                    {...register('steps_to_reproduce')}
                    className="input"
                    rows={3}
                    placeholder="1. Go to...&#10;2. Click on...&#10;3. See error"
                    disabled={isLoading}
                  />
                </div>
                <div>
                  <label className="label">Expected behavior</label>
                  <textarea
                    {...register('expected_behavior')}
                    className="input"
                    rows={2}
                    placeholder="What should have happened"
                    disabled={isLoading}
                  />
                </div>
                <div>
                  <label className="label">Actual behavior</label>
                  <textarea
                    {...register('actual_behavior')}
                    className="input"
                    rows={2}
                    placeholder="What actually happened"
                    disabled={isLoading}
                  />
                </div>
              </>
            )}

            {/* Feature request field */}
            {feedbackType === 'feature_request' && (
              <div>
                <label className="label">Use case</label>
                <textarea
                  {...register('use_case')}
                  className="input"
                  rows={3}
                  placeholder="Describe how you would use this feature"
                  disabled={isLoading}
                />
              </div>
            )}

            {/* Attachments */}
            <div>
              <label className="label">Attachments</label>
              <div
                className={`relative rounded-lg border-2 border-dashed p-4 text-center transition-colors ${
                  dragActive
                    ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
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
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-accent-600 dark:border-accent-400"></div>
                    <span className="text-sm text-gray-600 dark:text-gray-300">Uploading...</span>
                  </div>
                ) : (
                  <>
                    <Upload className="w-6 h-6 mx-auto text-gray-400 mb-2" />
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                      Drop screenshots or <span className="text-accent-600 dark:text-accent-400">browse</span>
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
                        src={attachment.thumbnail}
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

            {/* System info checkbox */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="include_system_info"
                {...register('include_system_info')}
                className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500 dark:bg-gray-700"
                disabled={isLoading}
              />
              <label htmlFor="include_system_info" className="ml-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Include system information (browser, app version, current URL)
              </label>
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
              Cancel
            </button>
            <button
              type="submit"
              className="btn-primary"
              disabled={isLoading || isUploading}
            >
              {isLoading ? 'Submitting...' : 'Submit feedback'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
