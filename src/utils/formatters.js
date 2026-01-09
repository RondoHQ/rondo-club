/**
 * Utility functions for formatting and displaying data
 */

/**
 * Decode HTML entities in a string
 * Converts entities like &amp; to & and &#8211; to –
 * 
 * @param {string} html - String potentially containing HTML entities
 * @returns {string} Decoded string
 */
export function decodeHtml(html) {
  if (!html) return '';
  const txt = document.createElement('textarea');
  txt.innerHTML = html;
  return txt.value;
}

/**
 * Get the display name for a company from various object formats
 * Handles WordPress REST API response format and decoded values
 * 
 * @param {Object} company - Company object (from API or transformed)
 * @returns {string} Decoded company name
 */
export function getCompanyName(company) {
  if (!company) return '';
  
  // Handle various formats WordPress might return
  const rawName = company.title?.rendered || company.title || company.name || '';
  return decodeHtml(rawName);
}

/**
 * Get the display name for a person from various object formats
 * Handles WordPress REST API response format and transformed objects
 * 
 * @param {Object} person - Person object (from API or transformed)
 * @returns {string} Decoded person name
 */
export function getPersonName(person) {
  if (!person) return '';
  
  // If already transformed (has .name that's not .title.rendered)
  if (person.name && typeof person.name === 'string' && !person.title?.rendered) {
    return decodeHtml(person.name);
  }
  
  // Handle WordPress REST API format
  const rawName = person.title?.rendered || person.title || person.name || '';
  return decodeHtml(rawName);
}

/**
 * Get the first initial of a person's name for avatars
 * 
 * @param {Object} person - Person object
 * @returns {string} First character or '?'
 */
export function getPersonInitial(person) {
  if (!person) return '?';
  
  // Prefer first_name if available
  const firstName = person.first_name || person.acf?.first_name;
  if (firstName) return firstName[0];
  
  // Fall back to full name
  const name = getPersonName(person);
  return name[0] || '?';
}

/**
 * Format a date value, optionally hiding the year
 * 
 * @param {string} dateString - Date string in Y-m-d format
 * @param {boolean} yearUnknown - Whether to hide the year
 * @param {Function} formatFn - date-fns format function
 * @returns {string} Formatted date string
 */
export function formatDateValue(dateString, yearUnknown = false, formatFn) {
  if (!dateString || !formatFn) return '';
  
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    
    if (yearUnknown) {
      return formatFn(date, 'MMMM d');
    }
    return formatFn(date, 'MMMM d, yyyy');
  } catch {
    return '';
  }
}

/**
 * Sanitize ACF data for person updates via REST API
 * - Converts empty strings to null for enum fields (gender)
 * - Ensures repeater fields are always arrays
 * 
 * @param {Object} acfData - The ACF data to sanitize
 * @param {Object} overrides - Fields to override in the sanitized data
 * @returns {Object} Sanitized ACF data ready for API submission
 */
export function sanitizePersonAcf(acfData, overrides = {}) {
  // Fields that are select/enum and should be null instead of empty string
  const enumFields = ['gender'];
  
  // Fields that are repeaters and should always be arrays
  const repeaterFields = ['contact_info', 'addresses', 'work_history', 'relationships', 'photo_gallery'];
  
  const sanitized = { ...acfData };
  
  // Convert empty strings to null for enum fields
  enumFields.forEach(field => {
    if (sanitized[field] === '') {
      sanitized[field] = null;
    }
  });
  
  // Ensure repeater fields are arrays
  repeaterFields.forEach(field => {
    if (!Array.isArray(sanitized[field])) {
      sanitized[field] = [];
    }
  });
  
  // Apply overrides
  Object.assign(sanitized, overrides);
  
  return sanitized;
}

