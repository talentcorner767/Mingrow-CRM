<?php

defined('PLUGINPATH') || exit('No direct script access allowed');

/*
  Plugin Name: Flexible Backup & Restore Module
  Plugin URL: https://codecanyon.net/item/flexible-backup-restore-module-for-rise-crm/48619366
  Description: A comprehensive backup and restore module with automatic scheduling feature
  Version: 1.1.0
  Requires at least: 3.5.2
 */
define('BACKUP_FOLDER', FCPATH.'flexiblebackup'.'/');

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/Libraries/Apiinit.php';
require_once __DIR__.'/Libraries/Backup.php';
require_once __DIR__.'/Libraries/BackupRemote.php';
require_once __DIR__.'/Libraries/ManageBackup.php';
require_once __DIR__.'/Libraries/SqlScriptParser.php';

use Flexiblebackup\Libraries\Apiinit;
use Flexiblebackup\Libraries\Backup;


app_hooks()->add_filter('app_filter_action_links_of_Flexiblebackup', function () {
    $action_links_array = [
        anchor(get_uri('flexiblebackup/settings/existing_backups'), 'Flexiblebackup settings'),
    ];

    return $action_links_array;
});

app_hooks()->add_filter('app_filter_staff_left_menu', function ($sidebar_menu) {
    $backup_submenu = [];

    $backup_submenu[] = [
        'name'  => 'existing_backups',
        'url'   => 'flexiblebackup/settings/existing_backups',
        'class' => '',
    ];

    $backup_submenu[] = [
        'name'  => 'next_scheduled_backup',
        'url'   => 'flexiblebackup/settings/next_scheduled_backup',
        'class' => '',
    ];

    $backup_submenu[] = [
        'name'  => 'settings',
        'url'   => 'flexiblebackup/settings/settings',
        'class' => '',
    ];

    $backup_submenu[] = [
        'name'  => 'backup',
        'url'   => 'flexiblebackup/backup',
        'class' => '',
    ];

    $sidebar_menu['flexiblebackup'] = [
        'name'     => 'flexiblebackup',
        'url'      => 'flexiblebackup',
        'class'    => 'download-cloud',
        'position' => 3,
        'submenu'  => $backup_submenu,
    ];

    return $sidebar_menu;
});

//install dependencies
register_installation_hook('Flexiblebackup', function ($item_purchase_code) {
    include PLUGINPATH.'Flexiblebackup/install/do_install.php';
});

register_uninstallation_hook('Flexiblebackup', function () {
    $dbprefix = get_db_prefix();
    $db = db_connect('default');

    $sql_query = 'DELETE FROM `'.$dbprefix.'settings` WHERE `'.$dbprefix."settings`.`setting_name` IN ('Flexiblebackup_verification_id', 'Flexiblebackup_last_verification', 'Flexiblebackup_product_token', 'Flexiblebackup_heartbeat');";
    $db->query($sql_query);
});

app_hooks()->add_action('app_hook_head_extension', function () {
    echo '
        <link href="'.base_url(PLUGIN_URL_PATH.'Flexiblebackup/assets/css/tree.css?v='.get_setting('app_version')).'"  rel="stylesheet" type="text/css" />
    ';
});

app_hooks()->add_action('app_hook_layout_main_view_extension', function () {
    echo '
        <script src="'.base_url(PLUGIN_URL_PATH.'Flexiblebackup/assets/js/tree.js?v='.get_setting('app_version')).'"></script>
    ';
});

app_hooks()->add_action('app_hook_after_cron_run', function () {
    $backup_lib = new Backup();
    $backup_lib->runScheduledBackups();
});
