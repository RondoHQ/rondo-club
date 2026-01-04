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

