/**
 * vCard Export Utility
 * 
 * Generates vCard (vcf) format from person data.
 * Supports vCard 3.0 format for maximum compatibility.
 */

/**
 * Escapes special characters in vCard values
 * @param {string} value - The value to escape
 * @returns {string} - Escaped value
 */
function escapeVCardValue(value) {
  if (!value) return '';
  return String(value)
    .replace(/\\/g, '\\\\')
    .replace(/;/g, '\\;')
    .replace(/,/g, '\\,')
    .replace(/\n/g, '\\n');
}

/**
 * Formats a phone number for vCard
 * @param {string} phone - Phone number
 * @returns {string} - Formatted phone number
 */
function formatPhone(phone) {
  if (!phone) return '';
  // Remove all non-digit characters except +
  return phone.replace(/[^\d+]/g, '');
}

/**
 * Formats a date for vCard (YYYYMMDD)
 * @param {string|Date} date - Date value
 * @returns {string} - Formatted date
 */
function formatVCardDate(date) {
  if (!date) return '';
  const d = new Date(date);
  if (isNaN(d.getTime())) return '';
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}${month}${day}`;
}

/**
 * Gets the current job title and organization from work history
 * @param {Array} workHistory - Work history array
 * @param {Object} teamMap - Map of team ID to team name
 * @returns {Object} - {title: string, org: string}
 */
function getCurrentJob(workHistory, teamMap = {}) {
  if (!workHistory || !Array.isArray(workHistory)) {
    return { title: '', org: '' };
  }
  
  const currentJob = workHistory.find(job => job.is_current);
  if (currentJob) {
    const teamName = currentJob.team && teamMap[currentJob.team] 
      ? teamMap[currentJob.team].name || teamMap[currentJob.team]
      : '';
    return {
      title: currentJob.job_title || '',
      org: teamName,
    };
  }
  
  // If no current job, get the most recent one
  const sorted = [...workHistory]
    .filter(job => job.start_date)
    .sort((a, b) => new Date(b.start_date) - new Date(a.start_date));
  
  if (sorted.length > 0) {
    const teamName = sorted[0].team && teamMap[sorted[0].team]
      ? teamMap[sorted[0].team].name || teamMap[sorted[0].team]
      : '';
    return {
      title: sorted[0].job_title || '',
      org: teamName,
    };
  }
  
  return { title: '', org: '' };
}

/**
 * Generates vCard 3.0 format from person data
 * @param {Object} person - Person object from API
 * @param {Object} options - Optional parameters
 * @param {Object} options.teamMap - Map of team ID to team data/name
 * @returns {string} - vCard content
 */
export function generateVCard(person, options = {}) {
  if (!person) {
    throw new Error('Person data is required');
  }

  const { teamMap = {} } = options;
  const fields = person.fields || {};
  const lines = [];
  
  // BEGIN:VCARD
  lines.push('BEGIN:VCARD');
  lines.push('VERSION:3.0');
  
  // Name fields
  const firstName = fields.first_name || '';
  const infix = fields.infix || '';
  const lastName = fields.last_name || '';
  const fullName = person.name || [firstName, infix, lastName].filter(Boolean).join(' ') || 'Unknown';

  // FN (Full Name) - required
  lines.push(`FN:${escapeVCardValue(fullName)}`);

  // N (Name) - Family;Given;Additional;Prefix;Suffix
  lines.push(`N:${escapeVCardValue(lastName)};${escapeVCardValue(firstName)};${escapeVCardValue(infix)};;`);
  
  // Nickname
  if (fields.nickname) {
    lines.push(`NICKNAME:${escapeVCardValue(fields.nickname)}`);
  }
  
  // Contact information from fixed fields
  if (fields.email_1) {
    lines.push(`EMAIL;TYPE=INTERNET:${escapeVCardValue(fields.email_1)}`);
  }
  if (fields.email_2) {
    lines.push(`EMAIL;TYPE=INTERNET,HOME:${escapeVCardValue(fields.email_2)}`);
  }
  if (fields.mobile_1) {
    const formattedPhone = formatPhone(fields.mobile_1);
    if (formattedPhone) {
      lines.push(`TEL;TYPE=CELL:${formattedPhone}`);
    }
  }
  if (fields.mobile_2) {
    const formattedPhone = formatPhone(fields.mobile_2);
    if (formattedPhone) {
      lines.push(`TEL;TYPE=CELL,HOME:${formattedPhone}`);
    }
  }
  if (fields.telephone_1) {
    const formattedPhone = formatPhone(fields.telephone_1);
    if (formattedPhone) {
      lines.push(`TEL;TYPE=VOICE:${formattedPhone}`);
    }
  }
  if (fields.telephone_2) {
    const formattedPhone = formatPhone(fields.telephone_2);
    if (formattedPhone) {
      lines.push(`TEL;TYPE=VOICE,HOME:${formattedPhone}`);
    }
  }
  
  // Addresses (structured format)
  if (fields.addresses && Array.isArray(fields.addresses)) {
    fields.addresses.forEach(address => {
      // ADR;TYPE=HOME:POBox;Extended;Street;City;State;PostalCode;Country
      const addrType = address.address_label ? `ADR;TYPE=${address.address_label.toUpperCase()}` : 'ADR;TYPE=HOME';
      const street = escapeVCardValue([address.street_name, address.house_number, address.house_number_addition].filter(Boolean).join(' '));
      const city = escapeVCardValue(address.city || '');
      const state = escapeVCardValue(address.state || '');
      const postalCode = escapeVCardValue(address.postal_code || '');
      const country = escapeVCardValue(address.country || '');
      
      // Only add if there's at least some address data
      if (street || city || state || postalCode || country) {
        lines.push(`${addrType}:;;${street};${city};${state};${postalCode};${country}`);
      }
    });
  }
  
  // Organization and title from work history
  const { title, org } = getCurrentJob(fields.work_history, teamMap);
  if (org) {
    lines.push(`ORG:${escapeVCardValue(org)}`);
  }
  if (title) {
    lines.push(`TITLE:${escapeVCardValue(title)}`);
  }
  
  // Birthday from person birthdate field
  if (fields.birthdate) {
    const bday = formatVCardDate(fields.birthdate);
    if (bday) {
      lines.push(`BDAY:${bday}`);
    }
  }
  
  // Photo (if available)
  if (person.thumbnail) {
    // Note: vCard photos are typically base64 encoded, but we'll use URL
    // Some clients support URL, others require base64
    // For now, we'll skip photo as it requires fetching and encoding
  }
  
  // Note (from timeline/notes if available)
  // Note: We'd need to fetch notes, but for now we'll skip this
  
  // REV (Revision) - last modified date
  if (person.modified) {
    const revDate = formatVCardDate(person.modified);
    if (revDate) {
      lines.push(`REV:${revDate}T000000Z`);
    }
  }
  
  // END:VCARD
  lines.push('END:VCARD');
  
  return lines.join('\r\n');
}

/**
 * Downloads a vCard file
 * @param {Object} person - Person object from API
 * @param {Object} options - Optional parameters
 * @param {string} options.filename - Optional filename (defaults to person name)
 * @param {Object} options.teamMap - Map of team ID to team data/name
 */
export function downloadVCard(person, options = {}) {
  const { filename, ...vcardOptions } = options;
  const vcardContent = generateVCard(person, vcardOptions);
  const blob = new Blob([vcardContent], { type: 'text/vcard;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  
  const link = document.createElement('a');
  link.href = url;
  link.download = filename || `${person.name || 'contact'}.vcf`.replace(/[^a-z0-9.-]/gi, '_');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  // Clean up
  URL.revokeObjectURL(url);
}
