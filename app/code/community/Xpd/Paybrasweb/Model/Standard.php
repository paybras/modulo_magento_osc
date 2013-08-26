<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybrasweb
 * @license    OSL v3.0
 */
class Xpd_Paybrasweb_Model_Standard extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'paybrasweb';
    protected $_formBlockType = 'paybrasweb/form';
    protected $_infoBlockType = 'paybrasweb/info';
    
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
        Mage::log("PaybrasWeb - " . $message, $level, 'paybras.log', $forceLog);
    }
    
    /**
     * Redirecionamento para criação da order (pedido)
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('paybrasweb/standard/redirect', array('_secure' => true));
    }
    
    /**
     * Retorna ambiente da loja
     * 
     * @return int
     */
    public function getEnvironment() {
        return Mage::getStoreConfig('payment/paybrasweb/environment');
    }
    
    /**
     * Retorna email do recebedor
     * 
     * @return string
     */
    public function getEmailStore() {
        return Mage::getStoreConfig('payment/paybrasweb/emailstore');
    }
    
    /**
     * Retorna Token de integração
     * 
     * @return string
     */
    public function getToken() {
        return Mage::getStoreConfig('payment/paybrasweb/token');
    }
    
    /**
     * Captura IP do Cliente
     * 
     * @return string $ip
     */ 
    public function getIpClient() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
        
    /**
     * Sobrecarga da função assignData, acrecentado dados adicionais.
     * 
	 * @param $data - Informação adiquirida do método de pagamento.
     * @return Mage_Payment_Model_Method_Cc
     */
    /*public function assignData($data) {
        $details = array();
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        //$additionaldata = array('cc_parcelas' => $data->getCcParcelas(), 'cc_cid_enc' => $info->encrypt($data->getCcCid()), 'cpf_titular' => $data->getCcCpftitular(), 'day_titular' => $data->getCcDobDay(), 'month_titular' => $data->getCcDobMonth(), 'year_titular' => $data->getCcDobYear(), 'tel_titular' => $data->getPhone(), 'forma_pagamento' => $data->getCheckFormapagamento(), 'tef_banco' => $data->getTefBanco());
        //$info->setAdditionalData(serialize($additionaldata));
        //$info->setCcType($data->getCcType());
        //$info->setCcOwner($data->getCcOwner());
        //$info->setCcExpMonth($data->getCcExpMonth());
        /*$info->setCcExpYear($data->getCcExpYear());
        $info->setCcNumberEnc($info->encrypt($data->getCcNumber()));
        $info->setCcCidEnc($info->encrypt($data->getCcCid()));
        $info->setCcLast4(substr($data->getCcNumber(), -4));*/
        
        //Mage::log($this->formaPagamento);
        //Mage::getSingleton('core/session')->setFormaPagamento($data->getCheckFormapagamento);
        //Mage::log(Mage::getSingleton('core/session')->getFormaPagamento());
        /*return $this;
    }*/
    
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
        
        $meiosPag = Mage::getStoreConfig('payment/paybrasweb/emailstore');
        $fields['pedido_tipos_pgto'] = '';
        foreach($meiosPag as $meio) {
            $fields['pedido_tipos_pgto'] .= $meio['value'];
        }
        
        $fields['pedido_url_redirecionamento'] = Mage::getBaseUrl() . 'paybrasweb/retorno';
        $fields['pedido_id'] = $order->getIncrementId();
        $fields['pedido_valor_total_original'] = number_format($order->getGrandTotal(), 2, '.', '');
        $fields['pagador_ip'] = $this->getIpClient();
        $fields['recebedor_url_retorno'] = Mage::getBaseUrl() . 'paybrasweb/standard/retorno';
        
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
            $fields['pagador_pais'] = $billingAddress->getCountry() ? Mage::helper('paybras')->convertCodeCountry($billingAddress->getCountry()) : "BRA";
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
            $fields['entrega_pais'] = $shippingAddress->getCountry() ? Mage::helper('paybras')->convertCodeCountry($shippingAddress->getCountry()) : "BRA";
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
        
        if($post) {
			$fields['pedido_id'] = $order->getIncrementId().'_1';
        }
				
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
    
    /**
     * Remove acentos e caracteres especiais
     * 
	 * @param string
     * @return string
     */
    public function removeInvalidos($str) {
        $invalid = array('Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z',
        'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A',
        'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
        'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y',
        'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
        'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e',  'ë'=>'e', 'ì'=>'i', 'í'=>'i',
        'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
        'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y',  'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r', "`" => "'", "´" => "'", "„" => ",", "`" => "'",
        "´" => "'", "“" => "\"", "”" => "\"", "´" => "'", "&acirc;€™" => "'", "{" => "",
        "~" => "", "–" => "-", "’" => "'");
         
        $str = str_replace(array_keys($invalid), array_values($invalid), $str);
         
        return $str;
    }

    /**
     * Compara nomes semelhantes
     * 
	 * @param string Nome
	 * @param string Nome
     * @return boolean
     */
    public function comparaNome($nomecartao, $nomepessoa) {
        $acertos = 1;
        $nomecartao = $this->removeInvalidos($nomecartao);
        //var_dump($nomecartao);
        $nomepessoa = $this->removeInvalidos($nomepessoa);
        //var_dump($nomepessoa);
        // Com intuito de melhorar a comparação:
        // Retiram-se espaços duplos, triplos etc., espaços nas laterais, e convertem-se caracteres para minúsculo
        // Convertem-se ainda para arrays
        $nomecartao = explode(" ", strtolower(trim(preg_replace('/\s+/', ' ', $nomecartao))));
        $nomepessoa = explode(" ", strtolower(trim(preg_replace('/\s+/', ' ', $nomepessoa))));
    
        // Número de comparações que devem ser atendidas com tolerância de 1 falha.
        // Este número corresponde ao tamanho do menor array, ou seja, menor quantidade de strings dos nomes (cartao ou pessoa)
        $objetivo_comparacoes = (count($nomecartao) > count($nomepessoa)) ? count($nomepessoa) : count($nomecartao);
        //echo "1 " . $nomecartao[0] ." ". $nomepessoa[0] . "<br>";
        // o primeiro nome deve coincidir
        if ($nomecartao[0] != $nomepessoa[0]) {
            return false;
        }
    
        // depois do primeiro nome, a validacao é feita pela quantidade
        // de caracteres abreviado do nome (Ex.: s - Silva) e deve
        // ser procurado em todo o sobrenome, não necessariamente na ordem em que são escritos
        // Ex.: daniel s a - daniel silva almeida (true) - 3 acertos
        //      daniel a s - daniel silva almeida (true) - 3 acertos
        //      daniel s   - daniel silva almeida (true) - 2 acertos - tolerancia de 1
        //      daniel a   - daniel silva almeida (true) - 2 acertos - tolerancia de 1
        //      daniel d b - daniel silva almeida (false) - 1 acerto - tolerancia de 1 - ainda faltou 1 acerto.
        //      daniel d   - daniel almeida       (false) - 1 acerto - tolerancia de 1 - ainda faltou 1 acerto.
        $totalCompare = (count($nomecartao) >= count($nomepessoa)) ? count($nomecartao) - 1 : count($nomepessoa) - 1;
        $minCompare = (count($nomecartao) <= count($nomepessoa)) ? count($nomecartao) - 1 : count($nomepessoa) - 1;
        $inicial = 0;
           
        for ($i = 1; $i < count($nomecartao); $i++) {
            $encontrou = false;         
    
            for ($j = 1; $j < count($nomepessoa) && !$encontrou; $j++) {
                // compara quantidade de caracteres iguais
                // se no sobrenome havia uma letra (s) do sobrenome completo
                // "silva" pegamos apenas o primeiro caracter para comparacao
                
                if (strlen($nomecartao[$i]) == 1) {
                    if ($nomecartao[$i] == $nomepessoa[$j][0]) {
                        $encontrou = true;
                        $acertos++;
                    }
                } else if (strlen($nomecartao[$i]) > 1) {
                    similar_text($nomecartao[$i], $nomepessoa[$j],$persim);
                    //echo $nomecartao[$i] . '=' . $nomepessoa[$j] . ' - ' . (similar_text($nomecartao[$i], $nomepessoa[$j], $per)) .' | ' . "($per) <br/> ";
                    
                    if ($nomecartao[$i][0] == $nomepessoa[$j][0] && $persim >= 70) {
                        $encontrou = true;
                        $acertos++;
                    }
                    else if($nomecartao[$i][0] != $nomepessoa[$j][0]) {
                        $inicial += 1;
                    }                                        
                }
            }
        }
        
        //var_dump($inicial);
        //var_dump($acertos);
        //var_dump($objetivo_comparacoes);
        //var_dump(($totalCompare - $inicial) <= $minCompare);
        //var_dump(($acertos == $objetivo_comparacoes || $acertos == $objetivo_comparacoes - 1) && ($totalCompare - $inicial) <= $minCompare);
        if (($acertos == $objetivo_comparacoes || $acertos == $objetivo_comparacoes - 1) && (($totalCompare - $inicial) <= $minCompare)) {
            return true;
        }
    }
        
}