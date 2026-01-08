<?php
/**
 * CardDAV Backend
 * 
 * Performs CRUD operations on Person CPT for CardDAV sync.
 * Includes sync token support for efficient synchronization.
 * 
 * @package Caelis
 */

namespace Caelis\CardDAV;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\CardDAV\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

class CardDAVBackend extends AbstractBackend implements SyncSupport {
    
    /**
     * Option name for sync tokens
     */
    const SYNC_TOKEN_OPTION = 'prm_carddav_sync_tokens';
    
    /**
     * Option name for change log
     */
    const CHANGE_LOG_OPTION = 'prm_carddav_changes';
    
    /**
     * Get address books for a principal
     *
     * @param string $principalUri Principal URI (e.g., 'principals/username')
     * @return array Array of address books
     */
    public function getAddressBooksForUser($principalUri) {
        $parts = explode('/', $principalUri);
        $username = end($parts);
        $user = get_user_by('login', $username);
        
        if (!$user) {
            return [];
        }
        
        // Each user has one address book containing their contacts
        return [
            [
                'id' => $user->ID,
                'uri' => 'contacts',
                'principaluri' => $principalUri,
                '{DAV:}displayname' => 'Caelis Contacts',
                '{' . Plugin::NS_CARDDAV . '}addressbook-description' => 'Contacts from Caelis CRM',
                '{http://calendarserver.org/ns/}getctag' => $this->getCtag($user->ID),
                '{http://sabredav.org/ns}sync-token' => $this->getCurrentSyncToken($user->ID),
            ],
        ];
    }
    
    /**
     * Update address book properties
     *
     * @param string $addressBookId Address book ID
     * @param \Sabre\DAV\PropPatch $propPatch Property patch
     * @return void
     */
    public function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {
        // We don't support updating address book properties
    }
    
    /**
     * Create a new address book
     *
     * @param string $principalUri Principal URI
     * @param string $url Address book URL
     * @param array $properties Address book properties
     * @return void
     */
    public function createAddressBook($principalUri, $url, array $properties) {
        // Each user automatically has one address book, no need to create
    }
    
    /**
     * Delete an address book
     *
     * @param mixed $addressBookId Address book ID
     * @return void
     */
    public function deleteAddressBook($addressBookId) {
        // We don't allow deleting the address book
    }
    
    /**
     * Get all cards in an address book
     *
     * @param mixed $addressBookId Address book ID (user ID)
     * @return array Array of card data
     */
    public function getCards($addressBookId) {
        $cards = [];
        
        // Set current user for access control
        wp_set_current_user($addressBookId);
        
        // Get all persons for this user
        $persons = get_posts([
            'post_type' => 'person',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'author' => $addressBookId,
        ]);
        
        foreach ($persons as $person) {
            $vcard = \PRM_VCard_Export::generate($person);
            $etag = $this->generateEtag($person);
            
            $cards[] = [
                'id' => $person->ID,
                'uri' => $person->ID . '.vcf',
                'lastmodified' => strtotime($person->post_modified_gmt),
                'etag' => $etag,
                'size' => strlen($vcard),
            ];
        }
        
        return $cards;
    }
    
    /**
     * Get a specific card
     *
     * @param mixed $addressBookId Address book ID
     * @param string $cardUri Card URI (e.g., '123.vcf')
     * @return array|null Card data or null if not found
     */
    public function getCard($addressBookId, $cardUri) {
        $person_id = $this->getPersonIdFromUri($cardUri);
        
        if (!$person_id) {
            return null;
        }
        
        // Set current user for access control
        wp_set_current_user($addressBookId);
        
        $person = get_post($person_id);
        
        if (!$person || $person->post_type !== 'person' || $person->post_status !== 'publish') {
            return null;
        }
        
        // Verify ownership
        if ((int) $person->post_author !== (int) $addressBookId) {
            return null;
        }
        
        $vcard = \PRM_VCard_Export::generate($person);
        $etag = $this->generateEtag($person);
        
        return [
            'id' => $person->ID,
            'uri' => $cardUri,
            'lastmodified' => strtotime($person->post_modified_gmt),
            'etag' => $etag,
            'size' => strlen($vcard),
            'carddata' => $vcard,
        ];
    }
    
    /**
     * Get multiple cards
     *
     * @param mixed $addressBookId Address book ID
     * @param array $uris Array of card URIs
     * @return array Array of card data
     */
    public function getMultipleCards($addressBookId, array $uris) {
        $cards = [];
        
        foreach ($uris as $uri) {
            $card = $this->getCard($addressBookId, $uri);
            if ($card) {
                $cards[] = $card;
            }
        }
        
        return $cards;
    }
    
    /**
     * Create a new card
     *
     * @param mixed $addressBookId Address book ID
     * @param string $cardUri Card URI
     * @param string $cardData vCard data
     * @return string|null ETag of the new card
     */
    public function createCard($addressBookId, $cardUri, $cardData) {
        // Parse the vCard data
        $parsed = \PRM_VCard_Export::parse($cardData);
        
        if (empty($parsed['first_name']) && empty($parsed['last_name']) && empty($parsed['full_name'])) {
            return null;
        }
        
        // Set current user
        wp_set_current_user($addressBookId);
        
        // Determine name fields
        $first_name = $parsed['first_name'] ?: '';
        $last_name = $parsed['last_name'] ?: '';
        
        if (empty($first_name) && empty($last_name) && !empty($parsed['full_name'])) {
            // Try to split full name
            $name_parts = explode(' ', $parsed['full_name'], 2);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1] ?? '';
        }
        
        // Create the person post
        $post_id = wp_insert_post([
            'post_type' => 'person',
            'post_status' => 'publish',
            'post_author' => $addressBookId,
            'post_title' => trim($first_name . ' ' . $last_name) ?: 'Unknown',
        ]);
        
        if (is_wp_error($post_id) || !$post_id) {
            return null;
        }
        
        // Update ACF fields
        update_field('first_name', $first_name, $post_id);
        update_field('last_name', $last_name, $post_id);
        
        if (!empty($parsed['nickname'])) {
            update_field('nickname', $parsed['nickname'], $post_id);
        }
        
        if (!empty($parsed['contact_info'])) {
            update_field('contact_info', $parsed['contact_info'], $post_id);
        }
        
        if (!empty($parsed['addresses'])) {
            update_field('addresses', $parsed['addresses'], $post_id);
        }
        
        // Log the change for sync
        $this->logChange($addressBookId, $post_id, 'added');
        
        // Return the etag
        $person = get_post($post_id);
        return $this->generateEtag($person);
    }
    
    /**
     * Update an existing card
     *
     * @param mixed $addressBookId Address book ID
     * @param string $cardUri Card URI
     * @param string $cardData vCard data
     * @return string|null ETag of the updated card
     */
    public function updateCard($addressBookId, $cardUri, $cardData) {
        $person_id = $this->getPersonIdFromUri($cardUri);
        
        if (!$person_id) {
            return null;
        }
        
        // Set current user
        wp_set_current_user($addressBookId);
        
        $person = get_post($person_id);
        
        if (!$person || $person->post_type !== 'person') {
            return null;
        }
        
        // Verify ownership
        if ((int) $person->post_author !== (int) $addressBookId) {
            return null;
        }
        
        // Parse the vCard data
        $parsed = \PRM_VCard_Export::parse($cardData);
        
        // Update name fields
        $first_name = $parsed['first_name'] ?: '';
        $last_name = $parsed['last_name'] ?: '';
        
        if (empty($first_name) && empty($last_name) && !empty($parsed['full_name'])) {
            $name_parts = explode(' ', $parsed['full_name'], 2);
            $first_name = $name_parts[0];
            $last_name = $name_parts[1] ?? '';
        }
        
        // Update the post
        wp_update_post([
            'ID' => $person_id,
            'post_title' => trim($first_name . ' ' . $last_name) ?: 'Unknown',
        ]);
        
        // Update ACF fields
        update_field('first_name', $first_name, $person_id);
        update_field('last_name', $last_name, $person_id);
        
        if (isset($parsed['nickname'])) {
            update_field('nickname', $parsed['nickname'], $person_id);
        }
        
        if (isset($parsed['contact_info'])) {
            update_field('contact_info', $parsed['contact_info'], $person_id);
        }
        
        if (isset($parsed['addresses'])) {
            update_field('addresses', $parsed['addresses'], $person_id);
        }
        
        // Log the change for sync
        $this->logChange($addressBookId, $person_id, 'modified');
        
        // Return new etag
        $person = get_post($person_id);
        return $this->generateEtag($person);
    }
    
    /**
     * Delete a card
     *
     * @param mixed $addressBookId Address book ID
     * @param string $cardUri Card URI
     * @return bool True if deleted
     */
    public function deleteCard($addressBookId, $cardUri) {
        $person_id = $this->getPersonIdFromUri($cardUri);
        
        if (!$person_id) {
            return false;
        }
        
        // Set current user
        wp_set_current_user($addressBookId);
        
        $person = get_post($person_id);
        
        if (!$person || $person->post_type !== 'person') {
            return false;
        }
        
        // Verify ownership
        if ((int) $person->post_author !== (int) $addressBookId) {
            return false;
        }
        
        // Log the change before deletion
        $this->logChange($addressBookId, $person_id, 'deleted');
        
        // Delete the post (move to trash)
        $result = wp_trash_post($person_id);
        
        return $result !== false;
    }
    
    /**
     * Get changes since a sync token
     * 
     * Required for SyncSupport interface
     *
     * @param string $addressBookId Address book ID
     * @param string $syncToken Sync token to compare from
     * @param int $syncLevel Sync level (1 = infinity)
     * @param int $limit Maximum number of results
     * @return array Changes since the token
     */
    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null) {
        $result = [
            'syncToken' => $this->getCurrentSyncToken($addressBookId),
            'added' => [],
            'modified' => [],
            'deleted' => [],
        ];
        
        // If no sync token provided, return all contacts as added
        if (empty($syncToken)) {
            wp_set_current_user($addressBookId);
            
            $persons = get_posts([
                'post_type' => 'person',
                'posts_per_page' => $limit ?: -1,
                'post_status' => 'publish',
                'author' => $addressBookId,
            ]);
            
            foreach ($persons as $person) {
                $result['added'][] = $person->ID . '.vcf';
            }
            
            return $result;
        }
        
        // Get changes since the sync token
        $changes = get_option(self::CHANGE_LOG_OPTION, []);
        $user_changes = $changes[$addressBookId] ?? [];
        
        // Parse sync token to get timestamp
        $token_timestamp = $this->parseSyncToken($syncToken);
        
        $count = 0;
        foreach ($user_changes as $person_id => $change) {
            if ($limit && $count >= $limit) {
                break;
            }
            
            if ($change['timestamp'] > $token_timestamp) {
                $uri = $person_id . '.vcf';
                
                switch ($change['type']) {
                    case 'added':
                        $result['added'][] = $uri;
                        break;
                    case 'modified':
                        $result['modified'][] = $uri;
                        break;
                    case 'deleted':
                        $result['deleted'][] = $uri;
                        break;
                }
                
                $count++;
            }
        }
        
        return $result;
    }
    
    /**
     * Get current sync token for an address book
     *
     * @param int $addressBookId Address book/user ID
     * @return string Sync token
     */
    private function getCurrentSyncToken($addressBookId) {
        $tokens = get_option(self::SYNC_TOKEN_OPTION, []);
        
        if (!isset($tokens[$addressBookId])) {
            $tokens[$addressBookId] = time();
            update_option(self::SYNC_TOKEN_OPTION, $tokens);
        }
        
        return 'sync-' . $tokens[$addressBookId];
    }
    
    /**
     * Update sync token for an address book
     *
     * @param int $addressBookId Address book/user ID
     * @return void
     */
    private function updateSyncToken($addressBookId) {
        $tokens = get_option(self::SYNC_TOKEN_OPTION, []);
        $tokens[$addressBookId] = time();
        update_option(self::SYNC_TOKEN_OPTION, $tokens);
    }
    
    /**
     * Parse sync token to get timestamp
     *
     * @param string $syncToken Sync token
     * @return int Timestamp
     */
    private function parseSyncToken($syncToken) {
        if (strpos($syncToken, 'sync-') === 0) {
            return (int) substr($syncToken, 5);
        }
        return 0;
    }
    
    /**
     * Log a change for sync tracking
     *
     * @param int $addressBookId Address book/user ID
     * @param int $personId Person ID
     * @param string $type Change type (added, modified, deleted)
     * @return void
     */
    private function logChange($addressBookId, $personId, $type) {
        $changes = get_option(self::CHANGE_LOG_OPTION, []);
        
        if (!isset($changes[$addressBookId])) {
            $changes[$addressBookId] = [];
        }
        
        $changes[$addressBookId][$personId] = [
            'type' => $type,
            'timestamp' => time(),
        ];
        
        update_option(self::CHANGE_LOG_OPTION, $changes);
        $this->updateSyncToken($addressBookId);
    }
    
    /**
     * Get CTag for an address book
     * 
     * CTag changes whenever any card in the address book changes
     *
     * @param int $addressBookId Address book/user ID
     * @return string CTag
     */
    private function getCtag($addressBookId) {
        // Get the most recent modification time
        wp_set_current_user($addressBookId);
        
        $persons = get_posts([
            'post_type' => 'person',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'author' => $addressBookId,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
        
        if (!empty($persons)) {
            return md5($persons[0]->post_modified_gmt);
        }
        
        return md5('empty-' . $addressBookId);
    }
    
    /**
     * Generate ETag for a person
     *
     * @param \WP_Post $person Person post
     * @return string ETag
     */
    private function generateEtag($person) {
        return '"' . md5($person->ID . $person->post_modified_gmt) . '"';
    }
    
    /**
     * Get person ID from card URI
     *
     * @param string $cardUri Card URI (e.g., '123.vcf')
     * @return int|null Person ID or null
     */
    private function getPersonIdFromUri($cardUri) {
        if (preg_match('/^(\d+)\.vcf$/', $cardUri, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}

