<?php
namespace AmeliaResourceManager;

class Activator
{
    public static function activate()
    {
        self::create_tables();
        self::set_default_options();
    }

    private static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}arm_resource_prices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            amelia_resource_id bigint(20) NOT NULL,
            role varchar(50) NOT NULL,
            full_day_price decimal(10,2) NOT NULL DEFAULT '0.00',
            half_day_price decimal(10,2) NOT NULL DEFAULT '0.00',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_resource_role (amelia_resource_id, role)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Debug
        if ($wpdb->last_error) {
            error_log('ARM Table Creation Error: ' . $wpdb->last_error);
        }
    }

    private static function set_default_options()
    {
        $default_options = [
            "arm_version" => ARM_VERSION,
            "default_quantity" => 1,
            "enable_logging" => true,
        ];

        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
