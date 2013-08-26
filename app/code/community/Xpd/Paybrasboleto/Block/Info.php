<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybraspaybrasboleto
 * @license    OSL v3.0
 */
class Xpd_Paybrasboleto_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpd/paybrasboleto/info.phtml');
    }

	/**
     * Recebe instancia corrente de order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder() {
        $order = Mage::registry('current_order');
		$info = $this->getInfo();

		if (!$order) {
			if ($this->getInfo() instanceof Mage_Sales_Model_Order_Payment) {
				$order = $this->getInfo()->getOrder();
			}
		}
        
        /*if(!$order) {
            $paybras = Mage::getSingleton('paybras/standard');
            $order = $paybras->getOrder(); 
        }*/

		return $order;
    }
    
    /**
     * Recupera ID da transação junto a Paybras
     * 
     * @return string
     */
    public function returnTransaction() {
        $order = $this->getOrder();
        if(isset($order)) {
            return $order->getPayment()->getPaybrasTransactionId() ? $order->getPayment()->getPaybrasTransactionId() : $this->getInfo()->getPaybrasTransactionId();
        }
        else {
            return NULL;
        }
    }
    
    /**
     * Recupera URL de destino para redirect
     *  
     * @return string
     */
    public function returnUrlToRedirect() {
        $order = $this->getOrder();
        if(Mage::getSingleton('checkout/session')->getUrlRedirect()) {
            return Mage::getSingleton('checkout/session')->getUrlRedirect();
        } elseif(isset($order)  && ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED && $order->getState() != Mage_Sales_Model_Order::STATE_PROCESSING && $order->getState() != Mage_Sales_Model_Order::STATE_COMPLETE)) {
            $payment = $order->getPayment();
            //Mage::log('URL DO PAYMENT: ' . $payment->getPaybrasTransactionId());
            return $payment->getPaybrasOrderId();
        }
        else {
            return NULL;
        }
    }
    
    /**
     * Gera informações do pagamento para admin.
     */
    protected function _prepareInfo()
    {
        $paybras = Mage::getSingleton('paybrasboleto/standard');
        if (!$order = $this->getInfo()->getOrder()) {
            $order = $this->getInfo()->getQuote();
        }
                
        $transactionId = $this->getInfo()->getPaybrasTransactionId();
        $url_redirect = $this->getInfo()->getPaybrasOrderId();
        $data = $this->getInfo()->getAdditionalData();
        $data = unserialize($data);
        
        $paymentMethod = $data['forma_pagamento'];
        
        if ($paymentMethod == 'boleto' && ($order->getState() == Mage_Sales_Model_Order::STATE_HOLDED || $order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)) {
            $paymentMethod .= ' (<a href="' . $url_redirect . '" onclick="this.target=\'_blank\'">Segunda Via do Boleto</a>)';
        }
        
        if ($paymentMethod == 'tef_bb' && ($order->getState() == Mage_Sales_Model_Order::STATE_HOLDED || $order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)) {
            
            if($data['tef_banco'] == 'tef_bb') {
                $paymentMethod .= ' (<a href="' . $url_redirect . '" onclick="this.target=\'_blank\'">Página do BB - TEF</a>)';
            } elseif($data['tef_banco'] == 'tef_itau') {
                $paymentMethod .= ' (<a href="' . $url_redirect . '" onclick="this.target=\'_blank\'">Página do Itaú - TEF</a>)';
            } else if($data['tef_banco'] == 'tef_bradesco') {
                $paymentMethod .= ' (<a href="' . $url_redirect . '" onclick="this.target=\'_blank\'">Página do Bradesco - TEF</a>)';
            }
        }
                
        $this->addData(array(
            'show_paylink' => (boolean) !$transactionId && $order->getState() == Mage_Sales_Model_Order::STATE_NEW,
            'pay_url' => $url_redirect,
            'show_info' => (boolean) $transactionId,
            'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
        ));
    }
}

