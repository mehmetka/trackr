function ajaxCall(method, url, data, async = false, successAlert = true, errorAlert = true) {

    var result = false;

    $.ajax({
        type: method,
        data: data,
        url: url,
        async: async,
        success: function (data, status, xhr) {

            if (status === 'success') {
                result = data;
            }

            if (successAlert === true) {
                $.bootstrapPurr(xhr.responseJSON.message, {
                    type: 'success'
                });
            }

        },
        error: function (data, status, xhr) {

            $.bootstrapPurr(data.responseJSON.message, {
                type: 'danger'
            });

        }
    });

    return result;
}