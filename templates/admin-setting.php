<?php
// Returns the values that appear more than once (ignoring blanks), used to
// stop two locations from silently sharing the same API key.
function rmfl_find_duplicate_values($values) {
    $seen = [];
    $duplicates = [];
    foreach ($values as $value) {
        $value = trim($value);
        if ($value === '') continue;
        if (isset($seen[$value])) {
            $duplicates[$value] = true;
        }
        $seen[$value] = true;
    }
    return array_keys($duplicates);
}

$validation_error = '';

// Handle form submission and save data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Build the dynamic location list (each location has its own PBX key and Repair Desk key)
    $posted_pbx_keys = isset($_POST['location_pbx_key']) && is_array($_POST['location_pbx_key']) ? $_POST['location_pbx_key'] : [];
    $posted_rd_keys  = isset($_POST['location_rd_key']) && is_array($_POST['location_rd_key']) ? $_POST['location_rd_key'] : [];
    $posted_rl_keys  = isset($_POST['location_rl_key']) && is_array($_POST['location_rl_key']) ? $_POST['location_rl_key'] : [];
    $posted_labels   = isset($_POST['location_label']) && is_array($_POST['location_label']) ? $_POST['location_label'] : [];
    $location_count = max(count($posted_pbx_keys), count($posted_rd_keys), count($posted_rl_keys));

    $locations_to_save = [];
    for ($i = 0; $i < $location_count; $i++) {
        $locations_to_save[] = [
            'pbx'   => sanitize_text_field($posted_pbx_keys[$i] ?? ''),
            'rd'    => sanitize_text_field($posted_rd_keys[$i] ?? ''),
            'rl'    => sanitize_text_field($posted_rl_keys[$i] ?? ''),
            'label' => sanitize_text_field($posted_labels[$i] ?? ''),
        ];
    }
    // Always keep at least one (possibly empty) location
    if (empty($locations_to_save)) {
        $locations_to_save[] = ['pbx' => '', 'rd' => '', 'rl' => '', 'label' => ''];
    }

    // Each location must use its own API key, otherwise leads would get routed to the wrong place
    $duplicate_pbx = rmfl_find_duplicate_values(wp_list_pluck($locations_to_save, 'pbx'));
    $duplicate_rd  = rmfl_find_duplicate_values(wp_list_pluck($locations_to_save, 'rd'));
    $duplicate_rl  = rmfl_find_duplicate_values(wp_list_pluck($locations_to_save, 'rl'));
    if (!empty($duplicate_pbx) || !empty($duplicate_rd) || !empty($duplicate_rl)) {
        $kinds = [];
        if (!empty($duplicate_pbx)) $kinds[] = 'PBX API key';
        if (!empty($duplicate_rd)) $kinds[] = 'Repair Desk API key';
        if (!empty($duplicate_rl)) $kinds[] = 'RingoLeads API key';
        $validation_error = 'Each location must use a unique ' . implode(' and ', $kinds) . '. The same key is used by more than one location below, so settings were not saved.';
        // Keep what was submitted so the user can see and fix it, instead of reverting to the saved values
        $locations = $locations_to_save;
    }

    if (!$validation_error) {
        update_option('pbx_enabled', isset($_POST['pbx_enabled']) ? '1' : '');
        update_option('repair_desk_enabled', isset($_POST['repair_desk_enabled']) ? '1' : '');
        update_option('ringoleads_enabled', isset($_POST['ringoleads_enabled']) ? '1' : '');
        update_option('error_api_email', sanitize_text_field($_POST['error_api_email'] ?? ''));
        update_option('rmfl_locations', wp_json_encode($locations_to_save));

        if (isset($_POST['pbx_referral']) && is_array($_POST['pbx_referral'])) {
            $referrals = array_map('sanitize_text_field', $_POST['pbx_referral']);
            update_option('pbx_referral', json_encode($referrals)); // Save as JSON
        }
        if (isset($_POST['repair_desk_referral']) && is_array($_POST['repair_desk_referral'])) {
            $rd_referrals = array_map('sanitize_text_field', $_POST['repair_desk_referral']);
            update_option('repair_desk_referral', json_encode($rd_referrals)); // Save as JSON
        }
        if (isset($_POST['ringoleads_referral']) && is_array($_POST['ringoleads_referral'])) {
            $rl_referrals = array_map('sanitize_text_field', $_POST['ringoleads_referral']);
            update_option('ringoleads_referral', json_encode($rl_referrals)); // Save as JSON
        }

        echo '<div class="notice notice-success is-dismissible rmfl-saved-notice"><p>Settings saved.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html($validation_error) . '</p></div>';
    }
}

// Register settings
function my_plugin_register_settings() {
    register_setting('form_plugins_options_group', 'pbx_enabled');
    register_setting('form_plugins_options_group', 'repair_desk_enabled');
    register_setting('form_plugins_options_group', 'ringoleads_enabled');
    register_setting('form_plugins_options_group', 'pbx_referral'); // Save dropdown selection
    register_setting('form_plugins_options_group', 'repair_desk_referral');
    register_setting('form_plugins_options_group', 'ringoleads_referral');
    register_setting('form_plugins_options_group', 'rmfl_locations');
    register_setting('form_plugins_options_group', 'error_api_email');
}
add_action('admin_init', 'my_plugin_register_settings');

// Retrieve saved settings. If this request just failed validation, re-show what was submitted
// (including the location list already set above) instead of the stale saved values, so the
// user can see and fix the duplicate rather than have it silently disappear.
if ($validation_error) {
    $pbx_enabled = isset($_POST['pbx_enabled']) ? '1' : '';
    $repair_desk_enabled = isset($_POST['repair_desk_enabled']) ? '1' : '';
    $ringoleads_enabled = isset($_POST['ringoleads_enabled']) ? '1' : '';
    $error_api_email = sanitize_text_field($_POST['error_api_email'] ?? '');
    $saved_referrals = isset($_POST['pbx_referral']) && is_array($_POST['pbx_referral']) ? array_map('sanitize_text_field', $_POST['pbx_referral']) : [];
    $repairDesk_referral = isset($_POST['repair_desk_referral']) && is_array($_POST['repair_desk_referral']) ? array_map('sanitize_text_field', $_POST['repair_desk_referral']) : [];
    $saved_ringoleads_referral = isset($_POST['ringoleads_referral']) && is_array($_POST['ringoleads_referral']) ? array_map('sanitize_text_field', $_POST['ringoleads_referral']) : [];
} else {
    $pbx_enabled = get_option('pbx_enabled', '');
    $repair_desk_enabled = get_option('repair_desk_enabled', '');
    $ringoleads_enabled = get_option('ringoleads_enabled', '');
    $error_api_email = get_option('error_api_email', '');
    $saved_referrals = json_decode(get_option('pbx_referral'), true) ?? [];
    $repairDesk_referral = json_decode(get_option('repair_desk_referral'), true) ?? [];
    $saved_ringoleads_referral = json_decode(get_option('ringoleads_referral'), true) ?? [];

    // Retrieve saved locations, migrating from the old single/dual API key options if this is the first load
    $locations = json_decode(get_option('rmfl_locations', ''), true);
    if (!is_array($locations) || empty($locations)) {
        $legacy_pbx_1 = get_option('pbx_api_key', '');
        $legacy_rd_1  = get_option('repair_desk_api_key', '');
        $legacy_pbx_2 = get_option('pbx_api_key_2', '');
        $legacy_rd_2  = get_option('repair_desk_api_key_2', '');

        $locations = [['pbx' => $legacy_pbx_1, 'rd' => $legacy_rd_1, 'rl' => '', 'label' => '']];
        if ($legacy_pbx_2 !== '' || $legacy_rd_2 !== '') {
            $locations[] = ['pbx' => $legacy_pbx_2, 'rd' => $legacy_rd_2, 'rl' => '', 'label' => ''];
        }
    }
}

function render_referral_dropdown($selected_value = '') {    
    $referral_options = json_decode(get_option('pbx_referral_opt'), true) ?? [];
    $dropdown = '<select name="pbx_referral[]" class="referralDropdown">';
    $dropdown .= '<option value="">Select a referral…</option>';
    foreach ($referral_options as $value) {        
        $dropdown .= '<option value="' . $value['val'] . '" ' . selected($selected_value, $value['val'], false) . '>' . $value['text'] . '</option>';    
    }
    $dropdown .= '</select>';
    return $dropdown;
}

function rmfl_ringoleads_sources() {
    return [
        'facebook'     => 'Facebook',
        'facebook_ads' => 'Facebook Ads',
        'google_maps'  => 'Google Maps',
        'website'      => 'Website',
        'google'       => 'Google',
        'instagram'    => 'Instagram',
        'google_ads'   => 'Google Ads',
        'organic'      => 'Organic',
        'calendly'     => 'Calendly',
        'sms'          => 'SMS',
        'ai'           => 'AI',
        'zapier'       => 'Zapier',
    ];
}

function render_ringoleads_referral_dropdown($selected_value = '') {
    $dropdown = '<select name="ringoleads_referral[]" class="ringoleadsReferralDropdown">';
    $dropdown .= '<option value="">Select a source…</option>';
    foreach (rmfl_ringoleads_sources() as $slug => $label) {
        $dropdown .= '<option value="' . esc_attr($slug) . '" ' . selected($selected_value, $slug, false) . '>' . esc_html($label) . '</option>';
    }
    $dropdown .= '</select>';
    return $dropdown;
}

// Renders a single "Location" card (PBX key + Repair Desk key + RingoLeads key)
function render_location_block($index, $pbx_value = '', $rd_value = '', $rl_value = '', $label_value = '') {
    $number = $index + 1;
    $remove_button = $index > 0
        ? '<button type="button" class="remove-location-btn rmfl-icon-btn" title="Remove this location" aria-label="Remove this location"><span class="dashicons dashicons-no-alt"></span></button>'
        : '';
    ob_start();
    ?>
    <div class="location-block rmfl-location-card">
        <div class="rmfl-location-head">
            <span class="rmfl-location-badge">Location <span class="location-number"><?php echo esc_html($number); ?></span></span>
            <input
                type="text"
                class="rmfl-location-label-input"
                name="location_label[]"
                placeholder="Label this location (e.g. Downtown Store), optional"
                value="<?php echo esc_attr($label_value); ?>"
            />
            <?php echo $remove_button; ?>
        </div>
        <div class="rmfl-location-fields">
            <div class="pbx-field rmfl-field">
                <label>PBX API key</label>
                <input type="text" name="location_pbx_key[]" value="<?php echo esc_attr($pbx_value); ?>" placeholder="Paste PBX API key" />
            </div>
            <div class="rd-field rmfl-field">
                <label>Repair Desk API key</label>
                <input type="text" name="location_rd_key[]" value="<?php echo esc_attr($rd_value); ?>" placeholder="Paste Repair Desk API key" />
            </div>
            <div class="rl-field rmfl-field">
                <label>RingoLeads API key</label>
                <input type="text" name="location_rl_key[]" value="<?php echo esc_attr($rl_value); ?>" placeholder="Paste RingoLeads API key" />
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<style>
    .rmfl-wrap { max-width: 900px; margin-top: 20px; }
    .rmfl-wrap * { box-sizing: border-box; }

    .rmfl-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
    }
    .rmfl-header img { width: 42px; height: 42px; border-radius: 8px; flex-shrink: 0; }
    .rmfl-header h1 { margin: 0; font-size: 22px; }
    .rmfl-header p { margin: 2px 0 0; color: #646970; font-size: 13px; }

    .rmfl-card {
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        padding: 22px 24px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .rmfl-card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 6px;
        color: #1d2327;
    }
    .rmfl-card-title .dashicons { color: #2271b1; }
    .rmfl-card-subtitle {
        margin: 0 0 18px;
        color: #646970;
        font-size: 13px;
    }

    /* Toggle switches */
    .rmfl-toggle-row { display: flex; flex-wrap: wrap; gap: 28px; }
    .rmfl-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: 500;
        color: #1d2327;
    }
    .rmfl-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
    .rmfl-toggle-slider {
        width: 38px;
        height: 21px;
        border-radius: 999px;
        background: #c3c4c7;
        position: relative;
        transition: background .15s ease;
        flex-shrink: 0;
    }
    .rmfl-toggle-slider::before {
        content: '';
        position: absolute;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        background: #fff;
        top: 2px;
        left: 2px;
        transition: transform .15s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.25);
    }
    .rmfl-toggle input:checked + .rmfl-toggle-slider { background: #2271b1; }
    .rmfl-toggle input:checked + .rmfl-toggle-slider::before { transform: translateX(17px); }
    .rmfl-toggle input:focus-visible + .rmfl-toggle-slider { outline: 2px solid #2271b1; outline-offset: 2px; }

    /* Location cards */
    #locationsContainer { display: flex; flex-direction: column; gap: 14px; margin-bottom: 14px; }
    .rmfl-location-card {
        border: 1px solid #e2e4e7;
        border-radius: 6px;
        padding: 14px 16px;
        background: #fafafa;
    }
    .rmfl-location-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }
    .rmfl-location-badge {
        display: inline-flex;
        align-items: center;
        background: #2271b1;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 999px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .rmfl-location-label-input {
        flex: 1;
        border: 1px dashed #c3c4c7;
        background: transparent;
        padding: 5px 8px;
        font-size: 13px;
        border-radius: 4px;
        min-width: 120px;
    }
    .rmfl-location-label-input:focus { border-style: solid; border-color: #2271b1; }
    .rmfl-icon-btn {
        border: none;
        background: transparent;
        cursor: pointer;
        color: #a7161d;
        padding: 4px;
        border-radius: 4px;
        display: inline-flex;
        line-height: 1;
    }
    .rmfl-icon-btn:hover { background: #fbeaea; }
    .rmfl-location-fields {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 860px) {
        .rmfl-location-fields { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
        .rmfl-location-fields { grid-template-columns: 1fr; }
    }
    .rmfl-field label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #50575e;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .rmfl-field input[type="text"] { width: 100%; }

    #add_location_btn { display: inline-flex; align-items: center; gap: 6px; line-height: normal; }
    #add_location_btn .dashicons { width: 16px; height: 16px; font-size: 16px; line-height: 16px; }

    /* Referral rows */
    .rmfl-referral-hint {
        background: #f0f6fc;
        border: 1px solid #c5d9ed;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 12.5px;
        color: #1d2327;
        margin-bottom: 14px;
    }
    .rmfl-referral-hint code {
        background: #fff;
        border: 1px solid #dcdcde;
        padding: 1px 5px;
        border-radius: 3px;
    }
    .referral-container,
    .repair-referral-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        background: #fafafa;
        border: 1px solid #e2e4e7;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }
    .referral-container select,
    .repair-referral-container input[type="text"] {
        min-width: 200px;
    }
    .class-display,
    .repair-desk-class-display {
        font-size: 12px;
        color: #50575e;
        flex-basis: 100%;
    }
    .remove-btn, .rd-remove-btn {
        border: none;
        background: #fff;
        border: 1px solid #dcdcde;
        color: #a7161d;
        width: 26px;
        height: 26px;
        border-radius: 4px;
        cursor: pointer;
        line-height: 1;
        flex-shrink: 0;
    }
    .remove-btn:hover, .rd-remove-btn:hover { background: #fbeaea; }

    .rmfl-error-email-field { max-width: 360px; }
    .rmfl-error-email-field label { display:block; font-size: 12px; font-weight: 600; color: #50575e; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .02em; }
    .rmfl-error-email-field input { width: 100%; }

    .rmfl-submit-row { margin-top: 6px; }
</style>

<div class="wrap rmfl-wrap">

    <div class="rmfl-header">
        <img src="<?php echo esc_url(RMFL_PLUGIN_URI . 'assets/logo/ringo-media-profile.png'); ?>" alt="" />
        <div>
            <h1>Leads Destinations</h1>
            <p>Route form submissions to PBX, Repair Desk, and RingoLeads, by location.</p>
        </div>
    </div>

    <form method="post" action="">
        <?php
        settings_fields('form_plugins_options_group');
        do_settings_sections('form-plugins-settings');
        ?>

        <div class="rmfl-card">
            <p class="rmfl-card-title"><span class="dashicons dashicons-admin-plugins"></span> Integrations</p>
            <p class="rmfl-card-subtitle">Turn on the systems you want leads delivered to.</p>
            <div class="rmfl-toggle-row">
                <label class="rmfl-toggle">
                    <input type="checkbox" name="pbx_enabled" id="pbx_enabled" value="1" <?php checked($pbx_enabled, '1'); ?> />
                    <span class="rmfl-toggle-slider"></span>
                    <span>Enable PBX</span>
                </label>
                <label class="rmfl-toggle">
                    <input type="checkbox" name="repair_desk_enabled" id="repair_desk_enabled" value="1" <?php checked($repair_desk_enabled, '1'); ?> />
                    <span class="rmfl-toggle-slider"></span>
                    <span>Enable Repair Desk</span>
                </label>
                <label class="rmfl-toggle">
                    <input type="checkbox" name="ringoleads_enabled" id="ringoleads_enabled" value="1" <?php checked($ringoleads_enabled, '1'); ?> />
                    <span class="rmfl-toggle-slider"></span>
                    <span>Enable RingoLeads</span>
                </label>
            </div>
        </div>

        <div class="rmfl-card">
            <p class="rmfl-card-title"><span class="dashicons dashicons-location"></span> Locations</p>
            <p class="rmfl-card-subtitle">Each location has its own PBX, Repair Desk, and RingoLeads API key. Location 1 is used by default, add more if you have additional branches.</p>

            <div id="locationsContainer">
                <?php foreach ($locations as $i => $loc) {
                    echo render_location_block($i, $loc['pbx'] ?? '', $loc['rd'] ?? '', $loc['rl'] ?? '', $loc['label'] ?? '');
                } ?>
            </div>
            <button type="button" id="add_location_btn" class="button">
                <span class="dashicons dashicons-plus-alt2"></span> Add location
            </button>

            <!-- Hidden template used by JS to add new locations -->
            <script type="text/template" id="location-template"><?php echo render_location_block(1, '', '', '', ''); ?></script>
        </div>

        <div class="rmfl-card" id="pbx_referrals_wrap">
            <p class="rmfl-card-title"><span class="dashicons dashicons-chart-line"></span> PBX referral sources</p>
            <button type="button" id="show_referrals_btn" class="button" style="margin-bottom:14px;">Fetch referrals from PBX</button>
            <div class="rmfl-referral-hint">
                Add the matching class to your form, using the referral's PBX ID shown below each selection (e.g. <code>form_submit_request-11</code>). Location 1 uses no number suffix; Location 2 uses <code>_2</code>, Location 3 uses <code>_3</code>, and so on, matching each location's position above.
            </div>
            <div id="dropdownContainer">
                <?php
                if (!empty($saved_referrals)) {
                    foreach ($saved_referrals as $referral) {
                        $sanitized_referral = sanitize_text_field($referral);
                    echo '<div class="referral-container">'
                    . render_referral_dropdown($sanitized_referral) .
                    '<button type="button" class="remove-btn" title="Remove">×</button>
                     <span class="class-display"></span>
                 </div>';
                    }
                } else {
                    // Default dropdown if no values are saved
                    echo '<div class="referral-container">'
                    . render_referral_dropdown('') .
                    '<button type="button" class="remove-btn" title="Remove">×</button>
                     <span class="class-display"></span>
                 </div>';
                }
                ?>
            </div>
            <button type="button" class="add-btn button">+ Add referral</button>
        </div>

        <div class="rmfl-card" id="repair_desk_referrals_wrap">
            <p class="rmfl-card-title"><span class="dashicons dashicons-hammer"></span> Repair Desk referral sources</p>
            <div class="rmfl-referral-hint">
                Add the matching class to your form. Location 1 uses no number suffix (e.g. <code>rd_form_request-google_ads</code>); Location 2 uses <code>_2</code>, Location 3 uses <code>_3</code>, and so on, matching each location's position above.
            </div>
            <div id="repairDropdownContainer">
                <?php
                if (!empty($repairDesk_referral)) {
                    foreach ($repairDesk_referral as $referral) {
                        $sanitized_referral = sanitize_text_field($referral);
                        echo '<div class="repair-referral-container">
                            <input placeholder="e.g. Google Ads" type="text" value="' . esc_attr($sanitized_referral)  . '" name="repair_desk_referral[]" class="rdReferralInput"/>
                            <button type="button" class="remove-btn" title="Remove">×</button>
                            <span class="repair-desk-class-display"></span>
                        </div>';
                    }
                } else {
                    // Default dropdown if no values are saved
                    echo '<div class="repair-referral-container">
                            <input placeholder="e.g. Google Ads" type="text" value="" name="repair_desk_referral[]" class="rdReferralInput"/>
                            <button type="button" class="rd-remove-btn" title="Remove">×</button>
                            <span class="repair-desk-class-display"></span>
                        </div>';
                }
                ?>
            </div>
            <button type="button" class="rd-add-btn button">+ Add referral</button>
        </div>

        <div class="rmfl-card" id="ringoleads_wrap">
            <p class="rmfl-card-title"><span class="dashicons dashicons-megaphone"></span> RingoLeads source classes</p>
            <p class="rmfl-card-subtitle">Use one CSS class on the form. The class selects both the RingoLeads source and the location/API key.</p>
            <div class="rmfl-referral-hint">
                Location 1 has no numeric suffix. Location 2 adds <code>_2</code>, Location 3 adds <code>_3</code>, and so on. Example: <code>rl_form_request_google_ads</code> for Location 1 or <code>rl_form_request_google_ads_2</code> for Location 2.
            </div>
            <div id="ringoleadsDropdownContainer">
                <?php
                if (!empty($saved_ringoleads_referral)) {
                    foreach ($saved_ringoleads_referral as $referral) {
                        $sanitized_referral = sanitize_text_field($referral);
                        echo '<div class="referral-container">'
                        . render_ringoleads_referral_dropdown($sanitized_referral) .
                        '<button type="button" class="remove-btn" title="Remove">×</button>
                         <span class="ringoleads-source-class-display"></span>
                     </div>';
                    }
                } else {
                    // Default dropdown if no values are saved
                    echo '<div class="referral-container">'
                    . render_ringoleads_referral_dropdown('') .
                    '<button type="button" class="remove-btn" title="Remove">×</button>
                     <span class="ringoleads-source-class-display"></span>
                 </div>';
                }
                ?>
            </div>
            <button type="button" class="ringoleads-add-btn button">+ Add referral</button>

            <!-- Hidden template used by JS to add new RingoLeads referral rows -->
            <script type="text/template" id="ringoleads-referral-template"><div class="referral-container"><?php echo render_ringoleads_referral_dropdown(''); ?><button type="button" class="remove-btn" title="Remove">×</button><span class="ringoleads-source-class-display"></span></div></script>

            <p class="rmfl-card-subtitle" style="margin-top:12px;">The current page URL is sent separately as <code>source_url</code>. Custom form fields still pass through to RingoLeads as qualifying answers.</p>
        </div>

        <div class="rmfl-card">
            <p class="rmfl-card-title"><span class="dashicons dashicons-email"></span> Failure notifications</p>
            <p class="rmfl-card-subtitle">If a lead fails to send to PBX or Repair Desk, we'll email this address.</p>
            <div class="rmfl-error-email-field">
                <label for="error_api_email">Notification email</label>
                <input type="email" id="error_api_email" name="error_api_email" value="<?php echo esc_attr($error_api_email); ?>" placeholder="you@example.com" />
            </div>
        </div>

        <div class="rmfl-submit-row">
            <?php submit_button('Save changes'); ?>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
jQuery(document).ready(function ($) {

    function locationCount() {
        return $('#locationsContainer .location-block').length;
    }

    // Build the "add this class" hint text across every currently-configured location
    function buildClassHint(base, source) {
        if (!source) return '';
        const n = locationCount();
        const classes = [];
        for (let i = 1; i <= n; i++) {
            const suffix = i === 1 ? '' : '_' + i;
            classes.push(base + suffix + '-' + source);
        }
        return 'Add <code>' + classes.join(' , ') + '</code> class to your form.';
    }

    function buildRingoLeadsClassHint(source) {
        if (!source) return '';
        const n = locationCount();
        const classes = [];
        for (let i = 1; i <= n; i++) {
            const suffix = i === 1 ? '' : '_' + i;
            classes.push('rl_form_request_' + source + suffix);
        }
        return '<code>' + classes.join('</code> , <code>') + '</code>';
    }

    function renumberLocations() {
        $('#locationsContainer .location-block').each(function (i) {
            $(this).find('.location-number').text(i + 1);
        });
        // First location can never be removed
        $('#locationsContainer .location-block').each(function (i) {
            const $btn = $(this).find('.remove-location-btn');
            if (i === 0) {
                $btn.remove();
            }
        });
        refreshAllHints();
    }

    function refreshAllHints() {
        $('.referralDropdown').each(function () {
            const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
            $(this).siblings('.class-display').html(buildClassHint('form_submit_request', source));
        });
        $('.rdReferralInput').each(function () {
            const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
            $(this).siblings('.repair-desk-class-display').html(buildClassHint('rd_form_request', source));
        });
        $('.ringoleadsReferralDropdown').each(function () {
            const source = $(this).val() || '';
            $(this).siblings('.ringoleads-source-class-display').html(buildRingoLeadsClassHint(source));
        });
    }

    // Add / remove locations
    $('#add_location_btn').on('click', function () {
        const template = $('#location-template').html();
        const $newBlock = $(template);
        $('#locationsContainer').append($newBlock);
        renumberLocations();
    });

    $(document).on('click', '.remove-location-btn', function () {
        $(this).closest('.location-block').remove();
        renumberLocations();
    });

    // Show/Hide API key fields (across all locations) based on the enable checkboxes
    $('#pbx_enabled').on('change', function () {
        $('#pbx_referrals_wrap').toggle(this.checked);
    }).trigger('change');

    $('#repair_desk_enabled').on('change', function () {
        $('#repair_desk_referrals_wrap').toggle(this.checked);
    }).trigger('change');

    $('#ringoleads_enabled').on('change', function () {
        $('#ringoleads_wrap').toggle(this.checked);
    }).trigger('change');

    // Referral rows
    $(document).on('click', '.remove-btn', function () {
        $(this).parent().remove();
    });

    $(document).on('change', '.referralDropdown', function () {
        const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
        $(this).siblings('.class-display').html(buildClassHint('form_submit_request', source));
    });

    ////////////////////// for ringoleads

    $('.ringoleads-add-btn').on('click', function () {
        const template = $('#ringoleads-referral-template').html();
        $('#ringoleadsDropdownContainer').append(template);
    });

    $(document).on('change', '.ringoleadsReferralDropdown', function () {
        const source = $(this).val() || '';
        $(this).siblings('.ringoleads-source-class-display').html(buildRingoLeadsClassHint(source));
    });

    ////////////////////// for repair desk

    $('.rd-add-btn').on('click', function () {
        $('#repairDropdownContainer').append(`
            <div class="repair-referral-container">
                <input placeholder="e.g. Google Ads" type="text" name="repair_desk_referral[]" class="rdReferralInput"/>
                <button type="button" class="rd-remove-btn" title="Remove">×</button>
                <span class="repair-desk-class-display"></span>
            </div>
        `);
    });

    $(document).on('click', '.rd-remove-btn', function () {
        $(this).parent().remove();
    });

    $(document).on('keyup', '.rdReferralInput', function () {
        const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
        $(this).siblings('.repair-desk-class-display').html(buildClassHint('rd_form_request', source));
    });

    // Initial render
    renumberLocations();

    // Auto-dismiss the "Settings saved" notice
    setTimeout(function () {
        $('.rmfl-saved-notice').fadeOut();
    }, 4000);
});
</script>
