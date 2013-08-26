<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrasweb
 * @license    OSL v3.0
 */
class Xpd_Paybrasweb_StandardController extends Mage_Core_Controller_Front_Action {

    /**
     * Header de Sessão Expirada
     *
     */
    protected function _expireAjax() {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    /**
     * Retorna singleton do Model do Módulo.
     *
     * @return Xpd_Paybras_Model_Standard
     */
    public function getStandard() {
        return Mage::getSingleton('paybrasweb/standard');
    }
    
    /**
     * Processa pagamento - cria transação via WebService 
     * 
     */
    protected function redirectAction() {
        $paybras = $this->getStandard();
        $session = Mage::getSingleton('checkout/session');
        $order = $paybras->getOrder();        
        $session->unsUrlRedirect();
        
        if($paybras->getEnvironment() == '1') {
            $url = 'https://service.paybras.com/payment/checkoutWeb';
        }
        else {
            $url = 'https://sandbox.paybras.com/payment/checkoutWeb';
        }
        
        $orderId = $order->getId();
        
        if(!$orderId) {
            $orders = Mage::getModel('sales/order')->getCollection()
                 ->setOrder('increment_id','DESC')
                 ->setPageSize(1)
                 ->setCurPage(1);
            $order = $orders->getFirstItem();
            $orderId = $orders->getFirstItem()->getEntityId();
        }
        
        $payment = $order->getPayment();
        
        if($order->getCustomerId()) {
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        }
        else {
            $customer = false;
        }
        
        $order = $paybras->changeState($order,1,NULL,"Aguardando Pagamento, pedido: ".$order->getRealOrderId());
		$order->save();

        $fields = $paybras->dataTransaction($customer,$order,$payment);
        
        $url_redirect = Mage::getBaseUrl() . 'paybrasweb/standard/pagamento/order_id/'.$orderId;
        $session->setUrlRedirect($url_redirect);
        $payment->setPaybrasOrderId($url_redirect)->save();
               
		if ($orderId) {
            if(!$order->getEmailSent()) {
            	$order->sendNewOrderEmail();
    			$order->setEmailSent(true);
    			$order->save();
                $paybras->log("Email do Pedido $orderId Enviado");
            }
        }
        
        $session->setOrderFields($fields);
        $session->setUrlAmbiente($url);
        
        $this->getResponse()->setBody($this->getLayout()->createBlock('paybrasweb/standard_redirect')->toHtml());
    }
    	
    /**
     * Nova tentativa de pagamento
     * 
     */
    public function pagamentoAction() {
		Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*'));
        $paybras = Mage::getSingleton('paybrasweb/standard');
		$session = Mage::getSingleton('checkout/session');
        
        if($this->getRequest()->getParam('order_id')) {
            $orderId = $this->getRequest()->getParam('order_id');
            $paybras->log('Tentativa de Repagamento, pedido: '.$orderId);
        }
        else {
            die();
        }
        
        if($paybras->getEnvironment() == '1') {
            $url = 'https://service.paybras.com/payment/checkoutWeb';
        }
        else {
            $url = 'https://sandbox.paybras.com/payment/checkoutWeb';
        }
        
        if(strlen((string)$orderId)<9) {
            $order = Mage::getModel('sales/order')->load((int)$orderId);
        }
        else {
            $order = Mage::getModel('sales/order')
                  ->getCollection()
                  ->addAttributeToFilter('increment_id', $orderId)
                  ->getFirstItem();
        }
        
		$order_payment = $order->getPayment();
		
        if($order && $paybras->getCode() == $order_payment->getMethodInstance()->getCode()) {
            switch ($order->getState()) {
                case Mage_Sales_Model_Order::STATE_PROCESSING:
                    $order_redirect = false;
                    break;
				case Mage_Sales_Model_Order::STATE_COMPLETE:
                    $order_redirect = false;
                    break;
                case Mage_Sales_Model_Order::STATE_HOLDED:
                    $order_redirect = false;
                    break;
				case Mage_Sales_Model_Order::STATE_CLOSED:
                    $order_redirect = false;
                    break;
				case Mage_Sales_Model_Order::STATE_CANCELED:
                    $order_redirect = false;
                    break;
                default:
                    $order_redirect = true;
					$session->setPayOrderId($orderId);
                    break;
            }
        }
        else {
            $order_redirect = false;
        }
		
        if($order_redirect === false) {
            $this->_redirect('');
        }
        else {
			$payment = $order->getPayment();
			$orderRealId = $order->getRealOrderId();
            
            $fields = $paybras->dataTransaction($customer,$order,$payment,1);
            
            $session->setOrderFields($fields);
            $session->setUrlAmbiente($url);
            
            $this->getResponse()->setBody($this->getLayout()->createBlock('paybrasweb/standard_redirect')->toHtml());
        }
    }
    
    /**
     * Captura Notificação do Pagamento
     * 
     */
    public function capturaAction() {
        if($this->getRequest()->isPost() && Mage::getStoreConfig('payment/paybrasweb/notification')) {
            $paybras = $this->getStandard();
            $json = $_POST['data'];
            $paybras->log($json);
            
            if(!$json) {
                $json = $_POST;
                $transactionId = $json['transacao_id'];
                $pedidoId = $json['pedido_id'];
                $pedidoIdVerifica = $pedidoId;
                $valor = $json['valor_original'];
                $status_codigo = $json['status_codigo'];
                $status_nome = $json['status_nome'];
                $recebedor_api = $json['recebedor_api_token'];
            }
            else {
                $json = json_decode($json);
                $transactionId = $json->{'transacao_id'};
                $pedidoId = $json->{'pedido_id'};
                $pedidoIdVerifica = $pedidoId;
                $valor = $json->{'valor_original'};
                $status_codigo = $json->{'status_codigo'};
                $status_nome = $json->{'status_nome'};
                $recebedor_api = $json->{'recebedor_api_token'};
            }
            $paybras = $this->getStandard();
            
            $paybras->log('Pedido ID: '.$pedidoId);
            $paybras->log('Status: '.$status_codigo);
            $paybras->log('Transaction ID: '.$transactionId);
			
            if($transactionId && $status_codigo && $pedidoId) {
                if(strpos($pedidoId,'_') !== false) {
                    $pedido = explode("_",$pedidoId);
                    $orderId = $pedido[0];
                }
                else {
                    $orderId = $pedidoId;
                }
                
                $order = Mage::getModel('sales/order')
                  ->getCollection()
                  ->addAttributeToFilter('increment_id', $orderId)
                  ->getFirstItem();
				  
				if(!$order) {
					$order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
				}
                
                $status = (int)$status_codigo;
				
				$paybras->log($order->getId());
				
                if($paybras->getEnvironment() == '1') {
                    $url = 'https://service.paybras.com/payment/getStatus';
                }
                else {
                    $url = 'https://sandbox.paybras.com/payment/getStatus';
                }
                
                $fields = array(
                    'recebedor_email' => $paybras->getEmailStore(),
                    'recebedor_api_token' => $paybras->getToken(),
                    'transacao_id' => $transactionId,
                    'pedido_id' => $pedidoId
                );
				
                $curlAdapter = new Varien_Http_Adapter_Curl();
                $curlAdapter->setConfig(array('timeout' => 20));
                $curlAdapter->write(Zend_Http_Client::POST, $url, '1.1',  array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
                $resposta = $curlAdapter->read();
                $retorno = substr($resposta,strpos($resposta, "\r\n\r\n"));
                $curlAdapter->close();
                
                $json = json_decode($retorno);
                if($json->{'sucesso'} == '1') {
                    if($json->{'pedido_id'} == $pedidoIdVerifica && $json->{'valor_original'} == $valor && $json->{'status_codigo'} == $status_codigo) {
                        $result = $paybras->processStatus($order,$status,$transactionId);
                        //if($result >= 0) {
                            echo '{"retorno":"ok"}';
							$paybras->log('{"retorno":"ok"}');
                        //}
                    }
					else {
						$paybras->log('Informações do pedido não bateram');
					}
                }
                else {
                    $paybras->log('Erro resposta de Consulta');
                }
            }
            else {
                $paybras->log('Erro na Captura - Nao foi possivel pergar os dados');
                $paybras->log($json);
                echo 'Erro na Captura - Nao foi possivel pergar os dados';
            }
			
			$paybras->log('Fim da Captura');
        }
    }
	
	/**
     * Exibe tela de sucesso após tentativa de repagamento
     * 
     */
    public function retornoAction() {
		$session = Mage::getSingleton('core/session');
		$paybras = Mage::getSingleton('paybrasweb/standard');
		$orderId = $session->getPayOrderId();
        
		if(strlen((string)$orderId)<9) {
			$order = Mage::getModel('sales/order')->load((int)$orderId);
		}
		else {
			$order = Mage::getModel('sales/order')
				  ->getCollection()
				  ->addAttributeToFilter('increment_id', $orderId)
				  ->getFirstItem();
		}
		
		$this->loadLayout();
		$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');			
		$block = $this->getLayout()->createBlock('Xpd_Paybrasweb_Block_Standard_Success','block_web_standard_success',array('template' => 'xpd/paybras/standard/success.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
		$this->renderLayout();
    }
}
