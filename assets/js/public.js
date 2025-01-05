jQuery(document).ready(function($) {
    // Integrazione con il form di prenotazione Amelia
    AmeliaBookingInitialized.then(function() {
        // Modifica il prezzo quando viene selezionata una risorsa
        ameliaAppointmentForm.on('resourceSelected', function(resourceId) {
            $.ajax({
                url: armAjax.ajaxurl,
                data: {
                    action: 'get_resource_price',
                    resource_id: resourceId,
                    nonce: armAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Aggiorna il prezzo visualizzato
                        ameliaAppointmentForm.setPrice(response.data.price);
                    }
                }
            });
        });
    });
});
