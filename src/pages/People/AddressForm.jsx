import { useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ArrowLeft, Save } from 'lucide-react';
import { usePerson, useUpdatePerson } from '@/hooks/usePeople';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { sanitizePersonAcf } from '@/utils/formatters';

export default function AddressForm() {
  const { personId, index } = useParams();
  const navigate = useNavigate();
  const addressIndex = parseInt(index, 10);
  const isEditing = !isNaN(addressIndex);
  
  const { data: person, isLoading: isLoadingPerson } = usePerson(personId);
  const updatePerson = useUpdatePerson();

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm({
    defaultValues: {
      address_label: '',
      street: '',
      postal_code: '',
      city: '',
      state: '',
      country: '',
    },
  });

  // Load address item when editing
  useEffect(() => {
    if (person && isEditing) {
      const addresses = person.acf?.addresses || [];
      const addressItem = addresses[addressIndex];
      
      if (addressItem) {
        reset({
          address_label: addressItem.address_label || '',
          street: addressItem.street || '',
          postal_code: addressItem.postal_code || '',
          city: addressItem.city || '',
          state: addressItem.state || '',
          country: addressItem.country || '',
        });
      }
    }
  }, [person, addressIndex, isEditing, reset]);

  // Update document title
  useDocumentTitle(
    isEditing
      ? `Edit address - ${person?.title?.rendered || person?.title || 'person'}`
      : `Add address - ${person?.title?.rendered || person?.title || 'person'}`
  );

  const onSubmit = async (data) => {
    try {
      const addresses = [...(person.acf?.addresses || [])];
      
      const addressItem = {
        address_label: data.address_label || '',
        street: data.street || '',
        postal_code: data.postal_code || '',
        city: data.city || '',
        state: data.state || '',
        country: data.country || '',
      };

      if (isEditing) {
        // Update existing item
        addresses[addressIndex] = addressItem;
      } else {
        // Add new item
        addresses.push(addressItem);
      }

      // Sanitize ACF data and set the updated addresses
      const acfData = sanitizePersonAcf(person.acf, {
        addresses: addresses,
      });

      await updatePerson.mutateAsync({
        id: personId,
        data: {
          acf: acfData,
        },
      });
      
      navigate(`/people/${personId}`);
    } catch (error) {
      console.error('Failed to save address:', error);
    }
  };

  if (isLoadingPerson) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>
    );
  }

  if (!person) {
    return (
      <div className="card p-6 text-center">
        <p className="text-red-600">Failed to load person.</p>
        <Link to="/people" className="btn-secondary mt-4">Back to people</Link>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <Link to={`/people/${personId}`} className="flex items-center text-gray-600 hover:text-gray-900">
          <ArrowLeft className="w-4 h-4 md:mr-2" />
          <span className="hidden md:inline">Back to person</span>
        </Link>
      </div>
      
      <div className="card p-6">
        <h1 className="text-xl font-bold mb-6">
          {isEditing ? 'Edit address' : 'Add address'}
        </h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          {/* Label */}
          <div>
            <label className="label">Label</label>
            <input
              {...register('address_label')}
              className="input"
              placeholder="e.g., Home, Work"
            />
          </div>

          {/* Street */}
          <div>
            <label className="label">Street</label>
            <input
              {...register('street')}
              className="input"
              placeholder="e.g., 123 Main Street"
            />
          </div>

          {/* City and Postal Code row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="label">Postal code</label>
              <input
                {...register('postal_code')}
                className="input"
                placeholder="e.g., 12345"
              />
            </div>
            
            <div>
              <label className="label">City</label>
              <input
                {...register('city')}
                className="input"
                placeholder="e.g., Amsterdam"
              />
            </div>
          </div>

          {/* State and Country row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="label">State/Province</label>
              <input
                {...register('state')}
                className="input"
                placeholder="e.g., North Holland"
              />
            </div>
            
            <div>
              <label className="label">Country</label>
              <input
                {...register('country')}
                className="input"
                placeholder="e.g., Netherlands"
              />
            </div>
          </div>
          
          {/* Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Link to={`/people/${personId}`} className="btn-secondary">
              Cancel
            </Link>
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
              {isEditing ? 'Save changes' : 'Add address'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

