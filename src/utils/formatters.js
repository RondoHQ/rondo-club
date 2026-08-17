/**
 * Utility functions for formatting and displaying data
 */

/**
 * Format a number as currency in euros
 *
 * @param {number|null|undefined} amount - Amount to format
 * @param {number} decimals - Number of decimal places (default 2)
 * @returns {string} Formatted currency string or '-' if null/undefined
 */
export function formatCurrency(amount, decimals = 2) {
  if (amount === null || amount === undefined) return '-';
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(amount);
}

/**
 * Whether an native field person payload has the independent sponsor role.
 *
 * @param {Object} fields Person native field values.
 * @returns {boolean}
 */
export function hasSponsorRole(fields = {}) {
  return fields.is_sponsor === true || fields.is_sponsor === 1 || fields.is_sponsor === '1';
}

/**
 * Format a decimal rate as a percentage
 *
 * @param {number} rate - Decimal rate (e.g., 0.25 for 25%)
 * @returns {string} Formatted percentage string (e.g., "25%")
 */
export function formatPercentage(rate) {
  return `${Math.round(rate * 100)}%`;
}

/**
 * Fixed palette of Tailwind color classes for fee category badges.
 * Indexed by sort_order from the API categories metadata.
 * Uses modulo for categories beyond the palette size.
 */
export const CATEGORY_COLOR_PALETTE = [
  'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
];

/**
 * Get Tailwind color classes for a category based on its sort order.
 *
 * @param {number|undefined} sortOrder - The category's sort_order from API metadata
 * @returns {string} Tailwind color class string
 */
export function getCategoryColor(sortOrder) {
  if (sortOrder === undefined || sortOrder === null) {
    return CATEGORY_COLOR_PALETTE[0];
  }
  return CATEGORY_COLOR_PALETTE[sortOrder % CATEGORY_COLOR_PALETTE.length];
}

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
 * Get the display name for a team from various object formats
 * Handles WordPress REST API response format and decoded values
 * 
 * @param {Object} team - Team object (from API or transformed)
 * @returns {string} Decoded team name
 */
export function getTeamName(team) {
  if (!team) return '';
  
  // Handle various formats WordPress might return
  const rawName = team.title?.rendered || team.title || team.name || '';
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
 * Format a person's full name from first_name, infix, and last_name.
 * Uses array_filter + join pattern to avoid double spaces when infix is empty.
 *
 * @param {string} firstName
 * @param {string} infix
 * @param {string} lastName
 * @returns {string} Formatted full name
 */
export function formatPersonName(firstName, infix, lastName) {
  return [firstName, infix, lastName].filter(Boolean).join(' ');
}

/**
 * Format the surname exactly as it should be displayed in Dutch lists.
 * Sorting must still use last_name itself, so an infix such as "de" does not
 * move De Valk to the letter D.
 *
 * @param {string} infix
 * @param {string} lastName
 * @returns {string} Formatted surname
 */
export function formatPersonSurname(infix, lastName) {
  return [infix, lastName].filter(Boolean).join(' ');
}

/**
 * Compare person-shaped records by a canonical name field with a stable
 * secondary name. The visible surname may contain an infix; the comparison
 * deliberately uses last_name only.
 */
export function comparePersonNames(a, b, field = 'last_name') {
  const primaryA = String(a?.[field] || '').trim();
  const primaryB = String(b?.[field] || '').trim();
  const primary = primaryA.localeCompare(primaryB, 'nl', { sensitivity: 'base' });
  if (primary !== 0) return primary;

  const secondaryField = field === 'last_name' ? 'first_name' : 'last_name';
  return String(a?.[secondaryField] || '').trim().localeCompare(
    String(b?.[secondaryField] || '').trim(),
    'nl',
    { sensitivity: 'base' },
  );
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
  const firstName = person.first_name || person.fields?.first_name;
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
 * Sanitize a partial canonical person-fields update.
 * - Converts empty strings to null for enum fields (select fields with restricted choices)
 * - Ensures repeater fields are always arrays
 *
 * @param {Object} currentFields - Current fields (retained for call-site compatibility)
 * @param {Object} overrides - Fields to override in the sanitized data
 * @returns {Object} Sanitized partial fields payload ready for API submission
 */
export function sanitizePersonFields(currentFields, overrides = {}) {
  // Fields that are select/enum and should be null instead of empty string
  const enumFields = ['gender', 'vergoeding_reden', 'person_type', 'sponsor_pass_variant'];

  // Fields that expect number|null — convert empty strings to null, string numbers to numbers
  const numericFields = [
    'freescout_id',
    '_nikki_2025_total',
    '_nikki_2025_saldo',
    '_nikki_2024_total',
    '_nikki_2024_saldo',
    '_nikki_2023_total',
    '_nikki_2023_saldo',
    '_nikki_2022_total',
    '_nikki_2022_saldo',
  ];

  // Fields that are repeaters and should always be arrays
  const repeaterFields = ['addresses', 'work_history', 'relationships', 'photo_gallery'];
  
  const sanitized = { ...overrides };
  
  // Convert empty strings to null for enum fields
  enumFields.forEach(field => {
    if (Object.hasOwn(sanitized, field) && sanitized[field] === '') {
      sanitized[field] = null;
    }
  });

  // Convert numeric fields to proper type
  numericFields.forEach(field => {
    if (!Object.hasOwn(sanitized, field)) return;
    if (sanitized[field] === '' || sanitized[field] === undefined) {
      sanitized[field] = null;
    } else if (typeof sanitized[field] === 'string') {
      sanitized[field] = Number(sanitized[field]);
    }
  });

  // Ensure repeater fields are arrays
  repeaterFields.forEach(field => {
    if (Object.hasOwn(sanitized, field) && !Array.isArray(sanitized[field])) {
      sanitized[field] = [];
    }
  });

  return sanitized;
}

export function sanitizeTeamFields(currentFields, overrides = {}) {
  // Fields that are repeaters and should always be arrays
  const repeaterFields = ['contact_info'];

  const sanitized = { ...overrides };

  // Ensure repeater fields are arrays
  repeaterFields.forEach(field => {
    if (Object.hasOwn(sanitized, field) && !Array.isArray(sanitized[field])) {
      sanitized[field] = [];
    }
  });

  return sanitized;
}

/**
 * Get the display name for a commissie from various object formats
 * Handles WordPress REST API response format and decoded values
 *
 * @param {Object} commissie - Commissie object (from API or transformed)
 * @returns {string} Decoded commissie name
 */
export function getCommissieName(commissie) {
  if (!commissie) return '';

  // Handle various formats WordPress might return
  const rawName = commissie.title?.rendered || commissie.title || commissie.name || '';
  return decodeHtml(rawName);
}

export function sanitizeCommissieFields(currentFields, overrides = {}) {
  // Fields that are repeaters and should always be arrays
  const repeaterFields = ['contact_info'];

  // Fields that are select/enum and should be null instead of empty string
  // (native field auto-generates a REST enum schema that rejects "" — see CLAUDE.md pitfall)
  const enumFields = ['uren_periode'];

  // Fields that expect number|null — convert empty strings to null, string numbers to numbers
  const numericFields = ['uren_aantal', 'max_leden', 'max_wachtlijst'];

  // Merge overrides first so coercion below also applies to edited values
  const sanitized = { ...overrides };

  // Ensure repeater fields are arrays
  repeaterFields.forEach(field => {
    if (Object.hasOwn(sanitized, field) && !Array.isArray(sanitized[field])) {
      sanitized[field] = [];
    }
  });

  // Convert empty strings to null for enum/select fields
  enumFields.forEach(field => {
    if (Object.hasOwn(sanitized, field) && sanitized[field] === '') {
      sanitized[field] = null;
    }
  });

  // Convert numeric fields to proper type
  numericFields.forEach(field => {
    if (!Object.hasOwn(sanitized, field)) return;
    if (sanitized[field] === '' || sanitized[field] === undefined) {
      sanitized[field] = null;
    } else if (typeof sanitized[field] === 'string') {
      sanitized[field] = Number(sanitized[field]);
    }
  });

  return sanitized;
}

/**
 * Parse a date value coming from native field or the REST API.
 *
 * Handles two formats both seen in practice:
 *  - ISO 8601 / "YYYY-MM-DD" / full datetime — passed straight to `new Date()`
 *  - "YYYYMMDD" — native field's internal date_picker storage format, which `new Date()`
 *    does NOT parse natively (returns Invalid Date). Common on work_history
 *    entries written by the rondo-sync pipeline.
 *
 * @param {string|null|undefined} dateString
 * @returns {Date|null} Parsed Date, or null if input is missing/invalid
 */
export function parseFieldDate(dateString) {
  if (!dateString || typeof dateString !== 'string') return null;
  const trimmed = dateString.trim();
  let date;
  if (/^\d{8}$/.test(trimmed)) {
    date = new Date(`${trimmed.slice(0, 4)}-${trimmed.slice(4, 6)}-${trimmed.slice(6, 8)}`);
  } else {
    date = new Date(trimmed);
  }
  return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Validate date strings
 *
 * @param {string|null|undefined} dateString - Date string to validate
 * @returns {boolean} True if valid date
 */
export function isValidDate(dateString) {
  return parseFieldDate(dateString) !== null;
}

/**
 * Get gender symbol for display
 *
 * @param {string|null|undefined} gender - Gender value
 * @returns {string|null} Unicode gender symbol or null
 */
export function getGenderSymbol(gender) {
  if (!gender) return null;
  switch (gender) {
    case 'male':
      return '\u2642'; // Male symbol
    case 'female':
      return '\u2640'; // Female symbol
    case 'non_binary':
    case 'other':
    case 'prefer_not_to_say':
      return '\u26A7'; // Transgender symbol
    default:
      return null;
  }
}

/**
 * Calculate VOG (Certificate of Conduct) status based on native field data
 *
 * @param {Object} fields - native field data object containing volunteer status and VOG date
 * @returns {Object|null} Status object with status, label, and color, or null if not applicable
 */
export function getVogStatus(fields) {
  // Only show VOG status for current volunteers.
  const isVolunteer = fields?.['huidig_vrijwilliger'] === true || fields?.['huidig_vrijwilliger'] === '1';
  if (!isVolunteer) {
    return null;
  }

  const vogDate = fields?.['datum_vog'] || fields?.vog_datum;
  if (!vogDate) {
    return { status: 'missing', label: 'Geen VOG', color: 'red' };
  }

  const vogDateObj = new Date(vogDate);
  const threeYearsAgo = new Date();
  threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);

  if (vogDateObj >= threeYearsAgo) {
    return { status: 'valid', label: 'VOG OK', color: 'green' };
  }
  return { status: 'expired', label: 'VOG verlopen', color: 'orange' };
}

/**
 * Format phone number for tel: and WhatsApp links
 * Removes all non-digit characters except + at the start, removes Unicode marks,
 * and converts Dutch mobile numbers (06...) to international format (+316...)
 *
 * @param {string|null|undefined} phone - Phone number to format
 * @returns {string} Formatted phone number
 */
export function formatPhoneForTel(phone) {
  if (!phone) return '';
  // Remove all Unicode marks and invisible characters
  let cleaned = phone.replace(/[\u200B-\u200D\uFEFF\u200E\u200F\u202A-\u202E]/g, '');
  // Extract + if present at the start
  const hasPlus = cleaned.startsWith('+');
  // Remove all non-digit characters
  cleaned = cleaned.replace(/\D/g, '');
  // Convert Dutch mobile numbers (06...) to international format (+316...)
  if (!hasPlus && cleaned.startsWith('06')) {
    return `+316${cleaned.slice(2)}`;
  }
  // Prepend + if it was at the start
  return hasPlus ? `+${cleaned}` : cleaned;
}

/**
 * Check if a phone value should be treated as Dutch mobile.
 *
 * @param {string|null|undefined} phone - Phone number to inspect
 * @returns {boolean} True when the number starts with +316 or 06
 */
/**
 * Format a phone number for readable display.
 * Converts E.164 phone numbers to familiar Dutch format.
 *
 * @param {string|null|undefined} phone - Phone number (typically E.164)
 * @returns {string} Readable phone number (e.g., 06-12345678, 020-1234567)
 */
export function formatPhoneForDisplay(phone) {
  if (!phone) return '';
  const cleaned = String(phone).trim();
  if (!cleaned) return '';

  // Dutch mobile: +316xxxxxxxx -> 06-xxxxxxxx
  if (/^\+316\d{8}$/.test(cleaned)) {
    return `06-${cleaned.slice(4)}`;
  }

  // Dutch landline: +31 followed by 9 national digits
  if (/^\+31\d{9}$/.test(cleaned)) {
    const national = cleaned.slice(3); // 9 digits after +31
    const threeDigitAreas = [
      '10','13','14','15','20','23','24','26','30','33','35','36','38',
      '40','43','45','46','50','53','55','58','70','71','72','73','74',
      '75','76','77','78','79','88',
    ];
    const areaPrefix = national.slice(0, 2);
    if (threeDigitAreas.includes(areaPrefix)) {
      // 3-digit area code (0xx): 0xx-xxxxxxx
      return `0${national.slice(0, 2)}-${national.slice(2)}`;
    }
    // 4-digit area code (0xxx): 0xxx-xxxxxx
    return `0${national.slice(0, 3)}-${national.slice(3)}`;
  }

  // Non-NL international: space after country code prefix
  if (/^\+\d+$/.test(cleaned) && cleaned.length > 4) {
    return `${cleaned.slice(0, 3)} ${cleaned.slice(3)}`;
  }

  // Fallback: return as-is
  return cleaned;
}

export function isDutchMobilePhone(phone) {
  if (!phone) return false;

  const cleaned = String(phone)
    .replace(/[\u200B-\u200D\uFEFF\u200E\u200F\u202A-\u202E]/g, '')
    .trim()
    .replace(/[^\d+]/g, '');

  return cleaned.startsWith('+316') || cleaned.startsWith('06');
}
