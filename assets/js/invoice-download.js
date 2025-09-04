jQuery(document).ready(function($) {
    'use strict';

    // Verificar si estamos en la página de orden
    if (!$('body').hasClass('post-type-shop_order')) {
        return;
    }

    // Manejar el click del botón Ver Factura
    $(document).on('click', '#sm-download-invoice', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const idmaeedo = $button.data('idmaeedo');
        
        if (!idmaeedo) {
            alert('Error: ID de documento no encontrado');
            return;
        }

        // Deshabilitar botón y mostrar estado
        const originalText = $button.text();
        $button.prop('disabled', true);
        $button.text('Descargando...');

        // Crear formulario temporal para la descarga
        const $form = $('<form>', {
            method: 'POST',
            action: ajaxurl,
            target: '_blank'
        });

        // Agregar campos necesarios
        $form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'sm_download_invoice_pdf'
        }));

        $form.append($('<input>', {
            type: 'hidden',
            name: 'idmaeedo',
            value: idmaeedo
        }));

        $form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: smInvoiceDownloadData.nonce
        }));

        // Agregar formulario al body, enviar y remover
        $('body').append($form);
        $form.submit();
        $form.remove();

        // Rehabilitar botón después de un momento
        setTimeout(function() {
            $button.prop('disabled', false);
            $button.text(originalText);
        }, 2000);
    });
});