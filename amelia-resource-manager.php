<?php
/**
 * Plugin Name: Amelia Resource Manager
 * Plugin URI:
 * Description: Gestione risorse condivise per Amelia Booking
 * Version: 1.0.0
 * Author: Il tuo nome
 * Author URI:
 * Text Domain: amelia-resource-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined("ABSPATH")) {
    exit();
}

// Definizione costanti
define("ARM_VERSION", "1.0.0");
define("ARM_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("ARM_PLUGIN_URL", plugin_dir_url(__FILE__));

/**
 * Verifica che Amelia sia attivo
 */
function arm_check_amelia_dependency()
{
    if (!function_exists("is_plugin_active")) {
        include_once ABSPATH . "wp-admin/includes/plugin.php";
    }

    // Modifica la verifica per includere più percorsi possibili
    $amelia_paths = [
        'ameliabooking/ameliabooking.php',
        'amelia/amelia.php',
        'ameliabooking/amelia.php'
    ];
    
    foreach ($amelia_paths as $path) {
        if (is_plugin_active($path)) {
            return true;
        }
    }

    add_action("admin_notices", function () {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e(
                "Amelia Resource Manager richiede il plugin Amelia Booking per funzionare.",
                "amelia-resource-manager"
            ); ?></p>
        </div>
        <?php
    });
    return false;
}

/**
 * Verifica dipendenze all'attivazione
 */
function arm_activation_check()
{
    if (!arm_check_amelia_dependency()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __(
                "Amelia Resource Manager richiede il plugin Amelia Booking. Il plugin è stato disattivato.",
                "amelia-resource-manager"
            )
        );
    }
}
register_activation_hook(__FILE__, "arm_activation_check");

/**
 * Inizializzazione del plugin
 */
function init_amelia_resource_manager()
{
    try {
        require_once ARM_PLUGIN_DIR . "includes/class-core.php";
        $plugin = new AmeliaResourceManager\Core();
        $plugin->run();
    } catch (Exception $e) {
        if (defined("WP_DEBUG") && WP_DEBUG) {
            error_log("Amelia Resource Manager Error: " . $e->getMessage());
        }
    }
}

// Inizializza il plugin senza controlli di dipendenza
add_action("plugins_loaded", "init_amelia_resource_manager");

/**
 * Gestione della disattivazione
 */
function arm_deactivation()
{
    // Pulizia se necessaria
}
register_deactivation_hook(__FILE__, "arm_deactivation");
