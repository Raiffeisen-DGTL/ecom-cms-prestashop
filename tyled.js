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
            if(result.type == 'redirect') {
                window.location.href = result.link;
                return;
            }

            if(result.type == 'popup') {
                const paymentPage = new PaymentPageSdk(result.pubid, {
                    url: 'https://pay-test.raif.ru/pay'
                });


                result.data

                paymentPage.openPopup(result.data)
                    .then(function() {
                        // console.log("Спасибо");
                    })
                    .catch(function() {
                        window.location.href = baseUrl;
                    });
            }

        },
        error:function (result){
            console.log(result);
        }
    });


    return;


}