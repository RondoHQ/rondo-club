import { useEffect, useState, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { X, Upload, FileCode, AlertCircle } from 'lucide-react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import api from '@/api/client';
import VisibilitySelector from '@/components/VisibilitySelector';

export default function PersonEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading,
  person = null // Pass person data for editing
}) {
  const isEditing = !!person;
  
  // vCard import state
  const [dragActive, setDragActive] = useState(false);
  const [vcardFile, setVcardFile] = useState(null);
  const [vcardError, setVcardError] = useState(null);

  // Visibility state
  const [visibility, setVisibility] = useState('private');
  const [selectedWorkspaces, setSelectedWorkspaces] = useState([]);
  
  // Fetch date types to get birthday term ID
  const { data: dateTypes = [] } = useQuery({
    queryKey: ['date-types'],
    queryFn: async () => {
      const response = await wpApi.getDateTypes();
      return response.data;
    },
    enabled: isOpen && !isEditing, // Only fetch when creating
  });
  
  const birthdayType = dateTypes.find(type => type.slug === 'birthday' || type.name.toLowerCase() === 'birthday');
  
  // Parse vCard mutation
  const parseVcardMutation = useMutation({
    mutationFn: async (file) => {
      const formData = new FormData();
      formData.append('file', file);
      const response = await api.post('/prm/v1/import/vcard/parse', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return response.data;
    },
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm({
    defaultValues: {
      first_name: '',
      last_name: '',
      nickname: '',
      gender: '',
      pronouns: '',
      email: '',
      phone: '',
      phone_type: 'mobile',
      birthday: '',
      how_we_met: '',
      is_favorite: false,
    },
  });

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      // Clear vCard state
      setVcardFile(null);
      setVcardError(null);
      parseVcardMutation.reset();

      if (person) {
        // Editing - populate with existing data
        const emailContact = person.acf?.contact_info?.find(contact => contact.contact_type === 'email');
        const phoneContact = person.acf?.contact_info?.find(contact =>
          contact.contact_type === 'phone' || contact.contact_type === 'mobile'
        );

        reset({
          first_name: person.acf?.first_name || '',
          last_name: person.acf?.last_name || '',
          nickname: person.acf?.nickname || '',
          gender: person.acf?.gender || '',
          pronouns: person.acf?.pronouns || '',
          email: emailContact?.contact_value || '',
          phone: phoneContact?.contact_value || '',
          phone_type: phoneContact?.contact_type || 'mobile',
          birthday: '', // Birthday is stored separately
          how_we_met: person.acf?.how_we_met || '',
          is_favorite: person.acf?.is_favorite || false,
        });
        // Load existing visibility settings
        setVisibility(person.acf?._visibility || 'private');
        setSelectedWorkspaces(person.acf?._assigned_workspaces || []);
      } else {
        // Creating - reset to defaults
        reset({
          first_name: '',
          last_name: '',
          nickname: '',
          gender: '',
          pronouns: '',
          email: '',
          phone: '',
          phone_type: 'mobile',
          birthday: '',
          how_we_met: '',
          is_favorite: false,
        });
        // Reset visibility to private
        setVisibility('private');
        setSelectedWorkspaces([]);
      }
    }
  }, [isOpen, person, reset]);

  // Handle vCard drag and drop
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
    setVcardError(null);
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleVcardFile(e.dataTransfer.files[0]);
    }
  }, []);
  
  const handleVcardFile = (file) => {
    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'vcf' && ext !== 'vcard') {
      setVcardError('Please select a vCard file (.vcf)');
      return;
    }
    
    setVcardFile(file);
    parseVcardMutation.mutate(file, {
      onSuccess: (data) => {
        // Pre-fill the form with vCard data
        reset({
          first_name: data.first_name || '',
          last_name: data.last_name || '',
          nickname: data.nickname || '',
          gender: data.gender || '',
          pronouns: data.pronouns || '',
          email: data.email || '',
          phone: data.phone || '',
          phone_type: data.phone_type || 'mobile',
          birthday: data.birthday || '',
          how_we_met: data.note || '',
          is_favorite: false,
        });
        
        // Show notice if multiple contacts in file
        if (data.contact_count > 1) {
          setVcardError(`Note: File contains ${data.contact_count} contacts. Only the first contact was loaded.`);
        }
      },
      onError: (error) => {
        setVcardError(error.response?.data?.message || 'Failed to parse vCard file');
        setVcardFile(null);
      },
    });
  };
  
  const handleVcardInput = (e) => {
    setVcardError(null);
    if (e.target.files && e.target.files[0]) {
      handleVcardFile(e.target.files[0]);
    }
  };
  
  const clearVcard = () => {
    setVcardFile(null);
    setVcardError(null);
    parseVcardMutation.reset();
  };

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      ...data,
      birthdayType: birthdayType, // Pass birthday type for creating birthday date
      visibility,
      assigned_workspaces: selectedWorkspaces,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">{isEditing ? 'Edit person' : 'Add person'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit(handleFormSubmit)} className="flex flex-col flex-1 overflow-hidden">
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* vCard Import - only when creating */}
            {!isEditing && (
              <div>
                <div
                  className={`relative rounded-lg border-2 border-dashed p-3 text-center transition-colors ${
                    dragActive
                      ? 'border-primary-500 bg-primary-50'
                      : vcardFile && !vcardError
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
                    accept=".vcf,.vcard"
                    onChange={handleVcardInput}
                    className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    disabled={isLoading}
                  />
                  
                  {parseVcardMutation.isPending ? (
                    <div className="flex items-center justify-center gap-2 py-1">
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary-600"></div>
                      <span className="text-sm text-gray-600">Parsing vCard...</span>
                    </div>
                  ) : vcardFile && !vcardError ? (
                    <div className="flex items-center justify-center gap-2 py-1">
                      <FileCode className="h-4 w-4 text-green-600" />
                      <span className="text-sm text-green-700">Loaded from {vcardFile.name}</span>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          clearVcard();
                        }}
                        className="ml-1 p-0.5 hover:bg-green-100 rounded"
                      >
                        <X className="h-3 w-3 text-green-600" />
                      </button>
                    </div>
                  ) : (
                    <div className="flex items-center justify-center gap-2 py-1">
                      <Upload className="h-4 w-4 text-gray-400" />
                      <span className="text-sm text-gray-600">
                        Drop a vCard or <span className="text-primary-600">browse</span>
                      </span>
                    </div>
                  )}
                </div>
                
                {vcardError && (
                  <div className="mt-2 flex items-start gap-2 text-xs text-amber-700 bg-amber-50 rounded-lg p-2">
                    <AlertCircle className="h-3 w-3 mt-0.5 flex-shrink-0" />
                    <span>{vcardError}</span>
                  </div>
                )}
              </div>
            )}
            
            {/* Name fields */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">First name *</label>
                <input
                  {...register('first_name', { required: 'First name is required' })}
                  className="input"
                  placeholder="John"
                  disabled={isLoading}
                  autoFocus
                />
                {errors.first_name && (
                  <p className="text-sm text-red-600 mt-1">{errors.first_name.message}</p>
                )}
              </div>
              
              <div>
                <label className="label">Last name</label>
                <input
                  {...register('last_name')}
                  className="input"
                  placeholder="Doe"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Nickname */}
            <div>
              <label className="label">Nickname</label>
              <input
                {...register('nickname')}
                className="input"
                placeholder="Johnny"
                disabled={isLoading}
              />
            </div>

            {/* Gender and Pronouns */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Gender</label>
                <select
                  {...register('gender')}
                  className="input"
                  disabled={isLoading}
                >
                  <option value="">Select...</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="non_binary">Non-binary</option>
                  <option value="other">Other</option>
                  <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
              </div>
              <div>
                <label className="label">Pronouns</label>
                <input
                  {...register('pronouns')}
                  type="text"
                  className="input"
                  placeholder="e.g., they/them"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Email - only editable when creating */}
            {!isEditing && (
              <div>
                <label className="label">Email</label>
                <input
                  {...register('email')}
                  type="email"
                  className="input"
                  placeholder="john@example.com"
                  disabled={isLoading}
                />
                <p className="text-xs text-gray-500 mt-1">
                  Gravatar will be auto-fetched if available
                </p>
              </div>
            )}

            {/* Phone - only editable when creating */}
            {!isEditing && (
              <div>
                <label className="label">Phone</label>
                <div className="flex gap-2">
                  <select
                    {...register('phone_type')}
                    className="input w-24"
                    disabled={isLoading}
                  >
                    <option value="mobile">Mobile</option>
                    <option value="phone">Phone</option>
                  </select>
                  <input
                    {...register('phone')}
                    type="tel"
                    className="input flex-1"
                    placeholder="+1 234 567 890"
                    disabled={isLoading}
                  />
                </div>
              </div>
            )}

            {/* Birthday - only when creating */}
            {!isEditing && (
              <div>
                <label className="label">Birthday</label>
                <input
                  {...register('birthday')}
                  type="date"
                  className="input"
                  disabled={isLoading}
                />
              </div>
            )}

            {/* How we met */}
            <div>
              <label className="label">How we met</label>
              <textarea
                {...register('how_we_met')}
                className="input"
                rows={3}
                placeholder="We met at..."
                disabled={isLoading}
              />
            </div>

            {/* Visibility */}
            <VisibilitySelector
              value={visibility}
              workspaces={selectedWorkspaces}
              onChange={({ visibility: v, workspaces: w }) => {
                setVisibility(v);
                setSelectedWorkspaces(w);
              }}
              disabled={isLoading}
            />

            {/* Favorite */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is_favorite"
                {...register('is_favorite')}
                className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                disabled={isLoading}
              />
              <label htmlFor="is_favorite" className="ml-2 text-sm text-gray-700 cursor-pointer">
                Mark as favorite
              </label>
            </div>
          </div>
          
          <div className="flex justify-end gap-2 p-4 border-t bg-gray-50">
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
              disabled={isLoading}
            >
              {isLoading ? 'Saving...' : (isEditing ? 'Save changes' : 'Create person')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
