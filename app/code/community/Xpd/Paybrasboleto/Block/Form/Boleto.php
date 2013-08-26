<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrasboleto
 * @license    OSL v3.0
 */
class Xpd_Paybrasboleto_Block_Form_Boleto extends Mage_Payment_Block_Form {

    /**
     * Especifica template.
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('xpd/paybrasboleto/form/boleto.phtml');
    }

}