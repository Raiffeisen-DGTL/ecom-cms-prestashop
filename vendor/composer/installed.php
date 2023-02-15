<?php return array(
    'root' => array(
        'name' => 'prestashop/paymentexample',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => NULL,
        'type' => 'prestashop-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'prestashop/paymentexample' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => NULL,
            'type' => 'prestashop-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'raiffeisen-ecom/payment-sdk' => array(
            'pretty_version' => 'v1.1.43',
            'version' => '1.1.43.0',
            'reference' => '5bf4539c05484c2fe29606ba6a8e36881a9e22ae',
            'type' => 'library',
            'install_path' => __DIR__ . '/../raiffeisen-ecom/payment-sdk',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
