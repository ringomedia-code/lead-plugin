<?php
namespace RMFL;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once RMFL_PLUGIN_INC . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p7\PucFactory;

class Updater {
    private $github_repo_url = 'https://github.com/ringomedia-code/lead-plugin';

    public function __construct() {
        $update_checker = PucFactory::buildUpdateChecker(
            $this->github_repo_url,
            RMFL_WP,
            'rm-form-leads'
        );

        $update_checker->getVcsApi()->enableReleaseAssets();

        // Force WordPress's own auto-update cycle (wp_maybe_auto_update(), roughly twice
        // a day) to update this plugin on every site without an admin needing to opt in
        // via the Plugins screen or click "Update Now" first.
        add_filter('auto_update_plugin', function ($should_update, $item) {
            if (isset($item->plugin) && $item->plugin === plugin_basename(RMFL_WP)) {
                return true;
            }
            return $should_update;
        }, 10, 2);
    }
}

new Updater();
