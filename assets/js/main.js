jQuery(document).ready(function($) {
    $('#sm_validate_connection').click(function() {
        $.ajax({
            url: 'http://localhost:8081/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'validate_connection'
            },
            beforeSend: function() {
                $('#sm_validate_connection').addClass('disabled');
                $('#sm_validate_connection_result').html('<div class="loader"></div>');
            },
            success: function(response) {
                $('#sm_validate_connection').removeClass('disabled');
                if (response.success) {
                    $('#sm_validate_connection_result').html('<span style="color: green;">' + response.data.message + '</span>');
                } else {
                    $('#sm_validate_connection_result').html('<span style="color: red;">' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $('#sm_validate_connection').removeClass('disabled');
                $('#sm_validate_connection_result').html('<span style="color: red;">' + 'Error al conectar con el ERP' + '</span>');
            }
        });
    });
});