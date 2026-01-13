<?php
/**
 * Abstract Base REST API Class
 *
 * Provides shared infrastructure for domain-specific REST API classes.
 * Contains common permission checks and response formatting methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class PRM_REST_Base {

    /**
     * Constructor
     *
     * Child classes should call parent::__construct() and register their routes.
     */
    public function __construct() {
        // Base constructor is intentionally empty.
        // Child classes register their own routes via rest_api_init hook.
    }

    /**
     * Check if user is logged in and approved
     *
     * Permission callback for endpoints requiring an approved user.
     * Admins are always considered approved.
     *
     * @return bool True if user is logged in and approved, false otherwise.
     */
    public function check_user_approved() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        // Admins are always approved
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Check if user is approved
        return PRM_User_Roles::is_user_approved($user_id);
    }

    /**
     * Check if user is admin
     *
     * Permission callback for admin-only endpoints.
     *
     * @return bool True if user has manage_options capability.
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Check if user can access a person
     *
     * Permission callback for person-specific endpoints.
     * Verifies user is approved and owns the person post.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user can access the person, false otherwise.
     */
    public function check_person_access($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }

        $person_id = $request->get_param('person_id');
        $access_control = new PRM_Access_Control();

        return $access_control->user_can_access_post($person_id);
    }

    /**
     * Check if user can edit a person
     *
     * Permission callback for person edit endpoints.
     * Verifies user is approved and has edit capability for the person post.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user can edit the person, false otherwise.
     */
    public function check_person_edit_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }

        $person_id = $request->get_param('person_id');
        $person = get_post($person_id);

        if (!$person || $person->post_type !== 'person') {
            return false;
        }

        // Check if user can edit this person
        return current_user_can('edit_post', $person_id);
    }

    /**
     * Check if user can edit a company
     *
     * Permission callback for company edit endpoints.
     * Verifies user is approved and has edit capability for the company post.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if user can edit the company, false otherwise.
     */
    public function check_company_edit_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }

        // Check approval first
        if (!$this->check_user_approved()) {
            return false;
        }

        $company_id = $request->get_param('company_id');
        $company = get_post($company_id);

        if (!$company || $company->post_type !== 'company') {
            return false;
        }

        // Check if user can edit this company
        return current_user_can('edit_post', $company_id);
    }

    /**
     * Format person for summary response
     *
     * Returns a minimal representation of a person for list views and relationships.
     *
     * @param WP_Post $post The person post object.
     * @return array Formatted person data.
     */
    protected function format_person_summary($post) {
        return [
            'id'          => $post->ID,
            'name'        => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'first_name'  => get_field('first_name', $post->ID),
            'last_name'   => get_field('last_name', $post->ID),
            'thumbnail'   => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'is_favorite' => (bool) get_field('is_favorite', $post->ID),
            'labels'      => wp_get_post_terms($post->ID, 'person_label', ['fields' => 'names']),
        ];
    }

    /**
     * Format company for summary response
     *
     * Returns a minimal representation of a company for list views and relationships.
     *
     * @param WP_Post $post The company post object.
     * @return array Formatted company data.
     */
    protected function format_company_summary($post) {
        return [
            'id'        => $post->ID,
            'name'      => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            'website'   => get_field('website', $post->ID),
            'labels'    => wp_get_post_terms($post->ID, 'company_label', ['fields' => 'names']),
        ];
    }

    /**
     * Format date for response
     *
     * Returns a representation of an important_date post including related people.
     *
     * @param WP_Post $post The important_date post object.
     * @return array Formatted date data.
     */
    protected function format_date($post) {
        $related_people = get_field('related_people', $post->ID) ?: [];
        $people_names = [];

        foreach ($related_people as $person) {
            $person_id = is_object($person) ? $person->ID : $person;
            $people_names[] = [
                'id'   => $person_id,
                'name' => html_entity_decode(get_the_title($person_id), ENT_QUOTES, 'UTF-8'),
            ];
        }

        return [
            'id'                   => $post->ID,
            'title'                => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
            'date_value'           => get_field('date_value', $post->ID),
            'is_recurring'         => (bool) get_field('is_recurring', $post->ID),
            'year_unknown'         => (bool) get_field('year_unknown', $post->ID),
            'date_type'            => wp_get_post_terms($post->ID, 'date_type', ['fields' => 'names']),
            'related_people'       => $people_names,
        ];
    }
}
