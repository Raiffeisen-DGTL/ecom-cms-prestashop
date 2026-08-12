$(document).ready(function() {
    const form = $('#payment-form-raifpay');

    function send() {
        const baseUrl = '//' + window.location.hostname;
        const url = baseUrl + '/module/raifpay/validation';
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url,
            data: {
                action: 'postProcess',
            },
            success: function (result) {
                debugger;
                const paymentPage = new PaymentPageSdk(result.publicId, {
                    url: form.data('modal'),
                });
                if (result.type == 'redirect') {
                    paymentPage.replace(result.data);
                } else if (result.type == 'popup') {
                    paymentPage.openPopup(result.data).catch(function() {
                        window.location.href = baseUrl;
                    });
                }
            },
        });
    }

    form.submit(function (e) {
        e.preventDefault();
        send();
    });
});
