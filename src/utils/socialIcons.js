/**
 * Social icon configuration utilities.
 * Centralizes the mapping of social media types to their icons and colors.
 */
import { Globe } from 'lucide-react';
import { SiFacebook, SiInstagram, SiX, SiBluesky, SiThreads, SiWhatsapp } from '@icons-pack/react-simple-icons';
import LinkedInIcon from '@/components/icons/LinkedInIcon.jsx';

/**
 * Configuration for social media icons.
 * Maps social type to icon component and brand color.
 */
export const SOCIAL_CONFIG = {
  facebook: {
    icon: SiFacebook,
    color: 'text-[#1877F2]',
  },
  linkedin: {
    icon: LinkedInIcon,
    color: 'text-[#0077B7]',
  },
  instagram: {
    icon: SiInstagram,
    color: 'text-[#E4405F]',
  },
  twitter: {
    icon: SiX, // Twitter/X uses SiX in Simple Icons
    color: 'text-[#000000] dark:text-white',
  },
  bluesky: {
    icon: SiBluesky,
    color: 'text-[#00A8E8]',
  },
  threads: {
    icon: SiThreads,
    color: 'text-[#000000] dark:text-white',
  },
  whatsapp: {
    icon: SiWhatsapp,
    color: 'text-[#25D366]',
  },
  website: {
    icon: Globe,
    color: 'text-gray-600',
  },
};

/**
 * Display order for social icons in the UI.
 */
export const SOCIAL_DISPLAY_ORDER = {
  linkedin: 1,
  twitter: 2,
  bluesky: 3,
  threads: 4,
  instagram: 5,
  facebook: 6,
  whatsapp: 7,
  website: 8,
  sportlink: 9,
  freescout: 10,
};

/**
 * Get the icon component for a social media type.
 *
 * @param {string} type - Social media type
 * @returns {React.ComponentType} Icon component
 */
export function getSocialIcon(type) {
  return SOCIAL_CONFIG[type]?.icon || Globe;
}

/**
 * Get the color class for a social media type.
 *
 * @param {string} type - Social media type
 * @returns {string} Tailwind color classes
 */
export function getSocialIconColor(type) {
  return SOCIAL_CONFIG[type]?.color || 'text-gray-600';
}

/**
 * Sort social links by display order.
 *
 * @param {Array} links - Array of contact objects with contact_type
 * @returns {Array} Sorted array
 */
export function sortSocialLinks(links) {
  return [...links].sort((a, b) => {
    const orderA = SOCIAL_DISPLAY_ORDER[a.contact_type] || 99;
    const orderB = SOCIAL_DISPLAY_ORDER[b.contact_type] || 99;
    return orderA - orderB;
  });
}

/**
 * Social media types that should be displayed as social icons (not contact info).
 */
export const SOCIAL_TYPES = ['facebook', 'linkedin', 'instagram', 'twitter', 'bluesky', 'threads', 'website'];
