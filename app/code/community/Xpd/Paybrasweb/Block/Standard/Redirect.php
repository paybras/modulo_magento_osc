<?php

class Xpd_Paybrasweb_Block_Standard_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $session = Mage::getSingleton('checkout/session');
        //$session = Mage::getSingleton('core/session');
        $fields = $session->getOrderFields();
        
        $html = '<html><head>
        </head><body>
        <center>
            <!--strong>Redirecionando</strong-->
            <p><img src="'. $this->getSkinUrl('images/paybras/ajax-loader.gif') .'" alt="Ajax Loarder Gif"/></p>
            <p><strong>Você está sendo redirecionado para o pagamento.</strong></p>
        </center>
		
		<form method="post" target="_self" id="webform" action="'. $session->getUrlAmbiente() .'">

		<!--DADOS DO LOJISTA-->
		<input type="hidden" name="recebedor_email" value="'. $fields['recebedor_email'] .'" />
		<input type="hidden" name="recebedor_url_retorno" value="'. $fields['recebedor_url_retorno'] .'" />

		<!--DADOS DO PEDIDO-->
		<input type="hidden" name="pedido_id" value="'. $fields['pedido_id'] .'" />
		<input type="hidden" name="pedido_descricao" value="'. $fields['pedido_descricao'] .'" />
		<input type="hidden" name="pedido_moeda" value="'. $fields['pedido_moeda'] .'" />
		<input type="hidden" name="pedido_valor_total_original" value="'. $fields['pedido_valor_total_original'] .'" />
		<input type="hidden" name="pedido_tipos_pgto" value="'. $fields['pedido_tipos_pgto'] .'" />

		<!--DADOS DO PAGADOR-->
		<input type="hidden" name="pagador_nome" value="'. $fields['pagador_nome'] .'" />
		<input type="hidden" name="pagador_email" value="'. $fields['pagador_email'] .'"  />
		<input type="hidden" name="pagador_cpf" value="'. $fields['pagador_cpf'] .'"  />
		<input type="hidden" name="pagador_rg" value="'. $fields['pagador_rg'] .'" />
		<input type="hidden" name="pagador_telefone_ddd" value="'. $fields['pagador_telefone_ddd'] .'" />
		<input type="hidden" name="pagador_telefone" value="'. $fields['pagador_telefone'] .'" />
		<input type="hidden" name="pagador_celular_ddd" value="'. $fields['pagador_celular_ddd'] .'" />
		<input type="hidden" name="pagador_celular" value="'. $fields['pagador_celular'] .'" />
		<input type="hidden" name="pagador_sexo" value="'. $fields['pagador_sexo'] .'" />
		<input type="hidden" name="pagador_data_nascimento" value="'. $fields['pagador_data_nascimento'] .'" />
		<input type="hidden" name="pagador_ip" value="'. $fields['pagador_ip'] .'" />
		<input type="hidden" name="pagador_logradouro" value="'. $fields['pagador_logradouro'] .'" />
		<input type="hidden" name="pagador_numero" value="'. $fields['pagador_numero'] .'" />
		<input type="hidden" name="pagador_complemento" value="'. $fields['pagador_complemento'] .'" />
		<input type="hidden" name="pagador_bairro" value="'. $fields['pagador_bairro'] .'" />
		<input type="hidden" name="pagador_cep" value="'. $fields['pagador_cep'] .'" />
		<input type="hidden" name="pagador_cidade" value="'. $fields['pagador_cidade'] .'" />
		<input type="hidden" name="pagador_estado" value="'. $fields['pagador_estado'] .'" />
		<input type="hidden" name="pagador_pais" value="BRA"/>

		<!--DADOS DO ENDEREÇO DE ENTREGA-->
		<input type="hidden" name="entrega_logradouro" value="'. $fields['entrega_logradouro'] .'" />
		<input type="hidden" name="entrega_numero" value="'. $fields['entrega_numero'] .'" />
		<input type="hidden" name="entrega_complemento" value="'. $fields['entrega_complemento'] .'" />
		<input type="hidden" name="entrega_bairro" value="'. $fields['entrega_bairro'] .'" />
		<input type="hidden" name="entrega_cep" value="'. $fields['entrega_cep'] .'" />
		<input type="hidden" name="entrega_cidade" value="'. $fields['entrega_cidade'] .'" />
		<input type="hidden" name="entrega_estado" value="'. $fields['entrega_estado'] .'" />
		<input type="hidden" name="entrega_pais" value="BRA"/>

		<!--DADOS DOS PRODUTOS-->';
        for($i=0;$i<count($fields['produto']);$i++) {
            $html .= '
            <input type="hidden" name="'. $fields['produto'][$i]['produto_codigo'].'"  />
    		<input type="hidden" name="'. $fields['produto'][$i]['produto_nome'].'"    />
    		<input type="hidden" name="'. $fields['produto'][$i]['produto_categoria'].'"    />
    		<input type="hidden" name="'. $fields['produto'][$i]['produto_qtd'].'"/>
    		<input type="hidden" name="'. $fields['produto'][$i]['produto_valor'].'"   />
    		<input type="hidden" name="'. $fields['produto'][$i]['produto_peso'].'"    />
            ';
        }

        $html .= '
		<button type="submit">COMPRAR</button>

		</form>';
        
        $html .= '
        <script "text/javascript">
        function submitForm() {
            document.forms["webform"].submit();
        }
        
    
        submitForm();
        </script>
		
        </body></html>';

        return $html;
    }
}