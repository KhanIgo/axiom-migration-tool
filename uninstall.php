<?php
/**
 * Plugin uninstall handler
 * 
 * Removes all plugin data when deleted
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete plugin options
delete_option('awm_settings');
delete_option('awm_connections');
delete_option('awm_backups');

// Delete custom tables
$tables = [
    $wpdb->prefix . 'awm_jobs',
    $wpdb->prefix . 'awm_job_steps',
    $wpdb->prefix . 'awm_logs',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Delete backup files
$backupDir = wp_upload_dir()['basedir'] . '/awm-backups/';
if (is_dir($backupDir)) {
    $files = glob($backupDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($backupDir);
}

// Delete transients
delete_transient('awm_used_nonces');
