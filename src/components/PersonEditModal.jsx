import { useEffect, useState, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { X, Upload, FileCode, AlertCircle } from 'lucide-react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { wpApi, prmApi } from '@/api/client';
import api from '@/api/client';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

export default function PersonEditModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  person = null, // Pass person data for editing
  prefillData = null // Pass prefillData for pre-filling from external context (e.g., meeting attendee)
}) {
  const isEditing = !!person;
  const isOnline = useOnlineStatus();
  
  // vCard import state
  const [dragActive, setDragActive] = useState(false);
  const [vcardFile, setVcardFile] = useState(null);
  const [vcardError, setVcardError] = useState(null);
  
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
      const response = await api.post('/stadion/v1/import/vcard/parse', formData, {
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
        });
      } else if (prefillData) {
        // Pre-fill mode - use provided data from external context (e.g., meeting attendee)
        reset({
          first_name: prefillData.first_name || '',
          last_name: prefillData.last_name || '',
          nickname: '',
          gender: '',
          pronouns: '',
          email: prefillData.email || '',
          phone: '',
          phone_type: 'mobile',
          birthday: '',
          how_we_met: '',
        });
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
        });
      }
    }
  }, [isOpen, person, prefillData, reset]);

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
      setVcardError('Selecteer een vCard-bestand (.vcf)');
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
        });
        
        // Show notice if multiple contacts in file
        if (data.contact_count > 1) {
          setVcardError(`Let op: bestand bevat ${data.contact_count} contacten. Alleen het eerste contact is geladen.`);
        }
      },
      onError: (error) => {
        setVcardError(error.response?.data?.message || 'vCard-bestand kon niet worden verwerkt');
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
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Lid bewerken' : 'Lid toevoegen'}</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
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
                      ? 'border-accent-500 bg-accent-50 dark:bg-accent-800'
                      : vcardFile && !vcardError
                      ? 'border-green-300 bg-green-50 dark:border-green-600 dark:bg-green-900/30'
                      : 'border-gray-300 hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500'
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
                      <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-accent-600 dark:border-accent-400"></div>
                      <span className="text-sm text-gray-600 dark:text-gray-300">vCard verwerken...</span>
                    </div>
                  ) : vcardFile && !vcardError ? (
                    <div className="flex items-center justify-center gap-2 py-1">
                      <FileCode className="h-4 w-4 text-green-600 dark:text-green-400" />
                      <span className="text-sm text-green-700 dark:text-green-400">Geladen uit {vcardFile.name}</span>
                      <button
                        type="button"
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          clearVcard();
                        }}
                        className="ml-1 p-0.5 hover:bg-green-100 dark:hover:bg-green-900/50 rounded"
                      >
                        <X className="h-3 w-3 text-green-600 dark:text-green-400" />
                      </button>
                    </div>
                  ) : (
                    <div className="flex items-center justify-center gap-2 py-1">
                      <Upload className="h-4 w-4 text-gray-400" />
                      <span className="text-sm text-gray-600 dark:text-gray-300">
                        Sleep een vCard of <span className="text-accent-600 dark:text-accent-400">blader</span>
                      </span>
                    </div>
                  )}
                </div>
                
                {vcardError && (
                  <div className="mt-2 flex items-start gap-2 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 rounded-lg p-2">
                    <AlertCircle className="h-3 w-3 mt-0.5 flex-shrink-0" />
                    <span>{vcardError}</span>
                  </div>
                )}
              </div>
            )}
            
            {/* Name fields */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Voornaam *</label>
                <input
                  {...register('first_name', { required: 'Voornaam is verplicht' })}
                  className="input"
                  placeholder="Jan"
                  disabled={isLoading}
                  autoFocus
                />
                {errors.first_name && (
                  <p className="text-sm text-red-600 dark:text-red-400 mt-1">{errors.first_name.message}</p>
                )}
              </div>

              <div>
                <label className="label">Achternaam</label>
                <input
                  {...register('last_name')}
                  className="input"
                  placeholder="Jansen"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Nickname */}
            <div>
              <label className="label">Bijnaam</label>
              <input
                {...register('nickname')}
                className="input"
                placeholder="Jansen"
                disabled={isLoading}
              />
            </div>

            {/* Gender and Pronouns */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Geslacht</label>
                <select
                  {...register('gender')}
                  className="input"
                  disabled={isLoading}
                >
                  <option value="">Selecteer...</option>
                  <option value="male">M (Man)</option>
                  <option value="female">V (Vrouw)</option>
                  <option value="non_binary">X (Non-binair)</option>
                  <option value="other">Anders</option>
                  <option value="prefer_not_to_say">Geen antwoord</option>
                </select>
              </div>
              <div>
                <label className="label">Voornaamwoorden</label>
                <input
                  {...register('pronouns')}
                  type="text"
                  className="input"
                  placeholder="bijv. die/hen"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* Email - only editable when creating */}
            {!isEditing && (
              <div>
                <label className="label">E-mail</label>
                <input
                  {...register('email')}
                  type="email"
                  className="input"
                  placeholder="jan@voorbeeld.nl"
                  disabled={isLoading}
                />
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Gravatar wordt automatisch opgehaald indien beschikbaar
                </p>
              </div>
            )}

            {/* Phone - only editable when creating */}
            {!isEditing && (
              <div>
                <label className="label">Telefoon</label>
                <div className="flex gap-2">
                  <select
                    {...register('phone_type')}
                    className="input w-24"
                    disabled={isLoading}
                  >
                    <option value="mobile">Mobiel</option>
                    <option value="phone">Telefoon</option>
                  </select>
                  <input
                    {...register('phone')}
                    type="tel"
                    className="input flex-1"
                    placeholder="+31 6 12345678"
                    disabled={isLoading}
                  />
                </div>
              </div>
            )}

            {/* Birthday - only when creating */}
            {!isEditing && (
              <div>
                <label className="label">Verjaardag</label>
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
              <label className="label">Hoe we elkaar kennen</label>
              <textarea
                {...register('how_we_met')}
                className="input"
                rows={3}
                placeholder="We ontmoetten elkaar bij..."
                disabled={isLoading}
              />
            </div>
          </div>
          
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
              className={`btn-primary ${!isOnline ? 'opacity-50 cursor-not-allowed' : ''}`}
              disabled={!isOnline || isLoading}
            >
              {isLoading ? 'Opslaan...' : (isEditing ? 'Wijzigingen opslaan' : 'Lid aanmaken')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
