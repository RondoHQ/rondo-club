/**
 * Reusable avatar component for displaying person thumbnails with fallback initials.
 * Consolidates the repeated avatar rendering pattern throughout the dashboard.
 */

const SIZE_CLASSES = {
  xs: 'w-5 h-5 text-xs',
  sm: 'w-6 h-6 text-xs',
  md: 'w-8 h-8 text-xs',
  lg: 'w-10 h-10 text-sm',
};

/**
 * Extract initials from a name string.
 * @param {string} name - Full name or first name
 * @param {string} firstName - Optional first name for fallback
 * @returns {string} Single initial character
 */
function getInitial(name, firstName) {
  if (firstName?.[0]) return firstName[0];
  if (name?.[0]) return name[0];
  return '?';
}

/**
 * PersonAvatar component displays a person's photo or fallback initials.
 *
 * @param {Object} props
 * @param {string} props.thumbnail - URL of the person's thumbnail image
 * @param {string} props.name - Person's full name (used for alt text and fallback initial)
 * @param {string} [props.firstName] - Person's first name (used for fallback initial if provided)
 * @param {'xs'|'sm'|'md'|'lg'} [props.size='lg'] - Size variant of the avatar
 * @param {string} [props.className] - Additional CSS classes to apply
 * @param {string} [props.borderClassName] - Border classes for stacked avatars (e.g., "border-2 border-white")
 */
export default function PersonAvatar({
  thumbnail,
  name,
  firstName,
  size = 'lg',
  className = '',
  borderClassName = '',
}) {
  const sizeClass = SIZE_CLASSES[size] || SIZE_CLASSES.lg;
  const initial = getInitial(name, firstName);

  if (thumbnail) {
    return (
      <img
        src={thumbnail}
        alt={name || 'Person'}
        loading="lazy"
        className={`${sizeClass} rounded-full object-cover ${borderClassName} ${className}`.trim()}
      />
    );
  }

  return (
    <div
      className={`${sizeClass} bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center ${borderClassName} ${className}`.trim()}
    >
      <span className="font-medium text-gray-500 dark:text-gray-300">
        {initial}
      </span>
    </div>
  );
}
