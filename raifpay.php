<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use OrderDetail;

// Autoload for standalone composer build.
if (!class_exists('Curl\Curl')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'curl' . DIRECTORY_SEPARATOR . 'curl' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Curl' . DIRECTORY_SEPARATOR . 'Curl.php';
}


if (!class_exists('Raiffeisen\Ecom\Client')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'raiffeisen-ecom' . DIRECTORY_SEPARATOR . 'payment-sdk' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Client.php';
}

class raifpay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'raifpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'DEV';
        $this->controllers = array('validation','webhook');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'Райффайзенбанк';
        $this->description = 'Платежный модуль Райффайзенбанка';

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        if(empty(Configuration::get('RAIF_STATUS_WAITING'))) {
            $orderWaiting = $this->createOrderStatus('Райффайзенбанк - ожидание оплаты', '#0042FF');
            $orderPaid = $this->createOrderStatus('Райффайзенбанк - оплачено', '#00ff05');
            $orderPartialRefunded = $this->createOrderStatus('Райффайзенбанк - частичный возврат', '#ff4f00');
            $orderFullRefunded = $this->createOrderStatus('Райффайзенбанк - возврат', '#ff0000');
            Configuration::updateValue('RAIF_PAY_NAME', '');
            Configuration::updateValue('RAIF_STATUS_WAITING', $orderWaiting->id);
            Configuration::updateValue('RAIF_STATUS_PART_REFUNDED', $orderPartialRefunded->id);
            Configuration::updateValue('RAIF_STATUS_PAID', $orderPaid->id);
            Configuration::updateValue('RAIF_STATUS_FULL_REFUNDED', $orderFullRefunded->id);
        }

        $this->registerHook('actionProductCancel');
        $this->registerHook('actionOrderStatusPostUpdate');
    }

    public function hookActionProductCancel($params) {
        //file_put_contents('/home/admin/web/prest.vk-smart.info/public_html/modules/raifpay/1', json_encode($params));
        $publicId = Configuration::get('RAIF_PAY_PUBID');
        $secretKey = Configuration::get('RAIF_PAY_SECKEY');
        $api_url = 'https://pay.raif.ru';
        if(Configuration::get('RAIF_PAY_TESTPAY_1') && Configuration::get('RAIF_PAY_TESTPAY_1') == 'on') {
            $api_url = 'https://pay-test.raif.ru';
        }
        $ecomClient = new \Raiffeisen\Ecom\Client($secretKey, $publicId, $api_url);
        $action = $params['action'];
        $sumReturned = $params['cancel_amount'];
        $retQuantity = $params['cancel_quantity'];
        $order_id = $params['order']->id;
        $customer       = new Customer ($params['order']->id_customer);
        $email         = $customer->email;
        $nds = Configuration::get('RAIF_PAY_NDS');
        //file_put_contents('/var/www/prestashop/public/modules/raifpay/1', print_r($params,1).'00000132'.PHP_EOL,FILE_APPEND);
        $amount = 0;
        $itemss = [];
        $items = new stdClass();
        foreach(OrderDetail::getList((int)$order_id) as $val) {
            if($params['id_order_detail'] == $val['id_order_detail']) {
                $id_detail = $val['id_order_detail'];
                $quantity = $params['cancel_quantity'];
                $price = number_format($val['total_price_tax_incl'], 2, '.', "");
                $name = $val['product_name'];
                $amount = $params['cancel_amount'];
                if($amount != $val['product_price'] * $quantity) $price = $amount / $quantity;
                $items->name = $name;
                $items->price = (float)number_format($price, 2, '.', "");
                $items->quantity = (float)$quantity;
                $items->amount = (float)number_format($amount, 2, '.', "");
                $items->vatType = $nds;
                $itemss[] = $items;
            }  
        }
        $items = (object)$items;
        if(Configuration::get('RAIF_PAY_FISCHECK_1') && Configuration::get('RAIF_PAY_FISCHECK_1') == 'on') {
            $query['paymentDetails'] = (string)"Возврат по заказу ".$order_id;
            $customer = new stdClass();
            $customer->email = $email;
            $receipt = new stdClass();
            $receipt->items = $itemss;
            $receipt->customer = $customer;
            $query['receipt'] = $receipt;
            file_put_contents('/var/www/prestashop/public/modules/raifpay/1', print_r($query,1).PHP_EOL,FILE_APPEND);
        }
        $responce = $ecomClient->postOrderRefund($order_id, $order_id, (float)number_format($amount, 2, '.', ""), $query);
        //file_put_contents('/home/admin/web/prest.vk-smart.info/public_html/modules/raifpay/1', print_r($responce,1))
        file_put_contents('/var/www/prestashop/public/modules/raifpay/1', print_r($responce,1).PHP_EOL,FILE_APPEND);
        if($responce['code'] == 'SUCCESS') {
            $order = new Order($order_id);
            $order->setCurrentState((int)Configuration::get('RAIF_STATUS_PART_REFUNDED'));
       }
    }
    /**
     *  array(
      *  'newOrderStatus' => (object) OrderState,
     *   'oldOrderStatus' => (object) OrderState,
      *  'id_order' => (int) Order ID
      *  );
     *
     */
    //actionOrderStatusUpdate
    public function hookActionOrderStatusPostUpdate($params) {

        $context = Context::getContext();
        $id_lang = (int)$context->language->id;
        $id_shop = (int)$context->shop->id;
        $checkApprovalProducts = array();
        //file_put_contents(__DIR__."/test.txt", 'test1 '.print_r($params,1).PHP_EOL, FILE_APPEND);
        //file_put_contents(__DIR__."/test.txt", 'test '.print_r($params['newOrderStatus']->id,1).PHP_EOL, FILE_APPEND);
        //file_put_contents(__DIR__."/test.txt", 'test 123'.print_r($params['id_order'],1).PHP_EOL, FILE_APPEND);
        if($params['newOrderStatus']->id == Configuration::get('RAIF_STATUS_FULL_REFUNDED')) {
            $publicId = Configuration::get('RAIF_PAY_PUBID');
            $secretKey = Configuration::get('RAIF_PAY_SECKEY');

            $api_url = 'https://pay.raif.ru';
            if(Configuration::get('RAIF_PAY_TESTPAY_1') && Configuration::get('RAIF_PAY_TESTPAY_1') == 'on') {
                 $api_url = 'https://pay-test.raif.ru';
            }
            $ecomClient = new \Raiffeisen\Ecom\Client($secretKey, $publicId, $api_url);

            $orderId = $params['id_order']; //Ok Id de la commande démarrée
            $order = new Order($orderId);
            $customer       = new Customer ($order->id_customer);
            $cart           = new Cart ($order->id_cart);
            $email         = $customer->email;
            $total         = (float)$cart->getOrderTotal(true, Cart::BOTH);

            $query = [
                'amount' => $total,
            ];

            $nds = Configuration::get('RAIF_PAY_NDS');
            $pr_items = [];
            $products = $cart->getProducts();
            $cost_pr = 0;
            foreach ($products as $product) {
             //  file_put_contents('/home/admin/web/prest.vk-smart.info/public_html/modules/raifpay/1', json_encode($product)); dd();
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


            $query['receipt'] = [
                'customer' => [
                    'email' => $email
                ],
                'items'=> $pr_items,
                'receiptNumber'=>$orderId
            ];

            $response = $ecomClient->postOrderRefundCheck($orderId, $orderId, $query);
           // PrestaShopLogger::addLog('Return order #'.$orderId.' -- '.$response);
        }
    }

    public function createOrderStatus($name, $color)
    {

        $order = new OrderState();
        $order->name = array_fill(0, 10, $name);
        $order->send_email = 0;
        $order->invoice = 0;
        $order->module_name = 'raifpay';
        $order->color = $color;
        $order->unremovable = false;
        $order->logable = 1;
        $order->save();
        return $order;
    }


    public function install()
    {
        $this->registerHook('actionProductCancel');
        $this->registerHook('actionOrderStatusPostUpdate');
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        
        $payment_options = [
            $this->getEmbeddedPaymentOption(),
        ];

        return $payment_options;
    }

    public function hookdisplayHeader() {
        //parent::hookHeaders();
        //$this->context->controller->addJS($this->_path.'tyled.js','all');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getOfflinePaymentOption()
    {
        $offlineOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $offlineOption->setCallToActionText($this->l('Pay offline'))
                      ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                      ->setAdditionalInformation($this->context->smarty->fetch('module:raifpay/views/templates/front/payment_infos.tpl'))
                      ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $offlineOption;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay external'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setInputs([
                            'token' => [
                                'name' =>'token',
                                'type' =>'hidden',
                                'value' =>'12345689',
                            ],
                        ])
                       ->setAdditionalInformation($this->context->smarty->fetch('module:raifpay/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $externalOption;
    }

    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $embeddedOption->setCallToActionText(Configuration::get('RAIF_PAY_NAME'))
                       ->setForm($this->generateForm())
                       ->setAdditionalInformation($this->context->smarty->fetch('module:raifpay/views/templates/front/payment_infos.tpl'));

            //->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $embeddedOption;
    }


    protected function generateForm()
    {

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'descr' => Configuration::get('RAIF_PAY_DESCR'),
        ]);

        return $this->context->smarty->fetch('module:raifpay/views/templates/front/payment_form.tpl');
    }

    /**
     * Module Configuration page controller.
     * Handle the form POST request and outputs the form.
     */
    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('update_settings_' . $this->name)) {
            Configuration::updateValue('RAIF_PAY_NAME', (string) Tools::getValue('RAIF_PAY_NAME'));
            Configuration::updateValue('RAIF_PAY_DESCR', (string) Tools::getValue('RAIF_PAY_DESCR'));
            Configuration::updateValue('RAIF_PAY_PUBID', (string) Tools::getValue('RAIF_PAY_PUBID'));
            Configuration::updateValue('RAIF_PAY_SECKEY', (string) Tools::getValue('RAIF_PAY_SECKEY'));
            Configuration::updateValue('RAIF_PAY_NDS', (string) Tools::getValue('RAIF_PAY_NDS'));
            Configuration::updateValue('RAIF_PAY_POPUP', (string) Tools::getValue('RAIF_PAY_POPUP'));
            Configuration::updateValue('RAIF_PAY_POPUP_1', (string) Tools::getValue('RAIF_PAY_POPUP_1'));
        //    Configuration::updateValue('RAIF_PAY_FROM_CSS', (string) Tools::getValue('RAIF_PAY_FROM_CSS'));
            Configuration::updateValue('RAIF_PAY_FISCHECK', (string) Tools::getValue('RAIF_PAY_FISCHECK'));
            Configuration::updateValue('RAIF_PAY_FISCHECK_1', (string) Tools::getValue('RAIF_PAY_FISCHECK_1'));
            Configuration::updateValue('RAIF_PAY_METHODS', (string) Tools::getValue('RAIF_PAY_METHODS'));
            Configuration::updateValue('RAIF_PAY_TESTPAY', (string) Tools::getValue('RAIF_PAY_TESTPAY'));
            Configuration::updateValue('RAIF_PAY_TESTPAY_1', (string) Tools::getValue('RAIF_PAY_TESTPAY_1'));



            preg_match_all('/style:(.*)succ/si', Tools::getValue('RAIF_PAY_FROM_CSS'), $output_array);
            if(!empty($output_array[1])) {
                $css = $output_array[1][0];
                Configuration::updateValue('RAIF_PAY_FROM_CSS', $css);
            } else {
                $css = (string) Tools::getValue('RAIF_PAY_FROM_CSS');
            }


            $css = str_replace(PHP_EOL, '', $css);
            $css = trim(preg_replace('/\s\s+/', '', $css));
            $css = str_replace(',},}', '}}', $css);
            $css = str_replace(',}', '}', $css);
            $css = str_replace("'", '"', $css);
            $to_repalce = [
                'header',
                'titlePlace',
                'button',
                'backgroundColor',
                'textColor',
                'hoverTextColor',
                'hoverBackgroundColor',
                'borderRadius',
                'logo',
            ];
            foreach ($to_repalce as $item) {
                $css = str_replace($item, '"'.$item.'"', $css);
            }
            $css = str_replace(" ", '', $css);
            $css = trim($css);

            // $css = '{'.$css.'}';
            //TODO fuck
            Configuration::updateValue('RAIF_PAY_FROM_CSS', $css);


            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output . $this->displayForm();
    }


    /**
     * Executes when uninstalling the module.
     * Cleanup DB fields and raise error if something goes wrong.
     */
    public function uninstall()
    {
       // Configuration::deleteByName('RAIF_PAY_PUBID');
      //  Configuration::deleteByName('RAIF_PAY_SECKEY');




      //  $orderWaiting = new OrderState(Configuration::get('QIWI_STATUS_WAITING'));
      //  $orderPaid = new OrderState(Configuration::get('QIWI_STATUS_PAID'));
      //  $orderFullRefunded = new OrderState(Configuration::get('QIWI_STATUS_FULL_REFUNDED'));

      //  $orderWaiting->delete();
      //  $orderPaid->delete();
     //   $orderFullRefunded->delete();


        Configuration::deleteByName('RAIF_STATUS_WAITING');

        return true;
    }



    /**
     * Generates a HTML Form that is used on the module configuration page.
     */
    public function displayForm()
    {
        
        $this->context->smarty->assign([
            'qiwi_notification' => $this->context->link->getModuleLink($this->name, 'webhook', array(), true),
        ]);
        $description = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/description.tpl');
        $notification = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/notification.tpl');
       /// $description = 'RAIF 1';
       // $notification = 'RAIF 2';
        $fields_form = [0 => []];
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'description' => $description,
            'input' => [
                [
                    'type' => 'html',
                    'label' => 'URL для настройки callback',
                    'html_content' => $notification,
                    'desc' => $this->l('Set this value in the payment system store settings.'),
                    'name' => 'QIWI_NOTIFICATION',
                ],
                [
                    'type' => 'text',
                    'label' => 'Название',
                    'desc' => 'Название способа оплаты',
                    'name' => 'RAIF_PAY_NAME',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => 'Описание',
                    'desc' => 'Описание способа оплаты',
                    'name' => 'RAIF_PAY_DESCR',
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => 'Public ID',
                    'desc' => '',
                    'name' => 'RAIF_PAY_PUBID',
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => 'Секретный ключ',
                    'desc' => '',
                    'name' => 'RAIF_PAY_SECKEY',
                    'required' => false,
                ],
                array(
                    'type' => 'select',
                    'label' => 'НДС',
                    'name' => 'RAIF_PAY_NDS',
                    'required' => false,
                    'options' => array(
                        'query' => $idevents = array(
                            array(
                                'idevents' => 'NONE',
                                'name' => 'без НДС'
                            ),
                            array(
                                'idevents' => 'VAT0',
                                'name' => 'НДС по ставке 0%'
                            ),
                            array(
                                'idevents' => 'VAT10',
                                'name' => 'НДС чека по ставке 10%'
                            ),
                            array(
                                'idevents' => 'VAT110',
                                'name' => 'НДС чека по расчетной ставке 10/110'
                            ),
                            array(
                                'idevents' => 'VAT20',
                                'name' => 'НДС чека по ставке 20%'
                            ),
                            array(
                                'idevents' => 'VAT120',
                                'name' => 'НДС чека по расчетной ставке 20/120'
                            ),
                        ),
                        'id' => 'idevents',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'checkbox',
                    'label' => 'Всплывающая форма оплаты',
                    'desc' => '',
                    'name' => 'RAIF_PAY_POPUP',
                    'values' => array(
                        'query' => $lesChoix = array(
                            array(
                                'check_id' => '1',
                                'name' => '',
                            ),
                        ),
                        'id' => 'check_id',
                        'name' => 'name',
                        'desc' => $this->l('Please select')
                    ),
                ),
                [
                    'type' => 'textarea',
                    'label' => 'Стили для фомы оплаты',
                    'desc' => 'CSS стили для формы оплаты. Измените внешний вид формы в конструкторе и перенесите код в эту форму. (https://e-commerce.raiffeisen.ru/pay/configurator/#/)',
                    'name' => 'RAIF_PAY_FROM_CSS',
                    'required' => false,
                ],
                array(
                    'type' => 'checkbox',
                    'label' => 'Фискализация чеков',
                    'desc' => '',
                    'name' => 'RAIF_PAY_FISCHECK',
                    'values' => array(
                        'query' => $lesChoix = array(
                            array(
                                'check_id' => '1',
                                'name' => '',
                            ),
                        ),
                        'id' => 'check_id',
                        'name' => 'name',
                        'desc' => $this->l('Please select')
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => 'Способы оплаты',
                    'name' => 'RAIF_PAY_METHODS',
                    'required' => false,
                    'options' => array(
                        'query' => $idevents = array(
                            array(
                                'idevents' => '-1',
                                'name' => 'Все'
                            ),
                            array(
                                'idevents' => 'ONLY_SBP',
                                'name' => 'СБП'
                            ),
                            array(
                                'idevents' => 'ONLY_ACQUIRING',
                                'name' => 'Банковская карта'
                            )
                        ),
                        'id' => 'idevents',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'checkbox',
                    'label' => 'Тестовый режим',
                    'desc' => '',
                    'name' => 'RAIF_PAY_TESTPAY',
                    'values' => array(
                        'query' => $lesChoix = array(
                            array(
                                'check_id' => '1',
                                'name' => '',
                            ),
                        ),
                        'id' => 'check_id',
                        'name' => 'name',
                        'desc' => $this->l('Please select')
                    ),
                ),

            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];


        $helper = new HelperForm();
        $helper->submit_action = 'update_settings_' . $this->name;

        // Sets current value from DB to the form.
        $helper->fields_value['RAIF_PAY_NAME'] = Configuration::get('RAIF_PAY_NAME');
        $helper->fields_value['RAIF_PAY_DESCR'] = Configuration::get('RAIF_PAY_DESCR');
        $helper->fields_value['RAIF_PAY_PUBID'] = Configuration::get('RAIF_PAY_PUBID');
        $helper->fields_value['RAIF_PAY_SECKEY'] = Configuration::get('RAIF_PAY_SECKEY');
        $helper->fields_value['RAIF_PAY_NDS'] = Configuration::get('RAIF_PAY_NDS');
        $helper->fields_value['RAIF_PAY_POPUP'] = Configuration::get('RAIF_PAY_POPUP');
        $helper->fields_value['RAIF_PAY_POPUP_1'] = Configuration::get('RAIF_PAY_POPUP_1');
        $helper->fields_value['RAIF_PAY_FROM_CSS'] = Configuration::get('RAIF_PAY_FROM_CSS');
        $helper->fields_value['RAIF_PAY_FISCHECK'] = Configuration::get('RAIF_PAY_FISCHECK');
        $helper->fields_value['RAIF_PAY_FISCHECK_1'] = Configuration::get('RAIF_PAY_FISCHECK_1');
        $helper->fields_value['RAIF_PAY_METHODS'] = Configuration::get('RAIF_PAY_METHODS');
        $helper->fields_value['RAIF_PAY_TESTPAY'] = Configuration::get('RAIF_PAY_TESTPAY');
        $helper->fields_value['RAIF_PAY_TESTPAY_1'] = Configuration::get('RAIF_PAY_TESTPAY_1');


        return $helper->generateForm($fields_form);
    }
}
