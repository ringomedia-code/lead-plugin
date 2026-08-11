<?php

/**
 * CARDApi Setup
 * @package CARDApi
 * @since 1.0
 */
defined('ABSPATH') || exit;

/**
 * Settings Class
 */
final class RMFL
{

    protected static $_instance = null;

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        add_action('wp_ajax_send_form_data_to_api', [$this, 'send_form_data_to_api']);
        add_action('wp_ajax_nopriv_send_form_data_to_api', [$this, 'send_form_data_to_api']);
        // AJAX for fetching forms based on plugin
        add_action('wp_ajax_get_forms_by_plugin', [$this,'get_forms_by_plugin']);
        add_action('wp_ajax_nopriv_get_forms_by_plugin', [$this,'get_forms_by_plugin']);
        add_action('wp_ajax_fetch_referrals', [$this,'fetch_referrals_callback']);
        add_action('wp_ajax_nopriv_fetch_referrals', [$this,'fetch_referrals_callback']);
        add_action('wp_ajax_fetch_referrals_opt', [$this,'fetch_referrals_opt']);
        add_action('wp_ajax_nopriv_fetch_referrals_opt', [$this,'fetch_referrals_opt']);
        $this->includes();
    }

    public function includes()
    {
        if (defined('RMFL_PLUGIN_INC')) {
            require_once RMFL_PLUGIN_INC . 'class-add-admin-menu.php';
        }
    }

    public function enqueue_frontend_scripts()
    {
        $style_path = RMFL_PLUGIN_PATH . 'assets/css/style.css';
        $script_path = RMFL_PLUGIN_PATH . 'assets/js/js.js';

        $style_version = file_exists($style_path) ? filemtime($style_path) : '1.0.0';
        $script_version = file_exists($script_path) ? filemtime($script_path) : '1.0.0';

        wp_enqueue_style('rmfl-style', RMFL_PLUGIN_URI . 'assets/css/style.css', [], $style_version);
        wp_enqueue_script('rmfl-jquery', RMFL_PLUGIN_URI . 'assets/js/js.js', ['jquery'], $script_version, true);
        wp_enqueue_script('rmfl-admin-jquery', RMFL_PLUGIN_URI . 'assets/admin/js/js.js', ['jquery'], $script_version, true);
        wp_localize_script('rmfl-jquery', 'ajaxurl', admin_url('admin-ajax.php'));
        wp_localize_script('rmfl-admin-jquery', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    public function enqueue_admin_scripts()
    {
        $script_path = RMFL_PLUGIN_PATH . 'assets/admin/js/js.js';
        $script_version = file_exists($script_path) ? filemtime($script_path) : '1.0.0';
        wp_enqueue_script('rmfl-admin-jquery', RMFL_PLUGIN_URI . 'assets/admin/js/js.js', ['jquery'], $script_version, true);
        wp_localize_script('rmfl-admin-jquery', 'ajaxurl', admin_url('admin-ajax.php'));
        // Separate admin scripts if necessary        
    }

    function send_form_data_to_api() {
        // Retrieve settings from the database
        $pbx_enabled = get_option('pbx_enabled', '');
        $repair_desk_enabled = get_option('repair_desk_enabled', '');
        $ringoleads_enabled = get_option('ringoleads_enabled', '');

        // Sanitize and validate input data
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $formNumber = isset($_POST['formNumber']) ? sanitize_text_field($_POST['formNumber']) : '';
        $apiName = isset($_POST['api']) ? sanitize_text_field($_POST['api']) : '';
        $formClassType = isset($_POST['formClassType']) ? sanitize_text_field($_POST['formClassType']) : '';
        $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'wordpress';

        // Keep the original lead message safe. PBX/Repair Desk blocks below reuse the
        // $message variable to hold their own status text, so anything running after them
        // (RingoLeads) needs its own untouched copy of what the visitor actually typed.
        $lead_message = $message;

        // Any custom form fields the site sent along (e.g. "service", "timeframe") for
        // pass-through to RingoLeads, which stores unrecognised fields verbatim.
        $extra_fields = [];
        if (isset($_POST['extra_fields'])) {
            $decoded_extra = json_decode(wp_unslash($_POST['extra_fields']), true);
            if (is_array($decoded_extra)) {
                foreach ($decoded_extra as $extra_key => $extra_value) {
                    $clean_key = sanitize_key($extra_key);
                    if ($clean_key === '' || !is_scalar($extra_value)) {
                        continue;
                    }
                    $extra_fields[$clean_key] = sanitize_text_field($extra_value);
                }
            }
        }

        // Check for required fields
        if (!$name || !$email || !$message) {
            wp_send_json_error(['message' => 'Required fields are missing.']);
        }

        // Work out which location this submission belongs to from its form class
        // (e.g. 'form_submit_request' => location 1, 'form_submit_request_3' => location 3)
        $location_index = 1;
        if (preg_match('/_(\d+)$/', $formClassType, $matches)) {
            $location_index = max(1, (int) $matches[1]);
        }

        $locations = json_decode(get_option('rmfl_locations', ''), true);
        if (!is_array($locations) || empty($locations)) {
            $locations = [['pbx' => get_option('pbx_api_key', ''), 'rd' => get_option('repair_desk_api_key', ''), 'rl' => '']];
        }
        $location = $locations[$location_index - 1] ?? $locations[0] ?? ['pbx' => '', 'rd' => '', 'rl' => ''];
        $current_pbx_key = $location['pbx'] ?? '';
        $current_rd_key = $location['rd'] ?? '';
        $current_rl_key = $location['rl'] ?? '';

        // Handle PBX API request
        if ($pbx_enabled && $apiName === 'PBX') {
            $pbx_url = 'https://app-api.ringopbx.com/api/Leads/AddFormLead';
            $pbx_data = [
                "name" => $name,
                "phonecode" => "1",
                "phonenumber" => $phone,
                "email" => $email,
                "notes" => $message,
                "leadCategoryId" => 1,
                "leadStatusId" => 1,
                "referralId" => $formNumber,
                "dueDate" => gmdate('Y-m-d\TH:i:s\Z')
            ];

            $response = wp_remote_post($pbx_url, [
                'headers' => [
                    'X-API-Key' => $current_pbx_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($pbx_data),
                'method' => 'POST',
                'timeout' => 30,
            ]);

            // Handle PBX API response
            if (is_wp_error($response)) {
                $status = 'error';
                $message = $response->get_error_message();
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $status = (isset($body['statusCode']) && $body['statusCode'] === 200) ? 'success' : 'error';
                $message = $body['message'] ?? 'Unknown error';
            }
            $response_body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $this->save_api_response('PBX', $status, $name, $phone, $email, $message, $response_body);
        }

        // Handle RepairDesk API request
        if ($repair_desk_enabled && $apiName === 'RepairDesk') {
            $repair_desk_url = 'https://rdi-01.ringopbx.com:5006/CreateAppointment';
            $repair_desk_data = [
                "apiKey" => $current_rd_key,
                "fname" => $name,
                "lname" => "",
                "email" => $email,
                "mobile" => $phone,
                "referredBy" => $formNumber,
                "customerNotes" => $message,
            ];

            $response = wp_remote_post($repair_desk_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($repair_desk_data),
                'method' => 'POST',
                'timeout' => 30,
            ]);

            // Handle RepairDesk API response
            if (is_wp_error($response)) {
                $status = 'error';
                $message = $response->get_error_message();
            } else {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $status = (isset($body['statusCode']) && $body['statusCode'] === 200) ? 'success' : 'error';
                $message = $body['message'] ?? 'Unknown error';
            }
            $response_body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $this->save_api_response('Repair Desk', $status, $name, $phone, $email, $message, $response_body);
        }

        // Handle RingoLeads inbound lead API request
        if ($ringoleads_enabled && $apiName === 'RingoLeads') {
            $ringoleads_url = 'https://app.ringoleads.com/api/inbound/lead';
            $ringoleads_data = [
                'name'       => $name,
                'phone'      => $phone,
                'email'      => $email,
                'message'    => $lead_message,
                'source_url' => $source_url,
                'source'     => $source ?: 'wordpress',
            ];
            // Any custom form fields pass straight through as their own top-level keys,
            // without overwriting the fields already set above.
            foreach ($extra_fields as $extra_key => $extra_value) {
                if (!isset($ringoleads_data[$extra_key])) {
                    $ringoleads_data[$extra_key] = $extra_value;
                }
            }

            $response = wp_remote_post($ringoleads_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $current_rl_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode($ringoleads_data),
                'method' => 'POST',
                'timeout' => 30,
            ]);

            // Handle RingoLeads API response
            if (is_wp_error($response)) {
                $status = 'error';
                $message = $response->get_error_message();
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $status = ($response_code === 200 && !empty($body['ok'])) ? 'success' : 'error';
                $message = $status === 'success' ? 'Lead delivered.' : ($body['error'] ?? 'Unknown error');
            }
            $response_body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $this->save_api_response('RingoLeads', $status, $name, $phone, $email, $message, $response_body);
        }

        // Send final response to the client
        if (isset($status) && $status === 'success') {
            wp_send_json_success(['message' => 'Data sent successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to send data to API.']);
        }
    }

    // Save API response history
    public function save_api_response($api_name, $status, $name, $phone, $email, $message, $response_body) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'api_response_history';

        $wpdb->insert($table_name, [
            'api_name' => $api_name,
            'status' => $status,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => $email,
            'message' => $message,
            'response_body' => $response_body,
        ]);
        $current_url = esc_url(admin_url('admin.php?page=api-history'));
        // Send email if status is error
        if ($status === 'error') {
            $to = get_option('error_api_email', '');
            $subject = 'API Error Notification: ' . $api_name;
            $message = sprintf(
                "Lead was not sent to %s\nCustomer Name: %s\nCustomer Email: %s\nCustomer Phone: %s\nMessage: %s\nResponse: %s\nCheck Here: %s",
                $api_name,
                $name,
                $email,
                $phone,
                $message,
                $response_body,
                $current_url
            );
            // Send email
            $mail_sent = wp_mail($to, $subject, $message);
            // Optional: Log if email fails (debugging)
            if ( ! $mail_sent ) {
                error_log('Failed to send API error email to: ' . $to);
            }
        }
    }

    function fetch_referrals_callback() {

    // Sanitize API key input
    $apiKey = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

    // Validate API key
    if (empty($apiKey)) {
        wp_send_json_error(['message' => 'API key is required.']);
    }

    // API request
    $response = wp_remote_get('https://app-api.ringopbx.com/api/Referrals/GetAPIReferrals?pageNumber=1&pageSize=100', [
        'headers' => [
            'X-API-Key' => $apiKey
        ],
        'timeout' => 30,
    ]);

    // Handle errors
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to fetch referrals.']);
    }

    // Retrieve and decode response body
    $body = wp_remote_retrieve_body($response);
    $data_json = json_decode($body);

    if (!$data_json || empty($data_json->data)) {
        wp_send_json_error(['message' => 'Invalid or empty response from API.']);
    }

    // Process data
    $referrals = [];
    foreach ($data_json->data as $data) {
        $referrals[] = [
            'text' => sanitize_text_field($data->name),
            'val'  => sanitize_text_field($data->id),
        ];
    }

    // Save as JSON in the options table
    update_option('pbx_referral_opt', wp_json_encode($referrals));

    // Send success response
    wp_send_json_success(['message' => 'Referrals updated successfully.']);
}


    function fetch_referrals_opt() {    
        $referral_options = json_decode(get_option('pbx_referral_opt'), true) ?? [];
        $dropdown = '<select name="pbx_referral[]" class="referralDropdown">';
        $dropdown .= '<option value="">Select a Referral</option>';        
        foreach ($referral_options as $value) {        
            $dropdown .= '<option value="' . $value['val'] . '">' . $value['text'] . '</option>';    
        }
        $dropdown .= '</select>';
        echo $dropdown;
        wp_die();
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
}