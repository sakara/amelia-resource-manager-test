<?php
namespace AmeliaResourceManager;

class PriceManager
{
    private $security_manager;

    public function __construct($security_manager)
    {
        $this->security_manager = $security_manager;
        add_action("wp_ajax_save_resource_price", [
            $this,
            "save_resource_price",
        ]);
        add_action("wp_ajax_get_resource_price", [$this, "get_resource_price"]);
    }

    public function save_resource_price()
    {
        error_log('=== DEBUG SAVE RESOURCE ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        try {
            check_ajax_referer('arm_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                throw new \Exception('Permessi insufficienti');
            }

            $data = $this->security_manager->sanitize_input($_POST);
            error_log('Dati sanitizzati: ' . print_r($data, true));
            
            // Validazione
            $required = ['role', 'full_day_price', 'half_day_price', 'quantity', 'amelia_resource_id'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new \Exception("Campo richiesto mancante: $field");
                }
            }

            global $wpdb;
            
            // Debug tabella
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}arm_resource_prices'");
            error_log('Tabella esiste: ' . ($table_exists ? 'SI' : 'NO'));
            
            if (!$table_exists) {
                throw new \Exception('Tabella non trovata: ' . $wpdb->prefix . 'arm_resource_prices');
            }

            // Query di inserimento
            $result = $wpdb->replace(
                $wpdb->prefix . 'arm_resource_prices',
                [
                    'amelia_resource_id' => intval($data['amelia_resource_id']),
                    'role' => sanitize_text_field($data['role']),
                    'full_day_price' => floatval($data['full_day_price']),
                    'half_day_price' => floatval($data['half_day_price'])
                ],
                ['%d', '%s', '%f', '%f']
            );

            error_log('Risultato query: ' . print_r($result, true));
            error_log('Ultimo errore DB: ' . $wpdb->last_error);

            if ($result === false) {
                throw new \Exception($wpdb->last_error ?: 'Errore sconosciuto nel salvataggio');
            }

            wp_send_json_success([
                'message' => 'Salvato con successo',
                'data' => $data,
                'insert_id' => $wpdb->insert_id
            ]);
            
        } catch (\Exception $e) {
            error_log('Errore in save_resource_price: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_resource_price()
    {
        error_log('=== DEBUG GET RESOURCES ===');
        
        try {
            check_ajax_referer('arm_ajax_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permessi insufficienti');
                return;
            }

            global $wpdb;
            
            // Migliorata la query per ottenere tutti i dati necessari
            $query = "SELECT 
                arp.*,
                ar.id as amelia_resource_id,
                ar.name as resource_name,
                ar.quantity as resource_quantity,
                ar.status as resource_status
            FROM {$wpdb->prefix}arm_resource_prices arp
            LEFT JOIN {$wpdb->prefix}amelia_resources ar 
                ON arp.amelia_resource_id = ar.id
            WHERE ar.status = 'visible'
            ORDER BY ar.name ASC";
            
            error_log('Query: ' . $query);
            $resources = $wpdb->get_results($query);
            error_log('Risultati: ' . print_r($resources, true));
            
            if ($wpdb->last_error) {
                throw new \Exception('Errore DB: ' . $wpdb->last_error);
            }

            wp_send_json_success($resources);
            
        } catch (\Exception $e) {
            error_log('Errore in get_resource_price: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_price_for_role($resource_id, $role, $duration = "full")
    {
        // Verifica che i parametri siano validi attraverso il SecurityManager
        try {
            $data = $this->security_manager->sanitize_input([
                "resource_id" => $resource_id,
                "role" => $role,
                "duration" => $duration,
            ]);

            // Validazione aggiuntiva dei parametri
            if (!in_array($data["duration"], ["full", "half"])) {
                throw new \Exception("Durata non valida");
            }

            global $wpdb;
            $table = $wpdb->prefix . "arm_resource_prices";

            // Preparazione query sicura
            $price = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE resource_id = %d AND role = %s",
                    intval($data["resource_id"]),
                    $data["role"]
                )
            );

            if (!$price) {
                return 0;
            }

            // Log dell'accesso al prezzo (opzionale)
            $this->security_manager->log_security_event(
                sprintf(
                    "Price retrieved for resource %d, role %s, duration %s",
                    $data["resource_id"],
                    $data["role"],
                    $data["duration"]
                )
            );

            return $data["duration"] === "full"
                ? (float) $price->full_day_price
                : (float) $price->half_day_price;
        } catch (\Exception $e) {
            // Log dell'errore
            $this->security_manager->log_security_event(
                "Error in get_price_for_role: " . $e->getMessage()
            );
            return 0;
        }
    }

    private function sync_with_amelia_resource($data)
    {
        global $wpdb;
        
        // Prepara i dati per la risorsa Amelia
        $resource_data = [
            'name' => sprintf('Resource for %s', $data['role']),
            'quantity' => intval($data['quantity']),
            'shared' => 'service',
            'status' => 'visible',
            'countAdditionalPeople' => 0
        ];

        // Se esiste già una risorsa, aggiornala
        if (!empty($data['amelia_resource_id'])) {
            $wpdb->update(
                $wpdb->prefix . 'amelia_resources',
                $resource_data,
                ['id' => $data['amelia_resource_id']],
                ['%s', '%d', '%s', '%s', '%d'],
                ['%d']
            );
            return $data['amelia_resource_id'];
        }

        // Crea una nuova risorsa in Amelia
        $wpdb->insert(
            $wpdb->prefix . 'amelia_resources',
            $resource_data,
            ['%s', '%d', '%s', '%s', '%d']
        );

        $resource_id = $wpdb->insert_id;

        // Opzionalmente, collega la risorsa a un servizio specifico
        if ($resource_id && !empty($data['service_id'])) {
            $wpdb->insert(
                $wpdb->prefix . 'amelia_resources_to_entities',
                [
                    'resourceId' => $resource_id,
                    'entityId' => $data['service_id'],
                    'entityType' => 'service'
                ],
                ['%d', '%d', '%s']
            );
        }

        return $resource_id;
    }

    public function check_resource_availability($amelia_resource_id, $start_date, $end_date) {
        global $wpdb;
        
        // Query per verificare la disponibilità della risorsa nelle prenotazioni Amelia
        $query = $wpdb->prepare(
            "SELECT ar.quantity as total_quantity,
                    COUNT(DISTINCT ab.id) as booked_quantity
             FROM {$wpdb->prefix}amelia_resources ar
             LEFT JOIN {$wpdb->prefix}amelia_providers_to_resources ptr ON ar.id = ptr.resourceId
             LEFT JOIN {$wpdb->prefix}amelia_appointments ab ON ptr.providerId = ab.providerId
             WHERE ar.id = %d 
             AND ab.bookingStart >= %s 
             AND ab.bookingEnd <= %s
             GROUP BY ar.id",
            $amelia_resource_id,
            $start_date,
            $end_date
        );

        return $wpdb->get_row($query);
    }
}
