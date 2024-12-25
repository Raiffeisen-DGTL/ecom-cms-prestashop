<input id="order_all_sum_raifpay" type="hidden" value="{$cart.totals.total.value}">
<input id="order_token" type="hidden" value="{$token|escape:'html':'UTF-8'}">
<form action="{$action}" id="payment-form-raifpay" data-modal="{$modal}">
    <script src="https://pay.raif.ru/pay/sdk/v2/payment.min.js"></script>
    <script src="https://pay.raif.ru/pay/sdk/v2/payment.styled.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
    <script src="/modules/raifpay/tyled.js"></script>
</form>
