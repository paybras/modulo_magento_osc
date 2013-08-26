<?php
/**
 * Paybras
 *
 * @category   Payments
 * @package    Xpd_Paybras
 * @license    OSL v3.0
 */
class Xpd_Paybras_Block_Standard_Pagamento extends Mage_Core_Block_Template {
    
	protected function _construct() {
        parent::_construct();
        $this->setTemplate('xpd/paybras/standard/pagamento.phtml');
    }
		
	/**
	 * Analisa o retorno do ajax de parcelamento (variavel de sessão) e retorna as parcelas
	 * 
	 */
	public function getParcelas() {
        $enabled = Mage::getStoreConfig('payment/paybras/installments');
        $paybras = Mage::getSingleton('paybras/standard');
                
        if($enabled) {
            $retorno = Mage::getSingleton('core/session')->getMyParcelamentoRe();
            
            if(function_exists('json_decode')) {
                $json_php = json_decode($retorno);
                if($json_php->{'sucesso'} == 1) {
                    $return_parcelas = array();
                    foreach($json_php as $param => $parcelas) {
                        if($param != 'sucesso') {
                            $return_parcelas[$param] = $parcelas->{'valor_parcela'};
                        }
                    }
                    return $return_parcelas;
                }
                else {
                    $paybras->log('Mensagem de Erro do Parcelamento: '.$json_php->{'mensagem_erro'});
                    return array('1' => 0);
                }
            }
            else {
                $paybras->log('Sua versao do PHP e antiga. Por favor, atualize.');
                return array('1' => 0);
            }
        }
        else {
            return array('1' => $total = Mage::getSingleton('core/session')->getMyParcelamentoTotal());
        }
    }
	
	/**
	 * Retorna o modelo de cartões
	 */
	public function getSourceModel() {
		return Mage::getSingleton('paybras/source_cartoes');
    }

    /**
     * Retorna os tipos de cartões de créditos 
     *
     * @return array
     */
    public function getCcAvailableTypes() {
        $arrayCartoes = $this->getSourceModel()->toOptionArray();

        $types = array();
        foreach($arrayCartoes as $cartao) {
            $types[$cartao['value']] = $cartao['label'];
        }

        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);

                foreach ($types as $code=>$name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }

        return $types;
    }

    /**
     * Retreive payment method form html
     *
     * @return string
     */
    public function getMethodFormBlock() {
        return $this->getLayout()->createBlock('payment/form_cc')
                        ->setMethod($this->getMethod());
    }
    
}