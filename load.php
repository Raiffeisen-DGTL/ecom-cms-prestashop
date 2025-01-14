<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('CLIENT_NAME')) {
    define('CLIENT_NAME', 'prestashop');
}

if (!defined('CLIENT_VERSION')) {
    define('CLIENT_VERSION', '0.0.3');
}

if (!class_exists('OviDigital\JsObjectToJson\JsConverter')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ovidigital' . DIRECTORY_SEPARATOR . 'js-object-to-json' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'JsConverter.php';
}

if (!class_exists('Raiffeisen\Ecom\Client')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'raiffeisen-ecom' . DIRECTORY_SEPARATOR . 'payment-sdk' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Client.php';
}
