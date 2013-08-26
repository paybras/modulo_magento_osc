<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybras
 * @license    OSL v3.0
 */
class Xpd_Paybras_Model_Source_Dob
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 0,
                'label' => 'Não exibir'
            ),
            array(
                'value' => 1,
                'label' => 'Exibir (Obrigatório)'
            ),
        );
    }
}

