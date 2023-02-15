<?php
class raifpayGetDataModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();
    }

    public function displayAjaxPostProcess() {

        //OrderDetail::getList((int)$params['id_order'])
        $conf = Configuration::get('RAIF_SECRET_KEY');
        echo Tools::jsonEncode($conf);
    }
}