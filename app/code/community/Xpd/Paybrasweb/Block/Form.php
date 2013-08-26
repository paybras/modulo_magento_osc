<?php
/**
 * Paybras Web
 *
 * @category   Payments
 * @package    Xpd_Paybrasweb
 * @license    OSL v3.0
 */
class Xpd_Paybrasweb_Block_Form extends Mage_Payment_Block_Form {
    
	protected function _construct() {
        parent::_construct();
        $this->setTemplate('xpd/paybras/form/web.phtml');
    }
	
}