<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrastef
 * @license    OSL v3.0
 */
class Xpd_Paybrastef_StandardController extends Mage_Core_Controller_Front_Action {

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
     * @return Xpd_Paybrastef_Model_Standard
     */
    public function getStandard() {
        return Mage::getSingleton('paybrastef/standard');
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
                    $error_msg = Mage::helper('paybrastef')->msgError($code_erro);
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
        
            $url_redirect = utf8_decode($json_php->{'url_pagamento'});
            if($url_redirect) {
                $session->setUrlRedirect($url_redirect);
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
     * Captura Notificação do Pagamento
     * 
     */
    public function capturaAction() {
        if($this->getRequest()->isPost() && Mage::getStoreConfig('payment/paybrastef/notification')) {
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
                $curlAdapter->write(Zend_Http_Client::POST, $url, '1.1', array('Content-Type: application/json','Content-Length: ' . strlen(json_encode($fields))), json_encode($fields));
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
    
}
