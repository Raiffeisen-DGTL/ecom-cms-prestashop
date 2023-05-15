<?php
include_once('raifpay.php');
require_once('../../config/config.inc.php');
require_once('../../init.php');
$instance = new raifpay();

/*if (!Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $instance->secure_key || !Tools::getValue('action')) {
	die(1);
}*/
if (Tools::getIsset('action') == 'test') {
	//$instance->ajaxProcessTest();
    echo Tools::jsonEncode('test');
    exit();
}