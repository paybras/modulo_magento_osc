<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrastef
 * @license    OSL v3.0
 */
class Xpd_Paybrastef_Model_Source_Tef
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'tef_bb',
                'label' => 'Banco do Brasil'
            ),
            array(
                'value' => 'tef_itau',
                'label' => 'Itau'
            ),
            array(
                'value' => 'tef_bradesco',
                'label' => 'Bradesco'
            ),
        );
    }
}