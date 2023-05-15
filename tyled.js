const baseUrl = 'https://' + window.location.hostname;
let sum = $('#order_all_sum_raifpay').attr('value').slice(0, -2);
let token = $('#order_token').attr('value');
//console.log(sum,token);
let secret = '';


$('#payment-form-raifpay').submit(function (e) {
    e.preventDefault();
    sd();
})

$( document ).ready(function() {
    $('#payment-form-raifpay').submit(function (e) {
        e.preventDefault();
        sd();
    })
});

function sd() {
    let url = baseUrl + '/module/raifpay/validation';
    $.ajax({
        type: 'POST',
        cache: false,
        dataType: 'json',
        url: url,
        data: {
            action: 'postProcess'
        },
        success:function (result) {
            console.log(result);

            const paymentPage = new PaymentPageSdk(result.publicId, {
                url: 'https://pay-test.raif.ru/pay'
            });

            if(result.type == 'redirect') {
                paymentPage.openWindow(result.data)
                    .then(function() {
                        // console.log("Спасибо");
                    })
                    .catch(function() {
                        window.location.href = baseUrl;
                    });
                return;
            }

            if(result.type == 'popup') {
                paymentPage.openPopup(result.data)
                    .then(function() {
                        // console.log("Спасибо");
                    })
                    .catch(function() {
                        window.location.href = baseUrl;
                    });
                return;
            }

        },
        error:function (result){
            console.log(result);
        }
    });


    return;


}