import { useEffect, useState, useRef, useMemo } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, ChevronDown } from 'lucide-react';

// Comprehensive list of countries
const COUNTRIES = [
  'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
  'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan',
  'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia',
  'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica',
  'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt',
  'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon',
  'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana',
  'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel',
  'Italy', 'Ivory Coast', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kosovo', 'Kuwait',
  'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
  'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico',
  'Micronesia', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru',
  'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway', 'Oman',
  'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal',
  'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe',
  'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia',
  'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria',
  'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey',
  'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu',
  'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'
];

/**
 * Searchable country selector component
 */
function SearchableCountrySelector({ value, onChange, disabled }) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const inputRef = useRef(null);
  const dropdownRef = useRef(null);

  // Filter countries based on search term
  const filteredCountries = useMemo(() => {
    if (!searchTerm) return COUNTRIES;
    const term = searchTerm.toLowerCase();
    return COUNTRIES.filter(country => country.toLowerCase().includes(term));
  }, [searchTerm]);

  // Handle click outside to close dropdown
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        inputRef.current &&
        !inputRef.current.contains(event.target)
      ) {
        setIsOpen(false);
        // Reset search term to show selected value
        setSearchTerm('');
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSelect = (country) => {
    onChange(country);
    setSearchTerm('');
    setIsOpen(false);
  };

  const handleClear = () => {
    onChange('');
    setSearchTerm('');
    setIsOpen(false);
  };

  const handleInputChange = (e) => {
    setSearchTerm(e.target.value);
    setIsOpen(true);
  };

  const handleFocus = () => {
    setIsOpen(true);
    // When focusing, if there's a selected value, show it in the input for editing
    if (value && !searchTerm) {
      setSearchTerm(value);
    }
  };

  // Display value in input: search term when typing, selected value when not open
  const displayValue = isOpen ? searchTerm : value;

  return (
    <div className="relative">
      <div className="relative">
        <input
          ref={inputRef}
          type="text"
          value={displayValue}
          onChange={handleInputChange}
          onFocus={handleFocus}
          placeholder="Search country..."
          className="input pr-8"
          disabled={disabled}
        />
        {value && !isOpen ? (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              handleClear();
            }}
            className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            disabled={disabled}
          >
            <X className="w-4 h-4" />
          </button>
        ) : (
          <ChevronDown className="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        )}
      </div>

      {isOpen && (
        <div
          ref={dropdownRef}
          className="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-48 overflow-y-auto"
        >
          {filteredCountries.length > 0 ? (
            filteredCountries.map(country => (
              <button
                key={country}
                type="button"
                onClick={() => handleSelect(country)}
                className={`w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 ${
                  value === country ? 'bg-accent-50 dark:bg-accent-900/30 text-accent-700 dark:text-accent-300' : 'text-gray-900 dark:text-gray-100'
                }`}
              >
                {country}
              </button>
            ))
          ) : (
            <div className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
              No countries found
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function AddressEditModal({ 
  isOpen, 
  onClose, 
  onSubmit, 
  isLoading, 
  address = null 
}) {
  const isEditing = !!address;

  const { register, handleSubmit, reset, control, formState: { errors } } = useForm({
    defaultValues: {
      address_label: '',
      street: '',
      postal_code: '',
      city: '',
      state: '',
      country: 'Netherlands',
    },
  });

  // Reset form when modal opens
  useEffect(() => {
    if (isOpen) {
      if (address) {
        reset({
          address_label: address.address_label || '',
          street: address.street || '',
          postal_code: address.postal_code || '',
          city: address.city || '',
          state: address.state || '',
          country: address.country || 'Netherlands',
        });
      } else {
        reset({
          address_label: '',
          street: '',
          postal_code: '',
          city: '',
          state: '',
          country: 'Netherlands',
        });
      }
    }
  }, [isOpen, address, reset]);

  if (!isOpen) return null;

  const handleFormSubmit = (data) => {
    onSubmit({
      address_label: data.address_label || '',
      street: data.street || '',
      postal_code: data.postal_code || '',
      city: data.city || '',
      state: data.state || '',
      country: data.country || '',
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{isEditing ? 'Edit address' : 'Add address'}</h2>
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
            {/* Label */}
            <div>
              <label className="label">Label</label>
              <input
                {...register('address_label')}
                className="input"
                placeholder="e.g., Home, Work"
                disabled={isLoading}
              />
            </div>

            {/* Street */}
            <div>
              <label className="label">Street</label>
              <input
                {...register('street')}
                className="input"
                placeholder="e.g., 123 Main Street"
                disabled={isLoading}
              />
            </div>

            {/* City and Postal Code row */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">Postal code</label>
                <input
                  {...register('postal_code')}
                  className="input"
                  placeholder="e.g., 12345"
                  disabled={isLoading}
                />
              </div>
              
              <div>
                <label className="label">City</label>
                <input
                  {...register('city')}
                  className="input"
                  placeholder="e.g., Amsterdam"
                  disabled={isLoading}
                />
              </div>
            </div>

            {/* State and Country row */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="label">State/Province</label>
                <input
                  {...register('state')}
                  className="input"
                  placeholder="e.g., North Holland"
                  disabled={isLoading}
                />
              </div>
              
              <div>
                <label className="label">Country</label>
                <Controller
                  name="country"
                  control={control}
                  render={({ field }) => (
                    <SearchableCountrySelector
                      value={field.value}
                      onChange={field.onChange}
                      disabled={isLoading}
                    />
                  )}
                />
              </div>
            </div>
          </div>
          
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
              disabled={isLoading}
            >
              {isLoading ? 'Saving...' : (isEditing ? 'Save changes' : 'Add address')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
