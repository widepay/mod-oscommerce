<?php


chdir('../../../');
require('includes/application_top.php');


function widepay_api($wallet, $token, $local, $params = array())
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

echo "<pre>";
var_dump($HTTP_POST_VARS);
if (isset($HTTP_POST_VARS["notificacao"])) {
    $notificacao = widepay_api(intval(MODULE_PAYMENT_WIDEPAY_WALLET_ID), trim(MODULE_PAYMENT_WIDEPAY_WALLET_TOKEN), 'recebimentos/cobrancas/notificacao', array(
        'id' => $_POST["notificacao"] // ID da notificação recebido do Wide Pay via POST
    ));
    if ($notificacao->sucesso) {
        $order_id = (int)$notificacao->cobranca['referencia'];
        $order_query = tep_db_query("select orders_id, orders_status, currency, currency_value from " . TABLE_ORDERS . " where orders_id = '" . $order_id . "'");
        if (!tep_db_num_rows($order_query)) {
            exit('Pedido não encontrado');
        }
        $transactionID = $notificacao->cobranca['id'];
        $status = $notificacao->cobranca['status'];
        if ($status == 'Baixado' || $status == 'Recebido' || $status == 'Recebido manualmente') {
            $order = tep_db_fetch_array($order_query);
            $order_status_id =MODULE_PAYMENT_WIDEPAY_APPROVED_ORDER_STATUS_ID;

            tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . $order_status_id . "', last_modified = now() where orders_id = '" . (int)$order['orders_id'] . "'");

            $sql_data_array = array('orders_id' => $order['orders_id'],
                'orders_status_id' => $order_status_id,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => '');

            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            exit('Pedido Atualizado');
        }
        exit('Status Incompatível');


    } else {
        echo $notificacao->erro; // Erro
        exit('Notificação com erro');
    }
}
exit('Erro Genérico');

require('includes/application_bottom.php');
?>
