<?php


require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'load.php';

/**
 * @since 1.5.0
 */
class raifpayvalidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {





        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }



        $customer      = new Customer((int)($cart->id_customer));
        $email         = $customer->email;
        $address       = new Address(intval($cart->id_address_invoice));
        $country       = Country::getIsoById((int)$address->id_country);
        $lang_iso_code = $this->context->language->iso_code;;

        $currency      = new Currency((int)($cart->id_currency));
        $currency_code = trim($currency->iso_code);
        $amount        = $cart->getOrderTotal(true, 3);
        $total         = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $phone = ($address->phone) ? $address->phone : $address->phone_mobile;

        $callbackurl = $this->context->link->getModuleLink($this->module->name, 'webhook', array(), true);
        $failUrl = 'https://'.Tools::getHttpHost(false).'/order';
        $successurl = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            array(
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
                'key' => $customer->secure_key
            )
        );



        $this->module->validateOrder(
            $cart->id,
            Configuration::get('RAIF_STATUS_WAITING'),
            $total,
            $this->module->displayName,
            NULL,
            array(),
            (int)$currency->id,
            false,
            $customer->secure_key
        );


        $orderId = method_exists('Order', 'getOrderByCartId') ?
            Order::getOrderByCartId($cart->id) : Order::getIdByCartId($cart->id);



      // var_dump($callbackurl);
      //  var_dump($successurl);
      //  die();


        $nds = Configuration::get('RAIF_PAY_NDS');
        $pr_items = [];
        $products = $cart->getProducts();
        $cost_pr = 0;
        foreach ($products as $product) {
            $pr_items[] =   [
                'name' => $product['name'],
                'price' => number_format($product['price_wt'], 2, '.', ""),
                'quantity' => $product['cart_quantity'],
                'amount' => number_format($product['total_wt'], 2, '.', ""),
                'vatType' => $nds,
                'measurementUnit' => "OTHER",
                'paymentMode' => "FULL_PAYMENT",
                'paymentObject' => "COMMODITY"
            ];

            $cost_pr = $cost_pr + number_format($product['total_wt'], 2, '.', "");
        }

        $pr_items[] =   [
            'name' => 'Доставка',
            'price' => number_format($total - $cost_pr, 2, '.', ""),
            'quantity' => 1,
            'amount' => number_format($total - $cost_pr, 2, '.', ""),
            'vatType' => $nds,
        ];

        $publicId = Configuration::get('RAIF_PAY_PUBID');
        $secretKey = Configuration::get('RAIF_PAY_SECKEY');
        $api_url = 'https://pay.raif.ru';
        if(Configuration::get('RAIF_PAY_TESTPAY_1') && Configuration::get('RAIF_PAY_TESTPAY_1') == 'on') {
            $api_url = 'https://pay-test.raif.ru';
        }
        $ecomClient = new \Raiffeisen\Ecom\Client($secretKey, $publicId, $api_url);

        $query = [
            'successUrl' => $successurl,
            'comment' => 'Оплата заказа '.$orderId,
            'paymentDetails' => 'Оплата заказа '.$orderId,
            'failUrl' => $failUrl
        //    'paymentMethod' => "both"
        ];

        if(Configuration::get('RAIF_PAY_METHODS') == 'ONLY_SBP' || Configuration::get('RAIF_PAY_METHODS') == 'ONLY_ACQUIRING') {
            $query['paymentMethod'] = Configuration::get('RAIF_PAY_METHODS');
        }


        if(Configuration::get('RAIF_PAY_FISCHECK_1') && Configuration::get('RAIF_PAY_FISCHECK_1') == 'on') {
            $query['receipt'] = [
                'customer' => [
                    'email' => $email
                ],
                'items'=> $pr_items,
                'receiptNumber'=>$orderId
            ];
        }

        if(Configuration::get('RAIF_PAY_POPUP_1') && Configuration::get('RAIF_PAY_POPUP_1') == 'on') {
            $query['orderId'] = $orderId;
            $query['amount'] = $total;

            if(!empty(Configuration::get('RAIF_PAY_FROM_CSS'))) {
                $query['style'] = Configuration::get('RAIF_PAY_FROM_CSS');
            }

            echo Tools::jsonEncode([
                'type' => 'popup',
                'pubid' => Configuration::get('RAIF_PAY_PUBID'),
                'data' => $query
            ]);
            die();
        }


        /** @var \Raiffeisen\Ecom\Client $client */
        $link = $ecomClient->getPayUrl($total, $orderId, $query);

        echo Tools::jsonEncode([
            'type' => 'redirect',
            'link' => $link
        ]);
        die();

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'raifpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $this->context->smarty->assign([
            'params' => $_REQUEST,
        ]);

        //$this->setTemplate('payment_return.tpl');
        $this->setTemplate('module:paymentexample/views/templates/front/payment_return.tpl');


        // $customer = new Customer($cart->id_customer);
        // if (!Validate::isLoadedObject($customer))
        //     Tools::redirect('index.php?controller=order&step=1');

        // $currency = $this->context->currency;
        // $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        // $mailVars = array(
        //     '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
        //     '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
        //     '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
        // );

        // $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
        // Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }
}
