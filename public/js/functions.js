function ajaxCall(method, url, data, redirect = null, reload = null, closeModal = null) {
    $.ajax({
        type: "POST",
        data: data,
        url: "/books",
        success: function (data, status, xhr) {

            $.bootstrapPurr(xhr.responseJSON.message, {
                type: 'success'
            });

            if (closeModal !== null) {
                $('#' + closeModal).modal('hide');
            }

            if (reload !== null) {
                location.reload();
            }

            if (redirect !== null) {
                location.reload();
            }

        },
        error: function (data, status, xhr) {
            $.bootstrapPurr(xhr.responseJSON.message, {
                type: 'danger'
            });
        }
    });
}