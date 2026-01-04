import { useNavigate } from 'react-router-dom';

/**
 * Person Node Component for Family Tree
 * Displays a person card in the tree visualization
 * Compatible with react-d3-tree's renderCustomNodeElement API
 */
export default function PersonNode({ nodeDatum, onClick }) {
  const navigate = useNavigate();
  const { name, attributes } = nodeDatum || {};
  const { id, gender, photo, age } = attributes || {};
  
  const handleClick = () => {
    if (onClick) {
      onClick(nodeDatum);
    } else if (id) {
      navigate(`/people/${id}`);
    }
  };
  
  // Get gender symbol
  function getGenderSymbol(gender) {
    if (!gender) return null;
    switch (gender) {
      case 'male':
        return '♂';
      case 'female':
        return '♀';
      case 'non_binary':
      case 'other':
      case 'prefer_not_to_say':
        return '⚧';
      default:
        return null;
    }
  }
  
  const genderSymbol = getGenderSymbol(gender);
  const displayName = name || 'Unknown';
  const initials = displayName.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '?';
  
  return (
    <g>
      <foreignObject
        x={-60}
        y={-40}
        width={120}
        height={80}
        className="person-node"
      >
        <div
          onClick={handleClick}
          className="bg-white border-2 border-gray-300 rounded-lg shadow-md p-2 cursor-pointer hover:border-primary-500 hover:shadow-lg transition-all"
          style={{ width: '100%', height: '100%', boxSizing: 'border-box' }}
        >
          {/* Photo or Initials */}
          <div className="flex justify-center mb-1">
            {photo ? (
              <img
                src={photo}
                alt={displayName}
                className="w-10 h-10 rounded-full object-cover"
              />
            ) : (
              <div className="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                <span className="text-xs font-medium text-gray-600">
                  {initials}
                </span>
              </div>
            )}
          </div>
          
          {/* Name */}
          <div className="text-center">
            <p className="text-xs font-semibold text-gray-900 truncate" title={displayName}>
              {displayName}
            </p>
            {(genderSymbol || (age !== null && age !== undefined)) && (
              <p className="text-xs text-gray-500 mt-0.5">
                {genderSymbol && <span className="mr-1">{genderSymbol}</span>}
                {age !== null && age !== undefined && <span>{age}</span>}
              </p>
            )}
          </div>
        </div>
      </foreignObject>
    </g>
  );
}

