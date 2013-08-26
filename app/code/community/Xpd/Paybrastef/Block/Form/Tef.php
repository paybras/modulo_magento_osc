<?php
/**
 * Paybras 
 *
 * @category   Payments
 * @package    Xpd_Paybrastef
 * @license    OSL v3.0
 */
class Xpd_Paybrastef_Block_Form_Tef extends Mage_Payment_Block_Form {

    /**
     * Especifica template.
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('xpd/paybrastef/form/tef.phtml');
    }

}