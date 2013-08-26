<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrasweb
 * @license    OSL v3.0
 */
class Xpd_Paybrasweb_Model_Source_Web
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'BOL',
                'label' => 'Boleto'
            ),
            array(
                'value' => 'TEF',
                'label' => 'Débito (TEF)'
            ),
            array(
                'value' => 'CC',
                'label' => 'Cartões de Crédito'
            ),
        );
    }
}