<?php
/**
 * @author    АО Райффайзенбанк <ecom@raiffeisen.ru>
 * @copyright 2007 АО Райффайзенбанк
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt The GNU General Public License version 2 (GPLv2)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// Autoload for standalone composer build.
if (!class_exists('OviDigital\JsObjectToJson\JsConverter')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ovidigital' . DIRECTORY_SEPARATOR . 'js-object-to-json' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'JsConverter.php';
}

if (!class_exists('Raiffeisen\Ecom\Client')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'raiffeisen-ecom' . DIRECTORY_SEPARATOR . 'payment-sdk' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Client.php';
}

class Raifpay extends PaymentModule
{
    public $is_eu_compatible = 1;

    public function __construct()
    {
        $this->name = 'raifpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->author = 'Raif Pay';
        $this->controllers = ['validation', 'webhook'];
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Raiffeisenbank');
        $this->description = $this->l('Raiffeisenbank payment module');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        if (null == Configuration::get('RAIF_PAY_NAME') || null == Configuration::get('RAIF_STATUS_WAITING')) {
            $orderWaiting = $this->createOrderStatus(
                $this->l('Raiffeisenbank - waiting for payment'),
                '#0042FF'
            );
            $orderPaid = $this->createOrderStatus(
                $this->l('Raiffeisenbank - paid'),
                '#00ff05'
            );
            $orderPartialRefunded = $this->createOrderStatus(
                $this->l('Raiffeisenbank - partial return'),
                '#ff4f00'
            );
            $orderFullRefunded = $this->createOrderStatus(
                $this->l('Raiffeisenbank - return'),
                '#ff0000'
            );
            Configuration::updateValue('RAIF_PAY_NAME', 'Raif Pay');
            Configuration::updateValue('RAIF_STATUS_WAITING', $orderWaiting->id);
            Configuration::updateValue('RAIF_STATUS_PART_REFUNDED', $orderPartialRefunded->id);
            Configuration::updateValue('RAIF_STATUS_PAID', $orderPaid->id);
            Configuration::updateValue('RAIF_STATUS_FULL_REFUNDED', $orderFullRefunded->id);
        }

        $this->registerHook('actionProductCancel');
    }

    public function hookActionProductCancel($params) {
        $publicId = Configuration::get('RAIF_PAY_PUBID');
        $secretKey = Configuration::get('RAIF_PAY_SECKEY');
        $api_url = 'https://pay.raif.ru';
        if ('on' == Configuration::get('RAIF_PAY_TESTPAY_1')) {
            $api_url = 'https://pay-test.raif.ru';
        }

        $ecomClient = new \Raiffeisen\Ecom\Client($secretKey, $publicId, $api_url);
        $order_id = $params['order']->id;
        $amount = (float) $params['cancel_amount'];
        foreach (OrderDetail::getList((int)$order_id) as $val) {
            if ($params['id_order_detail'] == $val['id_order_detail']) {

            }
        }

        $responce = $ecomClient->postOrderRefund(
            $order_id,
            $order_id,
            number_format($amount, 2, '.', '')
        );
        if ('SUCCESS' == $responce['code']) {
            $order = new Order($order_id);
            $order->setCurrentState((int)Configuration::get('RAIF_STATUS_PART_REFUNDED'));
        }
        //*/
    }

    public function createOrderStatus($name, $color)
    {
        $order = new OrderState();
        $order->name = array_fill(0, 10, $name);
        $order->send_email = false;
        $order->invoice = false;
        $order->module_name = 'raifpay';
        $order->color = $color;
        $order->unremovable = false;
        $order->logable = true;
        $order->save();

        return $order;
    }
    public function install()
    {
        $this->registerHook('actionProductCancel');
        if (!parent::install() || !$this->registerHook('paymentOptions')) {
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
                      ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/payment.jpg'));

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
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/payment.jpg'));

        return $externalOption;
    }

    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $embeddedOption->setCallToActionText(Configuration::get('RAIF_PAY_NAME'))
                       ->setForm($this->generateForm())
                       ->setAdditionalInformation($this->context->smarty->fetch('module:raifpay/views/templates/front/payment_infos.tpl'));

        return $embeddedOption;
    }

    protected function generateForm()
    {

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'descr' => Configuration::get('RAIF_PAY_DESCR'),
            'modal' => 'on' == Configuration::get('RAIF_PAY_TESTPAY_1')
                ? 'https://pay-test.raif.ru/pay'
                : 'https://pay.raif.ru/pay',
        ]);

        return $this->context->smarty->fetch('module:raifpay/views/templates/front/payment_form.tpl');
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('update_settings_' . $this->name)) {
            Configuration::updateValue('RAIF_PAY_NAME', (string) Tools::getValue('RAIF_PAY_NAME'));
            Configuration::updateValue('RAIF_PAY_DESCR', (string) Tools::getValue('RAIF_PAY_DESCR'));
            Configuration::updateValue('RAIF_PAY_PUBID', (string) Tools::getValue('RAIF_PAY_PUBID'));
            Configuration::updateValue('RAIF_PAY_SECKEY', (string) Tools::getValue('RAIF_PAY_SECKEY'));
            Configuration::updateValue('RAIF_PAY_NDS', (string) Tools::getValue('RAIF_PAY_NDS'));
            Configuration::updateValue('RAIF_PAY_POPUP', (string) Tools::getValue('RAIF_PAY_POPUP'));
            Configuration::updateValue('RAIF_PAY_POPUP_1', (string) Tools::getValue('RAIF_PAY_POPUP_1'));
            Configuration::updateValue('RAIF_PAY_FISCHECK', (string) Tools::getValue('RAIF_PAY_FISCHECK'));
            Configuration::updateValue('RAIF_PAY_FISCHECK_1', (string) Tools::getValue('RAIF_PAY_FISCHECK_1'));
            Configuration::updateValue('RAIF_PAY_METHODS', (string) Tools::getValue('RAIF_PAY_METHODS'));
            Configuration::updateValue('RAIF_PAY_TESTPAY', (string) Tools::getValue('RAIF_PAY_TESTPAY'));
            Configuration::updateValue('RAIF_PAY_TESTPAY_1', (string) Tools::getValue('RAIF_PAY_TESTPAY_1'));
            $style = stripcslashes((string) Tools::getValue('RAIF_PAY_FROM_CSS'));
            if (!empty($style)) {
                $style = OviDigital\JsObjectToJson\JsConverter::convertToArray($style);
                if (!empty($style)) {
                    $style = json_encode($style);
                } else {
                    $style = '';
                }
            }

            Configuration::updateValue('RAIF_PAY_FROM_CSS', $style);

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output . $this->displayForm();
    }

    public function uninstall()
    {
        Configuration::deleteByName('RAIF_STATUS_WAITING');

        return true;
    }

    public function displayForm()
    {
        $this->context->smarty->assign([
            'qiwi_notification' => $this->context->link->getModuleLink($this->name, 'webhook', array(), true),
        ]);
        $notification = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/notification.tpl');
        $fields_form = [0 => []];
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'description' => '',
            'input' => [
                [
                    'type' => 'html',
                    'label' => $this->l('URL to configure callback'),
                    'html_content' => $notification,
                    'desc' => $this->l('Set this value in the payment system store settings.'),
                    'name' => 'QIWI_NOTIFICATION',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'desc' => $this->l('Payment method name'),
                    'name' => 'RAIF_PAY_NAME',
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Description'),
                    'desc' => $this->l('Description of payment method'),
                    'name' => 'RAIF_PAY_DESCR',
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Public ID'),
                    'name' => 'RAIF_PAY_PUBID',
                    'required' => false,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Secret key'),
                    'desc' => '',
                    'name' => 'RAIF_PAY_SECKEY',
                    'required' => false,
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('VAT'),
                    'desc' => '',
                    'name' => 'RAIF_PAY_NDS',
                    'required' => false,
                    'options' => [
                        'query' => [
                            [
                                'idevents' => 'NONE',
                                'name' => $this->l('Without VAT'),
                            ],
                            [
                                'idevents' => 'VAT0',
                                'name' => $this->l('VAT at 0% rate'),
                            ],
                            [
                                'idevents' => 'VAT10',
                                'name' => $this->l('VAT on the receipt at the rate of 10%'),
                            ],
                            [
                                'idevents' => 'VAT110',
                                'name' => $this->l('VAT receipt at the estimated rate of 10/110'),
                            ],
                            [
                                'idevents' => 'VAT20',
                                'name' => $this->l('VAT on the receipt at the rate of 20%'),
                            ],
                            [
                                'idevents' => 'VAT120',
                                'name' => $this->l('VAT receipt at the estimated rate of 20/120'),
                            ],
                        ],
                        'id' => 'idevents',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'checkbox',
                    'label' => $this->l('Pop-up payment form'),
                    'desc' => '',
                    'name' => 'RAIF_PAY_POPUP',
                    'values' => [
                        'query' => [
                            [
                                'check_id' => '1',
                                'name' => '',
                            ],
                        ],
                        'id' => 'check_id',
                        'name' => 'name',
                        'desc' => $this->l('Please select')
                    ],
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Payment form styles'),
                    'desc' => $this->l('CSS styles for the payment form. Change the appearance of the form in the designer and transfer the code to this form. (https://pay.raif.ru/pay/configurator/#/)'),
                    'name' => 'RAIF_PAY_FROM_CSS',
                    'required' => false,
                ],
                [
                    'type' => 'checkbox',
                    'label' => $this->l('Fiscalization of checks'),
                    'desc' => '',
                    'name' => 'RAIF_PAY_FISCHECK',
                    'values' => [
                        'query' => [
                            [
                                'check_id' => '1',
                                'name' => '',
                            ],
                        ],
                        'id' => 'check_id',
                        'name' => 'name',
                        'desc' => $this->l('Please select'),
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Payment methods'),
                    'desc' => '',
                    'name' => 'RAIF_PAY_METHODS',
                    'required' => false,
                    'options' => [
                        'query' => [
                            [
                                'idevents' => '-1',
                                'name' => $this->l('All'),
                            ],
                            [
                                'idevents' => 'ONLY_SBP',
                                'name' => $this->l('SBP'),
                            ],
                            [
                                'idevents' => 'ONLY_ACQUIRING',
                                'name' => $this->l('Bank card'),
                            ],
                        ],
                        'id' => 'idevents',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'checkbox',
                    'label' => $this->l('Test mode'),
                    'desc' => '',
                    'name' => 'RAIF_PAY_TESTPAY',
                    'values' => [
                        'query' => [
                            [
                                'check_id' => '1',
                                'name' => '',
                            ],
                        ],
                        'id' => 'check_id',
                        'name' => 'name',
                        'desc' => $this->l('Please select'),
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
            ],
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
