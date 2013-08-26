<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrasweb
 * @license    OSL v3.0
 */
class Xpd_Paybrasweb_Model_Source_Environment
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 0,
                'label' => 'Sandbox'
            ),
            array(
                'value' => 1,
                'label' => 'Produção'
            ),
        );
    }
}

