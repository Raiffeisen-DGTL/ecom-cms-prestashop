<?php

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'load.php';

class RaifpayvalidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'raifpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.'));
        }

        $cart = $this->context->cart;

        if (
            $cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer((int) $cart->id_customer);
        $email = $customer->email;

        $currency = new Currency((int) $cart->id_currency);
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $failUrl = 'https://' . Tools::getHttpHost(false) . '/order';
        $successUrl = $this->context->link->getPageLink(
            'order-confirmation',
            true,
            null,
            [
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
                'key' => $customer->secure_key,
            ]
        );

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('RAIF_STATUS_WAITING'),
            $total,
            $this->module->displayName,
            NULL,
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $orderId = method_exists('Order', 'getOrderByCartId')
            ? Order::getOrderByCartId($cart->id)
            : Order::getIdByCartId($cart->id);

        $nds = Configuration::get('RAIF_PAY_NDS');
        $pr_items = [];
        $products = $cart->getProducts();
        $cost_pr = 0;
        foreach ($products as $product) {
            $pr_items[] = [
                'name' => $product['name'],
                'price' => number_format($product['price_wt'], 2, '.', ''),
                'quantity' => $product['cart_quantity'],
                'amount' => number_format($product['total_wt'], 2, '.', ''),
                'vatType' => $nds,
                'measurementUnit' => 'OTHER',
                'paymentMode' => 'FULL_PAYMENT',
                'paymentObject' => 'COMMODITY',
            ];

            $cost_pr = $cost_pr + $product['total_wt'];
        }

        $pr_items[] =   [
            'name' => 'Доставка',
            'price' => number_format($total - $cost_pr, 2, '.', ''),
            'quantity' => 1,
            'amount' => number_format($total - $cost_pr, 2, '.', ''),
            'vatType' => $nds,
        ];

        $query = [
            'successUrl' => $successUrl,
            'comment' => $this->module->l('Payment for the order: ') . $orderId,
            'paymentDetails' => $this->module->l('Payment for the order: ') . $orderId,
            'failUrl' => $failUrl,
            'publicId' => Configuration::get('RAIF_PAY_PUBID'),
        ];

        if ('ONLY_SBP' == Configuration::get('RAIF_PAY_METHODS') || 'ONLY_ACQUIRING' == Configuration::get('RAIF_PAY_METHODS')) {
            $query['paymentMethod'] = Configuration::get('RAIF_PAY_METHODS');
        }

        if ('on' == Configuration::get('RAIF_PAY_FISCHECK_1')) {
            $query['receipt'] = [
                'customer' => [
                    'email' => $email,
                ],
                'items' => $pr_items,
                'receiptNumber' => $orderId,
            ];
        }

        $query['orderId'] = $orderId;
        $query['amount'] = $total;

        $style = Configuration::get('RAIF_PAY_FROM_CSS');
        if (!empty($style)) {
            $query['style'] = json_decode($style);
        }

        echo json_encode([
            'type' => 'on' == Configuration::get('RAIF_PAY_POPUP_1') ? 'popup' : 'redirect',
            'publicId' => Configuration::get('RAIF_PAY_PUBID'),
            'data' => $query,
        ]);

        die();
    }
}
