<?php
include_once('../../raifpay.php');
require_once(dirname(__FILE__).'../../../config/config.inc.php');
require_once(dirname(__FILE__).'../../../init.php');
//$instance = new raifpay();

/*if (!Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $instance->secure_key || !Tools::getValue('action')) {
	die(1);
}*/
if (Tools::getValue('action') == 'test') {
	//$instance->ajaxProcessTest();
    Tools::jsonEncode('test');
}