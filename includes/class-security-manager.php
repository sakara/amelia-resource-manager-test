<?php
namespace AmeliaResourceManager;

class SecurityManager
{
    private $nonce_action = "arm_security_nonce";
    private $rate_limit_window = 300; // 5 minuti
    private $max_attempts = 5;

    public function __construct()
    {
        add_action("init", [$this, "init_security"]);
        add_filter(
            "arm_before_process_request",
            [$this, "validate_request"],
            10,
            2
        );
    }

    public function init_security()
    {
        // Rimuovi il controllo di Amelia
        return true;
    }

    public function validate_request($valid, $request_data)
    {
        // Semplifica la validazione rimuovendo i controlli non necessari
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Verifica solo i permessi base
        $user = wp_get_current_user();
        $allowed_roles = ["administrator", "editor"];
        
        if (!array_intersect($allowed_roles, $user->roles)) {
            return false;
        }
        
        return true;
    }

    private function check_user_permissions($request_data)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = ["administrator", "editor"];

        // Verifica se l'utente ha uno dei ruoli permessi
        if (!array_intersect($allowed_roles, $user->roles)) {
            return false;
        }

        return true;
    }

    private function check_rate_limit()
    {
        $user_id = get_current_user_id();
        $key = "arm_rate_limit_" . $user_id;
        $attempts = get_transient($key);

        if (false === $attempts) {
            set_transient($key, 1, $this->rate_limit_window);
            return true;
        }

        if ($attempts >= $this->max_attempts) {
            return false;
        }

        set_transient($key, $attempts + 1, $this->rate_limit_window);
        return true;
    }

    public function sanitize_input($data)
    {
        if (is_array($data)) {
            return array_map([$this, "sanitize_input"], $data);
        }

        if (is_string($data)) {
            return sanitize_text_field($data);
        }

        return $data;
    }

    public function validate_booking_data($booking_data)
    {
        $required_fields = [
            "resource_id",
            "start_date",
            "end_date",
            "quantity",
        ];

        foreach ($required_fields as $field) {
            if (!isset($booking_data[$field]) || empty($booking_data[$field])) {
                throw new \Exception(
                    sprintf(
                        __(
                            "Campo richiesto mancante: %s",
                            "amelia-resource-manager"
                        ),
                        $field
                    )
                );
            }
        }

        // Validazione date
        $start_date = strtotime($booking_data["start_date"]);
        $end_date = strtotime($booking_data["end_date"]);

        if ($start_date === false || $end_date === false) {
            throw new \Exception(
                __("Format data non valido", "amelia-resource-manager")
            );
        }

        if ($start_date >= $end_date) {
            throw new \Exception(
                __(
                    "La data di inizio deve essere precedente alla data di fine",
                    "amelia-resource-manager"
                )
            );
        }

        // Validazione quantità
        $quantity = intval($booking_data["quantity"]);
        if ($quantity <= 0) {
            throw new \Exception(
                __(
                    "La quantità deve essere maggiore di zero",
                    "amelia-resource-manager"
                )
            );
        }

        return true;
    }

    private function log_security_event($message)
    {
        if (!defined("WP_DEBUG") || !WP_DEBUG) {
            return;
        }

        $user_id = get_current_user_id();
        $user_ip = $_SERVER["REMOTE_ADDR"];
        $current_time = current_time("mysql");

        $log_message = sprintf(
            "[%s] Security Event - User ID: %d, IP: %s - %s",
            $current_time,
            $user_id,
            $user_ip,
            $message
        );

        error_log($log_message);
    }

    public function get_nonce()
    {
        return wp_create_nonce($this->nonce_action);
    }

    public function verify_nonce($nonce)
    {
        return wp_verify_nonce($nonce, $this->nonce_action);
    }

    private function check_ip_whitelist() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $whitelist = get_option('arm_ip_whitelist', []);
        return empty($whitelist) || in_array($ip, $whitelist);
    }

    private function verify_nonce_with_timeout($nonce) {
        $timestamp = wp_verify_nonce($nonce, $this->nonce_action);
        return $timestamp && $timestamp > (time() - 3600); // 1 ora di timeout
    }
}
