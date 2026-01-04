import { useNavigate } from 'react-router-dom';

/**
 * Person Node Component for Family Tree
 * Displays a person (or couple) card in the tree visualization
 */
export default function PersonNode({ node, onClick }) {
  const navigate = useNavigate();
  const { 
    id, 
    coupleIds,
    _name, 
    gender, 
    _photo, 
    _partnerPhoto,
    _age, 
    _birthDate, 
    isVirtualRoot, 
    _isCouple,
  } = node || {};
  
  const name = _name || `Person ${id}`;
  const photo = _photo;
  const partnerPhoto = _partnerPhoto;
  const age = _age;
  const birthDate = _birthDate;
  const isCouple = _isCouple;
  
  // Hide virtual root nodes completely
  if (isVirtualRoot || id === 'family-tree-root' || id === 'virtual-root') {
    return null;
  }
  
  const handleClick = () => {
    if (onClick) {
      onClick(node);
    } else if (coupleIds && coupleIds.length > 0) {
      // Navigate to first person in couple
      navigate(`/people/${coupleIds[0]}`);
    } else if (id) {
      navigate(`/people/${id}`);
    }
  };
  
  // Get gender symbol
  function getGenderSymbol(gender) {
    if (!gender) return null;
    switch (gender) {
      case 'male': return '♂';
      case 'female': return '♀';
      case 'non_binary':
      case 'other':
      case 'prefer_not_to_say':
        return '⚧';
      default: return null;
    }
  }
  
  // Format date as dd-mm-yyyy
  function formatDate(dateString) {
    if (!dateString) return null;
    try {
      let date;
      if (typeof dateString === 'string') {
        date = new Date(dateString);
        if (isNaN(date.getTime()) && dateString.match(/^\d{4}-\d{2}-\d{2}/)) {
          const parts = dateString.split('T')[0].split('-');
          date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        }
      } else {
        date = new Date(dateString);
      }
      
      if (isNaN(date.getTime())) return null;
      
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      
      return `${day}-${month}-${year}`;
    } catch (e) {
      return null;
    }
  }
  
  // Get initials from name
  function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?';
  }
  
  const genderSymbol = !isCouple ? getGenderSymbol(gender) : null;
  const displayName = name || 'Unknown';
  const formattedBirthDate = birthDate ? formatDate(birthDate) : null;
  
  // For couples, split the name to get initials for each
  const names = isCouple ? displayName.split(' & ') : [displayName];
  
  return (
    <div
      onClick={handleClick}
      className="bg-white border-2 border-gray-300 rounded-lg shadow-md p-2 cursor-pointer hover:border-primary-500 hover:shadow-lg transition-all"
      style={{ width: '100%', height: '100%' }}
    >
      {/* Photo(s) or Initials */}
      <div className="flex justify-center mb-1 gap-1">
        {isCouple ? (
          // Show two photos for couples
          <>
            {photo ? (
              <img src={photo} alt="" className="w-8 h-8 rounded-full object-cover" />
            ) : (
              <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                <span className="text-xs font-medium text-gray-600">{getInitials(names[0])}</span>
              </div>
            )}
            {partnerPhoto ? (
              <img src={partnerPhoto} alt="" className="w-8 h-8 rounded-full object-cover" />
            ) : (
              <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                <span className="text-xs font-medium text-gray-600">{getInitials(names[1])}</span>
              </div>
            )}
          </>
        ) : (
          // Single photo
          photo ? (
            <img src={photo} alt={displayName} className="w-10 h-10 rounded-full object-cover" />
          ) : (
            <div className="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
              <span className="text-xs font-medium text-gray-600">{getInitials(displayName)}</span>
            </div>
          )
        )}
      </div>
      
      {/* Name */}
      <div className="text-center">
        <p className="text-xs font-semibold text-gray-900 break-words leading-tight" title={displayName}>
          {displayName}
        </p>
        {!isCouple && (genderSymbol || (age !== null && age !== undefined) || formattedBirthDate) && (
          <p className="text-xs text-gray-500 mt-0.5 leading-tight">
            {genderSymbol && <span className="mr-1">{genderSymbol}</span>}
            {age !== null && age !== undefined && <span className="mr-1">{age}</span>}
            {formattedBirthDate && <span>{formattedBirthDate}</span>}
          </p>
        )}
      </div>
    </div>
  );
}
