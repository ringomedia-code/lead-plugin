<?php

function rm_form_leads_menu_page()
{
    add_menu_page(
        __('Ringo One', 'RMFormLeads'),
        'Ringo One',
        'manage_options',
        'rm-form-leads',
        'rmfl_menu_func',
        'dashicons-index-card',
        6
    );
    add_submenu_page('rm-form-leads', 'API Response History', 'API History', 'manage_options', 'api-history', 'render_api_history_page');
}
add_action('admin_menu', 'rm_form_leads_menu_page');

function rmfl_menu_func()
{    
    include RMFL_PLUGIN_TEMP . 'admin-setting.php';    
}
function render_api_history_page()
{    
    include RMFL_PLUGIN_TEMP . 'api-history-page.php';    
}


function rmfl_custom_menu_icon_css() {
    ?>
    <style>
        #adminmenu .toplevel_page_rm-form-leads .wp-menu-image {
            background: url('/wp-content/plugins/rm-form-leads/assets/logo/ringo-media-profile.png') no-repeat center center !important;
            background-size: contain !important;
        }
        #adminmenu .toplevel_page_rm-form-leads .wp-menu-image:before {
            content: '' !important;
        }
    </style>
    <?php
}
add_action('admin_head', 'rmfl_custom_menu_icon_css');


