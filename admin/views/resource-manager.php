<?php
if (!defined("ABSPATH")) {
    exit();
} ?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="arm-container">
        <div class="arm-resources-list">
            <h2><?php _e("Gestione Risorse", "amelia-resource-manager"); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e("ID Risorsa", "amelia-resource-manager"); ?></th>
                        <th><?php _e("Nome Risorsa", "amelia-resource-manager"); ?></th>
                        <th><?php _e("Ruolo", "amelia-resource-manager"); ?></th>
                        <th><?php _e("Prezzo Giornata Intera", "amelia-resource-manager"); ?></th>
                        <th><?php _e("Prezzo Mezza Giornata", "amelia-resource-manager"); ?></th>
                        <th><?php _e("Quantità Disponibile", "amelia-resource-manager"); ?></th>
                        <th><?php _e("Azioni", "amelia-resource-manager"); ?></th>
                    </tr>
                </thead>
                <tbody id="arm-resources-body">
                    <!-- I dati verranno caricati via AJAX -->
                </tbody>
            </table>

            <button class="button button-primary" id="arm-add-resource">
                <?php _e("Aggiungi Nuovo", "amelia-resource-manager"); ?>
            </button>
        </div>
    </div>
</div>

<!-- Template per il form di modifica -->
<script type="text/template" id="arm-resource-form-template">
    <div class="arm-form-container">
        <h3>
            <?php 
            // Nota: il testo verrà sostituito via JavaScript
            _e('Gestione Risorsa', 'amelia-resource-manager'); 
            ?>
        </h3>
        <form class="arm-resource-form">
            <input type="hidden" name="action" value="save_resource_price">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('arm_ajax_nonce'); ?>">
            <input type="hidden" name="resource_id" value="<%- resource_id %>">

            <div class="form-field">
                <label><?php _e("Ruolo", "amelia-resource-manager"); ?></label>
                <select name="role" required>
                    <?php wp_dropdown_roles(); ?>
                </select>
            </div>

            <div class="form-field">
                <label><?php _e(
                    "Prezzo Giornata Intera",
                    "amelia-resource-manager"
                ); ?></label>
                <input type="number" name="full_day_price" step="0.01" required>
            </div>

            <div class="form-field">
                <label><?php _e(
                    "Prezzo Mezza Giornata",
                    "amelia-resource-manager"
                ); ?></label>
                <input type="number" name="half_day_price" step="0.01" required>
            </div>

            <div class="form-field">
                <label><?php _e("Quantità", "amelia-resource-manager"); ?></label>
                <input type="number" name="quantity" min="1" required>
            </div>

            <div class="form-field">
                <label><?php _e("Risorsa Amelia", "amelia-resource-manager"); ?></label>
                <select name="amelia_resource_id" required>
                    <?php
                    global $wpdb;
                    $resources = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}amelia_resources WHERE status = 'visible' ORDER BY name ASC");
                    foreach ($resources as $resource) {
                        printf(
                            '<option value="%d">%s</option>',
                            esc_attr($resource->id),
                            esc_html($resource->name)
                        );
                    }
                    ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    <?php _e("Salva", "amelia-resource-manager"); ?>
                </button>
                <button type="button" class="button arm-cancel">
                    <?php _e("Annulla", "amelia-resource-manager"); ?>
                </button>
            </div>
        </form>
    </div>
</script>
