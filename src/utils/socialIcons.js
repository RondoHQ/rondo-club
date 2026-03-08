/**
 * Social/external link icon configuration utilities.
 * Centralizes the mapping of link types to their icons and colors.
 */
import { SiWhatsapp } from '@icons-pack/react-simple-icons';

/**
 * Configuration for link icons.
 * Maps type to icon component and brand color.
 */
export const SOCIAL_CONFIG = {
  whatsapp: {
    icon: SiWhatsapp,
    color: 'text-[#25D366]',
  },
};

/**
 * Display order for link icons in the UI.
 */
export const SOCIAL_DISPLAY_ORDER = {
  whatsapp: 1,
  sportlink: 2,
  freescout: 3,
  membership_pass: 4,
};

/**
 * Get the icon component for a link type.
 *
 * @param {string} type - Link type
 * @returns {React.ComponentType|null} Icon component or null
 */
export function getSocialIcon(type) {
  return SOCIAL_CONFIG[type]?.icon || null;
}

/**
 * Get the color class for a link type.
 *
 * @param {string} type - Link type
 * @returns {string} Tailwind color classes
 */
export function getSocialIconColor(type) {
  return SOCIAL_CONFIG[type]?.color || 'text-gray-600';
}

/**
 * Sort links by display order.
 *
 * @param {Array} links - Array of link objects with contact_type
 * @returns {Array} Sorted array
 */
export function sortSocialLinks(links) {
  return [...links].sort((a, b) => {
    const orderA = SOCIAL_DISPLAY_ORDER[a.contact_type] || 99;
    const orderB = SOCIAL_DISPLAY_ORDER[b.contact_type] || 99;
    return orderA - orderB;
  });
}
