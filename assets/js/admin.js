jQuery(document).ready(function ($) {
  // Debug iniziale
  console.log('=== DEBUG ARM ===');
  console.log('Ajax settings:', armAjax);

  console.log('ARM Admin JS loaded', {
    ajaxurl: armAjax.ajaxurl,
    nonce: armAjax.nonce
  });

  // Carica risorse con gestione errori e retry
  function loadResources(retryCount = 0) {
    console.log('Caricamento risorse...');
    
    const maxRetries = 3;

    return $.ajax({
      url: armAjax.ajaxurl,
      data: {
        action: "get_resource_price",
        nonce: armAjax.nonce,
      },
    })
    .then((response) => {
      console.log('Risposta loadResources:', response);
      if (response.success && response.data) {
        // Renderizza i dati nella tabella
        const $tbody = $('#arm-resources-body');
        $tbody.empty();
        
        response.data.forEach(resource => {
          // Debug dei dati di ogni risorsa
          console.log('Resource data:', resource);
          
          $tbody.append(`
            <tr>
              <td>${resource.amelia_resource_id || '-'}</td>
              <td>${resource.resource_name || '-'}</td>
              <td>${resource.role || '-'}</td>
              <td>${parseFloat(resource.full_day_price).toFixed(2) || '0.00'}</td>
              <td>${parseFloat(resource.half_day_price).toFixed(2) || '0.00'}</td>
              <td>${resource.resource_quantity || '0'}</td>
              <td>
                <button class="button edit-resource" data-id="${resource.id}">
                  ${armAjax.i18n.edit}
                </button>
                <button class="button delete-resource" data-id="${resource.id}">
                  ${armAjax.i18n.delete}
                </button>
              </td>
            </tr>
          `);
        });
      } else {
        console.error('Nessun dato ricevuto o errore nella risposta:', response);
      }
    })
    .catch((error) => {
      console.error('Errore loadResources:', error);
      if (retryCount < maxRetries) {
        return new Promise((resolve) => {
          setTimeout(() => {
            resolve(loadResources(retryCount + 1));
          }, 1000 * Math.pow(2, retryCount));
        });
      }
      throw error;
    });
  }

  // Modifica la funzione saveResource per restituire sempre una Promise
  const saveResource = function(formData) {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: armAjax.ajaxurl,
        method: 'POST',
        data: formData,
        success: resolve,
        error: reject
      });
    });
  };

  // Salva risorsa
  $(".arm-resource-form").on("submit", function (e) {
    e.preventDefault();

    var formData = $(this).serialize();
    formData += "&nonce=" + armAjax.nonce; // Il nonce viene passato da wp_localize_script

    saveResource(formData).then((response) => {
      if (response.success) {
        showMessage("success", "Salvato con successo");
        loadResources();
      } else {
        showMessage("error", response.data);
      }
    });
  });

  // Mostra messaggi
  function showMessage(type, message) {
    var messageClass = type === "success" ? "arm-success" : "arm-error";
    var $message = $('<div class="' + messageClass + '">' + message + "</div>");

    $(".arm-container").prepend($message);
    setTimeout(function () {
      $message.fadeOut(function () {
        $(this).remove();
      });
    }, 3000);
  }

  // Gestione click su "Aggiungi Nuovo"
  $('#arm-add-resource').on('click', function(e) {
    e.preventDefault();
    
    var template = _.template($('#arm-resource-form-template').html());
    var formHtml = template({
        resource_id: '', // vuoto per nuova risorsa
    });
    
    // Dopo aver inserito il form, aggiorna il titolo
    var $form = $(formHtml).appendTo('.arm-resources-list');
    $form.find('h3').text('Nuova Risorsa');
  });

  // Quando si modifica una risorsa esistente
  $(document).on('click', '.edit-resource', function(e) {
    e.preventDefault();
    
    var resourceId = $(this).data('id');
    var template = _.template($('#arm-resource-form-template').html());
    var formHtml = template({
        resource_id: resourceId,
    });
    
    var $form = $(formHtml).appendTo('.arm-resources-list');
    $form.find('h3').text('Modifica Risorsa');
  });

  // Delegazione eventi per form dinamici
  $(document).on('submit', '.arm-resource-form', function(e) {
    e.preventDefault();
    
    var $form = $(this);
    // Usa serialize() invece di FormData per una migliore compatibilità
    var formData = $form.serialize() + '&action=save_resource_price&nonce=' + armAjax.nonce;

    console.log('Invio dati:', formData);
    
    $.ajax({
        url: armAjax.ajaxurl,
        method: 'POST',
        data: formData,
        success: function(response) {
            console.log('Risposta salvataggio:', response);
            if (response.success) {
                showMessage('success', 'Risorsa salvata con successo');
                loadResources();
                $form.closest('.arm-form-container').remove();
            } else {
                showMessage('error', response.data || 'Errore durante il salvataggio');
            }
        },
        error: function(xhr, status, error) {
            console.error('Errore Ajax:', {xhr, status, error});
            showMessage('error', 'Errore di connessione');
        }
    });
  });

  // Aggiungi anche la gestione del pulsante Annulla
  $(document).on('click', '.arm-cancel', function(e) {
    e.preventDefault();
    $(this).closest('.arm-form-container').remove();
  });

  // Carica risorse all'avvio
  loadResources();
});
