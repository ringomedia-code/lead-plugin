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
    }
}

new Updater();
