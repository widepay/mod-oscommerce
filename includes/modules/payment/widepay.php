<?php

class widepay
{
    var $code, $title, $description, $enabled;

    // class constructor
    function widepay()
    {
        global $order;
        $this->code = 'widepay';
        $this->title = MODULE_PAYMENT_WIDEPAY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_WIDEPAY_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_WIDEPAY_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_WIDEPAY_STATUS == 'Sim') ? true : false);
        if ((int)MODULE_PAYMENT_WIDEPAY_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_WIDEPAY_ORDER_STATUS_ID;
        }
        if (is_object($order)) $this->update_status();
    }

    function update_status()
    {
        return true;
    }

    function javascript_validation()
    {
        return true;
    }

    /*******************************************************************************
     * function to handle options before exchanging data with the payment gateway
     * Payment information screen
     * ******************************************************************************/
    function selection()
    {
        global $order;
        $selection = array('id' => $this->code,
            'module' => $this->title);
        return $selection;
    }

    /*************************************************************
     * Checks the data in the Payment selection screen
     * Validate data from this->selection() function
     * if data is incorrect, return to checkout payment screen
     * and prompt user for the incorrect data.
     * ************************************************************/
    function pre_confirmation_check()
    {
        return false;
    }

    /******************************************
     * Function in the order confirmation screen
     * *****************************************/
    function confirmation()
    {
        global $cartID, $cart_widepay_ID, $customer_id, $languages_id, $order, $order_total_modules, $insert_id;
        $content = "<input type=\"text\" name='cpf_cnpj' value='' />";
        $confirmation = array('title' => "Preencha com seu CPF ou CNPJ" . ': ' . $content);

        return $confirmation;
    }

    function get_uf()
    {
        global $order;
        $res = tep_db_fetch_array($qry = tep_db_query('select countries_id from ' . TABLE_COUNTRIES . ' where countries_name="Brazil" or countries_name="Brasil"'));
        $br_id = $res['countries_id']; // c�digo do Brasil. em caso da loja ter usado outro c�digo interno.
        $res = tep_db_fetch_array($qry = tep_db_query('select zone_code from ' . TABLE_ZONES . ' where zone_country_id="' . $br_id . '" and zone_name="' . $order->delivery['state'] . '"'));
        return $res['zone_code'];
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        return true;


    }

    private function getFiscal($cpf_cnpj)
    {
        $cpf_cnpj = preg_replace('/\D/', '', $cpf_cnpj);
        // [CPF, CNPJ, FISICA/JURIDICA]
        if (strlen($cpf_cnpj) == 11) {
            return array($cpf_cnpj, '', 'Física');
        } else {
            return array('', $cpf_cnpj, 'Jurídica');
        }
    }

    function formatURL($url)
    {
        return str_replace('&amp;', '&', $url);
    }

    private function getVariableTax($tax, $taxType, $total)
    {
        //Formatação para calculo ou exibição na descrição
        $widepayTaxDouble = number_format((double)$tax, 2, '.', '');
        $widepayTaxReal = number_format((double)$tax, 2, ',', '');
        // ['Description', 'Value'] || Null

        if ($taxType == "Acrécimo em %") {//Acrécimo em Porcentagem
            return array(
                'Referente a taxa adicional de ' . $widepayTaxReal . '%',
                round((((double)$widepayTaxDouble / 100) * $total), 2));
        } elseif ($taxType == "Acrécimo valor fixo em R$") {//Acrécimo valor Fixo
            return array(
                'Referente a taxa adicional de R$' . $widepayTaxReal,
                ((double)$widepayTaxDouble));
        } elseif ($taxType == "Desconto em %") {//Desconto em Porcentagem
            return array(
                'Item referente ao desconto: ' . $widepayTaxReal . '%',
                round((((double)$widepayTaxDouble / 100) * $total), 2) * (-1));
        } elseif ($taxType == "Desconto valor fixo em R$") {//Desconto valor Fixo
            return array(
                'Item referente ao desconto: R$' . $widepayTaxReal,
                $widepayTaxDouble * (-1));
        }
        return null;
    }

    function after_process()
    {

        // chamado pelo checkout_process.php depois que a transa��o foi finalizada
        global $order, $cartID, $cart, $insert_id, $cart_widepay_id, $sendto, $billto, $currencies, $HTTP_POST_VARS;
        $insert_id = $insert_id;


        $total = $order->info['total'];
        $frete = $order->info['shipping_cost'];
        $produtos_array = $order->products;
        $endereco = $order->billing;
        $cliente = $order->customer;
        $tax = MODULE_PAYMENT_WIDEPAY_TAX_VARIATION;
        $tax_type = MODULE_PAYMENT_WIDEPAY_TAX_TYPE;


        //produtos
        $items = [];
        $i = 1;
        foreach ($produtos_array as $key => $value) {
            $items[$i]['descricao'] = $value['name'];
            $items[$i]['valor'] = number_format($value['final_price'], 2, '.', '');
            $items[$i]['quantidade'] = $value['qty'];
            $i++;
        }
        if (isset($frete) && $frete > 0) {
            $items[$i]['descricao'] = 'Frete';
            $items[$i]['valor'] = number_format($frete, 2, '.', '');
            $items[$i]['quantidade'] = 1;
            $i++;
        }
        $variableTax = $this->getVariableTax($tax, $tax_type, $total);
        if (isset($variableTax)) {
            list($description, $total) = $variableTax;
            $items[$i]['descricao'] = $description;
            $items[$i]['valor'] = $total;
            $items[$i]['quantidade'] = 1;
        }


        $invoiceDuedate = new DateTime(date('Y-m-d'));
        $invoiceDuedate->modify('+' . intval(MODULE_PAYMENT_WIDEPAY_VALIDADE) . ' day');
        $invoiceDuedate = $invoiceDuedate->format('Y-m-d');
        $tel = $cliente['telephone'];
        $fiscal = preg_replace('/\D/', '', $HTTP_POST_VARS['cpf_cnpj']);
        list($widepayCpf, $widepayCnpj, $widepayPessoa) = $this->getFiscal($fiscal);

        $widepayData = array(
            'forma' => MODULE_PAYMENT_WIDEPAY_WAY,
            'referencia' => $insert_id,
            'notificacao' => $this->formatURL(tep_href_link('ext/modules/payment/widepay.php', 'referencia=' . $insert_id, 'SSL', false)),
            'vencimento' => $invoiceDuedate,
            'cliente' => (preg_replace('/\s+/', ' ', $endereco['firstname'] . ' ' . $endereco['lastname'])),
            'telefone' => preg_replace('/\D/', '', str_replace('+55', '', $tel)),
            'email' => $cliente['email_address'],
            'pessoa' => $widepayPessoa,
            'cpf' => $widepayCpf,
            'cnpj' => $widepayCnpj,
            'enviar' => 'E-mail',
            'endereco' => array(
                'rua' => $endereco['street_address'],
                'complemento' => '',
                'cep' => preg_replace('/\D/', '', $endereco['postcode']),
                'estado' => $this->get_uf(),
                'cidade' => $endereco['city']
            ),
            'itens' => $items,
            'boleto' => array(
                'gerar' => 'Nao',
                'desconto' => 0,
                'multa' => doubleval(MODULE_PAYMENT_WIDEPAY_FINE),
                'juros' => doubleval(MODULE_PAYMENT_WIDEPAY_INTEREST)
            ));

        $response = $this->api(intval(MODULE_PAYMENT_WIDEPAY_WALLET_ID), trim(MODULE_PAYMENT_WIDEPAY_WALLET_TOKEN), 'recebimentos/cobrancas/adicionar', $widepayData);


        if ($response->sucesso) {

            $link_boleto = $response->link;
            $transacao = $response->id;
            $linhaDigitavel = @$response->boleto['linha-digitavel'];
            $total = 'R$' . number_format($order->info['total'], '2', ',', '');

            tep_redirect(tep_href_link('/checkout_widepay_success.php', 'link_boleto=' . $link_boleto . '&transacao=' . $transacao . '&linhaDigitavel=' . $linhaDigitavel . '&total=' . $total, 'NONSSL', true, false));

            return true;
        } else {
            $validacao = '';

            if ($response->erro) {
                $validacao = $response->erro . ' ';
            }

            if (isset($response->validacao)) {
                foreach ($response->validacao as $item) {
                    $validacao .= '- ' . strtoupper($item['id']) . ': ' . $item['erro'] . '. ';
                }
                $validacao = 'Erro Validação: ' . $validacao;
            }
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode($validacao), 'NONSSL', true, false));
            exit();
        }
    }

    function get_error()
    {
        global $language;
        $error_text['title'] = 'Erro:';
        $error_text['error'] = urldecode($_GET['error']);
        return $error_text;
    }

    function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_WIDEPAY_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install()
    {
        $sort_order = 1;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "set_function, date_added) values ('Aprovação de Pagamento', 'MODULE_PAYMENT_WIDEPAY_STATUS', 'Não', " .
            "'Você deseja habilitar o módulo?', '6', '" . $sort_order . "', " .
            "'tep_cfg_select_option(array(\'Sim\', \'Não\'), ', now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'ID da Carteira Wide Pay', 'MODULE_PAYMENT_WIDEPAY_WALLET_ID', '', " .
            "'Preencha este campo com o ID da carteira que deseja receber os pagamentos do sistema. O ID de sua carteira estará presente neste link: https://www.widepay.com/conta/configuracoes/carteiras', '6', '" . $sort_order . "', " .
            "now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'Token da Carteira Wide Pay', 'MODULE_PAYMENT_WIDEPAY_WALLET_TOKEN', '', " .
            "'Preencha com o token referente a sua carteira escolhida no campo acima. Clique no botão: \"Integrações\" na página do Wide Pay, será exibido o Token', '6', '" . $sort_order . "', " .
            "now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "set_function, date_added) values ('Tipo da Taxa de Variação', 'MODULE_PAYMENT_WIDEPAY_TAX_TYPE', 'Sem alteração', " .
            "'Modifique o valor final do recebimento. Configure aqui um desconto ou acrescimo na venda', '6', '" . $sort_order . "', " .
            "'tep_cfg_select_option(array(\'Sem alteração\', \'Acrécimo em %\', \'Acrécimo valor fixo em R$\', \'Desconto em %\', \'Desconto valor fixo em R$\'), ', now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'Taxa de Variação', 'MODULE_PAYMENT_WIDEPAY_TAX_VARIATION', '', " .
            "'O campo acima \"Tipo de Taxa de Variação\" será aplicado de acordo com este campo. Será adicionado um novo item na cobrança do Wide Pay. Esse item será possível verificar apenas na tela de pagamento do Wide Pay ', '6', '" . $sort_order . "', " .
            "now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'Acréscimo de Dias no Vencimento', 'MODULE_PAYMENT_WIDEPAY_VALIDADE', '0', " .
            "'Prazo de validade em dias para o Boleto', '6', '" . $sort_order . "', " .
            "now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'Configuração de Multa', 'MODULE_PAYMENT_WIDEPAY_FINE', '0', " .
            "'Configuração de multa após o vencimento, máximo 20', '6', '" . $sort_order . "', " .
            "now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'Configuração de Juros', 'MODULE_PAYMENT_WIDEPAY_INTEREST', '0', " .
            "'Configuração de juros após o vencimento, máximo 20', '6', '" . $sort_order . "', " .
            "now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "set_function, date_added) values ('Forma de Recebimento', 'MODULE_PAYMENT_WIDEPAY_WAY', 'Boleto,Cartão', " .
            "'Selecione uma opção', '6', '" . $sort_order . "', " .
            "'tep_cfg_select_option(array(\'Boleto,Cartão\', \'Boleto\', \'Cartão\'), ', now())");


        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "use_function, set_function, date_added" .
            ") values (" .
            "'Status dos pedidos', 'MODULE_PAYMENT_WIDEPAY_ORDER_STATUS_ID', '2', " .
            "'Atualiza o status dos pedidos efetuados por este módulo de pagamento para este valor.', '6', '" . $sort_order . "', " .
            "'tep_get_order_status_name', 'tep_cfg_pull_down_order_statuses(', now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "use_function, set_function, date_added" .
            ") values (" .
            "'Pedidos aprovados', 'MODULE_PAYMENT_WIDEPAY_APPROVED_ORDER_STATUS_ID', '2', " .
            "'Atualiza o status dos pedidos aprovados por este módulo de pagamento para este valor.', '6', '" . $sort_order . "', " .
            "'tep_get_order_status_name', 'tep_cfg_pull_down_order_statuses(', now())");
        $sort_order++;
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (" .
            "configuration_title, configuration_key, configuration_value, " .
            "configuration_description, configuration_group_id, sort_order, " .
            "date_added" .
            ") values (" .
            "'Ordem de exibição', 'MODULE_PAYMENT_WIDEPAY_SORT_ORDER', '0', " .
            "'Determina a ordem de exibição do meio de pagamento.', '6', '" . $sort_order . "', " .
            "now())");


        tep_db_query("CREATE TABLE temp_widepay (
                        id INT( 13 ) NOT NULL AUTO_INCREMENT ,
                        vendedoremail VARCHAR( 200 ) NOT NULL ,
                        transacaoid VARCHAR( 40 ) NOT NULL ,
                        referencia VARCHAR( 128 ) NOT NULL ,
                        anotacao TEXT ,
                        datatransacao DATE NOT NULL ,
                        tipopagamento VARCHAR( 32 ) NOT NULL ,
                        statustransacao VARCHAR( 32 ) NOT NULL ,
                        clinome VARCHAR( 128 ) NOT NULL ,
                        cliemail VARCHAR( 128 ) NOT NULL ,
                        date_created datetime ,
                        PRIMARY KEY ( id ));"
        );
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        tep_db_query("drop table temp_widepay");
    }

    function keys()
    {
        $key_listing = array();
        $qry = "select configuration_key from " . TABLE_CONFIGURATION . " where LOCATE('MODULE_PAYMENT_WIDEPAY_', configuration_key)>0 order by sort_order";
        $findkey = tep_db_query($qry);
        while ($key = tep_db_fetch_array($findkey)) {
            $key_listing[] = $key['configuration_key'];
        } // while
        return $key_listing;
    }

    function debug_var($var, $name)
    {
        if ($txt = @fopen('ext/debug.log', 'a')) {
            fwrite($txt, "-----------------------------------\n");
            fwrite($txt, "$name\n");
            fwrite($txt, print_r($var, true) . "\n");
            fclose($txt);
        }
    }

    private function api($wallet, $token, $local, $params = array())
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://api.widepay.com/v1/' . trim($local, '/'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, trim($wallet) . ':' . trim($token));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('WP-API: SDK-PHP'));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        $exec = curl_exec($curl);
        curl_close($curl);
        if ($exec) {
            $requisicao = json_decode($exec, true);
            if (!is_array($requisicao)) {
                $requisicao = array(
                    'sucesso' => false,
                    'erro' => 'Não foi possível tratar o retorno.'
                );
                if ($exec) {
                    $requisicao['retorno'] = $exec;
                }
            }
        } else {
            $requisicao = array(
                'sucesso' => false,
                'erro' => 'Sem comunicação com o servidor.'
            );
        }

        return (object)$requisicao;
    }
}

?>
