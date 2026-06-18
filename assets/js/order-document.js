jQuery(document).ready(function ($) {
    $('#sm_create_document_btn').on('click', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const orderId = $btn.data('order-id');
        const nonce = $btn.data('nonce');
        const $loading = $('#sm_document_loading');
        const $message = $('#sm_document_message');

        $btn.prop('disabled', true);
        $loading.show();
        $message.empty();

        $.ajax({
            type: 'POST',
            url: smOrderDocument.ajax_url,
            data: {
                action: 'sm_create_document',
                order_id: orderId,
                nonce: nonce
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $message.html('<p style="color: #4caf50;"><strong>Éxito:</strong> ' + response.data + '</p>');
                    // Recargar la página después de 2 segundos
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    $message.html('<p style="color: #d32f2f;"><strong>Error:</strong> ' + response.data + '</p>');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                $message.html('<p style="color: #d32f2f;"><strong>Error:</strong> Error en la solicitud AJAX</p>');
                $btn.prop('disabled', false);
            },
            complete: function () {
                $loading.hide();
            }
        });
    });
});
