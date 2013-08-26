<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybras
 * @license    OSL v3.0
 */
class Xpd_Paybras_Block_Standard_Failure extends Mage_Core_Block_Template {
	
	protected function _construct() {
        parent::_construct();
        $this->setTemplate('xpd/paybras/standard/failure.phtml');
    }
	
}