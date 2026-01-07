import { useEffect, useState, useCallback } from 'react';
import { Link, useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save, Upload, FileCode, X, AlertCircle } from 'lucide-react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { usePerson, useCreatePerson, useUpdatePerson } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { wpApi, prmApi } from '@/api/client';
import api from '@/api/client';

export default function PersonForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const isEditing = !!id;
  
  // Get return navigation parameters
  const returnTo = searchParams.get('returnTo');
  const sourcePersonId = searchParams.get('sourcePersonId');
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(id);
  const createPerson = useCreatePerson();
  const updatePerson = useUpdatePerson();
  
  // Fetch date types to get birthday term ID
  const { data: dateTypes = [] } = useQuery({
    queryKey: ['date-types'],
    queryFn: async () => {
      const response = await wpApi.getDateTypes();
      return response.data;
    },
  });
  
  const birthdayType = dateTypes.find(type => type.slug === 'birthday' || type.name.toLowerCase() === 'birthday');
  
  // vCard import state
  const [dragActive, setDragActive] = useState(false);
  const [vcardFile, setVcardFile] = useState(null);
  const [vcardError, setVcardError] = useState(null);
  
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
  
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      first_name: '',
      last_name: '',
      nickname: '',
      gender: '',
      email: '',
      how_we_met: '',
      is_favorite: false,
      birthday: '',
    },
  });
  
  useEffect(() => {
    if (person) {
      // Get email from contact_info if it exists
      const emailContact = person.acf?.contact_info?.find(contact => contact.contact_type === 'email');
      const email = emailContact?.contact_value || '';
      
      reset({
        first_name: person.acf?.first_name || '',
        last_name: person.acf?.last_name || '',
        nickname: person.acf?.nickname || '',
        gender: person.acf?.gender || '',
        email: email,
        how_we_met: person.acf?.how_we_met || '',
        is_favorite: person.acf?.is_favorite || false,
        birthday: '', // Birthday is stored separately as an important_date
      });
    }
  }, [person, reset]);
  
  // Update document title - MUST be called before early returns
  // to ensure consistent hook calls on every render
  useDocumentTitle(
    isEditing && person
      ? `Edit ${person.title?.rendered || person.title || 'person'}`
      : 'New person'
  );
  
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
          gender: '',
          email: data.email || '',
          how_we_met: data.note || '',
          is_favorite: false,
          birthday: data.birthday || '',
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
  
  const onSubmit = async (data) => {
    try {
      // Generate title from first and last name
      const fullName = `${data.first_name || ''} ${data.last_name || ''}`.trim();
      const title = fullName || 'Unnamed Person';
      
      const payload = {
        title: title,
        status: 'publish',
        acf: {
          first_name: data.first_name,
          last_name: data.last_name,
          nickname: data.nickname,
          gender: data.gender || null, // null instead of '' for ACF enum validation
          how_we_met: data.how_we_met,
          is_favorite: data.is_favorite,
        },
      };
      
      // Add email to contact_info if provided (only when creating)
      if (!isEditing && data.email) {
        payload.acf.contact_info = [
          {
            contact_type: 'email',
            contact_value: data.email,
            contact_label: '',
          },
        ];
      }
      
      if (isEditing) {
        // Update title when editing too
        payload.title = title;
        await updatePerson.mutateAsync({ id, data: payload });
        navigate(`/people/${id}`);
      } else {
        const result = await createPerson.mutateAsync(payload);
        const personId = result.data.id;
        
        // Try to sideload Gravatar if email is provided
        if (data.email) {
          try {
            await prmApi.sideloadGravatar(personId, data.email);
          } catch (gravatarError) {
            console.error('Failed to load Gravatar:', gravatarError);
            // Continue anyway - person was created successfully, just no gravatar
          }
        }
        
        // Create birthday if provided
        if (data.birthday && birthdayType) {
          try {
            const firstName = data.first_name || 'Person';
            await wpApi.createDate({
              title: `${firstName}'s Birthday`,
              status: 'publish',
              date_type: [birthdayType.id],
              acf: {
                date_value: data.birthday,
                is_recurring: true,
                related_people: [personId],
                reminder_days_before: 7,
              },
            });
          } catch (dateError) {
            console.error('Failed to create birthday:', dateError);
            // Continue anyway - person was created successfully
          }
        }
        
        // Handle return navigation if coming from relationship form
        if (returnTo === 'relationship' && sourcePersonId) {
          navigate(`/people/${sourcePersonId}/relationship/new?newPersonId=${personId}`);
        } else {
          navigate(`/people/${personId}`);
        }
      }
    } catch (error) {
      console.error('Failed to save person:', error);
    }
  };
  
  if (isEditing && isLoadingPerson) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        {returnTo === 'relationship' && sourcePersonId ? (
          <Link to={`/people/${sourcePersonId}/relationship/new`} className="flex items-center text-gray-600 hover:text-gray-900">
            <ArrowLeft className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Back to add relationship</span>
          </Link>
        ) : (
          <Link to="/people" className="flex items-center text-gray-600 hover:text-gray-900">
            <ArrowLeft className="w-4 h-4 md:mr-2" />
            <span className="hidden md:inline">Back to people</span>
          </Link>
        )}
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit person' : 'Add new person'}
        </h1>
        
        {/* vCard Import Drop Zone - only show when creating */}
        {!isEditing && (
          <div className="mb-6">
            <div
              className={`relative rounded-lg border-2 border-dashed p-4 text-center transition-colors ${
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
              />
              
              {parseVcardMutation.isPending ? (
                <div className="flex items-center justify-center gap-2 py-2">
                  <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600"></div>
                  <span className="text-sm text-gray-600">Parsing vCard...</span>
                </div>
              ) : vcardFile && !vcardError ? (
                <div className="flex items-center justify-center gap-2 py-2">
                  <FileCode className="h-5 w-5 text-green-600" />
                  <span className="text-sm text-green-700">Loaded from {vcardFile.name}</span>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.preventDefault();
                      e.stopPropagation();
                      clearVcard();
                    }}
                    className="ml-2 p-1 hover:bg-green-100 rounded"
                  >
                    <X className="h-4 w-4 text-green-600" />
                  </button>
                </div>
              ) : (
                <div className="flex items-center justify-center gap-2 py-2">
                  <Upload className="h-5 w-5 text-gray-400" />
                  <span className="text-sm text-gray-600">
                    Drop a vCard file here to import, or <span className="text-primary-600">browse</span>
                  </span>
                </div>
              )}
            </div>
            
            {vcardError && (
              <div className="mt-2 flex items-start gap-2 text-sm text-amber-700 bg-amber-50 rounded-lg p-2">
                <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                <span>{vcardError}</span>
              </div>
            )}
          </div>
        )}
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Basic info */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="label">First name *</label>
              <input
                {...register('first_name', { required: 'First name is required' })}
                className="input"
                placeholder="John"
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
              />
            </div>
          </div>
          
          <div>
            <label className="label">Nickname</label>
            <input
              {...register('nickname')}
              className="input"
              placeholder="Johnny"
            />
          </div>
          
          <div>
            <label className="label">Gender</label>
            <select
              {...register('gender')}
              className="input"
            >
              <option value="">Select gender...</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non_binary">Non-binary</option>
              <option value="other">Other</option>
              <option value="prefer_not_to_say">Prefer not to say</option>
            </select>
          </div>
          
          {!isEditing && (
            <div>
              <label className="label">Email</label>
              <input
                type="email"
                {...register('email')}
                className="input"
                placeholder="john@example.com"
              />
              <p className="text-xs text-gray-500 mt-1">
                Optional: If a Gravatar is associated with this email, it will be automatically set as the profile photo
              </p>
            </div>
          )}
          
          {!isEditing && (
            <div>
              <label className="label">Birthday</label>
              <input
                type="date"
                {...register('birthday')}
                className="input"
              />
              <p className="text-xs text-gray-500 mt-1">
                Optional: Set their birthday when creating a new person
              </p>
            </div>
          )}
          
          <div>
            <label className="label">How we met</label>
            <textarea
              {...register('how_we_met')}
              className="input"
              rows={4}
              placeholder="We met at..."
            />
          </div>
          
          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_favorite"
              {...register('is_favorite')}
              className="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <label htmlFor="is_favorite" className="ml-2 text-sm text-gray-700 cursor-pointer">Mark as favorite</label>
          </div>
          
          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            {returnTo === 'relationship' && sourcePersonId ? (
              <Link to={`/people/${sourcePersonId}/relationship/new`} className="btn-secondary">
                Cancel
              </Link>
            ) : (
              <Link to="/people" className="btn-secondary">
                Cancel
              </Link>
            )}
            <button 
              type="submit" 
              className="btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
              ) : (
                <Save className="w-4 h-4 mr-2" />
              )}
              {isEditing ? 'Save changes' : 'Create person'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
