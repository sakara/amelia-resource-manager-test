<?php
namespace AmeliaResourceManager;

class ResourceManager
{
    private $plugin_name;
    private $version;
    private $security_manager; // Dichiarazione della proprietà

    public function __construct($security_manager)
    {
        $this->plugin_name = "amelia-resource-manager";
        $this->version = ARM_VERSION;
        $this->security_manager = $security_manager; // Assegnazione nel costruttore
    }

    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            ARM_PLUGIN_URL . "assets/css/admin.css",
            [],
            $this->version,
            "all"
        );
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            ARM_PLUGIN_URL . "assets/js/admin.js",
            ["jquery"],
            $this->version,
            false
        );

        wp_localize_script($this->plugin_name, "armAjax", [
            "ajaxurl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("arm_ajax_nonce"),
            "i18n" => [
                "edit" => __("Modifica", "amelia-resource-manager"),
                "delete" => __("Elimina", "amelia-resource-manager"),
                "confirm_delete" => __("Sei sicuro di voler eliminare questa risorsa?", "amelia-resource-manager")
            ]
        ]);
    }

    public function add_menu_pages()
    {
        add_submenu_page(
            "amelia",
            __("Resource Manager", "amelia-resource-manager"),
            __("Resource Manager", "amelia-resource-manager"),
            "manage_options",
            "arm-resources",
            [$this, "display_resource_page"]
        );
    }

    public function display_resource_page()
    {
        include_once ARM_PLUGIN_DIR . "admin/views/resource-manager.php";
    }

    public function get_resource_availability($resource_id, $start_date, $end_date) {
        global $wpdb;
        
        // Aggiungi cache
        $cache_key = "arm_availability_{$resource_id}_{$start_date}_{$end_date}";
        $result = wp_cache_get($cache_key);
        
        if (false === $result) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT rp.quantity,
                        COUNT(DISTINCT be.bookingId) as booked,
                        rp.quantity - COUNT(DISTINCT be.bookingId) as available
                 FROM {$wpdb->prefix}arm_resource_prices rp
                 LEFT JOIN {$wpdb->prefix}amelia_customer_bookings_to_extras be 
                    ON rp.resource_id = be.extraId
                    AND DATE(be.booking_date) BETWEEN %s AND %s
                 WHERE rp.resource_id = %d
                 GROUP BY rp.resource_id, rp.quantity",
                $start_date, $end_date, $resource_id
            ));
            
            wp_cache_set($cache_key, $result, '', 300); // Cache per 5 minuti
        }
        
        return $result;
    }

    public function validate_resource_booking($resource_id, $quantity, $date)
    {
        $availability = $this->get_resource_availability(
            $resource_id,
            $date,
            $date
        );
        if (!$availability) {
            return true; // Nessuna prenotazione esistente
        }
        return $availability->quantity - $availability->booked >= $quantity;
    }

    // Aggiorna il metodo che gestisce le richieste AJAX
    public function handle_resource_request()
    {
        // Verifica sicurezza
        if (!$this->security_manager->validate_request(true, $_POST)) {
            wp_send_json_error("Security check failed");
            return;
        }

        $data = $this->security_manager->sanitize_input($_POST);

        try {
            $this->security_manager->validate_booking_data($data);
            // Procedi con la gestione della richiesta
            wp_send_json_success("Richiesta gestita con successo");
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
            return;
        }
    }

    private function check_resource_conflicts($resource_id, $start_date, $end_date) {
        // Hook nel processo di prenotazione di Amelia
        global $wpdb;
        
        // Verifica sovrapposizioni nelle prenotazioni
        $query = $wpdb->prepare(
            "SELECT COUNT(*) as conflicts
             FROM {$wpdb->prefix}amelia_appointments aa
             JOIN {$wpdb->prefix}amelia_providers_to_resources ptr 
                 ON aa.providerId = ptr.providerId
             WHERE ptr.resourceId = %d
             AND (
                 (bookingStart BETWEEN %s AND %s)
                 OR (bookingEnd BETWEEN %s AND %s)
                 OR (bookingStart <= %s AND bookingEnd >= %s)
             )",
            $resource_id,
            $start_date,
            $end_date,
            $start_date,
            $end_date,
            $start_date,
            $end_date
        );
        
        return $wpdb->get_var($query);
    }

    public function integrate_with_amelia_booking() 
    {
        // Hook nel processo di prenotazione di Amelia
        add_filter('amelia_before_appointment_booking_price_calculation', 
            [$this, 'modify_booking_price'], 10, 2);
            
        // Hook per la verifica disponibilità
        add_filter('amelia_before_appointment_booking_available_check', 
            [$this, 'check_resource_availability'], 10, 2);
    }

    public function modify_booking_price($price, $booking_data) 
    {
        // Modifica il prezzo in base al ruolo utente e alla durata
        $user = wp_get_current_user();
        $role = $user->roles[0];
        
        $resource_id = $booking_data['resourceId'];
        $duration = $this->calculate_duration_type($booking_data);
        
        $price_modifier = $this->price_manager->get_price_for_role(
            $resource_id,
            $role,
            $duration
        );
        
        return $price + $price_modifier;
    }
}
