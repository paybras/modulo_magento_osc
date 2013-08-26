<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybras
 * @license    OSL v3.0
 */
class Xpd_Paybras_Model_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
  public function getAllowedTypes()
  {
      return array('VI', 'MC');
  }
}

