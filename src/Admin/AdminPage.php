<?php
/**
 * Admin Page Handler
 * 
 * Handles WordPress admin menu and pages
 */

namespace Axiom\WPMigrate\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage {
    
    /**
     * Menu slug
     */
    const MENU_SLUG = 'axiom-wp-migrate';

    /**
     * Initialize admin menu
     */
    public function init(): void {
        add_menu_page(
            __('Axiom WP Migrate', 'axiom-wp-migrate'),
            __('Axiom Migrate', 'axiom-wp-migrate'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-migrate',
            80
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'axiom-wp-migrate'),
            __('Dashboard', 'axiom-wp-migrate'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Migrations', 'axiom-wp-migrate'),
            __('Migrations', 'axiom-wp-migrate'),
            'manage_options',
            'awm-migrations',
            [$this, 'renderMigrations']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Connections', 'axiom-wp-migrate'),
            __('Connections', 'axiom-wp-migrate'),
            'manage_options',
            'awm-connections',
            [$this, 'renderConnections']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Backups', 'axiom-wp-migrate'),
            __('Backups', 'axiom-wp-migrate'),
            'manage_options',
            'awm-backups',
            [$this, 'renderBackups']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'axiom-wp-migrate'),
            __('Settings', 'axiom-wp-migrate'),
            'manage_options',
            'awm-settings',
            [$this, 'renderSettings']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Logs', 'axiom-wp-migrate'),
            __('Logs', 'axiom-wp-migrate'),
            'manage_options',
            'awm-logs',
            [$this, 'renderLogs']
        );
    }

    /**
     * Render dashboard page
     */
    public function renderDashboard(): void {
        include AWM_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render migrations page
     */
    public function renderMigrations(): void {
        include AWM_PLUGIN_DIR . 'templates/admin/migrations.php';
    }

    /**
     * Render connections page
     */
    public function renderConnections(): void {
        include AWM_PLUGIN_DIR . 'templates/admin/connections.php';
    }

    /**
     * Render backups page
     */
    public function renderBackups(): void {
        include AWM_PLUGIN_DIR . 'templates/admin/backups.php';
    }

    /**
     * Render settings page
     */
    public function renderSettings(): void {
        include AWM_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render logs page
     */
    public function renderLogs(): void {
        include AWM_PLUGIN_DIR . 'templates/admin/logs.php';
    }
}
