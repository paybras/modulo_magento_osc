<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybras
 * @license    OSL v3.0
 */
class Xpd_Paybras_StandardController extends Mage_Core_Controller_Front_Action {

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
        return Mage::getSingleton('paybras/standard');
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
            $url = 'https://service.paybras.com/payment/api/criaTransacao';
        }
        else {
            $url = 'https://sandbox.paybras.com/payment/api/criaTransacao';
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

        $fields = $paybras->dataTransaction($customer,$order,$payment);
        Mage::log(json_encode($fields));
        $curlAdapter = new Varien_Http_Adapter_Curl();
        $curlAdapter->setConfig(array('timeout'   => 20));
        $curlAdapter->write(Zend_Http_Client::POST, $url, '1.1', array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
        $resposta = $curlAdapter->read();
        $retorno = substr($resposta,strpos($resposta, "\r\n\r\n"));
        $curlAdapter->close();
        //Mage::log($retorno);
        if(function_exists('json_decode')) {
            $json_php = json_decode($retorno);
            Mage::log($retorno);
            if($json_php->{'sucesso'} == '1') {
                $paybras->log('True para consulta');
                $flag = true;
            }
            else {
                if($json_php->{'sucesso'} == '0') {
                    $code_erro = $json_php->{'mensagem_erro'};
                    $error_msg = Mage::helper('paybras')->msgError($code_erro);
                    $paybras->log('False para consulta. Erro: '.$error_msg);
                    $flag = false;
                }
                else {
                    $paybras->log('Null para consulta '. $json_php->{'code'});
                    $flag = NULL;
                }
            }
        }
        else {
            $paybras->log('[ Function Json_Decode does not exist! Upgrade PHP ]');
        }
        
        if($flag) {
            $transactionId = $json_php->{'transacao_id'};
            $status_codigo = $json_php->{'status_codigo'};
            
            $payment->setPaybrasTransactionId(utf8_encode($transactionId))->save();
            $paybras->processStatus($order,$status_codigo,$transactionId);
            
            $session->setFormaPag($fields['pedido_meio_pagamento']);
			$session->setStatePag($paybras->convertStatus($status_codigo));
            
            if($fields['pedido_meio_pagamento'] == 'boleto' || $fields['pedido_meio_pagamento'] == 'tef_bb') {
                $url_redirect = utf8_decode($json_php->{'url_pagamento'});
                if($url_redirect) {
                    $session->setUrlRedirect($url_redirect);
                    $payment->setPaybrasOrderId($url_redirect)->save();
                }
            } elseif($fields['pedido_meio_pagamento'] == 'cartao') {
				$url_redirect = Mage::getBaseUrl() . 'paybras/standard/pagamento/order_id/' . $orderId;
				$payment->setPaybrasOrderId($url_redirect)->save();
			}
            
            $url = Mage::getUrl('checkout/onepage/success');
        }
        else {
            $url = Mage::getUrl('checkout/onepage/failure');
        }
        
		if ($orderId) {
            if(!$order->getEmailSent()) {
            	$order->sendNewOrderEmail();
    			$order->setEmailSent(true);
    			$order->save();
                $paybras->log("Email do Pedido $orderId Enviado");
            }
        }
		
        $session->setOrderId($orderId);
        $this->getResponse()->setRedirect($url);
        //$session->unsUrlRedirect();
    }
    
	/**
     * Processa nova tentativa de pagamento
     * 
     */
    public function repayAction() {
		if($this->getRequest()->isPost() && Mage::getStoreConfig('payment/paybras/repay')) {
			$session = Mage::getSingleton('core/session');
			$paybras = Mage::getSingleton('paybras/standard');
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
			
			$payment = $order->getPayment();
			$session->setOrderRealId($order->getRealOrderId());
			
			if($paybras->getEnvironment() == '1') {
				$url = 'https://service.paybras.com/payment/api/criaTransacao';
			}
			else {
				$url = 'https://sandbox.paybras.com/payment/api/criaTransacao';
			}
			
			if($order->getCustomerId()) {
				$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
			}
			else {
				$customer = false;
			}

			$fields = $paybras->dataTransaction($customer,$order,$payment,$_POST);
			
			$curlAdapter = new Varien_Http_Adapter_Curl();
			$curlAdapter->setConfig(array('timeout'   => 20));
			$curlAdapter->write(Zend_Http_Client::POST, $url, '1.1', array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
			$resposta = $curlAdapter->read();
			$retorno = substr($resposta,strpos($resposta, "\r\n\r\n"));
			$curlAdapter->close();
			
			if(function_exists('json_decode')) {
				$json_php = json_decode($retorno);
				
				if($json_php->{'sucesso'} == '1') {
					$paybras->log('True para consulta');
					$flag = true;
				}
				else {
					if($json_php->{'sucesso'} == '0') {
						$code_erro = $json_php->{'mensagem_erro'};
						$error_msg = Mage::helper('paybras')->msgError($code_erro);
						$paybras->log('False para consulta. Erro: '.$error_msg);
						$session->setMsgPaybrasErro($error_msg);
						$flag = false;
					}
					else {
						$paybras->log('Null para consulta '. $json_php->{'code'});
						$flag = NULL;
					}
				}
			}
			else {
				$paybras->log('[ Function Json_Decode does not exist! Upgrade PHP ]');
			}
			
			if($flag) {
				$transactionId = $json_php->{'transacao_id'};
				$status_codigo = $json_php->{'status_codigo'};
				
				$payment->setPaybrasTransactionId(utf8_encode($transactionId))->save();
				$paybras->processStatus($order,$status_codigo,$transactionId,$status_codigo);
				
				$session->setFormaPag($fields['pedido_meio_pagamento']);
				$session->setStatePag($paybras->convertStatus($status_codigo));
				
				$url = Mage::getUrl('paybras/standard/success');
			}
			else {
				$url = Mage::getUrl('paybras/standard/failure');
			}
			
			$this->getResponse()->setRedirect($url);
		}
		else {
			die();
		}
	}
	
    /**
     * Nova tentativa de pagamento
     * 
     */
    public function pagamentoAction() {
		Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*'));
        $paybras = Mage::getSingleton('paybras/standard');
		$session = Mage::getSingleton('core/session');
        
        if($this->getRequest()->getParam('order_id')) {
            $orderId = $this->getRequest()->getParam('order_id');
            $paybras->log('Tentativa de Repagamento, pedido: '.$orderId);
        }
        else {
            die();
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
        
		if(!$order) {
            die();
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
		
		/* Renova Parcelamento */
		if($paybras->getEnvironment()) {
            $url = 'https://service.paybras.com/payment/getParcelas';
        }
        else {
            $url = 'https://sandbox.paybras.com/payment/getParcelas';
        }
        
		$_totalData = $order->getData();
		$total = $_totalData['grand_total'];
        
        $fields = Array();
        $fields['recebedor_email'] = $paybras->getEmailStore();
        $fields['recebedor_api_token'] = $paybras->getToken();
        $fields['pedido_valor_total'] = $total;
        
        $curlAdapter = new Varien_Http_Adapter_Curl();
        $curlAdapter->setConfig(array('timeout'   => 20));
        //$curlAdapter->connect(your_host[, opt_port, opt_secure]);
        $curlAdapter->write(Zend_Http_Client::POST, $url, '1.1', array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
        $resposta = $curlAdapter->read();
        $retorno = substr($resposta,strpos($resposta, "\r\n\r\n"));
        $curlAdapter->close();
        $session->setMyParcelamentoRe($retorno);
		$session->setMyParcelamentoTotal($total);
        /* Fim Parcelamento */
		
        if($order_redirect === false) {
            $this->_redirect('');
        }
        else {
            $this->loadLayout();
    		$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');			
			$block = $this->getLayout()->createBlock('Xpd_Paybras_Block_Standard_Pagamento','block_standard_pagamento',array('template' => 'xpd/paybras/standard/pagamento.phtml'));
			$this->getLayout()->getBlock('content')->append($block);
			$this->renderLayout();
        }
    }
    
    /**
     * Captura Notificação do Pagamento
     * 
     */
    public function capturaAction() {
        if($this->getRequest()->isPost() && Mage::getStoreConfig('payment/paybras/notification')) {
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
                    $ehRepay = 1;
                }
                else {
                    $orderId = $pedidoId;
                    $ehRepay = NULL;
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
                $curlAdapter->write(Zend_Http_Client::POST, $url, '1.1', array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
                $resposta = $curlAdapter->read();
                $retorno = substr($resposta,strpos($resposta, "\r\n\r\n"));
                $curlAdapter->close();
                
                $json = json_decode($retorno);
                if($json->{'sucesso'} == '1') {
                    if($json->{'pedido_id'} == $pedidoIdVerifica && $json->{'valor_original'} == $valor && $json->{'status_codigo'} == $status_codigo) {
                        $result = $paybras->processStatus($order,$status,$transactionId,$ehRepay);
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
     * Controller para comparar de nomes via AJAX
     *
     */
    public function comparaAction() {
        $nameCustomer = $this->getRequest()->getParam('nome');
        $nameTitular = $this->getRequest()->getParam('titular');
        $paybras = $this->getStandard();
        
        if($nameCustomer && $nameTitular) {
            echo $paybras->comparaNome($nameCustomer,$nameTitular) ? '1' : '0';
        }
    }
	
	/**
     * Exibe tela de sucesso após tentativa de repagamento
     * 
     */
    public function successAction() {
		$session = Mage::getSingleton('core/session');
		$paybras = Mage::getSingleton('paybras/standard');
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
		$block = $this->getLayout()->createBlock('Xpd_Paybras_Block_Standard_Success','block_standard_success',array('template' => 'xpd/paybras/standard/success.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
		$this->renderLayout();
    }
	
	/**
     * Exibe tela de falha após tentativa de repagamento
     * 
     */
    public function failureAction() {
		$session = Mage::getSingleton('core/session');
		$paybras = Mage::getSingleton('paybras/standard');
		
		$this->loadLayout();
		$this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
		$block = $this->getLayout()->createBlock('Xpd_Paybras_Block_Standard_Failure','block_standard_failure',array('template' => 'xpd/paybras/standard/failure.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
		$this->renderLayout();
    }
}
