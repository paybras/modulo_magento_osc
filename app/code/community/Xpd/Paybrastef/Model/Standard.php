<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrastef
 * @license    OSL v3.0
 */
class Xpd_Paybrastef_Model_Standard extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'paybrastef';
    protected $_formBlockType = 'paybrastef/form_tef';
    protected $_infoBlockType = 'paybrastef/info';
    
    protected $_canUseInternal = false;
    protected $_canUseForMultishipping = false;
    protected $_canUseCheckout = true;
    protected $_order;
    protected $_ambiente = 1;
    
    public $formaPagamentoBandeira;
    public $formaPagamentoProduto;
    public $formaPagamento;
    
    /**
     *  Recupera order (pedido) para utilização do módulo
     *
     *  @return	Mage_Sales_Model_Order
     */
    public function getOrder() {
        if ($this->_order == null) {
            $this->_order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());
        }
        return $this->_order;
    }
    
    /**
     * Recupera o código do método de pagamento
     * 
     * @return string
     */
    public function getCode() {
        return $this->_code;
    }
    
    /**
     * Recupera a forma de pagamento
     * 
     * @return string
     */
    public function getFormaPagamento() {
        return $this->formaPagamento;
    }

    /**     
     * Registra log de eventos/erros.
     * 
     * @param string $message
     * @param integer $level
     * @param string $file
     * @param bool $forceLog
     */
    public function log($message, $level = null, $file = 'paybras.log', $forceLog = false) {
        Mage::log("Paybrastef - " . $message, $level, 'paybras.log', $forceLog);
    }
    
    /**
     * Redirecionamento para criação da order (pedido)
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('paybrastef/standard/redirect', array('_secure' => true));
    }
    
    /**
     * Retorna ambiente da loja
     * 
     * @return int
     */
    public function getEnvironment() {
        return Mage::getStoreConfig('payment/paybrastef/environment');
    }
    
    /**
     * Retorna email do recebedor
     * 
     * @return string
     */
    public function getEmailStore() {
        return Mage::getStoreConfig('payment/paybrastef/emailstore');
    }
    
    /**
     * Retorna Token de integração
     * 
     * @return string
     */
    public function getToken() {
        return Mage::getStoreConfig('payment/paybrastef/token');
    }
        
    /**
     * Sobrecarga da função assignData, acrecentado dados adicionais.
     * 
	 * @param $data - Informação adiquirida do método de pagamento.
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function assignData($data) {
        $details = array();
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $additionaldata = array('tef_banco' => $data->getTefBanco(), 'forma_pagamento' => 'tef_bb');
        $info->setAdditionalData(serialize($additionaldata));
        
        return $this;
    }
    
    /**
     * Recupera os dados necessários para a criacão de uma transação
     * 
	 * @param $customer - Objeto Cliente
	 * @param $order - Objeto Pedido
	 * @param $payment - Objeto do Pagamento do pedido
     * @return array
     */
    public function dataTransaction($customer,$order,$payment,$post = NULL) {
        $fields = Array();
        
        /* Dados do Recebedor */
        $fields['recebedor_api_token'] = $this->getToken();
        $fields['recebedor_email'] = $this->getEmailStore();
        $billingAddress = $order->getBillingAddress();
        
        /* Dados do Pagador */
        if($customer) {
            $fields['pagador_nome'] = $customer->getName() ? $customer->getName() : $customer->getFirstname() . ' ' . $customer->getLastname();
            
            if($customer->getCpfcnpj() || $billingAddress->getCpfcnpj()) {
                $cpf0 = $customer->getCpfcnpj() ? $customer->getCpfcnpj() : $billingAddress->getCpfcnpj();
                $fields['pagador_cpf'] = str_replace('-','',str_replace('.','',$cpf0));
            } elseif($customer->getTaxvat()) {
                $fields['pagador_cpf'] = str_replace('-','',str_replace('.','',$customer->getTaxvat()));
            } elseif($customer->getCpf()) {
                $fields['pagador_cpf'] = str_replace('-','',str_replace('.','',$customer->getCpf()));
            }
            
            if($customer->getRg()) {
                $fields['pagador_rg'] = str_replace('-','',str_replace('.','',$order->getRg())); 
            } elseif($order->getCustomerRg()) {
                $fields['pagador_rg'] = str_replace('-','',str_replace('.','',$order->getCustomerRg()));
            }
        
            $fields['pagador_email'] = $customer->getEmail();
        }
        else {
            $fields['pagador_nome'] = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
            
            if($billingAddress->getCpfcnpj()) {
                $fields['pagador_cpf'] = str_replace('-','',str_replace('.','',$billingAddress->getCpfcnpj()));
            } elseif($order->getCustomerTaxvat()) {
                $fields['pagador_cpf'] = str_replace('-','',str_replace('.','',$order->getCustomerTaxvat()));
            } elseif($order->getCustomerCpf()) {
                $fields['pagador_cpf'] = str_replace('-','',str_replace('.','',$order->getCustomerCpf()));
            }
            
            if($order->getCustomerRg()) {
                $fields['pagador_rg'] = str_replace('-','',str_replace('.','',$order->getCustomerRg())); 
            }
        
            $fields['pagador_email'] = $order->getCustomerEmail();
        }
        
        if($order->getCustomerDob()) {
            $dateTimestamp = Mage::getModel('core/date')->timestamp(strtotime($order->getCustomerDob())) + 15000;
            $fields['pagador_data_nascimento'] = date('d-m-Y', $dateTimestamp);
			$fields['pagador_data_nascimento'] = str_replace('-','/',$fields['pagador_data_nascimento']);
        }
        
        $telefone = $billingAddress->getData('telephone');
        $telefone = $this->removeCharInvalidos($telefone); 
        if(substr($telefone,0,1) == '0') {
            $telefone = substr($telefone,1);
        }
        
        $celular = $billingAddress->getData('celular') ? $billingAddress->getData('celular') : $billingAddress->getData('fax');
        $celular = $this->removeCharInvalidos($celular); 
        if(substr($celular,0,1) == '0') {
            $celular = substr($celular,1);
        }
        
        $fields['pagador_telefone_ddd'] = substr($telefone,0,2);
        $fields['pagador_telefone'] = substr($telefone,2);
        
        $fields['pagador_celular_ddd'] = substr($celular,0,2);
        $fields['pagador_celular'] = substr($celular,2);
        
        $fields['pagador_sexo'] = $order->getCustomerGender() ? $order->getCustomerGender() : $order->getCustomer()->getGender();
        
        switch((int)$fields['pagador_sexo']) {
            case 1: $fields['pagador_sexo'] = 'M'; break;
            case 2: $fields['pagador_sexo'] = 'F'; break;
            default: $fields['pagador_sexo'] = ''; break;
        }
        
        $additionaldata = unserialize($payment->getData('additional_data'));
        $this->formaPagamento = $additionaldata['tef_banco'];
        
        $fields['pedido_url_redirecionamento'] = Mage::getBaseUrl() . 'paybras/standard/success/';
		$fields['pedido_meio_pagamento'] = $additionaldata['tef_banco'];

        $fields['pedido_id'] = $order->getIncrementId();
        $fields['pedido_valor_total_original'] = number_format($order->getGrandTotal(), 2, '.', '');
        
        /* Endereço do Pagador */
        if($billingAddress) {
            if($billingAddress->getStreet(1) && $billingAddress->getStreet(2) && $billingAddress->getStreet(3) && $billingAddress->getStreet(4)) {
                $fields['pagador_logradouro'] = $billingAddress->getStreet(1);
                $fields['pagador_numero'] = $billingAddress->getStreet(2);
                $fields['pagador_complemento'] = $billingAddress->getStreet(3);
                $fields['pagador_bairro'] = $billingAddress->getStreet(4);
            }
            else {
                if($billingAddress->getStreet(1) && $billingAddress->getStreet(2) && $billingAddress->getStreet(3) && !$billingAddress->getStreet(4)) {
                    $fields['pagador_logradouro'] = $billingAddress->getStreet(1);
                    $fields['pagador_numero'] = $billingAddress->getStreet(2);
                    $fields['pagador_complemento'] = $billingAddress->getStreet(2);
                    $fields['pagador_bairro'] = $billingAddress->getStreet(3);
                }
                else {
                    $fields['pagador_logradouro'] = $billingAddress->getStreet(1);
                    $fields['pagador_numero'] = $billingAddress->getStreet(2);
                    $fields['pagador_complemento'] = $billingAddress->getStreet(2);
                    $fields['pagador_bairro'] = $billingAddress->getStreet(2);
                }
            }
            $fields['pagador_cep'] = str_replace('.','',$billingAddress->getData('postcode'));
            $fields['pagador_cidade'] = $billingAddress->getData('city');
            $fields['pagador_estado'] = $billingAddress->getRegionCode();
            $fields['pagador_pais'] = $billingAddress->getCountry() ? Mage::helper('paybrastef')->convertCodeCountry($billingAddress->getCountry()) : "BRA";
        }
        else {
            $this->log('Erro ao recuperar informacoes de endereco de cobranca');
        }
        
        /* Endereço da Entrega */
        $shippingAddress = $order->getShippingAddress();
        if($shippingAddress) {
            if($shippingAddress->getStreet(1) && $shippingAddress->getStreet(2) && $shippingAddress->getStreet(3) && $shippingAddress->getStreet(4)) {
                $fields['entrega_logradouro'] = $shippingAddress->getStreet(1);
                $fields['entrega_numero'] = $shippingAddress->getStreet(2);
                $fields['entrega_complemento'] = $shippingAddress->getStreet(3);
                $fields['entrega_bairro'] = $shippingAddress->getStreet(4);
            }
            else {
                if($shippingAddress->getStreet(1) && $shippingAddress->getStreet(2) && $shippingAddress->getStreet(3) && !$shippingAddress->getStreet(4)) {
                    $fields['entrega_logradouro'] = $shippingAddress->getStreet(1);
                    $fields['entrega_numero'] = $shippingAddress->getStreet(2);
                    $fields['entrega_complemento'] = $shippingAddress->getStreet(2);
                    $fields['entrega_bairro'] = $shippingAddress->getStreet(3);
                }
                else {
                    $fields['entrega_logradouro'] = $shippingAddress->getStreet(1);
                    $fields['entrega_numero'] = $shippingAddress->getStreet(2);
                    $fields['entrega_complemento'] = $shippingAddress->getStreet(2);
                    $fields['entrega_bairro'] = $shippingAddress->getStreet(2);
                }
            }
            $fields['entrega_cep'] = str_replace('.','',$shippingAddress->getData('postcode'));
            $fields['entrega_cidade'] = $shippingAddress->getData('city');
            $fields['entrega_estado'] = $shippingAddress->getRegionCode() ? $shippingAddress->getRegionCode() : $billingAddress->getRegionCode();
            $fields['entrega_pais'] = $shippingAddress->getCountry() ? Mage::helper('paybrastef')->convertCodeCountry($shippingAddress->getCountry()) : "BRA";
        }
        else {
            $this->log('Erro ao recuperar informacoes de endereco de entrega');
        }
        
        /* Produtos */
        $items = $order->getAllVisibleItems();
        $count = 0;
        foreach ($items as $item) {
            $fields['produtos'][$count]['produto_codigo'] = $item->getSku();
            $fields['produtos'][$count]['produto_nome'] = $item->getName();
            $fields['produtos'][$count]['produto_qtd'] = $item->getQtyOrdered(); 
            if($item->getWeight() < 500.00) {
                $fields['produtos'][$count]['produto_peso'] = number_format($item->getWeight(), '2');
            }
            else {
                $fields['produtos'][$count]['produto_peso'] = number_format($item->getWeight()/1000, '2');
            }
            $fields['produtos'][$count]['produto_valor'] = number_format(($item->getFinalPrice() ? $item->getFinalPrice() : $item->getPrice()), '2');
            $count += 1;
        }
        $fields['pedido_moeda'] = Mage::app()->getStore()->getCurrentCurrencyCode();
		
        return $fields;
    }
    
    /**
     * Analisa o status e em caso de pagamento aprovado, cria a fatura. No caso do status recusado ou não aprovado, cancela o pedido.
	 *
     * @param object $order - Objeto do Pedido
	 * @param integer $status - Status do Pedido 
	 * @param string $transactionId - ID da transação junto a Paybras
	 * @param integer
     * @return integer
     */
    public function processStatus($order,$status,$transactionId,$repay = NULL) {
        if ($status == 4) {
            if ($order->canUnhold()) {
        	    $order->unhold();
        	}
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice_msg = utf8_encode(sprintf('Pagamento confirmado. Transa&ccedil;&atilde;o: %s', $transactionId));
                $invoice->addComment($invoice_msg, true);
                $invoice->sendEmail(true, $invoice_msg);
				$invoice->register()->pay();
                $invoice->setEmailSent(true);
                //$invoice->save();
				
                Mage::getModel('core/resource_transaction')
                   ->addObject($invoice)
                   ->addObject($invoice->getOrder())
                   ->save();
                $comment = utf8_encode(sprintf('Fatura %s criada.', $invoice->getIncrementId()));
                $order = $this->changeState($order,$status,NULL,$comment,$repay);
				$order->save();
                return 1;
            }
            else {
                $this->log("Fatura nao pode ser criada");
                return -1;
            }
        }
        elseif ($status == 3 || $status == 5) {
            if ($order->canUnhold()) {
	           $order->unhold();
            }
            if ($order->canCancel()) {
                $order_msg = "Pedido Cancelado. Transação: ". $transactionId;
        		$order = $this->changeState($order,$status,NULL,$order_msg,$repay);
				$order->save();
        		$this->log("Pedido Cancelado: ".$order->getRealOrderId() . ". Transação: ". $transactionId);
                return 0;
            }
            else {
                $this->log("Pedido não pode ser Cancelada.");
                return -1;
            }
        }
        elseif($status == 2) {
            $order_msg = "Pedido em análise. Transação: ". $transactionId;
    		$order = $this->changeState($order,$status,NULL,$order_msg,$repay);
			$order->save();
			
            $this->log("Pedido em analise: ".$order->getRealOrderId() . ". Transação: ". $transactionId);
            return 0;
        }
        elseif($status == 1) {
            $order_msg = "Aguardando Pagamento. Transação: ". $transactionId;
    		$order = $this->changeState($order,$status,NULL,$order_msg,$repay);
			$order->save();
            $this->log("Aguardando Pagamento, pedido: ".$order->getRealOrderId() . ". Transação: ". $transactionId);
            return 0;
        }
        
        return -1;
    }
    
    /**
     * Altera estado de um pedido
     * 
	 * @param object $order - Objeto do Pedido
	 * @param integer $cod_state - Código do Estado do Pedido
	 * @param string $status - Status do Pedido
	 * @param $comment - Comentário/Observação
	 * @return Mage_Sales_Model_Order
     */
    public function changeState($order,$cod_state,$status,$comment,$repay = NULL) {
        $state = $this->convertState($cod_state);
        $status = $this->convertStatus($cod_state);

        $order->setState($state,$status,$comment,true,$repay);
        $order->getPayment()->setMessage($comment);
		if($state == Mage_Sales_Model_Order::STATE_CANCELED) {
			$order->cancel();
		}
		
		return $order;
    }
    
    /**
     * Converte número de state da Paybras para estado do Magento
     * 
	 * @param integer $num - Número do código do status da Paybras
     * @return Mage_Sales_Model_Order
     */
    public function convertState($num,$repay = NULL) {
		if($repay) {
			switch($num) {
				case 1: return Mage_Sales_Model_Order::STATE_NEW;//STATE_PENDING_PAYMENT;
				case 2: return Mage_Sales_Model_Order::STATE_HOLDED;//Mage_Sales_Model_Order::STATE_HOLDED;
				case 3: return Mage_Sales_Model_Order::STATE_CANCELED;
				case 4: return Mage_Sales_Model_Order::STATE_PROCESSING;
				case 5: return Mage_Sales_Model_Order::STATE_CANCELED;
				default: return Mage_Sales_Model_Order::STATE_CANCELED;
			}
		}
		else {
			switch($num) {
				case 1: return Mage_Sales_Model_Order::STATE_NEW;
				case 2: return Mage_Sales_Model_Order::STATE_HOLDED;//Mage_Sales_Model_Order::STATE_HOLDED;
				case 3: return Mage_Sales_Model_Order::STATE_NEW;
				case 4: return Mage_Sales_Model_Order::STATE_PROCESSING;
				case 5: return Mage_Sales_Model_Order::STATE_NEW;//Mage_Sales_Model_Order::STATE_CANCELED;
				default: return Mage_Sales_Model_Order::STATE_NEW;
			}
		}
    }
    
    /**
     * Converte número de status da Paybras para status do Magento
     * 
	 * @param integer $num - Número do código do status da Paybras
     * @return string
     */
    public function convertStatus($num,$repay = NULL) {
        $num = (int)$num;
		if($repay) {
			switch($num) {
				case 1: return 'pending';//'pending_payment';
				case 2: return 'holded';
				case 3: return 'canceled';//'canceled';
				case 4: return 'processing';
				case 5: return 'canceled';//'canceled';
				default: return 'canceled';
			}
		}
		else {
			switch($num) {
				case 1: return 'pending';
				case 2: return 'holded';
				case 3: return 'pending';
				case 4: return 'processing';
				case 5: return 'pending';
				default: return 'pending';
			}
		}
    }
    
    /**
     * Remove caracteres indesejados
     * 
	 * @param string
     * @return string
     */
    public function removeCharInvalidos($str) {
        $invalid = array(' '=>'', '-'=>'', '{'=>'', '}'=>'', '('=>'', ')'=>'', '_'=>'', '['=>'', ']'=>'', '+'=>'', '*'=>'', '#'=>'', '/'=>'', '|'=>'', "`" => '', "´" => '', "„" => '', "`" => '', "´" => '', "“" => '', "”" => '', "´" => '', "~" => '', "’" => '', "." => '', 'a' => '', 'a' => '' , 'b' => '' , 'c' => '' , 'd' => '' , 'e' => '' , 'f' => '' , 'g' => '' , 'h' => '' , 'i' => '' , 'j' => '' , 'l' => '' , 'k' => '' , 'm' => '' , 'n' => '' , 'o' => '' , 'p' => '' , 'q' => '' , 'r' => '' , 's' => '' , 't' => '' , 'u' => '' , 'v' => '' , 'x' => '' , 'z' => '' , 'y' => '' , 'w' => '' , 'A' => '' , 'B' => '' , 'C' => '' , 'D' => '' , 'E' => '' , 'F' => '' , 'G' => '' , 'H' => '' , 'I' => '' , 'J' => '' , 'L' => '' , 'K' => '' , 'M' => '' , 'N' => '' , 'O' => '' , 'P' => '' , 'Q' => '' , 'R' => '' , 'S' => '' , 'T' => '' , 'U' => '' , 'V' => '' , 'X' => '' , 'Z' => '' , 'Y' => '' , 'W' => '');
         
        $str = str_replace(array_keys($invalid), array_values($invalid), $str);
         
        return $str;
    }
}