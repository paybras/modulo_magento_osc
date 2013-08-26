function buscarEndereco(host,quale) {

	new Ajax.Request(host + 'paybras_cep.php?cep=' + document.getElementById(quale+':postcode').value.replace(/\+/g, ''), {
        method:'get',
        onSuccess: function(respostaCEP) {
			r = respostaCEP.responseText;
			
			street_1 = r.substring(0, (i = r.indexOf(':')));
			document.getElementById(quale+':street1').value = unescape(street_1.replace(/\+/g," "));

			r = r.substring(++i);
			street_4 = r.substring(0, (i = r.indexOf(':')));
			document.getElementById(quale+':street4').value = unescape(street_4.replace(/\+/g," "));

			r = r.substring(++i);
			city = r.substring(0, (i = r.indexOf(':')));
			document.getElementById(quale+':city').value = unescape(city.replace(/\+/g," "));

			r = r.substring(++i);
			region = r.substring(0, (i = r.indexOf(':')));

			r = r.substring(++i);
			
			regionID = r.substring(0, 3);
			
			regionSelect = region;
			region = region.replace(/\+/g," ");
			
			$$('select[name="'+quale+'[region_id]"] option').each(function(element) {
				if(element.value == regionID){
					element.selected = true;
				}
			});
			
			setTimeout(function() { document.getElementById(quale+':street2').focus(); }, 1);
		}
	});
	
};

function attrName(element) {
    nomeTitular = element.value;
}

    function toggleBoleto() {
        document.getElementById("paybras_check_formapagamento").value = "boleto";
         
        var cc =  document.getElementById('cc');
        if(typeof(cc) != 'undefined' && cc != null) {
            document.getElementById("cc").style.display="none";
        }
         
        var tef = document.getElementById('tef');
        if(typeof(tef) != 'undefined' && tef != null) {
            document.getElementById("tef").style.display="none";
        }
         
        document.getElementById("boleto").style.display="block";
    }
  
    function toggleCard() {
        document.getElementById("paybras_check_formapagamento").value = "cartao";
        
        var tef = document.getElementById('tef');
        if(typeof(tef) != 'undefined' && tef != null) {
            document.getElementById("tef").style.display="none";
        }
        
        document.getElementById("boleto").style.display="none";
        
        var cc =  document.getElementById('cc');
        if(typeof(cc) != 'undefined' && cc != null) {
            document.getElementById("cc").style.display="block";
        }
    }
  
    function toggleTef() {
        document.getElementById("paybras_check_formapagamento").value = "tef_bb";
 
        var cc =  document.getElementById('cc');
        if(typeof(cc) != 'undefined' && cc != null) {
            document.getElementById("cc").style.display="none";
        }
 
        var tef = document.getElementById('tef');
        if(typeof(tef) != 'undefined' && tef != null) {
            document.getElementById("tef").style.display="block";
        }
        
        document.getElementById("boleto").style.display="none";
    }

    function comparaNome(nomeTitular,host) {
        if(document.getElementById("billing:firstname") && document.getElementById("billing:lastname")) {
            nomeComprador = document.getElementById("billing:firstname").value + ' ' + document.getElementById("billing:lastname").value;
        }
       
        new Ajax.Request(host+'paybras/standard/compara?nome='+nomeComprador+'&titular='+nomeTitular+'', {
            method:'get',
            onSuccess: function(transport) {
                var response = transport.responseText || -1;
                
                if(response == 1) {
                    document.getElementById("paybras_cc_cpftitular_div").style.display="none";
                    document.getElementById("paybras_cc_phone_div").style.display="none";
                    document.getElementById("paybras_cc_type_dob_div").style.display="none";
                }
                else {
                    document.getElementById("paybras_cc_cpftitular_div").style.display="block";
                    document.getElementById("paybras_cc_phone_div").style.display="block";
                    document.getElementById("paybras_cc_type_dob_div").style.display="block";
                }
            },
            onFailure: function() { 
                document.getElementById("paybras_cc_cpftitular_div").style.display="block";
                document.getElementById("paybras_cc_phone_div").style.display="block";
                document.getElementById("paybras_cc_type_dob_div").style.display="block";
            }
        });
    }

function onCardChange(optElement) {
    var meuID = optElement.id;
    var selCard = optElement.value; 
    
    $$('.seleciona-bandeiras-cards label').each(function(e,i){
        $(e).removeClassName('selecionada');
    });
    
    if (selCard == 'diners') {
        $('paybras_cc_number').setAttribute('maxlength', 14);
        $('paybras_cc_cid').setAttribute('maxlength', 3);
        maskcid.unmask().mask('999');
        $(optElement).previous(0).addClassName('selecionada');
    }
    else {
        if (selCard == 'amex') {
            $('paybras_cc_number').setAttribute('maxlength', 15);
            $('paybras_cc_cid').setAttribute('maxlength', 4);
            maskcid.unmask().mask('9999');
            $(optElement).previous(0).addClassName('selecionada');
        }
        else {
            $('paybras_cc_number').setAttribute('maxlength', 16);
            $('paybras_cc_cid').setAttribute('maxlength', 3);
            maskcid.unmask().mask('999');
            
            if(selCard == 'visa') {
                $(optElement).previous(0).addClassName('selecionada');
            }
            if(selCard == 'mastercard') {
                $(optElement).previous(0).addClassName('selecionada');
            }
            if(selCard == 'elo') {
                $(optElement).previous(0).addClassName('selecionada');
            }
        }
    }
    
    $('paybras_cc_number').value = "";
    $('paybras_cc_cid').value = "";
}

function mascara(o,f) {
    v_obj=o
    v_fun=f
    setTimeout("execmascara()",1)
}

function execmascara() {
    v_obj.value=v_fun(v_obj.value)
}

function numeros(v){
    v=v.replace(/\D/g,"")
    return v
}

function verifyType(element) {
    //alert(element);
    var ccnum = element.value;
    //var ccnum = element.getAttribute('value');
    var visaReg = /^4\d{3}-?\d{4}-?\d{4}-?\d{4}$/;
    var masterReg = /^5[1-5]\d{2}-?\d{4}-?\d{4}-?\d{4}$/;
    var discoverReg = /^6011-?\d{4}-?\d{4}-?\d{4}$/;
    var amexReg = /^3[47]\d{13}$/;
    var dinersReg = /^3[068]\d{12}$/;
    
    $$('.seleciona-bandeiras-cards label').each(function(e,i){
        $(e).removeClassName('selecionada');
    });
    $('opt-visa').checked = false;
    $('opt-mastercard').checked = false;
    $('opt-amex').checked = false;
    $('opt-diners').checked = false;
    $('opt-elo').checked = false;
    
    if(visaReg.test(ccnum) && ccnum.length == 16) {
        $('opt-visa').checked = true;
        $('paybras_cc_cid').setAttribute('maxlength', 3);
        maskcid.unmask().mask('999');
        $('opt-visa').previous(0).addClassName('selecionada');
    } else if(masterReg.test(ccnum) && ccnum.length == 16) {
        $('opt-mastercard').checked = true;
        $('paybras_cc_cid').setAttribute('maxlength', 3);
        maskcid.unmask().mask('999');
        $('opt-mastercard').previous(0).addClassName('selecionada');
    } else if(amexReg.test(ccnum) && ccnum.length == 15) {
        $('opt-amex').checked = true;
        $('paybras_cc_cid').setAttribute('maxlength', 4);
        maskcid.unmask().mask('9999');
        $('opt-amex').previous(0).addClassName('selecionada');
    } else if(dinersReg.test(ccnum) && ccnum.length == 14) {
        $('opt-diners').checked = true;
        $('paybras_cc_cid').setAttribute('maxlength', 3);
        maskcid.unmask().mask('999');
        $('opt-diners').previous(0).addClassName('selecionada');
    } else {
        $('paybras_cc_cid').setAttribute('maxlength', 3);
        maskcid.unmask().mask('999');
        
        if((/^(636368|504175|438935|451416|636297)/).test(ccnum) && ccnum.length == 16) {
            $('opt-elo').checked = true;
            $('opt-elo').previous(0).addClassName('selecionada');
        }
    }
    
    ccnum = ccnum.split("-").join("");
    
    var checksum = 0;
    for (var i=(2-(ccnum.length % 2)); i<=ccnum.length; i+=2) {
        checksum += parseInt(ccnum.charAt(i-1));
    }
    
    for (var i=(ccnum.length % 2) + 1; i<ccnum.length; i+=2) {
        var digit = parseInt(ccnum.charAt(i-1)) * 2;
        if (digit < 10) { checksum += digit; } else { checksum += (digit-9); }
    }
    
    if ((checksum % 10) == 0) {
        return true;
    }
    else {
        //alert('Número do Cartão Inválido');
        //$('paybras_cc_number').value = "";
        $('paybras_cc_cid').value = "";
        return false;
    }
}