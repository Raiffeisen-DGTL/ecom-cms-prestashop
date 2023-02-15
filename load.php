<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('CLIENT_NAME')) {
    /**
     * The client name fingerprint.
     *
     * @const string
     */
    define('CLIENT_NAME', 'prestashop');
}

if (!defined('CLIENT_VERSION')) {
    /**
     * The client version fingerprint.
     *
     * @const string
     */
    define('CLIENT_VERSION', '0.0.3');
}

// Autoload for standalone composer build.
if (!class_exists('Curl\Curl')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'curl' . DIRECTORY_SEPARATOR . 'curl' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Curl' . DIRECTORY_SEPARATOR . 'Curl.php';
}


if (!class_exists('Raiffeisen\Ecom\Client')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'raiffeisen-ecom' . DIRECTORY_SEPARATOR . 'payment-sdk' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Client.php';
}

