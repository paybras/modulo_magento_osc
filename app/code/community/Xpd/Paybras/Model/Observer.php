<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybras
 * @license    OSL v3.0
 */
class Xpd_Paybras_Model_Observer extends Varien_Event_Observer {
    
    public function __construct() {
    
    }

    /**
     * Consulta o parcelamento para paybras
     * 
     */
    public function updateParcelamento($observer) {
        $paybras = Mage::getSingleton('paybras/standard');
        
        if($paybras->getEnvironment()) {
            $url = 'https://service.paybras.com/payment/getParcelas';
        }
        else {
            $url = 'https://sandbox.paybras.com/payment/getParcelas';
        }
        
        /*$quote = $observer['quote'];
        $totals = $quote->getTotals();
        $subtotal = $totals["subtotal"]->getValue();
        
        $methodEscolhido = $quote->getShippingAddress()->getShippingMethod();
        $allMethods = $quote->getShippingAddress()->getShippingRatesCollection();
        
        foreach($allMethods as $rate) {
            if($methodEscolhido == $rate->getCode()) {
                $subtotal += $rate->getPrice();
            }
        }*/
        $subtotal = $observer['quote']->getGrandTotal();
        
        $fields = Array();
        $fields['recebedor_email'] = $paybras->getEmailStore();
        $fields['recebedor_api_token'] = $paybras->getToken();
        $fields['pedido_valor_total'] = $subtotal;
        
        $curlAdapter = new Varien_Http_Adapter_Curl();
        $curlAdapter->setConfig(array('timeout'   => 20));
        //$curlAdapter->connect(your_host[, opt_port, opt_secure]);
        $curlAdapter->write(Zend_Http_Client::POST, $url, '1.1', array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
        $resposta = $curlAdapter->read();
        $retorno = substr($resposta,strpos($resposta, "\r\n\r\n"));
        $curlAdapter->close();
        Mage::getSingleton('core/session')->unsMyParcelamento($retorno);
        Mage::getSingleton('core/session')->setMyParcelamento($retorno);
    }
}
?>