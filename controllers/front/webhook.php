<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'load.php';

class RaifpayWebhookModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $publicId = Configuration::get('RAIF_PAY_PUBID');
        $secretKey = Configuration::get('RAIF_PAY_SECKEY');
        $api_url = 'https://pay.raif.ru';
        if ('on' == Configuration::get('RAIF_PAY_TESTPAY_1')) {
            $api_url = 'https://pay-test.raif.ru';
        }
        $ecomClient = new \Raiffeisen\Ecom\Client($secretKey, $publicId, $api_url);

        $signature = array_key_exists('HTTP_X_API_SIGNATURE_SHA256', $_SERVER)
            ? Tools::stripslashes($_SERVER['HTTP_X_API_SIGNATURE_SHA256'])
            : '';
        $body = Tools::file_get_contents('php://input');

        PrestaShopLogger::addLog($body);

        try {
            $eventBody = json_decode($body, true);

            /** @var \Raiffeisen\Ecom\Client $client */
            $checkEventSignature = $ecomClient->checkEventSignature($signature, $eventBody); // true or false
            if ($checkEventSignature) {
                $amount = $eventBody['transaction']['amount'];
                $orderId = $eventBody['transaction']['orderId'];
                $status = $eventBody['transaction']['status']['value'];
                if ($status !== 'SUCCESS') {
                    die();
                }

                $order = new Order($orderId);

                $order_amount = $order->getOrdersTotalPaid();
                $amount = number_format($amount, 2, '.', '');
                $order_amount = number_format($order_amount, 2, '.', '');

                if ($amount !== $order_amount) {
                    PrestaShopLogger::addLog('Check amount signature fail.' . $amount . '----' . $order_amount);
                    die();
                }

                $order->setCurrentState((int) Configuration::get('RAIF_STATUS_PAID'));

                PrestaShopLogger::addLog('Done');

                die();
            }

            PrestaShopLogger::addLog('Check notification signature fail.');
        } catch (Exception $exception) {
            PrestaShopLogger::addLog($exception->getMessage());
        }

        die('ok');
    }
}
